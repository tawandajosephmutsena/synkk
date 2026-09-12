#!/usr/bin/env bash
# ==============================================================================
# Synkk Turnkey 1-Click Installer
# Self-Hosted Obsidian Team Sync Engine
# https://synkk.space · https://github.com/tawandajosephmutsena/synkk
# ==============================================================================

set -euo pipefail

# Text formatting
BOLD="\033[1m"
GREEN="\033[0;32m"
BLUE="\033[0;34m"
YELLOW="\033[1;33m"
RED="\033[0;31m"
CYAN="\033[0;36m"
NC="\033[0m"

# Default paths and repository
INSTALL_DIR="${INSTALL_DIR:-/opt/synkk}"
REPO_URL="https://raw.githubusercontent.com/tawandajosephmutsena/synkk/main"

# Print banner
echo -e "${CYAN}"
cat << "EOF"
  ███████╗██╗   ██╗███╗   ██╗██╗  ██╗██╗  ██╗
  ██╔════╝╚██╗ ██╔╝████╗  ██║██║ ██╔╝██║ ██╔╝
  ███████╗ ╚████╔╝ ██╔██╗ ██║█████╔╝ █████╔╝ 
  ╚════██║  ╚██╔╝  ██║╚██╗██║██╔═██╗ ██╔═██╗ 
  ███████║   ██║   ██║ ╚████║██║  ██╗██║  ██╗
  ╚══════╝   ╚═╝   ╚═╝  ╚═══╝╚═╝  ╚═╝╚═╝  ╚═╝
EOF
echo -e "${BOLD}  Self-Hosted Obsidian Team Sync & Web Vault${NC}"
echo -e "${BLUE}  https://synkk.space${NC}\n"

# 1. Pre-flight checks: Root / Sudo check
if [ "$(id -u)" -ne 0 ]; then
    echo -e "${RED}[ERROR] This installer must be run as root or with sudo privileges.${NC}"
    echo -e "Please run: ${BOLD}sudo bash install.sh${NC}"
    exit 1
fi

# 2. Check architecture
ARCH="$(uname -m)"
if [ "$ARCH" != "x86_64" ] && [ "$ARCH" != "aarch64" ] && [ "$ARCH" != "arm64" ]; then
    echo -e "${YELLOW}[WARNING] Architecture '$ARCH' detected. Official builds are tested on x86_64 and arm64.${NC}"
fi

# 3. Detect and install Docker & Docker Compose if missing
if ! command -v docker >/dev/null 2>&1; then
    echo -e "${YELLOW}[INFO] Docker not found. Installing official Docker CE...${NC}"
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL https://get.docker.com | sh
    elif command -v wget >/dev/null 2>&1; then
        wget -qO- https://get.docker.com | sh
    else
        echo -e "${RED}[ERROR] Neither curl nor wget was found. Please install curl and re-run.${NC}"
        exit 1
    fi

    systemctl enable --now docker || true
    echo -e "${GREEN}[OK] Docker installed successfully.${NC}"
else
    echo -e "${GREEN}[OK] Docker is already installed.$(docker --version)${NC}"
fi

# Check Docker Compose v2 plugin
if ! docker compose version >/dev/null 2>&1; then
    echo -e "${YELLOW}[INFO] Docker Compose plugin not found. Installing docker-compose-plugin...${NC}"
    if [ -f /etc/debian_version ]; then
        apt-get update -qq && apt-get install -y -qq docker-compose-plugin
    elif [ -f /etc/redhat-release ]; then
        dnf install -y -q docker-compose-plugin || yum install -y -q docker-compose-plugin
    fi
    echo -e "${GREEN}[OK] Docker Compose plugin configured.${NC}"
fi

# 4. Determine domain & admin credentials
echo -e "\n${BOLD}--- Setup Configuration ---${NC}"

# Detect Public IP
SERVER_IP=$(curl -s -4 ifconfig.me || curl -s -4 api.ipify.org || echo "localhost")

# Interactive prompt if variables are not preset
if [ -t 0 ] && [ -z "${SYNKK_DOMAIN:-}" ]; then
    read -rp "$(echo -e "${BOLD}Enter domain name for Synkk (e.g. vault.yourdomain.com or press enter for '${SERVER_IP}'): ${NC}")" INPUT_DOMAIN
    SYNKK_DOMAIN="${INPUT_DOMAIN:-$SERVER_IP}"
