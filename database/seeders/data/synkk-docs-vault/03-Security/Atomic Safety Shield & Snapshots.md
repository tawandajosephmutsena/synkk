---
title: "Atomic Safety Shield & Snapshots"
description: "Hard deletion limits and automatic local snapshots protecting your knowledge base."
tags:
  - safety-shield
  - snapshots
  - data-loss-prevention
  - recovery
---

# 🛡️ Atomic Safety Shield & Snapshots

Sync tools must adhere to the first rule of medicine: *Primum non nocere* (First, do no harm).

Synkk implements an active defense system against accidental mass file deletion and sync corruption.

---

## 🛑 Guard Thresholds

1. **Percentage Threshold**: If a sync pulse proposes deleting more than **20%** of your tracked vault files, the sync is halted instantly.
2. **Bulk Count Limit**: If a deletion set contains more than **10 files** simultaneously, the shield engages unless an explicit one-time safety override is toggled.
3. **Audit Trail**: Every file deletion generates a soft-delete tombstone record with timestamp, user ID, device name, and commit version.

---

## 📸 Automatic Local Snapshots

Before any remote modification or deletion is applied to your local disk:
- The Obsidian plugin writes an exact binary snapshot of the local note into `.synkk/snapshots/YYYY-MM-DD/`.
- If an accidental overwrite occurs on another device, you can restore previous versions in one click from the Synkk Web Dashboard or directly from your local snapshot folder.

Next: Publish your notes online with [[04-Portals/Interactive Livewire Vault Portals|Interactive Livewire Vault Portals]].
