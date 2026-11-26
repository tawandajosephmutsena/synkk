---
title: "REST API Protocol v2"
description: "Complete technical reference for the Synkk HTTP Sync Protocol v2, Collaboration, Conflicts, Ghost Files, Vault AI, and Remote MCP endpoints."
tags:
  - api
  - rest
  - developers
  - protocol-v2
  - mcp
---

# 🔌 REST API Protocol v2 Reference

All authenticated requests to `/api/v1/*` include:
- `Authorization: Bearer <synkk_token>`
- `Accept: application/json`
- `X-Synkk-Protocol: 2` (required; older/missing headers return `HTTP 426`)
- `X-Client-Platform: mac | windows | linux | ios | android`

Rate limit: **300 requests/minute** per bearer token or IP.

---

## 🔐 Auth & Pairing

- `GET /api/v1/auth/verify` — Verify token, team, plan, and Reverb broadcasting config.
- `POST /api/v1/pairing/session` — Create a 10-minute single-use QR/deep-link pairing session.
- `POST /api/v1/pairing/exchange` *(public)* — Exchange a pairing session for a scoped device token (`HTTP 410` if expired/used).
- `GET /api/v1/pairing/status` *(public)* — Poll pairing session state (`pending`, `paired`, `expired`, `revoked`).

---

## 📦 Sync, Manifest, Pre-Flight & Ghost Files

- `GET /api/v1/vaults` — List vaults accessible to the device token.
- `POST /api/v1/vaults/{slug}/preflight` — Dry-run quota check & upload/download/skip simulation without uploading bytes.
- `GET /api/v1/vaults/{slug}/manifest?since_version={n}` — ACL-filtered file manifest + deleted tombstones.
- `GET /api/v1/vaults/{slug}/changes?since_version={n}` — Up to 500 incremental change log entries.
- `GET /api/v1/vaults/{slug}/download?path={path}&ghost={0|1}` — Stream file blob (or Ghost stub) with `X-Synkk-Sha256` & E2EE headers.
- `POST /api/v1/vaults/{slug}/upload` — Upload a single file (`file`, `content`, or `content_base64`, `base_version`, optional E2EE fields).
- `POST /api/v1/vaults/{slug}/batch-sync` — Atomic DB transaction of up to 100 upload/delete operations.
- `POST /api/v1/vaults/{slug}/delete` — Soft-delete a file path (tombstone pruned after 30 days).
- `POST /api/v1/vaults/{slug}/files/hydrate` & `POST /api/v1/vaults/{slug}/files/dehydrate` — On-demand Ghost File hydration/dehydration.
- `POST /api/v1/vaults/{slug}/e2ee/enable` & `GET /api/v1/vaults/{slug}/e2ee/status` — Enable/inspect client-side AES-256-GCM vault encryption.
- `GET /api/v1/vaults/{slug}/transport/status?client_version={n}` — Lightweight mobile background relay heartbeat.

---

## 🔀 Conflicts & Real-Time CRDT Collaboration

- `GET /api/v1/vaults/{slug}/conflicts` — List active conflict copies.
- `POST /api/v1/vaults/{slug}/conflicts/diff` — Compute 3-way line diff against ancestor version.
- `POST /api/v1/vaults/{slug}/conflicts/smart-merge` — Preview semantic AI merge without mutating disk.
- `POST /api/v1/vaults/{slug}/conflicts/resolve` — Commit reconciled note and soft-delete conflict copy.
- `POST /api/v1/vaults/{slug}/collab/join`, `GET .../collab/catch-up`, `POST .../collab/append`, `POST .../collab/checkpoint`, `POST .../collab/leave`, `GET .../collab/presence` — Durable Yjs collaboration journal + Reverb broadcast.

---

## 🤖 Vault AI, RAG & Remote MCP

- `POST /api/v1/vaults/{slug}/rag/query`, `POST .../rag/search`, `POST .../rag/index`, `GET .../rag/progress`, `GET .../rag/status`
- `GET/POST /api/v1/vaults/{slug}/ai/threads`, `POST .../ai/threads/{id}/messages`, `GET .../ai/runs/{run}`
- `GET /api/v1/vaults/{slug}/ai/proposals`, `POST .../ai/proposals/{id}/approve`, `POST .../ai/proposals/{id}/reject`
- `POST /api/v1/vaults/{slug}/ai/gardener/run`, `POST /api/v1/vaults/{slug}/ai/digests/generate`
- `POST /api/v1/mcp` — JSON-RPC 2.0 Streamable HTTP Remote MCP Server (`mcp_read`, `mcp_propose`, `mcp_write`).