else
    SYNKK_DOMAIN="${SYNKK_DOMAIN:-$SERVER_IP}"
fi

if [ -t 0 ] && [ -z "${SYNKK_ADMIN_EMAIL:-}" ]; then
    read -rp "$(echo -e "${BOLD}Enter Administrator Email (e.g. admin@yourdomain.com): ${NC}")" INPUT_EMAIL
    SYNKK_ADMIN_EMAIL="${INPUT_EMAIL:-admin@synkk.space}"
else
    SYNKK_ADMIN_EMAIL="${SYNKK_ADMIN_EMAIL:-admin@synkk.space}"
fi

if [ -z "${SYNKK_ADMIN_PASSWORD:-}" ]; then
    # Generate high-entropy password if not provided
    if [ -t 0 ]; then
        read -s -rp "$(echo -e "${BOLD}Enter Administrator Password (press enter to auto-generate): ${NC}")" INPUT_PASS
        echo ""
        if [ -n "$INPUT_PASS" ]; then
            SYNKK_ADMIN_PASSWORD="$INPUT_PASS"
        else
            SYNKK_ADMIN_PASSWORD="$(openssl rand -hex 12)"
        fi
    else
        SYNKK_ADMIN_PASSWORD="$(openssl rand -hex 12)"
    fi
fi

SYNKK_ADMIN_NAME="${SYNKK_ADMIN_NAME:-Administrator}"

