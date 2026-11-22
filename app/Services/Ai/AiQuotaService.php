<?php

namespace App\Services\Ai;

use App\Exceptions\AiException;
use App\Models\AiPlatformSetting;
use App\Models\AiUsageLog;
use App\Models\Team;
use App\Models\TeamAiConfig;
use App\Services\PlanService;
use Illuminate\Support\Facades\DB;

class AiQuotaService
{
    public function __construct(
        protected PlanService $planService,
    ) {}

    public function featureFlagFor(string $feature): string
    {
        return match ($feature) {
            'mcp' => 'ai_mcp_server',
            'smart_merge' => 'ai_smart_merge',
            'gardener' => 'ai_gardener',
            'digest' => 'ai_team_digests',
            'portal' => 'ai_portal_assistant',
            'ai_byok_core', 'ai_mcp_server', 'ai_smart_merge', 'ai_gardener', 'ai_team_digests', 'ai_portal_assistant', 'ai_managed_cloud' => $feature,
            default => 'ai_byok_core',
        };
    }

    /**
     * Resolve the active chat/agent route for a team after enforcing plan, kill switch, and credit rules.
     *
     * @throws AiException
     */
    public function resolveChatRoute(Team $team, bool $fast = false, string $feature = 'copilot'): ResolvedAiRoute
    {
        if ($team->isSuspended()) {
            throw new AiException('This workspace is currently suspended.', 'AI_DISABLED', 403);
        }

        $requiredFlag = $this->featureFlagFor($feature);
        if (! $this->planService->hasFeature($team, $requiredFlag)) {
            throw new AiException(
                "Your workspace plan does not include the '{$feature}' AI feature.",
                'AI_DISABLED',
                403,
            );
        }

        $config = $team->aiConfig()->first();
        if (! $config || ! $config->is_enabled || $config->provider_mode === 'off') {
            throw new AiException('Synkk AI Add-On is not enabled for this workspace.', 'AI_DISABLED', 403);
        }

        if ($config->provider_mode === 'managed') {
            return $this->resolveManagedChatRoute($team, $config, $fast);
        }

        if ($config->provider_mode === 'ollama') {
            if (! $config->hasOllamaConfigured()) {
                throw new AiException('Local Ollama model is not configured for this workspace.', 'NOT_CONFIGURED', 422);
            }

            return $this->buildOllamaChatRoute($config, $fast);
        }

        if ($config->provider_mode === 'byok') {
            $this->assertByokMonthlyBudget($team, $config);

            if (! $config->hasByokConfigured()) {
                throw new AiException('BYOK provider, model, or API key is not configured.', 'NOT_CONFIGURED', 422);
            }

            return $this->buildByokChatRoute($config, $fast);
        }

        throw new AiException("Unsupported AI provider mode '{$config->provider_mode}'.", 'NOT_CONFIGURED', 422);
    }

    /**
     * Resolve the active embedding route for a team, or null when embeddings are disabled/unconfigured.
     *
     * @throws AiException
     */
    public function resolveEmbeddingRoute(Team $team, bool $throwOnQuotaExceeded = false): ?ResolvedAiRoute
    {
        if ($team->isSuspended() || ! $this->planService->hasFeature($team, 'ai_byok_core')) {
            return null;
        }

        $config = $team->aiConfig()->first();
        if (! $config || ! $config->is_enabled || $config->provider_mode === 'off') {
            return null;
        }

        if ($config->embedding_provider === 'none') {
            return null;
        }

        $effectiveMode = $config->provider_mode;

        if ($effectiveMode === 'managed') {
            if (blank($config->embedding_provider) || $config->embedding_provider === 'managed') {
                $platform = AiPlatformSetting::current();
                if ($platform->managed_ai_kill_switch || ! $this->planService->hasFeature($team, 'ai_managed_cloud')) {
                    if ($throwOnQuotaExceeded) {
                        throw new AiException('Managed Cloud AI is temporarily unavailable.', 'KILL_SWITCH', 503);
                    }

                    return null;
                }

                $this->ensureCloudTrialGranted($team, $config);
                $config->refresh();

                if ($config->totalAvailableManagedCreditsMicrocents() <= 0) {
                    if ($config->fallback_to_byok) {
                        return $this->buildByokEmbeddingRoute($config);
                    }
                    if ($throwOnQuotaExceeded) {
                        throw new AiException('Managed AI credit allowance exhausted.', 'QUOTA_EXCEEDED', 402);
                    }

                    return null;
                }

                $provider = (string) config('synkk.ai.managed.embedding_provider', 'openai_compatible');
                $baseUrl = (string) config('synkk.ai.managed.embedding_base_url', 'https://api.openai.com/v1');
                $apiKey = config('synkk.ai.managed.embedding_api_key') ?: config('synkk.ai.managed.api_key');
                $model = (string) config('synkk.ai.managed.embedding_model', 'text-embedding-3-small');

                if (blank($apiKey) && $provider !== 'ollama') {
                    return null;
                }

                return new ResolvedAiRoute(
                    providerMode: 'managed',
                    provider: $provider,
                    baseUrl: $baseUrl,
                    apiKey: is_string($apiKey) ? $apiKey : null,
                    model: $model,
                    isLocal: $this->isLocalEndpoint($provider, $baseUrl),
                );
            }
        }

        return $this->buildByokEmbeddingRoute($config);
    }

