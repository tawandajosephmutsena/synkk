# Synkk Foundation Release Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the current Synkk baseline safer to run publicly, accurately document it, and present it as a premium GitHub and Lemon Squeezy-ready Foundation release.

**Architecture:** A small, pure plugin policy module makes path/config and deletion decisions deterministic. The sync engine consults it before each pull/push mutation and stashes local bytes before remote mutations. The existing Laravel version history is retained, but its restore action is authorized at the Livewire boundary. Public copy and release assets describe the resulting product truthfully.

**Tech Stack:** Laravel 13, Livewire 4/Flux, Pest 5, Tailwind CSS 4, Obsidian TypeScript plugin, Node built-in test runner, Vite.

**Spec:** `docs/superpowers/specs/2026-09-05-foundation-release-design.md`

## Global Constraints

- Preserve all pre-existing uncommitted files and their visual direction.
- Do not add dependencies, create a remote release, publish artifacts, configure payment credentials, push, or commit.
- Do not claim real-time sync, CRDT merging, zero-knowledge encryption, QR pairing, P2P sync, virtual files, or an official Community Plugins listing.
- Device-local policy and snapshot files must never be propagated through Synkk.
- Default deletion threshold is exactly 10 percent; explicit one-time override is consumed after use.

---

### Task 1: Authorize file-version restore

**Files:**
- Modify: `resources/views/pages/vaults/⚡show.blade.php:211-224`
- Modify: `tests/Feature/VaultFileVersionTest.php`

**Interfaces:**
- Consumes: `VaultPolicy::update(User, Vault)` and `RestoreFileVersionAction::execute(VaultFileVersion, User): VaultFile`.
- Produces: a restore action reachable only to a vault editor/owner.

- [ ] Add a Pest feature test that creates a read-only member, a file history record, calls the component's restore action, and asserts the member receives 403 and active content remains unchanged.
- [ ] Run `php artisan test --compact tests/Feature/VaultFileVersionTest.php` and confirm the new authorization assertion fails because restore currently lacks an authorization check.
- [ ] Add `$this->authorize('update', $this->vault);` as the first operation in `restoreVersion()`.
- [ ] Re-run the focused test file; then run `vendor/bin/pint --dirty --format agent`.

### Task 2: Make plugin sync policy deterministic

**Files:**
- Create: `obsidian-plugin/src/sync-policy.js`
- Modify: `obsidian-plugin/src/types.ts`
- Modify: `obsidian-plugin/src/syncEngine.ts`
- Test: `tests/JavaScript/sync-policy.test.js`

**Interfaces:**
- Produces: `shouldSyncPath(path, settings): boolean`, `shouldSnapshotPath(path): boolean`, and `deletionGuard(paths, baselineCount, thresholdPercent): { blocked: boolean, percentage: number }`.
- Consumes: `SynkkSettings` values `includedPaths`, `excludedPaths`, `syncPluginList`, `syncSnippets`, `syncPluginData`, and `deletionThresholdPercent`.

- [ ] Add Node tests covering: default content sync, ignored `.git`/`.trash`/`.synkk`, selected folder inclusion/exclusion, all three eligible `.obsidian` choices, permanently ignored workspace/hotkeys/state files, and a 10% deletion threshold boundary.
- [ ] Run `npm run test:js -- tests/JavaScript/sync-policy.test.js` and confirm it fails because the module does not exist.
- [ ] Implement only the pure policy helpers; normalize slash-separated prefix rules and do not access Obsidian APIs.
- [ ] Extend settings defaults/types and replace the sync engine's hard-coded ignore check with the policy helper for remote deletion, remote download, local upload, and local deletion scans.
- [ ] Re-run the Node policy test plus `npm run test:js`; run `npm --prefix obsidian-plugin run build`.

### Task 3: Add Atomic Safety Shield and local snapshot stash

**Files:**
- Modify: `obsidian-plugin/src/syncEngine.ts`
- Modify: `obsidian-plugin/src/settings.ts`
- Modify: `obsidian-plugin/src/types.ts`
- Test: `tests/JavaScript/sync-policy.test.js`

