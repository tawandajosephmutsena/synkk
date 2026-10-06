#!/usr/bin/env bash
# Synkk VPS installer. Run as root with a domain whose DNS points to this server.
set -euo pipefail
umask 077

INSTALL_DIR="${INSTALL_DIR:-/opt/synkk}"
SOURCE_ARCHIVE_URL="https://github.com/tawandajosephmutsena/synkk/archive/refs/heads/main.tar.gz"
COMPOSE_FILE="$INSTALL_DIR/docker-compose.install.yml"
ENV_FILE="$INSTALL_DIR/.env.production"
CADDY_FILE="$INSTALL_DIR/docker/Caddyfile.install"

fail() {
    printf '[ERROR] %s\n' "$1" >&2
    exit 1
}

info() {
    printf '[INFO] %s\n' "$1"
}

can_prompt() {
    ( : < /dev/tty ) 2>/dev/null
}

env_value() {
    awk -v name="$1" 'index($0, name "=") == 1 { print substr($0, length(name) + 2); exit }' "$ENV_FILE"
}

has_build_source() {
    [ -f "$1/Dockerfile" ] &&
        [ -f "$1/composer.json" ] &&
        [ -f "$1/package.json" ] &&
        [ -f "$1/docker/entrypoint.sh" ] &&
        [ -f "$1/docker/Caddyfile" ]
}

valid_domain() {
    local label
    local -a labels

    [[ "$1" =~ ^[a-z0-9.-]+$ ]] || return 1
    IFS='.' read -r -a labels <<< "$1"
    [ "${#labels[@]}" -ge 2 ] || return 1
    [[ "${labels[$((${#labels[@]} - 1))]}" =~ ^[a-z]{2,63}$ ]] || return 1

    for label in "${labels[@]}"; do
        [[ "$label" =~ ^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$ ]] || return 1
    done
}

if [ "$(id -u)" -ne 0 ]; then
    fail 'Run this installer as root, for example: sudo bash install.sh'
fi

