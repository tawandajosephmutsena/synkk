---
title: "Docker & Turnkey Setup"
description: "Production self-hosting guide, Docker Compose stack, Caddy reverse proxy, and maintenance."
tags:
  - self-hosting
  - docker
  - caddy
  - sysadmin
---

# 🐳 Docker & Turnkey Production Setup

Synkk is built with modern container standards for minimal operational overhead.

---

## 🏗️ Production Architecture

```
Internet / Obsidian Clients / Web Browsers
               │
               ▼ (Ports 80 / 443)
┌────────────────────────────────────────────────────────────┐
│                      CADDY PROXY                           │
│  ├── Automatic Let's Encrypt SSL/TLS                       │
│  ├── HTTP/3 & Gzip/Zstd Compression                        │
│  └── WebSocket Upgrade for /app /reverb                     │
└──────────────────────────────┬─────────────────────────────┘
                               │
               ┌───────────────┴───────────────┐
               ▼                               ▼
      PHP 8.5 FrankenPHP / FPM        Laravel Reverb (WS)
               │                               │
               ├───────────────┬───────────────┤
               ▼               ▼               ▼
          PostgreSQL         Redis         Local / S3
          (DB & Vector)     (Queue)        (File Storage)
```

---

## ⚙️ Key Environment Variables

| Variable | Description | Recommended Default |
| :--- | :--- | :--- |
| `APP_URL` | Canonical HTTPS URL of your Synkk server | `https://synkk.yourdomain.com` |
| `APP_ENV` | Application environment | `production` |
| `DB_CONNECTION` | Database engine | `pgsql` (or `mysql`) |
| `QUEUE_CONNECTION` | Asynchronous queue driver | `redis` |
| `BROADCAST_CONNECTION` | Live WebSocket broadcaster | `reverb` |
| `REVERB_APP_KEY` | Public WebSocket connection key | 16-character alphanumeric |

---

## 💾 Backups & Disaster Recovery

All state in Synkk resides in:
1. **The Database**: Run daily `pg_dump synkk_prod > backup.sql`.
2. **Storage Directory**: Backup `storage/app/vaults/` (or your S3 bucket).
3. **Restoration**: To restore to new hardware, launch the compose stack and import `backup.sql`.

Next: Explore the complete developer API with [[07-API-Reference/REST API Protocol v2|REST API Protocol v2]].
