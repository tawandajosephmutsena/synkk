---
title: "Zero-Knowledge End-to-End Encryption"
description: "Client-side WebCrypto AES-256-GCM encryption (PBKDF2 100k) with RAM-only passphrases, exact metadata boundaries, and Local E2EE AI."
tags:
  - security
  - encryption
  - e2ee
  - privacy
---

# 🔒 Zero-Knowledge End-to-End Encryption (E2EE)

For sensitive corporate IP, medical records, journalism, or personal journals, Synkk provides opt-in **client-side WebCrypto E2EE** per vault.

---

## 🛡️ Cryptographic Architecture

```
Client Device (Obsidian Plugin or Web Editor)
  │
  ├── User Passphrase + 16-Byte Random Vault Salt
  │     │ (PBKDF2-HMAC-SHA256, 100,000 iterations)
  │     ▼
  ├── Non-Extractable 256-bit AES-GCM CryptoKey (Volatile RAM Only)
  │     │
  │     ├── Encrypts Markdown Notes & Binary Attachments (96-bit IV + 128-bit Auth Tag)
  │     └── Encrypts Yjs CRDT Updates & Checkpoints with AAD:
  │         synkk-v2:{vaultId}:{documentId}:{purpose}:2
  │
  └── Transmits Ciphertext Envelope to Server (format_version: 2)
        │
        ▼
Synkk Server Storage (Stores Opaque Ciphertext Blobs Only)
```

---

## 🔍 Exact Zero-Knowledge Boundaries (Truthful Disclosure)

- **Encrypted client-side:** All synced Markdown note bodies, binary attachments, versioned file blobs, and Yjs CRDT collaboration updates/checkpoints.
- **Passphrase handling:** Your passphrase is **never written to disk** (stripped from `data.json` on save and load) and never sent to the server. You re-enter it in RAM after restarting Obsidian or reloading the web editor tab. **Important:** There is no server-side recovery key—store your vault passphrase in a trusted password manager.
- **Plaintext metadata on server:** Vault names/slugs, **file paths/filenames**, sizes, version numbers, timestamps, and real-time cursor awareness whispers remain plaintext so Synkk can route manifests, enforce Path ACLs, and coordinate sync.
- **What works on E2EE vaults:** Whole-file sync, 25-version history & restore, in-browser Web Editor (after unlocking in RAM), encrypted Yjs collaboration, and **Local AI via Ollama / LM Studio** inside the Obsidian plugin.
- **What is intentionally disabled on E2EE vaults:** Server-side Hybrid Search / RAG, Vault AI Agent, Proposal Inbox, Vault Gardener, Team Digests, Remote MCP Server (`HTTP 403` / `-32003`), Plaintext DLP scanning, and Public Vault Portals.

Next: Learn about [[03-Security/Role-Based Scoped Permissions|Team Roles & Path-Level ACLs]].
