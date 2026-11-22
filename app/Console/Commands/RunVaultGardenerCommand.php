<?php

namespace App\Console\Commands;

use App\Models\VaultAiSetting;
use App\Services\Ai\VaultGardenerService;
use App\Services\PlanService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:run-gardener {--vault= : Optional vault ID or slug} {--force : Ignore schedule mode and quiet window}')]
#[Description('Run scheduled nightly Vault Gardener sweeps for opted-in vaults')]
class RunVaultGardenerCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(VaultGardenerService $gardenerService, PlanService $planService): int
    {
        $rawVault = $this->option('vault');
        $vaultOption = is_string($rawVault) && trim($rawVault) !== '' ? trim($rawVault) : null;
        $force = (bool) $this->option('force');

        $query = VaultAiSetting::query()
            ->with(['vault.team.aiConfig'])
            ->where('ai_mode', 'server');

        if ($vaultOption !== null) {
            $query->whereHas('vault', function ($q) use ($vaultOption): void {
                if (ctype_digit($vaultOption)) {
                    $q->where('id', (int) $vaultOption);
                } else {
                    $q->where('slug', $vaultOption);
                }
            });
        } else {
            $query->where('gardener_enabled', true)
                ->whereIn('gardener_schedule', ['nightly', 'debounced']);
        }

        $settings = $query->get();

        $processed = 0;
        $proposalsStaged = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($settings as $setting) {
            $vault = $setting->vault;
            $team = $vault->team;
            $aiConfig = $team->aiConfig;

            if (
                $vault->is_e2ee
                || $aiConfig === null
                || ! $aiConfig->is_enabled
                || $aiConfig->provider_mode === 'off'
                || ! $planService->hasFeature($team, 'ai_gardener')
            ) {
                $skipped++;

                continue;
            }

            if (
                ! $force
                && $setting->last_gardened_at !== null
                && $setting->last_gardened_at->gt(now()->subHours(12))
            ) {
                $skipped++;

                continue;
            }

            try {
                $result = $gardenerService->runForVault(
                    vault: $vault,
                    force: $force,
                );

                $processed++;
                $proposalsStaged += $result['proposals_created'];
            } catch (\Throwable $e) {
                report($e);
                $this->warn("Vault {$vault->slug} failed: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Gardener sweep complete. Vaults processed: {$processed}, Proposals staged: {$proposalsStaged}, Skipped: {$skipped}, Failed: {$failed}");

        return ($vaultOption !== null && $failed > 0) ? self::FAILURE : self::SUCCESS;
    }
}
