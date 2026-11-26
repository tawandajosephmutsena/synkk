---
title: "Welcome to Synkk"
description: "Self-hosted team vault sync, Vault AI & Remote MCP, opt-in CRDT collaboration, and Livewire 4 publishing portals for Obsidian."
tags:
  - synkk
  - documentation
  - overview
  - obsidian
---

# ⚡ Welcome to Synkk

> [!NOTE] Synkk Philosophy
> **Keep Markdown, permissions, versions, AI memory, and trusted devices together on infrastructure you control.**
> Synkk is an MIT open-source team vault synchronization server, web workspace, opt-in Yjs collaboration engine, Livewire 4 publishing portal, and permissioned AI/MCP server built specifically for Obsidian.

---

## 🧭 Why Synkk?

Obsidian users and teams frequently face a painful trade-off:
- **Cloud monoliths** (Notion, Confluence, Google Docs) lock your knowledge inside proprietary databases.
- **Generic file sync** (iCloud, Dropbox, OneDrive) lacks path-level team ACLs, versioned conflict workflows, and web collaboration.
- **Git setups** break down on mobile devices and inject raw `<<<<<<< HEAD` markers into Markdown for non-technical teammates.
- **Proprietary Obsidian Sync** charges per-seat recurring fees and offers all-or-nothing vault sharing with no web workspace, portals, or MCP server.

**Synkk solves this end to end:**
1. **Local-First Plaintext**: Your notes remain standard Markdown (`.md`) files on your disk.
2. **One-Scan QR Pairing**: Pair any iPhone, Android, Mac, Windows, or Linux device in seconds via [[01-Quickstart/Instant 3-Second QR Pairing|Instant QR Pairing]].
3. **Pre-Flight Migration Engine**: Scan friction traps, sanitize cross-platform filenames, and dry-run quotas before syncing via [[02-Core-Sync/First-Sync Pre-Flight & Migration Engine|Pre-Flight Engine]].
4. **Zero-Knowledge E2EE**: Protect sensitive vaults with client-side WebCrypto AES-256-GCM encryption via [[03-Security/Zero-Knowledge End-to-End Encryption|E2EE]].
5. **Interactive Livewire 4 Portals**: Publish any non-E2EE vault or subfolder at `/p/{slug}` with `/llms.txt` and Portal AI via [[04-Portals/Interactive Livewire Vault Portals|Livewire Portals]].
6. **Visual Conflict Sandbox & Opt-In CRDT**: Reconcile offline conflicts safely with AI Smart Merge, or enable live Yjs collaboration via [[02-Core-Sync/Live Multiplayer CRDT & Carets|Multiplayer CRDT]].
7. **Vault AI Suite & Permissioned Remote MCP**: Run local Ollama (even on E2EE vaults), BYOK, or Managed Cloud AI with a human-approved Proposal Inbox and 7-tool MCP server via [[05-AI-Copilot/Vault Copilot & Vector RAG Server|Vault AI Suite]] and [[05-AI-Copilot/Remote MCP Server & Agent Automation|Remote MCP Server]].

---

## 🗺️ Documentation Map

- **Getting Started**:
  - [[01-Quickstart/Installation & Deployment|Installation & Deployment Guide]]
  - [[01-Quickstart/Instant 3-Second QR Pairing|Instant QR & Deep-Link Pairing]]
- **Core Sync Architecture**:
  - [[02-Core-Sync/First-Sync Pre-Flight & Migration Engine|First-Sync Pre-Flight & Migration Engine]]
  - [[02-Core-Sync/Live Multiplayer CRDT & Carets|Live Multiplayer CRDT & Visual Conflict Sandbox]]
  - [[02-Core-Sync/Mobile Ghost Files & Streaming|Mobile Ghost Files & Attachment Hydration]]
- **Security & Permissions**:
  - [[03-Security/Zero-Knowledge End-to-End Encryption|Zero-Knowledge End-to-End Encryption]]
  - [[03-Security/Role-Based Scoped Permissions|Team Roles & Path-Level ACLs]]
  - [[03-Security/Atomic Safety Shield & Snapshots|Atomic Safety Shield & Snapshots]]
- **Publishing & Portals**:
  - [[04-Portals/Interactive Livewire Vault Portals|Interactive Livewire 4 Vault Portals]]
- **AI & Remote MCP**:
  - [[05-AI-Copilot/Vault Copilot & Vector RAG Server|Vault AI Suite, Hybrid Search & Local E2EE AI]]
  - [[05-AI-Copilot/Remote MCP Server & Agent Automation|Remote MCP Server & Agent Automation]]
- **Operations & Self-Hosting**:
  - [[06-Self-Hosting/Docker & Turnkey Setup|Docker, VPS Installer & Production Setup]]
  - [[07-API-Reference/REST API Protocol v2|REST API Reference (Protocol v2)]]
