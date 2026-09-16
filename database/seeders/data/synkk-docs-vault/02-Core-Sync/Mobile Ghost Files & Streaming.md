---
title: "Mobile Ghost Files & Streaming"
description: "Virtual attachment stubs and on-demand streaming for phones and tablets."
tags:
  - mobile
  - ghost-files
  - storage
  - streaming
---

# 👻 Mobile Ghost Files & On-Demand Streaming

Obsidian vaults frequently accumulate massive attachment libraries: 4K screen recordings, multi-megabyte PDFs, keynote decks, and lossless audio files.

Syncing a 25 GB media folder to an iPhone or Android device quickly exhausts phone storage and drains battery life.

---

## ⚡ How Ghost Files Work

When **Mobile Ghost Files** is enabled in settings:
1. When syncing to mobile devices, any attachment larger than your threshold (default: **5 MB**) is dehydrated into a lightweight **Ghost Stub** (`< 1 KB`).
2. The stub file contains cryptographic metadata:
   ```markdown
   <!-- synkk:ghost path="media/architecture-overview.mp4" size="48291040" mime="video/mp4" sha256="..." -->
   > [!NOTE] 👻 Synkk Ghost Attachment
   > **File:** `media/architecture-overview.mp4` (46.1 MB)
   > **Status:** Lightweight stub on this device. Content will stream on demand.
   ```
3. Your notes, canvases, and links continue to function normally without broken references.
4. When you tap to view or play the media, Synkk streams the full binary content on demand from the server!

Next: Learn about our client-side encryption architecture with [[03-Security/Zero-Knowledge End-to-End Encryption|Zero-Knowledge End-to-End Encryption]].
