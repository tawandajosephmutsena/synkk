<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiStreamCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $citations
     * @param  array<int, int>  $proposalIds
     */
    public function __construct(
        public int $vaultId,
        public int $userId,
        public int $threadId,
        public string $runId,
        public string $status,
        public ?int $messageId = null,
        public string $content = '',
        public array $citations = [],
        public array $proposalIds = [],
        public ?string $errorMessage = null,
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
        return 'ai.completed';
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
            'status' => $this->status,
            'message_id' => $this->messageId,
            'content' => $this->content,
            'citations' => $this->citations,
            'proposal_ids' => $this->proposalIds,
            'error_message' => $this->errorMessage,
        ];
    }
}
