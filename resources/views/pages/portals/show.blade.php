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
    }

    public function filterTag(string $tag): void
    {
        $this->activeTag = ($this->activeTag === $tag) ? '' : $tag;
    }

    public function switchLayout(string $layout): void
    {
        $this->layoutOverride = in_array($layout, ['docs', 'bento', 'dashboard', 'minimal'], true)
            ? $layout
            : null;
    }

    #[Computed]
    public function currentLayout(): string
    {
        return $this->layoutOverride ?: $this->portal->layout;
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
                'html' => '<div class="p-8 text-center text-zinc-500">No note selected or vault is empty.</div>',
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
            $readingTime = max(1, (int) ceil($words / 200)) . ' min';

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
};
?>

<div
    class="min-h-screen antialiased selection:bg-amber-500 selection:text-zinc-950 {{ $portal->getThemePalette()['bg'] }}"
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
            this.previewX = Math.min(window.innerWidth - 300, Math.max(16, rect.left));
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
            });
        }
    }"
    x-init="initScrollTracker()"
    x-on:keydown.window.cmd.k.prevent="$wire.commandPaletteOpen = true"
    x-on:keydown.window.ctrl.k.prevent="$wire.commandPaletteOpen = true"
>
    <!-- Reading Progress Bar -->
    <div
        class="synkk-reading-progress bg-gradient-to-r from-amber-500 via-emerald-400 to-indigo-500"
        :style="`width: ${scrollProgress}%`"
    ></div>

    @if (! $isUnlocked)
        <!-- Password Gate Screen -->
        <div class="flex min-h-screen flex-col items-center justify-center p-6 text-center">
            <div class="w-full max-w-sm space-y-6 rounded-2xl border border-zinc-800 bg-zinc-900/90 p-8 shadow-2xl backdrop-blur-xl">
                <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-500 ring-1 ring-amber-500/20">
                    <flux:icon icon="lock-closed" class="size-7" />
                </div>

                <div class="space-y-1">
                    <h1 class="text-xl font-bold tracking-tight text-white">{{ $portal->name }}</h1>
                    <p class="text-xs text-zinc-400">{{ __('This portal is password protected. Enter password to continue.') }}</p>
                </div>

                <form wire:submit="unlock" class="space-y-4">
                    <div>
                        <input
                            type="password"
                            wire:model="passwordInput"
                            placeholder="Enter password"
                            class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                            autofocus
                        />
                        @error('passwordInput')
                            <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-amber-500 py-2.5 text-sm font-semibold text-zinc-950 shadow-md hover:bg-amber-400 transition-colors"
                    >
                        {{ __('Unlock Portal →') }}
                    </button>
                </form>

                <div class="text-[11px] text-zinc-500">
                    {{ __('Powered by Synkk Sovereign Knowledge Engine') }}
                </div>
            </div>
        </div>
    @else
        <!-- Global Portal Navigation Header -->
        <header class="sticky top-0 z-40 border-b {{ $portal->getThemePalette()['border'] }} bg-zinc-950/80 backdrop-blur-md">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-4">
                    <a href="{{ $portal->getUrl() }}" wire:navigate class="flex items-center gap-2.5 font-bold tracking-tight">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-amber-500 text-zinc-950 font-black shadow-xs">
                            S
                        </div>
                        <span class="text-sm font-semibold text-zinc-100 sm:text-base">{{ $portal->name }}</span>
                    </a>

                    @if ($portal->getSetting('badge_text'))
                        <span class="hidden rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[11px] font-semibold text-amber-400 sm:inline-block">
                            {{ $portal->getSetting('badge_text') }}
                        </span>
                    @endif
                </div>

                <!-- Center: Command Palette Trigger & Layout Toggles -->
                <div class="flex items-center gap-2">
                    <!-- Quick Search Pill -->
                    <button
                        type="button"
                        x-on:click="$wire.commandPaletteOpen = true"
                        class="hidden items-center gap-2 rounded-xl border border-zinc-800 bg-zinc-900/60 px-3.5 py-1.5 text-xs text-zinc-400 hover:border-zinc-700 hover:text-zinc-200 sm:flex transition-all"
                    >
                        <flux:icon icon="magnifying-glass" class="size-3.5" />
                        <span>{{ __('Search notes…') }}</span>
                        <kbd class="rounded bg-zinc-800 px-1.5 py-0.5 text-[10px] font-mono text-zinc-400">⌘K</kbd>
                    </button>

                    <!-- Layout Mode Switcher -->
                    <div class="flex items-center rounded-xl border border-zinc-800 bg-zinc-900 p-0.5 text-xs">
                        <button
                            type="button"
                            wire:click="switchLayout('docs')"
                            class="rounded-lg px-2.5 py-1 font-medium transition-all {{ $this->currentLayout === 'docs' ? 'bg-zinc-800 text-white shadow-xs' : 'text-zinc-400 hover:text-zinc-200' }}"
                            title="Documentation tree layout"
                        >
                            <flux:icon icon="bars-3-bottom-left" class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            wire:click="switchLayout('bento')"
                            class="rounded-lg px-2.5 py-1 font-medium transition-all {{ $this->currentLayout === 'bento' ? 'bg-zinc-800 text-white shadow-xs' : 'text-zinc-400 hover:text-zinc-200' }}"
                            title="Bento Card Grid layout"
                        >
                            <flux:icon icon="squares-2x2" class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            wire:click="switchLayout('dashboard')"
                            class="rounded-lg px-2.5 py-1 font-medium transition-all {{ $this->currentLayout === 'dashboard' ? 'bg-zinc-800 text-white shadow-xs' : 'text-zinc-400 hover:text-zinc-200' }}"
                            title="Interactive Client Hub layout"
                        >
                            <flux:icon icon="chart-bar-square" class="size-3.5" />
                        </button>
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

        <!-- LAYOUT 1: DOCS & KNOWLEDGE ENGINE -->
        @if ($this->currentLayout === 'docs')
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex gap-8 py-8">
                    <!-- Left Sidebar: File Tree -->
                    <aside class="hidden w-64 shrink-0 md:block">
                        <div class="sticky top-24 space-y-4">
                            <div class="text-xs font-bold uppercase tracking-wider text-zinc-500">
                                {{ __('Vault Explorer') }} ({{ $this->markdownFiles->count() }})
                            </div>

                            <nav class="space-y-1 text-xs">
                                @foreach ($this->markdownFiles as $file)
                                    @php
                                        $isActive = $this->activeFile && $this->activeFile->id === $file->id;
                                        $filename = pathinfo($file->path, PATHINFO_FILENAME);
                                    @endphp
                                    <button
                                        type="button"
                                        wire:click="selectNote('{{ addslashes($file->path) }}')"
                                        class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left font-medium transition-colors {{ $isActive ? 'bg-amber-500/10 text-amber-400 font-semibold' : 'text-zinc-400 hover:bg-zinc-900 hover:text-zinc-200' }}"
                                    >
                                        <flux:icon icon="document-text" class="size-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-zinc-500' }}" />
                                        <span class="truncate">{{ $filename }}</span>
                                    </button>
                                @endforeach
                            </nav>

                            @if ($this->interactiveGraph['nodes'])
                                <div class="pt-4 border-t border-zinc-800/80">
                                    <button
                                        type="button"
                                        x-on:click="$wire.graphModalOpen = true"
                                        class="flex w-full items-center justify-between rounded-xl border border-zinc-800 bg-zinc-900/60 px-3 py-2.5 text-xs font-semibold text-zinc-300 hover:border-amber-500/40 hover:text-white transition-all"
                                    >
                                        <span class="flex items-center gap-2">
                                            <flux:icon icon="share" class="size-4 text-amber-400" />
                                            {{ __('Interactive Graph') }}
                                        </span>
                                        <span class="rounded bg-zinc-800 px-1.5 py-0.5 text-[10px] text-zinc-400">
                                            {{ count($this->interactiveGraph['nodes']) }}
                                        </span>
                                    </button>
                                </div>
                            @endif
                        </div>
                    </aside>

                    <!-- Center Content: Rendered Article -->
                    <main class="min-w-0 flex-1 max-w-3xl">
                        @if ($this->activeFile)
                            <article class="space-y-6">
                                <!-- Article Header & Frontmatter Badges -->
                                <div class="space-y-3 pb-6 border-b border-zinc-800">
                                    @if (! empty($this->renderedNote['frontmatter']['tags']))
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ((array) $this->renderedNote['frontmatter']['tags'] as $tag)
                                                <span class="rounded-md border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[11px] font-medium text-amber-400">
                                                    #{{ $tag }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <h1 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                                        {{ $this->renderedNote['frontmatter']['title'] ?? pathinfo($this->activeFile->path, PATHINFO_FILENAME) }}
                                    </h1>

                                    <div class="flex items-center gap-4 text-xs text-zinc-400">
                                        <span class="flex items-center gap-1.5">
                                            <flux:icon icon="clock" class="size-3.5" />
                                            {{ $this->renderedNote['reading_time'] }}
                                        </span>
                                        <span>•</span>
                                        <span>{{ number_format($this->renderedNote['word_count']) }} words</span>
                                        <span>•</span>
                                        <span>Updated {{ $this->activeFile->updated_at?->diffForHumans() }}</span>
                                    </div>
                                </div>

                                <!-- Markdown Body -->
                                <div class="prose prose-invert prose-zinc max-w-none prose-headings:font-bold prose-headings:tracking-tight prose-a:text-amber-400 prose-a:underline-offset-4 prose-code:text-amber-300">
                                    {!! $this->renderedNote['html'] !!}
                                </div>

                                <!-- Backlinks / Mentions -->
                                @if (! empty($this->renderedNote['backlinks']))
                                    <div class="mt-12 rounded-2xl border border-zinc-800 bg-zinc-900/40 p-6 space-y-3">
                                        <div class="text-xs font-bold uppercase tracking-wider text-zinc-400 flex items-center gap-2">
                                            <flux:icon icon="link" class="size-3.5 text-amber-400" />
                                            {{ __('Notes linking to this document (:count)', ['count' => count($this->renderedNote['backlinks'])]) }}
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            @foreach ($this->renderedNote['backlinks'] as $bl)
                                                <button
                                                    type="button"
                                                    wire:click="selectNote('{{ addslashes($bl['path']) }}')"
                                                    class="flex items-center gap-2 rounded-xl border border-zinc-800 bg-zinc-900 p-3 text-left hover:border-amber-500/30 transition-all text-xs text-zinc-300 hover:text-white"
                                                >
                                                    <flux:icon icon="document-text" class="size-4 text-zinc-500" />
                                                    <span class="truncate font-medium">{{ $bl['title'] }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </article>
                        @endif
                    </main>

                    <!-- Right Sidebar: TOC & Table of Contents -->
                    <aside class="hidden w-56 shrink-0 xl:block">
                        <div class="sticky top-24 space-y-6">
                            @if (! empty($this->renderedNote['toc']))
                                <div class="space-y-2">
                                    <div class="text-xs font-bold uppercase tracking-wider text-zinc-500">
                                        {{ __('On This Page') }}
                                    </div>
                                    <nav class="space-y-1 text-xs">
                                        @foreach ($this->renderedNote['toc'] as $item)
                                            <a
                                                href="#{{ $item['id'] }}"
                                                class="block truncate text-zinc-400 hover:text-amber-400 transition-colors {{ $item['level'] > 2 ? 'pl-3 text-[11px]' : '' }}"
                                            >
                                                {{ $item['text'] }}
                                            </a>
                                        @endforeach
                                    </nav>
                                </div>
                            @endif

                            <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/40 p-3 text-[11px] text-zinc-500 space-y-1">
                                <p class="font-semibold text-zinc-400">{{ __('Sovereign Synkk Vault') }}</p>
                                <p>{{ __('Content is synced directly from local Obsidian markdown files via cryptographic verification.') }}</p>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>

        <!-- LAYOUT 2: BENTO SHOWCASE GRID -->
        @elseif ($this->currentLayout === 'bento')
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">
                <!-- Bento Hero -->
                <div class="max-w-2xl space-y-3">
                    <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">
                        {{ $portal->name }}
                    </h1>
                    <p class="text-sm text-zinc-400 leading-relaxed">
                        {{ $portal->description ?: __('Explore interactive notes, documentation, and connected graphs in this vault.') }}
                    </p>
                </div>

                <!-- Filter Bar: Search + Tags -->
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-zinc-800 pb-4">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button
                            type="button"
                            wire:click="filterTag('')"
                            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all {{ empty($this->activeTag) ? 'bg-amber-500 text-zinc-950 shadow-xs' : 'bg-zinc-900 text-zinc-400 hover:text-white' }}"
                        >
                            {{ __('All Notes') }} ({{ count($this->markdownFiles) }})
                        </button>
                        @foreach ($this->allTags as $tag => $count)
                            <button
                                type="button"
                                wire:click="filterTag('{{ $tag }}')"
                                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all {{ $this->activeTag === $tag ? 'bg-amber-500 text-zinc-950 shadow-xs' : 'bg-zinc-900 text-zinc-400 hover:text-white' }}"
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
                            class="w-full rounded-xl border border-zinc-800 bg-zinc-900 py-1.5 pl-9 pr-3 text-xs text-white placeholder-zinc-500 focus:border-amber-500 focus:outline-none"
                        />
                    </div>
                </div>

                <!-- Bento Card Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @forelse ($this->bentoCards as $card)
                        <div
                            wire:key="card-{{ $card['path'] }}"
                            wire:click="selectNote('{{ addslashes($card['path']) }}'); switchLayout('docs');"
                            class="group relative flex flex-col justify-between rounded-2xl border border-zinc-800 bg-zinc-900/60 p-6 shadow-sm hover:border-amber-500/40 hover:bg-zinc-900/90 transition-all cursor-pointer hover:shadow-xl hover:-translate-y-0.5"
                        >
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex size-9 items-center justify-center rounded-xl bg-amber-500/10 text-amber-400 group-hover:bg-amber-500 group-hover:text-zinc-950 transition-colors">
                                        <flux:icon icon="{{ $card['icon'] }}" class="size-4.5" />
                                    </div>
                                    @if ($card['badge'])
                                        <span class="rounded-full bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 text-[10px] font-semibold text-amber-400">
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
                                <span>{{ $card['reading_time'] }} read</span>
                                <span class="group-hover:text-zinc-300 transition-colors">Read note →</span>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full rounded-2xl border border-dashed border-zinc-800 p-12 text-center text-zinc-500">
                            {{ __('No notes match your filter.') }}
                        </div>
                    @endforelse
                </div>
            </div>

        <!-- LAYOUT 3: CLIENT HUB / DASHBOARD -->
        @elseif ($this->currentLayout === 'dashboard')
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">
                <!-- Welcome Banner -->
                <div class="relative overflow-hidden rounded-3xl border border-amber-500/20 bg-gradient-to-br from-amber-500/10 via-zinc-900 to-zinc-950 p-8">
                    <div class="max-w-2xl space-y-3">
                        <span class="rounded-full bg-amber-500/20 px-3 py-1 text-xs font-semibold text-amber-400">
                            {{ __('Client Portal & Knowledge Center') }}
                        </span>
                        <h1 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                            {{ $portal->name }}
                        </h1>
                        <p class="text-sm text-zinc-300 leading-relaxed">
                            {{ $portal->description ?: __('Secure real-time workspace for documents, milestones, and project resources.') }}
                        </p>
                    </div>
                </div>

                <!-- Overview Stat Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="rounded-2xl border border-zinc-800 bg-zinc-900/60 p-5">
                        <div class="text-xs font-semibold text-zinc-400">{{ __('Knowledge Documents') }}</div>
                        <div class="mt-2 text-3xl font-bold text-white">{{ $this->markdownFiles->count() }}</div>
                        <div class="mt-1 text-xs text-zinc-500">{{ __('Synchronized from vault') }}</div>
                    </div>
                    <div class="rounded-2xl border border-zinc-800 bg-zinc-900/60 p-5">
                        <div class="text-xs font-semibold text-zinc-400">{{ __('Total Vault Assets') }}</div>
                        <div class="mt-2 text-3xl font-bold text-amber-400">{{ $this->accessibleFiles->count() }}</div>
                        <div class="mt-1 text-xs text-zinc-500">{{ __('Notes & attachments') }}</div>
                    </div>
                    <div class="rounded-2xl border border-zinc-800 bg-zinc-900/60 p-5">
                        <div class="text-xs font-semibold text-zinc-400">{{ __('Interactive Views') }}</div>
                        <div class="mt-2 text-3xl font-bold text-emerald-400">{{ number_format($portal->views_count) }}</div>
                        <div class="mt-1 text-xs text-zinc-500">{{ __('All time visits') }}</div>
                    </div>
                </div>

                <!-- Recent Vault Documents Grid -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-white">{{ __('Featured Documents') }}</h2>
                        <button
                            type="button"
                            wire:click="switchLayout('docs')"
                            class="text-xs text-amber-400 hover:underline"
                        >
                            {{ __('Open Full Documentation →') }}
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($this->markdownFiles->take(6) as $f)
                            @php
                                $c = $f->getContents() ?? '';
                                $ex = app(PortalRendererService::class)->extractExcerpt($c, 130);
                            @endphp
                            <div
                                wire:click="selectNote('{{ addslashes($f->path) }}'); switchLayout('docs');"
                                class="flex items-start gap-4 rounded-2xl border border-zinc-800 bg-zinc-900/60 p-4 hover:border-amber-500/40 hover:bg-zinc-900 cursor-pointer transition-all"
                            >
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-800 text-amber-400">
                                    <flux:icon icon="document-text" class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1 space-y-1">
                                    <h3 class="truncate text-sm font-bold text-white hover:text-amber-400">
                                        {{ pathinfo($f->path, PATHINFO_FILENAME) }}
                                    </h3>
                                    <p class="text-xs text-zinc-400 line-clamp-2 leading-relaxed">{{ $ex }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Wikilink Hover Preview Popover -->
        <div
            x-show="previewVisible"
            x-transition
            class="synkk-wikilink-preview-card fixed z-50 w-72 rounded-2xl border border-zinc-700 bg-zinc-900/95 p-4 text-left shadow-2xl backdrop-blur-xl pointer-events-none"
            :style="`left: ${previewX}px; top: ${previewY}px;`"
            style="display: none;"
        >
            <div class="space-y-1.5">
                <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-amber-400">
                    <flux:icon icon="link" class="size-3" />
                    <span>Linked Note</span>
                </div>
                <h4 class="text-xs font-bold text-white" x-text="previewTitle"></h4>
                <p class="text-[11px] text-zinc-400 leading-relaxed line-clamp-3" x-text="previewExcerpt"></p>
            </div>
        </div>

        <!-- Command Palette Modal (Cmd+K) -->
        <div
            x-show="$wire.commandPaletteOpen"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-6 md:p-20 bg-black/70 backdrop-blur-sm"
            style="display: none;"
            x-on:keydown.escape.window="$wire.commandPaletteOpen = false"
        >
            <div
                x-on:click.outside="$wire.commandPaletteOpen = false"
                class="w-full max-w-xl rounded-2xl border border-zinc-800 bg-zinc-900 p-4 shadow-2xl space-y-4"
            >
                <div class="relative flex items-center">
                    <flux:icon icon="magnifying-glass" class="absolute left-3.5 size-4 text-zinc-400" />
                    <input
                        type="text"
                        wire:model.live.debounce.150ms="searchQuery"
                        placeholder="Search all notes by title or content…"
                        class="w-full rounded-xl border border-zinc-800 bg-zinc-950 py-3 pl-10 pr-4 text-sm text-white placeholder-zinc-500 focus:border-amber-500 focus:outline-none"
                        autofocus
                    />
                </div>

                <div class="max-h-72 overflow-y-auto space-y-1 pr-1">
                    @forelse ($this->bentoCards as $result)
                        <button
                            type="button"
                            wire:click="selectNote('{{ addslashes($result['path']) }}'); switchLayout('docs');"
                            class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-xs hover:bg-zinc-800 transition-colors"
                        >
                            <span class="flex items-center gap-2.5 truncate font-medium text-zinc-200">
                                <flux:icon icon="document-text" class="size-4 text-amber-400 shrink-0" />
                                <span class="truncate">{{ $result['title'] }}</span>
                            </span>
                            <span class="text-[10px] text-zinc-500 shrink-0">{{ $result['reading_time'] }}</span>
                        </button>
                    @empty
                        <div class="p-4 text-center text-xs text-zinc-500">
                            {{ __('No notes found.') }}
                        </div>
                    @endforelse
                </div>

                <div class="flex items-center justify-between border-t border-zinc-800/80 pt-3 text-[11px] text-zinc-500">
                    <span>{{ __('Navigate with mouse or click note') }}</span>
                    <span><kbd class="rounded bg-zinc-800 px-1.5 py-0.5">ESC</kbd> to close</span>
                </div>
            </div>
        </div>

        <!-- Interactive Graph View Modal -->
        <div
            x-show="$wire.graphModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
            style="display: none;"
            x-on:keydown.escape.window="$wire.graphModalOpen = false"
        >
            <div
                x-on:click.outside="$wire.graphModalOpen = false"
                class="relative h-[80vh] w-full max-w-4xl rounded-3xl border border-zinc-800 bg-zinc-950 p-6 flex flex-col justify-between"
            >
                <div class="flex items-center justify-between border-b border-zinc-800 pb-4">
                    <div>
                        <h3 class="text-base font-bold text-white">{{ __('Interactive Knowledge Graph') }}</h3>
                        <p class="text-xs text-zinc-400">{{ __('Connected notes and backlink relationships.') }}</p>
                    </div>
                    <button
                        type="button"
                        x-on:click="$wire.graphModalOpen = false"
                        class="rounded-lg p-1.5 text-zinc-400 hover:text-white"
                    >
                        <flux:icon icon="x-mark" class="size-5" />
                    </button>
                </div>

                <!-- Canvas Graph Visualization Placeholder / Nodes -->
                <div class="flex-1 flex flex-wrap items-center justify-center gap-3 p-6 overflow-y-auto">
                    @foreach ($this->interactiveGraph['nodes'] as $node)
                        <button
                            type="button"
                            wire:click="selectNote('{{ addslashes($node['path'] ?? '') }}'); $set('graphModalOpen', false); switchLayout('docs');"
                            class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-zinc-900 px-3.5 py-1.5 text-xs font-semibold text-zinc-200 hover:border-amber-400 hover:text-amber-400 transition-all shadow-sm"
                        >
                            <span class="size-2 rounded-full bg-amber-400"></span>
                            <span>{{ $node['name'] ?? 'Note' }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="text-center text-xs text-zinc-500 border-t border-zinc-800 pt-3">
                    {{ __('Click any node to navigate to the note.') }}
                </div>
            </div>
        </div>
    @endif
</div>
