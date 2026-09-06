# Synkk Dual-Repository & SaaS Architecture Strategy

This document defines the production repository segregation, multi-tenant SaaS architecture, Super Admin controls, and open-source Community Edition (CE) synchronization workflows for Synkk.

---

## 1. Dual-Repository Topology

Synkk follows the industry-standard **Open-Core** pattern (utilized by GitLab, PostHog, Cal.com, Mattermost, and Metabase):

```
                               ┌──────────────────────────────────────────────┐
                               │  tawandajosephmutsena/synkk-saas (Private)   │
                               │  • Super Admin Platform Dashboard (/admin)    │
                               │  • Lemon Squeezy / Stripe SaaS Billing        │
                               │  • In-App DLP Secret Scanning                │
                               │  • Path-Level ACLs & Fleet Remote Wipe        │
                               │  • CRDT Multiplayer & Vector RAG Engines      │
                               └──────────────────────┬───────────────────────┘
                                                      │
                                   [Automated Sanitization & Packaging]
                                      php artisan synkk:package-ce
                                                      │
                                                      ▼
                               ┌──────────────────────────────────────────────┐
                               │     tawandajosephmutsena/synkk (Public)      │
                               │  • Free Community Edition (CE) under MIT     │
                               │  • SQLite WAL whole-file sync engine         │
                               │  • Full Obsidian desktop & mobile plugin     │
                               │  • Web Markdown editor & 2D graph            │
                               │  • Self-hosted Docker / Herd single server   │
                               └──────────────────────────────────────────────┘
```

---

## 2. Settings & Limitations Matrix

| Capability / Setting | Community Free (CE) | Pro Lifetime Deal (LTD) | Synkk Cloud Managed SaaS |
| :--- | :--- | :--- | :--- |
| **GitHub Repository** | **Public** (`synkk`) | **Private Releases** | **Private Service** (`synkk-saas`) |
| **License** | Open Source (MIT) | Commercial / Proprietary | Commercial SaaS |
| **Super Admin Dashboard** | ❌ Omitted | ❌ Omitted | ✅ Full Control (`/admin`) |
| **Multi-Tenancy** | Single workspace | Up to 10 team seats | Unlimited multi-tenant teams |
| **Max Vaults per Team** | **1 Vault** | **15 Vaults** (customizable) | **50+ Vaults** |
| **Max Devices per User** | **3 Devices** | **25 Devices** | **100+ Devices** |
| **Storage Cap** | **1 GB** (local disk) | **15 GB** (local / S3) | **50 GB – 500 GB+** (S3 / R2) |
| **Path-Level ACLs** | ❌ | ✅ Granular (`clients/**`) | ✅ Granular |
| **In-App DLP Scanner** | ❌ | ✅ AWS, OpenAI, SSH keys | ✅ Real-time enterprise DLP |
| **Fleet Remote Wipe** | ❌ | ✅ 1-click token revocation | ✅ Instant device wipe |
| **Real-Time CRDT Multiplayer** | ❌ | ⚠️ Beta preview | ✅ Live collaborative editing |
| **Zero-Knowledge E2EE** | ❌ | ⚠️ Beta preview | ✅ Client WebCrypto E2EE |
| **Customer Impersonation** | ❌ | ❌ | ✅ 1-click support mode |

---

## 3. Git Remotes Configuration

For day-to-day development on your local machine:

```bash
# In your working repository:
git remote rename origin saas
git remote add community git@github.com:tawandajosephmutsena/synkk.git

# Verify remotes
git remote -v
# saas        git@github.com:tawandajosephmutsena/synkk-saas.git (fetch & push)
# community   git@github.com:tawandajosephmutsena/synkk.git (fetch & push)
```

---

## 4. Synchronization Workflows

### A. Automated Downstream Mirroring (CI/CD)
Whenever code is committed and pushed to `main` on `tawandajosephmutsena/synkk-saas`:
1. `.github/workflows/sync-community.yml` triggers.
2. Runs `php artisan synkk:package-ce`.
3. Strips `/resources/views/pages/admin/` and proprietary SaaS files.
4. Pushes the clean open-source distribution directly to `tawandajosephmutsena/synkk:main`.

### B. Manual Command-Line Package Export
To build a sanitized distribution package locally at any time:
```bash
php artisan synkk:package-ce
# Outputs: storage/app/exports/synkk-community-ce.zip
```

### C. Ingesting Community Pull Requests & Bugfixes
When an open-source contributor submits a bug fix to the public community repo:
```bash
git fetch community
git checkout -b community-fix community/main
# Review and test fix
git checkout main
git merge --no-ff community-fix
git push saas main
```

---

## 5. Super Admin Platform Operations

### Accessing the Control Center
- Route: `/admin` or `/admin/dashboard`
- Guarded by `EnsureSuperAdmin` middleware.
- Only users with `is_super_admin = true` can access.

### Creating a Super Admin User via Tinker
```bash
php artisan tinker --execute 'App\Models\User::where("email", "your-email@domain.com")->update(["is_super_admin" => true]);'
```

### Support Impersonation Mode
1. In the **Users** tab of `/admin`, locate the customer needing assistance.
2. Click **"Login as User"**.
3. Synkk signs you in as that user and redirects to their workspace.
4. A persistent gradient banner displays at the top:
   `"Support Impersonation Mode: You are viewing Synkk as [User Name]"`
5. Click **"Exit Impersonation"** to safely restore your Super Admin session.

### Tenant Deep-Dive & Emergency Fleet Revocation
1. In the **Tenants** tab of `/admin`, click **"Inspect"** on any organization.
2. View real-time tenant stats: active vaults, total files, storage consumed, team members, and the complete connected device fleet.
3. If a team experiences a credential leak or device compromise, click **"Emergency Revoke All Devices"** to instantly wipe all active tokens and disconnect the fleet.

### Real-Time Fleet Telemetry & In-App DLP Audit
1. Navigate to the **Telemetry & DLP** tab (`/admin`).
2. Live streaming audit log of all vault sync events across the entire platform.
3. Toggle **"Secrets Only"** to isolate changes where secrets (AWS access keys, OpenAI tokens, private SSH keys) were detected in notes.
4. Review affected vault paths and device origins, and click **"Acknowledge & Dismiss"** once resolved.

### Commercial License Generator & Batch Provisioning
1. Navigate to the **Licenses** tab (`/admin`).
2. Choose tier (`Pro LTD` or `Cloud Managed SaaS`).
3. Generate individual keys or batch-generate up to 50 license keys simultaneously for enterprise distributor or lifetime deal campaigns.
4. Directly activate or revoke licenses on specific tenants.

### System & Infrastructure Operations
1. Navigate to the **System & Ops** tab (`/admin`).
2. **Flush Application Cache**: Instant 1-click execution of `cache:clear` to purge stale route, config, and Redis caches.
3. **Prune Deleted Snapshots**: Execute automated pruning of soft-deleted vault file versions older than 30 days (`vaults:prune-deleted --days=30`).
4. **Database Table Metrics**: Live row counts across tenants, vaults, file versions, sync logs, and device tokens.
