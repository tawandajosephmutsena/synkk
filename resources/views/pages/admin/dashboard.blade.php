<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultChangeLog;
use App\Models\VaultFile;
use App\Models\VaultFileVersion;
use App\Services\PlanService;
use App\Services\VaultAnalyticsService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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

    // Vault Intelligence & Analytics state
    public ?int $selectedAnalyticsVaultId = null;
    public string $analyticsTimeframe = '30d';
    public string $analyticsSearchQuery = '';
    public string $analyticsActionFilter = 'all';

    // Quota edit state
    public ?int $editingTeamId = null;
    public string $editingTeamName = '';
    public ?int $editStorageLimitMb = null;
    public ?int $editMaxDevices = null;
    public ?int $editMaxVaults = null;
    public ?int $editMaxMembers = null;

    // Tenant deep-dive inspection state
    public ?int $inspectingTeamId = null;

    // Telemetry & Security audit state
    public string $telemetrySearch = '';
    public string $telemetryActionFilter = 'all';
    public bool $telemetrySecretsOnly = false;

    // License generator state
    public string $newLicenseTier = 'pro_ltd';
    public ?string $generatedKey = null;
    public int $batchLicenseCount = 5;
    public array $batchGeneratedKeys = [];

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    #[Computed]
    public function stats(): array
    {
        $totalTenants = Team::count();
        $totalUsers = User::count();
        $totalVaults = Vault::count();
        $totalFiles = VaultFile::where('is_deleted', false)->count();
        $totalBytes = (int) VaultFile::where('is_deleted', false)->sum('size');
        $activeDevices = DeviceToken::where('is_wiped', false)->count();
        $syncEvents = VaultChangeLog::count();
        $dlpAlerts = VaultChangeLog::where('has_secrets', true)->count();

        return [
            'total_tenants' => $totalTenants,
            'total_users' => $totalUsers,
            'total_vaults' => $totalVaults,
            'total_files' => $totalFiles,
            'total_storage' => $totalBytes > 0 ? Number::fileSize($totalBytes, precision: 1) : '0 B',
            'active_devices' => $activeDevices,
            'sync_events' => $syncEvents,
            'dlp_alerts' => $dlpAlerts,
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

    #[Computed]
    public function telemetryLogs(): Collection
    {
        $query = VaultChangeLog::with(['vault', 'user'])->latest('created_at');

        if ($this->telemetrySecretsOnly) {
            $query->where('has_secrets', true);
        }

        if ($this->telemetryActionFilter !== 'all') {
            $query->where('action', $this->telemetryActionFilter);
        }

        if (filled($this->telemetrySearch)) {
            $search = $this->telemetrySearch;
            $query->where(function ($q) use ($search) {
                $q->where('path', 'like', "%{$search}%")
                    ->orWhere('device_name', 'like', "%{$search}%");
            });
        }

        return $query->take(50)->get();
    }

    #[Computed]
    public function inspectedTeam(): ?Team
    {
        if (! $this->inspectingTeamId) {
            return null;
        }

        return Team::with(['vaults', 'deviceTokens.user', 'members'])->find($this->inspectingTeamId);
    }

    #[Computed]
    public function platformBreakdown(): array
    {
        return DeviceToken::where('is_wiped', false)
            ->whereNotNull('client_platform')
            ->select('client_platform', DB::raw('count(*) as count'))
            ->groupBy('client_platform')
            ->pluck('count', 'client_platform')
            ->toArray();
    }

    #[Computed]
    public function databaseTableMetrics(): array
    {
        return [
            'Tenants (Teams)' => Team::count(),
            'Users' => User::count(),
            'Vaults' => Vault::count(),
            'Active Files' => VaultFile::where('is_deleted', false)->count(),
            'File Versions' => VaultFileVersion::count(),
            'Sync Event Logs' => VaultChangeLog::count(),
            'Device Tokens' => DeviceToken::count(),
        ];
    }

    public function inspectTenant(int $teamId): void
    {
        $this->inspectingTeamId = $teamId;
        $this->dispatch('open-modal', name: 'inspect-tenant');
    }

    public function revokeAllTenantDevices(int $teamId): void
    {
        $team = Team::findOrFail($teamId);
        $count = $team->deviceTokens()->where('is_wiped', false)->count();

        $team->deviceTokens()->where('is_wiped', false)->update([
            'is_wiped' => true,
            'wiped_at' => now(),
        ]);

        Flux::toast(variant: 'warning', text: __("Emergency Device Revocation: :count device tokens wiped for ':name'.", [
            'count' => $count,
            'name' => $team->name,
        ]));
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
        $this->batchGeneratedKeys = [];

        Flux::toast(variant: 'success', text: __('New commercial license key generated.'));
    }

    public function generateBatchLicenses(): void
    {
        $this->validate([
            'batchLicenseCount' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $prefix = match ($this->newLicenseTier) {
            'cloud' => 'SYNK-CLOUD',
            default => 'SYNK-PRO',
        };

        $keys = [];
        for ($i = 0; $i < $this->batchLicenseCount; $i++) {
            $s1 = strtoupper(Str::random(4));
            $s2 = strtoupper(Str::random(4));
            $s3 = strtoupper(Str::random(4));
            $keys[] = "{$prefix}-{$s1}-{$s2}-{$s3}";
        }

        $this->batchGeneratedKeys = $keys;
        $this->generatedKey = $keys[0];

        Flux::toast(variant: 'success', text: __(':count commercial license keys generated.', ['count' => count($keys)]));
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

    public function dismissDlpAlert(int $changeLogId): void
    {
        $log = VaultChangeLog::findOrFail($changeLogId);
        $log->update(['has_secrets' => false]);

        Flux::toast(variant: 'success', text: __('DLP security alert acknowledged and resolved.'));
    }

    public function clearApplicationCache(): void
    {
        Artisan::call('cache:clear');
        Flux::toast(variant: 'success', text: __('Application and Redis/File cache successfully flushed.'));
    }

    public function pruneOldSnapshots(): void
    {
        Artisan::call('vaults:prune-deleted', ['--days' => 30]);
        Flux::toast(variant: 'success', text: __('Old deleted vault snapshots pruned past 30-day retention.'));
    }

    #[Computed]
    public function analyticsStats(): array
    {
        $service = app(VaultAnalyticsService::class);
        if ($this->selectedAnalyticsVaultId) {
            $vault = Vault::find($this->selectedAnalyticsVaultId);
            if ($vault) {
                return $service->getVaultStats($vault, $this->analyticsTimeframe);
            }
        }

        return $service->getGlobalStats($this->analyticsTimeframe);
    }

    #[Computed]
    public function analyticsWikiGraph(): array
    {
        $service = app(VaultAnalyticsService::class);

        return $service->getWikiLinkGraphStats($this->selectedAnalyticsVaultId);
    }

    #[Computed]
    public function analyticsLeaderboard(): array
    {
        $service = app(VaultAnalyticsService::class);

        return $service->getContributorLeaderboard($this->selectedAnalyticsVaultId, $this->analyticsTimeframe);
    }

    #[Computed]
    public function analyticsVelocity(): array
    {
        $service = app(VaultAnalyticsService::class);

        return $service->getActivityVelocityTimeline($this->selectedAnalyticsVaultId, 14);
    }

    #[Computed]
    public function analyticsRecentFeed(): Collection
    {
        $service = app(VaultAnalyticsService::class);

        return $service->getRecentChangeFeed(
            $this->selectedAnalyticsVaultId,
            $this->analyticsActionFilter !== 'all' ? $this->analyticsActionFilter : null,
            null,
            filled($this->analyticsSearchQuery) ? $this->analyticsSearchQuery : null,
            35
        );
    }

    #[Computed]
    public function allVaultsList(): Collection
    {
        return Vault::with('team')->orderBy('name')->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 font-sans text-slate-900 dark:text-slate-100">
    <!-- Top Platform Bar -->
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between border-b border-gray-200/80 pb-4 dark:border-zinc-800">
        <div class="flex items-center gap-3">
            <div class="flex size-11 items-center justify-center rounded-2xl bg-amber-500/15 text-amber-600 shadow-2xs dark:bg-amber-500/20 dark:text-amber-400">
                <flux:icon icon="shield-check" class="size-6" />
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-black tracking-tight text-slate-900 dark:text-white">{{ __('Synkk Platform Super Admin') }}</h1>
                    <span class="rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">{{ __('Multi-Tenant SaaS Control') }}</span>
                    @if ($this->stats['dlp_alerts'] > 0)
                        <span class="inline-flex items-center gap-1 rounded-md bg-rose-100 px-2 py-0.5 text-[11px] font-bold text-rose-800 animate-pulse dark:bg-rose-950/60 dark:text-rose-300">
                            {{ $this->stats['dlp_alerts'] }} {{ __('DLP Alerts') }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Multi-tenant SaaS governance, quota customizer, fleet telemetry, secret audits, and support impersonation.') }}</p>
            </div>
        </div>

        <!-- Tab Controls -->
        <div class="flex flex-wrap items-center gap-1 rounded-xl bg-slate-200/60 p-1 dark:bg-zinc-800/80">
            <button
                type="button"
                wire:click="setTab('overview')"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer {{ $activeTab === 'overview' ? 'bg-white text-slate-900 shadow-xs dark:bg-zinc-900 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white' }}"
            >
                {{ __('Overview') }}
            </button>
            <button
                type="button"
                wire:click="setTab('analytics')"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer {{ $activeTab === 'analytics' ? 'bg-white text-slate-900 shadow-xs dark:bg-zinc-900 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white' }}"
            >
                <span class="flex items-center gap-1.5">
                    <flux:icon icon="chart-bar-square" class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                    <span>{{ __('Vault Intelligence') }}</span>
                </span>
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
                wire:click="setTab('telemetry')"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer relative {{ $activeTab === 'telemetry' ? 'bg-white text-slate-900 shadow-xs dark:bg-zinc-900 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white' }}"
            >
                {{ __('Telemetry & DLP') }}
                @if ($this->stats['dlp_alerts'] > 0)
                    <span class="size-2 rounded-full bg-rose-500 absolute -top-0.5 -right-0.5"></span>
                @endif
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
                {{ __('System & Ops') }}
            </button>
        </div>
    </div>

    <!-- TAB 1: OVERVIEW -->
    @if ($activeTab === 'overview')
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ __('Total Tenants') }}</span>
                    <span class="flex size-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
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
                    <span class="flex size-7 items-center justify-center rounded-lg bg-teal-50 text-teal-600 dark:bg-teal-950/50 dark:text-teal-400">
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

                    <div class="rounded-xl border border-teal-500/30 bg-teal-50/40 p-3.5 dark:border-teal-500/20 dark:bg-teal-950/20">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-teal-800 dark:text-teal-300">{{ __('Synkk Cloud') }}</span>
                            <span class="rounded bg-teal-200 px-1.5 py-0.5 text-[10px] font-bold text-teal-900 dark:bg-teal-900/60 dark:text-teal-300">$12/mo</span>
                        </div>
                        <p class="mt-2 text-xl font-bold text-teal-900 dark:text-teal-200">{{ Team::where('plan', 'cloud')->count() }}</p>
                        <p class="text-[11px] text-teal-700/80 dark:text-teal-400/80 mt-1">{{ __('50 Vaults • 100 Devices • CRDT & RAG') }}</p>
                    </div>
                </div>

                <!-- Platform Devices breakdown -->
                <div class="mt-5 border-t border-gray-100 pt-4 dark:border-zinc-800">
                    <h4 class="text-xs font-bold text-slate-700 dark:text-zinc-300 uppercase tracking-wider mb-2.5">{{ __('Connected Client Platform Distribution') }}</h4>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($this->platformBreakdown as $platform => $count)
                            <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 dark:border-zinc-800 dark:bg-zinc-800">
                                <span class="font-bold text-slate-900 dark:text-white uppercase text-xs">{{ $platform }}</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-zinc-700 dark:text-zinc-300">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">{{ __('Platform Fast Actions') }}</h3>
                <div class="flex flex-col gap-2.5">
                    <button
                        type="button"
                        wire:click="setTab('telemetry')"
                        class="flex items-center justify-between rounded-xl border border-slate-200 p-3 text-start hover:bg-slate-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50 cursor-pointer"
                    >
                        <div class="flex items-center gap-2.5">
                            <flux:icon icon="bolt" class="size-4 text-emerald-500" />
                            <span class="text-xs font-semibold text-slate-800 dark:text-zinc-200">{{ __('Live Fleet Sync Telemetry') }}</span>
                        </div>
                        <flux:icon icon="chevron-right" class="size-4 text-slate-400" />
                    </button>

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
                            <span class="text-xs font-semibold text-slate-800 dark:text-zinc-200">{{ __('Generate AppSumo Batch Keys') }}</span>
                        </div>
                        <flux:icon icon="chevron-right" class="size-4 text-slate-400" />
                    </button>

                    <button
                        type="button"
                        wire:click="setTab('users')"
                        class="flex items-center justify-between rounded-xl border border-slate-200 p-3 text-start hover:bg-slate-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50 cursor-pointer"
                    >
                        <div class="flex items-center gap-2.5">
                            <flux:icon icon="user-circle" class="size-4 text-emerald-600 dark:text-emerald-400" />
                            <span class="text-xs font-semibold text-slate-800 dark:text-zinc-200">{{ __('Customer Support Impersonation') }}</span>
                        </div>
                        <flux:icon icon="chevron-right" class="size-4 text-slate-400" />
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB: VAULT INTELLIGENCE & ANALYTICS -->
    @if ($activeTab === 'analytics')
        <div class="flex flex-col gap-6">
            <!-- Filter & Scope Header -->
            <div class="flex flex-col gap-4 rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 md:flex-row md:items-center md:justify-between">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-3">
                    <div class="flex items-center gap-2">
                        <flux:icon icon="chart-pie" class="size-5 text-[#0D3B29] dark:text-emerald-400" />
                        <span class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Scope & Target:') }}</span>
                    </div>
                    <select
                        wire:model.live="selectedAnalyticsVaultId"
                        class="rounded-xl border border-gray-200/90 bg-slate-50/75 px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-2xs focus:border-[#0D3B29] focus:outline-none dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-200 cursor-pointer"
                    >
                        <option value="">{{ __('All Platform Vaults (Aggregated)') }}</option>
                        @foreach ($this->allVaultsList as $v)
                            <option value="{{ $v->id }}">{{ $v->name }} ({{ $v->team?->name ?? 'No Team' }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ __('Timeframe:') }}</span>
                    <div class="flex items-center rounded-xl bg-slate-100 p-1 dark:bg-zinc-800">
                        @foreach (['24h' => '24h', '7d' => '7d', '30d' => '30d', '90d' => '90d', 'all' => 'All'] as $tfKey => $tfLabel)
                            <button
                                type="button"
                                wire:click="$set('analyticsTimeframe', '{{ $tfKey }}')"
                                class="rounded-lg px-2.5 py-1 text-xs font-semibold transition-all cursor-pointer {{ $analyticsTimeframe === $tfKey ? 'bg-white text-[#0D3B29] shadow-2xs dark:bg-zinc-900 dark:text-emerald-400' : 'text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white' }}"
                            >
                                {{ $tfLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Top 4 Analytics KPI Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Notes & Words -->
                <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ __('Notes & Knowledge') }}</span>
                        <span class="flex size-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
                            <flux:icon icon="document-text" class="size-4" />
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ number_format($this->analyticsStats['notes_count']) }}</span>
                        <span class="text-xs text-slate-500 dark:text-zinc-400">{{ __('notes') }}</span>
                    </div>
                    <div class="mt-2.5 flex items-center justify-between border-t border-gray-100 pt-2 text-[11px] text-slate-500 dark:border-zinc-800/80 dark:text-zinc-400">
                        <span>~{{ number_format($this->analyticsStats['estimated_words']) }} {{ __('words') }}</span>
                        <span class="font-medium text-emerald-700 dark:text-emerald-400">~{{ $this->analyticsStats['reading_time_minutes'] }} {{ __('min read') }}</span>
                    </div>
                </div>

                <!-- Storage & Total Files -->
                <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ __('Total Storage & Files') }}</span>
                        <span class="flex size-7 items-center justify-center rounded-lg bg-teal-50 text-teal-700 dark:bg-teal-950/50 dark:text-teal-400">
                            <flux:icon icon="circle-stack" class="size-4" />
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $this->analyticsStats['total_storage_formatted'] }}</span>
                        <span class="text-xs text-slate-500 dark:text-zinc-400">({{ number_format($this->analyticsStats['total_files']) }} {{ __('files') }})</span>
                    </div>
                    <div class="mt-2.5 flex items-center justify-between border-t border-gray-100 pt-2 text-[11px] text-slate-500 dark:border-zinc-800/80 dark:text-zinc-400">
                        <span>{{ number_format($this->analyticsStats['images_count']) }} {{ __('images') }} ({{ $this->analyticsStats['images_size_formatted'] }})</span>
                        <span class="font-medium text-teal-700 dark:text-teal-400">{{ $this->analyticsStats['canvas_count'] }} {{ __('canvases') }}</span>
                    </div>
                </div>

                <!-- Wiki-Links & Graph Density -->
                <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ __('Wiki-Links & Network') }}</span>
                        <span class="flex size-7 items-center justify-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400">
                            <flux:icon icon="share" class="size-4" />
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ number_format($this->analyticsWikiGraph['total_links']) }}</span>
                        <span class="text-xs text-slate-500 dark:text-zinc-400">{{ __('connections') }}</span>
                    </div>
                    <div class="mt-2.5 flex items-center justify-between border-t border-gray-100 pt-2 text-[11px] text-slate-500 dark:border-zinc-800/80 dark:text-zinc-400">
                        <span>{{ $this->analyticsWikiGraph['density'] }} {{ __('links/note') }}</span>
                        <span class="font-medium text-amber-700 dark:text-amber-400">{{ $this->analyticsWikiGraph['orphan_notes_count'] }} {{ __('orphans') }}</span>
                    </div>
                </div>

                <!-- Sync & Mutation Velocity -->
                <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ __('Sync Velocity (:tf)', ['tf' => $analyticsTimeframe]) }}</span>
                        <span class="flex size-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
                            <flux:icon icon="bolt" class="size-4" />
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ number_format($this->analyticsStats['period_changes']) }}</span>
                        <span class="text-xs text-slate-500 dark:text-zinc-400">{{ __('mutations') }}</span>
                    </div>
                    <div class="mt-2.5 flex items-center justify-between border-t border-gray-100 pt-2 text-[11px] text-slate-500 dark:border-zinc-800/80 dark:text-zinc-400">
                        <span>{{ $this->analyticsStats['period_creations'] }} + / {{ $this->analyticsStats['period_updates'] }} ~ / {{ $this->analyticsStats['period_deletions'] }} -</span>
                        <span class="font-medium text-emerald-700 dark:text-emerald-400">{{ count($this->analyticsLeaderboard) }} {{ __('collaborators') }}</span>
                    </div>
                </div>
            </div>

            <!-- Content Distribution & 14-Day Velocity Grid -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- 14-Day Activity Velocity Chart -->
                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs lg:col-span-2 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('14-Day Mutation & Sync Velocity') }}</h3>
                            <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Daily note creations, edits, and file updates across the selected vault scope.') }}</p>
                        </div>
                        <div class="flex items-center gap-3 text-[11px]">
                            <span class="flex items-center gap-1 text-slate-600 dark:text-zinc-300">
                                <span class="size-2.5 rounded-full bg-emerald-500"></span> {{ __('Created') }}
                            </span>
                            <span class="flex items-center gap-1 text-slate-600 dark:text-zinc-300">
                                <span class="size-2.5 rounded-full bg-teal-500"></span> {{ __('Updated') }}
                            </span>
                            <span class="flex items-center gap-1 text-slate-600 dark:text-zinc-300">
                                <span class="size-2.5 rounded-full bg-rose-400"></span> {{ __('Deleted') }}
                            </span>
                        </div>
                    </div>

                    @php
                        $maxVelocity = collect($this->analyticsVelocity)->max('total') ?: 1;
                    @endphp

                    <div class="mt-6 flex h-44 items-end gap-2 border-b border-gray-200/80 pb-2 dark:border-zinc-800">
                        @foreach ($this->analyticsVelocity as $vPoint)
                            @php
                                $heightPercent = max(6, min(100, round(($vPoint['total'] / $maxVelocity) * 100)));
                            @endphp
                            <div class="flex flex-1 flex-col items-center gap-1.5 h-full justify-end group relative" title="{{ $vPoint['date'] }}: {{ $vPoint['total'] }} mutations ({{ $vPoint['created'] }} created, {{ $vPoint['updated'] }} updated, {{ $vPoint['deleted'] }} deleted)">
                                <div class="w-full max-w-[28px] rounded-t-md bg-slate-100 flex flex-col justify-end overflow-hidden dark:bg-zinc-800" style="height: {{ $heightPercent }}%;">
                                    @if ($vPoint['created'] > 0)
                                        <div class="w-full bg-emerald-500" style="height: {{ round(($vPoint['created'] / max(1, $vPoint['total'])) * 100) }}%;"></div>
                                    @endif
                                    @if ($vPoint['updated'] > 0)
                                        <div class="w-full bg-teal-500" style="height: {{ round(($vPoint['updated'] / max(1, $vPoint['total'])) * 100) }}%;"></div>
                                    @endif
                                    @if ($vPoint['deleted'] > 0)
                                        <div class="w-full bg-rose-400" style="height: {{ round(($vPoint['deleted'] / max(1, $vPoint['total'])) * 100) }}%;"></div>
                                    @endif
                                </div>
                                <span class="text-[10px] text-slate-400 group-hover:text-slate-900 dark:group-hover:text-white transition-colors">{{ $vPoint['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Asset Composition & Media Breakdown -->
                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 flex flex-col justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">{{ __('Asset & File Composition') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 mb-4">{{ __('Format breakdown of all files stored in the vault.') }}</p>

                        <!-- Segmented Bar -->
                        @php
                            $totalF = max(1, $this->analyticsStats['total_files']);
                            $notesPct = round(($this->analyticsStats['notes_count'] / $totalF) * 100);
                            $imagesPct = round(($this->analyticsStats['images_count'] / $totalF) * 100);
                            $canvasPct = round(($this->analyticsStats['canvas_count'] / $totalF) * 100);
                            $othersPct = max(0, 100 - $notesPct - $imagesPct - $canvasPct);
                        @endphp

                        <div class="h-3.5 w-full rounded-full bg-slate-100 flex overflow-hidden dark:bg-zinc-800 mb-4">
                            <div class="bg-emerald-600 transition-all" style="width: {{ $notesPct }}%;" title="Markdown Notes: {{ $notesPct }}%"></div>
                            <div class="bg-teal-500 transition-all" style="width: {{ $imagesPct }}%;" title="Images: {{ $imagesPct }}%"></div>
                            <div class="bg-amber-500 transition-all" style="width: {{ $canvasPct }}%;" title="Canvases: {{ $canvasPct }}%"></div>
                            <div class="bg-slate-400 transition-all" style="width: {{ $othersPct }}%;" title="Other Files: {{ $othersPct }}%"></div>
                        </div>

                        <!-- Image Format Breakdown Pills -->
                        <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-2">{{ __('Image Attachments by Format') }}</h4>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($this->analyticsStats['image_breakdown'] as $ext => $imgData)
                                @if ($imgData['count'] > 0)
                                    <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/80 p-2 text-xs dark:border-zinc-800 dark:bg-zinc-800/40">
                                        <span class="font-mono font-bold uppercase text-slate-700 dark:text-zinc-300">.{{ $ext }}</span>
                                        <div class="text-right">
                                            <div class="font-bold text-slate-900 dark:text-white">{{ $imgData['count'] }}</div>
                                            <div class="text-[10px] text-slate-400">{{ $imgData['size_formatted'] }}</div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4 border-t border-gray-100 pt-3 text-[11px] text-slate-500 dark:border-zinc-800 dark:text-zinc-400">
                        {{ __('Avg note length: ~:words words (:chars characters)', [
                            'words' => $this->analyticsStats['notes_count'] > 0 ? round($this->analyticsStats['estimated_words'] / $this->analyticsStats['notes_count']) : 0,
                            'chars' => $this->analyticsStats['notes_count'] > 0 ? round($this->analyticsStats['total_characters'] / $this->analyticsStats['notes_count']) : 0,
                        ]) }}
                    </div>
                </div>
            </div>

            <!-- Knowledge Graph Hubs & Contributor Leaderboard Grid -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Top Authority Hub Notes -->
                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="link" class="size-4 text-emerald-600 dark:text-emerald-400" />
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Knowledge Graph Hubs & Top Linked Notes') }}</h3>
                        </div>
                        <span class="rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                            {{ $this->analyticsWikiGraph['unique_targets_count'] }} {{ __('connected targets') }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-zinc-400 mb-4">{{ __('Most central notes with incoming [[wiki-links]] from other documents.') }}</p>

                    <div class="flex flex-col divide-y divide-gray-100 dark:divide-zinc-800">
                        @forelse ($this->analyticsWikiGraph['top_hubs'] as $hub)
                            <div class="flex items-center justify-between py-2.5">
                                <div class="flex items-center gap-2.5 truncate">
                                    <span class="flex size-6 shrink-0 items-center justify-center rounded-md bg-emerald-100 text-xs font-bold text-[#0D3B29] dark:bg-emerald-950/80 dark:text-emerald-300">
                                        #{{ $loop->iteration }}
                                    </span>
                                    <span class="font-mono text-xs font-semibold text-slate-800 truncate dark:text-zinc-200">
                                        [[{{ $hub['title'] }}]]
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                                        {{ $hub['inbound_links'] }} {{ __('inbound links') }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="py-6 text-center text-xs text-slate-400">{{ __('No internal [[wiki-links]] detected in the vault notes yet.') }}</div>
                        @endforelse
                    </div>
                </div>

                <!-- Active Collaborators & Leaderboard -->
                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="user-group" class="size-4 text-teal-600 dark:text-teal-400" />
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Collaborator Velocity Leaderboard') }}</h3>
                        </div>
                        <span class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Timeframe: :tf', ['tf' => $analyticsTimeframe]) }}</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-zinc-400 mb-4">{{ __('Top team members actively authoring, editing, and syncing changes.') }}</p>

                    <div class="flex flex-col divide-y divide-gray-100 dark:divide-zinc-800">
                        @forelse ($this->analyticsLeaderboard as $contributor)
                            <div class="flex items-center justify-between py-2.5">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-xs font-bold text-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
                                        {{ substr($contributor['user_name'], 0, 2) }}
                                    </span>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900 dark:text-white">{{ $contributor['user_name'] }}</div>
                                        <div class="text-[10px] text-slate-400">
                                            {{ $contributor['device_name'] ?? 'Obsidian Plugin' }} • {{ $contributor['last_active_human'] }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 text-right">
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">{{ $contributor['mutations_count'] }} {{ __('edits') }}</div>
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-mono text-slate-600 dark:bg-zinc-800 dark:text-zinc-400">
                                        +{{ $contributor['creations'] }} ~{{ $contributor['updates'] }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="py-6 text-center text-xs text-slate-400">{{ __('No collaborator activity recorded in this timeframe.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Real-Time Mutation & Change Stream Table -->
            <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between border-b border-gray-200/80 dark:border-zinc-800">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Real-Time Vault Change Stream') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Complete audit history of file additions, modifications, and deletions.') }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="relative w-48 sm:w-64">
                            <flux:icon icon="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-400" />
                            <input
                                wire:model.live.debounce.250ms="analyticsSearchQuery"
                                type="text"
                                placeholder="Filter path or device..."
                                class="w-full rounded-xl border border-gray-200/90 bg-white py-1.5 pl-8 pr-3 text-xs text-slate-900 shadow-2xs focus:border-[#0D3B29] focus:outline-none dark:border-zinc-800 dark:bg-zinc-800 dark:text-white"
                            />
                        </div>

                        <select
                            wire:model.live="analyticsActionFilter"
                            class="rounded-xl border border-gray-200/90 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs focus:outline-none dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-200 cursor-pointer"
                        >
                            <option value="all">{{ __('All Actions') }}</option>
                            <option value="created">{{ __('Created') }}</option>
                            <option value="updated">{{ __('Updated') }}</option>
                            <option value="deleted">{{ __('Deleted') }}</option>
                            <option value="conflict">{{ __('Conflict') }}</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-gray-200/80 bg-slate-50/75 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:border-zinc-800 dark:bg-zinc-800/50 dark:text-zinc-400">
                            <tr>
                                <th class="py-3 px-4">{{ __('Timestamp') }}</th>
                                <th class="py-3 px-4">{{ __('Vault') }}</th>
                                <th class="py-3 px-4">{{ __('File Path') }}</th>
                                <th class="py-3 px-4">{{ __('Action') }}</th>
                                <th class="py-3 px-4">{{ __('Contributor & Device') }}</th>
                                <th class="py-3 px-4">{{ __('Size') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800/80">
                            @forelse ($this->analyticsRecentFeed as $feedItem)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="py-3 px-4 whitespace-nowrap text-slate-500 dark:text-zinc-400 text-[11px]">
                                        {{ $feedItem->created_at->format('M d, H:i:s') }}
                                        <span class="block text-[10px] text-slate-400">({{ $feedItem->created_at->diffForHumans() }})</span>
                                    </td>
                                    <td class="py-3 px-4 font-medium text-slate-800 dark:text-zinc-200">
                                        {{ $feedItem->vault?->name ?? 'Vault #' . $feedItem->vault_id }}
                                    </td>
                                    <td class="py-3 px-4 font-mono text-slate-700 dark:text-zinc-300 max-w-xs truncate" title="{{ $feedItem->path }}">
                                        {{ $feedItem->path }}
                                    </td>
                                    <td class="py-3 px-4">
                                        @if ($feedItem->action === 'created')
                                            <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">{{ __('CREATED') }}</span>
                                        @elseif ($feedItem->action === 'updated')
                                            <span class="rounded bg-teal-100 px-1.5 py-0.5 text-[10px] font-bold text-teal-800 dark:bg-teal-950/60 dark:text-teal-300">{{ __('UPDATED') }}</span>
                                        @elseif ($feedItem->action === 'conflict')
                                            <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">{{ __('CONFLICT') }}</span>
                                        @else
                                            <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">{{ __('DELETED') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-slate-600 dark:text-zinc-300">
                                        <div class="font-medium text-slate-900 dark:text-white">{{ $feedItem->user?->name ?? 'Obsidian Sync' }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $feedItem->device_name ?? 'Desktop Client' }}</div>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-[11px] text-slate-500 dark:text-zinc-400">
                                        {{ $feedItem->file_size ? \Illuminate\Support\Number::fileSize($feedItem->file_size, precision: 1) : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-xs text-slate-400">{{ __('No change records match the filter criteria.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
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
                                                <button
                                                    type="button"
                                                    wire:click="inspectTenant({{ $tenant->id }})"
                                                    class="font-bold text-slate-900 hover:text-emerald-700 dark:text-white dark:hover:text-emerald-400 cursor-pointer text-left"
                                                >
                                                    {{ $tenant->name }}
                                                </button>
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
                                                wire:click="inspectTenant({{ $tenant->id }})"
                                                class="rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-200 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700 cursor-pointer"
                                            >
                                                {{ __('Deep Dive') }}
                                            </button>
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
                                                        class="rounded-lg bg-[#0D3B29] px-2.5 py-1 text-[11px] font-bold text-white shadow-xs hover:bg-[#0D3B29]/90 dark:bg-emerald-600 dark:hover:bg-emerald-500 transition-colors cursor-pointer"
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

    <!-- TAB 4: TELEMETRY & DLP SECURITY ALERTS -->
    @if ($activeTab === 'telemetry')
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full max-w-sm">
                    <flux:icon icon="magnifying-glass" class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                    <input
                        wire:model.live.debounce.250ms="telemetrySearch"
                        type="text"
                        placeholder="Filter by note path or device..."
                        class="w-full rounded-xl border border-gray-200/90 bg-white py-2 pl-9 pr-3 text-xs text-slate-900 shadow-2xs focus:border-[#0D3B29] focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-white"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="$toggle('telemetrySecretsOnly')"
                        class="rounded-xl px-3 py-2 text-xs font-bold transition-colors cursor-pointer {{ $telemetrySecretsOnly ? 'bg-rose-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-zinc-800 dark:text-zinc-300' }}"
                    >
                        {{ __('DLP Secrets Only') }}
                    </button>

                    <select
                        wire:model.live="telemetryActionFilter"
                        class="rounded-xl border border-gray-200/90 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-2xs focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 cursor-pointer"
                    >
                        <option value="all">{{ __('All Actions') }}</option>
                        <option value="created">{{ __('Created') }}</option>
                        <option value="updated">{{ __('Updated') }}</option>
                        <option value="deleted">{{ __('Deleted') }}</option>
                    </select>
                </div>
            </div>

            <!-- Telemetry Stream Table -->
            <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-gray-200/80 bg-slate-50/75 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:border-zinc-800 dark:bg-zinc-800/50 dark:text-zinc-400">
                            <tr>
                                <th class="py-3 px-4">{{ __('Timestamp') }}</th>
                                <th class="py-3 px-4">{{ __('Vault & Tenant') }}</th>
                                <th class="py-3 px-4">{{ __('File Path') }}</th>
                                <th class="py-3 px-4">{{ __('Action') }}</th>
                                <th class="py-3 px-4">{{ __('Device & User') }}</th>
                                <th class="py-3 px-4">{{ __('DLP & Security') }}</th>
                                <th class="py-3 px-4 text-right">{{ __('Audit Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800/80">
                            @forelse ($this->telemetryLogs as $log)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-zinc-800/30 transition-colors {{ $log->has_secrets ? 'bg-rose-50/40 dark:bg-rose-950/20' : '' }}">
                                    <td class="py-3 px-4 font-mono text-[11px] text-slate-500 dark:text-zinc-400">
                                        {{ $log->created_at?->format('H:i:s M j') ?? '—' }}
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                                        <div>{{ $log->vault?->name ?? 'Vault #'.$log->vault_id }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $log->vault?->team?->name }}</div>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-slate-700 dark:text-zinc-300">
                                        <span class="truncate max-w-xs block" title="{{ $log->path }}">{{ $log->path }}</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        @if ($log->action === 'created')
                                            <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">{{ __('CREATED') }}</span>
                                        @elseif ($log->action === 'updated')
                                            <span class="rounded bg-teal-100 px-1.5 py-0.5 text-[10px] font-bold text-teal-800 dark:bg-teal-950/60 dark:text-teal-300">{{ __('UPDATED') }}</span>
                                        @else
                                            <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">{{ __('DELETED') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-slate-600 dark:text-zinc-300">
                                        <div>{{ $log->device_name ?? 'API' }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $log->user?->name ?? 'Unknown User' }}</div>
                                    </td>
                                    <td class="py-3 px-4">
                                        @if ($log->has_secrets)
                                            <div class="flex items-center gap-1.5">
                                                <span class="rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-bold text-white uppercase">
                                                    {{ __('Secrets Leaked') }}
                                                </span>
                                                @if (! empty($log->detected_secrets))
                                                    <span class="text-[10px] text-rose-700 dark:text-rose-300 font-mono">
                                                        {{ implode(', ', $log->detected_secrets) }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-[11px] text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        @if ($log->has_secrets)
                                            <button
                                                type="button"
                                                wire:click="dismissDlpAlert({{ $log->id }})"
                                                class="rounded-lg bg-slate-200 px-2 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-300 dark:bg-zinc-800 dark:text-zinc-200 cursor-pointer"
                                            >
                                                {{ __('Acknowledge') }}
                                            </button>
                                        @else
                                            <span class="text-[11px] text-slate-400 italic">Clean</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-slate-500 dark:text-zinc-400">
                                        {{ __('No sync events found matching criteria.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 5: LICENSES & APPSUMO LTD -->
    @if ($activeTab === 'licenses')
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs lg:col-span-1 dark:border-zinc-800 dark:bg-zinc-900">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-2">{{ __('Generate Lifetime Licenses') }}</h3>
                <p class="text-xs text-slate-500 dark:text-zinc-400 mb-4">{{ __('Generate cryptographically formatted license keys for AppSumo, Lemon Squeezy, or custom deals in single or bulk batches.') }}</p>

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

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="generateLicenseKey"
                            class="flex-1 rounded-xl bg-[#0D3B29] py-2.5 text-xs font-bold text-white shadow-xs hover:bg-[#092B1E] transition-colors cursor-pointer"
                        >
                            {{ __('Generate 1 Key') }}
                        </button>

                        <button
                            type="button"
                            wire:click="generateBatchLicenses"
                            class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-bold text-slate-800 shadow-xs hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white cursor-pointer"
                        >
                            {{ __('Batch (5 Keys)') }}
                        </button>
                    </div>

                    @if ($generatedKey && empty($batchGeneratedKeys))
                        <div class="mt-3 rounded-xl border border-amber-500/30 bg-amber-50/50 p-3 dark:border-amber-500/20 dark:bg-amber-950/20">
                            <span class="text-[10px] font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wider block mb-1">{{ __('Generated License Key') }}</span>
                            <div class="flex items-center justify-between">
                                <code class="font-mono text-xs font-bold text-slate-900 dark:text-white select-all">{{ $generatedKey }}</code>
                            </div>
                        </div>
                    @endif

                    @if (! empty($batchGeneratedKeys))
                        <div class="mt-3 rounded-xl border border-amber-500/30 bg-amber-50/50 p-3 dark:border-amber-500/20 dark:bg-amber-950/20">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wider">{{ __('Generated Batch Keys') }} ({{ count($batchGeneratedKeys) }})</span>
                            </div>
                            <textarea
                                readonly
                                rows="5"
                                class="w-full font-mono text-[11px] bg-white rounded-lg p-2 border border-slate-200 dark:bg-zinc-900 dark:border-zinc-700 select-all"
                            >{{ implode("\n", $batchGeneratedKeys) }}</textarea>
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

    <!-- TAB 6: SYSTEM & OPS -->
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

            <!-- Maintenance & Operations Buttons -->
            <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">{{ __('Operations & Maintenance') }}</h3>
                <div class="flex flex-col gap-2.5">
                    <button
                        type="button"
                        wire:click="clearApplicationCache"
                        class="flex items-center justify-between rounded-xl border border-slate-200 p-2.5 text-xs font-semibold hover:bg-slate-50 dark:border-zinc-700 dark:hover:bg-zinc-800 cursor-pointer"
                    >
                        <span>{{ __('Flush Application Cache') }}</span>
                        <flux:icon icon="arrow-path" class="size-4 text-slate-500" />
                    </button>

                    <button
                        type="button"
                        wire:click="pruneOldSnapshots"
                        class="flex items-center justify-between rounded-xl border border-slate-200 p-2.5 text-xs font-semibold hover:bg-slate-50 dark:border-zinc-700 dark:hover:bg-zinc-800 cursor-pointer"
                    >
                        <span>{{ __('Prune Soft-Deleted Files (>30d)') }}</span>
                        <flux:icon icon="trash" class="size-4 text-slate-500" />
                    </button>
                </div>
            </div>

            <!-- Table Record Metrics -->
            <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs sm:col-span-2 lg:col-span-3 dark:border-zinc-800 dark:bg-zinc-900">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">{{ __('Database Record Scale & Table Footprint') }}</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
                    @foreach ($this->databaseTableMetrics as $table => $count)
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5 text-center dark:border-zinc-800 dark:bg-zinc-800/60">
                            <span class="text-[11px] text-slate-500 dark:text-zinc-400 block truncate">{{ $table }}</span>
                            <span class="text-base font-bold text-slate-900 dark:text-white mt-1 block">{{ number_format($count) }}</span>
                        </div>
                    @endforeach
                </div>
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

    <!-- Tenant Deep-Dive Slideover / Modal -->
    <flux:modal name="inspect-tenant" class="w-full max-w-2xl">
        @if ($this->inspectedTeam)
            <div class="space-y-5">
                <div class="flex items-start justify-between border-b border-gray-100 pb-3 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="xl">{{ $this->inspectedTeam->name }}</flux:heading>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-800 dark:bg-zinc-800 dark:text-zinc-200 uppercase">
                                {{ $this->inspectedTeam->planName() }}
                            </span>
                        </div>
                        <flux:subheading>Workspace slug: <code class="font-mono text-xs text-emerald-700 dark:text-emerald-400">/{{ $this->inspectedTeam->slug }}</code> • Created {{ $this->inspectedTeam->created_at->format('M j, Y') }}</flux:subheading>
                    </div>

                    @if ($this->inspectedTeam->owner())
                        <form method="POST" action="{{ route('admin.impersonate', $this->inspectedTeam->owner()) }}">
                            @csrf
                            <button
                                type="submit"
                                class="rounded-lg bg-[#0D3B29] px-3 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-[#0D3B29]/90 dark:bg-emerald-600 dark:hover:bg-emerald-500 transition-colors cursor-pointer"
                            >
                                {{ __('Login as Owner') }}
                            </button>
                        </form>
                    @endif
                </div>

                <!-- Vaults belonging to this team -->
                <div>
                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider dark:text-zinc-300 mb-2">{{ __('Active Vaults') }} ({{ $this->inspectedTeam->vaults->count() }})</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        @forelse ($this->inspectedTeam->vaults as $vault)
                            <div class="rounded-xl border border-slate-200 p-3 bg-slate-50/50 dark:border-zinc-800 dark:bg-zinc-800/40">
                                <div class="font-semibold text-xs text-slate-900 dark:text-white">{{ $vault->name }}</div>
                                <div class="text-[11px] text-slate-500 font-mono mt-0.5">/{{ $vault->slug }} • {{ $vault->files()->where('is_deleted', false)->count() }} files</div>
                            </div>
                        @empty
                            <p class="text-xs text-slate-400 italic">{{ __('No vaults created yet.') }}</p>
                        @endforelse
                    </div>
                </div>

                <!-- Connected Devices & Emergency Revoke -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider dark:text-zinc-300">{{ __('Connected Device Fleet') }} ({{ $this->inspectedTeam->deviceTokens->count() }})</h4>
                        @if ($this->inspectedTeam->deviceTokens->where('is_wiped', false)->isNotEmpty())
                            <button
                                type="button"
                                wire:click="revokeAllTenantDevices({{ $this->inspectedTeam->id }})"
                                class="rounded-lg bg-rose-50 px-2 py-1 text-[11px] font-bold text-rose-700 hover:bg-rose-100 dark:bg-rose-950/50 dark:text-rose-300 cursor-pointer"
                            >
                                {{ __('Emergency Revoke All Devices') }}
                            </button>
                        @endif
                    </div>

                    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-zinc-800">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-[10px] uppercase font-bold text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">
                                <tr>
                                    <th class="py-2 px-3">{{ __('Device') }}</th>
                                    <th class="py-2 px-3">{{ __('User') }}</th>
                                    <th class="py-2 px-3">{{ __('Scope') }}</th>
                                    <th class="py-2 px-3 text-right">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                                @forelse ($this->inspectedTeam->deviceTokens as $device)
                                    <tr>
                                        <td class="py-2 px-3 font-semibold text-slate-800 dark:text-zinc-200">{{ $device->name }} ({{ $device->client_platform ?? 'unknown' }})</td>
                                        <td class="py-2 px-3 text-slate-500">{{ $device->user?->name }}</td>
                                        <td class="py-2 px-3 text-slate-500">{{ $device->access_scope }}</td>
                                        <td class="py-2 px-3 text-right">
                                            @if ($device->is_wiped)
                                                <span class="text-rose-600 font-bold text-[10px]">{{ __('WIPED') }}</span>
                                            @else
                                                <span class="text-emerald-600 font-bold text-[10px]">{{ __('ACTIVE') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-4 text-center text-slate-400 italic">{{ __('No devices paired.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" size="sm">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