    /**
     * Estimate request cost in microcents (1 cent = 1,000,000 microcents).
     */
    public function estimateCostMicrocents(string $model, int $inputTokens, int $outputTokens, bool $isEmbedding = false): int
    {
        $normalized = strtolower(trim($model));
        /** @var array<string, array{input?: int, output?: int}> $modelMap */
        $modelMap = config('synkk.ai.pricing_microcents_per_1m.models', []);

        $rates = $modelMap[$normalized] ?? null;

        if ($rates === null) {
            foreach ($modelMap as $key => $candidateRates) {
                if (str_contains($normalized, strtolower($key))) {
                    $rates = $candidateRates;
                    break;
                }
            }
        }

        if ($isEmbedding) {
            $inputRate = (int) ($rates['input'] ?? config('synkk.ai.pricing_microcents_per_1m.default_embedding', 2_000_000));
            $cost = (int) round(($inputTokens / 1_000_000) * $inputRate);

            return max($inputTokens > 0 ? 1 : 0, $cost);
        }

        $inputRate = (int) ($rates['input'] ?? config('synkk.ai.pricing_microcents_per_1m.default_input', 15_000_000));
        $outputRate = (int) ($rates['output'] ?? config('synkk.ai.pricing_microcents_per_1m.default_output', 60_000_000));

        $cost = (int) round((($inputTokens / 1_000_000) * $inputRate) + (($outputTokens / 1_000_000) * $outputRate));

        return max(($inputTokens + $outputTokens) > 0 ? 1 : 0, $cost);
    }

    /**
     * Persist a usage log entry and atomically deduct Managed Cloud credits when applicable.
     *
     * @param array{
     *     vault_id?: int|null,
     *     user_id?: int|null,
     *     portal_id?: int|null,
     *     feature: string,
     *     provider_mode: string,
     *     provider: string,
     *     model: string,
     *     input_tokens: int,
     *     output_tokens: int,
     *     cost_microcents?: int,
     *     redacted_secrets_count?: int
     * } $attributes
     */
    public function recordUsage(Team $team, array $attributes): AiUsageLog
    {
        $isEmbedding = $attributes['feature'] === 'embedding';
        $costMicrocents = $attributes['cost_microcents'] ?? $this->estimateCostMicrocents(
            $attributes['model'],
            $attributes['input_tokens'],
            $attributes['output_tokens'],
            $isEmbedding,
        );

        return DB::transaction(function () use ($team, $attributes, $costMicrocents): AiUsageLog {
            $log = AiUsageLog::query()->create([
                'team_id' => $team->id,
                'vault_id' => $attributes['vault_id'] ?? null,
                'user_id' => $attributes['user_id'] ?? null,
                'portal_id' => $attributes['portal_id'] ?? null,
                'feature' => $attributes['feature'],
                'provider_mode' => $attributes['provider_mode'],
                'provider' => $attributes['provider'],
                'model' => $attributes['model'],
                'input_tokens' => max(0, $attributes['input_tokens']),
                'output_tokens' => max(0, $attributes['output_tokens']),
                'cost_microcents' => max(0, $costMicrocents),
                'redacted_secrets_count' => max(0, (int) ($attributes['redacted_secrets_count'] ?? 0)),
                'created_at' => now(),
            ]);

            if ($attributes['provider_mode'] === 'managed' && $costMicrocents > 0) {
                $config = TeamAiConfig::query()
                    ->where('team_id', $team->id)
                    ->lockForUpdate()
                    ->first();

                if ($config !== null) {
                    $remainingCost = $costMicrocents;

                    if (! $config->hasActiveManagedSubscription() && $config->trial_credits_microcents > 0) {
                        $fromTrial = min($remainingCost, (int) $config->trial_credits_microcents);
                        $config->trial_credits_microcents -= $fromTrial;
                        $remainingCost -= $fromTrial;
                    } elseif ($config->trial_credits_microcents > 0 && $config->remainingManagedCreditsMicrocents() === 0) {
                        $fromTrial = min($remainingCost, (int) $config->trial_credits_microcents);
                        $config->trial_credits_microcents -= $fromTrial;
                        $remainingCost -= $fromTrial;
                    }

                    if ($remainingCost > 0) {
                        $config->managed_credits_used_microcents += $remainingCost;
                    }

                    $config->save();
                }
            }

            return $log;
        });
    }

