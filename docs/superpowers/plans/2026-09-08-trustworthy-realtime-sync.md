# Synkk Trustworthy Realtime Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Synkk's implemented behavior match its public promise with scoped device authorization, protocol-enforced client encryption, recoverable encrypted history, convergent Yjs editing over Laravel Reverb, one-scan pairing, reliable ghost files, and production-safe deployment.

**Architecture:** Whole-file sync remains the durable storage and history layer. A versioned content envelope and one authorization service guard every mutation. Yjs owns active Markdown convergence, a Laravel journal provides replay, and Reverb provides authorized low-latency delivery. E2EE clients encrypt both file and Yjs payloads before they leave the browser or Obsidian process.

**Tech Stack:** PHP 8.5, Laravel 13.30, Livewire 4.4, Flux 2.18, Pest 5, Laravel Reverb, Tailwind CSS 4, Vite 8, Yjs 13, y-codemirror.next, Laravel Echo, Pusher JS, Obsidian TypeScript plugin, Node test runner.

**Spec:** `docs/superpowers/specs/2026-09-08-trustworthy-realtime-sync-design.md`

## Global constraints

- Preserve the existing uncommitted changes in `obsidian-plugin/src/e2ee.ts`, `obsidian-plugin/src/syncEngine.ts`, `obsidian-plugin/tsconfig.json`, and the matching standalone-plugin files.
- Make the same intentional plugin source change in both the embedded and standalone plugin repositories; never overwrite either checkout wholesale.
- Do not commit, push, deploy, publish, rotate credentials, or create a release without a separate user request.
- Do not edit deployed migrations. Add forward-only migrations for every schema change.
- Do not persist E2EE passphrases or derived keys. Paths and operational metadata remain visible to the server and must be described accurately.
- Do not advance past a task with a Critical or High defect, a failing focused test, or a stage score below 9/10.
- After each task: inspect the focused diff, run its focused tests, run `git diff --check`, and record a score using the rubric in the approved specification.

---

### Task 1: Install and configure approved collaboration dependencies

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `package.json`
- Modify: `package-lock.json`
- Modify: `obsidian-plugin/package.json`
- Modify: `obsidian-plugin/package-lock.json`
- Modify: `/Users/mac/Herd/obsidian-synkk-sync/package.json`
- Modify: `/Users/mac/Herd/obsidian-synkk-sync/package-lock.json`
- Create through Laravel installer: `config/broadcasting.php`
- Create through Laravel installer: `config/reverb.php`
- Create through Laravel installer: `routes/channels.php`

**Interfaces:**
- Produces: Laravel Reverb server commands and broadcasting configuration; frontend imports for `yjs`, `y-codemirror.next`, `laravel-echo`, and `pusher-js`.
- Consumes: the current Laravel 13 application and CodeMirror 6 supplied by Livewire/Obsidian.

- [ ] **Step 1: Install Reverb using the framework-supported installer**

Run:

```bash
php artisan install:broadcasting --reverb --no-interaction
```

Expected: `laravel/reverb`, `laravel-echo`, and `pusher-js` are installed; broadcasting, Reverb, and channel files exist. Inspect generated `.env` changes and copy only non-secret key names/defaults into `.env.example`; do not expose generated credentials in output.

- [ ] **Step 2: Add Yjs editor dependencies to the web application**

Run:

```bash
npm install yjs@^13 y-codemirror.next@^0.3
```

Expected: root manifest and lockfile contain compatible Yjs 13 and `y-codemirror.next` packages.

- [ ] **Step 3: Add the collaboration dependencies to both plugin checkouts**

Run in each plugin checkout:

```bash
npm install yjs@^13 y-codemirror.next@^0.3 laravel-echo@^2 pusher-js@^8
```

Expected: the two plugin manifests declare identical collaboration dependencies without changing the existing package identity or version.

- [ ] **Step 4: Verify dependency resolution before feature work**

Run:

```bash
composer validate --strict
npm ls yjs y-codemirror.next laravel-echo pusher-js
npm --prefix obsidian-plugin ls yjs y-codemirror.next laravel-echo pusher-js
```

Expected: all commands exit zero with one compatible Yjs major version.

---

