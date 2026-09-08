<?php

namespace App\Http\Controllers\Api;

use App\Actions\Collaboration\AppendCollaborationUpdateAction;
use App\Actions\Collaboration\PublishCollaborationCheckpointAction;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Vault;
use App\Models\VaultCollaborationDocument;
use App\Models\VaultCollaborationUpdate;
use App\Services\CrdtCollabService;
use App\Services\DeviceVaultAccess;
use App\ValueObjects\VaultContentEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VaultCollaborationController extends Controller
{
    public function __construct(
        protected DeviceVaultAccess $vaultAccess,
        protected CrdtCollabService $collabService,
        protected AppendCollaborationUpdateAction $appendAction,
        protected PublishCollaborationCheckpointAction $checkpointAction,
    ) {}

    /**
     * Join a document collaboration session.
     */
    public function join(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');

        $validated = $request->validate([
            'path' => ['required', 'string'],
            'peer_id' => ['nullable', 'string'],
        ]);

        $cleanPath = trim(str_replace('\\', '/', $validated['path']), '/');
        $peerId = $validated['peer_id'] ?? 'peer_'.Str::random(10);

        $this->vaultAccess->authorizeRead($deviceToken, $vault, $cleanPath);

        $document = VaultCollaborationDocument::firstOrCreate(
            ['vault_id' => $vault->id, 'path' => $cleanPath],
            ['latest_sequence' => 0, 'is_active' => true]
        );

        $joinResult = $this->collabService->join($vault, $deviceToken->user, $cleanPath, $peerId);

        return response()->json([
            'status' => 'joined',
            'document_id' => $document->id,
            'room_id' => "vault_{$vault->id}_doc_{$document->id}",
            'latest_sequence' => $document->latest_sequence,
            'clock' => $document->latest_sequence,
            'peers' => $joinResult['peers'],
            'deltas' => $joinResult['deltas'],
        ]);
    }

    /**
     * Catch up on durable updates after a sequence number.
     */
    public function catchUp(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');

        $validated = $request->validate([
            'document_id' => ['nullable', 'integer'],
            'path' => ['nullable', 'string'],
            'after_sequence' => ['nullable', 'integer', 'min:0'],
        ]);

        $document = $this->resolveDocument($vault, $validated);
        $this->vaultAccess->authorizeRead($deviceToken, $vault, $document->path);

        $afterSequence = (int) ($validated['after_sequence'] ?? 0);

        $query = $document->updates()->orderBy('sequence', 'asc');

        $latestCheckpoint = $document->updates()
            ->where('is_checkpoint', true)
            ->orderByDesc('sequence')
            ->first();

        if ($latestCheckpoint && $afterSequence < $latestCheckpoint->sequence) {
            $query->where('sequence', '>=', $latestCheckpoint->sequence);
        } else {
            $query->where('sequence', '>', $afterSequence);
        }

        $updates = $query->get()
            ->map(fn (VaultCollaborationUpdate $update): array => [
                'id' => $update->id,
                'sequence' => $update->sequence,
                'client_update_id' => $update->client_update_id,
                'payload' => $update->payload,
                'payload_sha256' => $update->payload_sha256,
                'is_encrypted' => $update->is_encrypted,
                'encryption_iv' => $update->encryption_iv,
                'encryption_tag' => $update->encryption_tag,
                'format_version' => $update->format_version,
                'is_checkpoint' => $update->is_checkpoint,
                'acknowledged_base_sequence' => $update->acknowledged_base_sequence,
                'created_at' => $update->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'status' => 'ok',
            'document_id' => $document->id,
            'latest_sequence' => $document->latest_sequence,
            'updates' => $updates,
        ]);
    }

    /**
     * Idempotently append a CRDT update.
     */
    public function append(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');

        $validated = $request->validate([
            'document_id' => ['nullable', 'integer'],
            'path' => ['nullable', 'string'],
            'client_update_id' => ['required', 'string'],
            'payload' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'payload_sha256' => ['nullable', 'string'],
            'sha256' => ['nullable', 'string'],
            'encrypted' => ['nullable', 'boolean'],
            'is_encrypted' => ['nullable', 'boolean'],
            'iv' => ['nullable', 'string'],
            'encryption_iv' => ['nullable', 'string'],
            'tag' => ['nullable', 'string'],
            'encryption_tag' => ['nullable', 'string'],
            'original_size' => ['nullable', 'integer'],
            'format_version' => ['nullable', 'integer'],
        ]);

        $document = $this->resolveDocument($vault, $validated);
        $this->vaultAccess->authorizeWrite($deviceToken, $vault, $document->path);

        $envelope = $this->buildEnvelope($vault, $validated);

        $update = $this->appendAction->execute(
            document: $document,
            clientUpdateId: $validated['client_update_id'],
            envelope: $envelope,
            deviceToken: $deviceToken,
            user: $deviceToken->user
        );

        return response()->json([
            'status' => 'committed',
            'document_id' => $document->id,
            'update' => [
                'id' => $update->id,
                'sequence' => $update->sequence,
                'client_update_id' => $update->client_update_id,
                'payload' => $update->payload,
                'payload_sha256' => $update->payload_sha256,
                'is_encrypted' => $update->is_encrypted,
                'encryption_iv' => $update->encryption_iv,
                'encryption_tag' => $update->encryption_tag,
                'format_version' => $update->format_version,
                'is_checkpoint' => $update->is_checkpoint,
                'created_at' => $update->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Publish a consolidated checkpoint update.
     */
    public function checkpoint(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');

        $validated = $request->validate([
            'document_id' => ['nullable', 'integer'],
            'path' => ['nullable', 'string'],
            'client_update_id' => ['required', 'string'],
            'acknowledged_base_sequence' => ['required', 'integer', 'min:0'],
            'payload' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'payload_sha256' => ['nullable', 'string'],
            'sha256' => ['nullable', 'string'],
            'encrypted' => ['nullable', 'boolean'],
            'is_encrypted' => ['nullable', 'boolean'],
            'iv' => ['nullable', 'string'],
            'encryption_iv' => ['nullable', 'string'],
            'tag' => ['nullable', 'string'],
            'encryption_tag' => ['nullable', 'string'],
            'original_size' => ['nullable', 'integer'],
            'format_version' => ['nullable', 'integer'],
        ]);

        $document = $this->resolveDocument($vault, $validated);
        $this->vaultAccess->authorizeWrite($deviceToken, $vault, $document->path);

        $envelope = $this->buildEnvelope($vault, $validated);

        $update = $this->checkpointAction->execute(
            document: $document,
            clientUpdateId: $validated['client_update_id'],
            acknowledgedBaseSequence: (int) $validated['acknowledged_base_sequence'],
            envelope: $envelope,
            deviceToken: $deviceToken,
            user: $deviceToken->user
        );

        return response()->json([
            'status' => 'checkpoint_committed',
            'document_id' => $document->id,
            'update' => [
                'id' => $update->id,
                'sequence' => $update->sequence,
                'client_update_id' => $update->client_update_id,
                'payload' => $update->payload,
                'payload_sha256' => $update->payload_sha256,
                'is_encrypted' => $update->is_encrypted,
                'encryption_iv' => $update->encryption_iv,
                'encryption_tag' => $update->encryption_tag,
                'format_version' => $update->format_version,
                'is_checkpoint' => $update->is_checkpoint,
                'acknowledged_base_sequence' => $update->acknowledged_base_sequence,
                'created_at' => $update->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Leave a collaboration room.
     */
    public function leave(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');

        $validated = $request->validate([
            'path' => ['required', 'string'],
            'peer_id' => ['required', 'string'],
        ]);

        $cleanPath = trim(str_replace('\\', '/', $validated['path']), '/');
        $this->vaultAccess->authorizeRead($deviceToken, $vault, $cleanPath);

        $this->collabService->leave($vault, $cleanPath, $validated['peer_id']);

        return response()->json(['status' => 'left']);
    }

    /**
     * Get active presence list for a note path.
     */
    public function presence(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');

        $validated = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $cleanPath = trim(str_replace('\\', '/', $validated['path']), '/');
        $this->vaultAccess->authorizeRead($deviceToken, $vault, $cleanPath);

        $peers = $this->collabService->getPresence($vault, $cleanPath);

        return response()->json([
            'status' => 'ok',
            'peers' => $peers,
        ]);
    }

    /**
     * Unified sync endpoint supporting legacy character deltas or Yjs update envelopes.
     */
    public function syncLegacyOrAppend(Request $request, Vault $vault): JsonResponse
    {
        if ($request->has('deltas')) {
            /** @var DeviceToken $deviceToken */
            $deviceToken = $request->attributes->get('device_token');

            $validated = $request->validate([
                'path' => ['required', 'string'],
                'peer_id' => ['required', 'string'],
                'deltas' => ['nullable', 'array'],
                'cursor' => ['nullable', 'array'],
                'since_clock' => ['nullable', 'integer'],
            ]);

            $cleanPath = trim(str_replace('\\', '/', $validated['path']), '/');
            if (! empty($validated['deltas'])) {
                $this->vaultAccess->authorizeWrite($deviceToken, $vault, $cleanPath);
            } else {
                $this->vaultAccess->authorizeRead($deviceToken, $vault, $cleanPath);
            }

            $res = $this->collabService->sync(
                vault: $vault,
                user: $deviceToken->user,
                path: $cleanPath,
                peerId: $validated['peer_id'],
                localDeltas: $validated['deltas'] ?? [],
                cursor: $validated['cursor'] ?? null,
                sinceClock: (int) ($validated['since_clock'] ?? 0)
            );

            return response()->json($res);
        }

        return $this->append($request, $vault);
    }

    /**
     * Resolve document model from request, enforcing vault ownership.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function resolveDocument(Vault $vault, array $validated): VaultCollaborationDocument
    {
        if (! empty($validated['document_id'])) {
            return VaultCollaborationDocument::where('vault_id', $vault->id)
                ->findOrFail((int) $validated['document_id']);
        }

        if (! empty($validated['path'])) {
            $cleanPath = trim(str_replace('\\', '/', (string) $validated['path']), '/');

            return VaultCollaborationDocument::firstOrCreate(
                ['vault_id' => $vault->id, 'path' => $cleanPath],
                ['latest_sequence' => 0, 'is_active' => true]
            );
        }

        throw ValidationException::withMessages([
            'document_id' => ['Either document_id or path is required.'],
        ]);
    }

    /**
     * Build and validate content envelope for collaboration update.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function buildEnvelope(Vault $vault, array $validated): VaultContentEnvelope
    {
        $payload = (string) ($validated['payload'] ?? $validated['content'] ?? '');
        $payloadSha256 = (string) ($validated['payload_sha256'] ?? $validated['sha256'] ?? hash('sha256', $payload));
        $isEncrypted = (bool) (($validated['encrypted'] ?? false) || ($validated['is_encrypted'] ?? false));
        $iv = (string) ($validated['iv'] ?? $validated['encryption_iv'] ?? '');
        $tag = (string) ($validated['tag'] ?? $validated['encryption_tag'] ?? '');
        $originalSize = (int) ($validated['original_size'] ?? strlen($payload));
        $formatVersion = (int) ($validated['format_version'] ?? 2);

        if ($vault->is_e2ee && ! $isEncrypted) {
            throw ValidationException::withMessages([
                'encrypted' => ['Encrypted vaults require a client-encrypted payload.'],
            ]);
        }

        if ($isEncrypted) {
            $errors = [];
            if (strlen($iv) !== 24 || ! ctype_xdigit($iv)) {
                $errors['iv'] = ['Encrypted envelope must include a valid 24-character hex IV.'];
            }
            if (strlen($tag) !== 32 || ! ctype_xdigit($tag)) {
                $errors['tag'] = ['Encrypted envelope must include a valid 32-character hex tag.'];
            }

            if (! empty($errors)) {
                throw ValidationException::withMessages($errors);
            }
        }

        return new VaultContentEnvelope(
            payload: $payload,
            payloadSha256: $payloadSha256,
            plaintextSize: $originalSize,
            encrypted: $isEncrypted,
            iv: $isEncrypted ? $iv : null,
            tag: $isEncrypted ? $tag : null,
            ghost: false,
            mimeType: null,
            formatVersion: $formatVersion,
        );
    }
}
