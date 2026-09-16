<div
    x-data="vaultGraph({
        nodes: {{ Js::from($this->interactiveGraph['nodes']) }},
        edges: {{ Js::from($this->interactiveGraph['edges']) }},
        theme: 'amber'
    })"
    wire:key="portal-vault-graph-{{ $portal->id }}"
    class="w-full overflow-hidden rounded-2xl border border-zinc-800 bg-[#0c0f12] shadow-2xl shadow-black/40"
    aria-labelledby="portal-graph-title"
>
    <!-- Header -->
    <header class="border-b border-white/10 bg-gradient-to-r from-amber-500/[0.08] via-transparent to-amber-500/[0.03] px-5 py-4 sm:px-6 sm:py-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="mb-1 flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-400">
                    <span class="size-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                    {{ __('Vault Knowledge Map') }}
                </div>
                <h3 id="portal-graph-title" class="text-lg font-bold tracking-tight text-white sm:text-xl">
                    {{ __('Interactive Document Graph') }}
                </h3>
                <p id="portal-graph-instructions" class="mt-1 max-w-xl text-xs text-zinc-400">
                    {{ __('Physics-based bidirectional links between notes. Drag nodes, scroll to zoom, or select any note from the index.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-300" aria-live="polite">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-black/30 px-3 py-1 text-xs">
                    <span class="size-1.5 rounded-full bg-amber-400"></span>
                    <span x-text="`${nodes.length} {{ __('notes') }}`"></span>
                </span>
                <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-black/30 px-3 py-1 text-xs">
                    <flux:icon icon="link" class="size-3.5 text-amber-400" />
                    <span x-text="`${edges.length} {{ __('connections') }}`"></span>
                </span>
            </div>
        </div>
    </header>

    <!-- Canvas + Note Index Split Pane -->
    <div class="grid h-[580px] lg:h-[660px] grid-cols-1 xl:grid-cols-[minmax(0,1fr)_19rem]">
        <!-- Left: Interactive Canvas -->
        <div class="relative size-full overflow-hidden bg-[radial-gradient(circle_at_center,rgba(245,158,11,0.06),transparent_55%)]">
            <canvas
                x-ref="graphCanvas"
                role="img"
                aria-label="{{ __('Interactive vault graph showing notes as nodes and wiki links as connections.') }}"
                aria-describedby="portal-graph-instructions"
                class="absolute inset-0 size-full cursor-grab active:cursor-grabbing"
            >
                {{ __('Your browser cannot render canvas. Use the note index to navigate.') }}
            </canvas>

            <!-- Search and Status Bar -->
            <div class="absolute inset-x-3 top-3 z-20 flex flex-col gap-2 sm:inset-x-4 sm:top-4 sm:flex-row sm:items-center sm:justify-between">
                <label class="flex min-h-9 items-center rounded-xl border border-white/10 bg-zinc-950/90 px-3 shadow-xl shadow-black/20 backdrop-blur-md sm:w-64">
                    <span class="sr-only">{{ __('Filter graph nodes') }}</span>
                    <flux:icon icon="magnifying-glass" class="mr-2 size-3.5 shrink-0 text-zinc-400" />
                    <input
                        x-model="search"
                        type="search"
                        autocomplete="off"
                        placeholder="{{ __('Filter graph nodes...') }}"
                        class="min-w-0 flex-1 bg-transparent text-xs text-white placeholder:text-zinc-500 focus:outline-none"
                    />
                    <button
                        x-show="search"
                        x-cloak
                        @click="search = ''"
                        type="button"
                        class="ml-2 rounded-md px-1 py-0.5 text-xs text-zinc-400 transition hover:bg-white/10 hover:text-white"
                        aria-label="{{ __('Clear graph filter') }}"
                    >
                        ×
                    </button>
                </label>

                <div class="self-start rounded-xl border border-white/10 bg-zinc-950/85 px-3 py-1.5 text-[11px] text-zinc-400 shadow-lg backdrop-blur-md sm:self-auto">
                    {{ __('Click a node or note to read document') }}
                </div>
            </div>

            <!-- Empty State -->
            <div
                x-show="nodes.length === 0"
                x-cloak
                class="absolute inset-0 z-10 grid place-items-center px-6 text-center"
            >
                <div class="max-w-sm rounded-2xl border border-white/10 bg-zinc-950/90 p-6 shadow-2xl backdrop-blur-md">
                    <div class="mx-auto grid size-11 place-items-center rounded-full bg-amber-400/10 text-amber-400">
                        <flux:icon icon="document-plus" class="size-5" />
                    </div>
                    <h4 class="mt-3 font-semibold text-white">{{ __('No connected notes found') }}</h4>
                    <p class="mt-1 text-xs text-zinc-400">{{ __('Add markdown files to your Obsidian vault to generate the knowledge graph.') }}</p>
                </div>
            </div>

            <!-- Floating Hover Tooltip -->
            <div
                x-show="hoveredNode"
                x-cloak
                :style="`left: ${tooltipX + 15}px; top: ${tooltipY + 15}px;`"
                class="pointer-events-none absolute z-30 hidden max-w-xs rounded-2xl border border-zinc-700/80 bg-zinc-950/95 p-3.5 text-xs text-white shadow-2xl backdrop-blur-md sm:block"
            >
                <div class="text-sm font-extrabold text-amber-400" x-text="hoveredNode?.name"></div>
                <div class="mt-0.5 truncate font-mono text-[10px] text-zinc-400" x-text="hoveredNode?.path"></div>
                <div class="mt-2 flex items-center gap-3 text-[11px] text-zinc-300">
                    <span>{{ __('Connections') }}: <strong class="font-bold text-white" x-text="hoveredNode?.linksCount"></strong></span>
                    <span>{{ __('Size') }}: <strong class="font-bold text-white" x-text="`${Math.round((hoveredNode?.size || 0)/1024)} KB`"></strong></span>
                </div>
                <div class="mt-2 text-[10px] font-semibold text-amber-400/90">
                    {{ __('Click to open note →') }}
                </div>
            </div>

            <!-- Zoom & Pan Navigation Controls -->
            <div class="absolute bottom-4 right-4 z-20 flex items-center gap-1 rounded-xl border border-white/10 bg-zinc-950/90 p-1.5 shadow-xl shadow-black/20 backdrop-blur-md">
                <button
                    @click="zoomIn()"
                    type="button"
                    class="rounded-lg p-2 text-zinc-300 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 cursor-pointer"
                    title="{{ __('Zoom In') }}"
                    aria-label="{{ __('Zoom in graph') }}"
                >
                    <flux:icon icon="plus" class="size-4" />
                </button>
                <button
                    @click="zoomOut()"
                    type="button"
                    class="rounded-lg p-2 text-zinc-300 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 cursor-pointer"
                    title="{{ __('Zoom Out') }}"
                    aria-label="{{ __('Zoom out graph') }}"
                >
                    <flux:icon icon="minus" class="size-4" />
                </button>
                <button
                    @click="resetView()"
                    type="button"
                    class="rounded-lg p-2 text-zinc-300 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 cursor-pointer"
                    title="{{ __('Reset View') }}"
                    aria-label="{{ __('Reset graph view') }}"
                >
                    <flux:icon icon="arrow-path" class="size-4" />
                </button>
            </div>
        </div>

        <!-- Right: Connected Note Index -->
        <aside class="flex min-h-0 flex-col border-t border-white/10 bg-zinc-950/80 xl:h-full xl:border-l xl:border-t-0" aria-labelledby="portal-graph-index-title">
            <div class="border-b border-white/10 px-4 py-3.5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-zinc-500">{{ __('Connected Note Index') }}</p>
                        <h4 id="portal-graph-index-title" class="mt-0.5 text-xs font-semibold text-white">{{ __('Open any document') }}</h4>
                    </div>
                    <kbd class="hidden rounded-md border border-white/10 bg-white/5 px-2 py-0.5 font-mono text-[10px] text-zinc-400 sm:inline">Enter</kbd>
                </div>
            </div>

            <div class="max-h-64 min-h-0 overflow-y-auto p-2.5 xl:max-h-none xl:flex-1" role="region" aria-label="{{ __('Graph notes') }}" tabindex="0">
                <ul class="space-y-1" role="list">
                    <template
                        x-for="node in nodes.filter((candidate) => !search || (candidate.name || '').toLowerCase().includes(search.toLowerCase()))"
                        :key="node.id"
                    >
                        <li>
                            <button
                                type="button"
                                @click="selectNode(node)"
                                @keydown="handleNodeKeydown($event, node)"
                                class="group flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left transition hover:bg-white/[0.06] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 cursor-pointer"
                                :aria-label="`{{ __('Open') }} ${node.name} {{ __('in the document reader') }}`"
                            >
                                <span class="relative flex size-7 shrink-0 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] text-zinc-400 transition group-hover:border-amber-400/30 group-hover:bg-amber-400/10 group-hover:text-amber-400">
                                    <flux:icon icon="document-text" class="size-3.5" />
                                    <span
                                        class="absolute -right-0.5 -top-0.5 size-1.5 rounded-full border border-zinc-950"
                                        :class="(node.linksCount || 0) > 0 ? 'bg-amber-400' : 'bg-zinc-600'"
                                    ></span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-xs font-medium text-zinc-200 group-hover:text-white" x-text="node.name"></span>
                                    <span class="mt-0.5 block truncate font-mono text-[10px] text-zinc-500" x-text="node.path"></span>
                                </span>
                                <span class="shrink-0 rounded-full bg-white/5 px-2 py-0.5 text-[9px] font-medium text-zinc-400" x-text="`${node.linksCount || 0} {{ __('links') }}`"></span>
                            </button>
                        </li>
                    </template>
                </ul>

                <div
                    x-show="nodes.length > 0 && nodes.filter((candidate) => !search || (candidate.name || '').toLowerCase().includes(search.toLowerCase())).length === 0"
                    x-cloak
                    class="px-4 py-8 text-center"
                    role="status"
                >
                    <flux:icon icon="magnifying-glass" class="mx-auto size-5 text-zinc-600" />
                    <p class="mt-2 text-xs font-medium text-zinc-300">{{ __('No notes match this filter') }}</p>
                    <button @click="search = ''" type="button" class="mt-2 text-[11px] font-medium text-amber-400 hover:text-amber-300 focus-visible:outline-none focus-visible:underline cursor-pointer">
                        {{ __('Clear filter') }}
                    </button>
                </div>
            </div>
        </aside>
    </div>
</div>