### Task 2: Centralize device, vault, path, and administrator authorization

**Files:**
- Create: `app/Services/DeviceVaultAccess.php`
- Modify: `app/Http/Middleware/AuthenticateDeviceToken.php`
- Modify: `app/Http/Controllers/Api/VaultSyncController.php`
- Modify: `app/Http/Controllers/Api/AuthController.php`
- Modify: `app/Actions/Vaults/ResolveConflictAction.php`
- Test: `tests/Feature/DeviceVaultAuthorizationTest.php`
- Test: `tests/Feature/QrPairingTest.php`
- Test: `tests/Feature/SecurityHardeningTest.php`

**Interfaces:**
- Produces: `authorizeRead(DeviceToken, Vault, string): void`, `authorizeWrite(DeviceToken, Vault, string): void`, and `authorizeAdmin(DeviceToken, Vault): void`.
- Consumes: `DeviceToken::hasAccessFromIp()`, `DeviceToken::allowed_vault_ids`, team membership roles, and `Vault::permissionForPath()`.

- [ ] **Step 1: Add failing endpoint authorization tests**

Cover read-only, hidden path, excluded vault, non-admin, revoked, wiped, and cross-team tokens for conflict diff/resolve, collaboration join/sync/leave/presence, ghost dehydration, E2EE enablement, RAG indexing, and pairing-session creation. A write refusal must assert no file, changelog, collaboration record, token, or queued job was created.

Representative test:

```php
it('returns 403 when a read-only device resolves a conflict', function () {
    [$vault, $token, $canonical, $conflict] = createConflictScenario(accessScope: 'read_only');

    $this->withToken($token)->postJson(route('api.vaults.conflicts.resolve', $vault), [
        'canonical_path' => $canonical->path,
        'conflict_path' => $conflict->path,
        'resolved_content' => '# forbidden',
    ])->assertForbidden();

    expect($canonical->fresh()->sha256)->toBe($canonical->sha256)
        ->and($conflict->fresh()->is_deleted)->toBeFalse();
});
```

- [ ] **Step 2: Run the authorization tests and confirm current failures**

Run:

```bash
php artisan test --compact tests/Feature/DeviceVaultAuthorizationTest.php tests/Feature/QrPairingTest.php tests/Feature/SecurityHardeningTest.php
```

Expected: new negative cases fail because the current endpoints apply inconsistent checks.

- [ ] **Step 3: Implement the central access service**

Use this public boundary:

```php
final class DeviceVaultAccess
{
    public function authorizeRead(DeviceToken $token, Vault $vault, string $path): void;
    public function authorizeWrite(DeviceToken $token, Vault $vault, string $path): void;
    public function authorizeAdmin(DeviceToken $token, Vault $vault): void;
}
```

Normalize paths before authorization. Return 404 for cross-team, excluded-vault, and hidden-path access; return 403 for known resources where a valid principal lacks write or administrator authority.

- [ ] **Step 4: Apply the service to every affected endpoint and conflict action**

Conflict resolution must authorize both paths and reject a conflict path unless its stored record maps to the supplied canonical path using Synkk's conflict naming rule. E2EE enablement and indexing require administrator access.

- [ ] **Step 5: Re-run and score the authorization stage**

Run the focused suite, `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`, and `vendor/bin/pint --dirty --format agent`. Advance only with all authorization cases green and a score of at least 9/10.

---

### Task 3: Introduce protocol v2 and a validated content envelope

**Files:**
- Create: `app/ValueObjects/VaultContentEnvelope.php`
- Create: `app/Services/VaultProtocol.php`
- Create: `app/Http/Requests/Api/UploadVaultFileRequest.php`
- Create: `app/Http/Requests/Api/BatchSyncRequest.php`
- Modify: `app/Http/Controllers/Api/VaultSyncController.php`
- Modify: `app/Actions/Vaults/SyncUploadAction.php`
- Modify: `app/Actions/Vaults/BatchSyncAction.php`
- Modify: `obsidian-plugin/src/apiClient.ts`
- Modify: `obsidian-plugin/src/types.ts`
- Mirror plugin changes in: `/Users/mac/Herd/obsidian-synkk-sync/src/`
- Test: `tests/Feature/VaultContentEnvelopeTest.php`
- Test: `tests/Feature/ApiSyncTest.php`

