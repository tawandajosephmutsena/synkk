---
title: "Live Multiplayer CRDT & Carets"
description: "Opt-in real-time Yjs co-authoring over Laravel Reverb plus Visual Conflict Sandbox and AI Smart Merge."
tags:
  - crdt
  - collaboration
  - realtime
  - yjs
  - conflicts
---

# 👥 Live Multiplayer CRDT & Visual Conflict Sandbox

Synkk defaults to rock-solid **whole-file SHA-256 synchronization** with a Visual Conflict Sandbox for offline edits, and offers **opt-in real-time Yjs CRDT collaboration** when teammates want to co-author Markdown notes simultaneously.

---

## ⚡ How Opt-In Real-Time Collaboration Works

> [!NOTE] Opt-In in Obsidian Plugin (`v1.1.1`)
> Inside Obsidian, **Enable Real-Time Collaboration (CRDT)** is **off by default** (`realtimeCollaboration: false`) to guarantee maximum atomic whole-file stability. Toggle it on in **Settings → Synkk Vault Sync → Real-Time Multiplayer Collaboration** whenever your team needs live co-editing.

1. **Durable Yjs Journal**: Opening a `.md` note joins a server-backed collaboration room (`POST /api/v1/vaults/{slug}/collab/join`), catches up missed binary updates (`GET .../collab/catch-up`), and binds a `Y.Doc` to the active CodeMirror 6 editor.
2. **CodeMirror 6 Live Carets**: Remote cursor positions and collaborator badges broadcast via Laravel Reverb private channel whispers (`vault-collaboration.{documentId}`).
3. **E2EE AAD Protection**: On E2EE vaults, Yjs updates and checkpoints are encrypted client-side using WebCrypto AES-256-GCM bound to document AAD (`synkk-v2:{vaultId}:{documentId}:{purpose}:2`).
4. **Durable Markdown Checkpointing**: After edits settle (3-second debounce) or on leaving a note, state flushes cleanly back to standard `.md` files and posts a compacted checkpoint (`POST .../collab/checkpoint`).

---

## 🔀 Visual Conflict Sandbox & AI Smart Merge

When two devices edit the same note offline, Synkk never silently overwrites either side:
- Pull conflicts save the remote version as `<note>.sync-conflict-<timestamp>.md`; push collisions save `<note>.conflict-<user>-<timestamp>.md`.
- Open the **Visual Conflict Sandbox** (split ribbon icon or Command Palette: `Visual Conflict Sandbox: Reconcile Notes`) to inspect **Canonical** vs **Conflict Copy** side-by-side.
- Choose **Keep Canonical Version**, **Keep Conflict Version**, **Combine Both**, or **✨ Smart Merge with AI** (uses server AI on standard vaults, or your local Ollama/OpenAI-compatible model on E2EE vaults).
- Review/edit the reconciled preview and click **Reconcile & Clean Up Conflict** to save, remove the conflict copy, and sync.

Next: Learn how mobile devices handle large media with [[02-Core-Sync/Mobile Ghost Files & Streaming|Mobile Ghost Files & Attachment Hydration]].
