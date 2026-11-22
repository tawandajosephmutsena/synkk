<?php

namespace App\Jobs;

use App\Models\Vault;
use App\Models\VaultFile;
use App\Services\Ai\VaultIndexService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IndexVaultFileJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 30;

    public function __construct(
        public int $vaultId,
        public int $vaultFileId,
        public bool $deleted = false,
    ) {
        $this->onQueue('ai-background');
    }

    public function uniqueId(): string
    {
        return "{$this->vaultId}:{$this->vaultFileId}:".($this->deleted ? 'delete' : 'upsert');
    }

    public function handle(VaultIndexService $indexService): void
    {
        $vault = Vault::query()->with(['team.aiConfig', 'aiSetting'])->find($this->vaultId);
        if (! $vault || $vault->is_e2ee) {
            return;
        }

        if ($this->deleted) {
            $indexService->removeFile($vault, $this->vaultFileId);

            return;
        }

        $file = VaultFile::query()
            ->where('vault_id', $vault->id)
            ->find($this->vaultFileId);

        if (! $file || $file->is_deleted) {
            $indexService->removeFile($vault, $this->vaultFileId);

            return;
        }

        $indexService->indexFile($vault, $file, force: false);
    }
}
