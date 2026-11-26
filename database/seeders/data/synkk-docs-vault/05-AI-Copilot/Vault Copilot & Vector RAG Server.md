---
title: "Vault Copilot & Vector RAG Server"
description: "Opt-in Hybrid SQLite FTS5 + vector search, streaming AI Sidebar, Proposal Diff Inbox, Vault Gardener, Team Digests, and Local E2EE Ollama AI."
tags:
  - ai
  - rag
  - copilot
  - ollama
  - embeddings
---

# 🤖 Vault AI Suite, Hybrid Search & Local E2EE AI

Synkk AI is **100% opt-in** at both the workspace level (**Team Settings → AI Add-On**) and per-vault level, with strict path ACL filtering, per-vault excluded folder rules, and automatic secret redaction (`[REDACTED:...]`) before calling external providers.

---

## ⚙️ Three Provider Modes

1. **Local AI (Ollama / OpenAI-Compatible — Works with E2EE!):**
   - Inside Obsidian (`v1.1.1`), configure **Zero-Knowledge E2EE Local AI** (`http://localhost:11434`, default `llama3.2`, or LM Studio / LocalAI).
   - On E2EE vaults, server AI is disabled automatically and the **Synkk AI** sidebar + **Smart Merge** run pure-JS BM25 + 1-hop `[[wikilink]]` graph retrieval locally on your device.
2. **BYOK (Bring Your Own Key — Included Free):**
   - Connect **OpenAI**, **Anthropic**, **Google Gemini**, **OpenRouter**, **Ollama**, or any OpenAI-compatible server with optional monthly spend caps.
3. **Managed Cloud AI Add-On (`$12/workspace/mo`, optional):**
   - Zero-config hosted AI credits (`$12/mo` pool; Cloud plans include a one-time `$2` trial credit) powered by OpenRouter chat routing + OpenAI `text-embedding-3-small`.

---

## 🔍 Hybrid Search & Graph Traversal

On standard (non-E2EE) vaults with Server AI enabled:
- Notes are chunked along Markdown headings into SQLite FTS5 (`vault_chunks_fts`) and float32 binary vectors (`vault_file_embeddings`).
- Queries fuse **FTS5 BM25 + vector cosine similarity via Reciprocal Rank Fusion (RRF)** and expand bidirectional `[[wikilink]]` graph neighbors while respecting every user's Path ACLs.

---

## 📋 Human-in-the-Loop AI Workflows

- **Dockable Synkk AI Sidebar (`Synkk AI`):** Multi-turn streaming chat (over Reverb with HTTP polling fallback), active-note toggle, clickable `[[note #heading]]` citations, and Copy / Insert at cursor / Save as note actions.
- **AI Change Inbox (Proposals):** Agent proposals (`create`, `update`, `append`, `move`, `delete`, `rename`, `tag`) stage with colored line diffs, staleness warnings, and optional inline editing until a human clicks **Approve & Sync** or **Reject**.
- **Vault Gardener:** Scheduled daily (02:30) or on-demand runs that triage inbox notes, suggest missing `[[wikilinks]]`, and flag contradictions as reviewable proposals.
- **Team Digests:** Daily or weekly activity summaries delivered to a vault Markdown note, the team feed, or opt-in email.

Next: Connect Claude Desktop, Cursor, or Windsurf via [[05-AI-Copilot/Remote MCP Server & Agent Automation|Remote MCP Server & Agent Automation]].
