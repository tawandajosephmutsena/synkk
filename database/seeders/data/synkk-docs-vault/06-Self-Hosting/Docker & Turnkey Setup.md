---
title: "Docker & Turnkey Setup"
description: "Production self-hosting architecture: single-container Nginx + PHP 8.5-FPM + Laravel 13 + Reverb + SQLite WAL + Caddy HTTPS."
tags:
  - self-hosting
  - docker
  - caddy
  - sysadmin
---

# 🐳 Docker & Turnkey Production Setup

Synkk is packaged as a single self-contained Docker image (`php:8.5.10-fpm-alpine3.24` + Nginx + Supervisord) backed by embedded **SQLite WAL + FTS5**, minimizing operational overhead on any VPS or homelab.

---

## 🏗️ Production Architecture

```
Internet / Obsidian Clients / Web Browsers / MCP Clients
               │
               ▼ (Ports 80 / 443)
┌────────────────────────────────────────────────────────────┐
│            CADDY / YOUR HTTPS REVERSE PROXY                │
│  ├── Automatic Let's Encrypt TLS                           │
│  └── Forwards HTTP & WebSocket Upgrades (/app/) -> :8000   │
└──────────────────────────────┬─────────────────────────────┘
                               │ (127.0.0.1:8000 -> container :80)
┌──────────────────────────────▼─────────────────────────────┐
│            SYNKK ALL-IN-ONE CONTAINER (Supervisord)        │
│  ├── Nginx (110M max body, proxies /app/ -> Reverb :8080)  │
│  ├── PHP 8.5-FPM (Laravel 13 + Livewire 4)                 │
│  ├── Laravel Reverb WebSockets (127.0.0.1:8080)            │
│  ├── Queue Worker (ai-stream, default, ai-background)      │
│  └── Laravel Scheduler (digests, gardener, pruning)        │
│                                                            │
│  Persistent Volume: /var/www/html/storage                  │
│  ├── storage/app/database.sqlite (SQLite WAL + FTS5)       │
│  └── storage/app/private/vaults/ (or optional S3 disk)     │
└────────────────────────────────────────────────────────────┘
```

---

## ⚙️ Key Environment Variables (`.env.production`)

| Variable | Description | Example / Default |
| :--- | :--- | :--- |
| `APP_KEY` | Required 32-byte encryption key (entrypoint fails hard if invalid) | `base64:...` (`openssl rand -base64 32`) |
| `APP_URL` | Canonical public HTTPS URL of your Synkk server | `https://vault.example.com` |
| `DB_CONNECTION` | Database driver (`sqlite` enables FTS5 hybrid search) | `sqlite` |
| `QUEUE_CONNECTION` | Asynchronous queue driver | `database` |
| `BROADCAST_CONNECTION` | Live WebSocket broadcaster | `reverb` |
| `REVERB_APP_KEY` / `SECRET` | Unique Reverb WebSocket credentials | `openssl rand -hex 16` / `32` |
| `SYNKK_STORAGE_DISK` | Vault blob disk (`local` or `s3`) | `local` |
| `SYNKK_BOOTSTRAP_EMAIL` / `PASSWORD` | First-boot Super Admin creation (remove after first login) | Set on initial start only |

---

## 💾 Backups & Disaster Recovery

Everything persistent lives inside the Docker volume mounted at `/var/www/html/storage` (unless using `SYNKK_STORAGE_DISK=s3`):
1. **Database:** Take a consistent online backup of `/var/www/html/storage/app/database.sqlite` using `sqlite3 /var/www/html/storage/app/database.sqlite ".backup '/backup/synkk.sqlite'"`.
2. **Vault Files & Versions:** Back up `/var/www/html/storage/app/private/` (or replicate your S3 bucket).
3. **Configuration:** Store `/opt/synkk/.env.production` (especially `APP_KEY`) securely in your secrets manager.

Next: Explore the complete API in [[07-API-Reference/REST API Protocol v2|REST API Protocol v2]].
