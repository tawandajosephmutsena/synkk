<?php

use App\Data\TeamPermissions;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Rules\TeamName;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Team $teamModel;

    public string $teamName = '';

    public array $teamData = [];

    public array $members = [];

    public array $invitations = [];

    public array $availableRoles = [];

    public bool $isCurrentTeam = false;

    public string $licenseKeyInput = '';

    public function redeemLicense(): void
    {
        Gate::authorize('update', $this->teamModel);

        $this->validate([
            'licenseKeyInput' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        $service = app(\App\Services\LicenseValidationService::class);
        $result = $service->activateLicenseKey($this->teamModel, $this->licenseKeyInput);

        if (! $result['success']) {
            $this->addError('licenseKeyInput', $result['message']);
            Flux::toast(variant: 'danger', text: $result['message']);

            return;
        }

        $this->licenseKeyInput = '';
        $this->populateTeamData();

        Flux::toast(variant: 'success', text: $result['message']);
    }

    public function deactivateLicense(): void
    {
        Gate::authorize('update', $this->teamModel);

        $service = app(\App\Services\LicenseValidationService::class);
        $result = $service->deactivateLicenseKey($this->teamModel);

        $this->populateTeamData();

        Flux::toast(variant: 'warning', text: $result['message']);
    }

    #[Computed]
    public function planSummary(): array
    {
        return app(\App\Services\PlanService::class)->getUsageSummary($this->teamModel);
    }

    public function mount(Team $team): void
    {
        $this->teamModel = $team;
        $this->teamName = $team->name;

        $this->populateTeamData();
    }

    public function updateTeam(): void
    {
        Gate::authorize('update', $this->teamModel);

        $validated = $this->validate([
            'teamName' => ['required', 'string', 'max:255', new TeamName],
        ]);

        $team = DB::transaction(function () use ($validated) {
            $team = Team::whereKey($this->teamModel->id)->lockForUpdate()->firstOrFail();

            $team->update(['name' => $validated['teamName']]);

            return $team;
        });

        $this->teamModel = $team;

        $this->populateTeamData();

        Flux::toast(variant: 'success', text: __('Team updated.'));

        $this->redirectRoute('teams.edit', ['team' => $this->teamModel->fresh()->slug], navigate: true);
    }

    public function updateMember(int $userId, string $role): void
    {
        Gate::authorize('updateMember', $this->teamModel);

        $validated = Validator::make(['role' => $role], [
            'role' => ['required', 'string', Rule::enum(TeamRole::class)],
        ])->validate();

        $this->teamModel->memberships()
            ->where('user_id', $userId)
            ->firstOrFail()
            ->update(['role' => TeamRole::from($validated['role'])]);

        $this->populateTeamData();

        Flux::toast(variant: 'success', text: __('Member role updated.'));
    }

    private function populateTeamData(): void
    {
        $user = Auth::user();

        $team = $this->teamModel->fresh();

        $this->teamData = [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'is_personal' => $team->is_personal,
            'plan' => $team->plan ?? 'free',
            'plan_name' => $team->planName(),
            'license_key' => $team->license_key,
            'license_status' => $team->license_status,
            'license_activated_at' => $team->license_activated_at?->format('M j, Y'),
        ];

        $this->members = $team->members()->get()->map(fn ($member) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'avatar' => $member->avatar ?? null,
            'initials' => $member->initials(),
            'role' => $member->pivot->role->value,
            'role_label' => $member->pivot->role->label(),
        ])->toArray();

        $this->invitations = $team->invitations()
            ->whereNull('accepted_at')
            ->get()
            ->map(fn ($invitation) => [
                'code' => $invitation->code,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
                'created_at' => $invitation->created_at->toISOString(),
            ])->toArray();

        $this->availableRoles = TeamRole::assignable();

        $this->isCurrentTeam = $user->isCurrentTeam($team);
    }

    public function render()
    {
        $teamName = $this->teamData['name'] ?? $this->teamModel->name;

        $title = $this->permissions->canUpdateTeam
            ? __('Edit :name', ['name' => $teamName])
            : __('View :name', ['name' => $teamName]);

        return $this->view()->title($title);
    }

    #[Computed]
    public function permissions(): TeamPermissions
    {
        return Auth::user()->toTeamPermissions($this->teamModel);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Teams') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Teams')" :subheading="__('Manage your team settings')">
        <div class="space-y-10">
            <div class="space-y-6">
                @if ($this->permissions->canUpdateTeam)
                    <div class="space-y-4">
                        <form wire:submit="updateTeam" class="space-y-6">
                            <flux:input wire:model="teamName" :label="__('Team name')" required data-test="team-name-input" />

                            <flux:button variant="primary" type="submit" data-test="team-save-button" class="!bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90 !rounded-full px-6 font-bold shadow-xs">
                                {{ __('Save') }}
                            </flux:button>
                        </form>
                    </div>
                @else
                    <div>
                        <flux:heading>{{ $teamData['name'] }}</flux:heading>
                    </div>
                @endif
            </div>

            <!-- PLAN & COMMERCIAL SUBSCRIPTION SECTION -->
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading>{{ __('Plan & Subscription') }}</flux:heading>
                        <flux:subheading>{{ __('Monitor storage & device fleet quotas or activate lifetime commercial licenses') }}</flux:subheading>
                    </div>

                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-black uppercase tracking-wider {{ $this->teamData['plan'] === 'cloud' ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/70 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800' : ($this->teamData['plan'] === 'pro_ltd' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-slate-200/80 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300') }}">
                        <span class="size-2 rounded-full {{ in_array($this->teamData['plan'], ['cloud', 'pro_ltd']) ? 'bg-emerald-500' : 'bg-slate-500' }}"></span>
                        {{ $this->planSummary['plan_badge'] }}
                    </span>
                </div>

                <!-- 4 Quota Utilization Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                    <!-- Storage -->
                    <div class="rounded-2xl border border-gray-100 bg-[#FBFBFA] p-4 shadow-2xs dark:border-zinc-800/80 dark:bg-zinc-800/40">
                        <div class="flex items-center justify-between text-xs font-semibold text-gray-500 dark:text-zinc-400">
                            <span>{{ __('Storage') }}</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $this->planSummary['storage']['percentage'] }}%</span>
                        </div>
                        <div class="mt-2 text-base font-black text-gray-900 dark:text-white">
                            {{ $this->planSummary['storage']['used_mb'] }} <span class="text-xs font-normal text-gray-500">/ {{ $this->planSummary['storage']['limit_mb'] }} MB</span>
                        </div>
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-zinc-700">
                            <div class="h-full rounded-full bg-[#0D3B29] dark:bg-emerald-500" style="width: {{ $this->planSummary['storage']['percentage'] }}%"></div>
                        </div>
                    </div>

                    <!-- Vaults -->
                    <div class="rounded-2xl border border-gray-100 bg-[#FBFBFA] p-4 shadow-2xs dark:border-zinc-800/80 dark:bg-zinc-800/40">
                        <div class="flex items-center justify-between text-xs font-semibold text-gray-500 dark:text-zinc-400">
                            <span>{{ __('Vaults') }}</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $this->planSummary['vaults']['used'] }}/{{ $this->planSummary['vaults']['limit'] }}</span>
                        </div>
                        <div class="mt-2 text-base font-black text-gray-900 dark:text-white">
                            {{ $this->planSummary['vaults']['used'] }} <span class="text-xs font-normal text-gray-500">of {{ $this->planSummary['vaults']['limit'] }} allowed</span>
                        </div>
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-zinc-700">
                            <div class="h-full rounded-full bg-[#0D3B29] dark:bg-emerald-500" style="width: {{ $this->planSummary['vaults']['percentage'] }}%"></div>
                        </div>
                    </div>

                    <!-- Devices -->
                    <div class="rounded-2xl border border-gray-100 bg-[#FBFBFA] p-4 shadow-2xs dark:border-zinc-800/80 dark:bg-zinc-800/40">
                        <div class="flex items-center justify-between text-xs font-semibold text-gray-500 dark:text-zinc-400">
                            <span>{{ __('Connected Devices') }}</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $this->planSummary['devices']['used'] }}/{{ $this->planSummary['devices']['limit'] }}</span>
                        </div>
                        <div class="mt-2 text-base font-black text-gray-900 dark:text-white">
                            {{ $this->planSummary['devices']['used'] }} <span class="text-xs font-normal text-gray-500">of {{ $this->planSummary['devices']['limit'] }} paired</span>
                        </div>
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-zinc-700">
                            <div class="h-full rounded-full bg-[#0D3B29] dark:bg-emerald-500" style="width: {{ $this->planSummary['devices']['percentage'] }}%"></div>
                        </div>
                    </div>

                    <!-- Members -->
                    <div class="rounded-2xl border border-gray-100 bg-[#FBFBFA] p-4 shadow-2xs dark:border-zinc-800/80 dark:bg-zinc-800/40">
                        <div class="flex items-center justify-between text-xs font-semibold text-gray-500 dark:text-zinc-400">
                            <span>{{ __('Team Seats') }}</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $this->planSummary['members']['used'] }}/{{ $this->planSummary['members']['limit'] }}</span>
                        </div>
                        <div class="mt-2 text-base font-black text-gray-900 dark:text-white">
                            {{ $this->planSummary['members']['used'] }} <span class="text-xs font-normal text-gray-500">of {{ $this->planSummary['members']['limit'] }} seats</span>
                        </div>
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-zinc-700">
                            <div class="h-full rounded-full bg-[#0D3B29] dark:bg-emerald-500" style="width: {{ $this->planSummary['members']['percentage'] }}%"></div>
                        </div>
                    </div>
                </div>

                <!-- License Status or Redemption Box -->
                @if ($this->permissions->canUpdateTeam)
                    @if ($this->teamData['license_status'] === 'active' && filled($this->teamData['license_key']))
                        <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/50 p-5 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-10 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300">
                                        <flux:icon icon="check-badge" class="size-5" />
                                    </div>
                                    <div>
                                        <div class="font-bold text-sm text-emerald-900 dark:text-emerald-200">
                                            {{ __('Commercial License Active') }} ({{ $this->teamData['plan_name'] }})
                                        </div>
                                        <div class="text-xs text-emerald-700 dark:text-emerald-400 font-mono mt-0.5">
                                            {{ substr($this->teamData['license_key'], 0, 9) . '••••-••••-' . substr($this->teamData['license_key'], -4) }}
                                            @if ($this->teamData['license_activated_at'])
                                                • {{ __('Activated on :date', ['date' => $this->teamData['license_activated_at']]) }}
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    wire:click="deactivateLicense"
                                    wire:confirm="{{ __('Are you sure you want to deactivate this license? Your team will revert to Community Free quotas.') }}"
                                    class="text-xs font-semibold text-red-600 hover:text-red-700 dark:text-red-400 hover:underline cursor-pointer"
                                >
                                    {{ __('Deactivate License') }}
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div>
                                    <div class="font-bold text-sm text-gray-900 dark:text-white">
                                        {{ __('Redeem Commercial License') }}
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1 max-w-xl">
                                        {{ __('Have an AppSumo, LemonSqueezy, or founder lifetime key? Enter it below to unlock higher quotas, In-App DLP secret scanning, and path-level permissions.') }}
                                    </p>
                                </div>
                                <a
                                    href="{{ route('home') }}#pricing"
                                    class="shrink-0 text-xs font-bold text-[#0D3B29] hover:underline dark:text-emerald-400"
                                >
                                    {{ __('Purchase License →') }}
                                </a>
                            </div>

                            <form wire:submit="redeemLicense" class="mt-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                                <div class="flex-1">
                                    <flux:input
                                        wire:model="licenseKeyInput"
                                        placeholder="SYNK-PRO-XXXX-XXXX-XXXX"
                                        class="font-mono uppercase text-xs"
                                        required
                                    />
                                </div>
                                <flux:button
                                    variant="primary"
                                    type="submit"
                                    class="!bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90 !rounded-full px-5 font-bold text-xs shrink-0"
                                >
                                    {{ __('Redeem Key') }}
                                </flux:button>
                            </form>
                        </div>
                    @endif
                @endif
            </div>

            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading>{{ __('Team members') }}</flux:heading>
                        @if ($this->permissions->canAddMember || $this->permissions->canUpdateMember || $this->permissions->canRemoveMember)
                            <flux:subheading>{{ __('Manage who belongs to this team') }}</flux:subheading>
                        @endif
                    </div>

                    @if ($this->permissions->canCreateInvitation)
                        <flux:modal.trigger name="invite-member">
                            <flux:button variant="primary" icon="user-plus" data-test="invite-member-button" class="!bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90 !rounded-full px-4 font-bold shadow-xs">
                                {{ __('Invite member') }}
                            </flux:button>
                        </flux:modal.trigger>
                    @endif
                </div>

                <div class="space-y-3">
                    @foreach ($members as $member)
                        <div class="flex items-center justify-between rounded-2xl border border-gray-100 bg-[#FBFBFA] p-4 transition-all hover:border-gray-200 dark:border-zinc-800/80 dark:bg-zinc-800/40 shadow-2xs" data-test="member-row">
                            <div class="flex items-center gap-3.5">
                                <flux:avatar :name="$member['name']" :initials="$member['initials']" size="md" />
                                <div>
                                    <div class="font-bold text-sm text-gray-900 dark:text-zinc-100">{{ $member['name'] }}</div>
                                    <flux:text class="text-xs text-gray-500 dark:text-zinc-400">{{ $member['email'] }}</flux:text>
                                </div>
                            </div>

                            <div class="flex items-center gap-2.5">
                                @if ($member['role'] !== 'owner' && $this->permissions->canUpdateMember)
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="subtle" size="sm" icon:trailing="chevron-down" data-test="member-role-trigger" class="font-medium !rounded-full">
                                            {{ $member['role_label'] }}
                                        </flux:button>
                                        <flux:menu>
                                            @foreach ($availableRoles as $role)
                                                <flux:menu.item
                                                    as="button"
                                                    type="button"
                                                    wire:click="updateMember({{ $member['id'] }}, '{{ $role['value'] }}')"
                                                    data-test="member-role-option"
                                                    class="text-xs"
                                                >
                                                    {{ $role['label'] }}
                                                </flux:menu.item>
                                            @endforeach
                                        </flux:menu>
                                    </flux:dropdown>
                                @else
                                    <flux:badge color="{{ $member['role'] === 'owner' ? 'emerald' : 'zinc' }}" size="sm" class="font-bold uppercase tracking-wider text-[10px] rounded-full {{ $member['role'] === 'owner' ? 'bg-emerald-50 text-[#0D3B29] border border-emerald-200/60 dark:bg-emerald-950 dark:text-emerald-400' : '' }}">{{ $member['role_label'] }}</flux:badge>
                                @endif

                                @if ($member['role'] !== 'owner' && $this->permissions->canRemoveMember)
                                    <flux:modal.trigger name="remove-member-{{ $member['id'] }}">
                                        <flux:tooltip :content="__('Remove member from team')">
                                            <flux:button
                                                variant="subtle"
                                                size="sm"
                                                icon="x-mark"
                                                data-test="member-remove-button"
                                                class="text-red-500 hover:text-red-600 rounded-full"
                                            />
                                        </flux:tooltip>
                                    </flux:modal.trigger>
                                @endif
                            </div>
                        </div>

                        @if ($member['role'] !== 'owner' && $this->permissions->canRemoveMember)
                            <livewire:pages::teams.remove-member-modal
                                :team="$teamModel"
                                :member-id="$member['id']"
                                :member-name="$member['name']"
                                :modal-name="'remove-member-'.$member['id']"
                                :key="'remove-member-modal-'.$member['id']"
                            />
                        @endif
                    @endforeach
                </div>
            </div>

            @if (count($invitations) > 0)
                <div class="space-y-6">
                    <div>
                        <flux:heading>{{ __('Pending invitations') }}</flux:heading>
                        <flux:subheading>{{ __('Invitations that have not been accepted yet') }}</flux:subheading>
                    </div>

                    <div class="space-y-3">
                        @foreach ($invitations as $invitation)
                            <div class="flex items-center justify-between rounded-2xl border border-gray-100 bg-[#FBFBFA] p-4 dark:border-zinc-800/80 dark:bg-zinc-800/40 shadow-2xs" data-test="invitation-row">
                                <div class="flex items-center gap-4">
                                    <div class="flex size-10 items-center justify-center rounded-2xl bg-emerald-50 text-[#0D3B29] dark:bg-emerald-950/60 dark:text-emerald-400">
                                        <flux:icon name="envelope" class="size-4 text-[#0D3B29] dark:text-emerald-400" />
                                    </div>
                                    <div>
                                        <div class="font-bold text-sm text-gray-900 dark:text-white">{{ $invitation['email'] }}</div>
                                        <flux:text class="text-xs text-gray-500 dark:text-zinc-400">{{ $invitation['role_label'] }}</flux:text>
                                    </div>
                                </div>

                                @if ($this->permissions->canCancelInvitation)
                                    <flux:modal.trigger name="cancel-invitation-{{ $invitation['code'] }}">
                                        <flux:tooltip :content="__('Cancel invitation')">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="x-mark"
                                                data-test="invitation-cancel-button"
                                                class="rounded-full hover:bg-gray-100 dark:hover:bg-zinc-800"
                                            />
                                        </flux:tooltip>
                                    </flux:modal.trigger>
                                @endif
                            </div>
                            @if ($this->permissions->canCancelInvitation)
                                <livewire:pages::teams.cancel-invitation-modal
                                    :team="$teamModel"
                                    :invitation-code="$invitation['code']"
                                    :invitation-email="$invitation['email']"
                                    :modal-name="'cancel-invitation-'.$invitation['code']"
                                    :key="'cancel-invitation-modal-'.$invitation['code']"
                                />
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($this->permissions->canDeleteTeam && ! $teamData['is_personal'])
                <div class="space-y-6">
                    <div>
                        <flux:heading>{{ __('Delete team') }}</flux:heading>
                        <flux:subheading>{{ __('Permanently delete your team') }}</flux:subheading>
                    </div>

                    <div class="space-y-4 rounded-2xl border border-red-200/80 bg-red-50/50 p-5 text-red-800 dark:border-red-900/40 dark:bg-red-950/20 dark:text-red-300">
                        <div>
                            <p class="font-bold text-sm">{{ __('Warning') }}</p>
                            <p class="text-xs mt-0.5 text-red-600 dark:text-red-400">{{ __('Please proceed with caution, this cannot be undone.') }}</p>
                        </div>

                        <flux:modal.trigger name="delete-team">
                            <flux:button variant="danger" data-test="delete-team-button" class="!rounded-full px-5 font-bold text-xs">
                                {{ __('Delete team') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>
            @endif
        </div>
    </x-pages::settings.layout>

    @if ($this->permissions->canCreateInvitation)
        <livewire:pages::teams.invite-member-modal :team="$teamModel" />
    @endif

    @if ($this->permissions->canDeleteTeam && ! $teamData['is_personal'])
        <livewire:pages::teams.delete-team-modal :team="$teamModel" />
    @endif
</section>
