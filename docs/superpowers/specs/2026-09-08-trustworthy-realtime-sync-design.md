# Synkk Trustworthy Realtime Sync Design

## Objective

Make Synkk's shipped behavior match its public promise: secure one-scan device pairing, scoped and revocable device access, client-side encrypted vault content, recoverable encrypted history, real concurrent Markdown editing with Yjs, reliable ghost-file behavior, and a production deployment that does not expose secrets or duplicate long-running work.

This design treats whole-file synchronization as the durable file and version-history layer. Yjs provides live collaborative editing and offline convergence for active Markdown documents. Laravel persists the collaboration journal and Laravel Reverb distributes new updates to connected clients.

## Product contract

The release may claim the following only after every acceptance gate passes:

- Whole-file synchronization across Obsidian and the Synkk web workspace.
- Deterministic Yjs convergence for simultaneous Markdown edits.
- Live collaborator presence and cursors while clients are connected.
- Offline edits that converge after reconnecting.
- One-scan mobile pairing through an Obsidian protocol URL.
- Scoped, revocable device tokens and remote wipe enforcement.
- Client-side AES-256-GCM encryption for vault file contents and collaborative updates.
- On-demand ghost-file hydration and dehydration for large attachments.
- Version restore that preserves the exact encrypted or ghost representation.

The release must not claim a guaranteed two-second pairing time, peer-to-peer transport, concealed paths or metadata, native operating-system background execution, or official Obsidian Community Plugins distribution unless those capabilities receive separate implementation and device-level verification.

## Scope and compatibility

### In scope

- Laravel API, authorization, validation, persistence, queues, broadcasting, deployment configuration, and Community Edition packaging.
- Livewire/CodeMirror web editor integration.
- The embedded `obsidian-plugin` submodule checkout and the matching standalone `/Users/mac/Herd/obsidian-synkk-sync` checkout.
- Existing whole-file clients through an explicit protocol compatibility window.
- Public landing page, plugin README, onboarding, and capability indicators.

### Compatibility policy

- Existing non-E2EE whole-file clients remain supported during a protocol-v1 compatibility window.
- E2EE vault mutations require protocol v2 immediately because accepting a v1 plaintext write would violate the security contract.
- The manifest response identifies `protocol_version`, `minimum_protocol_version`, `vault_id`, `device_id`, and enabled capabilities.
- A client below the required version receives HTTP 426 with an upgrade message and performs no local or remote mutation.
- Existing plugin state is migrated into a context-specific v2 state file. State that cannot be safely attributed to the selected server and vault is archived locally and followed by a full manifest reconciliation.
- Existing file-version rows are representation version 1. Newly created snapshots are representation version 2 and contain the complete content envelope.

## Trust and threat model

### Trusted components

- The user's active browser or Obsidian plugin process after successful authentication.
- The local passphrase supplied by the user for an E2EE vault.
- Laravel authorization and storage isolation after requests cross the API boundary.

### Untrusted inputs and infrastructure

- Every API payload, path, cursor, client identifier, forwarding header, and WebSocket subscription request.
- Device tokens whose scope may be read-only or limited to specific vaults and paths.
- The database, object storage, reverse proxy, queue, broadcast transport, and server operator with respect to E2EE content.
- Replayed QR sessions, collaboration updates, and batch items.

### E2EE confidentiality boundary

For an E2EE vault, the server may observe team and vault identifiers, normalized paths, payload sizes, versions, timestamps, device identifiers, and update frequency. The server must never receive plaintext file contents, plaintext Yjs updates, the passphrase, or the derived encryption key.

The client uses PBKDF2-SHA-256 with a per-vault random salt and a versioned iteration count, then encrypts each payload independently using AES-256-GCM with a fresh 96-bit IV. The authenticated additional data binds the ciphertext to the vault ID, normalized path, representation version, and payload purpose (`file`, `collaboration-update`, `collaboration-checkpoint`, or `verification`).

## Authorization design

### Central service

Create a `DeviceVaultAccess` service with three public operations:

```php
public function authorizeRead(DeviceToken $token, Vault $vault, string $path): void;
public function authorizeWrite(DeviceToken $token, Vault $vault, string $path): void;
public function authorizeAdmin(DeviceToken $token, Vault $vault): void;
```