**Interfaces:**
- Produces: protocol metadata in auth/manifest responses and `VaultContentEnvelope::fromValidated(array): self`.
- Consumes: existing upload, batch, conflict, storage, version, and changelog flows.

- [ ] **Step 1: Add failing protocol and envelope contract tests**

Test HTTP 426 for an E2EE mutation below protocol v2; HTTP 422 for plaintext E2EE payloads, malformed Base64, wrong IV/tag lengths, and unexpected encryption fields on plain vaults; retain v1 non-E2EE uploads.

```php
$this->withToken($token)
    ->withHeader('X-Synkk-Protocol', '2')
    ->postJson(route('api.vaults.upload', $vault), [
        'path' => 'Private/note.md',
        'content_base64' => base64_encode('cipher bytes'),
        'encrypted' => true,
        'iv' => bin2hex(random_bytes(12)),
        'tag' => bin2hex(random_bytes(16)),
        'format_version' => 2,
        'plaintext_size' => 14,
    ])->assertCreated();
```

- [ ] **Step 2: Implement the immutable envelope**

```php
final readonly class VaultContentEnvelope
{
    public function __construct(
        public string $payload,
        public string $payloadSha256,
        public int $plaintextSize,
        public bool $encrypted,
        public ?string $iv,
        public ?string $tag,
        public bool $ghost,
        public ?string $mimeType,
        public int $formatVersion,
    ) {}
}
```

Compute `payloadSha256` on the server. Do not trust a client hash for stored bytes.

- [ ] **Step 3: Pass one envelope through upload, batch, conflict, file metadata, and changelog code**

Remove post-upload metadata patching from `BatchSyncAction`; metadata and bytes must be committed by one action. Compensate for storage writes if the database transaction fails.

- [ ] **Step 4: Add protocol headers and response fields to the plugin API client**

Every authenticated plugin request sends `X-Synkk-Protocol: 2`. Auth and manifest types require `protocol_version`, `minimum_protocol_version`, `vault.id`, and `device.id`.

- [ ] **Step 5: Verify and score protocol v2**

Run focused PHP tests, both plugin type checks, both plugin tests, PHPStan, and Pint. Score at least 9/10 before changing plugin sync state.

---

### Task 4: Isolate plugin state and make E2EE/ghost synchronization idempotent

**Files:**
- Create: `obsidian-plugin/src/syncState.js`
- Create: `obsidian-plugin/src/contentRepresentation.js`
- Modify: `obsidian-plugin/src/syncEngine.ts`
- Modify: `obsidian-plugin/src/types.ts`
- Modify: `obsidian-plugin/src/settings.ts`
- Modify: `obsidian-plugin/src/e2ee.ts`
- Modify: `obsidian-plugin/src/ghostFiles.ts`
- Mirror plugin changes in: `/Users/mac/Herd/obsidian-synkk-sync/src/`
- Test: `obsidian-plugin/tests/sync-state.test.js`
- Test: `obsidian-plugin/tests/content-representation.test.js`
- Test: `/Users/mac/Herd/obsidian-synkk-sync/tests/sync-state.test.js`
- Test: `/Users/mac/Herd/obsidian-synkk-sync/tests/content-representation.test.js`

**Interfaces:**
- Produces: `contextStateKey(identity)`, `migrateLegacyState(state, identity)`, and independent local/remote representation hashes.
- Consumes: server identity metadata from Task 3, existing `E2eeVaultEngine`, `GhostFileManager`, and Safety Shield.

- [ ] **Step 1: Add failing pure JavaScript tests**

```js
test('does not reuse state after switching vaults', () => {
  const first = contextStateKey({ origin: 'https://one.test', vaultId: 1, deviceId: 9 });
  const second = contextStateKey({ origin: 'https://one.test', vaultId: 2, deviceId: 9 });
  assert.notEqual(first, second);
});
```

Cover server switching, vault switching, no secret in the key, legacy-state quarantine, encrypted pull followed by no upload, ghost pull followed by no upload, hydration hash verification, and malformed stubs.

- [ ] **Step 2: Replace the single state file with context-specific state**

