<?php

use App\Models\Team;
use App\Models\Vault;
use App\Models\VaultPortal;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Synkk Portals')] class extends Component {
    public ?int $editingPortalId = null;

    public ?int $vault_id = null;
    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public string $layout = 'docs';
    public string $theme = 'obsidian-noir';
    public string $root_path = '';
    public string $password = '';
    public bool $is_public = true;

    // Feature toggles
    public bool $enable_search = true;
    public bool $enable_graph = true;
    public bool $enable_backlinks = true;
    public bool $enable_popover = true;

    public string $search = '';
    public string $layoutFilter = '';

    public function openCreateModal(): void
    {
        $this->resetForm();
        if ($this->vaults->isNotEmpty()) {
            $this->vault_id = $this->vaults->first()->id;
        }
        $this->modal('portal-modal')->show();
        $this->dispatch('modal-show', name: 'portal-modal');
    }

    public function editPortal(int $id): void
    {
        $portal = $this->team->portals()->findOrFail($id);

        $this->editingPortalId = $portal->id;
        $this->vault_id = $portal->vault_id;
        $this->name = $portal->name;
        $this->slug = $portal->slug;
        $this->description = $portal->description ?? '';
        $this->layout = $portal->layout;
        $this->theme = $portal->theme;
        $this->root_path = $portal->root_path ?? '';
        $this->password = ''; // Don't expose password hash
        $this->is_public = $portal->is_public;

        $this->enable_search = (bool) $portal->getSetting('enable_search', true);
        $this->enable_graph = (bool) $portal->getSetting('enable_graph', true);
        $this->enable_backlinks = (bool) $portal->getSetting('enable_backlinks', true);
        $this->enable_popover = (bool) $portal->getSetting('enable_popover', true);

        $this->modal('portal-modal')->show();
        $this->dispatch('modal-show', name: 'portal-modal');
    }

    public function savePortal(): void
    {
        $team = $this->team;

        $this->validate([
            'vault_id' => ['required', Rule::exists('vaults', 'id')->where('team_id', $team->id)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'layout' => ['required', 'in:docs,bento,dashboard,minimal'],
            'theme' => ['required', 'in:obsidian-noir,slate-luxe,midnight-emerald,paper-craft,amber-gold'],
            'root_path' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
            'is_public' => ['boolean'],
        ]);

        $slug = filled($this->slug)
            ? Str::slug($this->slug)
            : Str::slug($this->name);

        if (empty($slug)) {
            $slug = 'portal-'.Str::random(6);
        }

        // Ensure slug uniqueness (excluding current portal if editing)
        $slugQuery = VaultPortal::where('slug', $slug);
        if ($this->editingPortalId) {
            $slugQuery->where('id', '!=', $this->editingPortalId);
        }
        if ($slugQuery->exists()) {
            $slug .= '-'.Str::random(4);
        }

        $settings = [
            'enable_search' => $this->enable_search,
            'enable_graph' => $this->enable_graph,
            'enable_backlinks' => $this->enable_backlinks,
            'enable_popover' => $this->enable_popover,
        ];

        $data = [
            'vault_id' => $this->vault_id,
            'name' => $this->name,
            'slug' => $slug,
            'description' => $this->description ?: null,
            'layout' => $this->layout,
            'theme' => $this->theme,
            'root_path' => filled($this->root_path) ? '/'.ltrim($this->root_path, '/') : '/',
            'is_public' => $this->is_public,
            'settings' => $settings,
        ];

        if (filled($this->password)) {
            $data['password'] = $this->password;
        }

        if ($this->editingPortalId) {
            $portal = $team->portals()->findOrFail($this->editingPortalId);
            $portal->update($data);
            Flux::toast(variant: 'success', text: __('Portal ":name" updated successfully.', ['name' => $portal->name]));
        } else {
            $data['team_id'] = $team->id;
            $data['created_by'] = Auth::id();
            $portal = VaultPortal::create($data);
            Flux::toast(variant: 'success', text: __('Portal ":name" created successfully.', ['name' => $portal->name]));
        }

        $this->resetForm();
        $this->modal('portal-modal')->close();
        $this->dispatch('modal-close', name: 'portal-modal');
    }

    public function deletePortal(int $id): void
    {
        $portal = $this->team->portals()->findOrFail($id);
        $name = $portal->name;
        $portal->delete();

        Flux::toast(variant: 'success', text: __('Portal ":name" removed.', ['name' => $name]));
    }

    public function resetForm(): void
    {
        $this->editingPortalId = null;
        $this->name = '';
        $this->slug = '';
        $this->description = '';
        $this->layout = 'docs';
        $this->theme = 'obsidian-noir';
        $this->root_path = '';
        $this->password = '';
        $this->is_public = true;
        $this->enable_search = true;
        $this->enable_graph = true;
        $this->enable_backlinks = true;
        $this->enable_popover = true;
    }

    #[Computed]
    public function team(): ?Team
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function vaults(): Collection
    {
        return $this->team ? $this->team->vaults()->latest()->get() : collect();
    }

    #[Computed]
    public function portals(): Collection
    {
        if (! $this->team) {
            return collect();
        }

        $query = $this->team->portals()
            ->with(['vault', 'primaryFile'])
            ->latest();

        if (filled($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('slug', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%');
            });
        }

        if (filled($this->layoutFilter)) {
            $query->where('layout', $this->layoutFilter);
        }

        return $query->get();
    }

    #[Computed]
    public function totalViews(): int
    {
        return (int) $this->portals->sum('views_count');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Breadcrumbs & Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:breadcrumbs class="mb-1">
                <flux:breadcrumbs.item href="{{ route('dashboard') }}">{{ $this->team?->name ?? __('Team') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ __('Portals') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
            <div class="flex items-center gap-2.5">
                <flux:heading size="xl" level="1" class="tracking-tight">{{ __('Synkk Portals Studio') }}</flux:heading>
                <span class="rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-0.5 text-xs font-semibold text-amber-500 dark:text-amber-400">
                    Livewire Engine
                </span>
            </div>
            <flux:subheading class="mt-1 text-xs">
                {{ __('Publish interactive documentation, client hubs, and bento cards directly from your Obsidian vaults.') }}
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:modal.trigger name="portal-modal">
                <flux:button wire:click="openCreateModal" variant="primary" size="sm" icon="plus">
                    {{ __('New Portal') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <flux:card class="flex items-center gap-4 p-4">
            <div class="flex size-11 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500 ring-1 ring-amber-500/20">
                <flux:icon icon="globe-alt" class="size-5" />
            </div>
            <div>
                <flux:text class="text-xs text-zinc-400">{{ __('Active Portals') }}</flux:text>
                <flux:heading size="lg" class="font-bold">{{ $this->portals->count() }}</flux:heading>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4 p-4">
            <div class="flex size-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500 ring-1 ring-emerald-500/20">
                <flux:icon icon="eye" class="size-5" />
            </div>
            <div>
                <flux:text class="text-xs text-zinc-400">{{ __('Total Views') }}</flux:text>
                <flux:heading size="lg" class="font-bold">{{ number_format($this->totalViews) }}</flux:heading>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4 p-4">
            <div class="flex size-11 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-500 ring-1 ring-indigo-500/20">
                <flux:icon icon="folder" class="size-5" />
            </div>
            <div>
                <flux:text class="text-xs text-zinc-400">{{ __('Vault Sources') }}</flux:text>
                <flux:heading size="lg" class="font-bold">{{ $this->vaults->count() }}</flux:heading>
            </div>
        </flux:card>
    </div>

    <!-- Filter Bar -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="w-full max-w-sm">
            <flux:input
                wire:model.live.debounce.250ms="search"
                size="sm"
                icon="magnifying-glass"
                placeholder="Search portals by name or slug..."
            />
        </div>

        <div class="flex items-center gap-2">
            <flux:select wire:model.live="layoutFilter" size="sm" class="w-40">
                <flux:select.option value="">{{ __('All Layouts') }}</flux:select.option>
                <flux:select.option value="docs">{{ __('Docs Hub') }}</flux:select.option>
                <flux:select.option value="bento">{{ __('Bento Grid') }}</flux:select.option>
                <flux:select.option value="dashboard">{{ __('Client Hub') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    <!-- Portals Grid -->
    @if ($this->portals->isEmpty())
        <flux:card class="flex flex-col items-center justify-center p-12 text-center border-dashed">
            <div class="flex size-14 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-500 ring-1 ring-amber-500/20">
                <flux:icon icon="sparkles" class="size-7" />
            </div>
            <flux:heading size="md" class="mt-4">{{ __('No portals created yet') }}</flux:heading>
            <flux:subheading class="max-w-md mt-1 text-xs text-zinc-400">
                {{ __('Transform any Obsidian vault into a public or password-protected documentation hub, interactive bento showcase, or client portal with live sync.') }}
            </flux:subheading>
            <flux:modal.trigger name="portal-modal">
                <flux:button wire:click="openCreateModal" variant="primary" size="sm" icon="plus" class="mt-5">
                    {{ __('Create First Portal') }}
                </flux:button>
            </flux:modal.trigger>
        </flux:card>
    @else
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->portals as $portal)
                <flux:card variant="outline" class="group relative flex flex-col justify-between transition-all hover:border-amber-500/40 hover:shadow-lg dark:hover:border-amber-500/30">
                    <div class="space-y-4">
                        <!-- Top Badges & Layout Indicator -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500 ring-1 ring-amber-500/20 font-bold">
                                    @if ($portal->layout === 'bento')
                                        <flux:icon icon="squares-2x2" class="size-5" />
                                    @elseif ($portal->layout === 'dashboard')
                                        <flux:icon icon="chart-bar-square" class="size-5" />
                                    @else
                                        <flux:icon icon="bars-3-bottom-left" class="size-5" />
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <h2 class="truncate font-bold text-sm text-zinc-900 dark:text-zinc-100">
                                        {{ $portal->name }}
                                    </h2>
                                    <span class="font-mono text-xs text-zinc-400">/p/{{ $portal->slug }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5">
                                @if ($portal->isPasswordProtected())
                                    <flux:badge color="amber" size="sm" inset icon="lock-closed">
                                        {{ __('Locked') }}
                                    </flux:badge>
                                @else
                                    <flux:badge color="emerald" size="sm" inset icon="globe-alt">
                                        {{ __('Public') }}
                                    </flux:badge>
                                @endif
                            </div>
                        </div>

                        <!-- Description / Tagline -->
                        @if ($portal->description)
                            <p class="line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $portal->description }}
                            </p>
                        @endif

                        <!-- Metadata Badges -->
                        <div class="flex flex-wrap items-center gap-1.5 text-xs">
                            <span class="rounded-md bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                Vault: {{ $portal->vault?->name ?? 'None' }}
                            </span>
                            <span class="rounded-md bg-zinc-100 px-2 py-0.5 text-[11px] font-medium capitalize text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ str_replace('-', ' ', $portal->theme) }}
                            </span>
                            @if ($portal->root_path)
                                <span class="rounded-md bg-zinc-100 px-2 py-0.5 text-[11px] font-mono text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ $portal->root_path }}
                                </span>
                            @endif
                        </div>

                        <!-- Quick Telemetry & Views -->
                        <div class="flex items-center justify-between rounded-xl bg-zinc-50 px-3 py-2 text-xs dark:bg-zinc-800/50">
                            <span class="text-zinc-500">{{ __('Views Recorded') }}</span>
                            <span class="font-bold text-zinc-900 dark:text-white">{{ number_format($portal->views_count) }}</span>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="mt-5 flex items-center justify-between border-t border-zinc-100 pt-3 dark:border-white/10" x-data="{ copied: false }">
                        <div class="flex items-center gap-1">
                            <!-- Copy Link Action with Feedback -->
                            <button
                                type="button"
                                x-on:click="
                                    navigator.clipboard.writeText('{{ $portal->getUrl() }}');
                                    copied = true;
                                    setTimeout(() => copied = false, 2000);
                                "
                                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white transition-colors cursor-pointer"
                            >
                                <flux:icon icon="clipboard" class="size-3.5" />
                                <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy Link') }}'"></span>
                            </button>

                            <!-- Edit Settings -->
                            <flux:modal.trigger name="portal-modal">
                                <button
                                    type="button"
                                    wire:click="editPortal({{ $portal->id }})"
                                    class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800 dark:hover:text-white cursor-pointer"
                                    title="{{ __('Edit Settings') }}"
                                >
                                    <flux:icon icon="cog-6-tooth" class="size-4" />
                                </button>
                            </flux:modal.trigger>

                            <!-- Delete Portal -->
                            <button
                                type="button"
                                wire:click="deletePortal({{ $portal->id }})"
                                wire:confirm="Are you sure you want to delete this portal? Your Obsidian vault files will not be deleted."
                                class="rounded-lg p-1 text-rose-400 hover:bg-rose-500/10 hover:text-rose-500 cursor-pointer"
                                title="{{ __('Delete Portal') }}"
                            >
                                <flux:icon icon="trash" class="size-4" />
                            </button>
                        </div>

                        <!-- Open Live Portal Button -->
                        <a
                            href="{{ $portal->getUrl() }}"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500/10 px-2.5 py-1 text-xs font-bold text-amber-600 hover:bg-amber-500/20 dark:text-amber-400 transition-colors"
                        >
                            <span>{{ __('Open Portal') }}</span>
                            <flux:icon icon="arrow-top-right-on-square" class="size-3.5" />
                        </a>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    <!-- Create / Edit Portal Modal -->
    <flux:modal name="portal-modal" focusable class="max-w-xl">
        <form wire:submit="savePortal" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingPortalId ? __('Edit Synkk Portal') : __('Create Synkk Portal') }}</flux:heading>
                <flux:subheading class="text-xs">
                    {{ __('Configure your interactive live Obsidian portal.') }}
                </flux:subheading>
            </div>

            <div class="space-y-4">
                <!-- Vault Selector -->
                <flux:select wire:model="vault_id" :label="__('Source Vault')" required>
                    @foreach ($this->vaults as $v)
                        <flux:select.option value="{{ $v->id }}">{{ $v->name }} ({{ $v->slug }})</flux:select.option>
                    @endforeach
                </flux:select>

                <!-- Name & Slug -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="name" :label="__('Portal Name')" placeholder="e.g. Acme Docs Hub" required />
                    <flux:input wire:model="slug" :label="__('URL Slug (Optional)')" placeholder="acme-docs" />
                </div>

                <flux:textarea wire:model="description" :label="__('Description (Optional)')" placeholder="Brief summary displayed on hero banners and meta tags..." rows="2" />

                <!-- Layout Mode -->
                <flux:select wire:model="layout" :label="__('Portal Layout Mode')">
                    <flux:select.option value="docs">{{ __('Documentation Hub (Sidebar tree, article reader, TOC, backlinks)') }}</flux:select.option>
                    <flux:select.option value="bento">{{ __('Bento Showcase Grid (Masonry cards, tags, live search filter)') }}</flux:select.option>
                    <flux:select.option value="dashboard">{{ __('Client Hub (Overview banner, stats, featured notes)') }}</flux:select.option>
                </flux:select>

                <!-- Theme Palette -->
                <flux:select wire:model="theme" :label="__('Color Palette & Theme')">
                    <flux:select.option value="obsidian-noir">{{ __('Obsidian Noir (Jet Black & Gold)') }}</flux:select.option>
                    <flux:select.option value="slate-luxe">{{ __('Slate Luxe (Cool Slate & Indigo)') }}</flux:select.option>
                    <flux:select.option value="midnight-emerald">{{ __('Midnight Emerald (Deep Emerald & Neon Teal)') }}</flux:select.option>
                    <flux:select.option value="paper-craft">{{ __('Paper Craft (Clean Ivory & Monospaced Slate)') }}</flux:select.option>
                    <flux:select.option value="amber-gold">{{ __('Amber Gold (Warm Amber & Copper)') }}</flux:select.option>
                </flux:select>

                <!-- Root Folder Scope -->
                <flux:input
                    wire:model="root_path"
                    :label="__('Vault Subfolder Scope (Optional)')"
                    placeholder="/Public or /Documentation (Leave empty to publish whole vault)"
                />

                <!-- Security / Password Protection -->
                <div class="rounded-xl border border-zinc-200 p-4 space-y-3 dark:border-zinc-800">
                    <div class="flex items-center justify-between">
                        <div class="space-y-0.5">
                            <flux:text variant="strong" class="text-xs">{{ __('Client Password Gate') }}</flux:text>
                            <flux:text class="text-[11px] text-zinc-400">{{ __('Require a password to access this portal.') }}</flux:text>
                        </div>
                    </div>
                    <flux:input
                        type="password"
                        wire:model="password"
                        placeholder="{{ $editingPortalId ? __('Leave empty to keep existing password') : __('Set client access password (optional)') }}"
                    />
                </div>

                <!-- Feature Toggles -->
                <div class="grid grid-cols-2 gap-3 pt-2">
                    <flux:checkbox wire:model="enable_search" :label="__('Command Palette (⌘K)')" />
                    <flux:checkbox wire:model="enable_graph" :label="__('Knowledge Graph View')" />
                    <flux:checkbox wire:model="enable_backlinks" :label="__('Backlinks & Mentions')" />
                    <flux:checkbox wire:model="enable_popover" :label="__('Wikilink Hover Previews')" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">
                    {{ $editingPortalId ? __('Save Changes') : __('Create Portal') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
