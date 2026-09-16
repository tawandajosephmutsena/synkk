---
title: "Vault Copilot & Vector RAG Server"
description: "Semantic search, citations, and graph exploration powered by local Ollama or cloud LLMs."
tags:
  - ai
  - rag
  - copilot
  - embeddings
---

# 🤖 Vault Copilot & Vector RAG Server

Your Obsidian vault is a storehouse of your thinking. **Vault Copilot** transforms it into an interactive semantic conversation partner.

---

## 🔍 Semantic Search & Citations

Unlike keyword search that fails when you forget the exact word you used six months ago, Vault Copilot uses vector embeddings:
- Chunks notes into semantic passages while respecting markdown heading boundaries.
- Generates vector embeddings via OpenAI `text-embedding-3-small` or local **Ollama** embeddings.
- Every Copilot answer includes direct **Wikilink Citations** (`[[Note Name#Heading]]`) and excerpt snippets with confidence scores.

---

## 🕸️ Knowledge Graph Integration

Vault Copilot doesn't just read isolated notes; it follows the graph:
- Traces bidirectional wikilinks `[[...]]`.
- Discovers second-order connections and surface clusters you hadn't realized were related.
- Summarizes the intersection between disparate ideas in your vault.

Next: Learn how to self-host Synkk on your own hardware with [[06-Self-Hosting/Docker & Turnkey Setup|Docker & Turnkey Setup]].