Persist `localPlaintextSha256`, `remotePayloadSha256`, `remoteVersion`, `representation`, and `hydrated`. Move unattributable legacy state into a timestamped local archive and request a full reconciliation.

- [ ] **Step 3: Make manifest policy authoritative for E2EE**

If `vault.is_e2ee` is true and no verified key is in memory, halt without upload or checkpoint advancement. Remove `e2eePassphrase` from `SynkkSettings`, delete it during settings migration, and provide an in-memory unlock action.

- [ ] **Step 4: Make ghost stubs a local representation only**

Download canonical bytes when hydrating. Verify the canonical remote hash before replacing a hydrated attachment with a stub. Never upload an unchanged Synkk ghost stub as file content.

- [ ] **Step 5: Serialize sync runs per connection context**

Use one active promise per state key. A second trigger awaits the existing run and receives its result instead of scanning concurrently.

- [ ] **Step 6: Verify and score E2EE/ghost idempotency**

Run both plugin test suites, both TypeScript checks, and both production plugin builds. Advance only when pull-then-sync emits zero uploads for encrypted and ghost fixtures and the score is at least 9/10.

---

### Task 5: Preserve complete representations in version history

**Files:**
- Create: `database/migrations/2026_09_08_000001_add_representation_fields_to_vault_file_versions_table.php`
- Modify: `app/Models/VaultFileVersion.php`
- Modify: `app/Actions/Vaults/SyncUploadAction.php`
- Modify: `app/Actions/Vaults/RestoreFileVersionAction.php`
- Modify: `resources/views/pages/vaults/show.blade.php`
- Test: `tests/Feature/VaultFileVersionTest.php`
- Test: `tests/Feature/E2eeVaultTest.php`

**Interfaces:**
- Produces: exact snapshot/restore of `encrypted`, IV, tag, ghost state, original size, MIME type, and format version.
- Consumes: `VaultContentEnvelope` from Task 3.

- [ ] **Step 1: Generate the migration with Artisan**

Run:

```bash
php artisan make:migration add_representation_fields_to_vault_file_versions_table --table=vault_file_versions --no-interaction
```

Rename only if Artisan's timestamp differs from the planned filename; keep the generated class structure.

- [ ] **Step 2: Add failing encrypted and ghost restore tests**

Create two encrypted revisions with distinct IV/tag values, restore the first, and assert exact bytes and metadata. Add a legacy encrypted row without metadata and assert a clear non-restorable response with no active-file mutation.

- [ ] **Step 3: Add representation columns and casts**

```php
$table->boolean('is_encrypted')->default(false);
$table->string('encryption_iv', 64)->nullable();
$table->string('encryption_tag', 64)->nullable();
$table->boolean('is_ghost')->default(false);
$table->unsignedBigInteger('original_size')->default(0);
$table->string('mime_type')->nullable();
$table->unsignedTinyInteger('format_version')->default(1);
```

- [ ] **Step 4: Restore through the representation-aware action**

Restore stored bytes and metadata as a new active version. The Livewire action returns encrypted data to JavaScript as Base64 plus envelope metadata; it never assigns ciphertext to the Markdown text property.

- [ ] **Step 5: Verify migration, rollback, and stage score**

Run the focused version/E2EE tests on a fresh migrated test database, run `php artisan migrate:status`, PHPStan, and Pint. Require at least 9/10.

---

### Task 6: Build the durable Yjs collaboration journal

**Files:**
- Create: `database/migrations/2026_09_08_000002_create_vault_collaboration_documents_table.php`
- Create: `database/migrations/2026_09_08_000003_create_vault_collaboration_updates_table.php`
- Create: `app/Models/VaultCollaborationDocument.php`
- Create: `app/Models/VaultCollaborationUpdate.php`
- Create: `app/Actions/Collaboration/AppendCollaborationUpdateAction.php`
- Create: `app/Actions/Collaboration/PublishCollaborationCheckpointAction.php`
- Create: `app/Http/Controllers/Api/VaultCollaborationController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/VaultCollaborationTest.php`

**Interfaces:**
- Produces: join/catch-up, append-update, checkpoint, leave, and presence endpoints with stable document IDs and server sequences.
- Consumes: `DeviceVaultAccess`, protocol v2, and content-envelope validation.