**Interfaces:**
- Consumes: `deletionGuard()` from Task 2 and existing `SyncStateData.files`.
- Produces: a sync result that reports a safety halt without deleting local/remote data; `.synkk/snapshots/<timestamp>/...` backup paths.

- [ ] Add unit assertions for a blocked 11% purge, an allowed 10% purge, and a one-time override path.
- [ ] Run the policy test and confirm the new safety cases fail.
- [ ] In the engine, guard remote and local deletion sets before mutation. Return a summary with `errors` incremented, show a clear Notice, and do not update the checkpoint after a halt.
- [ ] Add binary snapshot creation before a remote file overwrite/delete. Recursively ensure the snapshot directory exists; abort that mutation and report the error if the backup write fails.
- [ ] Add settings controls for folder rules, `.obsidian` categories, deletion threshold (1-100), and a clearly described one-time safety override. Reset the override after an allowed guarded run.
- [ ] Re-run the Node suite, TypeScript plugin build, and manually exercise the settings labels in Obsidian if a test vault is available.

### Task 4: Correct product truth in public and in-app documentation

**Files:**
- Modify: `resources/views/welcome.blade.php`
- Modify: `resources/views/pages/docs/⚡index.blade.php`
- Modify: `obsidian-plugin/README.md`
- Modify: `obsidian-plugin/manifest.json`
- Create: `README.md`
- Test: `tests/Feature/LandingPageTest.php`

**Interfaces:**
- Produces: verified feature language, GitHub beta installation path, configurable Lemon Squeezy CTA, and a visible Foundation-to-advanced-sync roadmap.
- Consumes: currently implemented polling, SHA-256 verification, path permissions, file version snapshots, device profiles, and safety shield behavior.

- [ ] Update the landing-page feature test to assert the Foundation feature names and assert that unsupported claim phrases are absent; run it and confirm it fails against current copy.
- [ ] Replace unsupported marketing/docs claims, clearly identify BRAT/GitHub beta as the current install route, and add a compact roadmap with Foundation/Private Preview/Future stages.
- [ ] Add a root README with setup, supported current capabilities, current limitations, responsible disclosure contact placeholder that does not masquerade as a real inbox, release procedure, and roadmap.
- [ ] Point plugin repository/install examples at the configured canonical GitHub repository value or leave a single visible `YOUR_GITHUB_REPOSITORY` launch-blocker marker in release documentation; do not fabricate a repository address.
- [ ] Run the landing test and plugin build; inspect rendered docs as an authenticated user.

### Task 5: Refine the public premium experience

**Files:**
- Modify: `resources/views/welcome.blade.php`
- Modify: `resources/css/landing.css`
- Modify: `tests/Feature/LandingPageTest.php`

**Interfaces:**
- Produces: progressive-reveal animation hooks for hero and major sections, with no motion when `prefers-reduced-motion: reduce` is active.
- Consumes: existing Vite/GSAP entry point and the current welcome-page visual world.

- [ ] Add an assertion that the welcome page exposes the reveal hooks and retains the no-remote-font constraint; confirm it fails first.
- [ ] Add CSS-first reveal transitions and a `prefers-reduced-motion` override. Avoid an animation dependency beyond the installed assets and never hide page content when JavaScript is unavailable.
- [ ] Run the landing test and `npm run build`.
- [ ] Capture desktop and narrow screenshots of welcome, dashboard, and docs with Computer Use. Rate each surface 0-5 for hierarchy, visual finish, trust, accessibility, and responsive behavior; revise the active surface until it scores 5.

### Task 6: Release-readiness verification

**Files:**
- Modify only files revealed by verification failures.

**Interfaces:**
- Consumes: every prior task.
- Produces: fresh evidence for the Foundation release; no external publication.

- [ ] Run `php artisan test --compact tests/Feature/VaultFileVersionTest.php tests/Feature/LandingPageTest.php`.
- [ ] Run `npm run test:js`, `npm --prefix obsidian-plugin run build`, `npm run build`, and `vendor/bin/pint --dirty --format agent`.
- [ ] Inspect `git diff --check`, `git diff --stat`, browser console errors, and the three live pages.
- [ ] Report every command result, any unverified mobile-native behavior, the exact release blockers that need real Lemon Squeezy/GitHub configuration, and the deferred advanced-sync roadmap.
