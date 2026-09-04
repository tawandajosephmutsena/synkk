<?php

use App\Data\UserTeam;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public function currentTeam(): ?array
    {
        $team = Auth::user()->currentTeam;

        if (! $team) {
            return null;
        }

        $membership = Auth::user()->teamMemberships->firstWhere('team_id', $team->id);
        $roleLabel = $membership ? ucfirst($membership->role->value ?? 'member') : 'Member';

        return [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'initials' => strtoupper(substr($team->name, 0, 2)),
            'role' => $roleLabel,
            'is_personal' => (bool) $team->is_personal,
        ];
    }

    /**
     * @return Collection<int, UserTeam>
     */
    public function teams(): Collection
    {
        return Auth::user()->toUserTeams(includeCurrent: true);
    }

    public function switchTeam(string $slug): void
    {
        $user = Auth::user();

        abort_unless(
            $user->belongsToTeam($team = Team::where('slug', $slug)->firstOrFail()),
            403
        );

        $currentTeamSlug = $user->currentTeam?->slug;

        $user->switchTeam($team);

        if (! request()->header('Referer')) {
            $this->redirectRoute('dashboard', ['current_team' => $team->slug], navigate: true);

            return;
        }

        if (! $currentTeamSlug) {
            $this->redirect(request()->header('Referer'), navigate: true);

            return;
        }

        $redirectTo = $this->replaceCurrentTeamInReferer(
            request()->header('Referer'),
            $currentTeamSlug,
            $team->slug,
        );

        $this->redirect($redirectTo ?? request()->header('Referer'), navigate: true);
    }

    protected function replaceCurrentTeamInReferer(string $referer, string $currentTeamSlug, string $newTeamSlug): ?string
    {
        $redirectTo = preg_replace(
            '#/'.preg_quote($currentTeamSlug, '#').'(?=/|\?|$)#',
            '/'.$newTeamSlug,
            $referer,
            1,
        );

        return preg_replace(
            '#([?&]current_team=)'.preg_quote($currentTeamSlug, '#').'(?=&|$)#',
            '$1'.$newTeamSlug,
            $redirectTo ?? $referer,
            1,
        );
    }
}; ?>

<div class="px-2 py-1">
    @php
        $curr = $this->currentTeam();
    @endphp
    <flux:dropdown position="bottom" align="start">
        <flux:button
            variant="ghost"
            class="group w-full justify-start rounded-xl border border-zinc-200/80 bg-white/70 p-2 shadow-xs transition-all hover:bg-white dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10 in-data-flux-sidebar-collapsed-desktop:justify-center"
            data-test="team-switcher-trigger"
        >
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-[11px] font-bold text-white shadow-xs dark:bg-indigo-500">
                    {{ $curr['initials'] ?? 'SY' }}
                </div>
                <div class="grid flex-1 text-start leading-tight in-data-flux-sidebar-collapsed-desktop:hidden min-w-0">
                    <span class="truncate text-xs font-semibold text-zinc-900 dark:text-zinc-100">{{ $curr['name'] ?? __('Select team') }}</span>
                    <span class="truncate text-[10px] text-zinc-400 font-medium">{{ $curr['role'] ?? __('Workspace') }}</span>
                </div>
            </div>
            <flux:icon
                name="chevrons-up-down"
                variant="micro"
                class="ms-auto size-4 text-zinc-400 in-data-flux-sidebar-collapsed-desktop:hidden"
            />
        </flux:button>

        <flux:menu class="min-w-64">
            <flux:menu.heading class="text-[10px] font-bold tracking-wider uppercase text-zinc-400">{{ __('Workspaces & Teams') }}</flux:menu.heading>

            @foreach ($this->teams() as $team)
                <flux:menu.item
                    wire:click="switchTeam('{{ $team->slug }}')"
                    class="cursor-pointer py-2"
                    data-test="team-switcher-item"
                >
                    <div class="flex w-full items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="flex size-6 shrink-0 items-center justify-center rounded-md {{ $team->isCurrent ? 'bg-indigo-600 text-white' : 'bg-zinc-100 text-zinc-700 dark:bg-white/10 dark:text-zinc-300' }} text-[10px] font-bold">
                                {{ strtoupper(substr($team->name, 0, 2)) }}
                            </div>
                            <div class="truncate text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $team->name }}
                            </div>
                        </div>

                        @if ($team->isCurrent)
                            <flux:icon name="check" class="size-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
                        @endif
                    </div>
                </flux:menu.item>
            @endforeach

            <flux:menu.separator />

            <flux:modal.trigger name="create-team-switcher">
                <flux:menu.item icon="plus" class="cursor-pointer text-xs font-medium" data-test="team-switcher-new-team">
                    {{ __('Create New Team') }}
                </flux:menu.item>
            </flux:modal.trigger>
        </flux:menu>
    </flux:dropdown>
</div>