- [ ] **Step 1: Add failing journal behavior tests**

Cover ordered sequences, `(document_id, client_update_id)` idempotency, duplicate response stability, path authorization, read-only publication refusal, E2EE plaintext refusal, catch-up after a sequence, and cross-vault document IDs.

- [ ] **Step 2: Create the document and update schemas**

Use unique `(vault_id, path)` documents and unique `(vault_collaboration_document_id, client_update_id)` updates. Allocate sequence numbers while holding a database row lock.

- [ ] **Step 3: Implement idempotent append after authorization**

```php
return DB::transaction(function () use ($document, $clientUpdateId, $envelope, $deviceToken) {
    $locked = VaultCollaborationDocument::query()->lockForUpdate()->findOrFail($document->id);
    $existing = $locked->updates()->where('client_update_id', $clientUpdateId)->first();

    if ($existing) {
        return $existing;
    }

    $sequence = ++$locked->latest_sequence;
    $locked->save();

    return $locked->updates()->create([
        'sequence' => $sequence,
        'client_update_id' => $clientUpdateId,
        'device_token_id' => $deviceToken->id,
        'payload' => $envelope->payload,
        'payload_sha256' => $envelope->payloadSha256,
        'is_encrypted' => $envelope->encrypted,
        'encryption_iv' => $envelope->iv,
        'encryption_tag' => $envelope->tag,
        'format_version' => $envelope->formatVersion,
    ]);
});
```

The real implementation must pass explicit validated fields; it must not mass-assign request data.

- [ ] **Step 4: Implement checkpoint retention rules**

Non-E2EE checkpoints may be server-compacted later. E2EE checkpoints are opaque full-state updates with an acknowledged base sequence. Retain superseded encrypted updates for the configured recovery period and do not prune during the initial rollout.

- [ ] **Step 5: Run concurrency-sensitive tests and score**

Run the collaboration file repeatedly after a deterministic duplicate/locking test, then the relevant authorization suite, PHPStan, and Pint. Require at least 9/10.

---

### Task 7: Implement reusable Yjs providers and convergence tests

**Files:**
- Create: `resources/js/collaboration/yjs-provider.js`
- Create: `resources/js/collaboration/encrypted-update-codec.js`
- Create: `obsidian-plugin/src/yjsProvider.js`
- Create: `obsidian-plugin/src/encryptedUpdateCodec.js`
- Mirror plugin files in: `/Users/mac/Herd/obsidian-synkk-sync/src/`
- Test: `tests/JavaScript/yjs-provider.test.js`
- Test: `obsidian-plugin/tests/yjs-provider.test.js`
- Test: `/Users/mac/Herd/obsidian-synkk-sync/tests/yjs-provider.test.js`

**Interfaces:**
- Produces: `SynkkYjsProvider`, `connect()`, `disconnect()`, `applyCatchUp()`, `queueLocalUpdate()`, `acknowledge()`, and `destroy()`.
- Consumes: Task 6 journal endpoints and Task 4 in-memory E2EE engine.

- [ ] **Step 1: Write convergence tests using independent Yjs documents**

```js
const alice = new Y.Doc();
const bob = new Y.Doc();
alice.getText('markdown').insert(0, 'Alpha');
bob.getText('markdown').insert(0, 'Beta');

const aliceUpdate = Y.encodeStateAsUpdate(alice);
const bobUpdate = Y.encodeStateAsUpdate(bob);
Y.applyUpdate(alice, bobUpdate);
Y.applyUpdate(bob, aliceUpdate);

assert.equal(alice.getText('markdown').toString(), bob.getText('markdown').toString());
```

Add reverse delivery, duplicates, offline outbox replay, acknowledgement, reconnect catch-up, wrong-key refusal, and encrypted payload tests.

- [ ] **Step 2: Implement the transport-independent provider**

The provider keeps a stable `clientUpdateId` until acknowledged, tags remote applications with the provider origin to prevent resend loops, and fetches durable updates before declaring itself live.

- [ ] **Step 3: Implement encrypted update encoding**

Bind AES-GCM additional authenticated data to vault ID, document ID, update purpose, and format version. Any authentication failure locks the document and leaves the durable sequence unacknowledged.

