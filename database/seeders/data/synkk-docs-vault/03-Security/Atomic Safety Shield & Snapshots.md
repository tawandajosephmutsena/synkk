---
title: "Atomic Safety Shield & Snapshots"
description: "Configurable deletion circuit breaker, local pre-mutation snapshots, and 25-version server history."
tags:
  - safety-shield
  - snapshots
  - data-loss-prevention
  - recovery
---

# 🛡️ Atomic Safety Shield & Snapshots

Sync engines must follow one rule above all: never silently destroy a user's vault.

---

## 🛑 Deletion Circuit Breaker

- **Client-Side Threshold (Default: `10%`):** Before applying incoming remote deletions or pushing outgoing local deletions, the Obsidian plugin compares the deletion ratio against **Deletion safety threshold** (`deletionThresholdPercent`, configurable from `1%` to `100%` in **Settings → Synkk Vault Sync → Atomic Safety Shield**).
- **One-Time Guarded Override:** If a sync exceeds the threshold, synchronization halts immediately and notifies you in the status bar. Review the pending changes and either toggle **Allow next guarded sync** in settings or click the Safety Shield status bar prompt to authorize that single run.
- **Pre-Flight Guidance:** The server's `POST /api/v1/vaults/{slug}/preflight` endpoint also reports recommended bulk-deletion guardrails (`20%` / `>10 files`) during initial migration checks.

---

## 📸 Local Snapshots & 25-Version Server Rollback

- **Local Pre-Mutation Backups:** Before applying a remote overwrite or remote deletion to your local vault, the Obsidian plugin writes a backup into `.synkk/snapshots/<ISO-timestamp>/<path>`.
- **Server Version History:** Every accepted update archives the prior revision (preserving E2EE IV/tag metadata on encrypted vaults), retaining up to **25 versions per file** by default (`SYNKK_VERSION_RETENTION_LIMIT`). Restore any version in one click from the Synkk web workspace.
- **Soft-Delete Tombstones:** Deleted files record tombstones in the changelog and are pruned after 30 days (`vaults:prune-deleted --days=30`). Re-uploading at the same path resurrects the file cleanly.

Next: Learn about [[03-Security/Role-Based Scoped Permissions|Team Roles & Path-Level ACLs]].
