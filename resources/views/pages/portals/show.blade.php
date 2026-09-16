<?php

use App\Models\VaultFile;
use App\Models\VaultPortal;
use App\Services\KnowledgeGraphService;
use App\Services\PortalRendererService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Synkk Portal')]
#[Layout('layouts.portal')]
class extends Component {
    public VaultPortal $portal;

    #[Url(as: 'note')]
    public ?string $notePath = null;

    #[Url(as: 'search')]
    public string $searchQuery = '';

    #[Url(as: 'tag')]
    public string $activeTag = '';

    #[Url(as: 'layout')]
    public ?string $layoutOverride = null;

    #[Url(as: 'theme')]
    public ?string $themeOverride = null;

    public string $sidebarFilter = '';
    public string $passwordInput = '';
    public bool $isUnlocked = false;
    public bool $mobileDrawerOpen = false;
    public bool $commandPaletteOpen = false;
    public bool $graphModalOpen = false;

    public function mount(string $slug, ?string $path = null): void
    {
        $this->portal = VaultPortal::with(['team', 'vault', 'primaryFile'])
            ->where('slug', $slug)
            ->firstOrFail();

        if (! $this->portal->is_public && ! auth()->check()) {
            abort(403, 'This portal is private.');
        }

        // Check password protection session
        if ($this->portal->isPasswordProtected()) {
            $sessionKey = "synkk_portal_unlocked_{$this->portal->id}";
            $this->isUnlocked = session()->get($sessionKey, false);
        } else {
            $this->isUnlocked = true;
        }

        if ($this->isUnlocked) {
            $this->portal->incrementViews();
        }

        // Handle path parameter
        if (filled($path)) {
            $this->notePath = $path;
        } elseif (empty($this->notePath) && $this->portal->primaryFile) {
            $this->notePath = $this->portal->primaryFile->path;
        }
    }

    public function unlock(): void
    {
        if ($this->portal->verifyPassword($this->passwordInput)) {
            $this->isUnlocked = true;
            session()->put("synkk_portal_unlocked_{$this->portal->id}", true);
            $this->portal->incrementViews();
            $this->passwordInput = '';
        } else {
            $this->addError('passwordInput', __('Incorrect portal password.'));
        }
    }

    public function selectNote(string $path): void
    {
        $this->notePath = $path;
        $this->mobileDrawerOpen = false;
        $this->commandPaletteOpen = false;
        $this->graphModalOpen = false;
        if ($this->currentLayout === 'graph') {
            $this->layoutOverride = 'docs';
        }
    }

    public function filterTag(string $tag): void
    {
        $this->activeTag = ($this->activeTag === $tag) ? '' : $tag;
    }

    public function switchLayout(string $layout): void
    {
        $this->layoutOverride = in_array($layout, ['docs', 'bento', 'dashboard', 'graph', 'minimal'], true)
            ? $layout
            : null;
    }

    public function switchTheme(string $theme): void
    {
        $validThemes = ['obsidian-noir', 'slate-luxe', 'midnight-emerald', 'paper-craft', 'amber-gold'];
        if (in_array($theme, $validThemes, true)) {
            $this->themeOverride = $theme;
        }
    }

    #[Computed]
    public function currentLayout(): string
    {
        return $this->layoutOverride ?: ($this->portal->layout ?: 'docs');
    }

    #[Computed]
    public function currentTheme(): string
    {
        return $this->themeOverride ?: ($this->portal->theme ?: 'obsidian-noir');
    }

    #[Computed]
    public function accessibleFiles(): Collection
    {
        return $this->portal->getAccessibleFiles();
    }

    #[Computed]
    public function markdownFiles(): Collection
    {
        return $this->accessibleFiles->filter(fn (VaultFile $f) => $f->isMarkdown());
    }

    #[Computed]
    public function filteredMarkdownFiles(): Collection
    {
        if (blank($this->sidebarFilter)) {
            return $this->markdownFiles;
        }

        $query = strtolower($this->sidebarFilter);
        return $this->markdownFiles->filter(function (VaultFile $f) use ($query) {
            return str_contains(strtolower($f->path), $query)
                || str_contains(strtolower(pathinfo($f->path, PATHINFO_FILENAME)), $query);
        });
    }

    #[Computed]
    public function activeFile(): ?VaultFile
    {
        if (filled($this->notePath)) {
            $matched = $this->markdownFiles->first(function (VaultFile $f) {
                return $f->path === $this->notePath
                    || pathinfo($f->path, PATHINFO_FILENAME) === $this->notePath
                    || pathinfo($f->path, PATHINFO_FILENAME) === urldecode($this->notePath);
            });

            if ($matched) {
                return $matched;
            }
        }

        if ($this->portal->primaryFile && $this->portal->primaryFile->isMarkdown()) {
            return $this->portal->primaryFile;
        }

        return $this->markdownFiles->first();
    }

    #[Computed]
    public function renderedNote(): array
    {
        if (! $this->activeFile) {
            return [
                'html' => '<div class="p-12 text-center text-zinc-500 font-medium">No note selected or vault has no markdown files.</div>',
                'toc' => [],
                'reading_time' => '0 min',
                'word_count' => 0,
                'frontmatter' => [],
                'backlinks' => [],
            ];
        }

        $content = $this->activeFile->getContents() ?? '';
        return app(PortalRendererService::class)->renderNoteHtml(
            $content,
            $this->portal,
            $this->accessibleFiles,
            $this->activeFile
        );
    }

