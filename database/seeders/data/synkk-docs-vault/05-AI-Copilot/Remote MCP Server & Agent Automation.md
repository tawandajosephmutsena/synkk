---
title: "Remote MCP Server & Agent Automation"
description: "Connect Claude Desktop, Cursor, Windsurf, or Antigravity to your Synkk vaults over JSON-RPC 2.0 Streamable HTTP."
tags:
  - mcp
  - ai
  - agents
  - claude
  - cursor
---

# 🔌 Permissioned Remote MCP Server

Synkk includes a native **Model Context Protocol (MCP)** server at `POST /api/v1/mcp` (JSON-RPC 2.0 Streamable HTTP, protocol `2025-03-26`) so external AI assistants can search, read, traverse, and safely propose edits to your non-E2EE team vaults.

---

## 🔑 Scoped MCP Tokens (Zero Device Seat Cost)

Generate MCP tokens in **Team Settings → AI Add-On → Permissioned Remote MCP Server**:
- MCP tokens use `client_platform = 'mcp'` and **do not count toward your Obsidian device seat limit**.
- Every request enforces the token owner's team membership, vault allowlist, Path ACLs (`hidden` folders are invisible), and per-vault AI excluded folders.
- E2EE vaults are always blocked from MCP (`-32003` / `HTTP 403 E2EE_BLOCKED`).

| Scope | Allowed Operations |
| :--- | :--- |
| **`mcp_read`** | List vaults, hybrid search, read notes, traverse `[[wikilink]]` graph, view recent activity, read MCP resources (`synkk://vault/{slug}/note/{path}`). |
| **`mcp_propose`** | Everything in `mcp_read` + stage proposed edits in the human-approved **AI Change Inbox** (`synkk_propose_note`). |
| **`mcp_write`** | Everything in `mcp_propose` + direct writes via `synkk_write_note` (requires **both** `mcp_write` scope **and** enabling *Allow direct MCP writes* per vault). |

---

## 🛠️ The 7 Native MCP Tools

1. `synkk_list_vaults` — List accessible vaults with AI/E2EE status.
2. `synkk_search_notes` — Hybrid FTS5 + vector search scoped by Path ACLs.
3. `synkk_read_note` — Read a specific Markdown note by path.
4. `synkk_traverse_graph` — Explore outgoing links and backlinks around a note.
5. `synkk_recent_activity` — Inspect recent vault changes and sync activity.
6. `synkk_propose_note` — Stage a create/update/append/move diff in the AI Change Inbox.
7. `synkk_write_note` — Commit a direct note write when explicitly enabled on the vault.

---

## 💻 Client Configuration (Claude Desktop / Cursor / Windsurf)

```json
{
  "mcpServers": {
    "synkk": {
      "url": "https://your-synkk-domain.com/api/v1/mcp",
      "headers": {
        "Authorization": "Bearer synkk_your_mcp_token"
      }
    }
  }
}
```

Next: Learn how to self-host Synkk on your own server with [[06-Self-Hosting/Docker & Turnkey Setup|Docker & Turnkey Setup]].