# Determine protocol
if [[ "$SYNKK_DOMAIN" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] || [ "$SYNKK_DOMAIN" = "localhost" ]; then
    APP_URL="http://${SYNKK_DOMAIN}"
    CADDY_DOMAIN="http://${SYNKK_DOMAIN}"
else
    APP_URL="https://${SYNKK_DOMAIN}"
    CADDY_DOMAIN="${SYNKK_DOMAIN}"
fi

echo -e "${CYAN}Target URL:${NC} ${APP_URL}"
echo -e "${CYAN}Admin Email:${NC} ${SYNKK_ADMIN_EMAIL}"
echo -e "${CYAN}Install Directory:${NC} ${INSTALL_DIR}"

# 5. Create deployment directory and download configuration files
mkdir -p "${INSTALL_DIR}/docker"

# Copy local files if running inside synkk repo; otherwise download from GitHub
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
if [ -f "${SCRIPT_DIR}/docker-compose.prod.yml" ] && [ -f "${SCRIPT_DIR}/docker/Caddyfile" ]; then
    echo -e "${BLUE}[INFO] Using local repository assets...${NC}"
    cp "${SCRIPT_DIR}/docker-compose.prod.yml" "${INSTALL_DIR}/docker-compose.yml"
    cp "${SCRIPT_DIR}/docker/Caddyfile" "${INSTALL_DIR}/docker/Caddyfile"
    if [ -f "${SCRIPT_DIR}/Dockerfile" ]; then
        cp -r "${SCRIPT_DIR}/"* "${INSTALL_DIR}/" 2>/dev/null || true
    fi
else
    echo -e "${BLUE}[INFO] Fetching production configuration from GitHub...${NC}"
    curl -fsSL "${REPO_URL}/docker-compose.prod.yml" -o "${INSTALL_DIR}/docker-compose.yml"
    curl -fsSL "${REPO_URL}/docker/Caddyfile" -o "${INSTALL_DIR}/docker/Caddyfile"
fi

# 6. Generate cryptographic secrets and .env file
cd "${INSTALL_DIR}"

APP_KEY="base64:$(openssl rand -base64 32)"
REVERB_APP_KEY="$(openssl rand -hex 10)"
REVERB_APP_SECRET="$(openssl rand -hex 16)"

cat > "${INSTALL_DIR}/.env" << EOF
APP_NAME="Synkk Vault Sync"
APP_ENV=production
APP_DEBUG=false
APP_KEY=${APP_KEY}
APP_URL=${APP_URL}

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=info

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

SYNKK_STORAGE_DISK=local
FILESYSTEM_DISK=local

BROADCAST_CONNECTION=reverb
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_HOST=${SYNKK_DOMAIN}
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_APP_ID=synkk-prod-app
REVERB_APP_KEY=${REVERB_APP_KEY}
REVERB_APP_SECRET=${REVERB_APP_SECRET}

SYNKK_DOMAIN=${CADDY_DOMAIN}
CADDY_EMAIL=${SYNKK_ADMIN_EMAIL}
SYNKK_ENFORCE_LICENSE=false
EOF

chmod 600 "${INSTALL_DIR}/.env"

# 7. Pull & Launch Docker Containers
echo -e "\n${BOLD}[INFO] Launching Synkk production containers...${NC}"
docker compose -f "${INSTALL_DIR}/docker-compose.yml" down 2>/dev/null || true
docker compose -f "${INSTALL_DIR}/docker-compose.yml" up -d --build

# 8. Wait for Healthcheck
echo -e "${BLUE}[INFO] Waiting for service healthcheck to pass...${NC}"
ATTEMPTS=0
MAX_ATTEMPTS=30
HEALTHY=false

while [ $ATTEMPTS -lt $MAX_ATTEMPTS ]; do
    if docker compose -f "${INSTALL_DIR}/docker-compose.yml" ps | grep -q "healthy"; then
        HEALTHY=true
        break
    fi
    ATTEMPTS=$((ATTEMPTS + 1))
    sleep 2
done

if [ "$HEALTHY" = false ]; then
    echo -e "${YELLOW}[WARNING] Container started, proceeding with database initialization...${NC}"
    sleep 5
fi

# 9. Provision SuperAdmin User headlessly
echo -e "${BLUE}[INFO] Bootstrapping Administrator account...${NC}"
docker compose -f "${INSTALL_DIR}/docker-compose.yml" exec -T synkk php artisan synkk:bootstrap-admin \
    --email="${SYNKK_ADMIN_EMAIL}" \
    --password="${SYNKK_ADMIN_PASSWORD}" \
    --name="${SYNKK_ADMIN_NAME}" || true

# 10. Final Completion Banner
echo -e "\n${GREEN}===================================================================${NC}"
echo -e "${BOLD}${GREEN}        🚀 Synkk Vault Sync Server is Ready and Online!           ${NC}"
echo -e "${GREEN}===================================================================${NC}"
echo -e "  ${BOLD}Dashboard URL:${NC}         ${CYAN}${APP_URL}${NC}"
echo -e "  ${BOLD}Sync API Endpoint:${NC}     ${CYAN}${APP_URL}/api/v1${NC}"
echo -e "  ${BOLD}Admin Email:${NC}           ${YELLOW}${SYNKK_ADMIN_EMAIL}${NC}"
echo -e "  ${BOLD}Admin Password:${NC}        ${YELLOW}${SYNKK_ADMIN_PASSWORD}${NC}"
echo -e "  ${BOLD}Installation Path:${NC}     ${INSTALL_DIR}"
echo -e "${GREEN}-------------------------------------------------------------------${NC}"
echo -e "${BOLD}📱 Connect Obsidian Client:${NC}"
echo -e "  1. In Obsidian, open ${BOLD}Settings > Community Plugins${NC} and enable ${BOLD}BRAT${NC}."
echo -e "  2. Add Beta Plugin: ${CYAN}tawandajosephmutsena/synk-obsidian-plugin${NC}"
echo -e "  3. In Synkk Plugin Settings, enter Server URL: ${CYAN}${APP_URL}/api/v1${NC}"
echo -e "  4. Generate a Device Token in your Synkk Dashboard under ${BOLD}/devices${NC}."
echo -e "${GREEN}-------------------------------------------------------------------${NC}"
echo -e "${BOLD}🛠️ Server Management Commands:${NC}"
echo -e "  View live logs:        ${CYAN}cd ${INSTALL_DIR} && docker compose logs -f${NC}"
echo -e "  Restart services:      ${CYAN}cd ${INSTALL_DIR} && docker compose restart${NC}"
echo -e "  Stop services:         ${CYAN}cd ${INSTALL_DIR} && docker compose down${NC}"
echo -e "${GREEN}===================================================================${NC}\n"