    /**
     * Ensure eligible Cloud workspaces receive their one-time free AI trial credits once.
     */
    public function ensureCloudTrialGranted(Team $team, TeamAiConfig $config): void
    {
        if ($config->trial_granted_at !== null || $config->hasActiveManagedSubscription()) {
            return;
        }

        if (! $this->planService->hasFeature($team, 'ai_managed_cloud')) {
            return;
        }

        DB::transaction(function () use ($config): void {
            $locked = TeamAiConfig::query()->whereKey($config->id)->lockForUpdate()->first();
            if (! $locked || $locked->trial_granted_at !== null || $locked->hasActiveManagedSubscription()) {
                return;
            }

            $trialMicrocents = (int) AiPlatformSetting::current()->default_trial_credits_microcents;
            if ($trialMicrocents <= 0) {
                return;
            }

            $locked->forceFill([
                'trial_credits_microcents' => $trialMicrocents,
                'trial_granted_at' => now(),
            ])->save();
        });
    }

    /**
     * Get monthly AI telemetry and credit status for a workspace.
     *
     * @return array<string, mixed>
     */
    public function getUsageSummary(Team $team): array
    {
        $config = $team->aiConfig()->first();
        $monthStart = now()->startOfMonth();

        $monthlyAggregate = AiUsageLog::query()
            ->where('team_id', $team->id)
            ->where('created_at', '>=', $monthStart)
            ->selectRaw('COUNT(*) as total_requests, COALESCE(SUM(input_tokens), 0) as input_tokens, COALESCE(SUM(output_tokens), 0) as output_tokens, COALESCE(SUM(cost_microcents), 0) as cost_microcents, COALESCE(SUM(redacted_secrets_count), 0) as redacted_secrets')
            ->first();

        $totalRequests = (int) ($monthlyAggregate->total_requests ?? 0);
        $inputTokens = (int) ($monthlyAggregate->input_tokens ?? 0);
        $outputTokens = (int) ($monthlyAggregate->output_tokens ?? 0);
        $costMicrocents = (int) ($monthlyAggregate->cost_microcents ?? 0);
        $redactedSecrets = (int) ($monthlyAggregate->redacted_secrets ?? 0);

        $managedTotal = $config !== null ? $config->managed_credits_microcents : 0;
        $managedUsed = $config !== null ? $config->managed_credits_used_microcents : 0;
        $managedRemaining = $config !== null ? $config->remainingManagedCreditsMicrocents() : 0;
        $trialRemaining = $config !== null ? $config->trial_credits_microcents : 0;
        $totalAvailable = $config !== null ? $config->totalAvailableManagedCreditsMicrocents() : 0;

        $managedPct = $managedTotal > 0
            ? min(100, (int) round(($managedUsed / $managedTotal) * 100))
            : 0;

        return [
            'is_enabled' => $config !== null ? $config->is_enabled : false,
            'provider_mode' => $config !== null ? $config->provider_mode : 'off',
            'requests_this_month' => $totalRequests,
            'input_tokens_this_month' => $inputTokens,
            'output_tokens_this_month' => $outputTokens,
            'total_tokens_this_month' => $inputTokens + $outputTokens,
            'cost_usd_this_month' => round($costMicrocents / 100_000_000, 4),
            'redacted_secrets_this_month' => $redactedSecrets,
            'managed_credits_total_usd' => round($managedTotal / 100_000_000, 2),
            'managed_credits_used_usd' => round($managedUsed / 100_000_000, 2),
            'managed_credits_remaining_usd' => round($managedRemaining / 100_000_000, 2),
            'managed_credits_used_pct' => $managedPct,
            'trial_credits_remaining_usd' => round($trialRemaining / 100_000_000, 2),
            'total_available_managed_usd' => round($totalAvailable / 100_000_000, 2),
            'has_active_managed_subscription' => (bool) ($config?->hasActiveManagedSubscription() ?? false),
            'ai_billing_status' => $config?->ai_billing_status,
            'managed_cycle_resets_at' => $config?->managed_cycle_resets_at?->toIso8601String(),
        ];
    }