- [ ] **Step 4: Run JS convergence suites and score**

Run root and both plugin JavaScript tests plus both plugin type checks. Require deterministic convergence across repeated runs and at least 9/10.

---

### Task 8: Add Reverb delivery and private channel authorization

**Files:**
- Create: `app/Events/VaultCollaborationUpdateCommitted.php`
- Create: `app/Broadcasting/VaultCollaborationChannel.php`
- Modify: `routes/channels.php`
- Modify: `bootstrap/app.php`
- Modify: `config/broadcasting.php`
- Modify: `config/reverb.php`
- Create or modify: `resources/js/echo.js`
- Modify: `resources/js/app.js`
- Modify: `resources/js/collaboration/yjs-provider.js`
- Modify: `obsidian-plugin/src/yjsProvider.js`
- Test: `tests/Feature/VaultCollaborationBroadcastTest.php`
- Test: `tests/JavaScript/yjs-provider.test.js`

**Interfaces:**
- Produces: private `vault-collaboration.{documentId}` channels and after-commit update events.
- Consumes: durable journal records and central read authorization.

- [ ] **Step 1: Add failing channel and event tests**

Test web owner, permitted member, hidden member, permitted device, read-only device, excluded-vault device, revoked device, and cross-team access. Append tests must assert one event after commit and zero events for duplicates or rolled-back writes.

- [ ] **Step 2: Authorize server-controlled document channels**

Resolve the numeric document ID, derive its vault/path from the database, and authorize that resource. Never authorize a channel using a client-provided vault slug or path alone.

- [ ] **Step 3: Broadcast only opaque update metadata and payload**

The event contains document ID, server sequence, client update ID, encrypted flag, payload Base64, IV, tag, and format version. It excludes bearer tokens, passphrases, email addresses, and unrelated file content.

- [ ] **Step 4: Attach Reverb to both providers**

Subscribe only after durable catch-up. On reconnect, fetch after the last applied sequence before processing buffered live events. Deduplicate by server sequence and Yjs update identity.

- [ ] **Step 5: Verify broadcast configuration and score**

Run focused PHP/JS tests, start Reverb locally, connect two authorized clients, and confirm hidden/revoked clients cannot subscribe. Require at least 9/10.

---

### Task 9: Bind Yjs to the web and Obsidian CodeMirror editors

**Files:**
- Modify: `resources/js/markdown-editor.js`
- Modify: `resources/views/pages/vaults/show.blade.php`
- Modify: `obsidian-plugin/src/collabExtension.ts`
- Modify: `obsidian-plugin/src/collabRelay.ts`
- Modify: `obsidian-plugin/src/main.ts`
- Mirror plugin changes in: `/Users/mac/Herd/obsidian-synkk-sync/src/`
- Test: `tests/JavaScript/markdown-editor.test.js`
- Test: `obsidian-plugin/tests/codemirror-extension.test.js`
- Test: `tests/Feature/VaultEditorTest.php`

**Interfaces:**
- Produces: one Yjs `Y.Text('markdown')` per active note, live awareness/cursors, shared undo manager, lock/unlock lifecycle, and debounced durable file snapshots.
- Consumes: `SynkkYjsProvider`, `yCollab`, Reverb configuration, and Livewire file selection.

- [ ] **Step 1: Add editor lifecycle tests**

Cover initialization from snapshot, remote updates, local updates, note switching, component teardown, passphrase clearing, read-only mode, hidden-note refusal, and no plaintext assignment to Livewire properties for E2EE notes.

- [ ] **Step 2: Replace decorative collaboration with a real Yjs binding**

```js
const ydoc = new Y.Doc();
const ytext = ydoc.getText('markdown');
const undoManager = new Y.UndoManager(ytext);

const collaboration = yCollab(ytext, provider.awareness, { undoManager });
```

Use awareness for cursor/name/color data. Destroy the old provider, listeners, document, and keys before opening another file.

- [ ] **Step 3: Flush converged text through whole-file history**

After a quiet interval, one writable client serializes current Markdown, encrypts it when required, and sends the normal idempotent upload envelope. A read-only client never becomes the snapshot writer.

