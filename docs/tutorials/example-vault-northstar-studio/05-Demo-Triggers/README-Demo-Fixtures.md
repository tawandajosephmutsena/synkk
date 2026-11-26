---
title: Presenter Demo Fixtures & Cheat Sheet
tags:
  - presenter
  - demo
---

# 🎬 Presenter Demo Fixtures (Delete or Keep Local Before Publishing)

Run these quick terminal commands inside your copied `example-vault-northstar-studio` folder right before recording **Video 2** and **Video 3** so every interactive Synkk safeguard triggers live on camera:

## 1. Pre-Flight Path Sanitizer Fixture (Video 2)
On macOS/Linux, create a note with an illegal Windows filename character (`?` or `:`):
```bash
printf "# Q4 Planning\nCreated to demonstrate Synkk's 1-click Auto-Sanitize.\n" > "00-Inbox/Meeting: Q4? Plan.md"
```
Then open **Vault Migration: Run Pre-Flight Diagnostic & Migration Wizard** → Step 1 flags the illegal characters → click **⚡ Auto-Sanitize Unsafe File Names**!

## 2. Mobile Ghost Files Fixture (Video 2)
Generate a ~7 MB dummy binary PDF in `06-Media/` (above the default 5 MB mobile Ghost File threshold):
```bash
mkdir -p 06-Media
printf "%%PDF-1.4\n%% Synkk Ghost File Demo Fixture\n" > 06-Media/Apollo-Field-Recording-7MB.pdf
dd if=/dev/zero bs=1048576 count=7 >> 06-Media/Apollo-Field-Recording-7MB.pdf
```
Sync on Desktop → sync on Mobile (with Ghost Files enabled at 5 MB) → watch it dehydrate into a `<1 KB` stub on your phone, then run **Ghost Files: Hydrate active file on-demand**!

## 3. Server-Side Plaintext DLP Secret Scanner Fixture (Video 3)
*(Never commit real keys to any vault or repo!)* To trigger Synkk's server-side plaintext DLP detector during Video 3, temporarily paste a **synthetic dummy pattern** matching `SecretScannerService` (for example, `AKIAIOSFODNN7EXAMPLE` or a fake `-----BEGIN RSA PRIVATE KEY-----` header) into a throwaway note on a **standard (non-E2EE)** vault, sync once, and show the **DLP Threat Alert** in the Synkk Dashboard — then delete the note!

## 4. Visual Conflict Sandbox Fixture (Video 3)
1. Turn **Auto-Sync** off temporarily (or disconnect Wi-Fi on Device B).
2. On **Desktop**, change the last bullet of `01-Projects/Apollo Launch/Launch Brief.md` to:
   `Target beta cohort size is 60 pilot teams (confirmed with Engineering).` and click **Sync Now**.
3. On **Device B / Web Editor**, change that same line to:
   `Target beta cohort size is 75 pilot teams (approved by Sales).` and sync.
4. Watch Synkk preserve the conflict copy without overwriting, then open **Visual Conflict Sandbox: Reconcile Notes** and click **✨ Smart Merge with AI**!
