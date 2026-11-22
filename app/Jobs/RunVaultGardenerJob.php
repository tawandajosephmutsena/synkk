<?php

namespace App\Jobs;

use App\Exceptions\AiException;
use App\Models\Vault;
use App\Models\VaultFile;
use App\Services\Ai\VaultGardenerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunVaultGardenerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        public int $vaultId,
        public ?int $vaultFileId = null,
        public ?string $debounceToken = null,
    ) {
        $this->onQueue('ai-background');
    }

    public function handle(VaultGardenerService $gardenerService): void
    {
        if ($this->vaultFileId !== null && $this->debounceToken !== null) {
            $activeToken = Cache::get($gardenerService->debounceCacheKey($this->vaultId, $this->vaultFileId));
            if ($activeToken !== $this->debounceToken) {
                return;
            }
        }

        $vault = Vault::query()
            ->with(['team.aiConfig', 'aiSetting'])
            ->find($this->vaultId);

        if (! $vault || $vault->is_e2ee) {
            return;
        }

        try {
            if ($this->vaultFileId !== null) {
                $file = VaultFile::query()
                    ->where('vault_id', $vault->id)
                    ->find($this->vaultFileId);

                if ($file !== null && ! $file->is_deleted) {
                    $gardenerService->runForFile($vault, $file);
                }

                return;
            }

            $gardenerService->runForVault($vault);
        } catch (AiException $e) {
            Log::warning('Vault Gardener background job skipped/aborted.', [
                'vault_id' => $vault->id,
                'vault_file_id' => $this->vaultFileId,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