- [ ] **Step 4: Run two-client runtime verification and score**

Open independent sessions, type simultaneously at the same position, disconnect one, edit both, reconnect, and verify byte-identical final Markdown and visible carets. Inspect browser logs and network failures. Require at least 9/10.

---

### Task 10: Make QR pairing atomic, scoped, and one-scan

**Files:**
- Modify: `app/Services/QrPairingService.php`
- Modify: `app/Http/Controllers/Api/AuthController.php`
- Modify: `resources/views/pages/devices/index.blade.php`
- Modify: `obsidian-plugin/src/main.ts`
- Modify: `obsidian-plugin/src/settings.ts`
- Modify: `obsidian-plugin/src/apiClient.ts`
- Mirror plugin changes in: `/Users/mac/Herd/obsidian-synkk-sync/src/`
- Test: `tests/Feature/QrPairingTest.php`
- Test: `obsidian-plugin/tests/pairing.test.js`

**Interfaces:**
- Produces: atomic session creation/exchange/status and `obsidian://synkk-pair` handling.
- Consumes: `DeviceVaultAccess`, selected vault, requested scope, protocol v2, and existing token creation.

- [ ] **Step 1: Add replay, scope, and URL tests**

Test simultaneous exchange attempts create exactly one token; child tokens cannot broaden scope; the QR contains no bearer token/key; malformed and non-HTTPS production origins are refused; local HTTP is accepted only for explicit loopback development.

- [ ] **Step 2: Implement atomic exchange**

Use `Cache::lock($cacheKey.'_lock', 5)->block(...)` and consume the secret session state once. Store a separate short-lived claimed-status value without token material.

- [ ] **Step 3: Generate and handle the Obsidian protocol URL**

```ts
this.registerObsidianProtocolHandler('synkk-pair', async (params) => {
  const result = await this.pairingService.exchange(params);
  await this.applyVerifiedPairing(result);
});
```

Show server origin, team, vault, and scope before enabling sync; run a read-only connection check immediately after exchange.

- [ ] **Step 4: Verify desktop and physical-mobile paths, then score**

Automate URL parsing and exchange. Test one actual iOS or Android camera-to-Obsidian handoff when a device is available. Without physical-device evidence, mark mobile one-scan as partial and do not publish the mobile claim.

---

### Task 11: Harden RAG, vault performance, Docker, proxies, and packaging

**Files:**
- Modify: `app/Jobs/IndexVaultRagJob.php`
- Modify: `app/Services/VaultRagService.php`
- Modify: `app/Http/Controllers/Api/VaultSyncController.php`
- Modify: `config/queue.php`
- Modify: `resources/views/pages/vaults/show.blade.php`
- Modify: `bootstrap/app.php`
- Modify: `Dockerfile`
- Create: `.dockerignore`
- Modify: `docker-compose.yml`
- Modify: `docker/nginx/default.conf`
- Modify: `docker/supervisord.conf`
- Modify: `app/Console/Commands/PackageCommunityEdition.php`
- Test: `tests/Feature/VaultRagTest.php`
- Test: `tests/Feature/VaultPerformanceTest.php`
- Test: `tests/Feature/TrustedProxyTest.php`
- Test: `tests/Feature/PackageCommunityEditionTest.php`

**Interfaces:**
- Produces: unique per-vault indexing, bounded UI queries, explicit proxy trust, Reverb-ready production containers, and installable Community Edition archives.
- Consumes: existing RAG, graph, queue, Docker, and packaging flows.

- [ ] **Step 1: Add failing operational regression tests**

Test one queued RAG job per vault, no server RAG for E2EE vaults, no synchronous full index in query, spoofed `X-Forwarded-For` refusal, paginated vault data, and archive inclusion of both lockfiles.

- [ ] **Step 2: Make RAG unique and bounded**

Implement `ShouldBeUnique`, return the vault ID from `uniqueId()`, stream file rows, batch embedding upserts, and expose queued/running/complete/failed status. Set queue `retry_after` at least 30 seconds above job/worker timeout.

- [ ] **Step 3: Bound vault page work**

Paginate file metadata before reading content. Cache graph projection by vault latest version and load a bounded history page only for the active file.

