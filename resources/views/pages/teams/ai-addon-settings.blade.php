<?php

use App\Jobs\IndexVaultRagJob;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\TeamAiConfig;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultAiSetting;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiQuotaService;
use App\Services\DodoPaymentsService;
use App\Services\PlanService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Team $team;

    public bool $isEnabled = false;

    public string $providerMode = 'off';

    public bool $fallbackToByok = false;

    public string $chatProvider = 'openai';

    public string $chatBaseUrl = '';

    public string $chatModel = 'gpt-4o-mini';

    public string $fastModel = '';

    public string $chatApiKeyInput = '';

    public bool $hasChatApiKey = false;

    public string $embeddingProvider = 'none';

    public string $embeddingBaseUrl = '';

    public string $embeddingModel = '';

    public string $embeddingApiKeyInput = '';

    public bool $hasEmbeddingApiKey = false;

    public bool $dlpRedactionEnabled = true;

    public string $monthlyBudgetUsd = '';

    /** @var array<int, array{ai_mode: string, excluded_paths_text: string, allow_direct_mcp_writes: bool}> */
    public array $vaultSettings = [];

    /** @var array{ok: bool, message: string, latency_ms: int, model: string}|null */
    public ?array $chatTestResult = null;

    /** @var array{ok: bool, message: string, latency_ms: int, model: string}|null */
    public ?array $embeddingTestResult = null;

    public string $mcpTokenName = '';

    public string $mcpTokenScope = 'mcp_propose';

    public string $mcpAllowedVaultId = '';

    public ?string $newlyCreatedMcpToken = null;

    public function mount(Team $team): void
    {
        $this->team = $team;
        $this->loadFromDatabase();
    }

    protected function loadFromDatabase(): void
    {
        $config = $this->team->aiConfig()->first();

        $this->isEnabled = (bool) ($config?->is_enabled ?? false);
        $this->providerMode = $config?->provider_mode ?? 'off';
        $this->fallbackToByok = (bool) ($config?->fallback_to_byok ?? false);
        $this->chatProvider = $config?->chat_provider ?? 'openai';
        $this->chatBaseUrl = (string) ($config?->chat_base_url ?? '');
        $this->chatModel = (string) ($config?->chat_model ?? 'gpt-4o-mini');
        $this->fastModel = (string) ($config?->fast_model ?? '');
        $this->chatApiKeyInput = '';
        $this->hasChatApiKey = filled($config?->chat_api_key);
        $this->embeddingProvider = $config?->embedding_provider ?? 'none';
        $this->embeddingBaseUrl = (string) ($config?->embedding_base_url ?? '');
        $this->embeddingModel = (string) ($config?->embedding_model ?? '');
        $this->embeddingApiKeyInput = '';
        $this->hasEmbeddingApiKey = filled($config?->embedding_api_key);
        $this->dlpRedactionEnabled = $config === null ? true : $config->dlp_redaction_enabled;
        $this->monthlyBudgetUsd = $config?->monthly_budget_cents !== null
            ? number_format($config->monthly_budget_cents / 100, 2, '.', '')
            : '';

        $vaultSettings = [];
        foreach ($this->team->vaults()->with('aiSetting')->orderBy('name')->get() as $vault) {
            $setting = $vault->aiSetting;
            $mode = $setting?->ai_mode ?? 'off';
            if ($vault->is_e2ee && $mode === 'server') {
                $mode = 'off';
            }

            $vaultSettings[$vault->id] = [
                'ai_mode' => $mode,
                'excluded_paths_text' => implode(', ', $setting?->excluded_paths ?? []),
                'allow_direct_mcp_writes' => (bool) ($setting?->allow_direct_mcp_writes ?? false),
            ];
        }
        $this->vaultSettings = $vaultSettings;
    }

    public function saveAiConfig(): void
    {
        Gate::authorize('update', $this->team);

        if (! $this->persistAiConfig()) {
            return;
        }

        Flux::toast(variant: 'success', text: __('Workspace AI Add-On settings saved.'));
    }

    protected function persistAiConfig(): bool
    {
        $this->validate([
            'isEnabled' => ['boolean'],
            'providerMode' => ['required', Rule::in(['off', 'managed', 'byok', 'ollama'])],
            'fallbackToByok' => ['boolean'],
            'chatProvider' => ['required', Rule::in(['openai', 'openrouter', 'anthropic', 'gemini', 'ollama', 'openai_compatible'])],
            'chatBaseUrl' => ['nullable', 'string', 'max:255', 'url'],
            'chatModel' => ['nullable', 'string', 'max:120'],
            'fastModel' => ['nullable', 'string', 'max:120'],
            'chatApiKeyInput' => ['nullable', 'string', 'max:500'],
            'embeddingProvider' => ['required', Rule::in(['none', 'managed', 'openai', 'gemini', 'openrouter', 'ollama', 'openai_compatible'])],
            'embeddingBaseUrl' => ['nullable', 'string', 'max:255', 'url'],
            'embeddingModel' => ['nullable', 'string', 'max:120'],
            'embeddingApiKeyInput' => ['nullable', 'string', 'max:500'],
            'dlpRedactionEnabled' => ['boolean'],
            'monthlyBudgetUsd' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        if ($this->isEnabled && $this->providerMode === 'off') {
            $this->providerMode = $this->canUseManagedCloud ? 'managed' : 'byok';
        }

        if ($this->providerMode === 'managed' && ! $this->canUseManagedCloud) {
            $this->addError('providerMode', __('Synkk Managed Cloud AI requires an active Synkk Cloud plan. Use BYOK or Local Ollama instead.'));
            Flux::toast(variant: 'danger', text: __('Managed Cloud AI requires an active Synkk Cloud plan.'));

            return false;
        }

        $monthlyBudgetCents = filled($this->monthlyBudgetUsd)
            ? (int) round(((float) $this->monthlyBudgetUsd) * 100)
            : null;

        $attributes = [
            'is_enabled' => $this->isEnabled,
            'provider_mode' => $this->isEnabled ? $this->providerMode : ($this->providerMode === 'off' ? 'off' : $this->providerMode),
            'fallback_to_byok' => $this->fallbackToByok,
            'chat_provider' => $this->providerMode === 'ollama' ? 'ollama' : $this->chatProvider,
            'chat_base_url' => filled($this->chatBaseUrl) ? trim($this->chatBaseUrl) : null,
            'chat_model' => filled($this->chatModel) ? trim($this->chatModel) : null,
            'fast_model' => filled($this->fastModel) ? trim($this->fastModel) : null,
            'embedding_provider' => $this->embeddingProvider,
            'embedding_base_url' => filled($this->embeddingBaseUrl) ? trim($this->embeddingBaseUrl) : null,
            'embedding_model' => filled($this->embeddingModel) ? trim($this->embeddingModel) : null,
            'dlp_redaction_enabled' => $this->dlpRedactionEnabled,
            'monthly_budget_cents' => $monthlyBudgetCents,
        ];

        if (trim($this->chatApiKeyInput) !== '') {
            $attributes['chat_api_key'] = trim($this->chatApiKeyInput);
        }

        if (trim($this->embeddingApiKeyInput) !== '') {
            $attributes['embedding_api_key'] = trim($this->embeddingApiKeyInput);
        }

        $config = TeamAiConfig::query()->updateOrCreate(
            ['team_id' => $this->team->id],
            $attributes,
        );

        if ($this->isEnabled && $this->providerMode === 'managed') {
            app(AiQuotaService::class)->ensureCloudTrialGranted($this->team, $config);
        }

        $this->chatApiKeyInput = '';
        $this->embeddingApiKeyInput = '';
        $this->hasChatApiKey = filled($config->fresh()?->chat_api_key);
        $this->hasEmbeddingApiKey = filled($config->fresh()?->embedding_api_key);

        unset($this->usageSummary);

        return true;
    }

    public function clearChatApiKey(): void
    {
        Gate::authorize('update', $this->team);

        TeamAiConfig::query()
            ->where('team_id', $this->team->id)
            ->update(['chat_api_key' => null]);

        $this->chatApiKeyInput = '';
        $this->hasChatApiKey = false;
        Flux::toast(variant: 'warning', text: __('Stored chat API key removed.'));
    }

    public function clearEmbeddingApiKey(): void
    {
        Gate::authorize('update', $this->team);

        TeamAiConfig::query()
            ->where('team_id', $this->team->id)
            ->update(['embedding_api_key' => null]);

        $this->embeddingApiKeyInput = '';
        $this->hasEmbeddingApiKey = false;
        Flux::toast(variant: 'warning', text: __('Stored embedding API key removed.'));
    }

    public function testChatConnection(): void
    {
        Gate::authorize('update', $this->team);

        if (! $this->persistAiConfig()) {
            return;
        }

        $config = $this->team->aiConfig()->firstOrFail();
        $result = app(AiGateway::class)->testConnection($config, 'chat');
        $this->chatTestResult = $result;

        Flux::toast(
            variant: $result['ok'] ? 'success' : 'danger',
            text: $result['message'],
        );
    }

    public function testEmbeddingConnection(): void
    {
        Gate::authorize('update', $this->team);

        if (! $this->persistAiConfig()) {
            return;
        }

        $config = $this->team->aiConfig()->firstOrFail();
        $result = app(AiGateway::class)->testConnection($config, 'embedding');
        $this->embeddingTestResult = $result;

        Flux::toast(
            variant: $result['ok'] ? 'success' : 'danger',
            text: $result['message'],
        );
    }

    public function saveVaultSetting(int $vaultId): void
    {
        Gate::authorize('update', $this->team);

        /** @var Vault $vault */
        $vault = $this->team->vaults()->findOrFail($vaultId);
        $entry = $this->vaultSettings[$vaultId] ?? ['ai_mode' => 'off', 'excluded_paths_text' => ''];
        $mode = (string) ($entry['ai_mode'] ?? 'off');

        if (! in_array($mode, ['off', 'server', 'client_local'], true)) {
            $mode = 'off';
        }

        if ($vault->is_e2ee && $mode === 'server') {
            $this->addError("vaultSettings.{$vaultId}.ai_mode", __('Zero-Knowledge E2EE vaults cannot use server-side AI. Choose Off or Client-Side Local AI.'));
            Flux::toast(variant: 'danger', text: __('Zero-Knowledge E2EE vaults cannot enable server-side AI.'));

            return;
        }

        $rawPaths = (string) ($entry['excluded_paths_text'] ?? '');
        $parts = preg_split('/[\r\n,]+/', $rawPaths) ?: [];
        $excludedPaths = array_values(array_filter(array_map(
            fn (string $item): string => trim($item),
            $parts
        ), fn (string $item): bool => $item !== ''));

        VaultAiSetting::query()->updateOrCreate(
            ['vault_id' => $vault->id],
            [
                'ai_mode' => $mode,
                'excluded_paths' => $excludedPaths,
                'allow_direct_mcp_writes' => ! $vault->is_e2ee && (bool) ($entry['allow_direct_mcp_writes'] ?? false),
            ]
        );

        $this->vaultSettings[$vaultId]['excluded_paths_text'] = implode(', ', $excludedPaths);
        $this->vaultSettings[$vaultId]['allow_direct_mcp_writes'] = ! $vault->is_e2ee && (bool) ($entry['allow_direct_mcp_writes'] ?? false);

        Flux::toast(variant: 'success', text: __('Vault AI settings saved for :name.', ['name' => $vault->name]));
    }

    public function createMcpToken(): void
    {
        Gate::authorize('update', $this->team);

        if (! app(PlanService::class)->hasFeature($this->team, 'ai_mcp_server')) {
            Flux::toast(variant: 'danger', text: __('MCP server access is not available on your current plan.'));

            return;
        }

        $this->validate([
            'mcpTokenName' => ['required', 'string', 'min:2', 'max:80'],
            'mcpTokenScope' => ['required', Rule::in(DeviceToken::MCP_SCOPES)],
            'mcpAllowedVaultId' => ['nullable', 'string'],
        ]);

        $allowedVaultIds = null;
        if (filled($this->mcpAllowedVaultId)) {
            $scopedVault = $this->team->vaults()->findOrFail((int) $this->mcpAllowedVaultId);
            $allowedVaultIds = [$scopedVault->id];
        }

        /** @var User $user */
        $user = Auth::user();
        $result = DeviceToken::createToken(
            user: $user,
            team: $this->team,
            name: trim($this->mcpTokenName),
            platform: DeviceToken::PLATFORM_MCP,
            accessScope: $this->mcpTokenScope,
            allowedIpSubnets: null,
            allowedVaultIds: $allowedVaultIds,
        );

        $this->newlyCreatedMcpToken = $result['plain_token'];
        $this->mcpTokenName = '';
        $this->mcpAllowedVaultId = '';
        unset($this->mcpTokens);

        Flux::toast(variant: 'success', text: __('MCP token created. Copy it now — it will not be shown again.'));
    }

    public function revokeMcpToken(int $tokenId): void
    {
        Gate::authorize('update', $this->team);

        $token = $this->team->deviceTokens()->mcpTokens()->findOrFail($tokenId);
        $token->triggerRemoteWipe();
        unset($this->mcpTokens);

        Flux::toast(variant: 'warning', text: __('MCP token revoked.'));
    }

    /**
     * @return Collection<int, DeviceToken>
     */
    #[Computed]
    public function mcpTokens(): Collection
    {
        return $this->team->deviceTokens()
            ->mcpTokens()
            ->where('is_wiped', false)
            ->with('user')
            ->latest('id')
            ->get();
    }

    public function reindexVault(int $vaultId): void
    {
        Gate::authorize('update', $this->team);

        /** @var Vault $vault */
        $vault = $this->team->vaults()->findOrFail($vaultId);
        if ($vault->is_e2ee) {
            Flux::toast(variant: 'warning', text: __('E2EE vaults cannot be indexed on the server.'));

            return;
        }

        IndexVaultRagJob::dispatch($vault, force: true);

        Flux::toast(variant: 'success', text: __('Queued full re-index for :name.', ['name' => $vault->name]));
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function usageSummary(): array
    {
        return app(AiQuotaService::class)->getUsageSummary($this->team);
    }

    #[Computed]
    public function canUseManagedCloud(): bool
    {
        return app(PlanService::class)->hasFeature($this->team, 'ai_managed_cloud');
    }

    #[Computed]
    public function isManagedAddonConfigured(): bool
    {
        return app(DodoPaymentsService::class)->isAiAddonConfigured();
    }

    #[Computed]
    public function canUpdateTeam(): bool
    {
        return Gate::allows('update', $this->team);
    }

    /**
     * @return Collection<int, Vault>
     */
    #[Computed]
    public function vaults(): Collection
    {
        return $this->team->vaults()->with('aiSetting')->orderBy('name')->get();
    }
}; ?>

<div class="space-y-6" data-test="team-ai-addon-section">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <flux:heading>{{ __('Synkk AI Add-On') }}</flux:heading>
            <flux:subheading>{{ __('100% opt-in team memory, hybrid FTS5 + vector search, permissioned MCP server, and vault automation') }}</flux:subheading>
        </div>

        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-black uppercase tracking-wider {{ $this->isEnabled ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/70 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800' : 'bg-slate-200/80 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300' }}">
            <span class="size-2 rounded-full {{ $this->isEnabled ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
            {{ $this->isEnabled ? __('AI Active (:mode)', ['mode' => strtoupper($this->providerMode)]) : __('AI Off (Default)') }}
        </span>
    </div>

    @if (! $this->canUpdateTeam)
        <div class="rounded-2xl border border-gray-100 bg-[#FBFBFA] p-5 dark:border-zinc-800/80 dark:bg-zinc-800/40">
            <flux:text class="text-xs text-gray-600 dark:text-zinc-400">
                {{ $this->isEnabled ? __('Workspace AI Add-On is enabled by a team owner or administrator.') : __('Workspace AI Add-On is currently disabled for this team.') }}
            </flux:text>
        </div>
    @else
        <!-- Monthly Usage Telemetry Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
            <div class="rounded-2xl border border-gray-100 bg-[#FBFBFA] p-4 shadow-2xs dark:border-zinc-800/80 dark:bg-zinc-800/40">
                <div class="text-xs font-semibold text-gray-500 dark:text-zinc-400">{{ __('AI Requests (Month)') }}</div>
                <div class="mt-1.5 text-lg font-black text-gray-900 dark:text-white">
                    {{ number_format((int) $this->usageSummary['requests_this_month']) }}
                </div>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-[#FBFBFA] p-4 shadow-2xs dark:border-zinc-800/80 dark:bg-zinc-800/40">
                <div class="text-xs font-semibold text-gray-500 dark:text-zinc-400">{{ __('Tokens Processed') }}</div>
                <div class="mt-1.5 text-lg font-black text-gray-900 dark:text-white">
                    {{ number_format((int) $this->usageSummary['total_tokens_this_month']) }}
                </div>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-[#FBFBFA] p-4 shadow-2xs dark:border-zinc-800/80 dark:bg-zinc-800/40">
                <div class="text-xs font-semibold text-gray-500 dark:text-zinc-400">{{ __('Est. Spend (Month)') }}</div>
                <div class="mt-1.5 text-lg font-black text-gray-900 dark:text-white">
                    ${{ number_format((float) $this->usageSummary['cost_usd_this_month'], 4) }}
                </div>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-[#FBFBFA] p-4 shadow-2xs dark:border-zinc-800/80 dark:bg-zinc-800/40">
                <div class="text-xs font-semibold text-gray-500 dark:text-zinc-400">{{ __('Secrets Redacted') }}</div>
                <div class="mt-1.5 text-lg font-black text-emerald-700 dark:text-emerald-400">
                    {{ number_format((int) $this->usageSummary['redacted_secrets_this_month']) }}
                </div>
            </div>
        </div>

        <!-- Master Opt-In & Provider Configuration Form -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-zinc-800 dark:bg-zinc-900 space-y-6">
            <form wire:submit="saveAiConfig" class="space-y-6">
                <div class="flex items-start justify-between gap-4 rounded-xl border border-gray-100 bg-[#FBFBFA] p-4 dark:border-zinc-800 dark:bg-zinc-800/40">
                    <div>
                        <flux:checkbox
                            wire:model.live="isEnabled"
                            :label="__('Enable Synkk AI Add-On for this workspace')"
                            data-test="ai-enable-checkbox"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">
                            {{ __('When unchecked, no indexing jobs run and no vault content ever reaches any model.') }}
                        </p>
                    </div>
                </div>

                @if ($this->isEnabled)
                    <div class="space-y-4">
                        <flux:select wire:model.live="providerMode" :label="__('Inference Mode')" data-test="ai-provider-mode-select">
                            <flux:select.option value="managed" :disabled="! $this->canUseManagedCloud">
                                {{ __('Synkk Managed Cloud AI ($:price/mo add-on · zero API key setup)', ['price' => config('synkk.pricing.ai_addon.amount', 12)]) }}
                            </flux:select.option>
                            <flux:select.option value="byok">{{ __('Bring Your Own Key (OpenAI, OpenRouter, Anthropic, Gemini, OpenAI-Compatible)') }}</flux:select.option>
                            <flux:select.option value="ollama">{{ __('Local / Private Ollama') }}</flux:select.option>
                        </flux:select>
                        <flux:error name="providerMode" />

                        @if ($this->providerMode === 'managed')
                            <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/40 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/20 space-y-3">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-bold text-emerald-950 dark:text-emerald-200">
                                            {{ $this->usageSummary['has_active_managed_subscription'] ? __('Managed AI Add-On Active') : __('Cloud AI Trial & Subscription Pool') }}
                                        </div>
                                        <p class="text-xs text-emerald-800/80 dark:text-emerald-300/80 mt-0.5">
                                            {{ __('Remaining available credits: $:avail (Monthly $:rem + Trial $:trial). Hard-stopped at 100% — zero surprise overages.', [
                                                'avail' => number_format((float) $this->usageSummary['total_available_managed_usd'], 2),
                                                'rem' => number_format((float) $this->usageSummary['managed_credits_remaining_usd'], 2),
                                                'trial' => number_format((float) $this->usageSummary['trial_credits_remaining_usd'], 2),
                                            ]) }}
                                        </p>
                                    </div>

                                    @if (! $this->usageSummary['has_active_managed_subscription'] && $this->isManagedAddonConfigured)
                                        <button
                                            type="submit"
                                            form="dodo-ai-addon-checkout-form"
                                            class="inline-flex items-center justify-center rounded-full bg-[#0D3B29] px-4 py-2 text-xs font-bold text-white hover:bg-[#0D3B29]/90 cursor-pointer shrink-0"
                                            data-test="ai-managed-checkout-button"
                                        >
                                            {{ __('Subscribe $:price/mo →', ['price' => config('synkk.pricing.ai_addon.amount', 12)]) }}
                                        </button>
                                    @endif
                                </div>

                                @if ($this->usageSummary['managed_credits_total_usd'] > 0)
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-emerald-200/70 dark:bg-emerald-950">
                                        <div class="h-full rounded-full bg-[#0D3B29] dark:bg-emerald-400" style="width: {{ (int) $this->usageSummary['managed_credits_used_pct'] }}%"></div>
                                    </div>
                                @endif

                                <flux:checkbox
                                    wire:model="fallbackToByok"
                                    :label="__('Automatically fall back to configured BYOK / Ollama credentials if Managed credits reach 100%')"
                                />
                            </div>
                        @endif

                        @if ($this->providerMode === 'byok' || $this->providerMode === 'ollama' || $this->fallbackToByok)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                @if ($this->providerMode !== 'ollama')
                                    <flux:select wire:model.live="chatProvider" :label="__('Chat / Agent Provider')" data-test="ai-chat-provider-select">
                                        <flux:select.option value="openai">OpenAI</flux:select.option>
                                        <flux:select.option value="openrouter">OpenRouter</flux:select.option>
                                        <flux:select.option value="anthropic">Anthropic (Claude)</flux:select.option>
                                        <flux:select.option value="gemini">Google Gemini</flux:select.option>
                                        <flux:select.option value="openai_compatible">OpenAI-Compatible (Groq / vLLM / LM Studio)</flux:select.option>
                                        <flux:select.option value="ollama">Ollama</flux:select.option>
                                    </flux:select>
                                @endif

                                <flux:input
                                    wire:model="chatModel"
                                    :label="__('Primary Chat Model')"
                                    placeholder="{{ $this->providerMode === 'ollama' ? 'llama3.2' : 'gpt-4o-mini' }}"
                                    data-test="ai-chat-model-input"
                                />

                                <flux:input
                                    wire:model="fastModel"
                                    :label="__('Fast Model (Optional · Triage & Merge)')"
                                    placeholder="{{ $this->providerMode === 'ollama' ? 'llama3.2' : 'gpt-4o-mini' }}"
                                />

                                <flux:input
                                    wire:model="chatBaseUrl"
                                    :label="__('Custom Base URL (Optional)')"
                                    placeholder="{{ $this->providerMode === 'ollama' || $this->chatProvider === 'ollama' ? 'http://localhost:11434' : 'https://api.openai.com/v1' }}"
                                />

                                @if ($this->providerMode !== 'ollama' && $this->chatProvider !== 'ollama')
                                    <div class="sm:col-span-2 space-y-2">
                                        <div class="flex items-center justify-between">
                                            <label class="text-xs font-semibold text-gray-700 dark:text-zinc-300">
                                                {{ __('Chat API Key') }}
                                            </label>
                                            @if ($this->hasChatApiKey)
                                                <div class="flex items-center gap-2">
                                                    <flux:badge color="emerald" size="sm">{{ __('Encrypted key saved') }}</flux:badge>
                                                    <button type="button" wire:click="clearChatApiKey" class="text-xs text-red-600 hover:underline cursor-pointer">
                                                        {{ __('Remove') }}
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                        <flux:input
                                            wire:model="chatApiKeyInput"
                                            type="password"
                                            autocomplete="off"
                                            :placeholder="$this->hasChatApiKey ? __('•••••••••••••••• (leave blank to keep existing key)') : __('Paste provider API key (encrypted at rest)')"
                                            data-test="ai-chat-api-key-input"
                                        />
                                    </div>
                                @endif
                            </div>

                            @if ($this->chatProvider === 'anthropic' && $this->embeddingProvider === 'none')
                                <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('Anthropic does not provide native embeddings. Your vault will use SQLite FTS5 BM25 unless you choose an OpenAI, Gemini, OpenRouter, or Ollama embedding provider below.')" />
                            @endif

                            <!-- Embeddings Configuration -->
                            <div class="border-t border-gray-100 dark:border-zinc-800 pt-4 space-y-4">
                                <div class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-zinc-400">
                                    {{ __('Vector Embeddings (Optional · Enhances FTS5 Hybrid Search)') }}
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <flux:select wire:model.live="embeddingProvider" :label="__('Embedding Provider')">
                                        <flux:select.option value="none">{{ __('FTS5 Lexical + Deterministic Only (Zero extra cost)') }}</flux:select.option>
                                        @if ($this->canUseManagedCloud && $this->providerMode === 'managed')
                                            <flux:select.option value="managed">{{ __('Synkk Managed Embeddings') }}</flux:select.option>
                                        @endif
                                        <flux:select.option value="openai">OpenAI Embeddings</flux:select.option>
                                        <flux:select.option value="gemini">Google Gemini Embeddings</flux:select.option>
                                        <flux:select.option value="openrouter">OpenRouter Embeddings</flux:select.option>
                                        <flux:select.option value="ollama">Local Ollama Embeddings</flux:select.option>
                                        <flux:select.option value="openai_compatible">OpenAI-Compatible Embeddings</flux:select.option>
                                    </flux:select>

                                    @if ($this->embeddingProvider !== 'none' && $this->embeddingProvider !== 'managed')
                                        <flux:input
                                            wire:model="embeddingModel"
                                            :label="__('Embedding Model')"
                                            placeholder="{{ $this->embeddingProvider === 'ollama' ? 'nomic-embed-text' : 'text-embedding-3-small' }}"
                                        />

                                        <flux:input
                                            wire:model="embeddingBaseUrl"
                                            :label="__('Embedding Base URL (Optional)')"
                                            placeholder="{{ $this->embeddingProvider === 'ollama' ? 'http://localhost:11434' : 'https://api.openai.com/v1' }}"
                                        />

                                        @if ($this->embeddingProvider !== 'ollama')
                                            <div class="space-y-1">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs font-semibold text-gray-700 dark:text-zinc-300">{{ __('Embedding API Key (Optional if same as chat)') }}</span>
                                                    @if ($this->hasEmbeddingApiKey)
                                                        <button type="button" wire:click="clearEmbeddingApiKey" class="text-xs text-red-600 hover:underline cursor-pointer">{{ __('Remove') }}</button>
                                                    @endif
                                                </div>
                                                <flux:input
                                                    wire:model="embeddingApiKeyInput"
                                                    type="password"
                                                    autocomplete="off"
                                                    :placeholder="$this->hasEmbeddingApiKey ? __('•••••••• (saved)') : __('Optional dedicated key')"
                                                />
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Privacy & Budget Guardrails -->
                        <div class="border-t border-gray-100 dark:border-zinc-800 pt-4 space-y-4">
                            <flux:checkbox
                                wire:model="dlpRedactionEnabled"
                                :label="__('DLP Pre-Flight Secret Redaction (scrub AWS, GitHub, Slack, OpenAI, Anthropic, Synkk & PEM keys before cloud LLM calls)')"
                                data-test="ai-dlp-checkbox"
                            />

                            @if ($this->providerMode === 'byok')
                                <div class="max-w-xs">
                                    <flux:input
                                        wire:model="monthlyBudgetUsd"
                                        type="number"
                                        step="0.50"
                                        min="0"
                                        :label="__('Optional Monthly BYOK Spend Cap (USD)')"
                                        placeholder="e.g. 25.00"
                                    />
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <flux:button
                        variant="primary"
                        type="submit"
                        data-test="ai-save-button"
                        class="!bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90 !rounded-full px-5 font-bold text-xs"
                    >
                        {{ __('Save AI Settings') }}
                    </flux:button>

                    @if ($this->isEnabled)
                        <flux:button
                            variant="subtle"
                            type="button"
                            wire:click="testChatConnection"
                            data-test="ai-test-chat-button"
                            class="!rounded-full text-xs font-semibold"
                        >
                            {{ __('Test Chat Connection') }}
                        </flux:button>

                        @if ($this->embeddingProvider !== 'none')
                            <flux:button
                                variant="subtle"
                                type="button"
                                wire:click="testEmbeddingConnection"
                                class="!rounded-full text-xs font-semibold"
                            >
                                {{ __('Test Embeddings') }}
                            </flux:button>
                        @endif
                    @endif
                </div>

                @if ($this->chatTestResult !== null)
                    <flux:callout
                        :variant="$this->chatTestResult['ok'] ? 'success' : 'danger'"
                        :icon="$this->chatTestResult['ok'] ? 'check-circle' : 'x-circle'"
                        :heading="$this->chatTestResult['message'] . ' (' . $this->chatTestResult['latency_ms'] . 'ms)'"
                    />
                @endif

                @if ($this->embeddingTestResult !== null)
                    <flux:callout
                        :variant="$this->embeddingTestResult['ok'] ? 'success' : 'danger'"
                        :icon="$this->embeddingTestResult['ok'] ? 'check-circle' : 'x-circle'"
                        :heading="$this->embeddingTestResult['message'] . ' (' . $this->embeddingTestResult['latency_ms'] . 'ms)'"
                    />
                @endif
            </form>

            @if ($this->canUseManagedCloud && $this->isManagedAddonConfigured)
                <form id="dodo-ai-addon-checkout-form" method="POST" action="{{ route('billing.dodo.ai_addon.checkout', ['current_team' => $this->team->slug]) }}" class="hidden">
                    @csrf
                </form>
            @endif
        </div>

        <!-- Per-Vault Opt-In & Path Exclusions -->
        <div class="space-y-3">
            <div>
                <flux:heading size="sm">{{ __('Per-Vault AI Access & Excluded Folders') }}</flux:heading>
                <flux:subheading>{{ __('Explicitly opt vaults into server AI and exclude private paths (e.g. Private/**, HR/**). Zero-Knowledge E2EE vaults are locked off from server AI.') }}</flux:subheading>
            </div>

            <div class="space-y-3">
                @forelse ($this->vaults as $vault)
                    <div class="rounded-2xl border border-gray-100 bg-[#FBFBFA] p-4 dark:border-zinc-800/80 dark:bg-zinc-800/40 space-y-3" data-test="ai-vault-row-{{ $vault->id }}">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm text-gray-900 dark:text-white">{{ $vault->name }}</span>
                                @if ($vault->is_e2ee)
                                    <flux:badge color="amber" size="sm">{{ __('Zero-Knowledge E2EE') }}</flux:badge>
                                @endif
                            </div>

                            @if (! $vault->is_e2ee)
                                <button
                                    type="button"
                                    wire:click="reindexVault({{ $vault->id }})"
                                    class="text-xs font-semibold text-[#0D3B29] hover:underline dark:text-emerald-400 cursor-pointer self-start sm:self-auto"
                                >
                                    {{ __('Re-index Vault') }}
                                </button>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                            <div class="sm:col-span-4">
                                <flux:select
                                    wire:model="vaultSettings.{{ $vault->id }}.ai_mode"
                                    :label="__('Vault AI Mode')"
                                    data-test="ai-vault-mode-{{ $vault->id }}"
                                >
                                    <flux:select.option value="off">{{ __('Off (Default)') }}</flux:select.option>
                                    <flux:select.option value="server" :disabled="$vault->is_e2ee">
                                        {{ $vault->is_e2ee ? __('Server AI (Blocked by E2EE)') : __('Server AI (Copilot, Hybrid Search, MCP)') }}
                                    </flux:select.option>
                                    <flux:select.option value="client_local">{{ __('Client-Side Local AI Only (Obsidian)') }}</flux:select.option>
                                </flux:select>
                                <flux:error name="vaultSettings.{{ $vault->id }}.ai_mode" />
                            </div>

                            <div class="sm:col-span-6">
                                <flux:input
                                    wire:model="vaultSettings.{{ $vault->id }}.excluded_paths_text"
                                    :label="__('Excluded Paths / Globs')"
                                    placeholder="Private/**, HR/**, *.secret.md"
                                    data-test="ai-vault-excluded-{{ $vault->id }}"
                                />
                            </div>

                            <div class="sm:col-span-2">
                                <flux:button
                                    variant="primary"
                                    type="button"
                                    wire:click="saveVaultSetting({{ $vault->id }})"
                                    data-test="ai-vault-save-{{ $vault->id }}"
                                    class="w-full !bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90 !rounded-full text-xs font-bold"
                                >
                                    {{ __('Save') }}
                                </flux:button>
                            </div>
                        </div>

                        @if (! $vault->is_e2ee)
                            <div class="pt-1">
                                <flux:checkbox
                                    wire:model="vaultSettings.{{ $vault->id }}.allow_direct_mcp_writes"
                                    :label="__('Allow direct MCP writes for mcp_write tokens (bypass Proposal Inbox)')"
                                    data-test="ai-vault-direct-mcp-{{ $vault->id }}"
                                />
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-200 p-4 text-xs text-gray-500 dark:border-zinc-800 dark:text-zinc-400">
                        {{ __('Create a vault first to configure per-vault AI settings.') }}
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Remote MCP Server & Scoped Agent Tokens -->
        <div class="space-y-4 pt-2" data-test="ai-mcp-tokens-section">
            <div>
                <flux:heading size="sm">{{ __('Permissioned Remote MCP Server') }}</flux:heading>
                <flux:subheading>{{ __('Connect Claude Desktop, Cursor, or Windsurf using a scoped MCP token. MCP tokens respect path ACLs and do not consume Obsidian device seats.') }}</flux:subheading>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-zinc-800 dark:bg-zinc-900 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-xl bg-gray-50 px-3.5 py-2.5 dark:bg-zinc-950 border border-gray-100 dark:border-zinc-800">
                    <span class="text-xs font-semibold text-gray-500 dark:text-zinc-400">{{ __('MCP Streamable HTTP Endpoint') }}</span>
                    <code class="text-xs font-mono text-gray-900 dark:text-emerald-400 select-all">{{ url('/api/v1/mcp') }}</code>
                </div>

                <form wire:submit="createMcpToken" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                    <div class="sm:col-span-4">
                        <flux:input
                            wire:model="mcpTokenName"
                            :label="__('Agent / Client Name')"
                            placeholder="Claude Desktop"
                            data-test="mcp-token-name-input"
                            required
                        />
                    </div>

                    <div class="sm:col-span-4">
                        <flux:select wire:model="mcpTokenScope" :label="__('Permission Scope')" data-test="mcp-token-scope-select">
                            <flux:select.option value="mcp_read">{{ __('Read-Only (Search & Read)') }}</flux:select.option>
                            <flux:select.option value="mcp_propose">{{ __('Propose-Only (Recommended · AI Inbox)') }}</flux:select.option>
                            <flux:select.option value="mcp_write">{{ __('Direct Write (When Vault Opted In)') }}</flux:select.option>
                        </flux:select>
                    </div>

                    <div class="sm:col-span-2">
                        <flux:select wire:model="mcpAllowedVaultId" :label="__('Vault Scope')">
                            <flux:select.option value="">{{ __('All AI Vaults') }}</flux:select.option>
                            @foreach ($this->vaults as $vault)
                                <flux:select.option value="{{ $vault->id }}">{{ $vault->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="sm:col-span-2">
                        <flux:button
                            variant="primary"
                            type="submit"
                            data-test="mcp-token-create-button"
                            class="w-full !bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90 !rounded-full text-xs font-bold"
                        >
                            {{ __('Create Token') }}
                        </flux:button>
                    </div>
                </form>

                @if ($newlyCreatedMcpToken)
                    <div class="rounded-xl border border-emerald-300 bg-emerald-50/60 p-4 dark:border-emerald-800 dark:bg-emerald-950/30 space-y-2.5" data-test="mcp-created-token-box">
                        <div class="text-xs font-bold text-emerald-900 dark:text-emerald-200">
                            {{ __('Copy your new MCP Bearer token now (shown once):') }}
                        </div>
                        <pre class="overflow-x-auto rounded-lg bg-zinc-950 p-3 text-xs font-mono text-emerald-300 select-all">{{ $newlyCreatedMcpToken }}</pre>
                        <div class="text-[11px] font-semibold text-emerald-900 dark:text-emerald-300">{{ __('Claude Desktop / Cursor / Windsurf JSON snippet:') }}</div>
                        <pre class="overflow-x-auto rounded-lg bg-zinc-950 p-3 text-[11px] font-mono text-zinc-200 select-all">{{ json_encode([
    'mcpServers' => [
        'synkk' => [
            'url' => url('/api/v1/mcp'),
            'headers' => [
                'Authorization' => 'Bearer '.$newlyCreatedMcpToken,
            ],
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                @endif

                <div class="space-y-2 pt-1">
                    @forelse ($this->mcpTokens as $mcpToken)
                        <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-[#FBFBFA] px-4 py-2.5 dark:border-zinc-800/80 dark:bg-zinc-800/40" data-test="mcp-token-row-{{ $mcpToken->id }}">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">{{ $mcpToken->name }}</span>
                                    <flux:badge size="sm" color="indigo">{{ $mcpToken->access_scope }}</flux:badge>
                                    <span class="font-mono text-[11px] text-gray-400">{{ $mcpToken->token_preview }}</span>
                                </div>
                                <div class="text-[11px] text-gray-500 dark:text-zinc-400 mt-0.5">
                                    {{ $mcpToken->last_used_at ? __('Last used :time', ['time' => $mcpToken->last_used_at->diffForHumans()]) : __('Never used') }}
                                </div>
                            </div>

                            <button
                                type="button"
                                wire:click="revokeMcpToken({{ $mcpToken->id }})"
                                data-test="mcp-token-revoke-{{ $mcpToken->id }}"
                                class="text-xs font-semibold text-red-600 hover:underline dark:text-red-400 cursor-pointer"
                            >
                                {{ __('Revoke') }}
                            </button>
                        </div>
                    @empty
                        <p class="text-xs text-gray-500 dark:text-zinc-400">{{ __('No MCP agent tokens created yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
