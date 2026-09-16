---
title: "Welcome to Synkk"
description: "Self-hosted team vault sync, real-time collaboration, and publishing engine for Obsidian."
tags:
  - synkk
  - documentation
  - overview
  - obsidian
---

# ⚡ Welcome to Synkk

> [!NOTE] Synkk Philosophy
> **Keep Markdown, permissions, versions, and trusted devices together on infrastructure you control.**
> Synkk is a self-hosted team vault synchronization server, multi-user CRDT collaboration engine, and interactive publishing portal designed specifically for Obsidian.

---

## 🧭 Why Synkk?

Obsidian users and research teams frequently face a painful dilemma:
- **Cloud proprietary silos** (Notion, Google Docs) force you to give up local plaintext files.
- **Generic cloud sync** (iCloud, Dropbox, OneDrive) causes silent file corruption, merge collisions, and lacks granular team path permissions.
- **Git setups** are clunky on mobile devices and intimidating for non-technical team members.

**Synkk solves this completely:**
1. **Local-First Plaintext**: Your notes remain standard Markdown (`.md`) files on your filesystem.
2. **Instant QR Pairing**: Pair any iPhone, Android, Mac, or Windows device in under 3 seconds using [[01-Quickstart/Instant 3-Second QR Pairing|Instant QR Pairing]].
3. **Pre-Flight Migration Engine**: Simulate your first sync and catch friction traps before sending a single byte via [[02-Core-Sync/First-Sync Pre-Flight & Migration Engine|Pre-Flight Engine]].
4. **Zero-Knowledge E2EE**: Secure notes with client-side WebCrypto AES-256-GCM encryption with [[03-Security/Zero-Knowledge End-to-End Encryption|E2EE]].
5. **Interactive Livewire Portals**: Publish your vaults as high-end websites in 1-click via [[04-Portals/Interactive Livewire Vault Portals|Livewire Portals]].
6. **Live Multiplayer CRDT**: Co-author notes with team carets and presence using [[02-Core-Sync/Live Multiplayer CRDT & Carets|Multiplayer CRDT]].
7. **Vector RAG Copilot**: Query your notes semantically with citations via [[05-AI-Copilot/Vault Copilot & Vector RAG Server|Vault Copilot]].

---

## 🗺️ Documentation Map

- **Getting Started**:
  - [[01-Quickstart/Installation & Deployment|Installation & Deployment Guide]]
  - [[01-Quickstart/Instant 3-Second QR Pairing|3-Second Instant QR Pairing]]
- **Core Sync Architecture**:
  - [[02-Core-Sync/First-Sync Pre-Flight & Migration Engine|First-Sync Pre-Flight & Migration Engine]]
  - [[02-Core-Sync/Live Multiplayer CRDT & Carets|Live Multiplayer CRDT & Carets]]
  - [[02-Core-Sync/Mobile Ghost Files & Streaming|Mobile Ghost Files & Attachment Streaming]]
- **Security & Permissions**:
  - [[03-Security/Zero-Knowledge End-to-End Encryption|Zero-Knowledge End-to-End Encryption]]
  - [[03-Security/Role-Based Scoped Permissions|Role-Based Scoped Path Permissions]]
  - [[03-Security/Atomic Safety Shield & Snapshots|Atomic Safety Shield & Snapshots]]
- **Publishing & Portals**:
  - [[04-Portals/Interactive Livewire Vault Portals|Interactive Livewire Vault Portals]]
- **AI & Knowledge Systems**:
  - [[05-AI-Copilot/Vault Copilot & Vector RAG Server|Vault Copilot & Vector RAG Server]]
- **Operations & Self-Hosting**:
  - [[06-Self-Hosting/Docker & Turnkey Setup|Docker, Turnkey Installer & Production Setup]]
  - [[07-API-Reference/REST API Protocol v2|REST API Reference (Protocol v2)]]