Every operation first proves that the token is active, belongs to the vault's team, includes the vault when `allowed_vault_ids` is set, passes the configured IP boundary, and belongs to an active team member. Path operations normalize and validate the path before applying `read_write`, `read_only`, or `hidden` membership rules.

`authorizeRead` accepts `read_write` and `read_only`; `authorizeWrite` accepts only `read_write` and refuses a token whose access scope is `read_only`; `authorizeAdmin` requires a full-access token owned by a team owner or administrator.

### Endpoint mapping

- Manifest, download, history, collaboration catch-up, and presence require read access to the requested path.
- Upload, delete, batch mutation, conflict resolution, collaboration publication, hydration-state mutation, and restore require write access.
- Vault-wide E2EE activation, E2EE rotation, indexing configuration, and new pairing-session creation require administrator access.
- Conflict resolution authorizes both the canonical and conflict paths, verifies that both records belong to the same vault, and verifies that the conflict filename maps to the supplied canonical path.

### Pairing derivation

A pairing session records the initiating principal, team, selected vault, requested access scope, and expiry. The resulting token may never have broader access than the initiator. A web administrator may create a full-access token; a device-token caller can create only an equal-or-narrower child token.

Exchange uses a cache lock and an atomic claim operation. A separate non-secret claimed-status record allows the originating screen to display success after the secret session value has been consumed. Replays return a generic expired-or-used response and create no token.

## Content envelope

Every upload and historical snapshot uses one representation object:

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

Rules:

- `payloadSha256` always hashes the exact stored bytes.
- The server does not accept a client-supplied hash without recomputing it.
- An E2EE vault requires `encrypted=true`, a valid 12-byte IV, a valid 16-byte GCM tag, and format version 2.
- A non-E2EE vault rejects encryption metadata unless an explicit import path is used.
- Ghost status describes the local representation requested by a client; it does not replace or overwrite the canonical server payload with the Markdown stub.
- A conflict copy inherits the complete representation metadata of the uploaded payload.
- Upload, metadata persistence, file-version snapshot, and changelog creation occur in one application transaction with compensating storage cleanup on failure.

## Plugin state and sync algorithm

### Context-specific state

The plugin state key is derived from the normalized server origin, server-issued vault ID, and device ID. No bearer token or passphrase is written into a filename or state key.

```ts
interface SyncFileState {
  localPlaintextSha256: string | null;
  remotePayloadSha256: string;
  remoteVersion: number;
  representation: 'plain' | 'encrypted' | 'ghost';
  hydrated: boolean;
}

interface SyncContextState {
  formatVersion: 2;
  serverFingerprint: string;
  vaultId: number;
  vaultSlug: string;
  deviceId: number;
  lastRemoteVersion: number;
  files: Record<string, SyncFileState>;
}
```

### Reconciliation order

1. Authenticate and fetch the current identity/capability document.
2. Select or create state for that exact connection context.
3. Fetch remote changes after `lastRemoteVersion`.
4. Validate every remote path and representation before touching the local vault.
5. Decrypt E2EE content in memory or create a ghost stub without changing the canonical remote hash.
6. Snapshot any existing local bytes before overwrite or deletion.
7. Write the local representation atomically.
8. Calculate local changes against `localPlaintextSha256`, never against ciphertext or stub hashes.
9. Apply Safety Shield before local or remote deletion batches.
10. Encrypt changed content when the manifest requires E2EE; refusal or missing keys halt the item without advancing its checkpoint.
11. Upload idempotent operations and record returned remote versions and payload hashes.
12. Advance the global checkpoint only after every earlier remote change is either applied or durably recorded as a recoverable failure.

The engine serializes sync runs per context. A second trigger joins the active run instead of starting concurrent scans and uploads.

## Yjs collaboration

### Document identity

The server issues a stable collaboration document ID derived from the vault ID and normalized path. Clients never choose a different vault or path through a document ID.

### Durable journal

Create `vault_collaboration_documents` and `vault_collaboration_updates` tables.

Each document stores vault ID, normalized path, latest sequence, latest checkpoint sequence, and timestamps. Each update stores document ID, monotonically increasing server sequence, client update ID, originating device, encrypted flag, payload, IV, tag, format version, and timestamp. `(document_id, client_update_id)` is unique, making retries idempotent.