[[ "$INSTALL_DIR" = /* ]] && [ "$INSTALL_DIR" != / ] || fail 'INSTALL_DIR must be an absolute directory other than /.'
[ ! -L "$INSTALL_DIR" ] || fail 'INSTALL_DIR must not be a symlink.'

for required_command in curl openssl tar; do
    command -v "$required_command" >/dev/null 2>&1 || fail "$required_command is required."
done

if ! command -v docker >/dev/null 2>&1; then
    info 'Installing Docker Engine...'
    curl -fsSL https://get.docker.com | sh
    if command -v systemctl >/dev/null 2>&1; then
        systemctl enable --now docker
    fi
fi

docker info >/dev/null 2>&1 || fail 'Docker Engine is unavailable. Start Docker and rerun the installer.'

if ! docker compose version >/dev/null 2>&1; then
    info 'Installing the Docker Compose v2 plugin...'
    if command -v apt-get >/dev/null 2>&1; then
        apt-get update -qq
        apt-get install -y -qq docker-compose-plugin
    elif command -v dnf >/dev/null 2>&1; then
        dnf install -y -q docker-compose-plugin
    elif command -v yum >/dev/null 2>&1; then
        yum install -y -q docker-compose-plugin
    else
        fail 'Install the Docker Compose v2 plugin and rerun the installer.'
    fi
fi

docker compose version >/dev/null 2>&1 || fail 'Docker Compose v2 is unavailable after installation.'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"

if ! has_build_source "$INSTALL_DIR"; then
    case "$INSTALL_DIR/" in
        "$SCRIPT_DIR/"*) fail 'INSTALL_DIR must be outside the local Synkk source directory.' ;;
    esac

    if [ -d "$INSTALL_DIR" ] && [ -n "$(find "$INSTALL_DIR" -mindepth 1 -maxdepth 1 -print -quit)" ]; then
        fail "$INSTALL_DIR contains files but is not a complete Synkk source checkout. Existing files were left untouched."
    fi

    mkdir -p "$(dirname "$INSTALL_DIR")"
    STAGING_DIR="$(mktemp -d "$(dirname "$INSTALL_DIR")/.synkk-install.XXXXXX")"
    ARCHIVE_PATH=''
    trap 'rm -rf "$STAGING_DIR"; if [ -n "$ARCHIVE_PATH" ]; then rm -f "$ARCHIVE_PATH"; fi' EXIT

    if has_build_source "$SCRIPT_DIR"; then
        info 'Copying local Synkk source into the installation directory...'
        tar -C "$SCRIPT_DIR" \
            --exclude='./.git' \
            --exclude='./vendor' \
            --exclude='./node_modules' \
            --exclude='./outputs' \
            --exclude='./videos' \
            --exclude='./.env' \
            --exclude='./.env.*' \
            --exclude='./auth.json' \
            --exclude='./.npmrc' \
            --exclude='./.aws' \
            --exclude='./.ssh' \
            --exclude='./.codex' \
            --exclude='./.agents' \
            --exclude='./docker-compose.install.yml' \
            --exclude='./docker/Caddyfile.install' \
            --exclude='./database/*.sqlite*' \
            --exclude='./storage/app/*' \
            --exclude='./storage/framework/cache/*' \
            --exclude='./storage/framework/sessions/*' \
            --exclude='./storage/framework/testing/*' \
            --exclude='./storage/framework/views/*' \
            --exclude='./storage/logs/*' \
            --exclude='./storage/*.key' \
            -cf - . | tar -C "$STAGING_DIR" -xf -
    else
        info 'Downloading complete Synkk source from GitHub...'
        ARCHIVE_PATH="$(mktemp)"
        curl -fsSL --retry 3 "$SOURCE_ARCHIVE_URL" -o "$ARCHIVE_PATH"
        tar -tzf "$ARCHIVE_PATH" >/dev/null
        tar -xzf "$ARCHIVE_PATH" -C "$STAGING_DIR" --strip-components=1
        if [ -e "$STAGING_DIR/.env" ] || [ -e "$STAGING_DIR/.env.production" ]; then
            fail 'The source archive unexpectedly contains an environment file; no installation was started.'
        fi
    fi

    has_build_source "$STAGING_DIR" || fail 'The downloaded source is incomplete; no installation was started.'

    if [ -d "$INSTALL_DIR" ]; then
        rmdir "$INSTALL_DIR" || fail "$INSTALL_DIR changed during setup; existing files were left untouched."
    fi
    mv "$STAGING_DIR" "$INSTALL_DIR"
    trap - EXIT
    if [ -n "$ARCHIVE_PATH" ]; then
        rm -f "$ARCHIVE_PATH"
    fi
else
    info "Using existing Synkk source in $INSTALL_DIR."
fi

if [ ! -f "$INSTALL_DIR/.dockerignore" ] ||
    ! grep -Fxq '.env.*' "$INSTALL_DIR/.dockerignore" ||
    grep -Fxq '!.env.production' "$INSTALL_DIR/.dockerignore"; then
    fail 'The source .dockerignore must exclude .env.production before a production build.'
fi

FRESH_ENV=false
if [ -f "$ENV_FILE" ]; then
    SYNKK_DOMAIN="$(env_value SYNKK_DOMAIN)"
    APP_URL="$(env_value APP_URL)"
    CADDY_EMAIL="$(env_value CADDY_EMAIL)"
    SYNKK_ADMIN_EMAIL="$(env_value SYNKK_BOOTSTRAP_EMAIL)"
    [ -n "$SYNKK_DOMAIN" ] || fail "Existing $ENV_FILE has no SYNKK_DOMAIN."
    [ "$APP_URL" = "https://$SYNKK_DOMAIN" ] || fail "Existing $ENV_FILE must set APP_URL=https://$SYNKK_DOMAIN."
    APP_KEY="$(env_value APP_KEY)"
    [ -n "$APP_KEY" ] && [ "$APP_KEY" != 'base64:GENERATE_ME_USING_INSTALL_SCRIPT' ] || fail "Existing $ENV_FILE needs a valid APP_KEY."
    [ "$(env_value DB_DATABASE)" = '/var/www/html/storage/app/database.sqlite' ] ||
        fail "Existing $ENV_FILE must use DB_DATABASE=/var/www/html/storage/app/database.sqlite."
    [ "$(env_value TRUSTED_PROXIES)" = '*' ] ||
        fail "Existing $ENV_FILE must set TRUSTED_PROXIES=* for the private Caddy proxy."
    info 'Preserving existing application key, configuration, and accounts.'
elif [ -e "$ENV_FILE" ]; then
    fail "$ENV_FILE exists but is not a regular file."
else
    FRESH_ENV=true
    SYNKK_DOMAIN="${SYNKK_DOMAIN:-}"
    if [ -z "$SYNKK_DOMAIN" ] && can_prompt; then
        read -r -p 'Public DNS name for Synkk (example: vault.example.com): ' SYNKK_DOMAIN < /dev/tty
    fi
    [ -n "$SYNKK_DOMAIN" ] || fail 'Set SYNKK_DOMAIN to a public DNS name before installing.'
    SYNKK_DOMAIN="$(printf '%s' "$SYNKK_DOMAIN" | tr '[:upper:]' '[:lower:]')"
    valid_domain "$SYNKK_DOMAIN" || fail 'SYNKK_DOMAIN must be a valid public DNS name, without a scheme, port, or path.'

    SYNKK_ADMIN_EMAIL="${SYNKK_ADMIN_EMAIL:-}"
    if [ -z "$SYNKK_ADMIN_EMAIL" ] && can_prompt; then
        read -r -p 'Administrator email address: ' SYNKK_ADMIN_EMAIL < /dev/tty
    fi
    [[ "$SYNKK_ADMIN_EMAIL" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,63}$ ]] ||
        fail 'Set SYNKK_ADMIN_EMAIL to a valid email address.'

    SYNKK_ADMIN_NAME="${SYNKK_ADMIN_NAME:-Administrator}"
    [[ "$SYNKK_ADMIN_NAME" =~ ^[A-Za-z0-9][A-Za-z0-9._\ -]{0,254}$ ]] ||
        fail 'SYNKK_ADMIN_NAME may contain only letters, numbers, spaces, periods, underscores, and hyphens.'

    SYNKK_ADMIN_PASSWORD="${SYNKK_ADMIN_PASSWORD:-}"
    if [ -z "$SYNKK_ADMIN_PASSWORD" ] && can_prompt; then
        read -r -s -p 'Administrator password (leave blank to generate one): ' SYNKK_ADMIN_PASSWORD < /dev/tty
        printf '\n' > /dev/tty
    fi
    if [ -z "$SYNKK_ADMIN_PASSWORD" ]; then
        SYNKK_ADMIN_PASSWORD="$(openssl rand -hex 24)aA1!"
    fi
    [[ "$SYNKK_ADMIN_PASSWORD" =~ ^[A-Za-z0-9!@#%^*()_+=:,.?-]{12,128}$ ]] &&
        [[ "$SYNKK_ADMIN_PASSWORD" =~ [a-z] ]] &&
        [[ "$SYNKK_ADMIN_PASSWORD" =~ [A-Z] ]] &&
        [[ "$SYNKK_ADMIN_PASSWORD" =~ [0-9] ]] &&
        [[ "$SYNKK_ADMIN_PASSWORD" =~ [^[:alnum:]] ]] ||
        fail 'Administrator password must be 12-128 characters with upper/lowercase, a number, and a symbol. Avoid spaces, dollar signs, quotes, and backslashes.'

    APP_URL="https://$SYNKK_DOMAIN"
    CADDY_EMAIL="$SYNKK_ADMIN_EMAIL"
    APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
    REVERB_APP_KEY="$(openssl rand -hex 16)"
    REVERB_APP_SECRET="$(openssl rand -hex 32)"

    cat > "$ENV_FILE" <<EOF
APP_NAME="Synkk Vault Sync"
APP_ENV=production
APP_DEBUG=false
APP_KEY=$APP_KEY
APP_URL=$APP_URL
TRUSTED_PROXIES=*

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=info

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/storage/app/database.sqlite
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

SYNKK_STORAGE_DISK=local
FILESYSTEM_DISK=local

BROADCAST_CONNECTION=reverb
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_APP_ID=synkk-vps-app
REVERB_APP_KEY=$REVERB_APP_KEY
REVERB_APP_SECRET=$REVERB_APP_SECRET

SYNKK_DOMAIN=$SYNKK_DOMAIN
CADDY_EMAIL=$CADDY_EMAIL
SYNKK_ENFORCE_LICENSE=false

SYNKK_BOOTSTRAP_EMAIL=$SYNKK_ADMIN_EMAIL
SYNKK_BOOTSTRAP_PASSWORD="$SYNKK_ADMIN_PASSWORD"
SYNKK_BOOTSTRAP_NAME="$SYNKK_ADMIN_NAME"
EOF
    chmod 600 "$ENV_FILE"
    INITIAL_PASSWORD="$SYNKK_ADMIN_PASSWORD"
    unset SYNKK_ADMIN_PASSWORD
fi

valid_domain "$SYNKK_DOMAIN" || fail "SYNKK_DOMAIN in $ENV_FILE is invalid."
[ -n "$CADDY_EMAIL" ] || fail "CADDY_EMAIL in $ENV_FILE is required."

if [ -f "$COMPOSE_FILE" ]; then
    grep -q '^# Generated by Synkk VPS installer' "$COMPOSE_FILE" ||
        fail "$COMPOSE_FILE was not generated by this installer; existing file was left untouched."
    grep -Fq "SYNKK_DOMAIN: \"$SYNKK_DOMAIN\"" "$COMPOSE_FILE" ||
        fail "$COMPOSE_FILE has a different domain than $ENV_FILE."
elif [ -e "$COMPOSE_FILE" ]; then
    fail "$COMPOSE_FILE exists but is not a regular file."
else
    cat > "$COMPOSE_FILE" <<EOF
# Generated by Synkk VPS installer. Keep this file with .env.production.
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    image: synkk/app:latest
    restart: unless-stopped
    expose:
      - "80"
    env_file:
      - .env.production
    volumes:
      - synkk_storage:/var/www/html/storage
    healthcheck:
      test: ["CMD", "curl", "-fsS", "http://127.0.0.1/up"]
      interval: 10s
      timeout: 5s
      start_period: 90s
      retries: 6

  caddy:
    image: caddy:2-alpine
    restart: unless-stopped
    depends_on:
      app:
        condition: service_healthy
    environment:
      SYNKK_DOMAIN: "$SYNKK_DOMAIN"
      CADDY_EMAIL: "$CADDY_EMAIL"
    ports:
      - "80:80"
      - "443:443"
      - "443:443/udp"
    volumes:
      - ./docker/Caddyfile.install:/etc/caddy/Caddyfile:ro
      - caddy_data:/data
      - caddy_config:/config

volumes:
  synkk_storage:
  caddy_data:
  caddy_config:
EOF
fi

if [ ! -f "$CADDY_FILE" ]; then
    sed -e 's/synkk:8080/app:80/g' -e 's/synkk:80/app:80/g' \
        "$INSTALL_DIR/docker/Caddyfile" > "$CADDY_FILE"
    chmod 644 "$CADDY_FILE"
fi
if grep -Eq 'reverse_proxy (synkk:|app:8080)' "$CADDY_FILE"; then
    fail "$CADDY_FILE has an unreachable upstream. Route both HTTP and WebSocket traffic to app:80."
fi

info 'Checking Compose configuration...'
docker compose -f "$COMPOSE_FILE" config --quiet

info 'Building and starting Synkk and the HTTPS proxy...'
docker compose -f "$COMPOSE_FILE" up -d --build

info 'Waiting for the app health check...'
APP_HEALTH=''
APP_CONTAINER_ID=''
for attempt in $(seq 1 90); do
    APP_CONTAINER_ID="$(docker compose -f "$COMPOSE_FILE" ps -q app 2>/dev/null || true)"
    if [ -n "$APP_CONTAINER_ID" ]; then
        APP_HEALTH="$(docker inspect --format '{{.State.Health.Status}}' "$APP_CONTAINER_ID" 2>/dev/null || true)"
    fi
    [ "$APP_HEALTH" = healthy ] && break
    [ "$APP_HEALTH" = unhealthy ] && fail 'Synkk app is unhealthy. Inspect docker compose logs app.'
    sleep 2
done
[ "$APP_HEALTH" = healthy ] || fail 'Synkk app did not become healthy. Inspect docker compose logs app.'

info 'Verifying initial administrator bootstrap...'
docker compose -f "$COMPOSE_FILE" exec -T -u www-data app \
    php artisan synkk:bootstrap-admin --if-empty --no-interaction ||
    fail 'Administrator bootstrap failed. Check .env.production and docker compose logs app.'

info 'Waiting for valid local HTTPS and the /up endpoint...'
HTTPS_READY=false
for attempt in $(seq 1 60); do
    if curl --noproxy '*' --resolve "$SYNKK_DOMAIN:443:127.0.0.1" \
        --fail --silent --max-time 5 "https://$SYNKK_DOMAIN/up" > /dev/null; then
        HTTPS_READY=true
        break
    fi
    sleep 2
done
[ "$HTTPS_READY" = true ] ||
    fail "HTTPS is not ready for $SYNKK_DOMAIN. Check DNS, inbound ports 80/443, and docker compose logs caddy."

curl --noproxy '*' --fail --silent --max-time 10 "$APP_URL/up" > /dev/null ||
    fail "The public HTTPS address $APP_URL is not reachable from this server. Check DNS and inbound ports 80/443."

printf '\nSynkk is online at %s\n' "$APP_URL"
printf 'Sync API: %s/api/v1\n' "$APP_URL"
printf 'Installation: %s\n' "$INSTALL_DIR"

if [ "$FRESH_ENV" = true ] &&
    docker logs "$APP_CONTAINER_ID" 2>&1 |
        grep -F "Administrator [$SYNKK_ADMIN_EMAIL] provisioned successfully." > /dev/null; then
    printf 'Administrator email: %s\n' "$SYNKK_ADMIN_EMAIL"
    printf 'Initial administrator password: %s\n' "$INITIAL_PASSWORD"
else
    printf 'Use the existing administrator credentials. Initial bootstrap values are stored in %s.\n' "$ENV_FILE"
fi

printf '\nInstall the Synkk Community Plugin in Obsidian and set its server URL to %s/api/v1.\n' "$APP_URL"
printf 'Management: cd %s && docker compose -f docker-compose.install.yml logs -f\n' "$INSTALL_DIR"
