---
title: "Instant 3-Second QR Pairing"
description: "Zero-friction device pairing with genuine protocol URI handlers and camera scanning."
tags:
  - pairing
  - mobile
  - onboarding
  - qr-code
---

# ⚡ Instant 3-Second QR Pairing (Milestone A)

Connecting mobile devices to self-hosted software is traditionally painful—typing complex server URLs and 64-character tokens on touch keyboards invites typos and frustration.

Synkk eliminates this entirely with **Instant QR Pairing**.

---

## 📱 How to Pair in 3 Seconds

1. On your desktop, open your Synkk Web Dashboard and navigate to **Devices → Pair New Device**.
2. A high-contrast QR code is rendered containing an encrypted handshake payload.
3. On your phone (iOS / Android), open Obsidian and click the Synkk status bar or go to **Settings → Synkk Sync**.
4. Tap **"📷 Scan QR Code"**:
   - The native camera viewfinder opens.
   - Point your phone at the screen.
   - The handshake exchanges credentials instantly, provisions a dedicated device token, registers platform metadata, and loads your target vault.

> [!TIP] Deep Link Protocol Handler
> Synkk registers the custom protocol handler `obsidian://synkk-pair`.
> Clicking a pairing link on your mobile browser (e.g. `https://synkk.space/pair?session=...`) automatically launches Obsidian and completes the pairing handshake hands-free!

---

## 🛡️ Security Architecture

- **Single-Use Ephemeral Sessions**: Pairing sessions expire automatically after 10 minutes and can only be consumed once.
- **Strict Origin Enforcement**: The plugin strictly verifies that the server URL uses HTTPS (or localhost for local development) before accepting credentials.
- **Enterprise Remote Wipe**: If a phone or laptop is lost or stolen, an administrator can trigger an instant remote wipe from the dashboard. The client purges its local credentials immediately upon its next sync pulse (HTTP 410).

Next: Prepare your initial sync safely using [[02-Core-Sync/First-Sync Pre-Flight & Migration Engine|First-Sync Pre-Flight & Migration Engine]].
