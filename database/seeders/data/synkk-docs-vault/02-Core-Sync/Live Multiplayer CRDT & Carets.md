---
title: "Live Multiplayer CRDT & Carets"
description: "Real-time conflict-free co-authoring with CodeMirror 6 carets and Yjs."
tags:
  - crdt
  - collaboration
  - realtime
  - yjs
---

# 👥 Live Multiplayer CRDT & Carets

Synkk is not just a file synchronization utility—it is a **real-time collaborative workspace**.

When two team members open the same note simultaneously—one in the Obsidian desktop client, another in the mobile app, and a third in the Synkk Web Editor—they can co-author simultaneously without conflicts.

---

## ⚡ How Synkk Multiplayer Works

1. **Yjs State Vectors**: Notes are represented as Conflict-Free Replicated Data Types (CRDTs). Each keystroke produces a deterministic binary update vector.
2. **CodeMirror 6 Presence Extension**: Team members' live cursors, selections, and user avatars appear directly inside Obsidian with custom color badges.
3. **Laravel Echo & Reverb WebSockets**: Updates broadcast over low-latency WebSockets directly to active peers in milliseconds.
4. **Snapshot Consolidation**: When all peers leave a note, the CRDT document state is automatically serialized into standard Markdown plaintext and saved to disk.

---

## 🔀 3-Way Merge Conflict Sandbox

In the rare event that two devices edit a note completely offline and reconnect simultaneously, Synkk's **3-Way Conflict Sandbox** protects both sides:
- Preserves the common base ancestor revision.
- Analyzes hunks from local and remote edits.
- Auto-resolves non-overlapping blocks cleanly.
- If overlapping lines conflict, Synkk preserves the original note and forks the second version into a named conflict copy (`note.sync-conflict-TIMESTAMP.md`), ensuring **zero data is ever overwritten or lost**.

Next: Learn how mobile devices handle multi-gigabyte media with [[02-Core-Sync/Mobile Ghost Files & Streaming|Mobile Ghost Files & Streaming]].
