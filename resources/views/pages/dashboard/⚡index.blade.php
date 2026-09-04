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
    public function teamMembers(): Collection
    {
        if (! $this->team) {
            return collect();
        }

        return $this->team->members()->take(4)->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-7 font-sans text-slate-900 dark:text-slate-100">
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
            <button type="button" class="flex size-10 items-center justify-center rounded-full border border-gray-200/90 bg-white text-gray-600 shadow-[0_2px_6px_rgba(0,0,0,0.02)] transition-colors hover:bg-gray-50 hover:text-gray-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800">
                <flux:icon icon="envelope" class="size-4" />
            </button>

            <!-- Notification Bell -->
            <button type="button" class="relative flex size-10 items-center justify-center rounded-full border border-gray-200/90 bg-white text-gray-600 shadow-[0_2px_6px_rgba(0,0,0,0.02)] transition-colors hover:bg-gray-50 hover:text-gray-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800">
                <flux:icon icon="bell" class="size-4" />
                @if ($this->secretAlertsCount > 0)
                    <span class="absolute top-2 right-2 size-2 rounded-full bg-amber-500 ring-2 ring-white dark:ring-zinc-900"></span>
                @else
                    <span class="absolute top-2 right-2 size-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-zinc-900"></span>
                @endif
            </button>

            <!-- User Profile Chip (Donezo Style) -->
            <div class="flex items-center gap-3 rounded-full border border-gray-200/90 bg-white py-1.5 pl-2 pr-4 shadow-[0_2px_6px_rgba(0,0,0,0.02)] dark:border-zinc-800 dark:bg-zinc-900">
                <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" size="sm" class="size-8 rounded-full" />
                <div class="text-left leading-tight hidden sm:block">
                    <div class="text-xs font-bold text-gray-900 dark:text-white">{{ auth()->user()->name }}</div>
                    <div class="text-[11px] text-gray-400 dark:text-zinc-400">{{ auth()->user()->email }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- DASHBOARD TITLE & PRIMARY ACTIONS (Donezo Style) -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between pt-1">
        <div>
            <h1 class="text-3xl font-black tracking-tight text-gray-900 dark:text-white">
                {{ __('Dashboard') }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">
                {{ __('Plan, prioritize, and accomplish your tasks with ease.') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <flux:modal.trigger name="create-vault">
                <button type="button" class="inline-flex items-center gap-2 rounded-full bg-[#0D3B29] px-6 py-2.5 text-sm font-semibold text-white shadow-xs transition-colors hover:bg-[#09261b] active:scale-98 dark:bg-emerald-700 dark:hover:bg-emerald-600">
                    <flux:icon icon="plus" class="size-4" />
                    <span>{{ __('Add Project') }}</span>
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

    <!-- SECTION 1: THE 4 SIGNATURE METRIC CARDS (Exact Donezo Re-creation!) -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Card 1: Total Projects (THE SIGNATURE DEEP FOREST GREEN HERO CARD) -->
        <div class="flex flex-col justify-between rounded-3xl bg-[#0D3B29] p-6 text-white shadow-sm min-h-[175px] dark:bg-[#0B3122]">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-white/90">{{ __('Total Projects') }}</span>
                <a href="{{ route('vaults.index') }}" wire:navigate class="flex size-8 items-center justify-center rounded-full bg-white/15 text-white transition-all hover:bg-white/25 hover:scale-105">
                    <flux:icon icon="arrow-up-right" class="size-4" />
                </a>
            </div>
            <div class="my-2 text-5xl font-black tracking-tight text-white">
                {{ max($this->vaults->count(), 24) }}
            </div>
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-md bg-white/10 px-2 py-0.5 text-xs font-medium text-emerald-200 border border-white/10">
                    <span class="font-bold">5</span>
                    <flux:icon.arrow-trending-up variant="micro" class="size-3" />
                    {{ __('Increased from last month') }}
                </span>
            </div>
        </div>

        <!-- Card 2: Ended Projects (Donezo Crisp White Style) -->
        <div class="flex flex-col justify-between rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] min-h-[175px] dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ __('Ended Projects') }}</span>
                <a href="{{ route('vaults.index') }}" wire:navigate class="flex size-8 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-700 transition-all hover:bg-gray-50 hover:scale-105 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                    <flux:icon icon="arrow-up-right" class="size-4" />
                </a>
            </div>
            <div class="my-2 text-5xl font-black tracking-tight text-gray-900 dark:text-white">
                10
            </div>
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2 py-0.5 text-xs font-medium text-gray-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                    <span class="font-bold">6</span>
                    <flux:icon.arrow-trending-up variant="micro" class="size-3 text-emerald-600" />
                    {{ __('Increased from last month') }}
                </span>
            </div>
        </div>

        <!-- Card 3: Running Projects (Donezo Crisp White Style) -->
        <div class="flex flex-col justify-between rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] min-h-[175px] dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ __('Running Projects') }}</span>
                <a href="{{ route('vaults.index') }}" wire:navigate class="flex size-8 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-700 transition-all hover:bg-gray-50 hover:scale-105 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                    <flux:icon icon="arrow-up-right" class="size-4" />
                </a>
            </div>
            <div class="my-2 text-5xl font-black tracking-tight text-gray-900 dark:text-white">
                12
            </div>
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2 py-0.5 text-xs font-medium text-gray-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                    <span class="font-bold">2</span>
                    <flux:icon.arrow-trending-up variant="micro" class="size-3 text-emerald-600" />
                    {{ __('Increased from last month') }}
                </span>
            </div>
        </div>

        <!-- Card 4: Pending Project (Donezo Crisp White Style) -->
        <div class="flex flex-col justify-between rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] min-h-[175px] dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ __('Pending Project') }}</span>
                <a href="{{ route('devices.index') }}" wire:navigate class="flex size-8 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-700 transition-all hover:bg-gray-50 hover:scale-105 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                    <flux:icon icon="arrow-up-right" class="size-4" />
                </a>
            </div>
            <div class="my-2 text-5xl font-black tracking-tight text-gray-900 dark:text-white">
                2
            </div>
            <div>
                <span class="text-xs font-medium text-gray-400 dark:text-zinc-500">
                    {{ __('On Discuss') }}
                </span>
            </div>
        </div>
    </div>

    <!-- SECTION 2: MIDDLE ROW (Project Analytics | Reminders | Projects List) -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <!-- 1. Project Analytics (Donezo Style Striped Bar Graph - 5 cols) -->
        <div class="lg:col-span-5 flex flex-col justify-between rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <h3 class="text-base font-extrabold text-gray-900 dark:text-white">{{ __('Project Analytics') }}</h3>

                <!-- 7 Daily Thick Pill Bars (Donezo Striped & Filled Style) -->
                <div class="mt-7 flex items-end justify-between gap-3 h-40 px-1">
                    <!-- Sunday (Striped) -->
                    <div class="flex flex-1 flex-col items-center gap-2.5">
                        <div class="w-full h-28 rounded-full bg-striped-pattern bg-gray-100 dark:bg-zinc-800 border border-gray-200/70 dark:border-zinc-700"></div>
                        <span class="text-xs font-bold text-gray-400 dark:text-zinc-500">S</span>
                    </div>

                    <!-- Monday (Striped) -->
                    <div class="flex flex-1 flex-col items-center gap-2.5">
                        <div class="w-full h-32 rounded-full bg-striped-pattern bg-gray-100 dark:bg-zinc-800 border border-gray-200/70 dark:border-zinc-700"></div>
                        <span class="text-xs font-bold text-gray-400 dark:text-zinc-500">M</span>
                    </div>

                    <!-- Tuesday (Deep Green Medium) -->
                    <div class="flex flex-1 flex-col items-center gap-2.5">
                        <div class="w-full h-36 rounded-full bg-[#1C5B3E] dark:bg-emerald-600"></div>
                        <span class="text-xs font-bold text-gray-400 dark:text-zinc-500">T</span>
                    </div>

                    <!-- Wednesday (ACTIVE HIGHLIGHT with 74% Tooltip) -->
                    <div class="flex flex-1 flex-col items-center gap-2.5 relative">
                        <!-- Floating Pill Tooltip -->
                        <div class="absolute -top-7 flex flex-col items-center">
                            <span class="rounded-full bg-[#E8F5E9] px-2 py-0.5 text-[11px] font-black text-[#0D3B29] border border-[#C8E6C9] shadow-xs">
                                74%
                            </span>
                            <div class="size-0 border-x-4 border-x-transparent border-t-4 border-t-[#C8E6C9]"></div>
                        </div>
                        <div class="w-full h-40 rounded-full bg-[#0D3B29] dark:bg-emerald-500 shadow-sm"></div>
                        <span class="text-xs font-bold text-gray-900 dark:text-white">W</span>
                    </div>

                    <!-- Thursday (Deep Green) -->
                    <div class="flex flex-1 flex-col items-center gap-2.5">
                        <div class="w-full h-40 rounded-full bg-[#0D3B29] dark:bg-emerald-600"></div>
                        <span class="text-xs font-bold text-gray-400 dark:text-zinc-500">T</span>
                    </div>

                    <!-- Friday (Striped) -->
                    <div class="flex flex-1 flex-col items-center gap-2.5">
                        <div class="w-full h-28 rounded-full bg-striped-pattern bg-gray-100 dark:bg-zinc-800 border border-gray-200/70 dark:border-zinc-700"></div>
                        <span class="text-xs font-bold text-gray-400 dark:text-zinc-500">F</span>
                    </div>

                    <!-- Saturday (Striped) -->
                    <div class="flex flex-1 flex-col items-center gap-2.5">
                        <div class="w-full h-32 rounded-full bg-striped-pattern bg-gray-100 dark:bg-zinc-800 border border-gray-200/70 dark:border-zinc-700"></div>
                        <span class="text-xs font-bold text-gray-400 dark:text-zinc-500">S</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Reminders Card (Donezo Style - 3 cols) -->
        <div class="lg:col-span-3 flex flex-col justify-between rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <h3 class="text-base font-extrabold text-gray-900 dark:text-white">{{ __('Reminders') }}</h3>
                <div class="mt-5">
                    <h4 class="text-lg font-black leading-tight text-[#0D3B29] dark:text-emerald-400">
                        {{ __('Meeting with Arc Company') }}
                    </h4>
                    <p class="mt-2 text-xs font-medium text-gray-400 dark:text-zinc-400">
                        {{ __('Time : 02.00 pm - 04.00 pm') }}
                    </p>
                </div>
            </div>

            <button type="button" class="mt-6 flex w-full items-center justify-center gap-2 rounded-full bg-[#0D3B29] py-3.5 px-4 text-xs font-bold text-white shadow-xs transition-colors hover:bg-[#09261b] active:scale-98 dark:bg-emerald-700 dark:hover:bg-emerald-600">
                <flux:icon icon="video-camera" class="size-4" />
                <span>{{ __('Start Meeting') }}</span>
            </button>
        </div>

        <!-- 3. Project Section List (Donezo Style - 4 cols) -->
        <div class="lg:col-span-4 flex flex-col justify-between rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-extrabold text-gray-900 dark:text-white">{{ __('Project') }}</h3>
                    <button type="button" class="rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-bold text-gray-700 hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                        + New
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Project 1 -->
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400 font-black">
                            <span class="text-base font-mono">///</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs font-extrabold text-gray-900 dark:text-white">{{ __('Develop API Endpoints') }}</div>
                            <div class="text-[11px] text-gray-400 dark:text-zinc-500">Due date: Nov 26, 2024</div>
                        </div>
                    </div>

                    <!-- Project 2 -->
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400 font-black">
                            <span class="text-base">◒</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs font-extrabold text-gray-900 dark:text-white">{{ __('Onboarding Flow') }}</div>
                            <div class="text-[11px] text-gray-400 dark:text-zinc-500">Due date: Nov 28, 2024</div>
                        </div>
                    </div>

                    <!-- Project 3 -->
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400 font-black">
                            <span class="text-base">✤</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs font-extrabold text-gray-900 dark:text-white">{{ __('Build Dashboard') }}</div>
                            <div class="text-[11px] text-gray-400 dark:text-zinc-500">Due date: Nov 30, 2024</div>
                        </div>
                    </div>

                    <!-- Project 4 -->
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600 dark:bg-orange-950/60 dark:text-orange-400 font-black">
                            <span class="text-base">◐</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs font-extrabold text-gray-900 dark:text-white">{{ __('Optimize Page Load') }}</div>
                            <div class="text-[11px] text-gray-400 dark:text-zinc-500">Due date: Dec 5, 2024</div>
                        </div>
                    </div>

                    <!-- Project 5 -->
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-950/60 dark:text-purple-400 font-black">
                            <span class="text-base">❖</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs font-extrabold text-gray-900 dark:text-white">{{ __('Cross-Browser Testing') }}</div>
                            <div class="text-[11px] text-gray-400 dark:text-zinc-500">Due date: Dec 6, 2024</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 3: BOTTOM ROW (Team Collaboration | Project Progress | Time Tracker) -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <!-- 1. Team Collaboration (Donezo Style - 5 cols) -->
        <div class="lg:col-span-5 flex flex-col justify-between rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-extrabold text-gray-900 dark:text-white">{{ __('Team Collaboration') }}</h3>
                    <a href="{{ route('teams.index') }}" wire:navigate class="rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-bold text-gray-700 hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                        + Add Member
                    </a>
                </div>

                <div class="space-y-4">
                    <!-- Member 1: Alexandra Deff -->
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-700 text-xs font-black">
                                AD
                            </div>
                            <div class="min-w-0">
                                <div class="truncate text-xs font-extrabold text-gray-900 dark:text-white">Alexandra Deff</div>
                                <div class="truncate text-[11px] text-gray-400 dark:text-zinc-500">Working on <span class="text-gray-600 dark:text-zinc-400">Github Project Repository</span></div>
                            </div>
                        </div>
                        <span class="shrink-0 rounded-md bg-[#E8F5E9] px-2.5 py-0.5 text-[10px] font-bold text-[#2E7D32] border border-[#C8E6C9] dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800">
                            {{ __('Completed') }}
                        </span>
                    </div>

                    <!-- Member 2: Edwin Adenike -->
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 text-xs font-black">
                                EA
                            </div>
                            <div class="min-w-0">
                                <div class="truncate text-xs font-extrabold text-gray-900 dark:text-white">Edwin Adenike</div>
                                <div class="truncate text-[11px] text-gray-400 dark:text-zinc-500">Working on <span class="text-gray-600 dark:text-zinc-400">Integrate User Authentication</span></div>
                            </div>
                        </div>
                        <span class="shrink-0 rounded-md bg-[#FFF8E1] px-2.5 py-0.5 text-[10px] font-bold text-[#E65100] border border-[#FFE082] dark:bg-amber-950/60 dark:text-amber-400 dark:border-amber-800">
                            {{ __('In Progress') }}
                        </span>
                    </div>

                    <!-- Member 3: Isaac Oluwatemilorun -->
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700 text-xs font-black">
                                IO
                            </div>
                            <div class="min-w-0">
                                <div class="truncate text-xs font-extrabold text-gray-900 dark:text-white">Isaac Oluwatemilorun</div>
                                <div class="truncate text-[11px] text-gray-400 dark:text-zinc-500">Working on <span class="text-gray-600 dark:text-zinc-400">Develop Search and Filter Functionality</span></div>
                            </div>
                        </div>
                        <span class="shrink-0 rounded-md bg-[#FFEBEE] px-2.5 py-0.5 text-[10px] font-bold text-[#C62828] border border-[#FFCDD2] dark:bg-rose-950/60 dark:text-rose-400 dark:border-rose-800">
                            {{ __('Pending') }}
                        </span>
                    </div>

                    <!-- Member 4: David Oshodi -->
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 text-xs font-black">
                                DO
                            </div>
                            <div class="min-w-0">
                                <div class="truncate text-xs font-extrabold text-gray-900 dark:text-white">David Oshodi</div>
                                <div class="truncate text-[11px] text-gray-400 dark:text-zinc-500">Working on <span class="text-gray-600 dark:text-zinc-400">Responsive Layout for Homepage</span></div>
                            </div>
                        </div>
                        <span class="shrink-0 rounded-md bg-[#FFF8E1] px-2.5 py-0.5 text-[10px] font-bold text-[#E65100] border border-[#FFE082] dark:bg-amber-950/60 dark:text-amber-400 dark:border-amber-800">
                            {{ __('In Progress') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Project Progress (Donezo Style Semi-Donut Gauge - 3 cols) -->
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
                            <path class="text-[#0D3B29] dark:text-emerald-500" stroke-dasharray="41, 100" stroke-width="4.5" stroke-linecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        </svg>
                        <div class="absolute flex flex-col items-center text-center">
                            <span class="text-3xl font-black text-gray-900 dark:text-white">41%</span>
                            <span class="text-[10px] font-bold text-gray-400 dark:text-zinc-500">{{ __('Project Ended') }}</span>
                        </div>
                    </div>

                    <!-- Legend Items (Donezo Style) -->
                    <div class="mt-4 flex items-center justify-center gap-3 text-[11px] font-bold text-gray-500 dark:text-zinc-400">
                        <span class="flex items-center gap-1">
                            <span class="size-2 rounded-full bg-[#0D3B29] dark:bg-emerald-500"></span>
                            {{ __('Completed') }}
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="size-2 rounded-full bg-[#10B981]"></span>
                            {{ __('In Progress') }}
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="size-2 rounded-full border border-gray-400 bg-striped-pattern"></span>
                            {{ __('Pending') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Time Tracker (Donezo Style Floating Dark Green Hero Card - 4 cols) -->
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
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-300/80">{{ __('Time Tracker') }}</span>
                <div class="mt-4 font-mono text-4xl font-black tracking-wider text-white">
                    01:24:08
                </div>
            </div>

            <!-- Controls (Donezo White Pause & Red Stop Circles) -->
            <div class="mt-6 flex items-center gap-3">
                <!-- White Pause Button -->
                <button type="button" class="flex size-11 items-center justify-center rounded-full bg-white text-gray-900 shadow-sm transition-transform hover:scale-105 active:scale-95">
                    <flux:icon icon="pause" class="size-4 fill-current" />
                </button>

                <!-- Red Stop Button -->
                <button type="button" class="flex size-11 items-center justify-center rounded-full bg-[#E53935] text-white shadow-sm transition-transform hover:scale-105 active:scale-95">
                    <span class="size-3.5 rounded-sm bg-white"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- SECTION 4: ACRU-STYLE REVISION & SYNC ACTIVITY STREAM DATA TABLE -->
    <div class="rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] space-y-4 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-extrabold text-gray-900 dark:text-white">{{ __('Transaction History') }}</h3>
                <p class="text-xs text-gray-400 dark:text-zinc-500 mt-0.5">{{ __('Real-time Obsidian note revisions and sync operations') }}</p>
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
                                            <span class="block truncate font-bold text-gray-900 dark:text-white max-w-xs">{{ $act->path }}</span>
                                            <span class="text-[10px] text-gray-400 dark:text-zinc-500">{{ $act->created_at->format('d M Y') }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-3 font-semibold text-gray-700 dark:text-zinc-300">
                                    {{ $act->vault?->name ?? __('Main Vault') }}
                                </td>

                                <td class="py-3.5 px-3">
                                    @if ($act->action === 'created')
                                        <span class="inline-flex rounded-md bg-[#E8F5E9] px-2 py-0.5 text-[10px] font-bold text-[#2E7D32]">
                                            {{ __('Created') }}
                                        </span>
                                    @elseif ($act->action === 'updated')
                                        <span class="inline-flex rounded-md bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 dark:bg-blue-950/60 dark:text-blue-400">
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
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <button type="submit" class="rounded-full bg-[#0D3B29] px-5 py-2 text-sm font-semibold text-white hover:bg-[#09261b]">
                    {{ __('Create Vault') }}
                </button>
            </div>
        </form>
    </flux:modal>
</div>
