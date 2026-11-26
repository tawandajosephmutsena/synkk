# Synkk — Complete 3-Video Tutorial Series & Production Script

> **Accuracy Guarantee:** Every UI label, Obsidian command, settings heading, cryptographic primitive, API endpoint, pricing tier, and limitation in this script has been verified directly against the **Synkk Server** (Laravel 13, PHP 8.5, Livewire 4, Reverb, SQLite WAL + FTS5) and **Synkk Team Vault Sync Plugin `v1.1.1`** (`minAppVersion: 1.7.2`, Desktop & Mobile).
>
> **Companion Example Vault:** [`docs/tutorials/example-vault-northstar-studio/`](./example-vault-northstar-studio/) — copy this folder onto your desktop before recording so you can open it directly in Obsidian and run every demo live.

---

## Pre-Production Setup Checklist (Before You Hit Record)

### What You Need Ready
1. **Desktop Computer** (macOS, Windows, or Linux) with a fresh copy of the example vault (`Northstar Studio`), screen recorder (1920×1080 or 4K), and clean browser profile.
2. **Mobile Device** (iPhone or Android) with **Obsidian Mobile (`1.7.2+`)** installed and screen mirroring ready (QuickTime on macOS / `scrcpy` on Android).
3. **Synkk Server** — either:
   - A **Synkk Cloud** account (`https://synkk.space`) with two test users: an **Owner** account (`Alex`) and a **Member** account (`Sam` / Contractor) for the Path ACL & collaboration demos in Video 3, **OR**
   - A **Self-Hosted Synkk** instance (VPS via `install.sh` with Caddy HTTPS, or local Docker via `docker compose -f docker-compose.prod.yml up -d --build` on `http://127.0.0.1:8000`).
4. **Optional for Video 3 (Local E2EE AI + Remote MCP)**:
   - **Ollama** installed locally with `ollama pull llama3.2` running at `http://localhost:11434`.
   - **Claude Desktop**, **Cursor**, **Windsurf**, or **Antigravity** ready to paste the Synkk Remote MCP JSON config.

### Copy the Example Vault & Prepare Live Demo Fixtures
```bash
# 1. Copy the ready-to-use Northstar Studio vault to your Desktop
cp -R docs/tutorials/example-vault-northstar-studio ~/Desktop/Northstar-Studio
cd ~/Desktop/Northstar-Studio

# 2. (For Video 2 Pre-Flight Auto-Sanitize demo — macOS/Linux only)
printf "# Q4 Planning\nCreated to demonstrate Synkk's 1-click Auto-Sanitize.\n" > "00-Inbox/Meeting: Q4? Plan.md"

# 3. (For Video 2 Mobile Ghost Files demo — creates a 7 MB dummy PDF > 5 MB default threshold)
mkdir -p 06-Media
printf "%%PDF-1.4\n%% Synkk Ghost File Demo Fixture\n" > 06-Media/Apollo-Field-Recording-7MB.pdf
dd if=/dev/zero bs=1048576 count=7 >> 06-Media/Apollo-Field-Recording-7MB.pdf
```

### Example Vault Map (`Northstar-Studio`)
| Folder / File | Purpose in Videos 2 & 3 |
|---|---|
| `00-Inbox/Raw Ideas.md` | Unsorted note without frontmatter — used for **Vault Gardener** triage & wikilink suggestions. |
| `00-Inbox/2026-11-26 Standup Notes.md` | Quick daily note for desktop ↔ mobile sync check. |
| `00-Inbox/Meeting: Q4? Plan.md` *(fixture)* | Triggers Step 1 **Pre-Flight Path Sanitizer** (`:` and `?` illegal on Windows). |
| `01-Projects/Apollo Launch/Launch Brief.md` | Hub note with YAML, callouts, `[[wikilinks]]`, tasks — used for **Editor**, **Graph**, **Versions**, **Conflict Sandbox**, and **Opt-In CRDT**. |
| `01-Projects/Apollo Launch/Architecture Overview.md` & `Pricing Strategy.md` | Intentionally disagree on beta cohort size (`50` vs `100` pilot teams) to showcase **Vault Copilot** & **Vault Gardener** contradiction detection. |
| `02-Clients/Acme Corp/Acme Corp Kickoff.md` | Configured as `read_only` for client/member **Path ACL** demo. |
| `03-Private/Executive Compensation & Keys.md` | Configured as `hidden` to prove zero-leak manifest filtering. |
| `04-Public-Docs/*` (3 notes) | Published live via **Livewire 4 Vault Portal** (`root_path: 04-Public-Docs`) + **Portal AI** + `/p/{slug}/llms.txt`. |
| `06-Media/Apollo-Field-Recording-7MB.pdf` *(fixture)* | Exceeds 5 MB mobile threshold — demonstrates **Mobile Ghost Files** dehydration & on-demand hydration. |

---

# VIDEO 1: What Is Synkk, Why Synkk, How It Works & Honest Comparison with the Competition

- **Target Length:** 12–15 minutes
- **Goal:** Give viewers total clarity on what Synkk is, how its architecture actually works under the hood, where it beats every alternative, AND where other tools might still fit better — zero hype, 100% engineering truth.

---

### [00:00 – 01:15] 1.1 Hook & The Obsidian Team Dilemma

**[VISUAL / SCREEN]**
- Open on your desktop showing Obsidian with the `Northstar-Studio` vault open on the left and the Synkk Web Workspace (Editor + Graph View) on the right.
- Quick cut to a split graphic: *Local Plaintext Markdown (.md)* vs *Cloud Silos (Notion / Confluence)* vs *All-or-Nothing Sync*.

**[SCRIPT]**
> "If you love Obsidian, you love it for one fundamental reason: your notes are plain `.md` files sitting right on your hard drive. No proprietary database, no vendor lock-in, and if any company disappears tomorrow, your entire brain is still readable in any text editor.
>
> The moment you try to scale Obsidian beyond a single laptop — across your phone, across a team, across contractors, or into modern AI tools like Claude and Cursor — you hit a wall.
>
> Either you pay recurring per-seat fees for proprietary sync that still gives every collaborator all-or-nothing access to your entire vault... or you glue together Git plugins that break on mobile and drop ugly `<<<<<<< HEAD` markers into your YAML frontmatter... or you give up and move back to Notion.
>
> That's why **Synkk** exists. In this three-part series, we're going from zero to complete mastery. In **Video 1**, I'll explain exactly what Synkk is, how it works under the hood, and give you a brutally honest comparison against Obsidian Sync, Obsidian Git, Remotely Save, Self-Hosted LiveSync, and Notion. In **Video 2**, we'll install Obsidian and Synkk from scratch with a real example vault. And in **Video 3**, we'll dive into every advanced power feature — Path ACLs, DLP, Conflict Sandbox, Opt-In CRDT, Livewire Portals, Zero-Knowledge E2EE with Local Ollama AI, and Synkk's Remote MCP Server."

---

### [01:15 – 03:30] 1.2 What Is Synkk?

**[VISUAL / SCREEN]**
- Show `https://synkk.space`, then switch to the authenticated Synkk Dashboard (`dashboard-overview.webp` / live dashboard), clicking briefly through the 9 vault tabs (`Editor`, `Graph`, `Permissions`, `Files`, `Activity`, `Analytics`, `Copilot`, `AI Inbox`, `Settings`) and the Obsidian plugin (`Synkk Team Vault Sync v1.1.1`).

