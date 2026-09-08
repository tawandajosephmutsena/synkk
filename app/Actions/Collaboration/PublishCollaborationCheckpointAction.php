<?php

namespace App\Actions\Collaboration;

use App\Models\DeviceToken;
use App\Models\User;
use App\Models\VaultCollaborationDocument;
use App\Models\VaultCollaborationUpdate;
use App\ValueObjects\VaultContentEnvelope;

class PublishCollaborationCheckpointAction
{
    public function __construct(
        protected AppendCollaborationUpdateAction $appendAction
    ) {}

    /**
     * Publish an authoritative CRDT checkpoint snapshot for a document.
     */
    public function execute(
        VaultCollaborationDocument $document,
        string $clientUpdateId,
        int $acknowledgedBaseSequence,
        VaultContentEnvelope $envelope,
        ?DeviceToken $deviceToken = null,
        ?User $user = null
    ): VaultCollaborationUpdate {
        $update = $this->appendAction->execute(
            document: $document,
            clientUpdateId: $clientUpdateId,
            envelope: $envelope,
            deviceToken: $deviceToken,
            user: $user,
            isCheckpoint: true,
            acknowledgedBaseSequence: $acknowledgedBaseSequence
        );

        if ($acknowledgedBaseSequence > 0) {
            $document->updates()
                ->where('sequence', '<=', $acknowledgedBaseSequence)
                ->where('is_checkpoint', false)
                ->delete();
        }

        return $update;
    }
}
