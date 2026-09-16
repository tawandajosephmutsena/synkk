# Synkk

<p align="center">
  <img src="public/images/synkk-logo.svg" alt="Synkk - Obsidian everywhere" width="360">
</p>

<p align="center">
  <strong>Self-hosted team vault sync for Obsidian.</strong><br>
  Keep Markdown, permissions, versions, and trusted devices together on infrastructure you control.
</p>

<p align="center">
  <a href="https://synkk.space">Website</a> ·
  <a href="https://synkk.space/documentation">Docs</a> ·
  <a href="https://github.com/tawandajosephmutsena/synk-obsidian-plugin/releases/latest">Plugin latest release</a> ·
  <a href="https://github.com/tawandajosephmutsena/synk-obsidian-plugin">Obsidian plugin repo</a> ·
  <a href="https://ottomate.space">Ottomate</a>
</p>

![Synkk dashboard overview](public/images/showcase/dashboard-overview.webp)

Synkk is the web server and dashboard for a controlled Obsidian sync workflow. The Foundation release is built for people who want a private team vault, path-aware access, reliable whole-file sync, clear version history, and a safer launch path before the advanced sync roadmap lands.

## ⚡ 1-Click Deployment

Deploy your private Synkk team vault sync server instantly:

<p align="left">
  <a href="https://render.com/deploy?repo=https://github.com/tawandajosephmutsena/synkk">
    <img src="https://render.com/images/deploy-to-render-button.svg" alt="Deploy to Render" height="30">
  </a>
  <a href="https://railway.app/new/template?template=https://github.com/tawandajosephmutsena/synkk">
    <img src="https://railway.app/button.svg" alt="Deploy on Railway" height="30">
  </a>
  <a href="https://synkk.space/documentation">
    <img src="https://elest.io/images/deploy-on-elestio-btn.svg" alt="Deploy on Elestio" height="30">
  </a>
</p>

### 🚀 1-Line Turnkey Installer (Ubuntu, Debian, AlmaLinux, Rocky, Any VPS)

Run this single command on your server to automatically install Docker, provision Let's Encrypt SSL/TLS via Caddy, configure queue workers and WebSockets, and bootstrap your superadmin account in under 3 minutes:

```bash
curl -sSL https://synkk.space/install.sh | bash
```

### 🐳 Run With Docker Compose

```bash
# 1. Download production compose file & Caddy configuration
curl -sSL https://raw.githubusercontent.com/tawandajosephmutsena/synkk/main/docker-compose.prod.yml -o docker-compose.yml
curl -sSL https://raw.githubusercontent.com/tawandajosephmutsena/synkk/main/docker/Caddyfile -o Caddyfile
curl -sSL https://raw.githubusercontent.com/tawandajosephmutsena/synkk/main/.env.production.example -o .env

# 2. Launch production container stack with automated HTTPS
docker compose up -d
```

## Product Screens

| Dashboard | Markdown Editor |
| --- | --- |
| ![Synkk dashboard showing vault health, sync events, and activity](public/images/showcase/dashboard-overview.webp) | ![Synkk Markdown editor showing notes, source mode, outline, and preview](public/images/showcase/editor-full.webp) |

| Graph View | Permissions Matrix |
| --- | --- |
| ![Synkk graph view showing linked notes and graph navigation](public/images/showcase/graph-full.webp) | ![Synkk permissions matrix showing member path access rules](public/images/showcase/permissions-full.webp) |

## What Ships Now

