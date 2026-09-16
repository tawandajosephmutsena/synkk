---
title: "Installation & Deployment"
description: "Deploy your Synkk team server and install the Obsidian plugin."
tags:
  - quickstart
  - deployment
  - installation
  - docker
---

# 🚀 Installation & Deployment

Synkk consists of two interconnected components:
1. **Synkk Server**: The backend coordinating synchronization, permissions, team vaults, CRDT rooms, vector indexing, and Livewire portals.
2. **Synkk Obsidian Plugin**: The community-style plugin operating inside your Obsidian vault.

---

## ⚡ 1-Line Turnkey Server Installer

On any Ubuntu, Debian, AlmaLinux, or Rocky Linux VPS, run:

```bash
curl -sSL https://synkk.space/install.sh | bash
```

This turnkey installer handles everything automatically:
- Installs Docker and Docker Compose (if not already present).
- Provisions automatic Let's Encrypt SSL/TLS certificates via Caddy.
- Configures background workers, Redis, and WebSockets (Laravel Reverb).
- Prompts you to bootstrap your administrator account in under 3 minutes.

---

## 🐳 Docker Compose Deployment

If you prefer manual container orchestration:

```bash
# 1. Download production stack configuration
curl -sSL https://raw.githubusercontent.com/tawandajosephmutsena/synkk/main/docker-compose.prod.yml -o docker-compose.yml
curl -sSL https://raw.githubusercontent.com/tawandajosephmutsena/synkk/main/docker/Caddyfile -o Caddyfile
curl -sSL https://raw.githubusercontent.com/tawandajosephmutsena/synkk/main/.env.production.example -o .env

# 2. Update .env with your domain and database credentials
nano .env

# 3. Start the container stack
docker compose up -d
```

---

## 🔌 Installing the Obsidian Plugin

### Manual Installation (From Releases)
1. Download the latest release (`main.js`, `manifest.json`, `styles.css`) from [GitHub Releases](https://github.com/tawandajosephmutsena/synk-obsidian-plugin/releases/latest).
2. Inside your Obsidian vault, navigate to `.obsidian/plugins/`.
3. Create a new directory: `.obsidian/plugins/synkk-sync/`.
4. Copy the three files into that directory.
5. In Obsidian, go to **Settings → Community plugins**, reload installed plugins, and toggle **Synkk Sync** to ON.

Next: Connect your device using [[01-Quickstart/Instant 3-Second QR Pairing|3-Second Instant QR Pairing]].
