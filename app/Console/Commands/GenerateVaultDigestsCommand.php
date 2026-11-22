<?php

namespace App\Console\Commands;

use App\Models\VaultAiSetting;
use App\Services\Ai\VaultDigestService;
use App\Services\PlanService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:generate-digests {--vault= : Optional vault ID or slug} {--period= : Filter by schedule period (daily|weekly)} {--force : Ignore schedule window and generate immediately}')]
#[Description('Generate automated AI activity digests for opted-in vaults')]
class GenerateVaultDigestsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(VaultDigestService $digestService, PlanService $planService): int
    {
        $rawVault = $this->option('vault');
        $vaultOption = is_string($rawVault) && trim($rawVault) !== '' ? trim($rawVault) : null;

        $rawPeriod = $this->option('period');
        $periodOption = is_string($rawPeriod) && in_array(trim($rawPeriod), ['daily', 'weekly'], true)
            ? trim($rawPeriod)
            : null;

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
        } elseif ($periodOption !== null) {
            $query->where('digest_schedule', $periodOption);
        } else {
            $query->whereIn('digest_schedule', ['daily', 'weekly']);
        }

        $settings = $query->get();

        $generated = 0;
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
                || ! $planService->hasFeature($team, 'ai_team_digests')
            ) {
                $skipped++;

                continue;
            }

            if (! $force && ! $digestService->isDue($setting)) {
                $skipped++;

                continue;
            }

            try {
                $result = $digestService->generateDigest(
                    vault: $vault,
                    period: $periodOption,
                    force: $force,
                );

                if ($result['status'] === 'generated') {
                    $generated++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                report($e);
                $this->warn("Vault {$vault->slug} failed: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Digests complete. Generated: {$generated}, Skipped: {$skipped}, Failed: {$failed}");

        return ($vaultOption !== null && $failed > 0) ? self::FAILURE : self::SUCCESS;
    }
}
