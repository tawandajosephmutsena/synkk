---
title: "Zero-Knowledge End-to-End Encryption"
description: "Client-side WebCrypto AES-256-GCM encryption where the server only ever sees opaque ciphertext."
tags:
  - security
  - encryption
  - e2ee
  - privacy
---

# 🔒 Zero-Knowledge End-to-End Encryption

For corporate IP, personal medical records, investigative journalism, and sensitive research, trust must be grounded in mathematical cryptography rather than provider promises.

Synkk features true **Zero-Knowledge Client-Side E2EE**.

---

## 🛡️ Cryptographic Architecture

```
Client Device (Obsidian Plugin)
  │
  ├── User Passphrase + Vault Salt
  │     │ (PBKDF2-HMAC-SHA256, 100,000 rounds)
  │     ▼
  ├── 256-bit AES-GCM Master Key (never leaves local device RAM)
  │     │
  │     ├── Encrypts Plaintext Note
  │     └── Generates unique 96-bit IV + 128-bit Auth Tag
  │
  └── Sends Ciphertext Envelope to Server
        │
        ▼
Synkk Server Storage (Sees ONLY Opaque Base64 Ciphertext)
```

1. **Zero-Server Knowledge**: The passphrase is never transmitted, logged, or stored on the server.
2. **Deterministic Authentication Verification**: An encrypted test cipher verifies passphrase correctness client-side without exposing plaintext.
3. **Loop-Breaking Local Hash**: Synkk tracks `localPlaintextSha256` in client state to prevent infinite sync feedback loops when comparing raw files against server ciphertext envelopes.

Next: Learn about controlling team permissions with [[03-Security/Role-Based Scoped Permissions|Role-Based Scoped Permissions]].
