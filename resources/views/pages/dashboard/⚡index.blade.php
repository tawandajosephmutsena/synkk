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
    public function recentActivities(): Collection
    {
        if (! $this->team) {
            return collect();
        }

        $query = VaultChangeLog::whereIn('vault_id', $this->vaults->pluck('id'))
            ->with(['user', 'vault'])
            ->latest('created_at');

        if ($this->activityFilter !== 'all') {
            $query->where('action', $this->activityFilter);
        }

        return $query->limit(10)->get();
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
    public function storageBreakdown(): array
    {
        if (! $this->team || $this->vaults->isEmpty()) {
            return [
                'markdown' => ['count' => 0, 'size' => 0, 'percentage' => 0],
                'assets' => ['count' => 0, 'size' => 0, 'percentage' => 0],
                'canvas' => ['count' => 0, 'size' => 0, 'percentage' => 0],
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

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Top Bar with Breadcrumbs & Quick Actions -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:breadcrumbs class="mb-1">
                <flux:breadcrumbs.item href="{{ route('dashboard') }}">{{ $this->team?->name ?? __('Team') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ __('Sync Engine') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
            <flux:heading size="xl" level="1" class="tracking-tight">{{ __('Vault Sync Dashboard') }}</flux:heading>
        </div>

        <div class="flex items-center gap-2">
            <flux:dropdown>
                <flux:button variant="subtle" size="sm" icon="ellipsis-horizontal" aria-label="Quick Actions" />
                <flux:menu>
                    <flux:menu.item icon="arrow-path" wire:click="$refresh">{{ __('Refresh Sync Status') }}</flux:menu.item>
                    <flux:menu.item icon="key" :href="route('devices.index')" wire:navigate>{{ __('Manage Device Tokens') }}</flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item icon="shield-check" :href="route('teams.edit', $this->team?->slug ?? '')" wire:navigate>{{ __('Team Permissions') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            <flux:button variant="filled" size="sm" icon="device-phone-mobile" :href="route('devices.index')" wire:navigate>
                {{ __('Connect Device') }}
            </flux:button>

            <flux:modal.trigger name="create-vault">
                <flux:button variant="primary" size="sm" icon="plus">
                    {{ __('New Vault') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <!-- Stat Cards (Flux Soft Cards with Micro Trends) -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- 1. Total Vaults -->
        <flux:card variant="soft" class="relative min-w-0 py-4.5">
            <div class="flex items-center justify-between">
                <flux:text class="truncate text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Team Vaults') }}</flux:text>
                <div class="flex size-7 items-center justify-center rounded-md bg-zinc-200/50 text-zinc-700 dark:bg-white/10 dark:text-zinc-300">
                    <flux:icon icon="folder" class="size-4" />
                </div>
            </div>
            <flux:heading size="xl" class="mt-2 text-2xl font-semibold tracking-tight">{{ $this->vaults->count() }}</flux:heading>
            <div class="mt-2 flex items-center gap-1.5 text-xs">
                <flux:badge color="emerald" size="sm" class="rounded-full px-1.5 py-0 text-[10px] font-semibold">{{ __('Active') }}</flux:badge>
                <flux:text inline class="text-zinc-400 dark:text-zinc-500">{{ __('in :team', ['team' => $this->team?->name]) }}</flux:text>
            </div>
        </flux:card>

        <!-- 2. Synced Files -->
        <flux:card variant="soft" class="relative min-w-0 py-4.5">
            <div class="flex items-center justify-between">
                <flux:text class="truncate text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Synced Notes & Assets') }}</flux:text>
                <div class="flex size-7 items-center justify-center rounded-md bg-zinc-200/50 text-zinc-700 dark:bg-white/10 dark:text-zinc-300">
                    <flux:icon icon="document-text" class="size-4" />
                </div>
            </div>
            <flux:heading size="xl" class="mt-2 text-2xl font-semibold tracking-tight">{{ number_format($this->totalFilesCount) }}</flux:heading>
            <div class="mt-2 flex items-center gap-1.5 text-xs">
                <flux:text inline class="flex items-center gap-0.5 font-medium text-emerald-600 dark:text-emerald-400">
                    <flux:icon.arrow-trending-up variant="micro" />
                    <span>{{ __('Continuous') }}</span>
                </flux:text>
                <flux:text inline class="text-zinc-400 dark:text-zinc-500">{{ __('two-way sync') }}</flux:text>
            </div>
        </flux:card>

        <!-- 3. Storage Consumed -->
        <flux:card variant="soft" class="relative min-w-0 py-4.5">
            <div class="flex items-center justify-between">
                <flux:text class="truncate text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Storage Consumed') }}</flux:text>
                <div class="flex size-7 items-center justify-center rounded-md bg-zinc-200/50 text-zinc-700 dark:bg-white/10 dark:text-zinc-300">
                    <flux:icon icon="server-stack" class="size-4" />
                </div>
            </div>
            <flux:heading size="xl" class="mt-2 text-2xl font-semibold tracking-tight">{{ $this->totalStorageFormatted }}</flux:heading>
            <div class="mt-2 flex items-center gap-1.5 text-xs">
                <flux:badge color="zinc" size="sm" class="rounded-full px-1.5 py-0 text-[10px]">{{ __('cPanel Disk') }}</flux:badge>
                <flux:text inline class="text-zinc-400 dark:text-zinc-500">{{ __('encrypted storage') }}</flux:text>
            </div>
        </flux:card>

        <!-- 4. Connected Devices -->
        <flux:card variant="soft" class="relative min-w-0 py-4.5">
            <div class="flex items-center justify-between">
                <flux:text class="truncate text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Connected Devices') }}</flux:text>
                <div class="flex size-7 items-center justify-center rounded-md bg-zinc-200/50 text-zinc-700 dark:bg-white/10 dark:text-zinc-300">
                    <flux:icon icon="device-phone-mobile" class="size-4" />
                </div>
            </div>
            <flux:heading size="xl" class="mt-2 text-2xl font-semibold tracking-tight">{{ $this->activeDevicesCount }}</flux:heading>
            <div class="mt-2 flex items-center gap-1.5 text-xs">
                <flux:text inline class="flex items-center gap-0.5 font-medium text-blue-600 dark:text-blue-400">
                    <flux:icon icon="signal" class="size-3" />
                    <span>{{ __('Online') }}</span>
                </flux:text>
                <flux:text inline class="text-zinc-400 dark:text-zinc-500">{{ __('iOS, Android, Mac, PC') }}</flux:text>
            </div>
        </flux:card>
    </div>

    <!-- Main Content Area: Vaults Grid & Storage Analytics -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Vaults Section (2 Cols) -->
        <div class="space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">{{ __('Active Vaults') }}</flux:heading>
                    <flux:badge size="sm" color="zinc" rounded>{{ $this->vaults->count() }}</flux:badge>
                </div>
                <flux:button variant="ghost" size="sm" icon:trailing="arrow-right" :href="route('vaults.index')" wire:navigate>
                    {{ __('View all') }}
                </flux:button>
            </div>

            @if ($this->vaults->isEmpty())
                <flux:card class="flex flex-col items-center justify-center p-12 text-center border-dashed">
                    <div class="flex size-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-400">
                        <flux:icon icon="folder-plus" class="size-6" />
                    </div>
                    <flux:heading size="md" class="mt-4">{{ __('No vaults configured yet') }}</flux:heading>
                    <flux:subheading class="max-w-sm mt-1 text-xs">{{ __('Create your team\'s first shared Obsidian vault to start synchronizing notes across all devices.') }}</flux:subheading>
                    <flux:modal.trigger name="create-vault">
                        <flux:button variant="primary" size="sm" icon="plus" class="mt-5">
                            {{ __('Create Team Vault') }}
                        </flux:button>
                    </flux:modal.trigger>
                </flux:card>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($this->vaults as $vault)
                        <flux:card variant="outline" class="flex flex-col justify-between transition-all hover:border-zinc-400 dark:hover:border-zinc-600">
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-800 dark:bg-white/10 dark:text-white">
                                            <flux:icon icon="folder" class="size-5" />
                                        </div>
                                        <div class="min-w-0">
                                            <a href="{{ route('vaults.show', $vault->slug) }}" wire:navigate class="block truncate font-semibold text-zinc-900 hover:underline dark:text-zinc-100">
                                                {{ $vault->name }}
                                            </a>
                                            <span class="font-mono text-xs text-zinc-400 dark:text-zinc-500">{{ $vault->slug }}</span>
                                        </div>
                                    </div>

                                    @if ($vault->default_permission === 'read_write')
                                        <flux:badge color="emerald" size="sm" inset>{{ __('Read/Write') }}</flux:badge>
                                    @elseif ($vault->default_permission === 'read_only')
                                        <flux:badge color="amber" size="sm" inset>{{ __('Read-Only') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm" inset>{{ __('Restricted') }}</flux:badge>
                                    @endif
                                </div>

                                @if ($vault->description)
                                    <p class="mt-3 line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $vault->description }}</p>
                                @endif

                                <div class="mt-4 grid grid-cols-2 gap-2 rounded-lg bg-zinc-50 p-2.5 text-xs dark:bg-white/5">
                                    <div>
                                        <flux:text class="text-[11px] text-zinc-400">{{ __('Files') }}</flux:text>
                                        <flux:text variant="strong" class="font-semibold">{{ number_format($vault->files_count) }}</flux:text>
                                    </div>
                                    <div>
                                        <flux:text class="text-[11px] text-zinc-400">{{ __('Version') }}</flux:text>
                                        <flux:text variant="strong" class="font-semibold">v{{ $vault->latestVersion() }}</flux:text>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between border-t border-zinc-100 pt-3 dark:border-white/10">
                                <span class="text-[11px] text-zinc-400">{{ $vault->updated_at->diffForHumans() }}</span>
                                <flux:button variant="ghost" size="sm" icon:trailing="chevron-right" :href="route('vaults.show', $vault->slug)" wire:navigate>
                                    {{ __('Permissions') }}
                                </flux:button>
                            </div>
                        </flux:card>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Right Column: Storage Breakdown & Fleet Status -->
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Storage & Fleet') }}</flux:heading>

            <flux:card variant="soft" class="space-y-5">
                <div>
                    <flux:heading size="sm">{{ __('Vault Content Breakdown') }}</flux:heading>
                    <flux:subheading class="text-xs">{{ __('Distribution of synced file formats') }}</flux:subheading>
                </div>

                <!-- Markdown Progress -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-1.5 font-medium text-zinc-700 dark:text-zinc-300">
                            <span class="size-2 rounded-full bg-blue-500"></span>
                            {{ __('Markdown Notes (.md)') }}
                        </span>
                        <span class="text-zinc-500 dark:text-zinc-400">{{ $this->storageBreakdown['markdown']['count'] }} {{ __('files') }} ({{ $this->storageBreakdown['markdown']['percentage'] }}%)</span>
                    </div>
                    <flux:progress :value="$this->storageBreakdown['markdown']['percentage']" max="100" color="blue" class="h-1.5" />
                </div>

                <!-- Assets Progress -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-1.5 font-medium text-zinc-700 dark:text-zinc-300">
                            <span class="size-2 rounded-full bg-emerald-500"></span>
                            {{ __('Attachments & Images') }}
                        </span>
                        <span class="text-zinc-500 dark:text-zinc-400">{{ $this->storageBreakdown['assets']['count'] }} {{ __('files') }} ({{ $this->storageBreakdown['assets']['percentage'] }}%)</span>
                    </div>
                    <flux:progress :value="$this->storageBreakdown['assets']['percentage']" max="100" color="emerald" class="h-1.5" />
                </div>

                <!-- Canvas Progress -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-1.5 font-medium text-zinc-700 dark:text-zinc-300">
                            <span class="size-2 rounded-full bg-purple-500"></span>
                            {{ __('Canvas Boards (.canvas)') }}
                        </span>
                        <span class="text-zinc-500 dark:text-zinc-400">{{ $this->storageBreakdown['canvas']['count'] }} {{ __('files') }} ({{ $this->storageBreakdown['canvas']['percentage'] }}%)</span>
                    </div>
                    <flux:progress :value="$this->storageBreakdown['canvas']['percentage']" max="100" color="purple" class="h-1.5" />
                </div>

                <div class="border-t border-zinc-200 pt-4 dark:border-white/10">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Sync Health Status:') }}</span>
                        <span class="flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400">
                            <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            {{ __('100% Operational') }}
                        </span>
                    </div>
                </div>
            </flux:card>

            <!-- Quick Endpoint Card -->
            <flux:card variant="outline" class="space-y-3">
                <div class="flex items-center gap-2">
                    <flux:icon icon="bolt" class="size-4 text-amber-500" />
                    <flux:heading size="sm">{{ __('Obsidian Sync API') }}</flux:heading>
                </div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Enter this endpoint in your Obsidian mobile or desktop client settings:') }}
                </p>
                <div class="flex items-center gap-2">
                    <code class="w-full truncate rounded bg-zinc-100 p-2 font-mono text-[11px] text-zinc-800 dark:bg-white/10 dark:text-zinc-200">
                        {{ url('/api/v1') }}
                    </code>
                </div>
            </flux:card>
        </div>
    </div>

    <!-- Live Sync Activity Table (Flux Table Component) -->
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <flux:heading size="lg">{{ __('Live Sync Activity') }}</flux:heading>
                <flux:badge size="sm" color="zinc" rounded>{{ $this->recentActivities->count() }}</flux:badge>
            </div>

            <div class="flex items-center gap-2">
                <flux:radio.group wire:model.live="activityFilter" variant="segmented" size="sm">
                    <flux:radio value="all">{{ __('All Events') }}</flux:radio>
                    <flux:radio value="created">{{ __('Created') }}</flux:radio>
                    <flux:radio value="updated">{{ __('Updated') }}</flux:radio>
                    <flux:radio value="deleted">{{ __('Deleted') }}</flux:radio>
                    <flux:radio value="conflict">{{ __('Conflicts') }}</flux:radio>
                </flux:radio.group>
            </div>
        </div>

        <flux:card class="p-0 overflow-hidden">
            @if ($this->recentActivities->isEmpty())
                <div class="p-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No sync events found matching the selected filter.') }}
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('File / Note') }}</flux:table.column>
                        <flux:table.column>{{ __('Target Vault') }}</flux:table.column>
                        <flux:table.column>{{ __('Action') }}</flux:table.column>
                        <flux:table.column>{{ __('Author & Device') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Timestamp') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->recentActivities as $act)
                            <flux:table.row :key="$act->id">
                                <flux:table.cell class="font-mono text-xs font-medium">
                                    <div class="flex items-center gap-2">
                                        @if ($act->action === 'conflict')
                                            <flux:icon icon="exclamation-triangle" class="size-4 text-amber-500 shrink-0" />
                                        @elseif ($act->action === 'deleted')
                                            <flux:icon icon="trash" class="size-4 text-red-500 shrink-0" />
                                        @elseif (str_ends_with(strtolower($act->path), '.md'))
                                            <flux:icon icon="document-text" class="size-4 text-blue-500 shrink-0" />
                                        @else
                                            <flux:icon icon="paper-clip" class="size-4 text-zinc-400 shrink-0" />
                                        @endif
                                        <span class="truncate max-w-xs">{{ $act->path }}</span>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $act->vault?->name ?? __('Unknown') }}</span>
                                </flux:table.cell>

                                <flux:table.cell>
                                    @if ($act->action === 'created')
                                        <flux:badge color="emerald" size="sm">{{ __('Created') }}</flux:badge>
                                    @elseif ($act->action === 'updated')
                                        <flux:badge color="blue" size="sm">{{ __('Updated') }}</flux:badge>
                                    @elseif ($act->action === 'deleted')
                                        <flux:badge color="red" size="sm">{{ __('Deleted') }}</flux:badge>
                                    @elseif ($act->action === 'conflict')
                                        <flux:badge color="amber" size="sm">{{ __('Conflict Branched') }}</flux:badge>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:avatar :name="$act->user?->name ?? 'Device'" size="xs" />
                                        <div class="text-xs">
                                            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $act->user?->name ?? __('Device Sync') }}</span>
                                            @if ($act->device_name)
                                                <span class="text-zinc-400">({{ $act->device_name }})</span>
                                            @endif
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell align="end" class="text-xs text-zinc-400">
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
