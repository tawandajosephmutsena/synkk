<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiStreamChunk implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $vaultId,
        public int $userId,
        public int $threadId,
        public string $runId,
        public string $delta = '',
        public ?string $statusNote = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("vault-ai.{$this->vaultId}.{$this->userId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ai.chunk';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'vault_id' => $this->vaultId,
            'thread_id' => $this->threadId,
            'run_id' => $this->runId,
            'delta' => $this->delta,
            'status_note' => $this->statusNote,
        ];
    }
}