    /**
     * @throws AiException
     */
    protected function resolveManagedChatRoute(Team $team, TeamAiConfig $config, bool $fast): ResolvedAiRoute
    {
        $platform = AiPlatformSetting::current();
        if ($platform->managed_ai_kill_switch) {
            throw new AiException(
                'Synkk Managed Cloud AI has been temporarily paused by the platform administrator.',
                'KILL_SWITCH',
                503,
            );
        }

        if (! $this->planService->hasFeature($team, 'ai_managed_cloud')) {
            throw new AiException(
                'Synkk Managed Cloud AI requires an active Synkk Cloud plan.',
                'AI_DISABLED',
                403,
            );
        }

        if ($platform->global_daily_spend_cap_cents !== null) {
            $todaySpendMicrocents = (int) AiUsageLog::query()
                ->where('provider_mode', 'managed')
                ->where('created_at', '>=', now()->startOfDay())
                ->sum('cost_microcents');

            if ($todaySpendMicrocents >= ($platform->global_daily_spend_cap_cents * 1_000_000)) {
                throw new AiException(
                    'Daily platform Managed AI spend limit reached. Please retry tomorrow or switch to BYOK.',
                    'KILL_SWITCH',
                    503,
                );
            }
        }

        $this->ensureCloudTrialGranted($team, $config);
        $config->refresh();

        if ($config->totalAvailableManagedCreditsMicrocents() <= 0) {
            if ($config->fallback_to_byok) {
                if ($config->hasByokConfigured()) {
                    $this->assertByokMonthlyBudget($team, $config);

                    return $this->buildByokChatRoute($config, $fast);
                }
                if ($config->chat_provider === 'ollama' && $config->hasOllamaConfigured()) {
                    return $this->buildOllamaChatRoute($config, $fast);
                }
            }

            throw new AiException(
                'Your workspace Managed AI credit allowance is exhausted. Add the AI Add-On subscription or switch to BYOK/Ollama.',
                'QUOTA_EXCEEDED',
                402,
            );
        }

        $provider = (string) config('synkk.ai.managed.provider', 'openai_compatible');
        $baseUrl = (string) config('synkk.ai.managed.base_url', 'https://openrouter.ai/api/v1');
        $apiKey = config('synkk.ai.managed.api_key');
        $model = $fast
            ? (string) config('synkk.ai.managed.fast_model', 'openai/gpt-4o-mini')
            : (string) config('synkk.ai.managed.chat_model', 'openai/gpt-4o-mini');

        if (blank($apiKey) && $provider !== 'ollama') {
            throw new AiException(
                'Synkk Managed Cloud AI credentials are not configured on this server.',
                'NOT_CONFIGURED',
                503,
            );
        }

        return new ResolvedAiRoute(
            providerMode: 'managed',
            provider: $provider,
            baseUrl: $baseUrl,
            apiKey: is_string($apiKey) ? $apiKey : null,
            model: $model,
            isLocal: $this->isLocalEndpoint($provider, $baseUrl),
        );
    }

