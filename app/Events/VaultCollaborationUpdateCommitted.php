<?php

namespace App\Events;

use App\Models\VaultCollaborationUpdate;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VaultCollaborationUpdateCommitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $documentId;

    public int $sequence;

    public string $clientUpdateId;

    public string $payload;

    public string $payloadSha256;

    public bool $isEncrypted;

    public ?string $encryptionIv;

    public ?string $encryptionTag;

    public int $formatVersion;

    public bool $isCheckpoint;

    public ?int $acknowledgedBaseSequence;

    public function __construct(VaultCollaborationUpdate $update)
    {
        $this->documentId = (int) $update->vault_collaboration_document_id;
        $this->sequence = (int) $update->sequence;
        $this->clientUpdateId = $update->client_update_id;
        $this->payload = $update->payload;
        $this->payloadSha256 = $update->payload_sha256;
        $this->isEncrypted = (bool) $update->is_encrypted;
        $this->encryptionIv = $update->encryption_iv;
        $this->encryptionTag = $update->encryption_tag;
        $this->formatVersion = (int) $update->format_version;
        $this->isCheckpoint = (bool) $update->is_checkpoint;
        $this->acknowledgedBaseSequence = $update->acknowledged_base_sequence !== null
            ? (int) $update->acknowledged_base_sequence
            : null;
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("vault-collaboration.{$this->documentId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'update.committed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'document_id' => $this->documentId,
            'sequence' => $this->sequence,
            'client_update_id' => $this->clientUpdateId,
            'payload' => $this->payload,
            'payload_sha256' => $this->payloadSha256,
            'is_encrypted' => $this->isEncrypted,
            'encryption_iv' => $this->encryptionIv,
            'encryption_tag' => $this->encryptionTag,
            'format_version' => $this->formatVersion,
            'is_checkpoint' => $this->isCheckpoint,
            'acknowledged_base_sequence' => $this->acknowledgedBaseSequence,
        ];
    }
}
