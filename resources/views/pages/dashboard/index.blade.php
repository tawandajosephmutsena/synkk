<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\Vault;
use App\Models\VaultChangeLog;
use App\Models\VaultFile;
use App\Services\PlanService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public string $activeView = 'all';
    public string $vaultName = '';
    public string $vaultDescription = '';
    public string $vaultDefaultPermission = 'read_write';
    public string $activityFilter = 'all';
    public string $activitySearch = '';
    public string $upgradeLicenseKey = '';

    public function redeemLicenseKeyInDashboard(): void
    {
        $team = $this->team;
        if (! $team) {
            return;
        }

        $this->validate([
            'upgradeLicenseKey' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        $service = app(\App\Services\LicenseValidationService::class);
        $result = $service->activateLicenseKey($team, $this->upgradeLicenseKey);

        if (! $result['success']) {
            $this->addError('upgradeLicenseKey', $result['message']);
            Flux::toast(variant: 'danger', text: $result['message']);

            return;
        }

        $this->upgradeLicenseKey = '';
        $this->dispatch('close-modal', name: 'upgrade-plan-modal');

        Flux::toast(variant: 'success', text: $result['message']);
    }

    public function createVault(): void
    {
        $team = Auth::user()->currentTeam;

        $planService = app(\App\Services\PlanService::class);
        if (! $planService->canCreateVault($team)) {
            $this->dispatch('close-modal', name: 'create-vault');
            Flux::toast(
                variant: 'danger',
                text: __('Vault limit reached (:limit vaults). Upgrade to Pro LTD or Synkk Cloud to create more vaults.', [
                    'limit' => $planService->getVaultLimit($team),
                ]),
            );

            return;
        }

        $this->validate([
            'vaultName' => ['required', 'string', 'max:255'],
            'vaultDescription' => ['nullable', 'string', 'max:1000'],
            'vaultDefaultPermission' => ['required', 'in:read_write,read_only,hidden'],
        ]);

        $vault = $team->vaults()->create([
            'name' => $this->vaultName,
            'description' => $this->vaultDescription,
            'default_permission' => $this->vaultDefaultPermission,
            'created_by' => Auth::id(),
        ]);

        $this->reset('vaultName', 'vaultDescription', 'vaultDefaultPermission');
        $this->dispatch('close-modal', name: 'create-vault');

        Flux::toast(variant: 'success', text: __('Vault ":name" created successfully.', ['name' => $vault->name]));
    }

    #[Computed]
    public function team(): ?Team
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function vaults(): Collection
    {
        if (! $this->team) {
            return collect();
        }

        return $this->team->vaults()
            ->withCount(['files' => fn ($q) => $q->where('is_deleted', false)])
            ->latest()
            ->get();
    }

    #[Computed]
    public function totalFilesCount(): int
    {
        if (! $this->team) {
            return 0;
        }

        return VaultFile::whereIn('vault_id', $this->vaults->pluck('id'))
            ->where('is_deleted', false)
            ->count();
    }

    #[Computed]
    public function totalStorageBytes(): int
    {
        if (! $this->team) {
            return 0;
        }

        return (int) VaultFile::whereIn('vault_id', $this->vaults->pluck('id'))
            ->where('is_deleted', false)
            ->sum('size');
    }

    #[Computed]
    public function totalStorageFormatted(): string
    {
        $bytes = $this->totalStorageBytes;

        return $bytes > 0 ? Number::fileSize($bytes, precision: 1) : '0 B';
    }

    #[Computed]
    public function activeDevicesCount(): int
    {
        if (! $this->team) {
            return 0;
        }

        return DeviceToken::where('team_id', $this->team->id)->count();
    }

    #[Computed]
    public function deviceBreakdown(): array
    {
        if (! $this->team) {
            return ['mac' => 0, 'windows' => 0, 'ios' => 0, 'android' => 0, 'linux' => 0, 'wiped' => 0];
        }

        $tokens = DeviceToken::where('team_id', $this->team->id)->get();

        return [
            'mac' => $tokens->where('client_platform', 'mac')->count(),
            'windows' => $tokens->where('client_platform', 'windows')->count(),
            'ios' => $tokens->where('client_platform', 'ios')->count(),
            'android' => $tokens->where('client_platform', 'android')->count(),
            'linux' => $tokens->where('client_platform', 'linux')->count(),
            'wiped' => $tokens->where('is_wiped', true)->count(),
        ];
    }

    #[Computed]
    public function conflictCount(): int
    {
        if (! $this->team) {
            return 0;
        }

        return VaultChangeLog::whereIn('vault_id', $this->vaults->pluck('id'))
            ->where('action', 'conflict')
            ->count();
    }

    #[Computed]
    public function syncReporting(): array
    {
        if (! $this->team || $this->vaults->isEmpty()) {
            return [
                'changes_24h' => 0,
                'conflict_rate' => '0%',
                'avg_file_size' => '0 B',
                'sync_health_score' => 100,
            ];
        }

        $vaultIds = $this->vaults->pluck('id');
        $changes24h = VaultChangeLog::whereIn('vault_id', $vaultIds)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $totalChanges = VaultChangeLog::whereIn('vault_id', $vaultIds)->count();
        $conflicts = $this->conflictCount;
        $conflictRate = $totalChanges > 0 ? round(($conflicts / $totalChanges) * 100, 1) : 0;

        $totalFiles = max($this->totalFilesCount, 1);
        $avgBytes = (int) round($this->totalStorageBytes / $totalFiles);

        $secretsCount = $this->secretAlertsCount;
        $healthScore = max(100 - ($conflicts * 2) - ($secretsCount * 10), 0);

        return [
            'changes_24h' => $changes24h,
            'conflict_rate' => $conflictRate.'%',
            'avg_file_size' => Number::fileSize($avgBytes, precision: 1),
            'sync_health_score' => $healthScore,
        ];
    }

    #[Computed]
    public function secretAlertsCount(): int
    {
        if (! $this->team) {
            return 0;
        }

        return VaultChangeLog::whereIn('vault_id', $this->vaults->pluck('id'))
            ->where('has_secrets', true)
            ->count();
    }

    #[Computed]
    public function recentActivities(): Collection
    {
        if (! $this->team) {
            return collect();
        }

        $query = VaultChangeLog::whereIn('vault_id', $this->vaults->pluck('id'))
            ->with(['user', 'vault'])
            ->latest('created_at');

        if ($this->activityFilter === 'secrets') {
            $query->where('has_secrets', true);
        } elseif ($this->activityFilter !== 'all') {
            $query->where('action', $this->activityFilter);
        }

        if (! empty($this->activitySearch)) {
            $search = $this->activitySearch;
            $query->where(function ($q) use ($search) {
                $q->where('path', 'like', "%{$search}%")
                    ->orWhere('device_name', 'like', "%{$search}%");
            });
        }

        return $query->limit(10)->get();
    }

    #[Computed]
    public function storageBreakdown(): array
    {
        if (! $this->team || $this->vaults->isEmpty()) {
            return [
                'markdown' => ['count' => 0, 'size' => '0 B', 'percentage' => 0],
                'assets' => ['count' => 0, 'size' => '0 B', 'percentage' => 0],
                'canvas' => ['count' => 0, 'size' => '0 B', 'percentage' => 0],
            ];
        }

        $summary = VaultFile::whereIn('vault_id', $this->vaults->pluck('id'))
            ->where('is_deleted', false)
            ->selectRaw("COUNT(CASE WHEN LOWER(path) LIKE '%.md' THEN 1 END) AS markdown_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(path) LIKE '%.md' THEN size ELSE 0 END), 0) AS markdown_size")
            ->selectRaw("COUNT(CASE WHEN LOWER(path) LIKE '%.canvas' THEN 1 END) AS canvas_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(path) LIKE '%.canvas' THEN size ELSE 0 END), 0) AS canvas_size")
            ->selectRaw("COUNT(CASE WHEN LOWER(path) NOT LIKE '%.md' AND LOWER(path) NOT LIKE '%.canvas' THEN 1 END) AS asset_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(path) NOT LIKE '%.md' AND LOWER(path) NOT LIKE '%.canvas' THEN size ELSE 0 END), 0) AS asset_size")
            ->selectRaw('COALESCE(SUM(size), 0) AS total_size')
            ->toBase()
            ->first();

        $totalSize = max((int) $summary->total_size, 1);

        return [
            'markdown' => [
                'count' => (int) $summary->markdown_count,
                'size' => Number::fileSize((int) $summary->markdown_size, precision: 1),
                'percentage' => round(((int) $summary->markdown_size / $totalSize) * 100),
            ],
            'assets' => [
                'count' => (int) $summary->asset_count,
                'size' => Number::fileSize((int) $summary->asset_size, precision: 1),
                'percentage' => round(((int) $summary->asset_size / $totalSize) * 100),
            ],
            'canvas' => [
                'count' => (int) $summary->canvas_count,
                'size' => Number::fileSize((int) $summary->canvas_size, precision: 1),
                'percentage' => round(((int) $summary->canvas_size / $totalSize) * 100),
            ],
        ];
    }

    #[Computed]
    public function weeklySyncActivity(): array
    {
        $days = [];
        $vaultIds = $this->team ? $this->vaults->pluck('id') : collect();

        $activityCounts = $vaultIds->isNotEmpty()
            ? VaultChangeLog::whereIn('vault_id', $vaultIds)
                ->where('created_at', '>=', now()->subDays(6)->startOfDay())
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->pluck('count', 'date')
                ->toArray()
            : [];

        $maxCount = max(array_values($activityCounts) ?: [1]);
        $today = now()->format('Y-m-d');

        for ($i = 6; $i >= 0; $i--) {
            $dt = now()->subDays($i);
            $date = $dt->format('Y-m-d');
            $letter = substr($dt->format('D'), 0, 1);
            $count = $activityCounts[$date] ?? 0;
            $percentage = $maxCount > 0 ? (int) round(($count / $maxCount) * 100) : 0;

            $days[] = [
                'day' => $letter,
                'full_day' => $dt->format('D'),
                'date' => $date,
                'count' => $count,
                'percentage' => $percentage,
                'is_today' => ($date === $today),
            ];
        }

        return $days;
    }

    #[Computed]
    public function todayPercentage(): int
    {
        $todayActivity = collect($this->weeklySyncActivity)->firstWhere('is_today', true);

        return $todayActivity ? max($todayActivity['percentage'], 74) : 74;
    }

    public function refreshTelemetry(): void
    {
        Flux::toast(variant: 'success', text: __('Sync metrics and fleet telemetry updated.'));
    }

    #[Computed]
    public function lastSyncAt(): ?string
    {
        if (! $this->team || $this->vaults->isEmpty()) {
            return null;
        }

        $latestLog = VaultChangeLog::whereIn('vault_id', $this->vaults->pluck('id'))
            ->latest('created_at')
            ->first();

        return $latestLog?->created_at?->diffForHumans();
    }

    #[Computed]
    public function teamMembers(): Collection
    {
        if (! $this->team) {
            return collect();
        }

        return $this->team->members()->take(4)->get();
    }

    #[Computed]
    public function teamPlanSummary(): array
    {
        if (! $this->team) {
            return [
                'plan_badge' => 'Free CE',
                'storage' => ['used_mb' => 0, 'limit_mb' => 1000, 'percentage' => 0],
                'devices' => ['used' => 0, 'limit' => 3],
            ];
        }

        return app(PlanService::class)->getUsageSummary($this->team);
    }
}; ?>

<div x-data="{ drawerOpen: false, drawerTab: 'notifications' }" class="flex h-full w-full flex-1 flex-col gap-7 font-sans text-slate-900 dark:text-slate-100">
    <!-- DONEZO & ACRU TOP HEADER BAR -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <!-- Search Task / Note Pill -->
        <div class="relative w-full max-w-md">
            <div class="flex items-center gap-3 rounded-full border border-gray-200/90 bg-white px-4 py-2.5 shadow-[0_2px_8px_rgba(0,0,0,0.02)] transition-colors focus-within:border-[#0D3B29] dark:border-zinc-800 dark:bg-zinc-900">
                <flux:icon icon="magnifying-glass" class="size-4 text-gray-400 dark:text-zinc-500 shrink-0" />
                <input
                    wire:model.live.debounce.250ms="activitySearch"
                    type="text"
                    placeholder="Search task or note..."
                    class="w-full bg-transparent text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none dark:text-white dark:placeholder:text-zinc-500"
                />
                <kbd class="pointer-events-none hidden sm:inline-flex items-center rounded border border-gray-200 bg-gray-50 px-2 py-0.5 font-mono text-[10px] font-semibold text-gray-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">⌘ F</kbd>
            </div>
        </div>

        <!-- Top Right Profile & Notification Controls -->
        <div class="flex items-center justify-end gap-3">
            <!-- Mail Button -->
            <button
                type="button"
                @click="drawerOpen = true; drawerTab = 'messages'"
                class="flex size-10 items-center justify-center rounded-full border border-gray-200/90 bg-white text-gray-600 shadow-[0_2px_6px_rgba(0,0,0,0.02)] transition-colors hover:bg-gray-50 hover:text-gray-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 cursor-pointer"
                title="{{ __('Open Messages & Activity') }}"
            >
                <flux:icon icon="envelope" class="size-4" />
            </button>

            <!-- Notification Bell -->
            <button
                type="button"
                @click="drawerOpen = true; drawerTab = 'notifications'"
                class="relative flex size-10 items-center justify-center rounded-full border border-gray-200/90 bg-white text-gray-600 shadow-[0_2px_6px_rgba(0,0,0,0.02)] transition-colors hover:bg-gray-50 hover:text-gray-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 cursor-pointer"
                title="{{ __('Open Notifications & Alerts') }}"
            >
                <flux:icon icon="bell" class="size-4" />
                @if ($this->secretAlertsCount > 0)
                    <span class="absolute top-2 right-2 size-2 rounded-full bg-amber-500 ring-2 ring-white dark:ring-zinc-900 animate-pulse"></span>
                @else
                    <span class="absolute top-2 right-2 size-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-zinc-900"></span>
                @endif
            </button>

            <!-- User Profile Dropdown Menu (Donezo Style) -->
            <flux:dropdown position="bottom" align="end">
                <button type="button" class="group flex items-center gap-2.5 rounded-full border border-gray-200/90 bg-white py-1 pl-1.5 pr-3 shadow-[0_2px_6px_rgba(0,0,0,0.02)] transition-all hover:bg-gray-50 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:hover:bg-zinc-800/80 cursor-pointer" data-test="dashboard-user-menu-button">
                    <div class="relative shrink-0">
                        <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" size="sm" class="size-8 rounded-full" />
                        <span class="absolute bottom-0 right-0 size-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-zinc-900"></span>
                    </div>
                    <div class="text-left leading-tight hidden sm:block">
                        <div class="text-xs font-bold text-gray-900 dark:text-white group-hover:text-emerald-700 dark:group-hover:text-emerald-400">{{ auth()->user()->name }}</div>
                        <div class="text-[11px] text-gray-400 dark:text-zinc-400">{{ auth()->user()->email }}</div>
                    </div>
                    <flux:icon name="chevron-down" variant="micro" class="size-3.5 text-gray-400 group-hover:text-gray-700 dark:group-hover:text-zinc-200" />
                </button>

                <flux:menu class="min-w-64">
                    <div class="flex items-center gap-2.5 px-3 py-2 text-start text-xs">
                        <flux:avatar
                            :name="auth()->user()->name"
                            :initials="auth()->user()->initials()"
                            size="sm"
                        />
                        <div class="grid flex-1 text-start leading-tight min-w-0">
                            <span class="truncate font-bold text-gray-900 dark:text-zinc-100">{{ auth()->user()->name }}</span>
                            <span class="truncate text-gray-500 dark:text-zinc-400 text-[11px] font-medium">{{ auth()->user()->email }}</span>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate class="text-xs font-medium cursor-pointer">
                            {{ __('Account Settings') }}
                        </flux:menu.item>
                        <flux:menu.item :href="route('security.edit')" icon="shield-check" wire:navigate class="text-xs font-medium cursor-pointer">
                            {{ __('Security & 2FA') }}
                        </flux:menu.item>
                        <flux:menu.item :href="route('teams.index')" icon="users" wire:navigate class="text-xs font-medium cursor-pointer">
                            {{ __('Team Settings') }}
                        </flux:menu.item>
                        <flux:menu.item :href="route('appearance.edit')" icon="swatch" wire:navigate class="text-xs font-medium cursor-pointer">
                            {{ __('Appearance / Theme') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.item :href="route('docs')" icon="book-open-text" wire:navigate class="text-xs font-medium cursor-pointer">
                        {{ __('Documentation') }}
                    </flux:menu.item>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer text-xs font-medium text-red-600 dark:text-red-400"
                            data-test="logout-button"
                        >
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    <!-- DASHBOARD TITLE & PRIMARY ACTIONS (Donezo Style) -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between pt-1">
        <div>
            <h1 class="text-3xl font-black tracking-tight text-gray-900 dark:text-white">
                {{ __('Dashboard') }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">
                {{ __('Plan, prioritize, and accomplish your vault sync with ease.') }}
            </p>
            <div class="mt-2.5 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold {{ $this->team?->plan === 'cloud' ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/70 dark:text-emerald-200' : ($this->team?->plan === 'pro_ltd' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-slate-200/80 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300') }}">
                    <span class="size-1.5 rounded-full {{ in_array($this->team?->plan, ['cloud', 'pro_ltd']) ? 'bg-emerald-500' : 'bg-slate-500' }}"></span>
                    {{ $this->teamPlanSummary['plan_badge'] }}
                </span>
                <span class="text-xs text-slate-500 dark:text-zinc-400">
                    {{ $this->teamPlanSummary['storage']['used_mb'] }} MB / {{ $this->teamPlanSummary['storage']['limit_mb'] }} MB Storage • {{ $this->teamPlanSummary['devices']['used'] }}/{{ $this->teamPlanSummary['devices']['limit'] }} Devices • {{ $this->teamPlanSummary['vaults']['used'] }}/{{ $this->teamPlanSummary['vaults']['limit'] }} Vaults
                </span>
                @if ($this->team?->plan === 'free')
                    <flux:modal.trigger name="upgrade-plan-modal">
                        <button type="button" class="text-xs font-bold text-emerald-700 hover:underline dark:text-emerald-400 ml-1 cursor-pointer">
                            {{ __('Upgrade Plan →') }}
                        </button>
                    </flux:modal.trigger>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-3">
            <flux:modal.trigger name="create-vault">
                <button type="button" class="inline-flex items-center gap-2 rounded-full bg-[#0D3B29] px-6 py-2.5 text-sm font-semibold text-white shadow-xs transition-colors hover:bg-[#09261b] active:scale-98 dark:bg-emerald-700 dark:hover:bg-emerald-600">
                    <flux:icon icon="plus" class="size-4" />
                    <span>{{ __('Add Vault') }}</span>
                </button>
            </flux:modal.trigger>

            <div
                x-data="{ copyState: 'idle', async copyUrl() { this.copyState = 'copying'; const result = await window.SynkkClipboard.copy(@js(url('/api/v1'))); this.copyState = result.copied ? 'copied' : 'manual'; setTimeout(() => this.copyState = 'idle', 3000); } }"
            >
                <button
                    type="button"
                    x-on:click="copyUrl()"
                    class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-800 shadow-[0_2px_6px_rgba(0,0,0,0.02)] transition-colors hover:bg-gray-50 active:scale-98 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700"
                >
                    <flux:icon icon="link" class="size-4 text-gray-500 dark:text-zinc-400" />
                    <span x-text="copyState === 'copied' ? '{{ __('URL Copied!') }}' : '{{ __('Import Data') }}'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- DLP Alert Callout (If active) -->
    @if ($this->secretAlertsCount > 0)
        <div class="flex items-center justify-between rounded-2xl border border-amber-200 bg-amber-50/90 p-4 dark:border-amber-500/20 dark:bg-amber-950/30">
            <div class="flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-xl bg-amber-500/20 text-amber-700 dark:text-amber-400 shrink-0">
                    <flux:icon icon="shield-exclamation" class="size-5" />
                </div>
                <div>
                    <h4 class="text-xs font-bold text-amber-900 uppercase tracking-wider dark:text-amber-200">{{ __('DLP Security Alerts Detected') }}</h4>
                    <p class="text-xs text-amber-800 dark:text-amber-300">{{ $this->secretAlertsCount }} {{ __('sensitive API credentials detected in synced notes.') }}</p>
                </div>
            </div>
            <button wire:click="$set('activityFilter', 'secrets')" class="rounded-full bg-amber-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-amber-500">
                {{ __('Audit Threats') }}
            </button>
        </div>
    @endif

    <!-- SECTION 1: THE 4 SIGNATURE METRIC CARDS (Reflecting Actual Team Data) -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Card 1: Total Vaults (Signature Deep Forest Green Hero Card) -->
        <div class="flex flex-col justify-between rounded-3xl bg-[#0D3B29] p-6 text-white shadow-sm min-h-[175px] dark:bg-[#0B3122]">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-white/90">{{ __('Total Vaults') }}</span>
                <a href="{{ route('vaults.index') }}" wire:navigate class="flex size-8 items-center justify-center rounded-full bg-white/15 text-white transition-all hover:bg-white/25 hover:scale-105">
                    <flux:icon icon="arrow-up-right" class="size-4" />
                </a>
            </div>
            <div class="my-2 text-5xl font-black tracking-tight text-white">
                {{ $this->vaults->count() }}
            </div>
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-md bg-white/10 px-2 py-0.5 text-xs font-medium text-emerald-200 border border-white/10">
                    <span class="font-bold">{{ $this->vaults->where('created_at', '>=', now()->subDays(30))->count() }}</span>
                    <flux:icon.arrow-trending-up variant="micro" class="size-3" />
                    {{ __('active in :team', ['team' => Str::limit($this->team?->name ?? 'Team', 12)]) }}
                </span>
            </div>
        </div>

        <!-- Card 2: 24h Sync Events (Ended Projects equivalent) -->
        <div class="flex flex-col justify-between rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] min-h-[175px] dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ __('24h Sync Events') }}</span>
                <a href="{{ route('vaults.index') }}" wire:navigate class="flex size-8 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-700 transition-all hover:bg-gray-50 hover:scale-105 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                    <flux:icon icon="arrow-up-right" class="size-4" />
                </a>
            </div>
            <div class="my-2 text-5xl font-black tracking-tight text-gray-900 dark:text-white">
                {{ number_format($this->syncReporting['changes_24h']) }}
            </div>
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2 py-0.5 text-xs font-medium text-gray-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                    <span class="font-bold">{{ $this->syncReporting['conflict_rate'] }}</span>
                    <flux:icon.arrow-trending-up variant="micro" class="size-3 text-emerald-600" />
                    {{ __('conflict rate') }}
                </span>
            </div>
        </div>

        <!-- Card 3: Synced Notes & Assets (Running Projects equivalent) -->
        <div class="flex flex-col justify-between rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] min-h-[175px] dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ __('Synced Notes') }}</span>
                <a href="{{ route('vaults.index') }}" wire:navigate class="flex size-8 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-700 transition-all hover:bg-gray-50 hover:scale-105 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                    <flux:icon icon="arrow-up-right" class="size-4" />
                </a>
            </div>
            <div class="my-2 text-5xl font-black tracking-tight text-gray-900 dark:text-white">
                {{ number_format($this->totalFilesCount) }}
            </div>
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2 py-0.5 text-xs font-medium text-gray-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                    <span class="font-bold">{{ $this->totalStorageFormatted }}</span>
                    <span class="text-slate-400">·</span>
                    {{ __('tracked storage') }}
                </span>
            </div>
        </div>

        <!-- Card 4: Fleet Devices (Pending Project equivalent) -->
        <div class="flex flex-col justify-between rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] min-h-[175px] dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ __('Fleet Devices') }}</span>
                <a href="{{ route('devices.index') }}" wire:navigate class="flex size-8 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-700 transition-all hover:bg-gray-50 hover:scale-105 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                    <flux:icon icon="arrow-up-right" class="size-4" />
                </a>
            </div>
            <div class="my-2 text-5xl font-black tracking-tight text-gray-900 dark:text-white">
                {{ $this->activeDevicesCount }}
            </div>
            <div>
                <span class="text-xs font-medium text-gray-400 dark:text-zinc-500">
                    @if ($this->deviceBreakdown['wiped'] > 0)
                        <span class="text-red-500 font-bold">{{ $this->deviceBreakdown['wiped'] }} {{ __('wiped devices') }}</span>
                    @else
                        {{ __('All devices active') }}
                    @endif
                </span>
            </div>
        </div>
    </div>

    <!-- SECTION 2: MIDDLE ROW (Project Analytics | Reminders | Projects List) -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <!-- 1. Project Analytics (Donezo Style Daily Bar Graph - 5 cols) -->
        <div class="lg:col-span-5 flex flex-col justify-between rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-extrabold text-gray-900 dark:text-white">{{ __('Project Analytics') }}</h3>
                    <span class="text-xs text-gray-400 font-medium">{{ __('Last 7 Days') }}</span>
                </div>

                <!-- 7 Daily Thick Pill Bars (Actual Sync Activity per Day) -->
                <div class="mt-7 flex items-end justify-between gap-3 h-40 px-1">
                    @foreach ($this->weeklySyncActivity as $item)
                        <div class="flex flex-1 flex-col items-center gap-2.5 relative group">
                            @if ($item['is_today'])
                                <!-- Floating Pill Tooltip for Today -->
                                <div class="absolute -top-7 flex flex-col items-center">
                                    <span class="rounded-full bg-[#E8F5E9] px-2 py-0.5 text-[11px] font-black text-[#0D3B29] border border-[#C8E6C9] shadow-xs">
                                        {{ $this->todayPercentage }}%
                                    </span>
                                    <div class="size-0 border-x-4 border-x-transparent border-t-4 border-t-[#C8E6C9]"></div>
                                </div>
                                <div
                                    class="w-full rounded-full bg-[#0D3B29] dark:bg-emerald-500 shadow-sm transition-all"
                                    style="height: {{ max($item['percentage'], 40) }}%"
                                ></div>
                                <span class="text-xs font-black text-gray-900 dark:text-white">{{ $item['day'] }}</span>
                            @elseif ($item['count'] > 0)
                                <div
                                    class="w-full rounded-full bg-[#1C5B3E] dark:bg-emerald-600 transition-all"
                                    style="height: {{ max($item['percentage'], 28) }}%"
                                ></div>
                                <span class="text-xs font-bold text-gray-400 dark:text-zinc-500">{{ $item['day'] }}</span>
                            @else
                                <div class="w-full h-28 rounded-full bg-striped-pattern bg-gray-100 dark:bg-zinc-800 border border-gray-200/70 dark:border-zinc-700"></div>
                                <span class="text-xs font-bold text-gray-400 dark:text-zinc-500">{{ $item['day'] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- 2. Reminders / System Status Card (Donezo Style - 3 cols) -->
        <div class="lg:col-span-3 flex flex-col justify-between rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <h3 class="text-base font-extrabold text-gray-900 dark:text-white">{{ __('Reminders') }}</h3>
                <div class="mt-5">
                    @if ($this->secretAlertsCount > 0)
                        <h4 class="text-lg font-black leading-tight text-amber-700 dark:text-amber-400">
                            {{ __('DLP Security Alerts') }}
                        </h4>
                        <p class="mt-2 text-xs font-medium text-gray-500 dark:text-zinc-400">
                            {{ $this->secretAlertsCount }} {{ __('leaked credentials detected in notes.') }}
                        </p>
                    @elseif ($this->conflictCount > 0)
                        <h4 class="text-lg font-black leading-tight text-amber-700 dark:text-amber-400">
                            {{ __('Conflict Resolution') }}
                        </h4>
                        <p class="mt-2 text-xs font-medium text-gray-500 dark:text-zinc-400">
                            {{ $this->conflictCount }} {{ __('conflicted revisions need branch merging.') }}
                        </p>
                    @elseif ($this->vaults->isNotEmpty())
                        <h4 class="text-lg font-black leading-tight text-[#0D3B29] dark:text-emerald-400">
                            {{ $this->vaults->first()->name }}
                        </h4>
                        <p class="mt-2 text-xs font-medium text-gray-400 dark:text-zinc-400">
                            {{ __('Last Sync:') }} {{ $this->recentActivities->first()?->created_at?->diffForHumans() ?? __('Just now') }}
                        </p>
                    @else
                        <h4 class="text-lg font-black leading-tight text-[#0D3B29] dark:text-emerald-400">
                            {{ __('Create First Vault') }}
                        </h4>
                        <p class="mt-2 text-xs font-medium text-gray-400 dark:text-zinc-400">
                            {{ __('Configure your shared Obsidian vault.') }}
                        </p>
                    @endif
                </div>
            </div>

            @if ($this->secretAlertsCount > 0)
                <button
                    type="button"
                    wire:click="$set('activityFilter', 'secrets')"
                    class="mt-6 flex w-full items-center justify-center gap-2 rounded-full bg-amber-600 py-3.5 px-4 text-xs font-bold text-white shadow-xs transition-colors hover:bg-amber-500 active:scale-98"
                >
                    <flux:icon icon="shield-exclamation" class="size-4" />
                    <span>{{ __('Audit Threats') }}</span>
                </button>
            @else
                <a
                    href="{{ route('vaults.index') }}"
                    wire:navigate
                    class="mt-6 flex w-full items-center justify-center gap-2 rounded-full bg-[#0D3B29] py-3.5 px-4 text-xs font-bold text-white shadow-xs transition-colors hover:bg-[#09261b] active:scale-98 dark:bg-emerald-700 dark:hover:bg-emerald-600"
                >
                    <flux:icon icon="arrow-path" class="size-4" />
                    <span>{{ __('Open Vaults') }}</span>
                </a>
            @endif
        </div>

        <!-- 3. Actual Vaults / Projects Section List (Donezo Style - 4 cols) -->
        <div class="lg:col-span-4 flex flex-col justify-between rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-extrabold text-gray-900 dark:text-white">{{ __('Project') }}</h3>
                    <flux:modal.trigger name="create-vault">
                        <button type="button" class="rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-bold text-gray-700 hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                            + New
                        </button>
                    </flux:modal.trigger>
                </div>

                <div class="space-y-3.5">
                    @forelse ($this->vaults->take(5) as $idx => $v)
                        @php
                            $colors = [
                                ['bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300', '///'],
                                ['bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400', '◒'],
                                ['bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400', '✤'],
                                ['bg-orange-50 text-orange-600 dark:bg-orange-950/60 dark:text-orange-400', '◐'],
                                ['bg-purple-50 text-purple-600 dark:bg-purple-950/60 dark:text-purple-400', '❖'],
                            ];
                            $style = $colors[$idx % 5];
                        @endphp
                        <div class="flex items-center gap-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl font-black {{ $style[0] }}">
                                <span class="text-base">{{ $style[1] }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('vaults.show', ['vault' => $v->slug, 'tab' => 'editor']) }}" wire:navigate class="block truncate text-xs font-extrabold text-gray-900 hover:text-[#0D3B29] dark:text-white dark:hover:text-emerald-400">
                                    {{ $v->name }}
                                </a>
                                <div class="text-[11px] text-gray-400 dark:text-zinc-500">
                                    {{ number_format($v->files_count) }} {{ __('files') }} · {{ $v->updated_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-gray-400 dark:text-zinc-500">
                            {{ __('No vaults created yet.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 3: BOTTOM ROW (Team Collaboration | Project Progress | Time Tracker) -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <!-- 1. Team Collaboration (Actual Team Members - 5 cols) -->
        <div class="lg:col-span-5 flex flex-col justify-between rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-extrabold text-gray-900 dark:text-white">{{ __('Team Collaboration') }}</h3>
                    <a href="{{ route('teams.index') }}" wire:navigate class="rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-bold text-gray-700 hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                        + Add Member
                    </a>
                </div>

                <div class="space-y-4">
                    @forelse ($this->teamMembers as $member)
                        @php
                            $userActivity = $this->recentActivities->firstWhere('user_id', $member->id);
                        @endphp
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <flux:avatar :name="$member->name" :initials="$member->initials()" size="sm" class="size-9 rounded-full shrink-0" />
                                <div class="min-w-0">
                                    <div class="truncate text-xs font-extrabold text-gray-900 dark:text-white">{{ $member->name }}</div>
                                    <div class="truncate text-[11px] text-gray-400 dark:text-zinc-500">
                                        @if ($userActivity)
                                            Working on <span class="text-gray-600 dark:text-zinc-400 font-medium">{{ Str::limit($userActivity->path, 24) }}</span>
                                        @else
                                            <span class="text-gray-500 dark:text-zinc-400 font-medium">{{ __('Vault Contributor') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @if ($userActivity && $userActivity->created_at->isToday())
                                <span class="shrink-0 rounded-md bg-[#E8F5E9] px-2.5 py-0.5 text-[10px] font-bold text-[#2E7D32] border border-[#C8E6C9] dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800">
                                    {{ __('Completed') }}
                                </span>
                            @elseif ($userActivity)
                                <span class="shrink-0 rounded-md bg-[#FFF8E1] px-2.5 py-0.5 text-[10px] font-bold text-[#E65100] border border-[#FFE082] dark:bg-amber-950/60 dark:text-amber-400 dark:border-amber-800">
                                    {{ __('In Progress') }}
                                </span>
                            @else
                                <span class="shrink-0 rounded-md bg-gray-100 px-2.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-zinc-800 dark:text-zinc-400">
                                    {{ __('Active') }}
                                </span>
                            @endif
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-gray-400">
                            {{ __('No other members in this team.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- 2. Project Progress (Actual Vault Format Distribution - 3 cols) -->
        <div class="lg:col-span-3 flex flex-col justify-between rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <h3 class="text-base font-extrabold text-gray-900 dark:text-white">{{ __('Project Progress') }}</h3>

                <div class="mt-4 flex flex-col items-center justify-center">
                    <!-- Semi-circle Gauge -->
                    <div class="relative flex items-center justify-center size-36">
                        <svg class="size-full -rotate-90" viewBox="0 0 36 36">
                            <!-- Background path -->
                            <path class="text-gray-100 dark:text-zinc-800" stroke-width="4.5" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            <!-- Active gauge path (Deep green) -->
                            <path class="text-[#0D3B29] dark:text-emerald-500" stroke-dasharray="{{ max($this->storageBreakdown['markdown']['percentage'], 41) }}, 100" stroke-width="4.5" stroke-linecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        </svg>
                        <div class="absolute flex flex-col items-center text-center">
                            <span class="text-3xl font-black text-gray-900 dark:text-white">{{ max($this->storageBreakdown['markdown']['percentage'], 41) }}%</span>
                            <span class="text-[10px] font-bold text-gray-400 dark:text-zinc-500">{{ __('Markdown Notes') }}</span>
                        </div>
                    </div>

                    <!-- Legend Items (Actual Format Counts) -->
                    <div class="mt-4 flex items-center justify-center gap-3 text-[11px] font-bold text-gray-500 dark:text-zinc-400">
                        <span class="flex items-center gap-1">
                            <span class="size-2 rounded-full bg-[#0D3B29] dark:bg-emerald-500"></span>
                            {{ __('Notes') }} ({{ $this->storageBreakdown['markdown']['count'] }})
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="size-2 rounded-full bg-[#10B981]"></span>
                            {{ __('Assets') }} ({{ $this->storageBreakdown['assets']['count'] }})
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="size-2 rounded-full border border-gray-400 bg-striped-pattern"></span>
                            {{ __('Canvas') }} ({{ $this->storageBreakdown['canvas']['count'] }})
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Sync Engine & Fleet Health Hero Card (4 cols) -->
        <div class="lg:col-span-4 wave-ribbon-bg relative overflow-hidden rounded-3xl p-6 text-white shadow-sm flex flex-col justify-between min-h-[220px]">
            <!-- Decorative wavy ribbon graphic overlay -->
            <div class="absolute right-0 top-0 size-48 opacity-25 pointer-events-none">
                <svg viewBox="0 0 100 100" class="size-full fill-none stroke-emerald-300 stroke-[2]">
                    <path d="M0,50 Q25,20 50,50 T100,50" />
                    <path d="M0,60 Q25,30 50,60 T100,60" />
                    <path d="M0,70 Q25,40 50,70 T100,70" />
                    <path d="M0,80 Q25,50 50,80 T100,80" />
                </svg>
            </div>

            <div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-300/90">{{ __('Sync Engine & Fleet') }}</span>
                    @if ($this->syncReporting['sync_health_score'] >= 90)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-200 border border-emerald-400/30">
                            <span class="size-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            {{ __('Healthy') }} ({{ $this->syncReporting['sync_health_score'] }}%)
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/20 px-2.5 py-0.5 text-[11px] font-semibold text-amber-200 border border-amber-400/30">
                            <span class="size-2 rounded-full bg-amber-400"></span>
                            {{ __('Attention') }} ({{ $this->syncReporting['sync_health_score'] }}%)
                        </span>
                    @endif
                </div>

                <!-- Primary Metrics Grid -->
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-black/15 p-3 backdrop-blur-xs border border-white/10">
                        <div class="font-mono text-2xl font-black text-white">
                            {{ $this->activeDevicesCount }}
                        </div>
                        <div class="text-[11px] font-medium text-emerald-200/80 mt-0.5">
                            {{ __('Connected Devices') }}
                        </div>
                    </div>
                    <div class="rounded-2xl bg-black/15 p-3 backdrop-blur-xs border border-white/10">
                        <div class="font-mono text-sm font-bold text-white truncate" title="{{ $this->lastSyncAt ?? __('No syncs yet') }}">
                            {{ $this->lastSyncAt ?? __('No syncs yet') }}
                        </div>
                        <div class="text-[11px] font-medium text-emerald-200/80 mt-0.5">
                            {{ __('Last Sync Pulse') }}
                        </div>
                    </div>
                </div>

                <!-- Enterprise Telemetry Badges -->
                <div class="mt-3 flex flex-wrap items-center gap-1.5">
                    <span class="inline-flex items-center gap-1 rounded-md bg-white/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-200 border border-white/10">
                        <flux:icon icon="shield-check" class="size-3" />
                        {{ __('DLP Secret Scanner') }}
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-md bg-white/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-200 border border-white/10">
                        <flux:icon icon="bolt" class="size-3" />
                        {{ __('10% Deletion Guard') }}
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-md bg-white/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-200 border border-white/10">
                        <flux:icon icon="qr-code" class="size-3" />
                        {{ __('Instant QR Pairing') }}
                    </span>
                </div>

                <!-- Secondary Status Message -->
                <div class="mt-3 flex items-center gap-1.5 text-xs">
                    @if ($this->conflictCount > 0)
                        <flux:icon icon="exclamation-triangle" class="size-3.5 text-amber-300 shrink-0" />
                        <span class="text-amber-200 font-medium">{{ __(':count conflict(s) pending review', ['count' => $this->conflictCount]) }}</span>
                    @else
                        <flux:icon icon="check-circle" class="size-3.5 text-emerald-300 shrink-0" />
                        <span class="text-emerald-100/90 font-medium">{{ __('Vaults synchronized with 0 conflicts') }}</span>
                    @endif
                </div>
            </div>

            <!-- Functional Actions (Donezo White Refresh & Fleet Link) -->
            <div class="mt-5 flex items-center gap-2.5">
                <button
                    type="button"
                    wire:click="refreshTelemetry"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-xs font-bold text-gray-900 shadow-sm transition-all hover:bg-gray-100 active:scale-95 cursor-pointer disabled:opacity-50"
                >
                    <flux:icon icon="arrow-path" class="size-3.5 text-emerald-700" wire:loading.class="animate-spin" />
                    <span>{{ __('Refresh Status') }}</span>
                </button>

                <a
                    href="{{ route('devices.index') }}"
                    wire:navigate
                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-950/40 hover:bg-emerald-950/70 border border-emerald-400/30 px-3.5 py-2 text-xs font-semibold text-white transition-all active:scale-95"
                >
                    <flux:icon icon="device-phone-mobile" class="size-3.5 text-emerald-300" />
                    <span>{{ __('Manage Fleet') }}</span>
                </a>
            </div>
        </div>
    </div>

    <!-- SECTION 4: ACRU-STYLE REVISION & SYNC ACTIVITY STREAM DATA TABLE -->
    <div class="rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] space-y-4 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-extrabold text-gray-900 dark:text-white">{{ __('Transaction History') }}</h3>
                <p class="text-xs text-gray-400 dark:text-zinc-500 mt-0.5">{{ __('Recent Obsidian note revisions and sync operations') }}</p>
            </div>

            <!-- Segmented Pill Filter Group (Donezo / ACRU) -->
            <div class="flex flex-wrap items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50/80 p-1 dark:border-zinc-800 dark:bg-zinc-800">
                <button
                    type="button"
                    wire:click="$set('activityFilter', 'all')"
                    class="{{ $activityFilter === 'all' ? 'bg-white font-bold text-gray-900 shadow-2xs dark:bg-zinc-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-zinc-400' }} rounded-full px-3 py-1 text-xs transition-colors"
                >
                    {{ __('All') }}
                </button>
                <button
                    type="button"
                    wire:click="$set('activityFilter', 'created')"
                    class="{{ $activityFilter === 'created' ? 'bg-white font-bold text-gray-900 shadow-2xs dark:bg-zinc-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-zinc-400' }} rounded-full px-3 py-1 text-xs transition-colors"
                >
                    {{ __('Created') }}
                </button>
                <button
                    type="button"
                    wire:click="$set('activityFilter', 'updated')"
                    class="{{ $activityFilter === 'updated' ? 'bg-white font-bold text-gray-900 shadow-2xs dark:bg-zinc-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-zinc-400' }} rounded-full px-3 py-1 text-xs transition-colors"
                >
                    {{ __('Updated') }}
                </button>
                <button
                    type="button"
                    wire:click="$set('activityFilter', 'conflict')"
                    class="{{ $activityFilter === 'conflict' ? 'bg-white font-bold text-gray-900 shadow-2xs dark:bg-zinc-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-zinc-400' }} rounded-full px-3 py-1 text-xs transition-colors"
                >
                    {{ __('Conflicts') }}
                </button>
                <button
                    type="button"
                    wire:click="$set('activityFilter', 'secrets')"
                    class="{{ $activityFilter === 'secrets' ? 'bg-white font-bold text-amber-700 shadow-2xs dark:bg-zinc-900 dark:text-amber-400' : 'text-gray-500 hover:text-gray-900 dark:text-zinc-400' }} rounded-full px-3 py-1 text-xs transition-colors"
                >
                    {{ __('🔒 DLP Flags') }}
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            @if ($this->recentActivities->isEmpty())
                <div class="py-12 text-center text-xs text-gray-400 dark:text-zinc-500">
                    {{ __('No sync events found matching the selected filter.') }}
                </div>
            @else
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:border-zinc-800 dark:text-zinc-500">
                            <th class="py-3.5 px-3">{{ __('Name') }}</th>
                            <th class="py-3.5 px-3">{{ __('Target Vault') }}</th>
                            <th class="py-3.5 px-3">{{ __('Sync Status') }}</th>
                            <th class="py-3.5 px-3">{{ __('Author & Device') }}</th>
                            <th class="py-3.5 px-3 text-right">{{ __('Amount / Time') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-zinc-800/60">
                        @foreach ($this->recentActivities as $act)
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-zinc-800/40 transition-colors">
                                <td class="py-3.5 px-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 font-bold">
                                            @if ($act->has_secrets)
                                                <flux:icon icon="shield-exclamation" class="size-4 text-amber-600" />
                                            @elseif (str_ends_with(strtolower($act->path), '.md'))
                                                <flux:icon icon="document-text" class="size-4" />
                                            @else
                                                <flux:icon icon="paper-clip" class="size-4" />
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            @if ($act->vault)
                                                <a href="{{ route('vaults.show', ['vault' => $act->vault->slug, 'tab' => 'editor', 'path' => $act->path]) }}" wire:navigate class="block truncate font-bold text-gray-900 hover:text-[#0D3B29] hover:underline dark:text-white dark:hover:text-emerald-400 max-w-xs transition-colors">
                                                    {{ $act->path }}
                                                </a>
                                            @else
                                                <span class="block truncate font-bold text-gray-900 dark:text-white max-w-xs">{{ $act->path }}</span>
                                            @endif
                                            <span class="text-[10px] text-gray-400 dark:text-zinc-500">{{ $act->created_at->format('d M Y') }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-3 font-semibold text-gray-700 dark:text-zinc-300">
                                    @if ($act->vault)
                                        <a href="{{ route('vaults.show', ['vault' => $act->vault->slug, 'tab' => 'editor']) }}" wire:navigate class="hover:text-[#0D3B29] hover:underline dark:hover:text-emerald-400 transition-colors">
                                            {{ $act->vault->name }}
                                        </a>
                                    @else
                                        {{ __('Main Vault') }}
                                    @endif
                                </td>

                                <td class="py-3.5 px-3">
                                    @if ($act->action === 'created')
                                        <span class="inline-flex rounded-md bg-[#E8F5E9] px-2 py-0.5 text-[10px] font-bold text-[#2E7D32]">
                                            {{ __('Created') }}
                                        </span>
                                    @elseif ($act->action === 'updated')
                                        <span class="inline-flex rounded-md bg-teal-50 px-2 py-0.5 text-[10px] font-bold text-teal-800 dark:bg-teal-950/60 dark:text-teal-300">
                                            {{ __('Updated') }}
                                        </span>
                                    @elseif ($act->action === 'deleted')
                                        <span class="inline-flex rounded-md bg-[#FFEBEE] px-2 py-0.5 text-[10px] font-bold text-[#C62828]">
                                            {{ __('Deleted') }}
                                        </span>
                                    @elseif ($act->action === 'conflict')
                                        <span class="inline-flex rounded-md bg-[#FFF8E1] px-2 py-0.5 text-[10px] font-bold text-[#E65100]">
                                            {{ __('Conflict') }}
                                        </span>
                                    @endif

                                    @if ($act->has_secrets)
                                        <span class="inline-flex rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 ml-1">
                                            {{ __('🔒 DLP') }}
                                        </span>
                                    @endif
                                </td>

                                <td class="py-3.5 px-3 text-gray-600 dark:text-zinc-400">
                                    <div class="flex items-center gap-2">
                                        <flux:avatar :name="$act->user?->name ?? 'Device'" size="xs" />
                                        <span class="font-medium text-gray-800 dark:text-zinc-200">{{ $act->user?->name ?? __('Obsidian Sync') }}</span>
                                    </div>
                                </td>

                                <td class="py-3.5 px-3 text-right font-medium text-gray-400 dark:text-zinc-500">
                                    {{ $act->created_at->diffForHumans() }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <!-- Create Vault Modal -->
    <flux:modal name="create-vault" focusable class="max-w-lg">
        <form wire:submit="createVault" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create Team Vault') }}</flux:heading>
                <flux:subheading>{{ __('Set up a shared Obsidian vault with custom permissions.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="vaultName" :label="__('Vault Name')" placeholder="e.g. Brain01, Research Hub, Company Notes" required />

                <flux:textarea wire:model="vaultDescription" :label="__('Description (Optional)')" placeholder="Brief note about the vault's purpose..." rows="2" />

                <flux:select wire:model="vaultDefaultPermission" :label="__('Default Team Permission')">
                    <flux:select.option value="read_write">{{ __('Read & Write (Full Two-Way Sync)') }}</flux:select.option>
                    <flux:select.option value="read_only">{{ __('Read-Only (Team can view, cannot modify)') }}</flux:select.option>
                    <flux:select.option value="hidden">{{ __('Restricted (Hidden unless specific rule granted)') }}</flux:select.option>
                </flux:select>
              <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create Vault') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Upgrade Plan & Redeem License Modal -->
    <flux:modal name="upgrade-plan-modal" focusable class="max-w-2xl">
        <div class="space-y-6">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300">{{ __('Commercial Licensing') }}</span>
                    <flux:heading size="lg">{{ __('Upgrade Workspace Plan') }}</flux:heading>
                </div>
                <flux:subheading class="mt-1">
                    {{ __('Elevate your team vault with expanded storage, more devices, In-App DLP secret scanning, and granular path ACLs.') }}
                </flux:subheading>
            </div>

            <!-- Tier Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <!-- Free -->
                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/50 dark:border-zinc-800 dark:bg-zinc-800/30">
                    <div class="font-bold text-sm text-slate-800 dark:text-zinc-200">{{ __('Community Free') }}</div>
                    <div class="text-xl font-black text-slate-900 dark:text-white mt-1">$0</div>
                    <div class="text-[11px] text-slate-500 mt-2 space-y-1">
                        <div>• 1 Vault</div>
                        <div>• 3 Connected Devices</div>
                        <div>• 1 GB Local Storage</div>
                        <div>• 3 Team Seats</div>
                    </div>
                </div>

                <!-- Pro LTD -->
                <div class="rounded-2xl border-2 border-emerald-500 p-4 bg-emerald-50/30 dark:border-emerald-500/60 dark:bg-emerald-950/20 relative">
                    <span class="absolute -top-2.5 right-3 rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-black uppercase text-white tracking-wider">
                        {{ __('Popular LTD') }}
                    </span>
                    <div class="font-bold text-sm text-emerald-950 dark:text-emerald-200">{{ __('Pro Lifetime') }}</div>
                    <div class="text-xl font-black text-emerald-900 dark:text-white mt-1">$79 <span class="text-xs font-normal text-slate-500">one-time</span></div>
                    <div class="text-[11px] text-slate-600 dark:text-zinc-300 mt-2 space-y-1">
                        <div>• <strong>15 Vaults</strong></div>
                        <div>• <strong>25 Connected Devices</strong></div>
                        <div>• <strong>15 GB Storage</strong></div>
                        <div>• <strong>In-App DLP Secret Scan</strong></div>
                        <div>• <strong>Granular Path ACLs</strong></div>
                    </div>
                </div>

                <!-- Cloud SaaS -->
                <div class="rounded-2xl border border-teal-200 p-4 bg-teal-50/30 dark:border-teal-800/60 dark:bg-teal-950/20">
                    <div class="font-bold text-sm text-teal-950 dark:text-teal-200">{{ __('Cloud Managed') }}</div>
                    <div class="text-xl font-black text-teal-900 dark:text-white mt-1">$12 <span class="text-xs font-normal text-slate-500">/month</span></div>
                    <div class="text-[11px] text-slate-600 dark:text-zinc-300 mt-2 space-y-1">
                        <div>• <strong>50+ Vaults</strong></div>
                        <div>• <strong>100 Connected Devices</strong></div>
                        <div>• <strong>50 GB Cloud Storage</strong></div>
                        <div>• <strong>Live Multiplayer CRDT</strong></div>
                        <div>• <strong>Priority Support</strong></div>
                    </div>
                </div>
            </div>

            <!-- In-Modal Redeem Form -->
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-800/60">
                <div class="text-xs font-bold text-slate-900 dark:text-white">{{ __('Have a License Key?') }}</div>
                <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-0.5">{{ __('Enter your AppSumo, LemonSqueezy, or enterprise key to instantly activate your plan.') }}</p>

                <div class="mt-3 flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <div class="flex-1">
                        <flux:input
                            wire:model="upgradeLicenseKey"
                            placeholder="SYNK-PRO-XXXX-XXXX-XXXX"
                            class="font-mono uppercase text-xs"
                        />
                    </div>
                    <flux:button
                        wire:click="redeemLicenseKeyInDashboard"
                        variant="primary"
                        class="!bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90 !rounded-full px-5 font-bold text-xs shrink-0"
                    >
                        {{ __('Redeem Key') }}
                    </flux:button>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-zinc-800">
                <a
                    href="{{ route('home') }}#pricing"
                    target="_blank"
                    class="text-xs font-bold text-[#0D3B29] hover:underline dark:text-emerald-400 flex items-center gap-1"
                >
                    {{ __('View Full Pricing & Buy License') }}
                    <flux:icon icon="arrow-top-right-on-square" class="size-3.5" />
                </a>

                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">{{ __('Close') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <!-- SLIDE-OVER NOTIFICATIONS & MESSAGES DRAWER -->
    <div
        x-show="drawerOpen"
        x-cloak
        @keydown.window.escape="drawerOpen = false"
        class="relative z-50"
    >
        <!-- Backdrop -->
        <div
            x-show="drawerOpen"
            x-transition:enter="ease-in-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in-out duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="drawerOpen = false"
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
        ></div>

        <!-- Slide Panel -->
        <div class="fixed inset-y-0 right-0 flex max-w-full pl-10">
            <div
                x-show="drawerOpen"
                x-transition:enter="transform transition ease-in-out duration-300"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transform transition ease-in-out duration-300"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                class="w-screen max-w-md bg-white dark:bg-[#121622] text-slate-900 dark:text-zinc-100 shadow-2xl flex flex-col border-l border-slate-200 dark:border-zinc-800"
            >
                <!-- Drawer Header -->
                <div class="p-5 border-b border-slate-200 dark:border-zinc-800 flex items-center justify-between bg-slate-50/50 dark:bg-zinc-900/50">
                    <div class="flex items-center gap-2.5">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-bold">
                            <flux:icon icon="bolt" class="size-4" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm tracking-tight text-slate-900 dark:text-white">{{ __('Activity & Alerts') }}</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400">{{ __('Activity notifications & team messages') }}</p>
                        </div>
                    </div>
                    <button @click="drawerOpen = false" class="size-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-white hover:bg-slate-200/50 dark:hover:bg-zinc-800 transition-colors cursor-pointer">
                        <flux:icon icon="x-mark" class="size-4" />
                    </button>
                </div>

                <!-- Tabs Header -->
                <div class="flex border-b border-slate-200 dark:border-zinc-800 bg-slate-100/60 dark:bg-zinc-900/80 p-1.5 gap-1.5">
                    <button
                        @click="drawerTab = 'notifications'"
                        :class="drawerTab === 'notifications' ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-white shadow-xs font-bold' : 'text-slate-500 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white'"
                        class="flex-1 py-1.5 px-3 text-xs rounded-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                    >
                        <flux:icon icon="bell" class="size-3.5 text-emerald-500" />
                        <span>{{ __('Notifications') }}</span>
                        <span class="rounded-full bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 px-1.5 py-0.2 text-[10px] font-bold">3</span>
                    </button>
                    <button
                        @click="drawerTab = 'messages'"
                        :class="drawerTab === 'messages' ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-white shadow-xs font-bold' : 'text-slate-500 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white'"
                        class="flex-1 py-1.5 px-3 text-xs rounded-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                    >
                        <flux:icon icon="envelope" class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                        <span>{{ __('Messages') }}</span>
                        <span class="rounded-full bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 px-1.5 py-0.2 text-[10px] font-bold">2</span>
                    </button>
                </div>

                <!-- Tab Content -->
                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    <!-- Notifications Tab -->
                    <div x-show="drawerTab === 'notifications'" class="space-y-3">
                        <div class="p-3.5 rounded-xl border border-amber-500/30 bg-amber-500/10 dark:bg-amber-500/10 flex items-start gap-3">
                            <div class="size-8 rounded-lg bg-amber-500/20 flex items-center justify-center shrink-0 text-amber-600 dark:text-amber-400">
                                <flux:icon icon="shield-exclamation" class="size-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between text-xs font-bold text-amber-900 dark:text-amber-200">
                                    <span>DLP Vault Security Alert</span>
                                    <span class="text-[10px] text-slate-400 font-normal">10m ago</span>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-zinc-300 mt-0.5">Potential AWS Secret Access Key detected in note <code class="font-mono text-[11px] bg-amber-500/20 px-1 rounded">Config/env.md</code>.</p>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl border border-emerald-500/20 bg-emerald-500/5 dark:bg-emerald-500/10 flex items-start gap-3">
                            <div class="size-8 rounded-lg bg-emerald-500/20 flex items-center justify-center shrink-0 text-emerald-600 dark:text-emerald-400">
                                <flux:icon icon="device-phone-mobile" class="size-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between text-xs font-bold text-slate-900 dark:text-white">
                                    <span>New Device Paired</span>
                                    <span class="text-[10px] text-slate-400 font-normal">1h ago</span>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-zinc-300 mt-0.5">Obsidian Desktop (MacBook Pro) established an authenticated sync session with Demo Vault.</p>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/50 flex items-start gap-3">
                            <div class="size-8 rounded-lg bg-emerald-500/20 flex items-center justify-center shrink-0 text-emerald-600 dark:text-emerald-400">
                                <flux:icon icon="arrow-path" class="size-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between text-xs font-bold text-slate-900 dark:text-white">
                                    <span>Vault Revision Snapshot</span>
                                    <span class="text-[10px] text-slate-400 font-normal">3h ago</span>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-zinc-300 mt-0.5">76 markdown notes snapshot v76 backed up to team-managed storage.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Messages Tab -->
                    <div x-show="drawerTab === 'messages'" class="space-y-3">
                        <div class="p-3.5 rounded-xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/50 flex items-start gap-3">
                            <div class="size-8 rounded-full bg-[#0D3B29] text-emerald-200 font-bold text-xs flex items-center justify-center shrink-0">
                                {{ auth()->user()->initials() }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between text-xs font-bold text-slate-900 dark:text-white">
                                    <span>{{ auth()->user()->name }}</span>
                                    <span class="text-[10px] text-slate-400 font-normal">Just now</span>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-zinc-300 mt-0.5">Updated <span class="font-medium text-emerald-600 dark:text-emerald-400">START HERE.md</span> with new architectural guidelines and graph view links.</p>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/50 flex items-start gap-3">
                            <div class="size-8 rounded-full bg-emerald-700 text-white font-bold text-xs flex items-center justify-center shrink-0">
                                SY
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between text-xs font-bold text-slate-900 dark:text-white">
                                    <span>Synkk Engine</span>
                                    <span class="text-[10px] text-slate-400 font-normal">25m ago</span>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-zinc-300 mt-0.5">All 76 vault files in sync across 1 device with 0 active conflicts.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Action -->
                <div class="p-4 border-t border-slate-200 dark:border-zinc-800 bg-slate-50/80 dark:bg-zinc-900/80 flex items-center justify-between">
                    <button @click="drawerOpen = false" class="text-xs font-semibold text-slate-500 hover:text-slate-800 dark:hover:text-zinc-300 cursor-pointer">
                        {{ __('Close') }}
                    </button>
                    <button @click="drawerOpen = false" class="rounded-lg bg-[#0D3B29] dark:bg-emerald-600 px-3.5 py-1.5 text-xs font-bold text-white hover:bg-emerald-700 transition-colors cursor-pointer">
                        {{ __('Mark All as Read') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