    /**
     * @throws AiException
     */
    protected function assertByokMonthlyBudget(Team $team, TeamAiConfig $config): void
    {
        if ($config->monthly_budget_cents === null) {
            return;
        }

        $usedMicrocents = (int) AiUsageLog::query()
            ->where('team_id', $team->id)
            ->where('provider_mode', 'byok')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('cost_microcents');

        if ($usedMicrocents >= ($config->monthly_budget_cents * 1_000_000)) {
            throw new AiException(
                'Your workspace monthly BYOK budget cap has been reached.',
                'QUOTA_EXCEEDED',
                402,
            );
        }
    }

    protected function buildByokChatRoute(TeamAiConfig $config, bool $fast): ResolvedAiRoute
    {
        $provider = (string) ($config->chat_provider ?: 'openai');
        $baseUrl = (string) ($config->chat_base_url ?: $this->defaultBaseUrlForProvider($provider));
        $model = ($fast && filled($config->fast_model))
            ? (string) $config->fast_model
            : (string) $config->chat_model;

        return new ResolvedAiRoute(
            providerMode: 'byok',
            provider: $provider,
            baseUrl: $baseUrl,
            apiKey: $config->chat_api_key,
            model: $model,
            isLocal: $this->isLocalEndpoint($provider, $baseUrl),
        );
    }

    protected function buildOllamaChatRoute(TeamAiConfig $config, bool $fast): ResolvedAiRoute
    {
        $baseUrl = (string) ($config->chat_base_url ?: 'http://localhost:11434');
        $model = ($fast && filled($config->fast_model))
            ? (string) $config->fast_model
            : (string) $config->chat_model;

        return new ResolvedAiRoute(
            providerMode: 'ollama',
            provider: 'ollama',
            baseUrl: $baseUrl,
            apiKey: null,
            model: $model,
            isLocal: true,
        );
    }

    protected function buildByokEmbeddingRoute(TeamAiConfig $config): ?ResolvedAiRoute
    {
        $provider = $config->embedding_provider;

        if (blank($provider)) {
            if ($config->provider_mode === 'ollama' && filled($config->embedding_model)) {
                $provider = 'ollama';
            } elseif (in_array($config->chat_provider, ['openai', 'gemini', 'openrouter', 'ollama', 'openai_compatible'], true) && filled($config->embedding_model)) {
                $provider = $config->chat_provider;
            } else {
                return null;
            }
        }

        if ($provider === 'none' || $provider === 'anthropic' || $provider === 'managed') {
            return null;
        }

        $model = (string) ($config->embedding_model ?: ($provider === 'ollama' ? 'nomic-embed-text' : 'text-embedding-3-small'));
        $baseUrl = (string) ($config->embedding_base_url ?: ($provider === $config->chat_provider ? ($config->chat_base_url ?: '') : '') ?: $this->defaultBaseUrlForProvider($provider));
        $apiKey = $config->embedding_api_key ?: ($provider === $config->chat_provider ? $config->chat_api_key : null);

        if ($provider !== 'ollama' && blank($apiKey) && $provider !== 'openai_compatible') {
            return null;
        }

        $mode = $provider === 'ollama' ? 'ollama' : 'byok';

        return new ResolvedAiRoute(
            providerMode: $mode,
            provider: $provider,
            baseUrl: $baseUrl,
            apiKey: $apiKey,
            model: $model,
            isLocal: $this->isLocalEndpoint($provider, $baseUrl),
        );
    }

    public function defaultBaseUrlForProvider(string $provider): string
    {
        return match ($provider) {
            'openrouter' => 'https://openrouter.ai/api/v1',
            'anthropic' => 'https://api.anthropic.com/v1',
            'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
            'ollama' => 'http://localhost:11434',
            default => 'https://api.openai.com/v1',
        };
    }

    protected function isLocalEndpoint(string $provider, string $baseUrl): bool
    {
        if ($provider === 'ollama') {
            return true;
        }

        $host = parse_url($baseUrl, PHP_URL_HOST);

        return is_string($host) && in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true);
    }
}
