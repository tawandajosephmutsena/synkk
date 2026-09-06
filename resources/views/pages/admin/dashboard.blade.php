<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultChangeLog;
use App\Models\VaultFile;
use App\Services\PlanService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Platform Super Admin')] class extends Component {
    public string $activeTab = 'overview';
    public string $tenantSearch = '';
    public string $planFilter = 'all';
    public string $statusFilter = 'all';

    public string $userSearch = '';

    // Quota edit state
    public ?int $editingTeamId = null;
    public string $editingTeamName = '';
    public ?int $editStorageLimitMb = null;
    public ?int $editMaxDevices = null;
    public ?int $editMaxVaults = null;
    public ?int $editMaxMembers = null;

    // License generator state
    public string $newLicenseTier = 'pro_ltd';
    public ?string $generatedKey = null;

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    #[Computed]
    public function stats(): array
    {
        $planService = app(PlanService::class);
        $totalTenants = Team::count();
        $totalUsers = User::count();
        $totalVaults = Vault::count();
        $totalFiles = VaultFile::where('is_deleted', false)->count();
        $totalBytes = (int) VaultFile::where('is_deleted', false)->sum('size');
        $activeDevices = DeviceToken::where('is_revoked', false)->count();
        $syncEvents = VaultChangeLog::count();

        return [
            'total_tenants' => $totalTenants,
            'total_users' => $totalUsers,
            'total_vaults' => $totalVaults,
            'total_files' => $totalFiles,
            'total_storage' => $totalBytes > 0 ? Number::fileSize($totalBytes, precision: 1) : '0 B',
            'active_devices' => $activeDevices,
            'sync_events' => $syncEvents,
            'paid_tenants' => Team::whereIn('plan', ['pro_ltd', 'cloud'])->count(),
        ];
    }

    #[Computed]
    public function tenants(): Collection
    {
        $query = Team::withCount(['members', 'vaults', 'deviceTokens'])->latest();

        if (filled($this->tenantSearch)) {
            $search = $this->tenantSearch;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($this->planFilter !== 'all') {
            $query->where('plan', $this->planFilter);
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return $query->take(50)->get();
    }

    #[Computed]
    public function users(): Collection
    {
        $query = User::withCount('teams')->latest();

        if (filled($this->userSearch)) {
            $search = $this->userSearch;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->take(50)->get();
    }

    public function updateTenantPlan(int $teamId, string $newPlan): void
    {
        $team = Team::findOrFail($teamId);
        $team->update(['plan' => $newPlan]);

        Flux::toast(variant: 'success', text: __("Tenant ':name' plan updated to :plan.", [
            'name' => $team->name,
            'plan' => $team->planName(),
        ]));
    }

    public function toggleTenantStatus(int $teamId): void
    {
        $team = Team::findOrFail($teamId);
        $newStatus = $team->status === 'active' ? 'suspended' : 'active';
        $team->update(['status' => $newStatus]);

        Flux::toast(variant: 'success', text: __("Tenant ':name' is now :status.", [
            'name' => $team->name,
            'status' => $newStatus,
        ]));
    }

    public function openQuotaModal(int $teamId): void
    {
        $team = Team::findOrFail($teamId);
        $planService = app(PlanService::class);

        $this->editingTeamId = $team->id;
        $this->editingTeamName = $team->name;
        $this->editStorageLimitMb = $team->storage_limit_mb ?? $planService->getStorageLimitMb($team);
        $this->editMaxDevices = $team->max_devices ?? $planService->getDeviceLimit($team);
        $this->editMaxVaults = $team->max_vaults ?? $planService->getVaultLimit($team);
        $this->editMaxMembers = $team->max_members ?? $planService->getMemberLimit($team);

        $this->dispatch('open-modal', name: 'edit-quota');
    }

    public function saveQuotas(): void
    {
        if (! $this->editingTeamId) {
            return;
        }

        $this->validate([
            'editStorageLimitMb' => ['required', 'integer', 'min:10', 'max:500000'],
            'editMaxDevices' => ['required', 'integer', 'min:1', 'max:1000'],
            'editMaxVaults' => ['required', 'integer', 'min:1', 'max:500'],
            'editMaxMembers' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $team = Team::findOrFail($this->editingTeamId);
        $team->update([
            'storage_limit_mb' => $this->editStorageLimitMb,
            'max_devices' => $this->editMaxDevices,
            'max_vaults' => $this->editMaxVaults,
            'max_members' => $this->editMaxMembers,
        ]);

        $this->dispatch('close-modal', name: 'edit-quota');
        Flux::toast(variant: 'success', text: __("Custom limits saved for tenant ':name'.", ['name' => $team->name]));
    }

    public function resetQuotasToDefault(int $teamId): void
    {
        $team = Team::findOrFail($teamId);
        $team->update([
            'storage_limit_mb' => null,
            'max_devices' => null,
            'max_vaults' => null,
            'max_members' => null,
        ]);

        $this->dispatch('close-modal', name: 'edit-quota');
        Flux::toast(variant: 'success', text: __("Quotas for ':name' restored to tier defaults.", ['name' => $team->name]));
    }

    public function toggleSuperAdmin(int $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->id === auth()->id()) {
            Flux::toast(variant: 'danger', text: __('You cannot revoke your own Super Admin access.'));
            return;
        }

        $user->update(['is_super_admin' => ! $user->is_super_admin]);

        Flux::toast(variant: 'success', text: __("User ':name' Super Admin status toggled.", ['name' => $user->name]));
    }

    public function generateLicenseKey(): void
    {
        $prefix = match ($this->newLicenseTier) {
            'cloud' => 'SYNK-CLOUD',
            default => 'SYNK-PRO',
        };

        $segment1 = strtoupper(Str::random(4));
        $segment2 = strtoupper(Str::random(4));
        $segment3 = strtoupper(Str::random(4));

        $this->generatedKey = "{$prefix}-{$segment1}-{$segment2}-{$segment3}";

        Flux::toast(variant: 'success', text: __('New commercial license key generated.'));
    }

    public function activateLicenseOnTeam(int $teamId): void
    {
        if (! $this->generatedKey) {
            Flux::toast(variant: 'danger', text: __('Generate a license key first.'));
            return;
        }

        $team = Team::findOrFail($teamId);
        $team->update([
            'license_key' => $this->generatedKey,
            'license_status' => 'active',
            'license_activated_at' => now(),
            'plan' => $this->newLicenseTier,
        ]);

        Flux::toast(variant: 'success', text: __("License activated on tenant ':name'.", ['name' => $team->name]));
    }

    public function revokeLicense(int $teamId): void
    {
        $team = Team::findOrFail($teamId);
        $team->update([
            'license_status' => 'revoked',
            'plan' => 'free',
        ]);

        Flux::toast(variant: 'success', text: __("License revoked from tenant ':name'. Reverted to Community Free.", ['name' => $team->name]));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 font-sans text-slate-900 dark:text-slate-100">
    <!-- Top Platform Bar -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-gray-200/80 pb-4 dark:border-zinc-800">
        <div class="flex items-center gap-3">
            <div class="flex size-10 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400">
                <flux:icon icon="shield-check" class="size-6" />
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Synkk Platform Super Admin') }}</h1>
                    <span class="rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">{{ __('Multi-Tenant SaaS Control') }}</span>
                </div>
                <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Global tenant management, subscription tiers, storage limits, and support impersonation.') }}</p>
            </div>
        </div>

        <!-- Tab Controls -->
        <div class="flex items-center gap-1 rounded-xl bg-slate-200/60 p-1 dark:bg-zinc-800/80">
            <button
                type="button"
                wire:click="setTab('overview')"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer {{ $activeTab === 'overview' ? 'bg-white text-slate-900 shadow-xs dark:bg-zinc-900 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white' }}"
            >
                {{ __('Overview') }}
            </button>
            <button
                type="button"
                wire:click="setTab('tenants')"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer {{ $activeTab === 'tenants' ? 'bg-white text-slate-900 shadow-xs dark:bg-zinc-900 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white' }}"
            >
                {{ __('Tenants') }} ({{ $this->stats['total_tenants'] }})
            </button>
            <button
                type="button"
                wire:click="setTab('users')"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer {{ $activeTab === 'users' ? 'bg-white text-slate-900 shadow-xs dark:bg-zinc-900 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white' }}"
            >
                {{ __('Users') }} ({{ $this->stats['total_users'] }})
            </button>
            <button
                type="button"
                wire:click="setTab('licenses')"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer {{ $activeTab === 'licenses' ? 'bg-white text-slate-900 shadow-xs dark:bg-zinc-900 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white' }}"
            >
                {{ __('Licenses') }}
            </button>
            <button
                type="button"
                wire:click="setTab('system')"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer {{ $activeTab === 'system' ? 'bg-white text-slate-900 shadow-xs dark:bg-zinc-900 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white' }}"
            >
                {{ __('System') }}
            </button>
        </div>
    </div>

    <!-- TAB 1: OVERVIEW -->
    @if ($activeTab === 'overview')
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ __('Total Tenants') }}</span>
                    <span class="flex size-7 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                        <flux:icon icon="building-office-2" class="size-4" />
                    </span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ number_format($this->stats['total_tenants']) }}</span>
                    <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                        {{ $this->stats['paid_tenants'] }} {{ __('Paid') }}
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ __('Total Users') }}</span>
                    <span class="flex size-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                        <flux:icon icon="users" class="size-4" />
                    </span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ number_format($this->stats['total_users']) }}</span>
                    <span class="text-[11px] text-slate-500 dark:text-zinc-400">{{ __('Accounts') }}</span>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ __('Active Device Fleet') }}</span>
                    <span class="flex size-7 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
                        <flux:icon icon="device-phone-mobile" class="size-4" />
                    </span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ number_format($this->stats['active_devices']) }}</span>
                    <span class="text-[11px] text-slate-500 dark:text-zinc-400">{{ __('Syncing') }}</span>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ __('Total Cloud Storage') }}</span>
                    <span class="flex size-7 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                        <flux:icon icon="cloud-arrow-up" class="size-4" />
                    </span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $this->stats['total_storage'] }}</span>
                    <span class="text-[11px] text-slate-500 dark:text-zinc-400">{{ number_format($this->stats['total_files']) }} {{ __('Files') }}</span>
                </div>
            </div>
        </div>

        <!-- Quick Platform Telemetry & Plan Distribution -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs lg:col-span-2 dark:border-zinc-800 dark:bg-zinc-900">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">{{ __('SaaS Subscription Tiers & Revenue Engine') }}</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3.5 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-600 dark:text-zinc-300">{{ __('Community Free') }}</span>
                            <span class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold text-slate-700 dark:bg-zinc-700 dark:text-zinc-300">$0</span>
                        </div>
                        <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ Team::where('plan', 'free')->count() }}</p>
                        <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">{{ __('1 Vault • 3 Devices • 1 GB Storage') }}</p>
                    </div>

                    <div class="rounded-xl border border-emerald-500/30 bg-emerald-50/40 p-3.5 dark:border-emerald-500/20 dark:bg-emerald-950/20">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-emerald-800 dark:text-emerald-300">{{ __('Pro Lifetime Deal') }}</span>
                            <span class="rounded bg-emerald-200 px-1.5 py-0.5 text-[10px] font-bold text-emerald-900 dark:bg-emerald-900/60 dark:text-emerald-300">$79 LTD</span>
                        </div>
                        <p class="mt-2 text-xl font-bold text-emerald-900 dark:text-emerald-200">{{ Team::where('plan', 'pro_ltd')->count() }}</p>
                        <p class="text-[11px] text-emerald-700/80 dark:text-emerald-400/80 mt-1">{{ __('15 Vaults • 25 Devices • DLP & ACLs') }}</p>
                    </div>

                    <div class="rounded-xl border border-indigo-500/30 bg-indigo-50/40 p-3.5 dark:border-indigo-500/20 dark:bg-indigo-950/20">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-indigo-800 dark:text-indigo-300">{{ __('Synkk Cloud') }}</span>
                            <span class="rounded bg-indigo-200 px-1.5 py-0.5 text-[10px] font-bold text-indigo-900 dark:bg-indigo-900/60 dark:text-indigo-300">$12/mo</span>
                        </div>
                        <p class="mt-2 text-xl font-bold text-indigo-900 dark:text-indigo-200">{{ Team::where('plan', 'cloud')->count() }}</p>
                        <p class="text-[11px] text-indigo-700/80 dark:text-indigo-400/80 mt-1">{{ __('50 Vaults • 100 Devices • CRDT & RAG') }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">{{ __('Platform Fast Actions') }}</h3>
                <div class="flex flex-col gap-2.5">
                    <button
                        type="button"
                        wire:click="setTab('tenants')"
                        class="flex items-center justify-between rounded-xl border border-slate-200 p-3 text-start hover:bg-slate-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50 cursor-pointer"
                    >
                        <div class="flex items-center gap-2.5">
                            <flux:icon icon="building-office" class="size-4 text-slate-500" />
                            <span class="text-xs font-semibold text-slate-800 dark:text-zinc-200">{{ __('Inspect Tenants & Quotas') }}</span>
                        </div>
                        <flux:icon icon="chevron-right" class="size-4 text-slate-400" />
                    </button>

                    <button
                        type="button"
                        wire:click="setTab('licenses')"
                        class="flex items-center justify-between rounded-xl border border-slate-200 p-3 text-start hover:bg-slate-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50 cursor-pointer"
                    >
                        <div class="flex items-center gap-2.5">
                            <flux:icon icon="key" class="size-4 text-amber-500" />
                            <span class="text-xs font-semibold text-slate-800 dark:text-zinc-200">{{ __('Generate AppSumo License Key') }}</span>
                        </div>
                        <flux:icon icon="chevron-right" class="size-4 text-slate-400" />
                    </button>

                    <button
                        type="button"
                        wire:click="setTab('users')"
                        class="flex items-center justify-between rounded-xl border border-slate-200 p-3 text-start hover:bg-slate-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50 cursor-pointer"
                    >
                        <div class="flex items-center gap-2.5">
                            <flux:icon icon="user-circle" class="size-4 text-indigo-500" />
                            <span class="text-xs font-semibold text-slate-800 dark:text-zinc-200">{{ __('Customer Support Impersonation') }}</span>
                        </div>
                        <flux:icon icon="chevron-right" class="size-4 text-slate-400" />
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 2: TENANTS -->
    @if ($activeTab === 'tenants')
        <div class="flex flex-col gap-4">
            <!-- Filter Bar -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full max-w-sm">
                    <flux:icon icon="magnifying-glass" class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                    <input
                        wire:model.live.debounce.250ms="tenantSearch"
                        type="text"
                        placeholder="Search tenant name or slug..."
                        class="w-full rounded-xl border border-gray-200/90 bg-white py-2 pl-9 pr-3 text-xs text-slate-900 shadow-2xs focus:border-[#0D3B29] focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-white"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <select
                        wire:model.live="planFilter"
                        class="rounded-xl border border-gray-200/90 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-2xs focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 cursor-pointer"
                    >
                        <option value="all">{{ __('All Plans') }}</option>
                        <option value="free">{{ __('Community Free') }}</option>
                        <option value="pro_ltd">{{ __('Pro LTD') }}</option>
                        <option value="cloud">{{ __('Synkk Cloud') }}</option>
                    </select>

                    <select
                        wire:model.live="statusFilter"
                        class="rounded-xl border border-gray-200/90 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-2xs focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 cursor-pointer"
                    >
                        <option value="all">{{ __('All Statuses') }}</option>
                        <option value="active">{{ __('Active') }}</option>
                        <option value="suspended">{{ __('Suspended') }}</option>
                    </select>
                </div>
            </div>

            <!-- Tenants Table -->
            <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-gray-200/80 bg-slate-50/75 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:border-zinc-800 dark:bg-zinc-800/50 dark:text-zinc-400">
                            <tr>
                                <th class="py-3 px-4">{{ __('Tenant') }}</th>
                                <th class="py-3 px-4">{{ __('Plan & Tier') }}</th>
                                <th class="py-3 px-4">{{ __('Status') }}</th>
                                <th class="py-3 px-4">{{ __('Usage (Seats / Vaults / Devices)') }}</th>
                                <th class="py-3 px-4">{{ __('Storage Quota') }}</th>
                                <th class="py-3 px-4 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800/80">
                            @forelse ($this->tenants as $tenant)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="py-3.5 px-4 font-medium text-slate-900 dark:text-white">
                                        <div class="flex items-center gap-2">
                                            <span class="flex size-7 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold text-slate-700 dark:bg-zinc-800 dark:text-zinc-200">
                                                {{ substr($tenant->name, 0, 2) }}
                                            </span>
                                            <div>
                                                <div class="font-bold text-slate-900 dark:text-white">{{ $tenant->name }}</div>
                                                <div class="text-[10px] text-slate-400 font-mono">/{{ $tenant->slug }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <select
                                            wire:change="updateTenantPlan({{ $tenant->id }}, $event.target.value)"
                                            class="rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 cursor-pointer"
                                        >
                                            <option value="free" @selected($tenant->plan === 'free')>Community Free</option>
                                            <option value="pro_ltd" @selected($tenant->plan === 'pro_ltd')>Pro LTD ($79)</option>
                                            <option value="cloud" @selected($tenant->plan === 'cloud')>Cloud SaaS ($12/mo)</option>
                                        </select>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        @if ($tenant->status === 'active')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                {{ __('Active') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                                <span class="size-1.5 rounded-full bg-rose-500"></span>
                                                {{ __('Suspended') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 dark:text-zinc-300">
                                        {{ $tenant->members_count }} {{ __('members') }} •
                                        {{ $tenant->vaults_count }} {{ __('vaults') }} •
                                        {{ $tenant->device_tokens_count }} {{ __('devices') }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="font-mono text-slate-700 dark:text-zinc-300">
                                            {{ $tenant->storage_limit_mb ? number_format($tenant->storage_limit_mb).' MB' : __('Tier Default') }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button
                                                type="button"
                                                wire:click="openQuotaModal({{ $tenant->id }})"
                                                class="rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800 cursor-pointer"
                                            >
                                                {{ __('Quotas') }}
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="toggleTenantStatus({{ $tenant->id }})"
                                                class="rounded-lg px-2.5 py-1 text-[11px] font-semibold transition-colors cursor-pointer {{ $tenant->status === 'active' ? 'bg-rose-50 text-rose-700 hover:bg-rose-100 dark:bg-rose-950/50 dark:text-rose-300' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-300' }}"
                                            >
                                                {{ $tenant->status === 'active' ? __('Suspend') : __('Activate') }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-slate-500 dark:text-zinc-400">
                                        {{ __('No tenants found matching criteria.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 3: USERS & IMPERSONATION -->
    @if ($activeTab === 'users')
        <div class="flex flex-col gap-4">
            <div class="relative w-full max-w-sm">
                <flux:icon icon="magnifying-glass" class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                <input
                    wire:model.live.debounce.250ms="userSearch"
                    type="text"
                    placeholder="Search users by name or email..."
                    class="w-full rounded-xl border border-gray-200/90 bg-white py-2 pl-9 pr-3 text-xs text-slate-900 shadow-2xs focus:border-[#0D3B29] focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-white"
                />
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-gray-200/80 bg-slate-50/75 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:border-zinc-800 dark:bg-zinc-800/50 dark:text-zinc-400">
                            <tr>
                                <th class="py-3 px-4">{{ __('User') }}</th>
                                <th class="py-3 px-4">{{ __('Role / Super Admin') }}</th>
                                <th class="py-3 px-4">{{ __('Teams') }}</th>
                                <th class="py-3 px-4">{{ __('Joined') }}</th>
                                <th class="py-3 px-4 text-right">{{ __('Support Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800/80">
                            @forelse ($this->users as $user)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="py-3.5 px-4 font-medium text-slate-900 dark:text-white">
                                        <div class="flex items-center gap-2.5">
                                            <flux:avatar :name="$user->name" :initials="$user->initials()" size="sm" />
                                            <div>
                                                <div class="font-bold text-slate-900 dark:text-white">{{ $user->name }}</div>
                                                <div class="text-[11px] text-slate-500 dark:text-zinc-400">{{ $user->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        @if ($user->is_super_admin)
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                                {{ __('Platform Super Admin') }}
                                            </span>
                                        @else
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-zinc-800 dark:text-zinc-400">
                                                {{ __('Tenant Member') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 dark:text-zinc-300">
                                        {{ $user->teams_count }} {{ __('teams') }}
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500 dark:text-zinc-400">
                                        {{ $user->created_at->format('M j, Y') }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            @if ($user->id !== auth()->id())
                                                <button
                                                    type="button"
                                                    wire:click="toggleSuperAdmin({{ $user->id }})"
                                                    class="rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800 cursor-pointer"
                                                >
                                                    {{ $user->is_super_admin ? __('Revoke Admin') : __('Make Admin') }}
                                                </button>

                                                <form method="POST" action="{{ route('admin.impersonate', $user) }}" class="inline">
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="rounded-lg bg-indigo-600 px-2.5 py-1 text-[11px] font-bold text-white shadow-xs hover:bg-indigo-700 transition-colors cursor-pointer"
                                                    >
                                                        {{ __('Login as User') }}
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-[11px] text-slate-400 italic">{{ __('Current Session') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-slate-500 dark:text-zinc-400">
                                        {{ __('No users found matching query.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 4: LICENSES & APPSUMO LTD -->
    @if ($activeTab === 'licenses')
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs lg:col-span-1 dark:border-zinc-800 dark:bg-zinc-900">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-2">{{ __('Generate Lifetime License') }}</h3>
                <p class="text-xs text-slate-500 dark:text-zinc-400 mb-4">{{ __('Generate cryptographically formatted license keys for AppSumo, Lemon Squeezy, or custom deals.') }}</p>

                <div class="flex flex-col gap-3">
                    <div>
                        <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300 block mb-1">{{ __('License Target Plan') }}</label>
                        <select
                            wire:model="newLicenseTier"
                            class="w-full rounded-xl border border-gray-200/90 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-2xs focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 cursor-pointer"
                        >
                            <option value="pro_ltd">{{ __('Pro Lifetime Deal ($79 LTD)') }}</option>
                            <option value="cloud">{{ __('Synkk Cloud Managed SaaS') }}</option>
                        </select>
                    </div>

                    <button
                        type="button"
                        wire:click="generateLicenseKey"
                        class="rounded-xl bg-[#0D3B29] py-2.5 text-xs font-bold text-white shadow-xs hover:bg-[#092B1E] transition-colors cursor-pointer"
                    >
                        {{ __('Generate New Key') }}
                    </button>

                    @if ($generatedKey)
                        <div class="mt-3 rounded-xl border border-amber-500/30 bg-amber-50/50 p-3 dark:border-amber-500/20 dark:bg-amber-950/20">
                            <span class="text-[10px] font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wider block mb-1">{{ __('Generated License Key') }}</span>
                            <div class="flex items-center justify-between">
                                <code class="font-mono text-xs font-bold text-slate-900 dark:text-white select-all">{{ $generatedKey }}</code>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Active Licenses Directory -->
            <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs lg:col-span-2 dark:border-zinc-800 dark:bg-zinc-900">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">{{ __('Active Tenant Licenses & Activations') }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-gray-200/80 text-[11px] font-bold uppercase text-slate-500 dark:border-zinc-800 dark:text-zinc-400">
                            <tr>
                                <th class="py-2.5 px-3">{{ __('Tenant') }}</th>
                                <th class="py-2.5 px-3">{{ __('License Key') }}</th>
                                <th class="py-2.5 px-3">{{ __('Status') }}</th>
                                <th class="py-2.5 px-3">{{ __('Activated At') }}</th>
                                <th class="py-2.5 px-3 text-right">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800/60">
                            @forelse (Team::whereNotNull('license_key')->latest('license_activated_at')->get() as $licensedTeam)
                                <tr>
                                    <td class="py-2.5 px-3 font-semibold text-slate-900 dark:text-white">{{ $licensedTeam->name }}</td>
                                    <td class="py-2.5 px-3 font-mono text-[11px] text-slate-600 dark:text-zinc-300">{{ $licensedTeam->license_key }}</td>
                                    <td class="py-2.5 px-3">
                                        <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            {{ $licensedTeam->license_status }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-500 dark:text-zinc-400">
                                        {{ $licensedTeam->license_activated_at?->format('M j, Y H:i') ?? '—' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        <button
                                            type="button"
                                            wire:click="revokeLicense({{ $licensedTeam->id }})"
                                            class="text-[11px] font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400 cursor-pointer"
                                        >
                                            {{ __('Revoke') }}
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-slate-500 dark:text-zinc-400">
                                        {{ __('No team licenses activated yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 5: SYSTEM & INFRASTRUCTURE -->
    @if ($activeTab === 'system')
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">{{ __('Environment & Framework') }}</h3>
                <dl class="divide-y divide-gray-100 dark:divide-zinc-800/60 text-xs">
                    <div class="py-2 flex justify-between">
                        <dt class="text-slate-500 dark:text-zinc-400">{{ __('Application Environment') }}</dt>
                        <dd class="font-bold text-slate-900 dark:text-white uppercase">{{ app()->environment() }}</dd>
                    </div>
                    <div class="py-2 flex justify-between">
                        <dt class="text-slate-500 dark:text-zinc-400">{{ __('PHP Version') }}</dt>
                        <dd class="font-mono text-slate-900 dark:text-white">{{ PHP_VERSION }}</dd>
                    </div>
                    <div class="py-2 flex justify-between">
                        <dt class="text-slate-500 dark:text-zinc-400">{{ __('Laravel Version') }}</dt>
                        <dd class="font-mono text-slate-900 dark:text-white">{{ app()->version() }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">{{ __('Storage & Drivers') }}</h3>
                <dl class="divide-y divide-gray-100 dark:divide-zinc-800/60 text-xs">
                    <div class="py-2 flex justify-between">
                        <dt class="text-slate-500 dark:text-zinc-400">{{ __('Vault Disk') }}</dt>
                        <dd class="font-mono font-bold text-slate-900 dark:text-white">{{ config('synkk.storage_disk', 'local') }}</dd>
                    </div>
                    <div class="py-2 flex justify-between">
                        <dt class="text-slate-500 dark:text-zinc-400">{{ __('Database Driver') }}</dt>
                        <dd class="font-mono text-slate-900 dark:text-white">{{ config('database.default') }}</dd>
                    </div>
                    <div class="py-2 flex justify-between">
                        <dt class="text-slate-500 dark:text-zinc-400">{{ __('Queue Connection') }}</dt>
                        <dd class="font-mono text-slate-900 dark:text-white">{{ config('queue.default') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">{{ __('Sync Telemetry & Limits') }}</h3>
                <dl class="divide-y divide-gray-100 dark:divide-zinc-800/60 text-xs">
                    <div class="py-2 flex justify-between">
                        <dt class="text-slate-500 dark:text-zinc-400">{{ __('Max Batch Sync Size') }}</dt>
                        <dd class="font-bold text-slate-900 dark:text-white">{{ config('synkk.max_batch_size', 100) }} files</dd>
                    </div>
                    <div class="py-2 flex justify-between">
                        <dt class="text-slate-500 dark:text-zinc-400">{{ __('Version Retention') }}</dt>
                        <dd class="font-bold text-slate-900 dark:text-white">{{ config('synkk.version_retention_limit', 25) }} snapshots</dd>
                    </div>
                    <div class="py-2 flex justify-between">
                        <dt class="text-slate-500 dark:text-zinc-400">{{ __('Atomic Abort Guard') }}</dt>
                        <dd class="font-bold text-emerald-600 dark:text-emerald-400">10% threshold</dd>
                    </div>
                </dl>
            </div>
        </div>
    @endif

    <!-- Quota Modal -->
    <flux:modal name="edit-quota" class="w-full max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Edit Tenant Quotas') }}</flux:heading>
                <flux:subheading>{{ __('Customize limits for tenant') }} <strong>{{ $editingTeamName }}</strong>.</flux:subheading>
            </div>

            <div class="space-y-3">
                <flux:input
                    wire:model="editStorageLimitMb"
                    type="number"
                    label="{{ __('Storage Limit (MB)') }}"
                    placeholder="1000"
                />
                <flux:input
                    wire:model="editMaxDevices"
                    type="number"
                    label="{{ __('Max Devices') }}"
                    placeholder="3"
                />
                <flux:input
                    wire:model="editMaxVaults"
                    type="number"
                    label="{{ __('Max Vaults') }}"
                    placeholder="1"
                />
                <flux:input
                    wire:model="editMaxMembers"
                    type="number"
                    label="{{ __('Max Team Members') }}"
                    placeholder="3"
                />
            </div>

            <div class="flex items-center justify-between pt-2">
                <flux:button
                    type="button"
                    wire:click="resetQuotasToDefault({{ $editingTeamId ?? 0 }})"
                    variant="subtle"
                    size="sm"
                >
                    {{ __('Restore Tier Defaults') }}
                </flux:button>

                <div class="flex items-center gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" size="sm">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="saveQuotas" variant="primary" size="sm">{{ __('Save Quotas') }}</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
