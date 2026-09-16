---
title: "REST API Protocol v2"
description: "Complete technical reference for the Synkk HTTP sync protocol, preflight simulation, and RAG endpoints."
tags:
  - api
  - rest
  - developers
  - protocol-v2
---

# 🔌 REST API Protocol v2 Reference

All requests to the Synkk Sync API must include:
- `Authorization: Bearer <device_token>`
- `Accept: application/json`
- `X-Synkk-Protocol: 2`
- `X-Client-Platform: mac | windows | linux | ios | android`

---

## 🛫 Pre-Flight Simulation API (Option C)

Simulates sync deltas and checks storage quota without moving file data.

`POST /api/v1/vaults/{vault:slug}/preflight`

### Request Payload
```json
{
  "total_files": 340,
  "total_bytes": 48291000,
  "categories": {
    "markdown": { "count": 310, "bytes": 1200000 },
    "images": { "count": 30, "bytes": 47091000 }
  },
  "files": [
    { "path": "Daily/2026-09-16.md", "size": 3400, "sha256": "a3f5..." }
  ]
}
```

### Success Response (`200 OK`)
```json
{
  "status": "ready",
  "authorized": true,
  "vault": {
    "id": 1,
    "slug": "engineering",
    "name": "Engineering Vault",
    "server_files_count": 320,
    "server_bytes": 45100000
  },
  "quota": {
    "allowed": true,
    "current_storage_bytes": 524288000,
    "storage_limit_bytes": 10737418240,
    "remaining_bytes": 10213130240
  },
  "simulation": {
    "to_upload_count": 25,
    "to_download_count": 5,
    "identical_skipped_count": 315,
    "bandwidth_saved_bytes": 42100000
  },
  "safety": {
    "atomic_shield_active": true,
    "max_deletion_threshold_percent": 20
  }
}
```

### Quota Exceeded Response (`422 Unprocessable Entity`)
```json
{
  "status": "quota_exceeded",
  "authorized": false,
  "message": "Vault pre-flight check failed: Proposed sync payload exceeds team storage quota.",
  "quota": {
    "allowed": false,
    "current_storage_bytes": 10485760000,
    "additional_bytes": 524288000,
    "storage_limit_bytes": 10737418240,
    "deficit_bytes": 272630760
  },
  "recommendation": "Upgrade to Synkk Pro LTD or Synkk Cloud to expand team storage limit."
}
```

---

## 📦 Sync Manifest & Batch Endpoints

- `GET /api/v1/vaults/{vault:slug}/manifest?since_version={n}`
- `POST /api/v1/vaults/{vault:slug}/upload/batch`
- `GET /api/v1/vaults/{vault:slug}/download?path={path}&ghost={bool}`
- `DELETE /api/v1/vaults/{vault:slug}/files?path={path}`
- `GET /api/v1/vaults/{vault:slug}/transport/status`

---

## 🤖 Vault Copilot & RAG Endpoints

- `POST /api/v1/vaults/{vault:slug}/rag/query` (takes `{ query, expand_graph, max_citations }`)
- `POST /api/v1/vaults/{vault:slug}/rag/search` (takes `{ query, limit }`)
- `POST /api/v1/vaults/{vault:slug}/rag/index` (takes `{ force }`)
- `GET /api/v1/vaults/{vault:slug}/rag/status`