**[SCRIPT]**
> "So, what *is* Synkk?
>
> Synkk is an **MIT open-source**, local-first synchronization server, web workspace, publishing engine, and permissioned AI/MCP layer built specifically for Obsidian.
>
> It has two halves:
> 1. **The Synkk Server** — a self-hostable Laravel 13, Livewire 4, and PHP 8.5 web application that ships in a single all-in-one Docker container with Nginx, Laravel Reverb WebSockets, background workers, and an embedded SQLite WAL + FTS5 database. You can self-host it on your own VPS or homelab for free, or use managed **Synkk Cloud** if you don't want to manage servers.
> 2. **The Official Obsidian Plugin (`Synkk Team Vault Sync v1.1.1`)** — free and MIT-licensed, live in Obsidian's Community Plugins directory, and compatible with **macOS, Windows, Linux, iOS, and Android** on Obsidian `1.7.2+`.
>
> Crucially, Synkk is *not* just a dumb file-transfer pipe. Around your plain `.md` files, Synkk gives you:
> - **Deterministic whole-file SHA-256 sync** with a 4-step Pre-Flight Migration Wizard and an Atomic Safety Shield that halts accidental mass wipes.
> - **Surgical Path-Level ACLs** — share `01-Projects/**` as read-write, `02-Clients/**` as read-only, and keep `03-Private/**` completely hidden from non-admins.
> - **A full Web Workspace** with a CodeMirror 6 Markdown editor, interactive 2D force-directed Graph View, 25-version file history with 1-click rollback, and ZIP export.
> - **Interactive Livewire 4 Vault Portals** — turn any non-E2EE vault or subfolder into a live website at `/p/{slug}` with password protection, Graph View, Ask This Portal AI, and an automatic `/llms.txt` feed.
> - **Zero-Knowledge Client-Side E2EE** using WebCrypto AES-256-GCM — plus **Local Ollama AI** that works even on encrypted vaults.
> - **Opt-In Real-Time Multiplayer CRDT** using Yjs over Reverb when you want live co-authoring.
> - **An Opt-In Vault AI Suite & Permissioned Remote MCP Server** so Claude Desktop, Cursor, Windsurf, or Antigravity can search your vault and stage human-reviewed note edits in an Approval Inbox."

---

### [03:30 – 06:30] 1.3 How Synkk Works Under the Hood (Architecture)

**[VISUAL / SCREEN]**
- Display a clean architecture diagram matching reality:
  `Obsidian Desktop / Mobile (Plugin v1.1.1)` ↔ `HTTPS + Protocol v2 (/api/v1/*) & Reverb WS (/app/)` ↔ `All-in-One Docker Container (Nginx -> PHP 8.5-FPM / Laravel 13 + Reverb :8080 + Queue Worker + Scheduler)` ↔ `SQLite WAL + FTS5 + storage/app/private (or S3)`.

**[SCRIPT]**
> "Let's look at how Synkk actually works so there's zero magic or marketing fluff.
>
> **1. Whole-File SHA-256 Sync (Protocol v2):**
> Every device authenticates with its own SHA-256-hashed bearer token (`synkk_...`) — not your account password — and talks to `/api/v1` with `X-Synkk-Protocol: 2`. On each sync, the plugin fetches an ACL-filtered vault manifest from the server, computes local SHA-256 content hashes via WebCrypto, checks the **Atomic Safety Shield** (which halts sync if more than 10% of tracked files are queued for deletion by default), writes local pre-mutation backups to `.synkk/snapshots/`, pulls changed files, and batches local uploads (up to 50 files or 8 MB per batch request). Identical hashes transfer zero bytes.
>
> **2. Conflict Safety & Opt-In CRDT:**
> By default, Synkk prioritizes rock-solid atomic whole-file sync. If two devices edit the same note offline, Synkk checks `base_version` and SHA-256 hashes, never overwrites your local note, and creates a non-destructive conflict copy (`*.sync-conflict-<timestamp>.md`). You resolve it side-by-side in the **Visual Conflict Sandbox** — manually or with **AI Smart Merge**. And when your team *does* want simultaneous Google-Docs-style typing, you can toggle on **Real-Time Collaboration (CRDT)** in settings, which streams Yjs updates and CodeMirror 6 cursors over Laravel Reverb with a durable server journal.
>
> **3. Plaintext Security vs. Zero-Knowledge E2EE (The Honest Trade-Off):**
> Synkk supports two vault modes, and you should know the exact boundary between them:
> - **Standard Vaults** travel over HTTPS and are stored on your server so Synkk can run **server-side plaintext DLP secret scanning** (flagging AWS, OpenAI, Anthropic, Stripe, GitHub, Slack, and PEM keys in your audit log and redacting them before external AI calls), render **Livewire Vault Portals**, run **Hybrid SQLite FTS5 + Vector Search**, and serve the **Remote MCP Server**.
> - **Zero-Knowledge E2EE Vaults** encrypt every note body, attachment, and Yjs collaboration payload right on your device using WebCrypto **AES-256-GCM** with **PBKDF2-SHA-256 (100,000 iterations)** before bytes ever leave Obsidian. Your passphrase stays in volatile RAM only — never written to `data.json`, never sent to the server. Because the server only sees opaque ciphertext, server-side search, Portals, DLP, MCP, and cloud AI are intentionally blocked on E2EE vaults. You *can* still open E2EE notes in the Web Editor by unlocking in browser RAM, and you *can* still run private AI Q&A and conflict merges locally via **Ollama** or **LM Studio** inside Obsidian. Also note honestly: file paths, sizes, and version numbers stay plaintext metadata so Synkk can still route manifests and enforce Path ACLs — and there is no server recovery key, so keep your passphrase in a password manager."

---

### [06:30 – 11:30] 1.4 Honest Comparison with the Competition

**[VISUAL / SCREEN]**
- Walk through the comparison table on `https://synkk.space/#comparison` (or a clean slide table with exact columns below).

| Capability | **Synkk** | **Official Obsidian Sync** | **Obsidian Git** | **Remotely Save (S3/WebDAV)** | **Self-Hosted LiveSync** | **Notion / Confluence** |
|---|---|---|---|---|---|---|
| **Plain Local `.md` Files** | Yes (100% local) | Yes | Yes | Yes | Yes | No (proprietary cloud DB) |
| **100% Self-Hostable** | Yes (1 Docker container, MIT) | No (closed cloud) | Partial (needs Git host) | Partial (needs S3/WebDAV) | Yes (Apache CouchDB) | No |
| **Path-Level Team ACLs** | Yes (`read_write`, `read_only`, `hidden`) | No (all-or-nothing vault) | No (repo-wide only) | No (bucket-wide) | No (DB-wide) | Workspace/page ACLs |
| **Conflict Handling** | Non-destructive forks + Visual Sandbox + AI Smart Merge + Opt-in Yjs CRDT | Auto-merge / conflict copies / version history | Raw `<<<<<<< HEAD` markers | Overwrite risk / conflict copies | CouchDB revision merge modal | Cloud LWW / live collab |
| **Web Editor & 2D Graph** | Built-in CM6 editor + 2D Graph + Portals | No web editor (Obsidian Publish is extra) | GitHub raw editor only | None | Fauxton raw JSON only | Web-first |
| **Mobile Onboarding** | 1-Scan QR / `obsidian://synkk-pair` | Email login | Painful (SSH/PAT/Termux) | Manual S3 keys | Manual CouchDB URI + keys | Mobile app login |
| **Mobile Storage Protection** | Mobile Ghost Files (>5 MB stubs, hydrate on demand) | Selective folder/type exclude (no stub hydration) | Full repo clone | Full sync | Full CouchDB replication | Cloud streamed |
| **Zero-Knowledge E2EE** | Yes (opt-in AES-256-GCM + PBKDF2 100k; paths plaintext) | Yes (encrypts filenames too!) | No (plaintext Git) | Optional password encryption | Optional E2EE in CouchDB | No (provider-managed keys) |
| **AI, RAG & Remote MCP** | Local Ollama (works on E2EE), BYOK, Managed AI, Proposal Inbox & 7-tool Remote MCP | None built-in | None | None | None | Closed vendor AI |
| **Team Pricing (15 users, 3 yrs)** | **$0** MIT self-host · **$79** Pro LTD · **$900** Cloud Business ($25/mo for up to 25 seats) | **$4,320+** ($8/seat/mo × 15 × 36) | Free (high dev/support time) | S3/storage fees | VPS + CouchDB ops | **$5,400–$10,800+** |

