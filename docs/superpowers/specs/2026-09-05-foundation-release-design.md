# Synkk Foundation Release Design

## Objective

Ship a public GitHub and Lemon Squeezy-ready Synkk Foundation release that is visually premium, technically honest, and safer than the current whole-file sync baseline. The release must not claim CRDT merging, zero-knowledge encryption, peer-to-peer transport, native background sync, virtual files, or an official Obsidian Community Plugins listing.

## Product boundary

Synkk Community is a public, self-hosted GitHub release with a useful Obsidian plugin and team-vault server. The $49 Lemon Squeezy Sovereign License is positioned as a commercial support, update, and future advanced-sync entitlement. The public server core must remain usable without an active license; the application already supports non-enforced local license activation.

## Foundation behavior

### Device sync policy

Each plugin installation owns a device-local `SynkkSettings` profile:

- `includedPaths` and `excludedPaths` are newline-delimited folder prefixes. A path is selected when it matches an included prefix (or no include prefixes exist) and does not match an excluded prefix.
- `.git`, `.trash`, operating-system noise, and Synkk's own local state/snapshot directories are always excluded.
- `.obsidian` remains device-local by default. Users may independently include `community-plugins.json`, `snippets`, and `plugins`; workspace/layout, hotkeys, cache, and `synkk-state.json` never sync.
- Policies apply equally to pull, push, and local deletion detection. A skipped file must not be mutated or propagated.

### Atomic Safety Shield

Before a sync mutates local state, the plugin calculates remote deletions relative to locally tracked, selected files. Before it propagates local deletions, it calculates them relative to tracked, selected files. A deletion set over the device-local configurable threshold (default 10%) halts the appropriate direction before any deletion request or local file deletion.

The blocked result names the direction, count, baseline, threshold, and a sample of paths. The user must explicitly enable a one-time override in settings; the override is consumed after the next guarded sync. This avoids an accidental broad purge silently spreading through a vault.

Before Synkk replaces or deletes a local file from a remote change, it writes the current bytes to `.synkk/snapshots/<ISO-safe timestamp>/<original path>`. That directory is always excluded from sync. Snapshot failures halt the affected local mutation instead of reducing safety silently.

### Time Machine

The web vault already has version history and restore UI. This release treats that as the Time Machine foundation: each historical snapshot is visible, restorable as a new active version, and tied to the file's vault. Restore requires the same update permission as editing the active note; a viewer cannot restore a snapshot. It is described as file-version rollback, not keystroke-level history.

### Public experience and release material

The welcome page retains the current sovereign visual world. Its hero and major sections use purposeful, progressively revealed motion that respects `prefers-reduced-motion`; cards, comparison copy, pricing, and CTAs use only verified current claims. The site advertises a GitHub public beta and links to a configurable Lemon Squeezy store without inventing a checkout URL.

The in-app documentation distinguishes the public beta/BRAT installation route from a future Community Plugins listing, explains current polling and conflict-copy behavior, documents device profiles, Safety Shield, local snapshots, version rollback, and the explicitly planned advanced-sync roadmap.

The public root README supplies installation, self-hosting, feature truth table, support boundary, security reporting route, release steps, and roadmap. Plugin metadata and README use repository URLs that match the release configuration, not placeholders.

## Non-goals and roadmap

The Foundation release deliberately excludes CRDT/Yjs or Automerge, client-side content encryption, QR pairing, WebRTC/Iroh networking, cloud/relay service, content-defined chunking, native mobile background bridge, remote hydration, cross-vault sharing, and a visual conflict sandbox. The public roadmap may name these as future stages without dates or guarantees.

## Quality gates

- Every PHP behavior change has a focused Pest test that first fails for the missing behavior.
- Plugin policy code is unit-tested with Node's existing test runner and the plugin bundles successfully.
- Modified PHP is formatted with Pint; affected test suites, JavaScript tests, and production builds run cleanly.
- Browser verification captures the public welcome page, dashboard, and documentation at desktop and narrow viewport widths. Each surface is rated 0-5 for hierarchy, visual finish, factual trust, accessibility, and responsive behavior; a surface advances only at 5/5.
- No release, publish, payment configuration, Git push, or marketplace submission occurs in this change set.