For non-E2EE vaults, an asynchronous compaction job merges Yjs updates into a checkpoint. For E2EE vaults, a client may publish an encrypted full-state checkpoint after acknowledging all updates through a base sequence. The server retains the replaced encrypted journal for a recovery interval before pruning; it never attempts to inspect or merge encrypted Yjs payloads.

### Client flow

1. Load the durable file snapshot into a new `Y.Doc`.
2. Fetch and apply the latest checkpoint and all journal updates after it.
3. Bind `Y.Text('markdown')` to CodeMirror 6 with `y-codemirror.next`.
4. Subscribe to the private Reverb document channel.
5. On local Yjs update, encrypt when required, POST it with a stable client update ID, and retain it in a local outbox until acknowledged.
6. Laravel commits the update and then broadcasts its server sequence.
7. Receiving clients fetch or apply the update once; Yjs update idempotency protects duplicate delivery.
8. On reconnect, clients fetch after their last durable sequence before accepting the live stream.
9. A debounced owner client serializes the converged Markdown into the ordinary file-sync layer, preserving version history and non-collaborative client compatibility.

Presence and cursor data are ephemeral, rate-limited, path-authorized, and never persisted in the content journal. In an E2EE vault, presence names remain operational metadata and are disclosed in product documentation.

### Broadcast authorization

Reverb channels are private. Web users authorize through the session guard; Obsidian clients authorize through device-token middleware. Both routes resolve the document from the server-controlled ID and call the central read access service. Read-only clients may subscribe and receive updates but cannot publish updates.

## Web editor behavior

The Livewire component remains responsible for selecting vaults/files, authorizing actions, and presenting server state. A dedicated JavaScript collaboration controller owns Yjs, CodeMirror binding, Reverb connection, local encryption, reconnect, and teardown.

For an E2EE vault:

- The editor begins locked.
- The user supplies the passphrase locally.
- A client-side verification envelope proves the key before content is requested.
- The passphrase and key remain in memory for the tab session and are cleared on logout, vault switch, page teardown, and explicit lock.
- Creating, saving, restoring, and resolving a conflict is disabled while locked.
- PHP/Livewire properties never contain plaintext editor content.

For non-E2EE vaults, the same Yjs collaboration path runs without encryption so both modes share convergence behavior.

## Encrypted version history

Add representation fields to `vault_file_versions`: `is_encrypted`, `encryption_iv`, `encryption_tag`, `is_ghost`, `original_size`, `mime_type`, and `format_version`.

Creating a version copies the complete envelope from the previous active record. Restoring a version creates a new active version with the selected snapshot's exact stored bytes and metadata. The web client decrypts restored E2EE bytes locally; the server never puts ciphertext directly into a text editor property.

Legacy rows use format version 1. A legacy encrypted row without sufficient metadata is reported as non-restorable instead of being guessed or silently corrupted.

## Ghost files

The server always keeps the canonical binary payload. `download?ghost=1` returns a generated local stub carrying normalized path, canonical size, MIME type, and remote payload hash. Uploading that unchanged stub is forbidden. Hydration downloads and verifies the canonical bytes; dehydration verifies that local content matches the known canonical hash before replacing it with a stub.

The plugin state records whether a path is hydrated. A ghost stub hash is never stored as the remote payload hash and never participates in upload-change detection.

## Pairing and onboarding

The QR code encodes an `obsidian://synkk-pair` URL containing the HTTPS API origin, opaque one-use session identifier, selected vault slug, and protocol version. It contains no permanent bearer token or E2EE key.

The plugin registers `synkk-pair`, validates HTTPS except for explicit local-development origins, exchanges the session, displays the server/team/vault/scope result, stores only the scoped and revocable device token in plugin data, and performs a read-only connection check before enabling sync. The E2EE passphrase and derived key are never persisted. Existing persisted passphrase fields are deleted during state migration after the user is told that the vault will lock again on restart.

The dashboard reports pending, paired, expired, and revoked states without redisplaying the issued token.

## Queue, performance, and operations

### RAG

`IndexVaultRagJob` becomes unique per vault and remains idempotent. Queue `retry_after` exceeds the worker timeout by at least 30 seconds. Search never starts a full synchronous index. Indexing streams files, batches embedding persistence, and records observable queued/running/complete/failed state.