    #[Computed]
    public function bentoCards(): array
    {
        $service = app(PortalRendererService::class);
        $cards = [];

        foreach ($this->markdownFiles as $file) {
            $content = $file->getContents() ?? '';
            $parsed = $service->parseFrontmatter($content);
            $fm = $parsed['frontmatter'];
            $excerpt = $service->extractExcerpt($content, 180);
            $title = $fm['title'] ?? pathinfo($file->path, PATHINFO_FILENAME);
            $tags = (array) ($fm['tags'] ?? []);
            $badge = $fm['badge'] ?? null;
            $icon = $fm['icon'] ?? 'document-text';
            $words = str_word_count(strip_tags($parsed['body']));
            $readingTime = max(1, (int) ceil($words / 200)) . ' min read';

            if (filled($this->activeTag) && ! in_array($this->activeTag, $tags, true)) {
                continue;
            }

            if (filled($this->searchQuery)) {
                $query = strtolower($this->searchQuery);
                if (! str_contains(strtolower($title), $query) && ! str_contains(strtolower($excerpt), $query)) {
                    continue;
                }
            }

            $cards[] = [
                'path' => $file->path,
                'title' => $title,
                'excerpt' => $excerpt,
                'tags' => $tags,
                'badge' => $badge,
                'icon' => $icon,
                'reading_time' => $readingTime,
                'word_count' => $words,
                'updated_at' => $file->updated_at?->diffForHumans() ?? 'recently',
            ];
        }

        return $cards;
    }

    #[Computed]
    public function allTags(): array
    {
        $service = app(PortalRendererService::class);
        $tags = [];

        foreach ($this->markdownFiles as $file) {
            $content = $file->getContents() ?? '';
            $parsed = $service->parseFrontmatter($content);
            $fileTags = (array) ($parsed['frontmatter']['tags'] ?? []);
            foreach ($fileTags as $t) {
                if (is_string($t) && filled($t)) {
                    $tags[$t] = ($tags[$t] ?? 0) + 1;
                }
            }
        }

        arsort($tags);
        return $tags;
    }

    #[Computed]
    public function interactiveGraph(): array
    {
        try {
            return app(KnowledgeGraphService::class)->getInteractiveGraph(
                $this->portal->vault,
                $this->accessibleFiles
            );
        } catch (\Throwable) {
            return ['nodes' => [], 'edges' => []];
        }
    }

    #[Computed]
    public function totalWordCount(): int
    {
        $total = 0;
        foreach ($this->markdownFiles as $file) {
            $c = $file->getContents() ?? '';
            $total += str_word_count(strip_tags($c));
        }
        return $total;
    }
};
?>

<div
    class="synkk-portal-wrapper min-h-screen relative overflow-hidden"
    data-portal-theme="{{ $this->currentTheme }}"
    x-data="{
        scrollProgress: 0,
        previewVisible: false,
        previewTitle: '',
        previewExcerpt: '',
        previewX: 0,
        previewY: 0,
        showPreview(e, title, excerpt) {
            const rect = e.target.getBoundingClientRect();
            this.previewTitle = title;
            this.previewExcerpt = excerpt;
            this.previewX = Math.min(window.innerWidth - 320, Math.max(16, rect.left));
            this.previewY = rect.bottom + 8;
            this.previewVisible = true;
        },
        hidePreview() {
            this.previewVisible = false;
        },
        initScrollTracker() {
            window.addEventListener('scroll', () => {
                const total = document.documentElement.scrollHeight - window.innerHeight;
                this.scrollProgress = total > 0 ? (window.scrollY / total) * 100 : 0;
                const readingBar = document.getElementById('synkk-reading-bar');
                if (readingBar) {
                    readingBar.style.width = this.scrollProgress + '%';
                }
            }, { passive: true });
        },
        openGraphModal() {
            $wire.graphModalOpen = true;
        }
    }"
    x-init="initScrollTracker()"
    x-on:keydown.window.cmd.k.prevent="$wire.commandPaletteOpen = true"
    x-on:keydown.window.ctrl.k.prevent="$wire.commandPaletteOpen = true"
