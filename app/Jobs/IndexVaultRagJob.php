<?php

namespace App\Jobs;

use App\Models\Vault;
use App\Services\VaultRagService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class IndexVaultRagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    public function __construct(
        public Vault $vault,
        public bool $force = false
    ) {}

    public function handle(VaultRagService $ragService): void
    {
        Log::info("Starting background RAG indexing for Vault [{$this->vault->id}: {$this->vault->name}]");

        try {
            $telemetry = $ragService->indexVault($this->vault, $this->force);
            Log::info("Completed background RAG indexing for Vault [{$this->vault->id}] in {$telemetry['duration_ms']}ms");
        } catch (Throwable $e) {
            Log::error("Failed background RAG indexing for Vault [{$this->vault->id}]: {$e->getMessage()}");
            throw $e;
        }
    }
}