- [ ] **Step 4: Harden proxy and container boundaries**

Read trusted proxies from validated configuration and default to none for direct exposure. Add `.dockerignore`, explicit production copies, Reverb process management, WebSocket proxy headers, allowed origins, and service health checks.

- [ ] **Step 5: Repair Community Edition packaging**

Include `composer.lock`, `package-lock.json`, `.dockerignore`, Reverb configuration, and required runtime files. Extract the archive into a temporary directory and run dependency/install validation without using secrets.

- [ ] **Step 6: Benchmark, build, and score**

Run focused tests, a repeatable representative-vault benchmark, Docker Compose validation, production image build, health checks, PHPStan, and Pint. Require at least 9/10.

---

### Task 12: Align product UX and run the release-quality verification loop

**Files:**
- Modify: `resources/views/welcome.blade.php`
- Modify: `resources/views/pages/docs/index.blade.php`
- Modify: `resources/views/pages/devices/index.blade.php`
- Modify: `resources/views/pages/vaults/show.blade.php`
- Modify: `obsidian-plugin/README.md`
- Modify: `README.md`
- Modify: `tests/Feature/LandingPageTest.php`
- Modify: relevant onboarding/editor tests from earlier tasks

**Interfaces:**
- Produces: one verified capability matrix, truthful public language, visible sync health, secure unlock flow, and launch verdict.
- Consumes: executable evidence from Tasks 1-11.

- [ ] **Step 1: Build the capability matrix from test evidence**

Use `Shipped`, `Verified with limitation`, and `Planned`. “Two-second pairing” becomes “one-scan pairing”; “native background sync” becomes “automatic sync while Obsidian is active.” E2EE copy discloses visible metadata and disabled server RAG.

- [ ] **Step 2: Add failing product-contract assertions**

Assert shipped capability names and required limitation text. Assert that unsupported phrases such as guaranteed pairing time, peer-to-peer, hidden metadata, and native mobile background execution are absent.

- [ ] **Step 3: Add polished operational UX**

Display connection identity, protocol version, encryption lock state, last acknowledged remote version, pending outbox count, last error, conflict/safety halt, Reverb state, and recovery actions. Follow the existing Tailwind v4, dark-mode, Flux, and accessibility conventions.

- [ ] **Step 4: Run the complete automated verification matrix**

```bash
vendor/bin/pint --format agent
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php artisan test --compact --no-coverage
npm run test:js
npm run build
npm --prefix obsidian-plugin test
npm --prefix obsidian-plugin run build
/Users/mac/Herd/obsidian-synkk-sync/node_modules/.bin/tsc --project /Users/mac/Herd/obsidian-synkk-sync/tsconfig.json --noEmit
npm --prefix /Users/mac/Herd/obsidian-synkk-sync test
npm --prefix /Users/mac/Herd/obsidian-synkk-sync run build
composer audit
git diff --check
docker compose config
```

Expected: every command exits zero. Any failure is classified, fixed at its root cause, and the affected focused checks are rerun before this matrix repeats.

- [ ] **Step 5: Run browser and protocol verification**

Verify welcome, docs, devices, vault editor, locked E2EE vault, plain vault, two-client collaboration, conflict recovery, and narrow/mobile layouts. Check browser console/network logs, keyboard operation, focus, reduced motion, contrast, and no plaintext leakage through Livewire payloads.

- [ ] **Step 6: Produce the final score and release verdict**

Score production readiness, problem-solution fit, performance, and innovation independently. Do not declare release-ready unless the overall implementation score is at least 9/10, every stage is at least 9/10, no Critical/High issue remains, and every shipped claim maps to fresh evidence.

## Execution workflow

Execute Tasks 1-12 in order. At the end of each task record:

```text
Task identifier and name:
Focused test commands and results:
Broader check commands and results:
Contract correctness score, maximum 3:
Security and tenant isolation score, maximum 3:
Data integrity and recovery score, maximum 2:
Executed evidence score, maximum 2:
Total score, maximum 10:
Open Critical or High findings:
Decision and supporting evidence:
```

If the decision is `repeat task`, return to the smallest failing step, make one causal correction, rerun the focused evidence, re-score, and advance only after reaching the gate.