**[SCRIPT]**
> "Now let's do an honest comparison — including where competitors *are* genuinely strong, so you can pick the right tool for your actual situation.
>
> **1. vs. Official Obsidian Sync:**
> Official Obsidian Sync is polished, dead-simple for solo users, and encrypts filenames in addition to contents. If you're a solo user who doesn't want to touch a server, doesn't need a web editor or MCP server, and is happy paying $4–$8/month, Obsidian Sync is great.
> Where it falls short is **teams, sovereignty, and workflows**: it cannot be self-hosted, it has zero folder-level permissions (everyone sees everything), it has no web editor, no built-in publishing portals without paying extra for Obsidian Publish, no MCP server, and for a 15-person team you're paying $4,320+ over three years. Synkk gives you flat workspace pricing ($25/mo Cloud Business covers up to 25 members — $900 over 3 years) or $0 self-hosted MIT / $79 Pro LTD, plus Path ACLs, Portals, and AI/MCP.
>
> **2. vs. Obsidian Git:**
> Developers love Git, and it's free — until you hand it to a designer, a PM, or try to use it on an iPhone. Mobile Git is notoriously fragile, concurrent edits inject `<<<<<<< HEAD` markers that corrupt Markdown frontmatter, repos have zero folder masking for contractors, and secrets committed to Git live in history forever. Synkk gives you version history (25 revisions per file) and safe conflict forking without turning your team into Git operators.
>
> **3. vs. Remotely Save (S3 / WebDAV / Dropbox):**
> Remotely Save is great for cheap personal backup to an S3 bucket. But an S3 bucket has no web Markdown editor, no Graph View, no team RBAC/Path ACLs, no pre-flight safety shield, no portals, and no real-time collaboration or MCP server.
>
> **4. vs. Self-Hosted LiveSync (CouchDB):**
> LiveSync is an impressive open-source project for tinkerers who want CouchDB replication. The trade-offs are operational complexity (running & tuning CouchDB, CORS, manual setup URIs on phones), no team Path ACLs, no web Markdown workspace or Portals, and no integrated AI/MCP stack. Synkk replaces CouchDB with a single Docker container on SQLite WAL, 2-second QR mobile pairing, and a complete web workspace.
>
> **5. vs. Notion & Confluence:**
> Notion and Confluence are great if you want a cloud-only database wiki and don't care about owning plain files. If you want local speed, offline-first Markdown files on disk, zero vendor lock-in, and your choice of local Ollama or BYOK AI, Synkk + Obsidian gives you team collaboration *without* surrendering your files."

---

### [11:30 – 13:30] 1.5 Transparent Pricing & Honest Limitations

**[VISUAL / SCREEN]**
- Scroll through `https://synkk.space/#pricing` and `#roadmap`.

**[SCRIPT]**
> "Before we jump into installation in Video 2, let's be 100% transparent about both pricing and current limitations:
>
> **Pricing:**
> - **Obsidian Plugin:** MIT open-source, free forever.
> - **Synkk Community Server:** MIT open-source ($0). Hosted Community Free defaults to 1 vault, 3 members, 3 devices, and 1 GB; self-hosted admin deployments run on your own server capacity. BYOK AI and Remote MCP are included.
> - **Synkk Cloud Pro ($12/workspace/mo):** 30-day card-verified trial, 5 GB, 5 vaults, 5 members, 25 devices + Path ACLs, Remote Wipe, IP allowlisting, AI automation, and $2 Managed AI trial credit.
> - **Synkk Cloud Business ($25/workspace/mo):** 25 GB, 25 vaults, 25 members, 100 devices — flat per workspace, no per-seat tax.
> - **Self-Host Pro LTD ($79 one-time):** Commercial team controls (Path ACLs, Remote Wipe, IP allowlisting, Read-Only Tokens, AI Smart Merge, Gardener, Digests, Portal AI; defaults to 15 vaults / 10 members / 25 devices / 15 GB, customizable on self-hosted instances).
> - **Optional Managed Cloud AI Add-On ($12/workspace/mo):** Only needed if you want turnkey hosted LLMs ($12/mo credit pool) instead of connecting your own API keys or free local Ollama.
>
> **Honest Current Limitations (as of today):**
> - In E2EE vaults, file *paths* and sizes remain plaintext metadata on the server, and there is no server-side E2EE recovery key.
> - Real-time CRDT is opt-in (off by default in the Obsidian plugin) and works on one active `.md` file at a time.
> - Vault Portals currently live at `/p/{slug}` — custom domain routing, outbound Slack/Discord webhooks, an interactive Trash/Recycle Bin UI, a headless CLI client, and native OIDC/SAML SSO are on the roadmap, not shipped yet.
> - In the web Markdown preview, LaTeX math, Mermaid, and Dataview blocks display as styled code boxes rather than rendered diagrams/queries.
>
> Now that you know the exact truth of what Synkk is and isn't, click into **Video 2**, where we'll install Obsidian and Synkk from scratch and sync our `Northstar Studio` example vault across desktop, web, and mobile."

---

# VIDEO 2: How to Install Obsidian & Synkk and Use Every Core Feature

- **Target Length:** 18–22 minutes
- **Goal:** Complete hands-on walkthrough from a blank machine — installing Obsidian, setting up Synkk (Cloud or Self-Hosted), pairing desktop & mobile, running the Pre-Flight Migration Wizard, syncing daily, exploring the Web Workspace, and using Mobile Ghost Files.

---

### [00:00 – 02:30] 2.1 Installing Obsidian & Opening the Example Vault

**[VISUAL / SCREEN]**
- Browser on `https://obsidian.md/download` → install Obsidian Desktop (confirm version is `1.7.2` or newer).
- Open Obsidian Vault Switcher → click **Open folder as vault** → select `~/Desktop/Northstar-Studio`.
- Expand the folder tree in Obsidian showing `00-Inbox`, `01-Projects`, `02-Clients`, `03-Private`, `04-Public-Docs`, and `06-Media`.

**[ACTION]**
1. Download and install Obsidian (`1.7.2+`) from `obsidian.md` (and install Obsidian on iOS/Android from App Store / Play Store).
2. Click **Open** next to **Open folder as vault** and choose `Northstar-Studio`.
3. Click **Trust author and enable plugins** if prompted.
4. Open `01-Projects/Apollo Launch/Launch Brief.md` to show YAML frontmatter, callouts, `[[wikilinks]]`, and tasks.

