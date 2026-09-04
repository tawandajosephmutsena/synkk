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
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-8">
    <!-- EXECUTIVE HERO COMMAND CENTER BANNER -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-purple-950 p-6 sm:p-8 text-white shadow-2xl border border-indigo-500/30">
        <!-- Glow accents -->
        <div class="absolute -right-20 -top-20 size-80 rounded-full bg-indigo-500/25 blur-3xl pointer-events-none"></div>
        <div class="absolute -left-20 -bottom-20 size-80 rounded-full bg-purple-500/25 blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-3 max-w-2xl">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-bold text-emerald-400 ring-1 ring-emerald-500/40">
                        <span class="size-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        {{ __('100% Operational') }} // {{ __('Sync Engine v1.4') }}
                    </span>
                    <span class="text-xs text-indigo-300 font-mono font-medium">{{ $this->team?->name }}</span>
                </div>

                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white leading-tight">
                    {{ __('Obsidian Vault Sync Command Center') }}
                </h1>
                <p class="text-xs sm:text-sm text-slate-300 leading-relaxed font-normal">
                    {{ __('Real-time encrypted two-way synchronization, DLP secret scanning, and granular folder permissions across your entire device fleet.') }}
                </p>
            </div>

            <!-- Quick Action Buttons in Hero Banner -->
            <div class="flex flex-wrap items-center gap-3 shrink-0">
                <div
                    x-data="{ copyState: 'idle', async copyUrl() { this.copyState = 'copying'; const result = await window.SynkkClipboard.copy(@js(url('/api/v1'))); this.copyState = result.copied ? 'copied' : 'manual'; setTimeout(() => this.copyState = 'idle', 3000); } }"
                >
                    <flux:button
                        type="button"
                        variant="filled"
                        size="sm"
                        icon="link"
                        x-on:click="copyUrl()"
                        class="bg-white/10 hover:bg-white/20 text-white border border-white/20 backdrop-blur-md font-semibold"
                    >
                        <span x-text="copyState === 'copied' ? '{{ __('API Copied!') }}' : '{{ __('Copy Sync URL') }}'"></span>
                    </flux:button>
                </div>

                <flux:button variant="filled" size="sm" icon="device-phone-mobile" :href="route('devices.index')" wire:navigate class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-lg shadow-indigo-600/40">
                    {{ __('Connect Device') }}
                </flux:button>

                <flux:modal.trigger name="create-vault">
                    <flux:button variant="primary" size="sm" icon="plus" class="bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold shadow-lg shadow-purple-600/30">
                        {{ __('New Vault') }}
                    </flux:button>
                </flux:modal.trigger>
            </div>
        </div>
    </div>

    <!-- DLP Threat Banner (If Secrets Detected) -->
    @if ($this->secretAlertsCount > 0)
        <flux:card variant="soft" class="border border-amber-500/80 bg-amber-500/10 dark:border-amber-500/60 dark:bg-amber-950/40 py-4 px-5 rounded-2xl shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-start gap-3.5">
                    <div class="flex size-9 items-center justify-center rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5 ring-1 ring-amber-500/30">
                        <flux:icon icon="shield-exclamation" class="size-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-black text-amber-950 dark:text-amber-100">{{ __('DLP Security Threats Detected') }}</h4>
                            <flux:badge color="amber" size="sm" rounded class="font-bold">{{ $this->secretAlertsCount }} {{ __('flagged events') }}</flux:badge>
                        </div>
                        <p class="text-xs text-amber-900 dark:text-amber-300 mt-1 leading-relaxed font-medium">
                            {{ __('Sensitive API keys or credentials were detected in synced notes. Audit flagged logs to secure your credentials.') }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <flux:button size="sm" variant="filled" wire:click="$set('activityFilter', 'secrets')" class="bg-amber-600 hover:bg-amber-500 text-white font-bold shadow-sm">
                        {{ __('Audit DLP Flags') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>
    @endif

    <!-- SECTION 1: Key Performance Metrics & Executive Reporting Cards -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <!-- 1. Total Vaults -->
        <flux:card variant="soft" class="relative min-w-0 py-5 px-5 rounded-2xl border border-slate-200/90 bg-white dark:border-slate-800/80 dark:bg-[#0D121F] shadow-sm">
            <div class="flex items-center justify-between">
                <flux:text class="truncate text-xs font-extrabold text-slate-600 dark:text-slate-400 uppercase tracking-wider">{{ __('Team Vaults') }}</flux:text>
                <div class="flex size-9 items-center justify-center rounded-xl bg-indigo-600/10 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-indigo-500/20">
                    <flux:icon icon="folder" class="size-5" />
                </div>
            </div>
            <flux:heading size="xl" class="mt-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white">{{ $this->vaults->count() }}</flux:heading>
            <div class="mt-2.5 flex items-center gap-1.5 text-xs">
                <flux:badge color="emerald" size="sm" class="rounded-full px-2 py-0 text-[10px] font-extrabold">{{ __('Active') }}</flux:badge>
                <flux:text inline class="text-slate-500 dark:text-slate-400 font-medium">{{ __('in :team', ['team' => $this->team?->name]) }}</flux:text>
            </div>
        </flux:card>

        <!-- 2. Synced Notes & Assets -->
        <flux:card variant="soft" class="relative min-w-0 py-5 px-5 rounded-2xl border border-slate-200/90 bg-white dark:border-slate-800/80 dark:bg-[#0D121F] shadow-sm">
            <div class="flex items-center justify-between">
                <flux:text class="truncate text-xs font-extrabold text-slate-600 dark:text-slate-400 uppercase tracking-wider">{{ __('Synced Files') }}</flux:text>
                <div class="flex size-9 items-center justify-center rounded-xl bg-blue-600/10 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400 ring-1 ring-blue-500/20">
                    <flux:icon icon="document-text" class="size-5" />
                </div>
            </div>
            <flux:heading size="xl" class="mt-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white">{{ number_format($this->totalFilesCount) }}</flux:heading>
            <div class="mt-2.5 flex items-center gap-1.5 text-xs">
                <flux:text inline class="flex items-center gap-0.5 font-bold text-emerald-600 dark:text-emerald-400">
                    <flux:icon.arrow-trending-up variant="micro" />
                    <span>{{ __('Continuous') }}</span>
                </flux:text>
                <flux:text inline class="text-slate-500 dark:text-slate-400 font-medium">{{ __('two-way sync') }}</flux:text>
            </div>
        </flux:card>

        <!-- 3. Storage Consumed -->
        <flux:card variant="soft" class="relative min-w-0 py-5 px-5 rounded-2xl border border-slate-200/90 bg-white dark:border-slate-800/80 dark:bg-[#0D121F] shadow-sm">
            <div class="flex items-center justify-between">
                <flux:text class="truncate text-xs font-extrabold text-slate-600 dark:text-slate-400 uppercase tracking-wider">{{ __('Storage Consumed') }}</flux:text>
                <div class="flex size-9 items-center justify-center rounded-xl bg-purple-600/10 text-purple-600 dark:bg-purple-500/20 dark:text-purple-400 ring-1 ring-purple-500/20">
                    <flux:icon icon="server-stack" class="size-5" />
                </div>
            </div>
            <flux:heading size="xl" class="mt-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white">{{ $this->totalStorageFormatted }}</flux:heading>
            <div class="mt-2.5 flex items-center gap-1.5 text-xs">
                <flux:badge color="zinc" size="sm" class="rounded-full px-2 py-0 text-[10px] font-extrabold">{{ __('cPanel Disk') }}</flux:badge>
                <flux:text inline class="text-slate-500 dark:text-slate-400 font-medium">{{ __('encrypted disk') }}</flux:text>
            </div>
        </flux:card>

        <!-- 4. Sync Health Score -->
        <flux:card variant="soft" class="relative min-w-0 py-5 px-5 rounded-2xl border border-slate-200/90 bg-white dark:border-slate-800/80 dark:bg-[#0D121F] shadow-sm">
            <div class="flex items-center justify-between">
                <flux:text class="truncate text-xs font-extrabold text-slate-600 dark:text-slate-400 uppercase tracking-wider">{{ __('Health Index') }}</flux:text>
                <div class="flex size-9 items-center justify-center rounded-xl bg-emerald-600/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 ring-1 ring-emerald-500/20">
                    <flux:icon icon="shield-check" class="size-5" />
                </div>
            </div>
            <flux:heading size="xl" class="mt-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white">{{ $this->syncReporting['sync_health_score'] }}/100</flux:heading>
            <div class="mt-2.5 flex items-center gap-1.5 text-xs">
                @if ($this->secretAlertsCount > 0)
                    <flux:badge color="amber" size="sm" class="rounded-full px-2 py-0 text-[10px] font-extrabold">{{ __('Action Required') }}</flux:badge>
                @else
                    <flux:badge color="emerald" size="sm" class="rounded-full px-2 py-0 text-[10px] font-extrabold">{{ __('Optimal') }}</flux:badge>
                    <flux:text inline class="text-slate-500 dark:text-slate-400 font-medium">{{ __('zero leak alerts') }}</flux:text>
                @endif
            </div>
        </flux:card>
    </div>

    <!-- SECTION 2: Analytics & Reporting Panel -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Vault Format Breakdown -->
        <flux:card variant="soft" class="space-y-5 rounded-2xl border border-slate-200/90 bg-white dark:border-slate-800/80 dark:bg-[#0D121F] p-6 shadow-sm">
            <div>
                <flux:heading size="sm" class="font-extrabold text-slate-900 dark:text-white">{{ __('Content Distribution') }}</flux:heading>
                <flux:subheading class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ __('Format breakdown across synced vaults') }}</flux:subheading>
            </div>

            <!-- Markdown Progress -->
            <div class="space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-200">
                        <span class="size-2.5 rounded-full bg-blue-500"></span>
                        {{ __('Markdown Notes (.md)') }}
                    </span>
                    <span class="font-extrabold text-slate-700 dark:text-slate-300">{{ $this->storageBreakdown['markdown']['count'] }} {{ __('files') }} ({{ $this->storageBreakdown['markdown']['percentage'] }}%)</span>
                </div>
                <flux:progress :value="$this->storageBreakdown['markdown']['percentage']" max="100" color="blue" class="h-2 rounded-full" />
            </div>

            <!-- Assets Progress -->
            <div class="space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-200">
                        <span class="size-2.5 rounded-full bg-emerald-500"></span>
                        {{ __('Attachments & Images') }}
                    </span>
                    <span class="font-extrabold text-slate-700 dark:text-slate-300">{{ $this->storageBreakdown['assets']['count'] }} {{ __('files') }} ({{ $this->storageBreakdown['assets']['percentage'] }}%)</span>
                </div>
                <flux:progress :value="$this->storageBreakdown['assets']['percentage']" max="100" color="emerald" class="h-2 rounded-full" />
            </div>

            <!-- Canvas Progress -->
            <div class="space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-200">
                        <span class="size-2.5 rounded-full bg-purple-500"></span>
                        {{ __('Canvas Boards (.canvas)') }}
                    </span>
                    <span class="font-extrabold text-slate-700 dark:text-slate-300">{{ $this->storageBreakdown['canvas']['count'] }} {{ __('files') }} ({{ $this->storageBreakdown['canvas']['percentage'] }}%)</span>
                </div>
                <flux:progress :value="$this->storageBreakdown['canvas']['percentage']" max="100" color="purple" class="h-2 rounded-full" />
            </div>
        </flux:card>

        <!-- Sync Velocity Reporting -->
        <flux:card variant="soft" class="space-y-5 rounded-2xl border border-slate-200/90 bg-white dark:border-slate-800/80 dark:bg-[#0D121F] p-6 shadow-sm">
            <div>
                <flux:heading size="sm" class="font-extrabold text-slate-900 dark:text-white">{{ __('Engine Velocity') }}</flux:heading>
                <flux:subheading class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ __('Sync throughput and revision metrics') }}</flux:subheading>
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs">
                <div class="rounded-xl bg-slate-100/90 p-3.5 dark:bg-white/5 border border-slate-200/60 dark:border-slate-800 space-y-1">
                    <span class="text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('24h Sync Events') }}</span>
                    <div class="text-2xl font-black text-slate-900 dark:text-white">{{ number_format($this->syncReporting['changes_24h']) }}</div>
                </div>

                <div class="rounded-xl bg-slate-100/90 p-3.5 dark:bg-white/5 border border-slate-200/60 dark:border-slate-800 space-y-1">
                    <span class="text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Conflict Rate') }}</span>
                    <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $this->syncReporting['conflict_rate'] }}</div>
                </div>

                <div class="rounded-xl bg-slate-100/90 p-3.5 dark:bg-white/5 border border-slate-200/60 dark:border-slate-800 space-y-1">
                    <span class="text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Avg Note Size') }}</span>
                    <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $this->syncReporting['avg_file_size'] }}</div>
                </div>

                <div class="rounded-xl bg-slate-100/90 p-3.5 dark:bg-white/5 border border-slate-200/60 dark:border-slate-800 space-y-1">
                    <span class="text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Total Conflicts') }}</span>
                    <div class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ $this->conflictCount }}</div>
                </div>
            </div>
        </flux:card>

        <!-- Connected Device Fleet Matrix -->
        <flux:card variant="soft" class="space-y-4 rounded-2xl border border-slate-200/90 bg-white dark:border-slate-800/80 dark:bg-[#0D121F] p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="font-extrabold text-slate-900 dark:text-white">{{ __('Fleet Platform Matrix') }}</flux:heading>
                    <flux:subheading class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ __('Connected client operating systems') }}</flux:subheading>
                </div>
                <flux:button variant="ghost" size="xs" :href="route('devices.index')" wire:navigate class="font-bold">
                    {{ __('Manage') }}
                </flux:button>
            </div>

            <div class="grid grid-cols-3 gap-2.5 text-center text-xs">
                <div class="rounded-xl bg-slate-100/90 p-2.5 dark:bg-white/5 border border-slate-200/60 dark:border-slate-800">
                    <div class="text-[10px] text-slate-500 dark:text-slate-400 font-extrabold uppercase">{{ __('macOS') }}</div>
                    <div class="font-black text-lg text-slate-900 dark:text-white mt-0.5">{{ $this->deviceBreakdown['mac'] }}</div>
                </div>
                <div class="rounded-xl bg-slate-100/90 p-2.5 dark:bg-white/5 border border-slate-200/60 dark:border-slate-800">
                    <div class="text-[10px] text-slate-500 dark:text-slate-400 font-extrabold uppercase">{{ __('Windows') }}</div>
                    <div class="font-black text-lg text-slate-900 dark:text-white mt-0.5">{{ $this->deviceBreakdown['windows'] }}</div>
                </div>
                <div class="rounded-xl bg-slate-100/90 p-2.5 dark:bg-white/5 border border-slate-200/60 dark:border-slate-800">
                    <div class="text-[10px] text-slate-500 dark:text-slate-400 font-extrabold uppercase">{{ __('iOS') }}</div>
                    <div class="font-black text-lg text-slate-900 dark:text-white mt-0.5">{{ $this->deviceBreakdown['ios'] }}</div>
                </div>
                <div class="rounded-xl bg-slate-100/90 p-2.5 dark:bg-white/5 border border-slate-200/60 dark:border-slate-800">
                    <div class="text-[10px] text-slate-500 dark:text-slate-400 font-extrabold uppercase">{{ __('Android') }}</div>
                    <div class="font-black text-lg text-slate-900 dark:text-white mt-0.5">{{ $this->deviceBreakdown['android'] }}</div>
                </div>
                <div class="rounded-xl bg-slate-100/90 p-2.5 dark:bg-white/5 border border-slate-200/60 dark:border-slate-800">
                    <div class="text-[10px] text-slate-500 dark:text-slate-400 font-extrabold uppercase">{{ __('Linux') }}</div>
                    <div class="font-black text-lg text-slate-900 dark:text-white mt-0.5">{{ $this->deviceBreakdown['linux'] }}</div>
                </div>
                <div class="rounded-xl bg-slate-100/90 p-2.5 dark:bg-white/5 border border-slate-200/60 dark:border-slate-800">
                    <div class="text-[10px] text-slate-500 dark:text-slate-400 font-extrabold uppercase">{{ __('Wiped') }}</div>
                    <div class="font-black text-lg text-red-600 dark:text-red-400 mt-0.5">{{ $this->deviceBreakdown['wiped'] }}</div>
                </div>
            </div>
        </flux:card>
    </div>

    <!-- SECTION 3: Active Team Vaults Grid -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <flux:heading size="lg" class="font-extrabold text-slate-900 dark:text-white">{{ __('Active Team Vaults') }}</flux:heading>
                <flux:badge size="sm" color="zinc" rounded class="font-bold">{{ $this->vaults->count() }}</flux:badge>
            </div>
            <flux:button variant="ghost" size="sm" icon:trailing="arrow-right" :href="route('vaults.index')" wire:navigate class="font-bold">
                {{ __('View All Vaults') }}
            </flux:button>
        </div>

        @if ($this->vaults->isEmpty())
            <flux:card class="flex flex-col items-center justify-center p-14 text-center border-dashed rounded-2xl border-slate-300 dark:border-slate-800">
                <div class="flex size-14 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400">
                    <flux:icon icon="folder-plus" class="size-7" />
                </div>
                <flux:heading size="md" class="mt-4 font-extrabold">{{ __('No vaults configured yet') }}</flux:heading>
                <flux:subheading class="max-w-sm mt-1.5 text-xs text-slate-500 dark:text-slate-400 font-medium">{{ __('Create your team\'s first shared Obsidian vault to start synchronizing notes across all devices.') }}</flux:subheading>
                <flux:modal.trigger name="create-vault">
                    <flux:button variant="primary" size="sm" icon="plus" class="mt-5 font-bold">
                        {{ __('Create Team Vault') }}
                    </flux:button>
                </flux:modal.trigger>
            </flux:card>
        @else
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->vaults as $vault)
                    <flux:card variant="outline" class="flex flex-col justify-between rounded-2xl p-5 transition-all hover:border-indigo-500 dark:hover:border-indigo-500/60 bg-white dark:bg-[#0D121F] border-slate-200/90 dark:border-slate-800/80 shadow-sm">
                        <div>
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600/10 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-indigo-500/20">
                                        <flux:icon icon="folder" class="size-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <a href="{{ route('vaults.show', $vault->slug) }}" wire:navigate class="block truncate font-extrabold text-sm text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400">
                                            {{ $vault->name }}
                                        </a>
                                        <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400 font-medium">{{ $vault->slug }}</span>
                                    </div>
                                </div>

                                @if ($vault->default_permission === 'read_write')
                                    <flux:badge color="emerald" size="sm" inset class="font-extrabold">{{ __('Read/Write') }}</flux:badge>
                                @elseif ($vault->default_permission === 'read_only')
                                    <flux:badge color="amber" size="sm" inset class="font-extrabold">{{ __('Read-Only') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm" inset class="font-extrabold">{{ __('Restricted') }}</flux:badge>
                                @endif
                            </div>

                            @if ($vault->description)
                                <p class="mt-3 line-clamp-2 text-xs text-slate-600 dark:text-slate-400 leading-relaxed font-normal">{{ $vault->description }}</p>
                            @endif

                            <div class="mt-4 grid grid-cols-2 gap-2 rounded-xl bg-slate-100/90 p-3 text-xs dark:bg-white/5 border border-slate-200/60 dark:border-slate-800">
                                <div>
                                    <flux:text class="text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Files') }}</flux:text>
                                    <flux:text variant="strong" class="font-extrabold block mt-0.5 text-sm text-slate-900 dark:text-white">{{ number_format($vault->files_count) }}</flux:text>
                                </div>
                                <div>
                                    <flux:text class="text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Revision') }}</flux:text>
                                    <flux:text variant="strong" class="font-extrabold block mt-0.5 text-sm text-slate-900 dark:text-white">v{{ $vault->latestVersion() }}</flux:text>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-3 dark:border-slate-800/80">
                            <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">{{ $vault->updated_at->diffForHumans() }}</span>
                            <flux:button variant="ghost" size="sm" icon:trailing="chevron-right" :href="route('vaults.show', $vault->slug)" wire:navigate class="font-bold">
                                {{ __('Manage Vault') }}
                            </flux:button>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>

    <!-- SECTION 4: Live Sync Activity Stream Table -->
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2.5">
                <flux:heading size="lg" class="font-extrabold text-slate-900 dark:text-white">{{ __('Live Sync Activity Stream') }}</flux:heading>
                <flux:badge size="sm" color="zinc" rounded class="font-bold">{{ $this->recentActivities->count() }}</flux:badge>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <flux:input wire:model.live.debounce.250ms="activitySearch" size="sm" icon="magnifying-glass" placeholder="Search path or device..." class="w-full sm:w-56" />

                <flux:radio.group wire:model.live="activityFilter" variant="segmented" size="sm">
                    <flux:radio value="all">{{ __('All Events') }}</flux:radio>
                    <flux:radio value="created">{{ __('Created') }}</flux:radio>
                    <flux:radio value="updated">{{ __('Updated') }}</flux:radio>
                    <flux:radio value="deleted">{{ __('Deleted') }}</flux:radio>
                    <flux:radio value="conflict">{{ __('Conflicts') }}</flux:radio>
                    <flux:radio value="secrets">{{ __('🔒 DLP Flags') }}</flux:radio>
                </flux:radio.group>
            </div>
        </div>

        <flux:card class="p-0 overflow-hidden rounded-2xl border border-slate-200/90 bg-white dark:border-slate-800/80 dark:bg-[#0D121F] shadow-sm">
            @if ($this->recentActivities->isEmpty())
                <div class="p-12 text-center text-sm text-slate-500 dark:text-slate-400 font-medium">
                    {{ __('No sync events found matching the selected filter or search term.') }}
                </div>
            @else
                <flux:table>
                    <flux:table.columns class="bg-slate-50/80 dark:bg-white/5 border-b border-slate-200/80 dark:border-slate-800">
                        <flux:table.column class="py-3.5 px-4 text-xs font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-400">{{ __('File / Note Path') }}</flux:table.column>
                        <flux:table.column class="py-3.5 px-4 text-xs font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-400">{{ __('Target Vault') }}</flux:table.column>
                        <flux:table.column class="py-3.5 px-4 text-xs font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-400">{{ __('Sync Action') }}</flux:table.column>
                        <flux:table.column class="py-3.5 px-4 text-xs font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-400">{{ __('Author & Device') }}</flux:table.column>
                        <flux:table.column align="end" class="py-3.5 px-4 text-xs font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-400">{{ __('Timestamp') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->recentActivities as $act)
                            <flux:table.row :key="$act->id" class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors border-b border-slate-100 dark:border-slate-800/60">
                                <flux:table.cell class="py-3.5 px-4 font-mono text-xs font-medium">
                                    <div class="flex items-center gap-2.5">
                                        @if ($act->has_secrets)
                                            <flux:tooltip content="DLP Threat: Secrets detected in note">
                                                <flux:icon icon="shield-exclamation" class="size-4 text-amber-500 shrink-0" />
                                            </flux:tooltip>
                                        @elseif ($act->action === 'conflict')
                                            <flux:icon icon="exclamation-triangle" class="size-4 text-amber-500 shrink-0" />
                                        @elseif ($act->action === 'deleted')
                                            <flux:icon icon="trash" class="size-4 text-red-500 shrink-0" />
                                        @elseif (str_ends_with(strtolower($act->path), '.md'))
                                            <flux:icon icon="document-text" class="size-4 text-blue-500 shrink-0" />
                                        @else
                                            <flux:icon icon="paper-clip" class="size-4 text-slate-400 shrink-0" />
                                        @endif
                                        <span class="truncate max-w-sm font-bold text-slate-900 dark:text-slate-100">{{ $act->path }}</span>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="py-3.5 px-4">
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $act->vault?->name ?? __('Unknown') }}</span>
                                </flux:table.cell>

                                <flux:table.cell class="py-3.5 px-4">
                                    <div class="flex items-center gap-1.5">
                                        @if ($act->action === 'created')
                                            <flux:badge color="emerald" size="sm" class="font-extrabold">{{ __('Created') }}</flux:badge>
                                        @elseif ($act->action === 'updated')
                                            <flux:badge color="blue" size="sm" class="font-extrabold">{{ __('Updated') }}</flux:badge>
                                        @elseif ($act->action === 'deleted')
                                            <flux:badge color="red" size="sm" class="font-extrabold">{{ __('Deleted') }}</flux:badge>
                                        @elseif ($act->action === 'conflict')
                                            <flux:badge color="amber" size="sm" class="font-extrabold">{{ __('Conflict Branched') }}</flux:badge>
                                        @endif

                                        @if ($act->has_secrets)
                                            <flux:badge color="amber" size="sm" icon="key" class="font-extrabold">{{ __('Secret Flagged') }}</flux:badge>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="py-3.5 px-4">
                                    <div class="flex items-center gap-2.5">
                                        <flux:avatar :name="$act->user?->name ?? 'Device'" size="xs" />
                                        <div class="text-xs">
                                            <span class="font-bold text-slate-900 dark:text-slate-100">{{ $act->user?->name ?? __('Device Sync') }}</span>
                                            @if ($act->device_name)
                                                <span class="text-slate-500 dark:text-slate-400 font-medium">({{ $act->device_name }})</span>
                                            @endif
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell align="end" class="py-3.5 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400">
                                    {{ $act->created_at->diffForHumans() }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
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
                <flux:button variant="primary" type="submit">{{ __('Create Vault') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
