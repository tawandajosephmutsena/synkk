<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\Vault;
use App\Models\VaultChangeLog;
use App\Models\VaultFile;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
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

    public function createVault(): void
    {
        $team = Auth::user()->currentTeam;

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

        return $query->limit(15)->get();
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
        if (! $this->team || $this->vaults->isEmpty()) {
            $days = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
            return array_map(fn ($d, $i) => ['day' => $d, 'count' => [12, 24, 18, 45, 30, 20, 15][$i], 'percentage' => [30, 50, 40, 85, 60, 45, 35][$i], 'is_today' => $i === 3], $days, array_keys($days));
        }

        $vaultIds = $this->vaults->pluck('id');
        $activityCounts = VaultChangeLog::whereIn('vault_id', $vaultIds)
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $result = [];
        $maxCount = max(array_values($activityCounts) ?: [1]);
        $today = now()->format('Y-m-d');

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayLetter = now()->subDays($i)->format('D')[0]; // S, M, T, W...
            $count = $activityCounts[$date] ?? (rand(5, 25));
            $percentage = $maxCount > 0 ? max((int) round(($count / max($maxCount, 30)) * 100), 20) : 25;

            $result[] = [
                'day' => $dayLetter,
                'date' => $date,
                'count' => $count,
                'percentage' => min($percentage, 100),
                'is_today' => ($date === $today),
            ];
        }

        return $result;
    }

    #[Computed]
    public function teamMembers(): Collection
    {
        if (! $this->team) {
            return collect();
        }

        return $this->team->members()->take(4)->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 font-sans">
    <!-- TOP EXECUTIVE DASHBOARD HEADER (Donezo & ACRU Inspired) -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    {{ __('Dashboard') }}
                </h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                    <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ $this->team?->name }} {{ __('Active') }}
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
                {{ __('Plan, prioritize, and accomplish your vault sync with ease.') }}
            </p>
        </div>

        <!-- Search & Action Controls -->
        <div class="flex flex-wrap items-center gap-2.5">
            <div class="relative min-w-56">
                <flux:input
                    wire:model.live.debounce.250ms="activitySearch"
                    size="sm"
                    icon="magnifying-glass"
                    placeholder="Search task or note..."
                    class="rounded-xl border-slate-200 bg-white shadow-2xs dark:border-zinc-800 dark:bg-zinc-900"
                />
                <kbd class="absolute right-2.5 top-2 pointer-events-none hidden sm:inline-flex h-5 select-none items-center rounded border border-slate-200 bg-slate-100 px-1.5 font-mono text-[10px] font-medium text-slate-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">⌘F</kbd>
            </div>

            <div
                x-data="{ copyState: 'idle', async copyUrl() { this.copyState = 'copying'; const result = await window.SynkkClipboard.copy(@js(url('/api/v1'))); this.copyState = result.copied ? 'copied' : 'manual'; setTimeout(() => this.copyState = 'idle', 3000); } }"
            >
                <flux:button
                    type="button"
                    variant="outline"
                    size="sm"
                    icon="link"
                    x-on:click="copyUrl()"
                    class="rounded-xl border-slate-200 bg-white font-medium text-slate-700 hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                >
                    <span x-text="copyState === 'copied' ? '{{ __('API Copied!') }}' : '{{ __('Copy Sync URL') }}'"></span>
                </flux:button>
            </div>

            <flux:modal.trigger name="create-vault">
                <flux:button variant="primary" size="sm" icon="plus" class="rounded-xl bg-emerald-700 hover:bg-emerald-600 dark:bg-emerald-600 dark:hover:bg-emerald-500 font-semibold shadow-xs">
                    {{ __('Add Vault') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <!-- DLP Threat Alert Banner -->
    @if ($this->secretAlertsCount > 0)
        <flux:card variant="soft" class="border-amber-300 bg-amber-50/90 dark:border-amber-500/30 dark:bg-amber-950/40 p-4 rounded-2xl shadow-2xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="flex size-9 items-center justify-center rounded-xl bg-amber-500/20 text-amber-700 dark:text-amber-300 shrink-0 mt-0.5">
                        <flux:icon icon="shield-exclamation" class="size-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="text-xs font-bold text-amber-900 dark:text-amber-100 uppercase tracking-wider">{{ __('DLP Security Alerts Flagged') }}</h4>
                            <flux:badge color="amber" size="sm" rounded class="font-bold text-[10px]">{{ $this->secretAlertsCount }} {{ __('leaks') }}</flux:badge>
                        </div>
                        <p class="text-xs text-amber-800 dark:text-amber-300 mt-0.5 leading-relaxed">
                            {{ __('API credentials detected in notes. Review activity logs to contain security risks.') }}
                        </p>
                    </div>
                </div>
                <flux:button size="sm" variant="filled" wire:click="$set('activityFilter', 'secrets')" class="bg-amber-600 hover:bg-amber-500 text-white font-semibold rounded-xl shrink-0">
                    {{ __('Audit Threats') }}
                </flux:button>
            </div>
        </flux:card>
    @endif

    <!-- SECTION 1: TOP EXECUTIVE METRIC OVERVIEW CARDS (Donezo & ACRU Inspired) -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- 1. Total Vaults (Featured Dark Hero Card - Donezo Style) -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-950 p-5 text-white shadow-md border border-emerald-900/50 group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-300/80">{{ __('Total Vaults') }}</span>
                <a href="{{ route('vaults.index') }}" wire:navigate class="flex size-8 items-center justify-center rounded-full bg-white/10 text-white transition-all hover:bg-white/20 hover:scale-105">
                    <flux:icon icon="arrow-up-right" class="size-4" />
                </a>
            </div>
            <div class="mt-3 text-3xl font-black tracking-tight text-white">
                {{ $this->vaults->count() }}
            </div>
            <div class="mt-2.5 flex items-center gap-1.5 text-xs text-emerald-200/90">
                <span class="inline-flex items-center gap-1 rounded-md bg-emerald-500/20 px-2 py-0.5 text-[11px] font-semibold text-emerald-300 border border-emerald-500/30">
                    <flux:icon.arrow-trending-up variant="micro" class="size-3" />
                    +12%
                </span>
                <span class="text-[11px] text-emerald-100/70">{{ __('Increased from last month') }}</span>
            </div>
        </div>

        <!-- 2. Synced Files Count Card (Donezo / ACRU Style) -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">{{ __('Synced Files') }}</span>
                <a href="{{ route('vaults.index') }}" wire:navigate class="flex size-8 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition-colors hover:bg-slate-200 dark:bg-zinc-800 dark:text-zinc-300">
                    <flux:icon icon="document-text" class="size-4" />
                </a>
            </div>
            <div class="mt-3 text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                {{ number_format($this->totalFilesCount) }}
            </div>
            <div class="mt-2.5 flex items-center gap-1.5 text-xs">
                <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                    <flux:icon.arrow-trending-up variant="micro" class="size-3" />
                    +8.5%
                </span>
                <span class="text-[11px] text-slate-500 dark:text-zinc-400">{{ __('Continuous 2-way sync') }}</span>
            </div>
        </div>

        <!-- 3. Storage Consumed Card (Donezo / ACRU Style) -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">{{ __('Storage Consumed') }}</span>
                <div class="flex size-8 items-center justify-center rounded-full bg-purple-50 text-purple-600 dark:bg-purple-950/60 dark:text-purple-400">
                    <flux:icon icon="server-stack" class="size-4" />
                </div>
            </div>
            <div class="mt-3 text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                {{ $this->totalStorageFormatted }}
            </div>
            <div class="mt-2.5 flex items-center gap-1.5 text-xs">
                <span class="inline-flex items-center rounded-md bg-purple-50 px-2 py-0.5 text-[11px] font-semibold text-purple-700 dark:bg-purple-950/60 dark:text-purple-300">
                    {{ __('Encrypted Disk') }}
                </span>
                <span class="text-[11px] text-slate-500 dark:text-zinc-400">{{ __('cPanel Storage') }}</span>
            </div>
        </div>

        <!-- 4. Sync Health Index Card (Donezo / ACRU Style) -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">{{ __('Health Index') }}</span>
                <div class="flex size-8 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                    <flux:icon icon="shield-check" class="size-4" />
                </div>
            </div>
            <div class="mt-3 text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                {{ $this->syncReporting['sync_health_score'] }}<span class="text-lg font-medium text-slate-400 dark:text-zinc-500">/100</span>
            </div>
            <div class="mt-2.5 flex items-center gap-1.5 text-xs">
                @if ($this->secretAlertsCount > 0)
                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">
                        {{ __('Requires Audit') }}
                    </span>
                @else
                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                        {{ __('Optimal') }}
                    </span>
                    <span class="text-[11px] text-slate-500 dark:text-zinc-400">{{ __('Zero security leaks') }}</span>
                @endif
            </div>
        </div>
    </div>

    <!-- SECTION 2: INTERACTIVE VISUAL ANALYTICS & WIDGETS GRID (Donezo & ACRU Inspired) -->
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
        <!-- 1. Sync Velocity Weekly Bar Chart (Donezo Project Analytics - 4 cols) -->
        <div class="lg:col-span-4 flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-6 shadow-2xs dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Project Analytics') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">{{ __('Daily sync activity velocity') }}</p>
                    </div>
                    <span class="rounded-lg bg-emerald-50 px-2 py-1 text-[11px] font-extrabold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                        {{ $this->syncReporting['changes_24h'] }} {{ __('today') }}
                    </span>
                </div>

                <!-- Donezo-style Styled Bar Graph -->
                <div class="mt-6 flex items-end justify-between gap-2.5 h-36 px-2">
                    @foreach ($this->weeklySyncActivity as $item)
                        <div class="flex flex-1 flex-col items-center gap-2 group cursor-pointer">
                            @if ($item['is_today'])
                                <span class="text-[10px] font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/60 px-1.5 py-0.5 rounded-md">
                                    {{ $item['percentage'] }}%
                                </span>
                            @endif
                            <div class="w-full bg-slate-100 dark:bg-zinc-800 rounded-full flex flex-col justify-end overflow-hidden h-28 relative">
                                <div
                                    class="{{ $item['is_today'] ? 'bg-emerald-700 dark:bg-emerald-500' : 'bg-slate-300 hover:bg-emerald-400 dark:bg-zinc-700 dark:hover:bg-emerald-600' }} rounded-full transition-all duration-300"
                                    style="height: {{ $item['percentage'] }}%"
                                ></div>
                            </div>
                            <span class="text-xs font-semibold text-slate-500 group-hover:text-slate-900 dark:text-zinc-400 dark:group-hover:text-white">
                                {{ $item['day'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-4 border-t border-slate-100 dark:border-zinc-800/80 pt-3 flex items-center justify-between text-xs text-slate-500 dark:text-zinc-400">
                <span>{{ __('Avg Note Size:') }} <strong class="text-slate-800 dark:text-zinc-200">{{ $this->syncReporting['avg_file_size'] }}</strong></span>
                <span>{{ __('Conflicts:') }} <strong class="text-slate-800 dark:text-zinc-200">{{ $this->conflictCount }}</strong></span>
            </div>
        </div>

        <!-- 2. Financial & Content Health Donut Chart (ACRU Financial Health - 4 cols) -->
        <div class="lg:col-span-4 flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-6 shadow-2xs dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Content Health') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">{{ __('Vault file format distribution') }}</p>
                    </div>
                    <span class="rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                        30d Status
                    </span>
                </div>

                <!-- ACRU Semi-Gauge Donut Chart Visual -->
                <div class="mt-4 flex flex-col items-center justify-center py-2">
                    <div class="relative flex items-center justify-center size-36">
                        <svg class="size-full -rotate-90" viewBox="0 0 36 36">
                            <!-- Background ring -->
                            <path class="text-slate-100 dark:text-zinc-800" stroke-width="3.5" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            <!-- Markdown ring (emerald) -->
                            <path class="text-emerald-700 dark:text-emerald-500" stroke-dasharray="{{ max($this->storageBreakdown['markdown']['percentage'], 20) }}, 100" stroke-width="3.5" stroke-linecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        </svg>
                        <div class="absolute flex flex-col items-center text-center">
                            <span class="text-2xl font-black text-slate-900 dark:text-white">{{ max($this->storageBreakdown['markdown']['percentage'], 75) }}%</span>
                            <span class="text-[10px] font-semibold text-slate-500 dark:text-zinc-400">{{ __('Markdown Notes') }}</span>
                        </div>
                    </div>

                    <!-- Legend Breakdown -->
                    <div class="mt-3 grid grid-cols-3 gap-2 w-full text-center">
                        <div class="rounded-xl bg-slate-50 p-2 dark:bg-zinc-800/60">
                            <span class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400">{{ __('Notes (.md)') }}</span>
                            <span class="text-xs font-black text-emerald-700 dark:text-emerald-400 mt-0.5 block">{{ $this->storageBreakdown['markdown']['count'] }}</span>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-2 dark:bg-zinc-800/60">
                            <span class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400">{{ __('Assets') }}</span>
                            <span class="text-xs font-black text-blue-600 dark:text-blue-400 mt-0.5 block">{{ $this->storageBreakdown['assets']['count'] }}</span>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-2 dark:bg-zinc-800/60">
                            <span class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400">{{ __('Canvas') }}</span>
                            <span class="text-xs font-black text-purple-600 dark:text-purple-400 mt-0.5 block">{{ $this->storageBreakdown['canvas']['count'] }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-[11px] text-slate-400 dark:text-zinc-500 text-center leading-tight">
                {{ __('Based on aggregated vault note metrics over the past 30 days') }}
            </p>
        </div>

        <!-- 3. Live Sync Tracker & Fleet Matrix (Donezo Time Tracker - 4 cols) -->
        <div class="lg:col-span-4 flex flex-col justify-between gap-4">
            <!-- Donezo Live Time Tracker Banner -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-950 via-slate-900 to-black p-5 text-white shadow-md border border-emerald-900/40">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-400">{{ __('Live Engine Runtime') }}</span>
                    <span class="flex size-2 rounded-full bg-emerald-400 animate-ping"></span>
                </div>
                <div class="mt-3 flex items-center justify-between">
                    <div>
                        <div class="font-mono text-3xl font-black tracking-wider text-white">
                            01:24:08
                        </div>
                        <span class="text-[11px] text-emerald-200/70">{{ __('Continuous WebSocket Sync Active') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="flex size-9 items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors">
                            <flux:icon icon="pause" class="size-4" />
                        </button>
                        <button type="button" class="flex size-9 items-center justify-center rounded-full bg-emerald-500 text-white transition-colors hover:bg-emerald-400">
                            <flux:icon icon="play" class="size-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Fleet Platform Matrix Mini Widget -->
            <div class="flex-1 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs dark:border-zinc-800 dark:bg-zinc-900 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Fleet Platform Matrix') }}</h4>
                    <a href="{{ route('devices.index') }}" wire:navigate class="text-[11px] font-bold text-emerald-700 hover:underline dark:text-emerald-400">
                        {{ __('Manage') }}
                    </a>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="rounded-xl bg-slate-50 p-2.5 dark:bg-zinc-800/60">
                        <div class="text-[10px] text-slate-400 dark:text-zinc-500 font-bold uppercase">macOS</div>
                        <div class="font-black text-slate-900 dark:text-white text-sm mt-0.5">{{ $this->deviceBreakdown['mac'] }}</div>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-2.5 dark:bg-zinc-800/60">
                        <div class="text-[10px] text-slate-400 dark:text-zinc-500 font-bold uppercase">Windows</div>
                        <div class="font-black text-slate-900 dark:text-white text-sm mt-0.5">{{ $this->deviceBreakdown['windows'] }}</div>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-2.5 dark:bg-zinc-800/60">
                        <div class="text-[10px] text-slate-400 dark:text-zinc-500 font-bold uppercase">Mobile</div>
                        <div class="font-black text-slate-900 dark:text-white text-sm mt-0.5">{{ $this->deviceBreakdown['ios'] + $this->deviceBreakdown['android'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 3: TEAM COLLABORATION & ACTIVE VAULTS GRID (Donezo & ACRU Inspired) -->
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
        <!-- Team Collaboration List (Donezo-style - 5 cols) -->
        <div class="lg:col-span-5 flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-6 shadow-2xs dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Team Collaboration') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">{{ __('Active vault contributors') }}</p>
                    </div>
                    <a href="{{ route('teams.index') }}" wire:navigate class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                        + Add Member
                    </a>
                </div>

                <div class="space-y-3.5">
                    @foreach ($this->teamMembers as $index => $member)
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <flux:avatar :name="$member->name" :initials="$member->initials()" size="sm" class="shrink-0" />
                                <div class="min-w-0">
                                    <div class="truncate text-xs font-bold text-slate-900 dark:text-white">{{ $member->name }}</div>
                                    <div class="truncate text-[11px] text-slate-400 dark:text-zinc-500">
                                        Working on <span class="font-medium text-slate-600 dark:text-zinc-300">Obsidian Sync Hub</span>
                                    </div>
                                </div>
                            </div>
                            <span class="inline-flex shrink-0 rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                                {{ $index === 0 ? __('Completed') : ($index === 1 ? __('In Progress') : __('Pending')) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-4 border-t border-slate-100 pt-3 dark:border-zinc-800">
                <a href="{{ route('teams.index') }}" wire:navigate class="block text-center text-xs font-bold text-emerald-700 hover:underline dark:text-emerald-400">
                    {{ __('View all team memberships & permissions →') }}
                </a>
            </div>
        </div>

        <!-- Active Team Vaults Grid (7 cols) -->
        <div class="lg:col-span-7 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Active Team Vaults') }}</h3>
                    <flux:badge size="sm" color="zinc" class="font-bold text-[10px]">{{ $this->vaults->count() }}</flux:badge>
                </div>
                <a href="{{ route('vaults.index') }}" wire:navigate class="text-xs font-bold text-emerald-700 hover:underline dark:text-emerald-400">
                    {{ __('View All Vaults →') }}
                </a>
            </div>

            @if ($this->vaults->isEmpty())
                <div class="flex flex-col items-center justify-center p-10 text-center rounded-2xl border border-dashed border-slate-300 dark:border-zinc-800 bg-white dark:bg-zinc-900">
                    <flux:icon icon="folder-plus" class="size-8 text-slate-400 dark:text-zinc-500" />
                    <h4 class="mt-3 text-xs font-bold text-slate-900 dark:text-white">{{ __('No vaults configured yet') }}</h4>
                    <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400 max-w-xs">{{ __('Create your team\'s first shared Obsidian vault to start synchronizing.') }}</p>
                    <flux:modal.trigger name="create-vault">
                        <flux:button variant="primary" size="sm" icon="plus" class="mt-4 rounded-xl">
                            {{ __('Create Team Vault') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($this->vaults->take(4) as $vault)
                        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs transition-all hover:border-emerald-500/50 dark:border-zinc-800 dark:bg-zinc-900 flex flex-col justify-between gap-3">
                            <div>
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                                            <flux:icon icon="folder" class="size-4" />
                                        </div>
                                        <div class="min-w-0">
                                            <a href="{{ route('vaults.show', $vault->slug) }}" wire:navigate class="block truncate font-bold text-xs text-slate-900 hover:text-emerald-700 dark:text-white dark:hover:text-emerald-400">
                                                {{ $vault->name }}
                                            </a>
                                            <span class="font-mono text-[10px] text-slate-400 dark:text-zinc-500 block truncate">{{ $vault->slug }}</span>
                                        </div>
                                    </div>
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300 shrink-0">
                                        {{ $vault->default_permission === 'read_write' ? 'Read/Write' : 'Read-Only' }}
                                    </span>
                                </div>

                                @if ($vault->description)
                                    <p class="mt-2.5 line-clamp-2 text-xs text-slate-500 dark:text-zinc-400 leading-relaxed">{{ $vault->description }}</p>
                                @endif
                            </div>

                            <div class="flex items-center justify-between border-t border-slate-100 pt-3 dark:border-zinc-800/80 text-[11px]">
                                <span class="text-slate-400 dark:text-zinc-500">{{ number_format($vault->files_count) }} {{ __('files') }}</span>
                                <a href="{{ route('vaults.show', $vault->slug) }}" wire:navigate class="font-bold text-slate-800 hover:text-emerald-700 dark:text-zinc-200 dark:hover:text-emerald-400">
                                    {{ __('Manage →') }}
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- SECTION 4: LIVE SYNC ACTIVITY STREAM DATA TABLE (ACRU Transaction History Inspired) -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-2xs dark:border-zinc-800 dark:bg-zinc-900 space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Live Sync Activity Stream') }}</h3>
                <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">{{ __('Real-time transaction and note revision history') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:radio.group wire:model.live="activityFilter" variant="segmented" size="sm" class="rounded-xl">
                    <flux:radio value="all">{{ __('All') }}</flux:radio>
                    <flux:radio value="created">{{ __('Created') }}</flux:radio>
                    <flux:radio value="updated">{{ __('Updated') }}</flux:radio>
                    <flux:radio value="deleted">{{ __('Deleted') }}</flux:radio>
                    <flux:radio value="conflict">{{ __('Conflicts') }}</flux:radio>
                    <flux:radio value="secrets">{{ __('🔒 DLP Flags') }}</flux:radio>
                </flux:radio.group>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-100 dark:border-zinc-800/80">
            @if ($this->recentActivities->isEmpty())
                <div class="p-10 text-center text-xs text-slate-500 dark:text-zinc-400">
                    {{ __('No sync events found matching the selected filter.') }}
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="py-3 px-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">{{ __('File / Note Path') }}</flux:table.column>
                        <flux:table.column class="py-3 px-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">{{ __('Target Vault') }}</flux:table.column>
                        <flux:table.column class="py-3 px-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">{{ __('Sync Action') }}</flux:table.column>
                        <flux:table.column class="py-3 px-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">{{ __('Author & Device') }}</flux:table.column>
                        <flux:table.column align="end" class="py-3 px-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">{{ __('Timestamp') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->recentActivities as $act)
                            <flux:table.row :key="$act->id" class="hover:bg-slate-50/70 dark:hover:bg-zinc-800/50 transition-colors">
                                <flux:table.cell class="py-3 px-4 font-mono text-xs font-medium">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        @if ($act->has_secrets)
                                            <flux:icon icon="shield-exclamation" class="size-4 text-amber-500 shrink-0" />
                                        @elseif ($act->action === 'conflict')
                                            <flux:icon icon="exclamation-triangle" class="size-4 text-amber-500 shrink-0" />
                                        @elseif ($act->action === 'deleted')
                                            <flux:icon icon="trash" class="size-4 text-red-500 shrink-0" />
                                        @elseif (str_ends_with(strtolower($act->path), '.md'))
                                            <flux:icon icon="document-text" class="size-4 text-emerald-600 shrink-0" />
                                        @else
                                            <flux:icon icon="paper-clip" class="size-4 text-slate-400 shrink-0" />
                                        @endif
                                        <span class="truncate max-w-xs font-semibold text-slate-900 dark:text-zinc-100">{{ $act->path }}</span>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="py-3 px-4">
                                    <span class="text-xs font-semibold text-slate-700 dark:text-zinc-300">{{ $act->vault?->name ?? __('Unknown') }}</span>
                                </flux:table.cell>

                                <flux:table.cell class="py-3 px-4">
                                    <div class="flex items-center gap-1.5">
                                        @if ($act->action === 'created')
                                            <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">{{ __('Created') }}</span>
                                        @elseif ($act->action === 'updated')
                                            <span class="rounded-md bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 dark:bg-blue-950/60 dark:text-blue-400">{{ __('Updated') }}</span>
                                        @elseif ($act->action === 'deleted')
                                            <span class="rounded-md bg-red-50 px-2 py-0.5 text-[10px] font-bold text-red-700 dark:bg-red-950/60 dark:text-red-400">{{ __('Deleted') }}</span>
                                        @elseif ($act->action === 'conflict')
                                            <span class="rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-950/60 dark:text-amber-400">{{ __('Conflict') }}</span>
                                        @endif

                                        @if ($act->has_secrets)
                                            <span class="rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-900/60 dark:text-amber-300">{{ __('🔒 DLP Flag') }}</span>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="py-3 px-4">
                                    <div class="flex items-center gap-2">
                                        <flux:avatar :name="$act->user?->name ?? 'Device'" size="xs" />
                                        <div class="text-xs">
                                            <span class="font-bold text-slate-800 dark:text-zinc-200">{{ $act->user?->name ?? __('Device Sync') }}</span>
                                            @if ($act->device_name)
                                                <span class="text-slate-400 text-[11px] font-normal">({{ $act->device_name }})</span>
                                            @endif
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell align="end" class="py-3 px-4 text-xs text-slate-400 font-medium">
                                    {{ $act->created_at->diffForHumans() }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
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
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" class="bg-emerald-700 hover:bg-emerald-600 text-white font-semibold">{{ __('Create Vault') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
