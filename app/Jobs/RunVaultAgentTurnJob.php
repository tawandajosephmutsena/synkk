<?php

namespace App\Jobs;

use App\Events\AiStreamChunk;
use App\Events\AiStreamCompleted;
use App\Models\AiRun;
use App\Models\AiThread;
use App\Models\User;
use App\Services\Ai\VaultAgentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunVaultAgentTurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public string $runId,
        public int $threadId,
        public int $userId,
        public string $prompt,
    ) {
        $this->onQueue('ai-stream');
    }

    public function handle(VaultAgentService $agentService): void
    {
        $run = AiRun::query()->find($this->runId);
        $thread = AiThread::query()->with('vault.team.aiConfig', 'vault.aiSetting')->find($this->threadId);
        $user = User::query()->find($this->userId);

        if (! $run || ! $thread || ! $user) {
            return;
        }

        $run->forceFill([
            'status' => 'streaming',
            'partial_content' => '',
            'status_note' => 'Searching vault...',
        ])->save();

        $vaultId = $thread->vault_id;
        $userId = $user->id;
        $pendingDelta = '';
        $accumulated = '';

        try {
            $assistantMessage = $agentService->runTurn(
                $thread,
                $user,
                $this->prompt,
                function (string $type, string $payload) use ($run, $vaultId, $userId, &$pendingDelta, &$accumulated): void {
                    if ($type === 'delta') {
                        $pendingDelta .= $payload;
                        $accumulated .= $payload;

                        if (mb_strlen($pendingDelta) >= 60) {
                            $run->forceFill(['partial_content' => $accumulated])->save();
                            broadcast(new AiStreamChunk(
                                vaultId: $vaultId,
                                userId: $userId,
                                threadId: $run->thread_id,
                                runId: $run->id,
                                delta: $pendingDelta,
                            ));
                            $pendingDelta = '';
                        }

                        return;
                    }

                    if ($type === 'tool_start') {
                        $note = "Running {$payload}...";
                        $run->forceFill(['status_note' => $note])->save();
                        broadcast(new AiStreamChunk(
                            vaultId: $vaultId,
                            userId: $userId,
                            threadId: $run->thread_id,
                            runId: $run->id,
                            delta: '',
                            statusNote: $note,
                        ));
                    }
                }
            );

            if ($pendingDelta !== '') {
                broadcast(new AiStreamChunk(
                    vaultId: $vaultId,
                    userId: $userId,
                    threadId: $run->thread_id,
                    runId: $run->id,
                    delta: $pendingDelta,
                ));
            }

            $run->forceFill([
                'status' => 'completed',
                'partial_content' => $assistantMessage->content,
                'status_note' => null,
                'assistant_message_id' => $assistantMessage->id,
            ])->save();

            broadcast(new AiStreamCompleted(
                vaultId: $vaultId,
                userId: $userId,
                threadId: $thread->id,
                runId: $run->id,
                status: 'completed',
                messageId: $assistantMessage->id,
                content: $assistantMessage->content,
                citations: $assistantMessage->citations ?? [],
                proposalIds: $assistantMessage->proposal_ids ?? [],
            ));
        } catch (Throwable $e) {
            $run->forceFill([
                'status' => 'failed',
                'status_note' => null,
                'error_message' => $e->getMessage(),
            ])->save();

            broadcast(new AiStreamCompleted(
                vaultId: $vaultId,
                userId: $userId,
                threadId: $thread->id,
                runId: $run->id,
                status: 'failed',
                errorMessage: $e->getMessage(),
            ));
        }
    }
}
