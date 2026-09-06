<?php

use App\Models\Team;
use App\Models\Vault;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Vaults')] class extends Component {
    public string $vaultName = '';
    public string $vaultDescription = '';
    public string $vaultDefaultPermission = 'read_write';
    public string $search = '';

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

        $this->redirectRoute('vaults.show', ['vault' => $vault->slug], navigate: true);
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

        $query = $this->team->vaults()
            ->withCount(['files' => fn ($q) => $q->where('is_deleted', false)])
            ->latest();

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%')
                    ->orWhere('slug', 'like', '%' . $this->search . '%');
            });
        }

        return $query->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Breadcrumbs & Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:breadcrumbs class="mb-1">
                <flux:breadcrumbs.item href="{{ route('dashboard') }}">{{ $this->team?->name ?? __('Team') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ __('Vaults') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
            <flux:heading size="xl" level="1" class="tracking-tight">{{ __('Team Vaults') }}</flux:heading>
        </div>

        <div class="flex items-center gap-2">
            <flux:modal.trigger name="create-vault">
                <flux:button variant="primary" size="sm" icon="plus">
                    {{ __('New Vault') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="flex items-center gap-3">
        <div class="w-full max-w-sm">
            <flux:input wire:model.live.debounce.250ms="search" size="sm" icon="magnifying-glass" placeholder="Search vaults by name or slug..." />
        </div>
    </div>

    <!-- Vaults Grid -->
    @if ($this->vaults->isEmpty())
        <flux:card class="flex flex-col items-center justify-center p-12 text-center border-dashed">
            <div class="flex size-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-400">
                <flux:icon icon="folder-plus" class="size-6" />
            </div>
            <flux:heading size="md" class="mt-4">{{ __('No vaults found') }}</flux:heading>
            <flux:subheading class="max-w-sm mt-1 text-xs">{{ __('No team vaults match your search query, or no vaults have been created yet.') }}</flux:subheading>
            <flux:modal.trigger name="create-vault">
                <flux:button variant="primary" size="sm" icon="plus" class="mt-4">
                    {{ __('Create Team Vault') }}
                </flux:button>
            </flux:modal.trigger>
        </flux:card>
    @else
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->vaults as $vault)
                <flux:card variant="outline" class="flex flex-col justify-between transition-all hover:border-zinc-400 dark:hover:border-zinc-600">
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-800 dark:bg-white/10 dark:text-white">
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
                                <flux:text class="text-[11px] text-zinc-400">{{ __('Synced Notes') }}</flux:text>
                                <flux:text variant="strong" class="font-semibold">{{ number_format($vault->files_count) }}</flux:text>
                            </div>
                            <div>
                                <flux:text class="text-[11px] text-zinc-400">{{ __('Revision') }}</flux:text>
                                <flux:text variant="strong" class="font-semibold">v{{ $vault->latestVersion() }}</flux:text>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between border-t border-zinc-100 pt-3 dark:border-white/10">
                        <span class="text-[11px] text-zinc-400">{{ $vault->updated_at->diffForHumans() }}</span>
                        <flux:button variant="ghost" size="sm" icon:trailing="chevron-right" :href="route('vaults.show', $vault->slug)" wire:navigate>
                            {{ __('Manage & Rules') }}
                        </flux:button>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    <!-- Create Vault Modal -->
    <flux:modal name="create-vault" focusable class="max-w-lg">
        <form wire:submit="createVault" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create Team Vault') }}</flux:heading>
                <flux:subheading class="text-xs">{{ __('Set up a shared Obsidian vault for your team.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="vaultName" :label="__('Vault Name')" placeholder="e.g. Brain01, Ottomate Vault" required />

                <flux:textarea wire:model="vaultDescription" :label="__('Description (Optional)')" placeholder="Brief note about the vault's purpose..." rows="2" />

                <flux:select wire:model="vaultDefaultPermission" :label="__('Default Team Permission')">
                    <flux:select.option value="read_write">{{ __('Read & Write (Full Two-Way Sync)') }}</flux:select.option>
                    <flux:select.option value="read_only">{{ __('Read-Only (Team can view, cannot modify)') }}</flux:select.option>
                    <flux:select.option value="hidden">{{ __('Restricted (Hidden unless explicit permission granted)') }}</flux:select.option>
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