>
    <!-- Background Dot Grid Atmosphere -->
    <div class="synkk-portal-grid-bg"></div>

    <!-- Ambient Floating Aurora Orbs -->
    <div class="synkk-aurora-orb synkk-aurora-float-1"></div>
    <div class="synkk-aurora-orb synkk-aurora-float-2"></div>

    @if (! $isUnlocked)
        <!-- Password Gate Screen -->
        <div class="relative z-10 flex min-h-screen flex-col items-center justify-center p-6 text-center">
            <div class="synkk-glass-card w-full max-w-md space-y-6 p-8 shadow-2xl">
                <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-500 ring-1 ring-amber-500/30">
                    <flux:icon icon="lock-closed" class="size-7" />
                </div>

                <div class="space-y-1.5">
                    <h1 class="text-2xl font-black tracking-tight text-white">{{ $portal->name }}</h1>
                    <p class="text-xs text-zinc-400">{{ __('This portal is password protected. Enter password to access.') }}</p>
                </div>

                <form wire:submit="unlock" class="space-y-4">
                    <div>
                        <input
                            type="password"
                            wire:model="passwordInput"
                            placeholder="Enter portal password"
                            class="w-full rounded-xl border border-zinc-700 bg-zinc-900/80 px-4 py-3 text-sm text-white placeholder-zinc-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-all"
                            autofocus
                        />
                        @error('passwordInput')
                            <p class="mt-2 text-xs text-rose-400 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 py-3 text-sm font-bold text-zinc-950 shadow-lg shadow-amber-500/20 hover:from-amber-400 hover:to-amber-500 transition-all cursor-pointer"
                    >
                        {{ __('Unlock Sovereign Portal →') }}
                    </button>
                </form>

                <div class="flex items-center justify-center gap-2 text-[11px] text-zinc-500 pt-2 border-t border-zinc-800/80">
                    <flux:icon icon="shield-check" class="size-3.5 text-amber-500/70" />
                    <span>{{ __('Cryptographically Verified · Powered by Synkk') }}</span>
                </div>
            </div>
        </div>
    @else
        <!-- Global Portal Navigation Header -->
        <header class="synkk-portal-header sticky top-0 z-40">
            <div class="mx-auto flex h-16 max-w-[1600px] w-full items-center justify-between px-4 sm:px-6 lg:px-8">
                <!-- Left: Logo & Portal Identity -->
                <div class="flex items-center gap-3.5">
                    <a href="{{ $portal->getUrl() }}" wire:navigate class="group flex items-center gap-2.5 font-bold tracking-tight">
                        <div class="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 text-zinc-950 font-black shadow-md shadow-amber-500/20 group-hover:scale-105 transition-transform">
                            S
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm font-bold tracking-tight text-white group-hover:text-amber-400 transition-colors sm:text-base">
                                {{ $portal->name }}
                            </span>
                            <span class="text-[10px] font-medium text-zinc-500 flex items-center gap-1">
                                <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                {{ __('Obsidian Sync Active') }}
                            </span>
                        </div>
                    </a>

                    @if ($portal->getSetting('badge_text'))
                        <span class="hidden rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-amber-400 sm:inline-block">
                            {{ $portal->getSetting('badge_text') }}
                        </span>
                    @endif
                </div>

                <!-- Center: Command Palette Trigger & Layout Toggles -->
                <div class="flex items-center gap-3">
                    <!-- Quick Search Pill -->
                    <button
                        type="button"
                        x-on:click="$wire.commandPaletteOpen = true"
                        class="hidden items-center gap-2 rounded-xl border border-zinc-800 bg-zinc-900/60 px-3.5 py-1.5 text-xs text-zinc-400 hover:border-zinc-700 hover:text-zinc-200 sm:flex transition-all cursor-pointer shadow-xs"
                    >
                        <flux:icon icon="magnifying-glass" class="size-3.5 text-zinc-400" />
                        <span>{{ __('Search notes…') }}</span>
                        <kbd class="rounded bg-zinc-800/80 px-1.5 py-0.5 text-[10px] font-mono text-zinc-400 border border-zinc-700/60">⌘K</kbd>
                    </button>

                    <!-- Layout Mode Switcher -->
                    <div class="flex items-center rounded-xl border border-zinc-800/80 bg-zinc-900/80 p-1 text-xs shadow-inner">
                        <button
                            type="button"
                            wire:click="switchLayout('docs')"
                            class="flex items-center gap-1.5 rounded-lg px-2.5 py-1 font-medium transition-all {{ $this->currentLayout === 'docs' ? 'bg-amber-500 text-zinc-950 font-bold shadow-xs' : 'text-zinc-400 hover:text-white' }}"
                            title="Documentation tree layout"
                        >
                            <flux:icon icon="bars-3-bottom-left" class="size-3.5" />
                            <span class="hidden md:inline">{{ __('Docs') }}</span>
                        </button>
                        <button
                            type="button"
                            wire:click="switchLayout('bento')"
                            class="flex items-center gap-1.5 rounded-lg px-2.5 py-1 font-medium transition-all {{ $this->currentLayout === 'bento' ? 'bg-amber-500 text-zinc-950 font-bold shadow-xs' : 'text-zinc-400 hover:text-white' }}"
                            title="Bento Card Grid layout"
                        >
                            <flux:icon icon="squares-2x2" class="size-3.5" />
                            <span class="hidden md:inline">{{ __('Bento') }}</span>
                        </button>
                        <button
                            type="button"
                            wire:click="switchLayout('dashboard')"
                            class="flex items-center gap-1.5 rounded-lg px-2.5 py-1 font-medium transition-all {{ $this->currentLayout === 'dashboard' ? 'bg-amber-500 text-zinc-950 font-bold shadow-xs' : 'text-zinc-400 hover:text-white' }}"
                            title="Client Hub / Dashboard layout"
                        >
                            <flux:icon icon="chart-bar-square" class="size-3.5" />
                            <span class="hidden md:inline">{{ __('Hub') }}</span>
                        </button>
                        <button
                            type="button"
                            wire:click="switchLayout('graph')"
                            class="flex items-center gap-1.5 rounded-lg px-2.5 py-1 font-medium transition-all {{ $this->currentLayout === 'graph' ? 'bg-amber-500 text-zinc-950 font-bold shadow-xs' : 'text-zinc-400 hover:text-white' }}"
                            title="Interactive Knowledge Graph layout"
                        >
                            <flux:icon icon="share" class="size-3.5" />
                            <span class="hidden md:inline">{{ __('Graph') }}</span>
                        </button>
                    </div>

                    <!-- Interactive Knowledge Graph Button -->
                    <button
                        type="button"
                        x-on:click="openGraphModal()"
                        class="hidden lg:inline-flex items-center gap-1.5 rounded-xl border border-zinc-800 bg-zinc-900/70 px-3 py-1.5 text-xs font-semibold text-zinc-300 hover:border-amber-500/40 hover:text-amber-400 transition-all shadow-xs"
                        title="Open interactive knowledge graph canvas"
                    >
                        <flux:icon icon="share" class="size-3.5 text-amber-400" />
                        <span>{{ __('Graph') }}</span>
                        <span class="rounded bg-zinc-800 px-1.5 py-0.2 text-[10px] text-zinc-400">
                            {{ count($this->interactiveGraph['nodes']) }}
                        </span>
                    </button>

                    <!-- Theme Switcher Dropdown Menu -->
                    <div class="relative" x-data="{ themeMenuOpen: false }">
                        <button
                            type="button"
                            x-on:click="themeMenuOpen = ! themeMenuOpen"
                            class="flex items-center gap-1.5 rounded-xl border border-zinc-800 bg-zinc-900/70 p-2 text-zinc-300 hover:border-zinc-700 hover:text-white transition-all shadow-xs"
                            title="Switch Theme"
                        >
                            <flux:icon icon="paint-brush" class="size-4 text-amber-400" />
                            <flux:icon icon="chevron-down" class="size-3 text-zinc-500" />
                        </button>

                        <div
                            x-show="themeMenuOpen"
                            x-on:click.outside="themeMenuOpen = false"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            class="absolute right-0 mt-2 w-48 rounded-2xl border border-zinc-800 bg-zinc-900/95 p-1.5 shadow-2xl backdrop-blur-xl z-50 space-y-1"
                            style="display: none;"
                        >
                            <div class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-zinc-500">
                                {{ __('Color Themes') }}
                            </div>
                            @php
                                $themes = [
                                    'obsidian-noir' => ['label' => 'Obsidian Noir', 'dot' => 'bg-amber-400'],
                                    'slate-luxe' => ['label' => 'Slate Luxe', 'dot' => 'bg-indigo-400'],
                                    'midnight-emerald' => ['label' => 'Midnight Emerald', 'dot' => 'bg-emerald-400'],
                                    'paper-craft' => ['label' => 'Paper Craft', 'dot' => 'bg-stone-300'],
                                    'amber-gold' => ['label' => 'Amber Gold', 'dot' => 'bg-yellow-400'],
                                ];
                            @endphp
                            @foreach ($themes as $tKey => $tMeta)
                                <button
                                    type="button"
                                    wire:click="switchTheme('{{ $tKey }}');"
                                    x-on:click="window.setPortalTheme('{{ $tKey }}'); themeMenuOpen = false;"
                                    data-theme-choice="{{ $tKey }}"
                                    class="flex w-full items-center justify-between rounded-xl px-2.5 py-1.5 text-xs text-left transition-colors {{ $this->currentTheme === $tKey ? 'bg-zinc-800 text-white font-semibold' : 'text-zinc-400 hover:bg-zinc-800/60 hover:text-zinc-200' }}"
                                >
                                    <span class="flex items-center gap-2">
                                        <span class="size-2 rounded-full {{ $tMeta['dot'] }}"></span>
                                        <span>{{ $tMeta['label'] }}</span>
                                    </span>
                                    @if ($this->currentTheme === $tKey)
                                        <flux:icon icon="check" class="size-3.5 text-amber-400" />
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Mobile Drawer Toggle -->
                    <button
                        type="button"
                        x-on:click="$wire.mobileDrawerOpen = ! $wire.mobileDrawerOpen"
                        class="rounded-xl border border-zinc-800 p-2 text-zinc-400 hover:text-white md:hidden"
                    >
                        <flux:icon icon="bars-3" class="size-5" />
                    </button>
                </div>
            </div>
        </header>

        <!-- ======================================================================
             LAYOUT 1: DOCS HUB & SOVEREIGN KNOWLEDGE ENGINE
             ====================================================================== -->
        @if ($this->currentLayout === 'docs')
            <div class="relative z-10 mx-auto max-w-[1600px] w-full px-4 sm:px-6 lg:px-8">
                <div class="flex gap-8 py-8">
                    <!-- Left Sidebar: Obsidian Vault Tree Explorer -->
                    <aside class="hidden w-72 shrink-0 md:block">
                        <div class="sticky top-24 space-y-4">
                            <!-- Sidebar Header with Filter -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-zinc-500">
                                    <span>{{ __('Vault Explorer') }}</span>
                                    <span class="rounded-full bg-zinc-800/80 px-2 py-0.5 text-[10px] text-zinc-400">
                                        {{ $this->markdownFiles->count() }} notes
                                    </span>
                                </div>

                                <div class="relative">
                                    <flux:icon icon="magnifying-glass" class="absolute left-2.5 top-2 size-3.5 text-zinc-500" />
                                    <input
                                        type="text"
                                        wire:model.live.debounce.150ms="sidebarFilter"
                                        placeholder="Filter documents…"
                                        class="w-full rounded-xl border border-zinc-800 bg-zinc-900/60 py-1.5 pl-8 pr-3 text-xs text-white placeholder-zinc-500 focus:border-amber-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <!-- Document Nav List -->
                            <nav class="max-h-[calc(100vh-280px)] overflow-y-auto space-y-1 text-xs pr-1">
                                @forelse ($this->filteredMarkdownFiles as $file)
                                    @php
                                        $isActive = $this->activeFile && $this->activeFile->id === $file->id;
                                        $filename = pathinfo($file->path, PATHINFO_FILENAME);
                                        $dirname = pathinfo($file->path, PATHINFO_DIRNAME);
                                    @endphp
                                    <button
                                        type="button"
                                        wire:click="selectNote('{{ addslashes($file->path) }}')"
                                        class="group flex w-full items-center justify-between rounded-xl px-2.5 py-2 text-left font-medium transition-all {{ $isActive ? 'bg-amber-500/10 text-amber-400 font-semibold border-l-2 border-amber-500 pl-2' : 'text-zinc-400 hover:bg-zinc-900/80 hover:text-zinc-200' }}"
                                    >
                                        <div class="flex items-center gap-2 truncate">
                                            <flux:icon icon="document-text" class="size-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-zinc-500 group-hover:text-zinc-400' }}" />
                                            <div class="truncate">
                                                <div class="truncate">{{ $filename }}</div>
                                                @if ($dirname !== '.')
                                                    <div class="text-[10px] text-zinc-500 truncate">{{ $dirname }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </button>
                                @empty
                                    <div class="py-6 text-center text-xs text-zinc-500">
                                        {{ __('No matching notes.') }}
                                    </div>
                                @endforelse
                            </nav>

                            <!-- Mini Graph Button in Sidebar -->
                            @if ($this->interactiveGraph['nodes'])
                                <div class="pt-3 border-t border-zinc-800/80">
                                    <button
                                        type="button"
                                        x-on:click="openGraphModal()"
                                        class="synkk-glass-card flex w-full items-center justify-between p-3 text-xs font-semibold text-zinc-300 hover:border-amber-500/40 hover:text-white transition-all cursor-pointer"
                                    >
                                        <span class="flex items-center gap-2">
                                            <flux:icon icon="share" class="size-4 text-amber-400" />
                                            <span>{{ __('Graph View') }}</span>
                                        </span>
                                        <span class="rounded-full bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 text-[10px] text-amber-400 font-bold">
                                            {{ count($this->interactiveGraph['nodes']) }}
                                        </span>
                                    </button>
                                </div>
                            @endif
                        </div>
                    </aside>

                    <!-- Center Content: Rendered Article & Backlinks -->
                    <main class="min-w-0 flex-1 max-w-5xl">
                        @if ($this->activeFile)
                            <article class="space-y-6">
                                <!-- Top Breadcrumb Trail -->
                                <div class="flex items-center gap-2 text-xs text-zinc-500">
                                    <a href="{{ $portal->getUrl() }}" wire:navigate class="hover:text-zinc-300">Vault</a>
                                    <flux:icon icon="chevron-right" class="size-3 text-zinc-600" />
                                    @php
                                        $dir = pathinfo($this->activeFile->path, PATHINFO_DIRNAME);
                                    @endphp
                                    @if ($dir !== '.')
                                        <span>{{ $dir }}</span>
                                        <flux:icon icon="chevron-right" class="size-3 text-zinc-600" />
                                    @endif
                                    <span class="text-amber-400 font-semibold truncate">{{ pathinfo($this->activeFile->path, PATHINFO_BASENAME) }}</span>
                                </div>

                                <!-- Article Header & Metadata -->
                                <div class="space-y-4 pb-6 border-b border-zinc-800/80">
                                    @if (! empty($this->renderedNote['frontmatter']['tags']))
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ((array) $this->renderedNote['frontmatter']['tags'] as $tag)
                                                <span class="rounded-lg border border-amber-500/20 bg-amber-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-amber-400">
                                                    #{{ $tag }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <h1 class="text-3xl font-black tracking-tight text-white sm:text-4xl">
                                        {{ $this->renderedNote['frontmatter']['title'] ?? pathinfo($this->activeFile->path, PATHINFO_FILENAME) }}
                                    </h1>

                                    <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-400">
                                        <span class="flex items-center gap-1.5">
                                            <flux:icon icon="clock" class="size-3.5 text-zinc-500" />
                                            {{ $this->renderedNote['reading_time'] }}
                                        </span>
                                        <span>•</span>
                                        <span class="flex items-center gap-1.5">
                                            <flux:icon icon="document-text" class="size-3.5 text-zinc-500" />
                                            {{ number_format($this->renderedNote['word_count']) }} words
                                        </span>
                                        <span>•</span>
                                        <span>Synced {{ $this->activeFile->updated_at?->diffForHumans() }}</span>
                                        <span>•</span>
                                        <span class="flex items-center gap-1 text-emerald-400">
                                            <flux:icon icon="shield-check" class="size-3.5" />
                                            <span>Sovereign Verified</span>
                                        </span>
                                    </div>
                                </div>

                                <!-- Markdown Body with .synkk-prose engine -->
                                <div class="synkk-prose mt-6">
                                    {!! $this->renderedNote['html'] !!}
                                </div>

                                <!-- Backlinks / Mentions -->
                                @if (! empty($this->renderedNote['backlinks']))
                                    <div class="synkk-glass-card mt-14 p-6 space-y-4">
                                        <div class="text-xs font-bold uppercase tracking-wider text-amber-400 flex items-center gap-2">
                                            <flux:icon icon="link" class="size-4" />
                                            {{ __('Notes linking to this document (:count)', ['count' => count($this->renderedNote['backlinks'])]) }}
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            @foreach ($this->renderedNote['backlinks'] as $bl)
                                                <button
                                                    type="button"
                                                    wire:click="selectNote('{{ addslashes($bl['path']) }}')"
                                                    class="flex items-center gap-3 rounded-xl border border-zinc-800 bg-zinc-900/60 p-3 text-left hover:border-amber-500/40 hover:bg-zinc-900 transition-all text-xs text-zinc-300 hover:text-white cursor-pointer"
                                                >
                                                    <flux:icon icon="document-text" class="size-4 text-amber-400 shrink-0" />
                                                    <span class="truncate font-semibold">{{ $bl['title'] }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </article>
                        @endif
                    </main>

                    <!-- Right Sidebar: Scrollspy Table of Contents -->
                    <aside class="hidden w-60 shrink-0 xl:block">
                        <div class="sticky top-24 space-y-6">
                            @if (! empty($this->renderedNote['toc']))
                                <div class="space-y-2">
                                    <div class="text-xs font-bold uppercase tracking-wider text-zinc-500">
                                        {{ __('On This Page') }}
                                    </div>
                                    <nav class="space-y-0.5 text-xs max-h-[calc(100vh-320px)] overflow-y-auto">
                                        @foreach ($this->renderedNote['toc'] as $item)
                                            <a
                                                href="#{{ $item['id'] }}"
                                                class="synkk-toc-link {{ $item['level'] > 2 ? 'ml-3 text-[11px]' : '' }}"
                                            >
                                                {{ $item['text'] }}
                                            </a>
                                        @endforeach
                                    </nav>
                                </div>
                            @endif

                            <div class="synkk-glass-card p-4 text-[11px] text-zinc-400 space-y-2">
                                <div class="flex items-center gap-2 font-bold text-zinc-200">
                                    <flux:icon icon="shield-check" class="size-4 text-amber-400" />
                                    <span>{{ __('Local-First Sovereign') }}</span>
                                </div>
                                <p class="leading-relaxed text-zinc-500">
                                    {{ __('Content synced directly from local Obsidian vault via cryptographic verification.') }}
                                </p>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>

        <!-- ======================================================================
             LAYOUT 2: BENTO SHOWCASE GRID
             ====================================================================== -->
        @elseif ($this->currentLayout === 'bento')
            <div class="relative z-10 mx-auto max-w-[1600px] w-full px-4 py-8 sm:px-6 lg:px-8 space-y-8">
                <!-- Bento Hero Header -->
                <div class="max-w-3xl space-y-3">
                    <div class="inline-flex items-center gap-2 rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-400">
                        <flux:icon icon="squares-2x2" class="size-3.5" />
                        <span>{{ __('Bento Knowledge Showcase') }}</span>
                    </div>
                    <h1 class="text-4xl font-black tracking-tight text-white sm:text-5xl">
                        {{ $portal->name }}
                    </h1>
                    <p class="text-sm text-zinc-400 leading-relaxed">
                        {{ $portal->description ?: __('Interactive notes, connected mental models, and architectural specifications.') }}
                    </p>
                </div>

                <!-- Top Feature Bento Row (Large Card + Graph Card + Stat Card) -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                    @if ($this->activeFile)
                        <!-- Large Spotlight Hero Card -->
                        <div
                            wire:click="switchLayout('docs')"
                            class="synkk-bento-card lg:col-span-7 group cursor-pointer"
                        >
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="rounded-full bg-amber-500/20 border border-amber-500/30 px-3 py-1 text-[11px] font-bold text-amber-400">
                                        {{ __('Featured Document') }}
                                    </span>
                                    <span class="text-xs text-zinc-400 flex items-center gap-1.5">
                                        <flux:icon icon="clock" class="size-3.5" />
                                        {{ $this->renderedNote['reading_time'] }}
                                    </span>
                                </div>

                                <h2 class="text-2xl font-black tracking-tight text-white group-hover:text-amber-400 transition-colors">
                                    {{ $this->renderedNote['frontmatter']['title'] ?? pathinfo($this->activeFile->path, PATHINFO_FILENAME) }}
                                </h2>

                                <p class="text-xs text-zinc-400 line-clamp-4 leading-relaxed">
                                    {{ app(PortalRendererService::class)->extractExcerpt($this->activeFile->getContents() ?? '', 260) }}
                                </p>
                            </div>

                            <div class="mt-8 flex items-center justify-between border-t border-zinc-800/80 pt-4 text-xs font-semibold">
                                <span class="text-amber-400 group-hover:underline">{{ __('Read Complete Document →') }}</span>
                                <span class="text-zinc-500">{{ number_format($this->renderedNote['word_count']) }} words</span>
                            </div>
                        </div>
                    @endif

                    <!-- Interactive Graph Preview Bento Card -->
                    <div
                        x-on:click="openGraphModal()"
                        class="synkk-bento-card lg:col-span-5 group cursor-pointer"
                    >
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="rounded-full bg-sky-500/20 border border-sky-500/30 px-3 py-1 text-[11px] font-bold text-sky-400">
                                    {{ __('Force-Directed Graph') }}
                                </span>
                                <flux:icon icon="share" class="size-4 text-sky-400" />
                            </div>

                            <h3 class="text-xl font-bold tracking-tight text-white group-hover:text-sky-400 transition-colors">
                                {{ __('Interactive Knowledge Topology') }}
                            </h3>

                            <p class="text-xs text-zinc-400 leading-relaxed">
                                {{ __('Explore bidirectional links, note clusters, and conceptual relationships in a real-time 2D physics simulation.') }}
                            </p>
                        </div>

                        <div class="mt-6 flex items-center justify-between border-t border-zinc-800/80 pt-4 text-xs font-semibold">
                            <span class="text-sky-400 group-hover:underline">{{ __('Launch Full Graph Modal →') }}</span>
                            <span class="rounded-full bg-zinc-800 px-2.5 py-0.5 text-[11px] text-zinc-300">
                                {{ count($this->interactiveGraph['nodes']) }} connected nodes
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Filter Bar: Search + Tags -->
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-zinc-800/80 pb-4 pt-2">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button
                            type="button"
                            wire:click="filterTag('')"
                            class="rounded-xl px-3 py-1.5 text-xs font-bold transition-all cursor-pointer {{ empty($this->activeTag) ? 'bg-amber-500 text-zinc-950 shadow-md shadow-amber-500/20' : 'bg-zinc-900/80 border border-zinc-800 text-zinc-400 hover:text-white' }}"
                        >
                            {{ __('All Documents') }} ({{ count($this->markdownFiles) }})
                        </button>
                        @foreach ($this->allTags as $tag => $count)
                            <button
                                type="button"
                                wire:click="filterTag('{{ $tag }}')"
                                class="rounded-xl px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer {{ $this->activeTag === $tag ? 'bg-amber-500 text-zinc-950 shadow-md shadow-amber-500/20' : 'bg-zinc-900/80 border border-zinc-800 text-zinc-400 hover:text-white' }}"
                            >
                                #{{ $tag }} <span class="opacity-60 text-[10px]">({{ $count }})</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="relative w-full max-w-xs sm:w-64">
                        <flux:icon icon="magnifying-glass" class="absolute left-3 top-2.5 size-4 text-zinc-500" />
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="searchQuery"
                            placeholder="Filter cards…"
                            class="w-full rounded-xl border border-zinc-800 bg-zinc-900/80 py-2 pl-9 pr-3 text-xs text-white placeholder-zinc-500 focus:border-amber-500 focus:outline-none shadow-inner"
                        />
                    </div>
                </div>

                <!-- Bento Card Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @forelse ($this->bentoCards as $card)
                        <div
                            wire:key="card-{{ $card['path'] }}"
                            wire:click="selectNote('{{ addslashes($card['path']) }}'); switchLayout('docs');"
                            class="synkk-bento-card group"
                        >
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex size-9 items-center justify-center rounded-xl bg-amber-500/10 text-amber-400 group-hover:bg-amber-500 group-hover:text-zinc-950 transition-colors shadow-xs">
                                        <flux:icon icon="{{ $card['icon'] }}" class="size-4.5" />
                                    </div>
                                    @if ($card['badge'])
                                        <span class="rounded-full bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 text-[10px] font-bold text-amber-400">
                                            {{ $card['badge'] }}
                                        </span>
                                    @endif
                                </div>

                                <h2 class="text-base font-bold tracking-tight text-white group-hover:text-amber-400 transition-colors">
                                    {{ $card['title'] }}
                                </h2>

                                <p class="text-xs text-zinc-400 line-clamp-3 leading-relaxed">
                                    {{ $card['excerpt'] }}
                                </p>
                            </div>

                            <div class="mt-6 flex items-center justify-between border-t border-zinc-800/80 pt-3 text-[11px] text-zinc-500">
                                <span>{{ $card['reading_time'] }}</span>
                                <span class="text-amber-400 font-semibold group-hover:underline">Read Note →</span>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full rounded-2xl border border-dashed border-zinc-800 p-12 text-center text-zinc-500">
                            {{ __('No notes match your filter.') }}
                        </div>
                    @endforelse
                </div>
            </div>

        <!-- ======================================================================
             LAYOUT 3: CLIENT HUB / EXECUTIVE DASHBOARD
             ====================================================================== -->
        @elseif ($this->currentLayout === 'dashboard')
            <div class="relative z-10 mx-auto max-w-[1600px] w-full px-4 py-8 sm:px-6 lg:px-8 space-y-8">
                <!-- Welcome Executive Banner -->
                <div class="synkk-glass-card relative overflow-hidden p-8 sm:p-10">
                    <div class="max-w-2xl space-y-3">
                        <div class="inline-flex items-center gap-2 rounded-full bg-amber-500/20 border border-amber-500/30 px-3 py-1 text-xs font-bold text-amber-400">
                            <flux:icon icon="shield-check" class="size-3.5" />
                            <span>{{ __('Client Portal & Knowledge Center') }}</span>
                        </div>
                        <h1 class="text-3xl font-black tracking-tight text-white sm:text-4xl">
                            {{ $portal->name }}
                        </h1>
                        <p class="text-sm text-zinc-300 leading-relaxed">
                            {{ $portal->description ?: __('Secure workspace for specifications, documentation, and connected project deliverables.') }}
                        </p>
                    </div>
                </div>

                <!-- 4 KPI Overview Metric Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="synkk-glass-card p-5 space-y-1">
                        <div class="text-xs font-semibold text-zinc-400">{{ __('Knowledge Documents') }}</div>
                        <div class="text-3xl font-black text-white">{{ $this->markdownFiles->count() }}</div>
                        <div class="text-[11px] text-zinc-500">{{ __('Synchronized from Obsidian vault') }}</div>
                    </div>
                    <div class="synkk-glass-card p-5 space-y-1">
                        <div class="text-xs font-semibold text-zinc-400">{{ __('Graph Connections') }}</div>
                        <div class="text-3xl font-black text-amber-400">{{ count($this->interactiveGraph['edges']) }}</div>
                        <div class="text-[11px] text-zinc-500">{{ __('Bidirectional wikilinks') }}</div>
                    </div>
                    <div class="synkk-glass-card p-5 space-y-1">
                        <div class="text-xs font-semibold text-zinc-400">{{ __('Total Vault Volume') }}</div>
                        <div class="text-3xl font-black text-emerald-400">{{ number_format($this->totalWordCount) }}</div>
                        <div class="text-[11px] text-zinc-500">{{ __('Written words verified') }}</div>
                    </div>
                    <div class="synkk-glass-card p-5 space-y-1">
                        <div class="text-xs font-semibold text-zinc-400">{{ __('Portal Views') }}</div>
                        <div class="text-3xl font-black text-sky-400">{{ number_format($portal->views_count) }}</div>
                        <div class="text-[11px] text-zinc-500">{{ __('All time page visits') }}</div>
                    </div>
                </div>

                <!-- Document Repository Table & Overview -->
                <div class="synkk-glass-card p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-zinc-800 pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-white">{{ __('Document Repository') }}</h2>
                            <p class="text-xs text-zinc-400">{{ __('All accessible knowledge files in this sovereign vault.') }}</p>
                        </div>
                        <button
                            type="button"
                            wire:click="switchLayout('docs')"
                            class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-3.5 py-1.5 text-xs font-bold text-amber-400 hover:bg-amber-500/20 transition-all cursor-pointer"
                        >
                            {{ __('Open Full Documentation →') }}
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-zinc-800 text-zinc-400 uppercase tracking-wider font-semibold">
                                    <th class="py-3 px-4">{{ __('Document') }}</th>
                                    <th class="py-3 px-4">{{ __('Location') }}</th>
                                    <th class="py-3 px-4">{{ __('Read Time') }}</th>
                                    <th class="py-3 px-4">{{ __('Last Synced') }}</th>
                                    <th class="py-3 px-4 text-right">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-800/60">
                                @foreach ($this->markdownFiles as $file)
                                    @php
                                        $fName = pathinfo($file->path, PATHINFO_FILENAME);
                                        $fDir = pathinfo($file->path, PATHINFO_DIRNAME);
                                    @endphp
                                    <tr class="hover:bg-zinc-800/40 transition-colors group">
                                        <td class="py-3 px-4 font-semibold text-white group-hover:text-amber-400">
                                            <div class="flex items-center gap-2">
                                                <flux:icon icon="document-text" class="size-4 text-amber-400 shrink-0" />
                                                <span>{{ $fName }}</span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-zinc-400">
                                            {{ $fDir === '.' ? 'Root' : $fDir }}
                                        </td>
                                        <td class="py-3 px-4 text-zinc-400">
                                            {{ max(1, (int) ceil(str_word_count(strip_tags($file->getContents() ?? '')) / 200)) }} min
                                        </td>
                                        <td class="py-3 px-4 text-zinc-400">
                                            {{ $file->updated_at?->diffForHumans() ?? 'recently' }}
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <button
                                                type="button"
                                                wire:click="selectNote('{{ addslashes($file->path) }}'); switchLayout('docs');"
                                                class="rounded-lg bg-zinc-800 px-2.5 py-1 text-[11px] font-semibold text-zinc-200 hover:bg-amber-500 hover:text-zinc-950 transition-colors cursor-pointer"
                                            >
                                                {{ __('View') }}
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <!-- ======================================================================
             LAYOUT 4: INTERACTIVE KNOWLEDGE GRAPH FULL VIEW
             ====================================================================== -->
        @elseif ($this->currentLayout === 'graph')
            <div class="relative z-10 mx-auto max-w-[1600px] w-full px-4 py-8 sm:px-6 lg:px-8 space-y-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-400 mb-2">
                            <flux:icon icon="share" class="size-3.5" />
                            <span>{{ __('Knowledge Graph Canvas') }}</span>
                        </div>
                        <h1 class="text-3xl font-black tracking-tight text-white sm:text-4xl">
                            {{ $portal->name }}
                        </h1>
                        <p class="text-xs text-zinc-400 leading-relaxed mt-1">
                            {{ __('Explore how your notes connect. Drag nodes, scroll to zoom, or select any note from the index to read.') }}
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="switchLayout('docs')"
                        class="inline-flex items-center gap-2 rounded-xl border border-zinc-800 bg-zinc-900/80 px-4 py-2 text-xs font-semibold text-zinc-300 hover:text-white hover:border-amber-500/40 transition-colors self-start sm:self-auto cursor-pointer"
                    >
                        <flux:icon icon="arrow-left" class="size-3.5" />
                        <span>{{ __('Back to Document Reader') }}</span>
                    </button>
                </div>

                @include('pages.portals.partials.vault-graph-view')
            </div>
        @endif

        <!-- ======================================================================
             GLOBAL MODALS & INTERACTIVE OVERLAYS
             ====================================================================== -->

        <!-- Wikilink Hover Preview Popover -->
        <div
            x-show="previewVisible"
            x-transition
            class="synkk-wikilink-preview-card"
            :style="`left: ${previewX}px; top: ${previewY}px;`"
            style="display: none;"
        >
            <div class="space-y-1.5">
                <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-amber-400">
                    <flux:icon icon="link" class="size-3" />
                    <span>{{ __('Connected Note') }}</span>
                </div>
                <h4 class="text-xs font-bold text-white" x-text="previewTitle"></h4>
                <p class="text-[11px] text-zinc-400 leading-relaxed line-clamp-3" x-text="previewExcerpt"></p>
            </div>
        </div>

        <!-- Command Palette Modal (Cmd+K) -->
        <div
            x-show="$wire.commandPaletteOpen"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-6 md:p-20 bg-black/75 backdrop-blur-md"
            style="display: none;"
            x-on:keydown.escape.window="$wire.commandPaletteOpen = false"
        >
            <div
                x-on:click.outside="$wire.commandPaletteOpen = false"
                class="synkk-glass-card w-full max-w-xl p-4 shadow-2xl space-y-4"
            >
                <div class="relative flex items-center">
                    <flux:icon icon="magnifying-glass" class="absolute left-3.5 size-4 text-zinc-400" />
                    <input
                        type="text"
                        wire:model.live.debounce.150ms="searchQuery"
                        placeholder="Search all notes by title or content…"
                        class="w-full rounded-xl border border-zinc-800 bg-zinc-950/80 py-3 pl-10 pr-4 text-sm text-white placeholder-zinc-500 focus:border-amber-500 focus:outline-none"
                        autofocus
                    />
                </div>

                <div class="max-h-72 overflow-y-auto space-y-1 pr-1">
                    @forelse ($this->bentoCards as $result)
                        <button
                            type="button"
                            wire:click="selectNote('{{ addslashes($result['path']) }}'); switchLayout('docs');"
                            class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-xs hover:bg-zinc-800/80 transition-colors group cursor-pointer"
                        >
                            <span class="flex items-center gap-2.5 truncate font-semibold text-zinc-200 group-hover:text-amber-400">
                                <flux:icon icon="document-text" class="size-4 text-amber-400 shrink-0" />
                                <span class="truncate">{{ $result['title'] }}</span>
                            </span>
                            <span class="text-[10px] text-zinc-500 shrink-0">{{ $result['reading_time'] }}</span>
                        </button>
                    @empty
                        <div class="p-6 text-center text-xs text-zinc-500">
                            {{ __('No notes found matching your search.') }}
                        </div>
                    @endforelse
                </div>

                <div class="flex items-center justify-between border-t border-zinc-800/80 pt-3 text-[11px] text-zinc-500">
                    <span>{{ __('Navigate with mouse or click note') }}</span>
                    <span><kbd class="rounded bg-zinc-800 px-1.5 py-0.5 font-mono">ESC</kbd> to close</span>
                </div>
            </div>
        </div>

        <!-- Interactive 2D Knowledge Graph Modal -->
        <div
            x-show="$wire.graphModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-black/85 backdrop-blur-xl"
            style="display: none;"
            x-on:keydown.escape.window="$wire.graphModalOpen = false"
        >
            <div
                x-on:click.outside="$wire.graphModalOpen = false"
                class="relative w-full max-w-6xl shadow-2xl overflow-hidden rounded-[1.75rem] border border-zinc-700/60 bg-[#0c0f12]"
            >
                <div class="absolute top-4 right-4 z-30">
                    <button
                        type="button"
                        x-on:click="$wire.graphModalOpen = false"
                        class="rounded-xl border border-zinc-700/80 bg-zinc-900/90 p-2 text-zinc-400 hover:text-white hover:bg-zinc-800 transition-colors cursor-pointer shadow-lg"
                        title="{{ __('Close Graph') }}"
                    >
                        <flux:icon icon="x-mark" class="size-5" />
                    </button>
                </div>

                @include('pages.portals.partials.vault-graph-view')
            </div>
        </div>

        <!-- Mobile Drawer -->
        <div
            x-show="$wire.mobileDrawerOpen"
            class="fixed inset-0 z-50 flex md:hidden bg-black/80 backdrop-blur-md"
            style="display: none;"
        >
            <div
                x-on:click.outside="$wire.mobileDrawerOpen = false"
                class="w-4/5 max-w-sm bg-zinc-950 border-r border-zinc-800 p-6 flex flex-col justify-between"
            >
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-white">{{ __('Vault Notes') }}</span>
                        <button
                            type="button"
                            x-on:click="$wire.mobileDrawerOpen = false"
                            class="text-zinc-400 hover:text-white"
                        >
                            <flux:icon icon="x-mark" class="size-5" />
                        </button>
                    </div>

                    <nav class="space-y-1 text-xs max-h-[70vh] overflow-y-auto pr-1">
                        @foreach ($this->markdownFiles as $file)
                            @php
                                $isActive = $this->activeFile && $this->activeFile->id === $file->id;
                                $filename = pathinfo($file->path, PATHINFO_FILENAME);
                            @endphp
                            <button
                                type="button"
                                wire:click="selectNote('{{ addslashes($file->path) }}')"
                                class="flex w-full items-center gap-2 rounded-xl p-2.5 text-left {{ $isActive ? 'bg-amber-500/10 text-amber-400 font-bold' : 'text-zinc-400 hover:text-white' }}"
                            >
                                <flux:icon icon="document-text" class="size-4 shrink-0 text-amber-400" />
                                <span class="truncate">{{ $filename }}</span>
                            </button>
                        @endforeach
                    </nav>
                </div>
            </div>
        </div>
    @endif
</div>