E2EE vaults cannot be indexed on the server because the server cannot read their content. The UI states this explicitly and disables server-side RAG. A future client-side RAG design is outside this release.

### Vault UI and graph

File metadata is paginated before content is loaded. Graph projection runs from bounded metadata/link records and is cached by vault version. Opening one file fetches only that file and its bounded history page.

### Deployment

- Add a `.dockerignore` excluding `.env*`, Git metadata, credentials, cookies, local databases, caches, dependency trees, test output, and developer artifacts.
- Copy only declared production inputs into image stages.
- Pin external build images to reviewed versions or digests.
- Configure the Reverb process under Supervisor and route WebSocket upgrades through Nginx.
- Configure allowed Reverb origins explicitly.
- Trust only configured reverse-proxy addresses; direct Docker exposure does not trust client forwarding headers.
- Provide health checks for HTTP, queue, scheduler, and Reverb processes.
- Include `composer.lock` and `package-lock.json` in Community Edition archives.

## API failure behavior

- Authentication failure: HTTP 401.
- Cross-team or hidden-resource lookup: HTTP 404.
- Authenticated but insufficient write/admin scope: HTTP 403.
- Invalid path or content envelope: HTTP 422 with field-specific errors.
- Client protocol below the vault minimum: HTTP 426.
- Pairing session expired or already consumed: HTTP 410.
- Duplicate collaboration update: HTTP 200 with the original sequence and no second record or broadcast.
- Collaboration cursor/presence throttling: HTTP 429 without dropping durable document updates.
- E2EE key verification failure occurs client-side and sends no file request.
- Storage failure rolls back database state and leaves the previous active representation intact.

## Validation strategy

### Laravel feature and integration tests

- Complete device-token authorization matrix across every API endpoint.
- Cross-team, cross-vault, hidden-path, read-only, revoked, wiped, and IP-limited cases.
- Atomic pairing exchange and scope derivation.
- E2EE envelope validation and plaintext rejection.
- Version snapshot and restore metadata fidelity.
- Collaboration idempotency, ordering, catch-up, channel authorization, and transaction-after-broadcast behavior.
- RAG unique dispatch, E2EE prohibition, queue-state reporting, and timeout configuration.
- Community Edition archive contents and production Docker context exclusions.

### JavaScript and plugin tests

- Yjs convergence for concurrent operations delivered in different orders.
- Duplicate and delayed collaboration updates.
- Encrypted update round trips and wrong-key refusal.
- Context-specific state migration and vault/server switching.
- Pull-then-sync idempotency for plaintext, encrypted, and ghost representations.
- Offline outbox replay and acknowledgement.
- Pairing URL parsing and refusal of insecure or malformed origins.
- Editor teardown, passphrase clearing, and reconnect ordering.

### Runtime checks

- Two independent browser/plugin clients edit the same document and converge with visible carets.
- Network interruption, offline edits, restart, and reconnect preserve content.
- A server/database inspection confirms E2EE plaintext markers are absent from file, version, collaboration, queue, cache, and log storage.
- A mobile camera opens the installed Obsidian plugin and completes one-scan pairing.
- Docker production image starts HTTP, worker, scheduler, and Reverb services; WebSocket upgrades and health checks pass.
- A representative large vault meets recorded response-time and memory budgets before claims are marked shipped.

## Stage score gate

Every implementation stage is scored out of ten:

- Contract correctness: 3 points.
- Security and tenant isolation: 3 points.
- Data integrity and recovery: 2 points.
- Executed test and operational evidence: 2 points.

A stage advances only at 9/10 or higher, with no unresolved Critical or High finding. A failed focused check returns the work to the smallest responsible implementation step. Repeating a command without identifying and fixing the cause does not improve the score.

## Delivery order

1. Authorization and protocol-v2 envelope boundary.
2. E2EE, ghost representation, and context-specific plugin state.
3. Encrypted history and recovery.
4. Yjs durable journal and convergence without live transport.
5. Reverb transport, channel authorization, presence, and editor bindings.
6. One-scan pairing and onboarding.
7. Queue, RAG, vault UI, Docker, and packaging hardening.
8. Capability matrix, public copy, production builds, runtime verification, and release verdict.

Each item is independently testable and leaves the application in a coherent state. External deployment, publication, Git push, release creation, credential rotation, and marketplace submission remain outside this implementation unless separately requested.
