---
title: "First-Sync Pre-Flight & Migration Engine"
description: "Simulate, protect, and optimize your initial vault sync before sending a single byte."
tags:
  - preflight
  - migration
  - data-safety
  - synchronization
---

# 🛫 First-Sync Pre-Flight & Migration Engine (Milestone C)

> [!WARNING] The First-Sync Anxiety Trap
> Syncing an existing vault with 5,000+ notes for the first time is stressful. Other tools often cause silent file deletions, media bloat crashes, or thousands of duplicate conflict notes.

Synkk's **First-Sync Pre-Flight & Migration Engine** turns initial sync into a safe, deterministic, zero-data-loss transition.

---

## 🧭 The 4-Step Migration Wizard

Open the wizard via the Obsidian command palette (`Vault Migration: Run Pre-Flight Diagnostic & Migration Wizard`) or from **Settings → Launch Migration Wizard**.

```
┌────────────────────────────────────────────────────────────┐
│          SYNKK 4-STEP PRE-FLIGHT MIGRATION WIZARD          │
└────────────────────────────────────────────────────────────┘
  Step 1: Health Diagnostic & Friction Scan
  ├── Classifies files by type (Markdown, Canvases, Images, Media)
  ├── Flags traps: .trash/, .git/, oversized files (>25MB)
  └── 1-Click "⚡ Auto-Sanitize Unsafe File Names" (: * ? " < > |)
                              │
                              ▼
  Step 2: Smart Exclusion Presets & Live Savings
  ├── 1-Click Toggles: Exclude .trash & .git, enable Ghost Files
  └── Live Bandwidth & Storage Saved Calculator
                              │
                              ▼
  Step 3: Server Simulation Dry-Run (Zero Data Transferred)
  ├── Calls POST /api/v1/vaults/{slug}/preflight
  ├── Checks team cloud storage quota & calculates byte deficit
  └── Previews exact diff: Files to Upload, Pull, and Identical Skips
                              │
                              ▼
  Step 4: Guarded Execution & Verification Seal
  ├── Animated gradient progress bar with live throughput (KB/s)
  └── Issues Vault Verified Completion Seal & arms Atomic Safety Shield
```

---

## 🛡️ Atomic Safety Shield

The **Atomic Safety Shield** is a hard server and client safeguard:
- If an incoming or outgoing sync pulse requests the deletion of more than **20%** of your vault (or more than 10 files at once), Synkk halts the sync automatically.
- A notification prompts the user to review the changes before granting a one-time guarded override.
- Before any remote file overwrite or deletion occurs, the plugin takes an automatic local snapshot inside `.synkk/snapshots/`.

Next: Learn about co-authoring notes in real time with [[02-Core-Sync/Live Multiplayer CRDT & Carets|Live Multiplayer CRDT & Carets]].
