<?php

namespace App\Services\Admin;

use App\Models\AiPlatformSetting;
use App\Models\AiUsageLog;
use App\Models\Team;
use App\Models\TeamAiConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdminAiOpsService
{
    /**
     * Collect live platform AI spend telemetry, per-feature breakdown, and tenant credit balances.
     *
     * @return array{
     *     platform: array{
     *         managed_ai_kill_switch: bool,
     *         default_trial_credits_usd: float,
     *         global_daily_spend_cap_usd: float|null
     *     },
     *     summary: array{
     *         today_managed_spend_usd: float,
     *         month_managed_spend_usd: float,
     *         month_total_spend_usd: float,
     *         month_requests: int,
     *         month_tokens: int,
     *         month_redacted_secrets: int,
     *         enabled_workspaces: int,
     *         active_managed_subscriptions: int
     *     },
     *     features: array<int, array{
     *         feature: string,
     *         requests: int,
     *         tokens: int,
     *         cost_usd: float
     *     }>,
     *     tenants: array<int, array{
     *         team_id: int,
     *         team_name: string,
     *         team_slug: string,
     *         plan: string,
     *         is_enabled: bool,
     *         provider_mode: string,
     *         ai_billing_status: string|null,
     *         trial_remaining_usd: float,
     *         managed_remaining_usd: float,
     *         requests_30d: int,
     *         cost_30d_usd: float
     *     }>
     * }
     */
    public function getPlatformTelemetry(): array
    {
        $platform = AiPlatformSetting::current();
        $todayStart = now()->startOfDay();
        $windowStart = now()->subDays(30);

        $todayManagedMicrocents = (int) AiUsageLog::query()
            ->where('provider_mode', 'managed')
            ->where('created_at', '>=', $todayStart)
            ->sum('cost_microcents');

        $monthAggregate = AiUsageLog::query()
            ->where('created_at', '>=', $windowStart)
            ->selectRaw('COUNT(*) as total_requests, COALESCE(SUM(input_tokens + output_tokens), 0) as total_tokens, COALESCE(SUM(cost_microcents), 0) as total_cost_microcents, COALESCE(SUM(CASE WHEN provider_mode = \'managed\' THEN cost_microcents ELSE 0 END), 0) as managed_cost_microcents, COALESCE(SUM(redacted_secrets_count), 0) as redacted_secrets')
            ->first();

        $featureRows = AiUsageLog::query()
            ->where('created_at', '>=', $windowStart)
            ->selectRaw('feature, COUNT(*) as requests, COALESCE(SUM(input_tokens + output_tokens), 0) as tokens, COALESCE(SUM(cost_microcents), 0) as cost_microcents')
            ->groupBy('feature')
            ->orderByDesc('requests')
            ->get()
            ->map(fn ($row): array => [
                'feature' => (string) $row->feature,
                'requests' => (int) ($row->requests ?? 0),
                'tokens' => (int) ($row->tokens ?? 0),
                'cost_usd' => round(((int) ($row->cost_microcents ?? 0)) / 100_000_000, 4),
            ])
            ->values()
            ->all();

        $enabledWorkspaces = TeamAiConfig::query()->where('is_enabled', true)->count();
        $activeManagedSubscriptions = TeamAiConfig::query()->where('ai_billing_status', 'active')->count();

        $teamStats30d = AiUsageLog::query()
            ->where('created_at', '>=', $windowStart)
            ->selectRaw('team_id, COUNT(*) as requests, COALESCE(SUM(cost_microcents), 0) as cost_microcents')
            ->groupBy('team_id')
            ->get()
            ->keyBy('team_id');

        $tenants = Team::query()
            ->with('aiConfig')
            ->orderBy('name')
            ->get()
            ->map(function (Team $team) use ($teamStats30d): array {
                $config = $team->aiConfig;
                $stat = $teamStats30d->get($team->id);

                return [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'team_slug' => $team->slug,
                    'plan' => $team->plan,
                    'is_enabled' => $config !== null ? $config->is_enabled : false,
                    'provider_mode' => $config !== null ? $config->provider_mode : 'off',
                    'ai_billing_status' => $config?->ai_billing_status,
                    'trial_remaining_usd' => $config !== null ? round($config->trial_credits_microcents / 100_000_000, 2) : 0.0,
                    'managed_remaining_usd' => $config !== null ? round($config->remainingManagedCreditsMicrocents() / 100_000_000, 2) : 0.0,
                    'requests_30d' => (int) ($stat->requests ?? 0),
                    'cost_30d_usd' => round(((int) ($stat->cost_microcents ?? 0)) / 100_000_000, 4),
                ];
            })
            ->values()
            ->all();

        return [
            'platform' => [
                'managed_ai_kill_switch' => $platform->managed_ai_kill_switch,
                'default_trial_credits_usd' => round($platform->default_trial_credits_microcents / 100_000_000, 2),
                'global_daily_spend_cap_usd' => $platform->global_daily_spend_cap_cents !== null
                    ? round($platform->global_daily_spend_cap_cents / 100, 2)
                    : null,
            ],
            'summary' => [
                'today_managed_spend_usd' => round($todayManagedMicrocents / 100_000_000, 4),
                'month_managed_spend_usd' => round(((int) ($monthAggregate->managed_cost_microcents ?? 0)) / 100_000_000, 4),
                'month_total_spend_usd' => round(((int) ($monthAggregate->total_cost_microcents ?? 0)) / 100_000_000, 4),
                'month_requests' => (int) ($monthAggregate->total_requests ?? 0),
                'month_tokens' => (int) ($monthAggregate->total_tokens ?? 0),
                'month_redacted_secrets' => (int) ($monthAggregate->redacted_secrets ?? 0),
                'enabled_workspaces' => $enabledWorkspaces,
                'active_managed_subscriptions' => $activeManagedSubscriptions,
            ],
            'features' => $featureRows,
            'tenants' => $tenants,
        ];
    }

    public function updatePlatformControls(
        bool $killSwitch,
        float $defaultTrialUsd,
        ?float $globalDailyCapUsd,
    ): AiPlatformSetting {
        $trialMicrocents = max(0, (int) round($defaultTrialUsd * 100_000_000));
        $dailyCapCents = $globalDailyCapUsd !== null
            ? max(0, (int) round($globalDailyCapUsd * 100))
            : null;

        $setting = AiPlatformSetting::current();
        $setting->forceFill([
            'managed_ai_kill_switch' => $killSwitch,
            'default_trial_credits_microcents' => $trialMicrocents,
            'global_daily_spend_cap_cents' => $dailyCapCents,
        ])->save();

        return $setting->fresh() ?? $setting;
    }

    public function grantTenantCredits(Team $team, float $amountUsd, string $bucket = 'trial'): TeamAiConfig
    {
        if ($amountUsd <= 0) {
            throw new InvalidArgumentException('Credit grant amount must be positive.');
        }

        if (! in_array($bucket, ['trial', 'managed'], true)) {
            throw new InvalidArgumentException("Unsupported credit bucket '{$bucket}'.");
        }

        $microcents = (int) round($amountUsd * 100_000_000);

        return DB::transaction(function () use ($team, $bucket, $microcents): TeamAiConfig {
            TeamAiConfig::query()->firstOrCreate(
                ['team_id' => $team->id],
                [
                    'is_enabled' => true,
                    'provider_mode' => 'managed',
                ],
            );

            $locked = TeamAiConfig::query()
                ->where('team_id', $team->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($bucket === 'managed') {
                $locked->managed_credits_microcents += $microcents;
            } else {
                $locked->trial_credits_microcents += $microcents;
                if ($locked->trial_granted_at === null) {
                    $locked->trial_granted_at = Carbon::now();
                }
            }

            $locked->save();

            return $locked->fresh() ?? $locked;
        });
    }

    public function setTenantAiEnabled(Team $team, bool $enabled): TeamAiConfig
    {
        $config = TeamAiConfig::query()->firstOrCreate(
            ['team_id' => $team->id],
            [
                'is_enabled' => $enabled,
                'provider_mode' => 'off',
            ],
        );

        $config->forceFill([
            'is_enabled' => $enabled,
        ])->save();

        return $config->fresh() ?? $config;
    }
}