**[SCRIPT]**
> "Welcome back! In Video 2 we're going completely hands-on. We'll start from a fresh Obsidian install, connect to Synkk, migrate our `Northstar Studio` example vault safely, explore the web workspace, and pair our phone with Ghost Files.
>
> First, download Obsidian from `obsidian.md` — make sure you're on Obsidian **1.7.2 or newer**, because Synkk plugin `v1.1.1` requires `1.7.2+`. Also grab the free Obsidian app on your iPhone or Android phone so we can pair it in a few minutes.
>
> In Obsidian, click **Open folder as vault** and select our `Northstar-Studio` example vault. Notice how realistic this folder layout is: an `00-Inbox` folder (including a note I deliberately named `Meeting: Q4? Plan.md` with illegal Windows characters so we can test Synkk's Pre-Flight sanitizer!), `01-Projects/Apollo Launch`, a `02-Clients/Acme Corp` folder, a confidential `03-Private` folder, `04-Public-Docs`, and a 7-megabyte dummy PDF inside `06-Media`."

---

### [02:30 – 06:00] 2.2 Setting Up Your Synkk Server (Cloud vs. Self-Hosted) & Securing Your Account

**[VISUAL / SCREEN]**
- Show **Option A (Fastest — Synkk Cloud)**: Register at `https://synkk.space/register` → Verify email → Land on Dashboard.
- Show **Option B (Self-Hosted)** in a terminal overlay (briefly demonstrate both the VPS Caddy installer and local Docker Compose):
  ```bash
  # VPS with domain & automatic Caddy Let's Encrypt HTTPS:
  git clone --recurse-submodules https://github.com/tawandajosephmutsena/synkk.git
  cd synkk
  sudo SYNKK_DOMAIN=vault.example.com SYNKK_ADMIN_EMAIL=you@example.com bash install.sh

  # OR local Docker Compose trial on http://127.0.0.1:8000:
  cp .env.production.example .env.production
  # Set APP_KEY (openssl rand -base64 32), REVERB_APP_KEY (hex 16), REVERB_APP_SECRET (hex 32),
  # APP_URL=http://localhost:8000, SESSION_SECURE_COOKIE=false, SYNKK_BOOTSTRAP_EMAIL & PASSWORD
  docker compose -f docker-compose.prod.yml up -d --build
  ```
- Open **Settings → Security** in the Synkk web dashboard and highlight **Two-Factor Authentication (TOTP + Recovery Codes)** and **Passkeys (WebAuthn)**.

**[ACTION]**
1. Log into your Synkk workspace.
2. Open **Settings → Security** → show how to register a **Passkey** (Touch ID / Face ID / YubiKey) and enable **2FA**.
3. Open the **Team Switcher** (top-left) → click **Create Team** → name it `Northstar Studio`.
4. Navigate to **Vaults** → click **Create Vault** → Name: `Northstar Studio` (slug `northstar-studio`), Default Permission: `read_write` → click **Create Vault**.

**[SCRIPT]**
> "Now let's get Synkk running. You have two paths:
> - **Option A — Synkk Cloud:** Sign up at `synkk.space`, verify your email, and you're on the dashboard in 30 seconds.
> - **Option B — Self-Hosted:** On any Ubuntu, Debian, AlmaLinux, or Rocky VPS with a DNS record pointed to it, clone the repo with `--recurse-submodules` and run `install.sh` as root. It installs Docker, provisions automatic Let's Encrypt HTTPS with Caddy, generates your secrets, and bootstraps your admin account. Or for a local Docker test, copy `.env.production.example` to `.env.production`, paste your generated `APP_KEY` and Reverb keys, set your first-boot `SYNKK_BOOTSTRAP_EMAIL` and `PASSWORD`, and run `docker compose -f docker-compose.prod.yml up -d --build` — it runs on `http://127.0.0.1:8000`. Just remember to remove the bootstrap credentials from `.env.production` after your first login.
>
> Before syncing anything, pop into **Settings → Security**. Synkk natively supports **TOTP Two-Factor Authentication** with recovery codes *and* **WebAuthn Passkeys** (Touch ID, Windows Hello, or hardware security keys). Lock down your account right away.
>
> Next, use the **Team Switcher** to create a team called `Northstar Studio`, head to **Vaults**, and click **Create Vault**. Name it `Northstar Studio` and leave default access as `Read & Write`."

---

### [06:00 – 09:00] 2.3 Installing the Plugin (`v1.1.1`) & One-Scan / Deep-Link Pairing

**[VISUAL / SCREEN]**
- In Obsidian: **Settings (gear icon) → Community plugins → Turn on community plugins → Browse** → search `Synkk Team Vault Sync` → click **Install** → click **Enable**.
- *(Brief note overlay showing manual fallback: download `main.js`, `manifest.json`, `styles.css` from GitHub Releases into `<vault>/.obsidian/plugins/synkk-sync/`.)*
- Switch to Synkk Web Dashboard → **Devices** → click **Connect Device** → select target vault `Northstar Studio` → show the live **QR Code** and **Copy Deep Link / Bridge URL**.
- Back in Obsidian → **Settings → Synkk Vault Sync** → under **⚡ One-Scan Quick Connect**, paste the pairing link (or click the deep link in the browser to trigger `obsidian://synkk-pair`)!
- Watch `Server API URL`, `Device Sync Token`, and `Target Vault` populate automatically and verify!

**[ACTION]**
1. Install and enable **Synkk Team Vault Sync** (`v1.1.1`) from Community Plugins.
2. In Synkk Dashboard → **Devices** → generate a pairing session (10-minute single-use expiry).
3. On desktop, click the `obsidian://synkk-pair` link or paste the `/pair?...` URL into **⚡ One-Scan Quick Connect** in Obsidian settings.
4. *(Also show the manual path briefly: `Server API URL` ending in `/api/v1`, `Device Sync Token` starting with `synkk_`, click **Verify & Load Vaults**, select `Northstar Studio` in **Target Vault**.)*

**[SCRIPT]**
> "Now let's connect Obsidian to Synkk.
>
> Inside Obsidian, go to **Settings → Community plugins**, turn off Restricted Mode, click **Browse**, and search for **Synkk Team Vault Sync**. Hit **Install** and **Enable**. *(If you're in an air-gapped environment, you can also drop `main.js`, `manifest.json`, and `styles.css` from the latest GitHub Release into `.obsidian/plugins/synkk-sync/`.)*
>
> Notice two immediate changes in Obsidian: three new icons appeared in your left ribbon — the **Synkk Octopus (`Synkk: Sync Now`)**, the **Split icon (`Synkk: Visual Conflict Sandbox`)**, and the **Sparkles icon (`Synkk AI: Open Copilot Sidebar`)** — plus a **`Synkk: Ready`** indicator in the bottom status bar.
>
> Now let's pair this laptop without typing a single token. In the Synkk dashboard, open **Devices** and click **Connect Device**. Synkk mints a single-use, 10-minute pairing session with both a QR code and an `obsidian://synkk-pair` deep link. On desktop, just click the deep link — or paste the pairing link into **Settings → Synkk Vault Sync → ⚡ One-Scan Quick Connect**.
>
> Boom! Synkk automatically validates the HTTPS origin (or `localhost`/LAN for local self-hosting), exchanges the session for a dedicated device token (`Mac (Obsidian)` / `Windows PC (Obsidian)`), stores your Reverb WebSocket config, verifies auth, and binds `Northstar Studio` as your Target Vault."

---

### [09:00 – 12:30] 2.4 Running the 4-Step Pre-Flight Diagnostic & Migration Wizard

**[VISUAL / SCREEN]**
- In Obsidian **Settings → Synkk Vault Sync**, scroll to **Pre-Flight & Migration Wizard** and click **Launch Migration Wizard** (or press `Cmd/Ctrl+P` → `Vault Migration: Run Pre-Flight Diagnostic & Migration Wizard`).
- Walk clearly through all 4 steps on screen:
  1. **Step 1 — Diagnostic Scan:** Point out the 7 category pills (Markdown, Canvases, Images, Audio/Video, PDFs, Config, Other) and the red friction alert flagging `00-Inbox/Meeting: Q4? Plan.md`! Click **⚡ Auto-Sanitize Unsafe File Names** and watch it rename safely!
  2. **Step 2 — Exclusion Presets:** Show the toggles (`.trash`/`.git` exclusion, Mobile Ghost Files toggle, >25 MB estimate) and the live Bandwidth & Storage Saved readout.
  3. **Step 3 — Server Simulation:** Click next → calls `POST /api/v1/vaults/northstar-studio/preflight` with **zero file bytes transferred**. Point out: Quota Check (Green), Files to Upload, Files to Pull, Identical Files Skipped, and remaining storage.
  4. **Step 4 — Migration Sync:** Click start → watch the first guarded sync complete cleanly!

**[SCRIPT]**
> "If you have an existing vault with hundreds or thousands of notes, *never* blindly hit sync on a new tool. Use Synkk's **4-Step Pre-Flight & Migration Wizard** first.
>
> Press `Cmd+P` (or `Ctrl+P`) and run **`Vault Migration: Run Pre-Flight Diagnostic & Migration Wizard`**, or click **Launch Migration Wizard** in Settings.
>
> - **Step 1 — Diagnostic Scan:** Synkk scans your entire vault locally before sending a single byte. It rolls up counts and sizes across Markdown, Canvases, Images, Audio/Video, PDFs, and Config — and immediately catches our trap! Look at that severe warning: `00-Inbox/Meeting: Q4? Plan.md` contains `:` and `?`, which will break syncing on Windows and mobile file systems. Click **`⚡ Auto-Sanitize Unsafe File Names`**, and Synkk renames it cleanly via Obsidian's FileManager so internal links stay intact.
> - **Step 2 — Exclusion Presets:** Here you can toggle Mobile Ghost Files and see estimated storage savings. Note that `.trash/`, `.git/`, `.DS_Store`, `.synkk/`, and Synkk's own device credentials (`plugins/synkk-sync/data.*`) are *always* excluded automatically so your secret tokens never leak into your vault.
> - **Step 3 — Server Simulation:** Synkk calls `POST /api/v1/vaults/northstar-studio/preflight` with just paths, sizes, and SHA-256 hashes — zero file contents uploaded yet. It confirms our team has enough storage quota, shows exact upload/pull/skip counts, and would block Step 4 cleanly if our vault exceeded quota.
> - **Step 4 — Migration Sync:** Hit start, and our entire `Northstar Studio` vault uploads cleanly in batched SHA-256 transactions. Every future sync will only send changed files."

---

### [12:30 – 16:00] 2.5 Everyday Sync Settings & Touring the Synkk Web Workspace

**[VISUAL / SCREEN]**
- Briefly scroll through Obsidian **Settings → Synkk Vault Sync** highlighting:
  - **Selective Sync & Configuration** (`Include folders`, `Exclude folders`).
  - **Vault Environment & Plugin Suite Sync** (`Sync Plugin Suite & Themes` default ON — explain it syncs `community-plugins.json`, `snippets/`, `themes/`, and `plugins/` while keeping `workspace.json`, `hotkeys.json`, and Synkk credentials local, and automatically skips desktop-only plugins like `obsidian-git` or `terminal` on mobile!).
  - **Sync Schedule & Automation** (`Automatic Background Sync` every 5 min, `Sync on Startup`, `Sync on File Change` with 2.5s debounce).
- Switch to the **Synkk Web Workspace** and open the `Northstar Studio` vault. Tour each core tab live:
  1. **Editor tab**: Open `Launch Brief.md` → show CodeMirror 6 formatting toolbar, `[[wikilink]]` highlights, callout styling, Split Preview, Document Outline, word count, and edit a line → press `Cmd/Ctrl+S`.
  2. **Version History modal**: Click **Versions** on `Launch Brief.md` → show the archived revision with SHA-256 hash and timestamp → demonstrate **1-Click Restore**!
  3. **Graph tab**: Click **Graph** → drag nodes, zoom, search `Apollo`, click `Pricing Strategy` to jump straight into the editor!
  4. **Files tab**: Show List/Grid toggle, search/category filters, file inspector, rename/duplicate/delete, and **Export ZIP** (`northstar-studio-export-*.zip`).
  5. **Activity & Analytics tabs**: Show the audit changelog, word/note metrics, contributor leaderboard, and 14-day velocity chart.

**[SCRIPT]**
> "Let's check our everyday sync controls in Obsidian settings: by default, Synkk syncs on startup, 2.5 seconds after you edit a file, and every 5 minutes (configurable from 1 to 30 minutes). Under **Vault Environment & Plugin Suite Sync**, Synkk can even sync your community plugins, themes, and CSS snippets across machines — while automatically keeping `workspace.json`, `hotkeys.json`, and Synkk's own device token strictly local, and automatically blocking desktop-only plugins like `obsidian-git` or `terminal` from breaking your phone.
>
> Now let's switch to the browser and open our `Northstar Studio` vault on the **Synkk Web Workspace** — because with Synkk, you can read and edit your vault even on a machine that doesn't have Obsidian installed.
>
> - In the **Editor** tab, we have a full CodeMirror 6 Markdown editor with live split preview, heading outline, word count, and version-aware saves. Watch: if I make an edit here and press `Cmd+S`, Synkk archives the previous copy automatically (retaining up to **25 versions per file**). Click **Versions** and you can inspect and restore any earlier snapshot in one click.
> - Click the **Graph** tab to explore your vault's `[[wikilinks]]` in an interactive 2D force-directed physics map. Search, zoom, drag clusters, and click any node to jump right into editing.
> - In the **Files** tab, you can browse by category, rename or download individual files, or click **Export ZIP** to download a complete portable archive of your vault anytime.
> - And **Activity** + **Analytics** give you a full audit trail of every sync, word count stats, and team velocity."

---

### [16:00 – 19:30] 2.6 Pairing Mobile (iOS / Android) & Mobile Ghost Files

**[VISUAL / SCREEN]**
- Mirror your iPhone or Android screen alongside the desktop browser.
- On Mobile Obsidian: Create a new empty local vault `Northstar Mobile` → **Settings → Community plugins → Browse** → install & enable **Synkk Team Vault Sync**.
- On Desktop Synkk Dashboard → **Devices → Connect Device** → display the QR code.
- On Mobile Obsidian → **Settings → Synkk Vault Sync → 📷 Scan QR Code** → point phone camera at screen → instant vibration & pairing!
- Tap **Sync Now** (or ribbon Octopus icon) → watch all Markdown notes arrive in seconds!
- Open `06-Media/Apollo-Field-Recording-7MB.pdf` on Mobile → show the `<1 KB` **👻 Synkk Ghost Attachment** callout stub!
- Open Mobile Command Palette → run **`Ghost Files: Hydrate active file on-demand`** → watch the full 7 MB file download immediately! Then show **`Ghost Files: Dehydrate active file to ghost stub`** to reclaim mobile storage!

**[SCRIPT]**
> "Now let's bring your phone online. Create an empty vault in Obsidian Mobile, install **Synkk Team Vault Sync** from Community Plugins, and enable it.
>
> On your desktop dashboard, open **Devices → Connect Device** to display a fresh QR code. On your phone, open **Settings → Synkk Vault Sync** and tap **`📷 Scan QR Code`**. Point the camera at your monitor — feel the haptic buzz — and your phone is paired, authenticated, and bound to `Northstar Studio` without typing a single character! *(And if your camera isn't handy, the modal also supports pasting a `/pair` link or selecting a QR screenshot from your photo library.)*
>
> Hit sync. In seconds, all our Markdown notes appear on the phone — and look at our **7 MB PDF** in `06-Media/`. Because **Enable On-Demand Ghost Files** is turned on by default with a **5 MB threshold** (configurable from 2 to 50 MB), Synkk did *not* bloat your phone's flash storage. Instead, it downloaded a tiny `<1 KB` Ghost Stub (`<!-- synkk:ghost ... -->`) so all your links and folders remain intact.
>
> Need the full file while traveling? Open the Command Palette and run **`Ghost Files: Hydrate active file on-demand`**. Synkk fetches the full binary immediately. Done reviewing it? Run **`Ghost Files: Dehydrate active file to ghost stub`** to free those 7 megabytes right back up.
>
> One honest mobile tip: iOS and Android suspend background apps aggressively. Synkk's **Adaptive Background Relay** automatically polls the server every 60 seconds while Obsidian is open *and* triggers an instant check the moment you bring Obsidian back to the foreground.
>
> You now have a complete, cross-platform Obsidian setup! Ready for the serious power tools? Jump into **Video 3**, where we unlock Path ACLs, DLP, Conflict Sandbox + AI Smart Merge, Opt-In CRDT, Livewire Portals, Zero-Knowledge E2EE with Local Ollama AI, Vault Gardener, and Synkk's Remote MCP Server."

---

# VIDEO 3: Synkk Advanced Power Usage (Deep Dive)

- **Target Length:** 25–30 minutes
- **Goal:** Master every advanced team, security, publishing, encryption, AI, and MCP capability in Synkk — with live demonstrations and zero hand-waving.

---

### [00:00 – 04:00] 3.1 Team RBAC & Surgical Path-Level ACLs

**[VISUAL / SCREEN]**
- Open Synkk Dashboard → **Team Settings → Members** → invite a second account (`Sam`, role `Member`). Briefly explain the 3 real team roles:
  - **Owner:** Full team control (manage members/roles, delete team, always `read_write` on all vaults).
  - **Admin:** Update team, create/cancel invitations, manage vaults & Path ACLs, always `read_write` on all vaults (cannot change member roles or delete the team).
  - **Member:** Subject to Vault Path ACLs!
- Open `Northstar Studio` vault → **Permissions** tab.
- Add three exact path rules for `Sam` (or team-wide):
  1. `03-Private/**` → **`hidden`**
  2. `02-Clients/Acme Corp/**` → **`read_only`**
  3. `01-Projects/**` → **`read_write`**
- Switch to an incognito browser / second Obsidian window logged in as `Sam`:
  - Show `03-Private/` does **not exist anywhere** in Sam's file tree, Graph View, or manifest!
  - Open `02-Clients/Acme Corp/Acme Corp Kickoff.md` → show read-only mode (push attempts blocked with `HTTP 403`).
  - Open `01-Projects/Apollo Launch/Launch Brief.md` → edit & save cleanly!

**[SCRIPT]**
> "Welcome to Video 3 — Synkk Advanced Power Usage. Let's start with the feature that separates Synkk from every other Obsidian sync tool: **Path-Level Access Control**.
>
> First, understand the two layers of permissions in Synkk:
> 1. **Team Roles** (`Owner`, `Admin`, `Member`): Owners have full control over billing, roles, and team deletion. Admins manage invitations, vaults, and path rules. Both Owners and Admins always resolve to `read_write` across team vaults. Regular **Members** are governed by Path ACLs.
> 2. **Vault Path ACLs** (`read_write`, `read_only`, `hidden`): Inside our `Northstar Studio` vault's **Permissions** tab, let's lock down access for our contractor `Sam`:
>    - `03-Private/**` → **`hidden`**
>    - `02-Clients/Acme Corp/**` → **`read_only`**
>    - `01-Projects/**` → **`read_write`**
>
> Rules resolve in strict priority: user-specific rules beat team-wide rules, and the longest, most specific folder prefix wins before falling back to the vault's default permission.
>
> Now watch what happens when `Sam` logs in or syncs Obsidian: `03-Private/` isn't just locked — the server strips it from the sync manifest, search index, Graph View, and MCP tools *before* sending a response. Sam has zero visibility that `Executive Compensation & Keys.md` even exists. `02-Clients/Acme Corp/` opens cleanly for reading, while any write attempt returns `HTTP 403`. And `01-Projects/` syncs two-way seamlessly."

---

### [04:00 – 07:30] 3.2 Scoped Tokens, IP Allowlisting, Remote Wipe, Plaintext DLP & Atomic Safety Shield

**[VISUAL / SCREEN]**
- **Devices Page**: Show editing a device token — Access Scope (`full_access`, `read_write`, `read_only`), Vault Allowlist, and **Allowed IPs** *(note on screen: exact IP or wildcard prefix like `192.168.1.*`, not CIDR bitmasking)*.
- **Remote Wipe Demo**: Click **Remote Wipe** on a test device → run sync on that test device → show `HTTP 410` notice (`Device Remotely Wiped`) clearing the local device token and sync state file!
- **Plaintext DLP Demo**: On our standard (non-E2EE) vault, create a temporary note `00-Inbox/DLP-Test.md`, paste a **synthetic dummy AWS key** (`AKIAIOSFODNN7EXAMPLE`), and click **Sync Now**.
  - Show the plugin's DLP warning Notice AND switch to the Synkk **Dashboard** → show the **DLP Threat Alert** & health score penalty! Then delete `DLP-Test.md`.
- **Atomic Safety Shield Demo**: Open Obsidian **Settings → Synkk Vault Sync → Atomic Safety Shield** (`Deletion safety threshold`, default `10%`). Briefly demonstrate deleting >10% of files (e.g., 2 notes in a 10-note vault), click **Sync Now**, watch the status bar halt with **`Safety Shield requires confirmation.`**! Show `.synkk/snapshots/` on disk, restore the deleted notes (or toggle **Allow next guarded sync** if intentional).

**[SCRIPT]**
> "Next, let's test Synkk's four operational security shields live.
>
> **1. Scoped Tokens, IP Allowlists & 1-Click Remote Wipe:**
> In **Devices**, every laptop or phone gets its own token. You can restrict a device to `read_only`, restrict it to specific vaults, or add IP allowlist entries (exact IPs or wildcard prefixes like `10.0.1.*`). If a laptop is stolen at an airport, click **Remote Wipe**. The moment that device touches the server, Synkk returns **`HTTP 410`** — revoking the token and telling the Obsidian plugin to immediately purge its stored credentials and local sync state file.
>
> **2. Server-Side Plaintext DLP Secret Scanning:**
> On standard (non-E2EE) vaults, Synkk inspects uploads server-side for leaked AWS keys, OpenAI/Anthropic/Stripe/GitHub/Slack/Synkk tokens, and PEM/SSH private keys. Watch: I'll drop a synthetic dummy AWS test key into a throwaway note and sync. Instantly, Obsidian flashes a DLP alert, and our Synkk Dashboard flags the file in the Audit Threats modal so admins can rotate keys immediately. Even better, Synkk automatically redacts matched secrets (`[REDACTED:...]`) before sending note excerpts to external AI providers. *(Remember: on E2EE vaults, the server only sees ciphertext, so server-side DLP is intentionally skipped.)*
>
> **3. Atomic Safety Shield & Pre-Mutation Snapshots:**
> What if a broken script or accidental drag-and-drop deletes half your vault? By default, Synkk's **Atomic Safety Shield** halts any sync proposing to delete more than **10%** of your tracked files (configurable from 1% to 100%). Watch: if I delete two notes out of ten and hit sync, the status bar halts with **`Safety Shield requires confirmation.`**. Nothing propagates until you intentionally click the status bar or toggle **Allow next guarded sync** — and every time a remote overwrite *does* happen, Synkk first archives your previous local copy inside `.synkk/snapshots/`."

---

### [07:30 – 11:30] 3.3 Visual Conflict Sandbox, AI Smart Merge & Opt-In Real-Time CRDT

**[VISUAL / SCREEN]**
- **Part A — Conflict Sandbox**:
  1. Temporarily pause auto-sync on Desktop, edit the last bullet of `Launch Brief.md` on Desktop (`60 pilot teams — confirmed with Engineering`) AND edit the same bullet in the Web Editor (`75 pilot teams — approved by Sales`) → click **Sync Now** in Obsidian.
  2. Show the non-destructive `Launch Brief.sync-conflict-<timestamp>.md` created alongside `Launch Brief.md`.
  3. Click the **Split** icon in Obsidian's left ribbon (`Synkk: Visual Conflict Sandbox`).
  4. Show the side-by-side **Canonical** vs **Conflict Copy** panes, buttons (`Keep Canonical Version`, `Keep Conflict Version`, `Combine Both`), and click **`✨ Smart Merge with AI`**!
  5. Inspect the editable reconciled preview and click **`⚡ Reconcile & Clean Up Conflict`** — watch the conflict copy vanish and clean merged Markdown sync to both sides!
- **Part B — Opt-In Real-Time CRDT Collaboration**:
  1. Open Obsidian **Settings → Synkk Vault Sync → 👥 Real-Time Multiplayer Collaboration** → toggle **`Enable Real-Time Collaboration (CRDT)`** ON.
  2. Place Obsidian Desktop and the Synkk Web Editor side-by-side, both open to `01-Projects/Apollo Launch/Launch Brief.md`.
  3. Type in both windows simultaneously — highlight the live **CodeMirror 6 remote carets** syncing over Laravel Reverb and flushing cleanly back to `.md` after 3 seconds!

**[SCRIPT]**
> "Let's talk about concurrent edits — both offline and real-time.
>
> First, offline collisions. I've edited the exact same sentence in `Launch Brief.md` on my laptop and in the Web Editor while out of sync. When I click **Sync Now**, Synkk detects the divergence via SHA-256 and `base_version`, refuses to overwrite my local work, and writes the incoming version as `Launch Brief.sync-conflict-<timestamp>.md`.
>
> Click the **Split** icon in the left ribbon to open **`⚡ Synkk Visual Conflict Sandbox`**. You see the **Canonical** note and **Conflict Copy** side-by-side. You can click **Keep Canonical**, **Keep Conflict**, **Combine Both**, or — my favorite — click **`✨ Smart Merge with AI`** (even adding optional merge instructions like *'keep both numbers and flag for Alex to decide'*). Review the editable output, click **`⚡ Reconcile & Clean Up Conflict`**, and Synkk saves the merged note, cleans up the conflict copy, and syncs cleanly.
>
> Now, what if two teammates want to co-write a spec *at the exact same second*? Open **Settings → Synkk Vault Sync → Real-Time Multiplayer Collaboration** and toggle **`Enable Real-Time Collaboration (CRDT)`** on. Why is this off by default in `v1.1.1`? Because Synkk's engineers prioritize bulletproof atomic whole-file sync for solo and async work, while letting teams opt into live Yjs multiplayer whenever they want it. Watch: with Obsidian and the Web Editor open side-by-side on `Launch Brief.md`, edits and live remote carets stream over Laravel Reverb, backed by a durable server journal that flushes back to plain Markdown as soon as you pause."

---

### [11:30 – 15:00] 3.4 Interactive Livewire 4 Vault Portals, Password Gates, Portal AI & `/llms.txt`

**[VISUAL / SCREEN]**
- In Synkk Dashboard → **Portals** → click **Create Portal**:
  - Vault: `Northstar Studio`
  - Name: `Northstar Studio Docs` | Slug: `northstar-docs`
  - Root Path: `04-Public-Docs` *(so ONLY public docs publish — never `03-Private` or `02-Clients`!)*
  - Layout: `docs` (then show switching to `bento`, `dashboard`, `minimal`)
  - Theme: `midnight-emerald` (mention all 5: `obsidian-noir`, `slate-luxe`, `midnight-emerald`, `paper-craft`, `amber-gold`)
  - Toggles: Public ON, Search ON, Graph ON, Backlinks ON, Popovers ON, **Ask This Portal AI** ON (limit `100`/day), **`llms.txt`** ON.
  - Optional: demonstrate adding an 8+ character bcrypt password gate and unlocking it!
- Open `/p/northstar-docs` in a new tab:
  - Test `Cmd+K` instant search, hover over a `[[wikilink]]` for a live popover, open the interactive Portal Graph View, and click **Ask This Portal** to ask `"How do AI tools read Northstar docs?"`.
  - Open `/p/northstar-docs/llms.txt` to show the clean machine-readable LLM index!
- Edit `04-Public-Docs/Welcome to Northstar.md` in Obsidian → sync → refresh `/p/northstar-docs` to show instant zero-build publishing!

**[SCRIPT]**
> "Want to publish your documentation, digital garden, or client handbook without paying extra for Obsidian Publish or fighting static site generators? Open **Portals** in the Synkk dashboard and click **Create Portal**.
>
> Pick `Northstar Studio`, set the slug to `northstar-docs`, and set **Root Path** to `04-Public-Docs`. That ensures *only* our public docs folder is exposed — your internal projects and private notes never touch the portal.
>
> You can mix **4 layouts** (`Docs`, `Bento`, `Dashboard`, `Minimal`) with **5 color themes** (`Obsidian Noir`, `Slate Luxe`, `Midnight Emerald`, `Paper Craft`, `Amber Gold`), keep it public, team-only, or behind a rate-limited bcrypt password gate.
>
> Open `/p/northstar-docs`. You get instant `Cmd+K` search, Obsidian callouts, `[[wikilink]]` hover popovers, backlinks, and an interactive 2D Graph View. Toggle on **Ask This Portal** for a rate-limited visitor Q&A drawer, and check out `/p/northstar-docs/llms.txt` — Synkk automatically publishes a clean `llms.txt` index for AI crawlers and coding assistants. Edit a note in Obsidian, hit sync, refresh — live immediately, zero build steps. *(Note: portals currently live under `/p/{slug}`; custom domain mapping is on the roadmap.)*"

---

### [15:00 – 19:30] 3.5 Zero-Knowledge E2EE + Local Ollama AI (100% Private Workflow)

**[VISUAL / SCREEN]**
- Create a second vault in Synkk Dashboard called `Personal Encrypted` (`personal-encrypted`) so `Northstar Studio` stays non-E2EE for the MCP/Portal demos.
- Point a test Obsidian vault (or switch Target Vault temporarily) to `personal-encrypted`.
- Open **Settings → Synkk Vault Sync → 🔒 Zero-Knowledge End-to-End Encryption (E2EE)**:
  1. Toggle **Enable Zero-Knowledge E2EE** ON.
  2. Type a strong **Vault Passphrase** (explain: stored in RAM only, never saved to `data.json`, no server recovery key!).
  3. Click **`Lock Vault with E2EE`** (`POST /vaults/personal-encrypted/e2ee/enable` sends only the random salt + encrypted `synkk-e2ee-verify-token-v1` check blob).
- Sync a secret note → open the Synkk Web Editor on `personal-encrypted` → show the **Unlock Vault with Passphrase** prompt (proving the server cannot read plaintext; decrypting happens strictly in browser RAM via WebCrypto!).
- Now configure **Zero-Knowledge E2EE Local AI** in Obsidian Settings:
  - `Local AI Provider`: **`Ollama (Local)`** *(also mention `OpenAI-Compatible` for LM Studio / LocalAI / BYOK)*
  - `Local AI Base URL`: `http://localhost:11434`
  - `Local AI Model`: `llama3.2`
  - Click **`Test Local AI`** → green connection confirmed!
- Click the **Sparkles** ribbon icon (`Synkk AI: Open Copilot Sidebar`) → point out the **`E2EE Local`** pill in the toolbar → ask a question about your encrypted notes! Explain it runs pure-JS BM25 + 1-hop `[[wikilink]]` graph expansion locally and sends excerpts strictly to `localhost:11434`.

**[SCRIPT]**
> "What if you have a vault of legal docs, medical notes, or private journals where not even the server operator should ever be able to read a single sentence? That's where **Zero-Knowledge E2EE** comes in.
>
> Create a dedicated vault — let's call it `Personal Encrypted` — and in Obsidian **Settings → Synkk Vault Sync**, toggle **Enable Zero-Knowledge E2EE**, enter a strong passphrase, and click **`Lock Vault with E2EE`**.
>
> Under the hood, Obsidian generates a 16-byte random salt, derives a non-extractable 256-bit **AES-GCM** key using **100,000 PBKDF2-SHA-256 iterations** via WebCrypto, encrypts a verification token, and sends only the salt and ciphertext to the server. Your passphrase stays in volatile RAM only — Synkk actively strips it from `data.json` so it's never written to disk, and there is **no server recovery key**, so store it safely in your password manager.
>
> Sync a note and open that vault in the web browser: Synkk prompts you to unlock it in browser RAM because the server only holds opaque ciphertext. Remember the exact zero-knowledge trade-off: file paths and sizes stay plaintext for sync routing, while server-side search, Portals, DLP, MCP, and cloud AI are automatically disabled on E2EE vaults.
>
> Does E2EE mean giving up AI? **No!** Scroll to **Zero-Knowledge E2EE Local AI** in Obsidian settings, point it at local **Ollama** (`http://localhost:11434`, model `llama3.2`) or LM Studio, and click **`Test Local AI`**. Open the **Synkk AI** sidebar — notice the badge automatically switched to **`E2EE Local`**. Ask a question, and the plugin runs local BM25 + 1-hop `[[wikilink]]` graph retrieval right on your machine and queries your local model. Even **Smart Conflict Merge** runs 100% locally in E2EE mode!"

---

### [19:30 – 23:30] 3.6 Server Vault AI Suite: BYOK / Managed AI, AI Sidebar, Proposal Inbox, Gardener & Team Digests

**[VISUAL / SCREEN]**
- Switch back to `Northstar Studio` (standard non-E2EE vault).
- Open **Team Settings → AI Add-On**:
  - Toggle **`Enable Synkk AI Add-On for this workspace`** ON.
  - Show the 3 provider modes: **BYOK** (OpenAI, Anthropic, Gemini, OpenRouter, custom OpenAI-compatible + monthly budget cap), **Ollama** (self-hosted server URL), or **Managed Cloud AI** ($12/workspace/mo; Cloud plans include $2 trial credit).
  - Under **Per-Vault AI Access & Excluded Folders**, set `Northstar Studio` to **`Server AI (Copilot, Hybrid Search, MCP)`** and add `03-Private/**` to **Excluded Folders** as defense-in-depth!
- In Obsidian:
  1. Open **`Synkk AI: Open Copilot Sidebar`** (Sparkles ribbon icon) → notice **`Cloud Copilot`** mode pill, thread switcher, `+ New`, `Inbox`, and `Gardener` buttons.
  2. Ask: *"Compare the beta cohort targets between Architecture Overview and Pricing Strategy, and propose an update to Launch Brief flagging the discrepancy."*
  3. Watch the live streaming response over Reverb with clickable `[[Architecture Overview]]` and `[[Pricing Strategy]]` citation chips and a **`📋 Review 1 proposed edit(s)`** button!
  4. Click **`Inbox`** (or status bar **`✨ 1 AI`** badge / Command Palette **`Synkk AI: Open Change Inbox (Proposals)`**).
  5. Inspect the colored line-by-line diff, show the collapsible **Edit proposed note content before approving** box, and click **`Approve & Sync`**!
  6. Click **`Gardener`** (or run **`Synkk AI: Run Vault Gardener`**) to watch it triage `00-Inbox/Raw Ideas.md` and suggest `[[wikilinks]]` as reviewable proposals!
  7. Back in **Team Settings → AI Add-On**, click **`Generate Digest Now`** to demonstrate **Team Digests** (delivered to a vault note, team feed, or opt-in email).

**[SCRIPT]**
> "Now switch back to our standard team vault, `Northstar Studio`, and unlock Synkk's opt-in **Vault AI Suite**.
>
> In **Team Settings → AI Add-On**, enable AI for the workspace. You are *never* locked into one model vendor: plug in your own **BYOK** keys for OpenAI, Anthropic, Google Gemini, or OpenRouter (with an optional monthly budget cap), point to a self-hosted **Ollama** server, or opt into the **$12/month Managed Cloud AI Add-On** (Cloud plans include a $2 trial credit to test immediately). Below that, opt in per vault and add `03-Private/**` to **Excluded Folders** so sensitive directories never even enter the FTS5 + vector search index.
>
> Back in Obsidian, click the **Sparkles** icon to dock the **Synkk AI** sidebar. Let's ask it to compare our beta cohort numbers across `Architecture Overview` and `Pricing Strategy` — remember we planted a subtle contradiction (`50` vs `100` pilot teams)?
>
> Watch it run multi-step hybrid search (`SQLite FTS5 BM25 + vector cosine similarity + [[wikilink]] graph traversal`), stream the answer over Reverb, cite both notes with clickable wikilink chips, *and* propose an edit to `Launch Brief.md`.
>
> Crucially, Synkk AI **never silently rewrites your notes**. Look at the **`✨ 1 AI`** badge in the status bar. Open the **AI Change Inbox**, inspect the colored line diff (you can even tweak the proposed text inline before approving), and click **`Approve & Sync`**.
>
> Finally, hit **`Gardener`** — or run **`Synkk AI: Run Vault Gardener`** — to triage unsorted notes like `00-Inbox/Raw Ideas.md`, suggest missing wikilinks, and flag contradictions, all safely staged in your Proposal Inbox. And back in Team Settings, click **`Generate Digest Now`** (or schedule daily/weekly digests) to summarize team activity straight to a Markdown note, your team feed, or opt-in email."

---

### [23:30 – 27:30] 3.7 Permissioned Remote MCP Server (`POST /api/v1/mcp`)

**[VISUAL / SCREEN]**
- Open **Team Settings → AI Add-On → Permissioned Remote MCP Server**.
- Click **Create MCP Token**:
  - Name: `Cursor / Claude Agent`
  - Scope: **`mcp_propose`** *(explain all 3 scopes: `mcp_read`, `mcp_propose`, `mcp_write` + per-vault `Allow direct MCP writes` safeguard, and highlight that MCP tokens do NOT consume Obsidian device seats!)*.
  - Copy the generated `synkk_...` Bearer token (shown once).
- Show pasting the exact JSON snippet into Claude Desktop / Cursor / Windsurf / Antigravity MCP settings:
  ```json
  {
    "mcpServers": {
      "synkk": {
        "url": "https://your-synkk-domain.com/api/v1/mcp",
        "headers": {
          "Authorization": "Bearer synkk_your_mcp_token"
        }
      }
    }
  }
  ```
- Run a live prompt in Claude Desktop / Cursor / Antigravity:
  1. *"List my Synkk vaults and search `Northstar Studio` for our Apollo launch checklist."* → invokes `synkk_list_vaults` & `synkk_search_notes`.
  2. *"Read `Launch Brief.md`, traverse its graph links, and propose adding a post-launch retrospective task."* → invokes `synkk_read_note`, `synkk_traverse_graph`, and `synkk_propose_note`.
- Switch back to **Obsidian** → watch the status bar badge light up (`✨ 1 AI`) → open the **AI Change Inbox** and approve the edit proposed by Claude / Cursor!

**[SCRIPT]**
> "Now for arguably Synkk's biggest power-user superpower: the built-in **Permissioned Remote MCP Server** at `POST /api/v1/mcp`.
>
> Scroll to the bottom of **Team Settings → AI Add-On** and create an **MCP Token**. Notice two awesome security details:
> 1. MCP tokens **do not consume your Obsidian device seats**.
> 2. Every MCP call respects your exact **Path ACLs**, vault excluded folders, and blocks E2EE vaults automatically — AND you choose one of three scopes: **`mcp_read`** (read/search only), **`mcp_propose`** (read + stage diffs in the Proposal Inbox), or **`mcp_write`** (which *also* requires explicitly enabling *Allow direct MCP writes* on that specific vault).
>
> Let's mint an `mcp_propose` token, copy the JSON config block straight into Claude Desktop or Cursor, and watch all **7 native MCP tools** in action (`synkk_list_vaults`, `synkk_search_notes`, `synkk_read_note`, `synkk_traverse_graph`, `synkk_recent_activity`, `synkk_propose_note`, and `synkk_write_note`).
>
> Look at that: Claude searches our vault, traverses the `[[wikilink]]` graph, and calls `synkk_propose_note` — and two seconds later, the diff pops up right inside **Obsidian's AI Change Inbox** waiting for our human approval. Your external AI coding and research agents now share live, permissioned memory with your Obsidian vault without ever bypassing your security boundaries."

---

### [27:30 – 29:30] 3.8 Self-Hosting Operations, Super Admin & Wrap-Up

**[VISUAL / SCREEN]**
- Briefly open `/admin` (Super Admin Dashboard on self-hosted instance) showing the 8 tabs (`Overview`, `Analytics`, `Tenants`, `Users`, `Telemetry`, `Licenses`, `System`, `AI Ops` with global kill switch & daily USD spend cap).
- Show a clean backup command overlay:
  ```bash
  # Consistent online SQLite backup + vault blob backup
  docker exec synkk-app sqlite3 /var/www/html/storage/app/database.sqlite ".backup '/var/www/html/storage/app/backup.sqlite'"
  # Back up the synkk_storage Docker volume (/var/www/html/storage) and .env.production (APP_KEY!)
  ```
- Closing card with links: `synkk.space`, `synkk.space/documentation`, `synkk.space/p/synkk-docs`, and GitHub repos.

**[SCRIPT]**
> "If you're self-hosting Synkk for your company or homelab, three quick operational power tips before we wrap up:
> 1. **Super Admin Console (`/admin`):** Your bootstrapped owner account unlocks the `/admin` console where you can inspect tenant storage, manage quotas, review global DLP telemetry, clear caches, and even set global daily AI spend caps or flip an instant AI kill switch under **AI Ops**.
> 2. **Backups & S3:** Because Synkk runs on embedded SQLite WAL inside `/var/www/html/storage`, backing up your entire deployment is just backing up your `synkk_storage` volume (using `sqlite3 ... .backup` for clean SQLite snapshots) plus your `.env.production` file — or set `SYNKK_STORAGE_DISK=s3` to offload vault file blobs to S3-compatible object storage.
> 3. **SMTP:** Don't forget to configure SMTP credentials in `.env.production` before sending team invitations, password resets, or email digests.
>
> That's the complete tour — from installing Obsidian all the way to Path ACLs, Zero-Knowledge E2EE with local Ollama, Livewire Portals, and a permissioned Remote MCP Server. Grab the `Northstar Studio` example vault from the description, try Synkk free via self-hosting or the 30-day Cloud trial at `synkk.space`, and let me know in the comments which workflow you're setting up first!"

---

## Quick Reference: Every Obsidian Command & Ribbon Icon in Plugin `v1.1.1`

| Surface | Exact Label in Obsidian (`v1.1.1`) |
|---|---|
| Ribbon Icon 1 (`synkk-octopus`) | `Synkk: Sync Now` |
| Ribbon Icon 2 (`split`) | `Synkk: Visual Conflict Sandbox` |
| Ribbon Icon 3 (`sparkles`) | `Synkk AI: Open Copilot Sidebar` |
| Command Palette | `Synchronize Now` |
| Command Palette | `Visual Conflict Sandbox: Reconcile Notes` |
| Command Palette | `Ghost Files: Hydrate active file on-demand` |
| Command Palette | `Ghost Files: Dehydrate active file to ghost stub` |
| Command Palette | `Transport Relay: Check server transport status` |
| Command Palette | `Vault Copilot: Ask a question (RAG)` |
| Command Palette | `Vault Copilot: Re-index vector embeddings` |
| Command Palette | `Synkk AI: Open Copilot Sidebar` |
| Command Palette | `Synkk AI: Open Change Inbox (Proposals)` |
| Command Palette | `Synkk AI: Run Vault Gardener` |
| Command Palette | `Vault Migration: Run Pre-Flight Diagnostic & Migration Wizard` |
