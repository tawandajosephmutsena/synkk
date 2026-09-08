<?php

namespace App\Actions\Collaboration;

use App\Events\VaultCollaborationUpdateCommitted;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\VaultCollaborationDocument;
use App\Models\VaultCollaborationUpdate;
use App\ValueObjects\VaultContentEnvelope;
use Illuminate\Support\Facades\DB;

class AppendCollaborationUpdateAction
{
    /**
     * Idempotently append a collaboration update to a document under a row lock.
     */
    public function execute(
        VaultCollaborationDocument $document,
        string $clientUpdateId,
        VaultContentEnvelope $envelope,
        ?DeviceToken $deviceToken = null,
        ?User $user = null,
        bool $isCheckpoint = false,
        ?int $acknowledgedBaseSequence = null
    ): VaultCollaborationUpdate {
        $isNew = false;

        $update = DB::transaction(function () use (
            $document,
            $clientUpdateId,
            $envelope,
            $deviceToken,
            $user,
            $isCheckpoint,
            $acknowledgedBaseSequence,
            &$isNew
        ) {
            $locked = VaultCollaborationDocument::query()->lockForUpdate()->findOrFail($document->id);
            $existing = $locked->updates()->where('client_update_id', $clientUpdateId)->first();

            if ($existing) {
                return $existing;
            }

            $isNew = true;
            $sequence = ++$locked->latest_sequence;
            $locked->save();

            $userId = $user !== null ? $user->id : $deviceToken?->user_id;

            return $locked->updates()->create([
                'sequence' => $sequence,
                'client_update_id' => $clientUpdateId,
                'device_token_id' => $deviceToken?->id,
                'user_id' => $userId,
                'payload' => $envelope->payload,
                'payload_sha256' => $envelope->payloadSha256,
                'is_encrypted' => $envelope->encrypted,
                'encryption_iv' => $envelope->iv,
                'encryption_tag' => $envelope->tag,
                'format_version' => $envelope->formatVersion,
                'is_checkpoint' => $isCheckpoint,
                'acknowledged_base_sequence' => $acknowledgedBaseSequence,
            ]);
        });

        if ($isNew) {
            event(new VaultCollaborationUpdateCommitted($update));
        }

        return $update;
    }
}
