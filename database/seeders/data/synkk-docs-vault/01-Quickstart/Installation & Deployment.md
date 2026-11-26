---
title: "Installation & Deployment"
description: "Deploy your Synkk team server (VPS installer or Docker Compose) and install the Obsidian Community Plugin v1.1.1."
tags:
  - quickstart
  - deployment
  - installation
  - docker
---

# 🚀 Installation & Deployment

Synkk consists of two MIT open-source components:
1. **Synkk Server** (Laravel 13 + PHP 8.5 + Livewire 4 + Reverb + SQLite WAL): Coordinates synchronization, path ACLs, web editing, 2D Graph View, Livewire Portals, Vault AI, and Remote MCP.
2. **Synkk Team Vault Sync Plugin (`v1.1.1`)**: Runs inside Obsidian (`1.7.2+`) on macOS, Windows, Linux, iOS, and Android.

---

## ⚡ VPS Turnkey Installer (Ubuntu, Debian, AlmaLinux, Rocky)

Point a DNS record to your VPS and allow ports `80` and `443`. Clone the repository and run `install.sh` as root:

```bash
git clone --recurse-submodules https://github.com/tawandajosephmutsena/synkk.git
cd synkk
sudo SYNKK_DOMAIN=vault.example.com SYNKK_ADMIN_EMAIL=you@example.com bash install.sh
```

The installer:
- Installs Docker and Docker Compose v2 if not already present.
- Provisions automatic Let's Encrypt HTTPS certificates via Caddy (`docker-compose.install.yml`).
- Generates `/opt/synkk/.env.production` with secure `APP_KEY` and Reverb credentials.
- Bootstraps your initial Super Admin account and verifies `/up` over HTTPS.

---

## 🐳 Production Docker Compose

For manual container orchestration behind your own reverse proxy:

```bash
git clone --recurse-submodules https://github.com/tawandajosephmutsena/synkk.git
cd synkk
cp .env.production.example .env.production

# Generate APP_KEY and Reverb secrets
openssl rand -base64 32
openssl rand -hex 16
openssl rand -hex 32

# Populate APP_KEY, APP_URL, REVERB_APP_KEY, REVERB_APP_SECRET, and first-boot SYNKK_BOOTSTRAP_* values
docker compose -f docker-compose.prod.yml up -d --build
```

The all-in-one container serves HTTP on `127.0.0.1:8000`. Place an HTTPS reverse proxy in front of port `8000` (forwarding WebSocket upgrades on `/app/`), and remove `SYNKK_BOOTSTRAP_*` after your first login.

---

## 🔌 Installing the Obsidian Plugin (`v1.1.1`)

### Method 1: Obsidian Community Plugins (Recommended)
1. Open Obsidian (`1.7.2+`) on Desktop or Mobile.
2. Go to **Settings → Community plugins → Browse** (or open `obsidian://show-plugin?id=synkk-sync`).
3. Search for **Synkk Team Vault Sync**, click **Install**, and toggle it **On**.

### Method 2: Manual Installation (GitHub Releases)
1. Download `main.js`, `manifest.json`, and `styles.css` from [GitHub Releases](https://github.com/tawandajosephmutsena/synk-obsidian-plugin/releases/latest).
2. Create `<vault>/.obsidian/plugins/synkk-sync/` and copy all three files inside.
3. In Obsidian, go to **Settings → Community plugins**, reload, and enable **Synkk Team Vault Sync**.

Next: Connect your device using [[01-Quickstart/Instant 3-Second QR Pairing|Instant QR & Deep-Link Pairing]].