- **Instant 3-Second QR Mobile Pairing:** Pair any iOS or Android device in seconds using camera scanning or `obsidian://synkk-pair` deep link protocols without manual key entry.
- **Interactive Livewire Vault Portals:** Publish any team vault as a live, interactive web portal at `/p/{slug}` in real time without static site builders or node scripts. Includes 4 design presets (Obsidian Clean, Enterprise Documentation, Digital Garden, Minimalist Blog), instant Livewire fuzzy search, interactive backlinks, and dark mode. [Explore the live Synkk Docs Portal](https://synkk.space/p/synkk-docs).
- **First-Sync Pre-Flight & Migration Engine:** Deterministic 4-phase local diagnostic scan (category rollup across Markdown, Canvases, Images, Media, PDFs), friction trap detection, 1-click cross-platform path sanitizer, dry-run simulation API (`POST /api/v1/vaults/{slug}/preflight`), and Atomic Safety Shield (20% deletion circuit breaker).
- **Laravel Backend Server:** High-performance local-first sync server with SQLite WAL, team tenancy, device tokens, and scoped vault access.
- **Whole-File Sync & Conflict Engine:** SHA-256 manifest verification, immutable file versions, conflict copies (`.sync-conflict-[timestamp].md`), audit logs, and soft-delete tombstones.
- **Obsidian Plugin v1.0.0:** Native desktop and mobile plugin with startup, scheduled, and manual sync pulses.
- **Device-Level Selective Sync:** Granular include/exclude glob patterns and path-based team access controls (Admin, Editor, Reader, Hidden).
- **In-App DLP Secret Scanning:** Intercepts accidental commits of AWS, OpenAI, Anthropic, SSH keys, or JWT tokens before storage.
- **Granular `.obsidian` Controls:** Synchronizes plugin lists, snippets, and plugin data while preserving local workspace layout and hotkeys.
- **Enterprise Remote Device Wipe:** 1-click revocation and remote token invalidation (HTTP 410 auto-purge).
- **Web Workspace & Visual Graph:** In-browser Markdown editor with split preview and 2D physics-based force graph canvas.

## Launch Links

- Public app: [synkk.space](https://synkk.space)
- Live Docs Portal: [synkk.space/p/synkk-docs](https://synkk.space/p/synkk-docs)
- Documentation: [synkk.space/documentation](https://synkk.space/documentation)
- Web/server repo: [github.com/tawandajosephmutsena/synkk](https://github.com/tawandajosephmutsena/synkk)
- Obsidian plugin repo: [github.com/tawandajosephmutsena/synk-obsidian-plugin](https://github.com/tawandajosephmutsena/synk-obsidian-plugin)
- Plugin release: [Latest release](https://github.com/tawandajosephmutsena/synk-obsidian-plugin/releases/latest)
- Creator studio: [Ottomate](https://ottomate.space)
- Commercial license: Lemon Squeezy checkout is enabled only after the live product, checkout, and activation flow are verified end to end.
- AppSumo: planned after the GitHub public release and production checkout are stable.

## Current Limits & Planned Milestones

Synkk Foundation ships reliable whole-file sync, pre-flight migration safety, instant QR pairing, and livewire vault portals. Next milestones on our active development horizon:

- **Zero-Knowledge E2EE:** Client-side XChaCha20-Poly1305 encryption before network transport for compliance-regulated teams.
- **Mobile Ghost Files:** Metadata-only stubs for large multimedia archives with sub-second on-demand binary streaming.
- **Live Multiplayer CRDT:** Character-level real-time multiplayer editing via Yjs (`y-codemirror.next`) with active collaborator carets.
- **Vault Copilot & Local RAG:** Self-hosted vector embeddings and private Ollama/vLLM LLM querying over your vault graph.

## Install The Plugin

Build the plugin from `obsidian-plugin/`:

```bash
cd obsidian-plugin
npm ci
npm run build
```

Install `main.js`, `manifest.json`, and `styles.css` in `<vault>/.obsidian/plugins/synkk-sync/`, then configure an HTTPS Synkk API endpoint ending in `/api/v1` and a device token from the dashboard (or scan the Instant QR Code).

## Run The Server Locally

```bash
composer setup
composer run dev
```

For production, provide a unique `APP_KEY`, set `APP_ENV=production`, turn `APP_DEBUG=false`, run migrations, configure durable storage and database backups, serve HTTPS, and keep the required workers running. Never commit a populated `.env` file.

## Roadmap

- **Foundation live:** GitHub plugin release, self-hosted Laravel server, dashboard, Markdown editor, Graph View, path permissions, version restore, selective sync rules, `.obsidian` controls, deletion safety, and snapshots.
- **Milestone A (Shipped):** Instant 3-Second QR Mobile Pairing and deep linking protocol (`obsidian://synkk-pair`).
- **Milestone B (Shipped):** Interactive Livewire Vault Portals at `/p/{slug}` with 4 themes, live search, and wikilink navigation.
- **Milestone C (Shipped):** First-Sync Pre-Flight & Migration Engine, 4-phase local diagnostic rollup, 1-click path sanitizer, dry-run simulation API, and 20% mass deletion atomic safety shield.
- **Next:** Client-side zero-knowledge E2EE, mobile ghost files with on-demand streaming, and live multiplayer CRDT.

## Creators

Synkk is created by [Ottomate](https://ottomate.space). Product direction and engineering are led by [Tawanda Joseph Mutsena](https://github.com/tawandajosephmutsena).

## Security

Do not include credentials, device tokens, or vault content in public issues. Until a dedicated security reporting address is published, report suspected vulnerabilities privately through GitHub's private reporting channel when available.

## License

Synkk is released under the [MIT License](LICENSE).
