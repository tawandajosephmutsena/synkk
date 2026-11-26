<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth dark" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Synkk — Obsidian everywhere</title>
        <meta name="description" content="Self-hosted Obsidian sync with a real Markdown workspace, a visual vault graph, trusted devices, path permissions, verified changes, and recoverable versions.">
        <meta name="theme-color" content="#080c14" id="synkk-theme-color">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <script>
            (function() {
                const t = localStorage.getItem('synkk-theme') || 'dark';
                const isDark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                const meta = document.getElementById('synkk-theme-color');
                if (isDark) {
                    document.documentElement.classList.add('dark');
                    document.documentElement.setAttribute('data-theme', 'dark');
                    if (meta) meta.setAttribute('content', '#080c14');
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.setAttribute('data-theme', 'light');
                    if (meta) meta.setAttribute('content', '#f7f8f3');
                }
            })();
        </script>
        @fonts
        @vite(['resources/css/landing.css', 'resources/js/landing.js'])
    </head>
    <body class="synkk-site font-sans antialiased" data-motion="on">
        @php
            $pluginUrl = 'https://github.com/tawandajosephmutsena/synk-obsidian-plugin';
            $pluginReleaseUrl = 'https://github.com/tawandajosephmutsena/synk-obsidian-plugin/releases/latest';
            $pluginObsidianUri = 'obsidian://show-plugin?id=synkk-sync';
            $storeUrl = config('services.dodo.pro_checkout_url') ?: config('synkk.lemon_squeezy.store_url');
            $storeReady = (filled($storeUrl) && (filled(config('synkk.lemon_squeezy.store_id')) && filled(config('synkk.lemon_squeezy.product_id')) || filled(config('services.dodo.pro_checkout_url'))))
                || (filled(config('services.dodo.api_key')) && filled(config('services.dodo.pro_product_id')));
            $proPricing = config('synkk.pricing.pro_ltd', []);
            $cloudProPricing = config('synkk.pricing.cloud_pro', []);
            $cloudBusinessPricing = config('synkk.pricing.cloud_business', []);
            $aiAddonPricing = config('synkk.pricing.ai_addon', []);
            $proPrice = ($proPricing['currency_symbol'] ?? '$').number_format((float) ($proPricing['amount'] ?? 79), 0);
            $cloudProPrice = ($cloudProPricing['currency_symbol'] ?? '$').number_format((float) ($cloudProPricing['amount'] ?? 12), 0);
            $cloudBusinessPrice = ($cloudBusinessPricing['currency_symbol'] ?? '$').number_format((float) ($cloudBusinessPricing['amount'] ?? 25), 0);
            $aiAddonPrice = ($aiAddonPricing['currency_symbol'] ?? '$').number_format((float) ($aiAddonPricing['amount'] ?? 12), 0);
            $dodoReady = filled(config('services.dodo.api_key'))
                && filled(config('services.dodo.cloud_product_id'));
            $dodoTestMode = config('services.dodo.environment') === 'test_mode';
            $userTeam = auth()->check()
                ? (auth()->user()->currentTeam ?? auth()->user()->personalTeam() ?? auth()->user()->teams->first())
                : null;
            $dashboardUrl = $userTeam
                ? route('dashboard', ['current_team' => $userTeam->slug])
                : route('home');
        @endphp

        <a href="#main-content" class="synkk-skip-link">Skip to content</a>
        <div class="synkk-reading-progress" aria-hidden="true"><span></span></div>

        <header class="synkk-header">
            <div class="synkk-shell synkk-header__inner">
                <a href="{{ route('home') }}" class="synkk-brand" aria-label="Synkk home">
                    <img src="/images/synkk-logo.svg" alt="Synkk — Obsidian everywhere" width="689" height="270" fetchpriority="high">
                </a>

                <nav class="synkk-nav" aria-label="Primary navigation">
                    <a href="#product">Product</a>
                    <a href="#workflow">How it works</a>
                    <a href="#safety">Safety</a>
                    <a href="#comparison">Why Synkk</a>
                    <a href="#moonshot">Moonshot</a>
                    <a href="#pricing">Pricing</a>
                    <a href="#roadmap">Roadmap</a>
                    <a href="{{ route('about') }}">About</a>
                    <a href="{{ route('public.docs') }}">Docs</a>
                </nav>

                <div class="synkk-header__actions">
                    <div class="synkk-theme-switcher" data-theme-switcher aria-label="Theme switcher">
                        <button type="button" data-theme-set="light" title="Light mode" aria-label="Light mode">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        </button>
                        <button type="button" data-theme-set="dark" title="Dark mode" aria-label="Dark mode" class="is-active" aria-pressed="true">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                        </button>
                        <button type="button" data-theme-set="system" title="System preference" aria-label="System preference">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        </button>
                    </div>
                    @auth
                        <a href="{{ $dashboardUrl }}" class="synkk-button synkk-button--ink">Open dashboard <span aria-hidden="true">↗</span></a>
                    @else
                        <a href="{{ route('login') }}" class="synkk-login">Log in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="synkk-button synkk-button--ink">Create workspace <span aria-hidden="true">↗</span></a>
                        @endif
                    @endauth
                    <details class="synkk-mobile-menu" data-mobile-menu>
                        <summary aria-label="Open navigation"><span></span><span></span></summary>
                        <nav aria-label="Mobile navigation">
                            <a href="#product">Product <span>01</span></a>
                            <a href="#workflow">How it works <span>02</span></a>
                            <a href="#safety">Safety <span>03</span></a>
                            <a href="#comparison">Why Synkk <span>04</span></a>
                            <a href="#moonshot">Moonshot <span>05</span></a>
                            <a href="#pricing">Pricing <span>06</span></a>
                            <a href="#roadmap">Roadmap <span>07</span></a>
                            <a href="{{ route('about') }}">About &amp; Philosophy <span>08</span></a>
                            <a href="{{ route('public.docs') }}">Documentation <span>↗</span></a>
                            @guest
                                <a href="{{ route('login') }}">Log in <span>↗</span></a>
                            @endguest
                        </nav>
                    </details>
                </div>
            </div>
        </header>

        <div class="synkk-announcement">
            <div class="synkk-shell synkk-announcement__inner">
                <p><span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span> Obsidian plugin v1.1.1 is live in the Obsidian Community Plugins directory — now with AI Sidebar, Proposal Inbox &amp; Local E2EE AI</p>
                <a href="{{ $pluginObsidianUri }}">Install Plugin <span aria-hidden="true">→</span></a>
            </div>
        </div>

        <main id="main-content">
            <section class="synkk-hero synkk-shell" aria-labelledby="hero-heading">
                <div class="synkk-hero__frame">
                    <div class="synkk-hero__copy">
                        <p class="synkk-eyebrow synkk-reveal synkk-reveal--one">Sovereign Team Sync, Vault AI &amp; Remote MCP for Obsidian</p>
                        <h1 id="hero-heading" class="synkk-reveal--two"><span class="synkk-hero__line"><span>Your vault.</span></span> <span class="synkk-hero__line"><span>On every</span></span> <span class="synkk-hero__line synkk-hero__line--accent"><span>device.</span><svg viewBox="0 0 100 100" fill="none" aria-hidden="true"><path d="M18 51h64M53 22l29 29-29 29" stroke="currentColor" stroke-width="5"/></svg></span></h1>
                        <p class="synkk-hero__lede synkk-reveal synkk-reveal--three">A home for your notes. A connection between your devices. All on infrastructure you control. Granular path permissions, deterministic conflict safety, Vault AI &amp; permissioned MCP, Livewire portals, and instant QR pairing.</p>
                        <div class="synkk-hero__actions synkk-reveal synkk-reveal--four">
                            @auth
                                <a href="{{ $dashboardUrl }}" class="synkk-button synkk-button--accent">Open your vaults <span aria-hidden="true">→</span></a>
                            @else
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="synkk-button synkk-button--accent">Start your workspace <span aria-hidden="true">→</span></a>
                                @endif
                            @endauth
                            <a href="#product" class="synkk-button synkk-button--quiet">See the product <span aria-hidden="true">↓</span></a>
                        </div>
                        <ul class="synkk-hero__proof synkk-reveal synkk-reveal--four" aria-label="Synkk product principles">
                            <li>Plain Markdown</li>
                            <li>Self-hostable</li>
                            <li>Recoverable history</li>
                            <li>Path-Level ACLs</li>
                        </ul>
                    </div>

                    <div class="synkk-hero__media">
                        <div class="synkk-orbit synkk-orbit--outer" aria-hidden="true"><i></i></div>
                        <div class="synkk-orbit synkk-orbit--inner" aria-hidden="true"><i></i></div>
                        <span class="synkk-stage-label">A little more connected.</span>
                        <div class="synkk-device-chip synkk-device-chip--desktop" aria-hidden="true"><img src="/images/platforms/obsidian.svg" alt="" width="20" height="20">Your Obsidian vault <span>↗</span></div>
                        <div class="synkk-laptop" data-hero-layer aria-label="Synkk dashboard preview">
                            <div class="synkk-laptop__screen">
                                <div class="synkk-window-bar" aria-hidden="true"><span><i></i><i></i><i></i></span><small>synkk / workspace</small><span>↗</span></div>
                                <img src="/images/showcase/dashboard-overview.webp" alt="Synkk dashboard showing vault health, connected devices, recent activity, and storage" width="2300" height="1294" fetchpriority="high" decoding="async">
                            </div>
                        </div>
                        <div class="synkk-graph-peek" data-hero-layer aria-hidden="true">
                            <div><span><i></i> Connected thinking</span><span>↗</span></div>
                            <img src="/images/showcase/graph-full.webp" alt="" width="1280" height="720" decoding="async">
                        </div>
                        <div class="synkk-hero-status" data-hero-layer aria-label="Revision status shown in the product capture">
                            <span>Current revision</span>
                            <strong><i aria-hidden="true"></i> v78</strong>
                            <small>From the product preview</small>
                        </div>
                        <div class="synkk-stage-footer"><span>YOUR NOTES. YOUR INFRASTRUCTURE.</span><span aria-hidden="true">↙</span></div>
                    </div>
                </div>
                <div class="synkk-hero__footnote"><span>Made for the way your mind moves.</span><a href="#product">Explore Synkk <span aria-hidden="true">↓</span></a></div>
            </section>

            <section class="synkk-platforms" aria-label="Supported platforms">
                <div class="synkk-shell synkk-platforms__inner">
                    <p>One vault across the devices you already use.</p>
                    <ul>
                        @foreach ([
                            ['obsidian', 'Obsidian'],
                            ['macos', 'macOS'],
                            ['windows', 'Windows'],
                            ['linux', 'Linux'],
                            ['ios', 'iOS'],
                            ['android', 'Android'],
                        ] as [$icon, $platform])
                            <li><img src="/images/platforms/{{ $icon }}.svg" alt="" width="24" height="24" loading="lazy"><span>{{ $platform }}</span></li>
                        @endforeach
                    </ul>
                </div>
            </section>

            <section id="product" class="synkk-product synkk-viewport-section synkk-shell" aria-labelledby="product-heading">
                <header class="synkk-section-heading">
                    <div>
                        <p class="synkk-eyebrow">01 / Inside Synkk</p>
                        <h2 id="product-heading">Edit the note. Follow the graph.</h2>
                    </div>
                    <p>The same vault opens as a full Markdown workspace or a live map of the notes and links you can access.</p>
                </header>

                <div class="synkk-product-viewer" data-product-workbench>
                    <div class="synkk-product-tabs" role="tablist" aria-label="Real Synkk product views">
                        <button type="button" id="tab-markdown-editor" role="tab" data-product-tab="markdown-editor" aria-controls="panel-markdown-editor" aria-selected="true" tabindex="0"><span>01</span> Markdown Editor</button>
                        <button type="button" id="tab-graph" role="tab" data-product-tab="graph" aria-controls="panel-graph" aria-selected="false" tabindex="-1"><span>02</span> Graph View</button>
                        <p><i aria-hidden="true"></i> Real product capture</p>
                    </div>

                    <div class="synkk-product-screen">
                        <figure id="panel-markdown-editor" data-product-view="markdown-editor" role="tabpanel" aria-labelledby="tab-markdown-editor" tabindex="0">
                            <img src="/images/showcase/editor-full.webp" alt="The actual Synkk vault page showing the Markdown editor, note navigator, source with line numbers, and split preview" width="1280" height="720" loading="eager" decoding="async">
                            <figcaption><strong>A complete writing workspace.</strong><span>Formatting tools, document outline, minimap, split preview, sharing, versions, and save state.</span></figcaption>
                        </figure>
                        <figure id="panel-graph" data-product-view="graph" role="tabpanel" aria-labelledby="tab-graph" tabindex="0" hidden>
                            <img src="/images/showcase/graph-full.webp" alt="The actual Synkk vault graph showing connected notes, filtering, and an accessible note index" width="1280" height="720" loading="lazy" decoding="async">
                            <figcaption><strong>The same vault, mapped.</strong><span>Search, zoom, inspect links, and open any accessible note directly in the editor.</span></figcaption>
                        </figure>
                    </div>
                </div>
            </section>

            <section id="product-proof" class="synkk-features synkk-viewport-section" aria-labelledby="product-proof-heading">
                <div class="synkk-shell">
                    <header class="synkk-section-heading">
                        <div>
                            <p class="synkk-eyebrow">02 / Full product capabilities</p>
                            <h2 id="product-proof-heading">Four working surfaces. <span>One vault.</span> Built for power.</h2>
                        </div>
                        <p>Every tool your notes need to stay organized, connected, safe, and private — with large visuals and straight-to-the-point control.</p>
                    </header>

                    <div class="synkk-feature-cards">
                        <!-- Card 1: Overview & Fleet Visibility -->
                        <article class="synkk-feature-card">
                            <div class="synkk-feature-card__copy">
                                <div class="synkk-feature-card__badge">
                                    <span class="synkk-status-dot" aria-hidden="true"></span> Available now · Fleet Dashboard
                                </div>
                                <h3>See the vault at a glance.</h3>
                                <p>Monitor your entire vault ecosystem from a single pane of glass. Track storage usage, note counts, recent sync events, fleet revision status, and verify that all connected laptops and phones are synchronized.</p>
                                <ul class="synkk-feature-card__list">
                                    <li><strong>Live health scoring:</strong> Real-time audit of vault integrity, unverified changes, and storage limits.</li>
                                    <li><strong>Fleet telemetry:</strong> Inspect device sync times, client versions, and connection states across platforms.</li>
                                    <li><strong>Multi-vault switcher:</strong> Manage personal, work, and client vaults under one authenticated roof.</li>
                                </ul>
                                <div class="synkk-feature-card__actions">
                                    @auth
                                        <a href="{{ $dashboardUrl }}" class="synkk-button synkk-button--ink">Open fleet dashboard <span aria-hidden="true">↗</span></a>
                                    @else
                                        @if (Route::has('register'))
                                            <a href="{{ route('register') }}" class="synkk-button synkk-button--ink">Create workspace <span aria-hidden="true">↗</span></a>
                                        @endif
                                    @endauth
                                    <a href="{{ route('public.docs') }}#architecture" class="synkk-button synkk-button--quiet">Read architecture <span aria-hidden="true">→</span></a>
                                </div>
                            </div>
                            <div class="synkk-feature-card__media">
                                <div class="synkk-window-frame">
                                    <div class="synkk-window-bar" aria-hidden="true">
                                        <span><i></i><i></i><i></i></span>
                                        <small>synkk / fleet-overview</small>
                                        <span>↗</span>
                                    </div>
                                    <img src="/images/showcase/dashboard-overview.webp" alt="Synkk dashboard showing vault health, connected devices, recent activity, and storage" width="2300" height="1294" loading="lazy" decoding="async">
                                </div>
                            </div>
                        </article>

                        <!-- Card 2: Markdown Workspace (Reversed) -->
                        <article class="synkk-feature-card synkk-feature-card--reverse">
                            <div class="synkk-feature-card__copy">
                                <div class="synkk-feature-card__badge">
                                    <span class="synkk-status-dot" aria-hidden="true"></span> Available now · Web Workspace
                                </div>
                                <h3>Markdown Editor: Stay in the note.</h3>
                                <p>Never lose flow when you are away from your main machine. Synkk includes a full in-browser Markdown editor with live split preview, interactive document outline, minimap navigation, reading metrics, and keyboard shortcuts.</p>
                                <ul class="synkk-feature-card__list">
                                    <li><strong>Live split preview:</strong> GitHub-flavored Markdown rendered instantaneously side-by-side with your source.</li>
                                    <li><strong>Document outline & minimap:</strong> Jump through long-form notes with automated heading tree parsing.</li>
                                    <li><strong>Version-aware saves:</strong> Edit safely with revision checks that prevent silent overwrites.</li>
                                </ul>
                                <div class="synkk-feature-card__actions">
                                    @auth
                                        <a href="{{ $dashboardUrl }}" class="synkk-button synkk-button--ink">Open Markdown editor <span aria-hidden="true">↗</span></a>
                                    @else
                                        @if (Route::has('register'))
                                            <a href="{{ route('register') }}" class="synkk-button synkk-button--ink">Try the editor <span aria-hidden="true">↗</span></a>
                                        @endif
                                    @endauth
                                    <a href="{{ route('public.docs') }}#quickstart" class="synkk-button synkk-button--quiet">Read editor docs <span aria-hidden="true">→</span></a>
                                </div>
                            </div>
                            <div class="synkk-feature-card__media">
                                <div class="synkk-window-frame">
                                    <div class="synkk-window-bar" aria-hidden="true">
                                        <span><i></i><i></i><i></i></span>
                                        <small>synkk / markdown-editor</small>
                                        <span>↗</span>
                                    </div>
                                    <img src="/images/showcase/editor-full.webp" alt="The real Synkk Markdown editor showing note navigation, source, outline, and split preview" width="1280" height="720" loading="lazy" decoding="async">
                                </div>
                            </div>
                        </article>

                        <!-- Card 3: Interactive Visual Graph View -->
                        <article class="synkk-feature-card">
                            <div class="synkk-feature-card__copy">
                                <div class="synkk-feature-card__badge">
                                    <span class="synkk-status-dot" aria-hidden="true"></span> Available now · Knowledge Mapping
                                </div>
                                <h3>Graph View: Follow the thinking.</h3>
                                <p>Discover how your ideas connect across thousands of notes. Synkk constructs an interactive 2D physics-based force graph of all your <code>[[wikilinks]]</code> and tags. Inspect clusters, search for hidden connections, filter orphan notes, and click any node to jump straight into editing.</p>
                                <ul class="synkk-feature-card__list">
                                    <li><strong>Force-directed physics map:</strong> Smooth canvas simulation of relationships and note links.</li>
                                    <li><strong>Interactive inspection:</strong> Zoom, drag, and search nodes with instant cluster highlights.</li>
                                    <li><strong>Direct jump to editor:</strong> Click any node to open the underlying Markdown note immediately.</li>
                                </ul>
                                <div class="synkk-feature-card__actions">
                                    @auth
                                        <a href="{{ $dashboardUrl }}" class="synkk-button synkk-button--ink">Open Graph view <span aria-hidden="true">↗</span></a>
                                    @else
                                        @if (Route::has('register'))
                                            <a href="{{ route('register') }}" class="synkk-button synkk-button--ink">Explore Graph view <span aria-hidden="true">↗</span></a>
                                        @endif
                                    @endauth
                                    <a href="{{ route('public.docs') }}#architecture" class="synkk-button synkk-button--quiet">Read graph docs <span aria-hidden="true">→</span></a>
                                </div>
                            </div>
                            <div class="synkk-feature-card__media">
                                <div class="synkk-window-frame">
                                    <div class="synkk-window-bar" aria-hidden="true">
                                        <span><i></i><i></i><i></i></span>
                                        <small>synkk / graph-view</small>
                                        <span>↗</span>
                                    </div>
                                    <img src="/images/showcase/graph-full.webp" alt="The real Synkk Graph View showing connected notes and an accessible note index" width="1280" height="720" loading="lazy" decoding="async">
                                </div>
                            </div>
                        </article>

                        <!-- Card 4: Granular Path Permissions Matrix (Reversed) -->
                        <article class="synkk-feature-card synkk-feature-card--reverse">
                            <div class="synkk-feature-card__copy">
                                <div class="synkk-feature-card__badge">
                                    <span class="synkk-status-dot" aria-hidden="true"></span> Available now · Team Access Control
                                </div>
                                <h3>Set the boundary before you share.</h3>
                                <p>Traditional sync forces you into all-or-nothing sharing. Synkk gives you surgical control with a granular per-path permission matrix. Keep <code>Private/**</code> notes hidden, share <code>Client/**</code> as read-only, and collaborate on <code>Projects/**</code> with full read-write access.</p>
                                <ul class="synkk-feature-card__list">
                                    <li><strong>Per-member path rules:</strong> Assign Read, Write, or Hidden access per team member.</li>
                                    <li><strong>Inheritance & overrides:</strong> Subdirectories inherit parent rules with specific exceptions.</li>
                                    <li><strong>Zero leaks:</strong> Hidden paths are stripped from manifests before reaching untrusted devices.</li>
                                </ul>
                                <div class="synkk-feature-card__actions">
                                    @auth
                                        <a href="{{ $dashboardUrl }}" class="synkk-button synkk-button--ink">Configure permissions <span aria-hidden="true">↗</span></a>
                                    @else
                                        @if (Route::has('register'))
                                            <a href="{{ route('register') }}" class="synkk-button synkk-button--ink">Create team workspace <span aria-hidden="true">↗</span></a>
                                        @endif
                                    @endauth
                                    <a href="{{ route('public.docs') }}#permissions" class="synkk-button synkk-button--quiet">Permissions guide <span aria-hidden="true">→</span></a>
                                </div>
                            </div>
                            <div class="synkk-feature-card__media">
                                <div class="synkk-window-frame">
                                    <div class="synkk-window-bar" aria-hidden="true">
                                        <span><i></i><i></i><i></i></span>
                                        <small>synkk / permissions-matrix</small>
                                        <span>↗</span>
                                    </div>
                                    <img src="/images/showcase/permissions-full.webp" alt="The real Synkk Permissions Matrix showing default access, path-rule inheritance, hidden paths, and Add Path Rule controls" width="1280" height="720" loading="lazy" decoding="async">
                                </div>
                            </div>
                        </article>

                        <!-- Card 5: Safety Shield & Anti-Data Loss Protection -->
                        <article class="synkk-feature-card">
                            <div class="synkk-feature-card__copy">
                                <div class="synkk-feature-card__badge">
                                    <span class="synkk-status-dot" aria-hidden="true"></span> Available now · Safety Shield
                                </div>
                                <h3>Safety Shield: No note is ever silently lost.</h3>
                                <p>Other sync tools risk catastrophic data loss when mass deletions or stale sync collisions happen. Synkk includes Safety Shield: a deterministic guard that halts any sync attempting to delete more than 10% of your notes until you confirm it.</p>
                                <ul class="synkk-feature-card__list">
                                    <li><strong>10% Deletion threshold guard:</strong> Aborts destructive mass wipes with a safe one-time manual override.</li>
                                    <li><strong>Pre-mutation snapshots:</strong> Files are backed up locally to <code>.synkk/snapshots/</code> before remote overwrites.</li>
                                    <li><strong>Conflict fork preservation:</strong> Concurrent edits fork to <code>.sync-conflict-[timestamp].md</code> instead of clobbering.</li>
                                    <li><strong>Cryptographic fingerprinting:</strong> Every revision is verified using full SHA-256 content hashes.</li>
                                </ul>
                                <div class="synkk-feature-card__actions">
                                    <a href="{{ $pluginObsidianUri }}" class="synkk-button synkk-button--ink">Install Plugin in Obsidian <span aria-hidden="true">↗</span></a>
                                    <a href="{{ route('public.docs') }}#safety-shield" class="synkk-button synkk-button--quiet">Safety documentation <span aria-hidden="true">→</span></a>
                                </div>
                            </div>
                            <div class="synkk-feature-card__media">
                                <div class="synkk-feature-graphic synkk-feature-graphic--shield">
                                    <div class="synkk-graphic-shield-badge">
                                        <span class="synkk-graphic-shield-icon">🛡️</span>
                                        <strong>Safety Shield Active</strong>
                                        <small>10% bulk deletion threshold guard</small>
                                    </div>
                                    <div class="synkk-graphic-shield-items">
                                        <div class="synkk-shield-item">
                                            <span class="synkk-shield-item__badge">SHA-256</span>
                                            <div><strong>Content Checksum</strong><p>Cryptographic fingerprint on every accepted revision</p></div>
                                        </div>
                                        <div class="synkk-shield-item">
                                            <span class="synkk-shield-item__badge">SNAPSHOTS</span>
                                            <div><strong>Pre-Mutation Backups</strong><p>Local snapshot folder saved before remote changes</p></div>
                                        </div>
                                        <div class="synkk-shield-item">
                                            <span class="synkk-shield-item__badge">FORK-SAFE</span>
                                            <div><strong>Conflict Preservation</strong><p>Forked conflict copies prevent accidental overwrite</p></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <!-- Card 6: Self-Hosted Docker & Instant Remote Wipe (Reversed) -->
                        <article class="synkk-feature-card synkk-feature-card--reverse">
                            <div class="synkk-feature-card__copy">
                                <div class="synkk-feature-card__badge">
                                    <span class="synkk-status-dot" aria-hidden="true"></span> Available now · Enterprise Security
                                </div>
                                <h3>Your server. Zero telemetry. Instant remote wipe.</h3>
                                <p>Deploy Synkk with a single all-in-one Docker container or our VPS Caddy installer using embedded SQLite WAL + FTS5. Retain 100% data ownership on your own infrastructure. Every device authenticates using scoped, revocable SHA-256 hashed tokens—and lost devices can be remotely wiped with one click.</p>
                                <ul class="synkk-feature-card__list">
                                    <li><strong>Single Docker container:</strong> Production-ready Nginx, PHP 8.5-FPM, Laravel 13, Reverb WebSockets, and SQLite WAL out of the box.</li>
                                    <li><strong>Instant remote token wipe:</strong> Returns HTTP 410 to revoke stolen device tokens and clear local credentials &amp; sync state.</li>
                                    <li><strong>Zero telemetry:</strong> No tracking, no behavioral analytics, and external services run only when explicitly configured.</li>
                                </ul>
                                <div class="synkk-feature-card__actions">
                                    <a href="{{ route('public.docs') }}#docker" class="synkk-button synkk-button--ink">Read self-hosting docs <span aria-hidden="true">→</span></a>
                                    <a href="https://github.com/tawandajosephmutsena/synkk" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--quiet">View GitHub source <span aria-hidden="true">↗</span></a>
                                </div>
                            </div>
                            <div class="synkk-feature-card__media">
                                <div class="synkk-feature-graphic synkk-feature-graphic--docker">
                                    <div class="synkk-graphic-docker-header">
                                        <span><i></i><i></i><i></i></span>
                                        <small>docker-compose.prod.yml</small>
                                    </div>
                                    <pre class="synkk-docker-code"><code>services:
  app:
    build: .
    image: synkk/app:latest
    ports: ["127.0.0.1:8000:80"]
    env_file: [".env.production"]
    volumes:
      - synkk_storage:/var/www/html/storage</code></pre>
                                    <div class="synkk-graphic-wipe-tag">
                                        <span>🔒 Remote Device Wipe</span>
                                        <small>HTTP 410 revokes token &amp; clears local credentials</small>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <!-- Card 7: Interactive Livewire Vault Portals (Instant Web Publishing) -->
                        <article class="synkk-feature-card">
                            <div class="synkk-feature-card__copy">
                                <div class="synkk-feature-card__badge">
                                    <span class="synkk-status-dot" aria-hidden="true"></span> Available now · Zero-Config Publishing
                                </div>
                                <h3>Turn any vault into an interactive web portal.</h3>
                                <p>Publish any non-E2EE Obsidian vault (or a specific subfolder) as an interactive Livewire 4 web portal at <code>/p/{slug}</code> with zero static build pipelines. Choose from 4 layouts and 5 color themes, add optional password protection, an interactive Graph View, an Ask This Portal AI drawer, and an automatic <code>/llms.txt</code> feed.</p>
                                <ul class="synkk-feature-card__list">
                                    <li><strong>Zero build pipelines:</strong> Sync a note in Obsidian and it publishes live at <code>/p/{slug}</code> immediately.</li>
                                    <li><strong>4 Layouts &amp; 5 Color Themes:</strong> Docs, Bento, Dashboard, and Minimal layouts paired with Obsidian Noir, Slate Luxe, Midnight Emerald, Paper Craft, or Amber Gold.</li>
                                    <li><strong>Search, Graph &amp; AI-Ready:</strong> Instant <code>Cmd+K</code> search, wikilink hover popovers, backlinks, optional Portal AI Q&amp;A, and <code>/p/{slug}/llms.txt</code>.</li>
                                </ul>
                                <div class="synkk-feature-card__actions">
                                    <a href="{{ url('/p/synkk-docs') }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--accent">Explore Live Docs Portal <span aria-hidden="true">↗</span></a>
                                    <a href="{{ route('public.docs') }}#portals" class="synkk-button synkk-button--quiet">Portals guide <span aria-hidden="true">→</span></a>
                                </div>
                            </div>
                            <div class="synkk-feature-card__media">
                                <div class="synkk-window-frame">
                                    <div class="synkk-window-bar" aria-hidden="true">
                                        <span><i></i><i></i><i></i></span>
                                        <small>synkk / portal / synkk-docs</small>
                                        <span>↗</span>
                                    </div>
                                    <div class="p-6 bg-zinc-950 text-white font-sans space-y-4 rounded-b-xl border-t border-zinc-800">
                                        <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 font-mono text-[10px] font-bold">Docs Layout</span>
                                                <span class="text-xs font-bold text-zinc-300">Synkk Documentation</span>
                                            </div>
                                            <span class="text-[10px] font-mono text-zinc-500">/p/synkk-docs · /llms.txt</span>
                                        </div>
                                        <div class="p-2.5 rounded-lg bg-zinc-900 border border-zinc-800 flex items-center gap-2 text-xs text-zinc-400">
                                            <svg class="size-3.5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                            <span>Search notes, tags, backlinks (Cmd+K)...</span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 text-xs">
                                            <div class="p-2.5 rounded-lg bg-zinc-900/80 border border-zinc-800">
                                                <div class="text-[10px] text-emerald-400 font-mono">01-Quickstart</div>
                                                <div class="font-bold text-zinc-200 mt-0.5">Instant QR Pairing</div>
                                            </div>
                                            <div class="p-2.5 rounded-lg bg-zinc-900/80 border border-zinc-800">
                                                <div class="text-[10px] text-emerald-400 font-mono">05-AI-Copilot</div>
                                                <div class="font-bold text-zinc-200 mt-0.5">Vault AI &amp; Remote MCP</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <!-- Card 8: First-Sync Pre-Flight & Migration Engine (Reversed) -->
                        <article class="synkk-feature-card synkk-feature-card--reverse">
                            <div class="synkk-feature-card__copy">
                                <div class="synkk-feature-card__badge">
                                    <span class="synkk-status-dot" aria-hidden="true"></span> Available now · Trust &amp; Safety
                                </div>
                                <h3>Migrate 5,000+ notes without fear.</h3>
                                <p>Migrating an established Obsidian vault is the highest-anxiety step in adopting a sync tool. Synkk eliminates uncertainty with a 4-phase local diagnostic scan, cross-platform path sanitizer, deterministic dry-run quota simulation, and the configurable Atomic Safety Shield.</p>
                                <ul class="synkk-feature-card__list">
                                    <li><strong>Local category rollup:</strong> Instant breakdown across Markdown, Canvases, Images, Audio/Video, PDFs, and config files.</li>
                                    <li><strong>1-Click path sanitizer:</strong> Fixes illegal characters (<code>: * ? " &lt; &gt; | \</code>) and flags files over 25 MB, <code>.trash/</code>, and <code>.git/</code>.</li>
                                    <li><strong>Atomic safety shield:</strong> Configurable client-side deletion circuit breaker (defaults to 10%, adjustable 1–100%) halts accidental bulk wipes before they propagate.</li>
                                </ul>
                                <div class="synkk-feature-card__actions">
                                    <a href="{{ route('public.docs') }}#safety-shield" class="synkk-button synkk-button--ink">Migration engine docs <span aria-hidden="true">→</span></a>
                                    <a href="{{ $pluginReleaseUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--quiet">Get the Plugin <span aria-hidden="true">↗</span></a>
                                </div>
                            </div>
                            <div class="synkk-feature-card__media">
                                <div class="synkk-feature-graphic synkk-feature-graphic--shield">
                                    <div class="synkk-graphic-shield-badge">
                                        <span class="synkk-graphic-shield-icon">🛫</span>
                                        <strong>Pre-Flight Dry-Run</strong>
                                        <small>4-phase deterministic migration diagnostic</small>
                                    </div>
                                    <div class="synkk-graphic-shield-items">
                                        <div class="synkk-shield-item">
                                            <span class="synkk-shield-item__badge">ROLLUP</span>
                                            <div><strong>Category Inspector</strong><p>Markdown, Canvases, Images, Media &amp; PDFs categorized</p></div>
                                        </div>
                                        <div class="synkk-shield-item">
                                            <span class="synkk-shield-item__badge">SANITIZER</span>
                                            <div><strong>Cross-Platform Clean</strong><p>1-click fix for illegal characters on macOS, Windows &amp; mobile</p></div>
                                        </div>
                                        <div class="synkk-shield-item">
                                            <span class="synkk-shield-item__badge">10% GUARD</span>
                                            <div><strong>Atomic Safety Shield</strong><p>Configurable circuit breaker halts destructive bulk wipes</p></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <!-- Spotlight 1: 3-Way Visual Conflict Sandbox & Live Carets -->
            <section class="synkk-spotlight synkk-viewport-section synkk-shell" aria-labelledby="spotlight-conflict-heading">
                <div class="synkk-spotlight__ambient" aria-hidden="true"></div>
                <div class="synkk-spotlight__grid">
                    <div class="synkk-spotlight__copy">
                        <div class="synkk-spotlight-badge">
                            <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                            <span>Visual Reconciliation · Zero Corrupted Markdown</span>
                        </div>
                        <p class="synkk-eyebrow">Interactive Conflict Sandbox</p>
                        <h2 id="spotlight-conflict-heading">Never lose a paragraph to merge collisions.</h2>
                        <p>No more broken YAML frontmatter or cryptic <code>&lt;&lt;&lt;&lt;&lt;&lt;&lt; HEAD</code> delimiters. When offline edits collide, Synkk preserves both copies and opens an interactive Visual Conflict Sandbox with optional AI Smart Merge—plus opt-in CodeMirror 6 live carets when you want real-time multiplayer co-authoring.</p>
                        <ul class="synkk-feature-card__list">
                            <li><strong>Visual conflict sandbox:</strong> Side-by-side comparison of Canonical, Conflict Copy, and Editable Reconciled output—in both Obsidian and the web workspace.</li>
                            <li><strong>1-Click reconcile &amp; AI Smart Merge:</strong> Keep Canonical, Keep Conflict, Combine Both, or run semantic AI Smart Merge (cloud or local Ollama in E2EE).</li>
                            <li><strong>Opt-in CodeMirror 6 live carets:</strong> Toggle Yjs real-time collaboration over Laravel Reverb whenever teams co-edit live.</li>
                        </ul>
                        <div class="synkk-spotlight__actions">
                            <a href="{{ $pluginObsidianUri }}" class="synkk-button synkk-button--accent">Install Plugin in Obsidian <span aria-hidden="true">↗</span></a>
                            <a href="{{ route('public.docs') }}#safety-shield" class="synkk-button synkk-button--paper">Conflict Sandbox Guide <span aria-hidden="true">→</span></a>
                        </div>
                    </div>

                    <div class="synkk-spotlight-terminal" aria-label="3-Way visual conflict sandbox preview">
                        <div class="synkk-spotlight-terminal__header">
                            <div class="synkk-spotlight-terminal__controls" aria-hidden="true">
                                <span class="synkk-dot synkk-dot--red"></span>
                                <span class="synkk-dot synkk-dot--amber"></span>
                                <span class="synkk-dot synkk-dot--green"></span>
                            </div>
                            <span class="synkk-spotlight-terminal__title">synkk-diff-sandbox.tsx</span>
                            <span class="synkk-spotlight-terminal__badge">3-Way Reconciler</span>
                        </div>
                        <div class="synkk-spotlight-terminal__body">
                            <div class="flex items-center justify-between text-xs font-mono pb-2 border-b border-white/10 text-zinc-400">
                                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> Strategy/2026-Vision.md</span>
                                <span class="synkk-terminal-tag synkk-terminal-tag--amber">Conflict Fork #1042</span>
                            </div>
                            <div class="synkk-diff-chunk synkk-diff-chunk--mine">
                                <span class="synkk-terminal-tag synkk-terminal-tag--green mb-1">Mine (Local Laptop)</span>
                                <div>+ Synkk enables surgical folder permissions and local RAG.</div>
                            </div>
                            <div class="synkk-diff-chunk synkk-diff-chunk--theirs">
                                <span class="synkk-terminal-tag synkk-terminal-tag--amber mb-1">Server (Marcus / iPhone)</span>
                                <div>~ Synkk enables surgical folder ACLs and local Ollama inference.</div>
                            </div>
                            <div class="synkk-diff-chunk synkk-diff-chunk--reconciled">
                                <span class="synkk-terminal-tag synkk-terminal-tag--green mb-1">Reconciled Output [1-Click Merged]</span>
                                <div>✓ Synkk enables surgical folder ACLs and local RAG with Ollama inference.</div>
                            </div>
                            <div class="synkk-code-output">
                                <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                                <span>Reconciled revision committed · v79 SHA-256 verified</span>
                            </div>
                            <div class="synkk-code-line synkk-code-line--comment"># Live carets: Alice (L14:C8), Marcus (L14:C42)</div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="workflow" class="synkk-workflow synkk-viewport-section" aria-labelledby="workflow-heading">
                <div class="synkk-shell">
                    <header class="synkk-section-heading synkk-section-heading--inverse">
                        <div>
                            <p class="synkk-eyebrow">03 / A change, end to end</p>
                            <h2 id="workflow-heading">Every sync has a checkpoint.</h2>
                        </div>
                        <p>Each change passes through a visible chain of checks before it becomes part of the shared vault.</p>
                    </header>

                    <div class="synkk-workflow-track" aria-hidden="true"><span></span></div>
                    <ol class="synkk-workflow-grid">
                        <li>
                            <span class="synkk-step-number">01</span>
                            <div class="synkk-step-icon" aria-hidden="true">01</div>
                            <h3>Prepare the change</h3>
                            <p>Edit in Obsidian or the web workspace. Your vault remains portable files and folders.</p>
                            <small>Local-first authoring</small>
                        </li>
                        <li>
                            <span class="synkk-step-number">02</span>
                            <div class="synkk-step-icon" aria-hidden="true">02</div>
                            <h3>Check the boundary</h3>
                            <p>Synkk verifies the device token and path rule before accepting a note or asset.</p>
                            <small>Scoped access</small>
                        </li>
                        <li>
                            <span class="synkk-step-number">03</span>
                            <div class="synkk-step-icon" aria-hidden="true">03</div>
                            <h3>Fingerprint the file</h3>
                            <p>A SHA-256 hash and base revision make the accepted state inspectable and stale writes visible.</p>
                            <small>Integrity recorded</small>
                        </li>
                        <li>
                            <span class="synkk-step-number">04</span>
                            <div class="synkk-step-icon" aria-hidden="true">04</div>
                            <h3>Deliver to devices</h3>
                            <p>Trusted devices pull the approved change on startup, schedule, or manual request.</p>
                            <small>Predictable delivery</small>
                        </li>
                    </ol>
                </div>
            </section>

            <!-- Spotlight 2: Ghost Files & On-Demand Mobile Storage Hydration -->
            <section class="synkk-spotlight synkk-spotlight--alt synkk-viewport-section synkk-shell" aria-labelledby="spotlight-ghost-heading">
                <div class="synkk-spotlight__ambient" aria-hidden="true"></div>
                <div class="synkk-spotlight__grid synkk-spotlight__grid--reverse">
                    <div class="synkk-spotlight__copy">
                        <div class="synkk-spotlight-badge">
                            <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                            <span>Mobile Storage Solved · 50GB Vaults on 64GB Phones</span>
                        </div>
                        <p class="synkk-eyebrow">Ghost File Dehydration</p>
                        <h2 id="spotlight-ghost-heading">Sync massive archives without filling your phone.</h2>
                        <p>Large team vaults packed with PDFs, audio recordings, and visual canvas boards shouldn't overwhelm your mobile storage. Synkk replaces heavy attachments with lightweight metadata stubs and hydrates full binary content on demand.</p>
                        <ul class="synkk-feature-card__list">
                            <li><strong>Lightweight ghost stubs:</strong> Replaces heavy attachments with small metadata placeholders.</li>
                            <li><strong>Sub-second hydration:</strong> Tap any note or trigger ribbon command to pull full content via API.</li>
                            <li><strong>Zero broken links:</strong> Preserves wikilinks, file names, and frontmatter across the entire vault.</li>
                        </ul>
                        <div class="synkk-spotlight__actions">
                            <a href="{{ route('public.docs') }}#quickstart" class="synkk-button synkk-button--accent">See Hydration Protocol <span aria-hidden="true">→</span></a>
                        </div>
                    </div>

                    <div class="synkk-spotlight-terminal" aria-label="Ghost file dehydration preview">
                        <div class="synkk-spotlight-terminal__header">
                            <div class="synkk-spotlight-terminal__controls" aria-hidden="true">
                                <span class="synkk-dot synkk-dot--red"></span>
                                <span class="synkk-dot synkk-dot--amber"></span>
                                <span class="synkk-dot synkk-dot--green"></span>
                            </div>
                            <span class="synkk-spotlight-terminal__title">Archive/2025-Financial-Audit.pdf</span>
                            <span class="synkk-spotlight-terminal__badge">Ghost Hydrator</span>
                        </div>
                        <div class="synkk-spotlight-terminal__body">
                            <div class="synkk-code-line synkk-code-line--comment">&lt;!-- synkk:ghost path="Archive/2025-Financial-Audit.pdf" size="48291044" mime="application/pdf" sha256="e3b0c442..." --&gt;</div>
                            <div class="p-3 rounded-lg bg-zinc-950/60 border border-white/10 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-mono text-zinc-300">👻 <strong>Synkk Ghost Attachment (dehydrated on mobile).</strong></span>
                                    <span class="synkk-terminal-tag synkk-terminal-tag--blue">48.2 MB Remote</span>
                                </div>
                                <p class="text-xs text-zinc-400">Local stub (&lt; 1 KB). Run "Ghost Files: Hydrate active file on-demand" to download full binary.</p>
                            </div>
                            <div class="synkk-code-line"><span class="synkk-prompt">&gt;</span> <span class="synkk-cmd">GET /api/v1/vaults/team-vault/download?path=Archive/2025-Financial-Audit.pdf</span></div>
                            <div class="synkk-code-output">
                                <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                                <span>Full binary downloaded on demand · Local stub replaced</span>
                            </div>
                            <div class="synkk-code-line synkk-code-line--comment"># Default mobile threshold: 5 MB (configurable 2–50 MB in plugin settings)</div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="safety" class="synkk-safety synkk-viewport-section synkk-shell" aria-labelledby="safety-heading">
                <header class="synkk-section-heading">
                    <div>
                        <p class="synkk-eyebrow">04 / Access and recovery</p>
                        <h2 id="safety-heading">Keep access narrow. Keep every version.</h2>
                    </div>
                    <p>Synkk scopes vault paths by member, fingerprints stored content, and keeps note versions available for restore.</p>
                </header>

                <div class="synkk-safety-grid">
                    <article class="synkk-safety-card synkk-safety-card--primary">
                        <div class="synkk-safety-card__top"><span>CONTENT INTEGRITY</span><strong>SHA-256</strong></div>
                        <h3>Every accepted upload gets a fingerprint.</h3>
                        <p>Synkk computes a content checksum before storing the current file and its version record.</p>
                        <div class="synkk-hash-readout" aria-label="Example SHA-256 content fingerprint">
                            <span>CONTENT HASH</span>
                            <code>f2a8c1d7…9b6e</code>
                            <small>Recorded with the version</small>
                        </div>
                    </article>

                    <article class="synkk-safety-card">
                        <div class="synkk-safety-card__top"><span>PATH RULES</span><strong>Per member</strong></div>
                        <h3>Share a folder, not the whole vault.</h3>
                        <div class="synkk-rule-list">
                            <p><span>00-Inbox/**</span><strong>Read + write</strong></p>
                            <p><span>Client/**</span><strong>Read only</strong></p>
                            <p><span>Private/**</span><strong>Hidden</strong></p>
                        </div>
                    </article>

                    <article class="synkk-safety-card">
                        <div class="synkk-safety-card__top"><span>VERSION HISTORY</span><strong>Recoverable</strong></div>
                        <h3>Restore a note without learning Git.</h3>
                        <ol class="synkk-version-list">
                            <li><i></i><span><strong>v78 · current</strong><small>Verified and available</small></span></li>
                            <li><i></i><span><strong>v77 · 42m</strong><small>One-click restore</small></span></li>
                            <li><i></i><span><strong>v76 · 2h</strong><small>Content retained</small></span></li>
                        </ol>
                    </article>
                </div>
            </section>

            <!-- Spotlight 3: Agentic Knowledge Graph & Local RAG Copilot -->
            <section class="synkk-spotlight synkk-viewport-section synkk-shell" aria-labelledby="spotlight-rag-heading">
                <div class="synkk-spotlight__ambient" aria-hidden="true"></div>
                <div class="synkk-spotlight__grid">
                    <div class="synkk-spotlight__copy">
                        <div class="synkk-spotlight-badge">
                            <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                            <span>Opt-In Vault AI · Local Ollama, BYOK, Managed AI &amp; MCP</span>
                        </div>
                        <p class="synkk-eyebrow">Vault AI Agent, Hybrid Search &amp; Remote MCP</p>
                        <h2 id="spotlight-rag-heading">Ask your vault anything. Keep thoughts confidential.</h2>
                        <p>Turn your knowledge base into an opt-in research assistant—in Obsidian's dockable AI Sidebar, the web workspace, or external AI clients via Synkk's permissioned Remote MCP Server (<code>POST /api/v1/mcp</code>). Run 100% locally with Ollama (even on E2EE vaults), bring your own API keys, or enable the optional Managed Cloud AI Add-On.</p>
                        <ul class="synkk-feature-card__list">
                            <li><strong>Hybrid FTS5 + Vector &amp; Graph Retrieval:</strong> Combines SQLite FTS5 BM25, vector cosine similarity, and <code>[[wikilink]]</code> graph traversal with strict path ACL filtering.</li>
                            <li><strong>Human-Approved AI Proposals:</strong> The Vault AI Agent and Vault Gardener stage note edits in a visual diff Proposal Inbox—never writing silently without your approval.</li>
                            <li><strong>Permissioned Remote MCP Server:</strong> Connect Claude Desktop, Cursor, or Windsurf using scoped tokens (<code>mcp_read</code>, <code>mcp_propose</code>, <code>mcp_write</code>) across 7 vault tools without using Obsidian device seats.</li>
                        </ul>
                        <div class="synkk-spotlight__actions">
                            <a href="{{ route('public.docs') }}#ai-mcp" class="synkk-button synkk-button--accent">Explore AI &amp; MCP Architecture <span aria-hidden="true">→</span></a>
                        </div>
                    </div>

                    <div class="synkk-spotlight-terminal" aria-label="Local RAG copilot preview">
                        <div class="synkk-spotlight-terminal__header">
                            <div class="synkk-spotlight-terminal__controls" aria-hidden="true">
                                <span class="synkk-dot synkk-dot--red"></span>
                                <span class="synkk-dot synkk-dot--amber"></span>
                                <span class="synkk-dot synkk-dot--green"></span>
                            </div>
                            <span class="synkk-spotlight-terminal__title">synkk-vault-copilot.py</span>
                            <span class="synkk-spotlight-terminal__badge">Ollama / Llama-3.2</span>
                        </div>
                        <div class="synkk-spotlight-terminal__body">
                            <div class="synkk-code-line"><span class="synkk-prompt">&gt; Query:</span> <span class="synkk-cmd">"What is our team deletion policy and how does Safety Shield prevent data loss?"</span></div>
                            <div class="p-2.5 rounded-md bg-zinc-950/80 border border-white/10 space-y-1.5 text-xs">
                                <div class="text-zinc-400 font-mono text-[0.65rem] flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Hybrid search + 1-hop [[wikilink]] graph expansion:
                                </div>
                                <div class="flex flex-wrap gap-1.5 font-mono text-[0.62rem]">
                                    <span class="synkk-terminal-tag synkk-terminal-tag--green">[[Security/Safety-Shield.md]] (score 0.98)</span>
                                    <span class="synkk-terminal-tag synkk-terminal-tag--blue">[[Architecture/Path-ACLs.md]] (score 0.94)</span>
                                </div>
                            </div>
                            <div class="p-3 rounded-lg bg-emerald-950/30 border border-emerald-500/20 text-xs text-emerald-200">
                                <strong>Vault Copilot:</strong> Synkk enforces a configurable Atomic Safety Shield (default 10% deletion threshold in Obsidian settings). If a sync exceeds the threshold, synchronization halts until you explicitly allow a one-time guarded override. Local pre-mutation snapshots are written to <code>.synkk/snapshots/</code>.
                                <div class="mt-2 pt-2 border-t border-emerald-500/20 text-[0.65rem] text-emerald-400/80 font-mono">
                                    Citations: [[Security/Safety-Shield]] · [[Architecture/Path-ACLs]] · Proposed edits staged in AI Inbox
                                </div>
                            </div>
                            <div class="synkk-code-line synkk-code-line--comment"># Local Ollama works on E2EE vaults · Standard vaults also support BYOK, Managed AI &amp; MCP</div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="comparison" class="synkk-comparison synkk-viewport-section" aria-labelledby="comparison-heading">
                <div class="synkk-shell">
                    <header class="synkk-section-heading">
                        <div>
                            <p class="synkk-eyebrow">05 / The Sovereign Standard</p>
                            <h2 id="comparison-heading">What makes Synkk better than existing Obsidian sync tools?</h2>
                        </div>
                        <p>Most sync alternatives force a painful compromise: fragile DIY git setups, complex CouchDB database maintenance, or closed proprietary cloud silos with recurring monthly seat taxes. Synkk gives your team a local-first brain with path-level security, secret leak prevention, and 100% data sovereignty on hardware you own.</p>
                    </header>

                    <div class="synkk-comparison-table-wrapper" tabindex="0" role="region" aria-labelledby="comparison-heading">
                        <table class="synkk-comparison-table" aria-label="Obsidian Sync tools feature comparison matrix">
                            <thead>
                                <tr>
                                    <th scope="col" class="synkk-col-dim">
                                        <span class="synkk-th-label">Architecture &amp; Security</span>
                                        <small class="synkk-th-sub">Core capabilities</small>
                                    </th>
                                    <th scope="col" class="synkk-col-featured">
                                        <div class="synkk-col-featured-header">
                                            <div class="synkk-featured-badge-row">
                                                <span class="synkk-comparison-badge-synkk">Team Standard</span>
                                                <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                                            </div>
                                            <span class="synkk-th-title">Synkk</span>
                                            <small class="synkk-th-sub">Local-First Server</small>
                                        </div>
                                    </th>
                                    <th scope="col">
                                        <span class="synkk-th-title">Official Obsidian Sync</span>
                                        <small class="synkk-th-sub">Proprietary Cloud</small>
                                    </th>
                                    <th scope="col">
                                        <span class="synkk-th-title">Obsidian Git</span>
                                        <small class="synkk-th-sub">Community Plugin</small>
                                    </th>
                                    <th scope="col">
                                        <span class="synkk-th-title">Remotely Save (S3/WebDAV)</span>
                                        <small class="synkk-th-sub">Object Storage</small>
                                    </th>
                                    <th scope="col">
                                        <span class="synkk-th-title">Self-Hosted LiveSync</span>
                                        <small class="synkk-th-sub">CouchDB Replication</small>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th scope="row" class="synkk-col-dim">
                                        <strong>100% Self-Hosted</strong>
                                        <p class="synkk-cell-dim-desc">Complete data sovereignty. Run on private VPS, Docker, or bare metal.</p>
                                    </th>
                                    <td class="synkk-col-featured">
                                        <div class="synkk-cell-check">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> 100% Local-First</strong>
                                            <span class="synkk-cell-sub">Private SQLite WAL engine. External processing occurs only for services your operator configures.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">Hosted on proprietary Obsidian cloud infrastructure.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-warn">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg> Partial</strong>
                                            <span class="synkk-cell-sub">Requires GitHub, GitLab, or self-maintained Git server.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-warn">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg> Partial</strong>
                                            <span class="synkk-cell-sub">Relies on commercial S3 (AWS/Cloudflare) or custom WebDAV.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-check">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Yes</strong>
                                            <span class="synkk-cell-sub">Self-hosted Apache CouchDB or IBM Cloudant database.</span>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" class="synkk-col-dim">
                                        <strong>Path-Level ACLs</strong>
                                        <p class="synkk-cell-dim-desc">Granular team directory rules. Share specific folders while keeping private notes hidden.</p>
                                    </th>
                                    <td class="synkk-col-featured">
                                        <div class="synkk-cell-check">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Native Path ACLs</strong>
                                            <span class="synkk-cell-sub">Per-member glob rules: Read-write, Read-only, or Hidden boundaries.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> None</strong>
                                            <span class="synkk-cell-sub">All-or-nothing vault sync. Every collaborator has full access to all files.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> None</strong>
                                            <span class="synkk-cell-sub">Git repository permissions are repo-wide. No folder-level masking.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> None</strong>
                                            <span class="synkk-cell-sub">Client receives full bucket contents without user permission filters.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> None</strong>
                                            <span class="synkk-cell-sub">CouchDB replicates entire database documents with no folder boundaries.</span>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" class="synkk-col-dim">
                                        <strong>Data Loss Prevention (DLP)</strong>
                                        <p class="synkk-cell-dim-desc">Automatic secret &amp; credential scanning on plaintext syncs and outbound AI prompts.</p>
                                    </th>
                                    <td class="synkk-col-featured">
                                        <div class="synkk-cell-check">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Server DLP Shield</strong>
                                            <span class="synkk-cell-sub">Flags AWS, OpenAI, Anthropic, Stripe, GitHub, Slack &amp; PEM keys on plaintext uploads and redacts before external AI calls (skipped on E2EE).</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">Transmits any text without inspection. Leaked secrets sync to cloud.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">No pre-commit hook in plugin. Secrets are committed to Git history.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">Blind file upload to remote S3 bucket.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">Direct replication to CouchDB without secret analysis.</span>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" class="synkk-col-dim">
                                        <strong>Atomic Safety Shield</strong>
                                        <p class="synkk-cell-dim-desc">Protection against accidental bulk deletion from rogue scripts or misconfigured clients.</p>
                                    </th>
                                    <td class="synkk-col-featured">
                                        <div class="synkk-cell-check">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> 10% Abort Guard</strong>
                                            <span class="synkk-cell-sub">Automatically halts sync if &gt;10% of notes are queued for deletion. Vault preserved.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">Deletions immediately replicate across all devices.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-warn">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg> Manual</strong>
                                            <span class="synkk-cell-sub">Git can revert commits, but requires terminal commands and recovery know-how.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">Deletions immediately remove files from S3 bucket.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">CouchDB doc deletions replicate immediately to all clients.</span>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" class="synkk-col-dim">
                                        <strong>Conflict Handling</strong>
                                        <p class="synkk-cell-dim-desc">Safety when multiple teammates edit the same document simultaneously.</p>
                                    </th>
                                    <td class="synkk-col-featured">
                                        <div class="synkk-cell-check">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Safe Forking</strong>
                                            <span class="synkk-cell-sub">Creates non-destructive *.sync-conflict-*.md copies. Zero overwritten work.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-warn">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg> Last-Write-Wins</strong>
                                            <span class="synkk-cell-sub">Silent file overwrite; requires manual version history recovery.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> Broken Markers</strong>
                                            <span class="synkk-cell-sub">Raw Git merge conflicts inject &lt;&lt;&lt;&lt;&lt;&lt;&lt; HEAD into markdown.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> Overwrite Risk</strong>
                                            <span class="synkk-cell-sub">Timestamp race conditions frequently clobber parallel edits.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-warn">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg> Complex Merge</strong>
                                            <span class="synkk-cell-sub">CouchDB revision trees require technical manual resolution modal.</span>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" class="synkk-col-dim">
                                        <strong>Web Workspace &amp; Graph</strong>
                                        <p class="synkk-cell-dim-desc">Edit notes and explore connections in a browser without installing Obsidian.</p>
                                    </th>
                                    <td class="synkk-col-featured">
                                        <div class="synkk-cell-check">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Full Web Workspace</strong>
                                            <span class="synkk-cell-sub">Rich Markdown editor and interactive 2D force graph built-in.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">Requires Obsidian desktop or mobile application for all users.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-warn">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg> GitHub UI Only</strong>
                                            <span class="synkk-cell-sub">Basic code editing in browser. No Obsidian graph visualization.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">Raw cloud storage bucket. No web reading or editing interface.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">Fauxton admin dashboard only displays raw CouchDB JSON.</span>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" class="synkk-col-dim">
                                        <strong>Mobile Onboarding</strong>
                                        <p class="synkk-cell-dim-desc">Speed of provisioning new iOS and Android devices.</p>
                                    </th>
                                    <td class="synkk-col-featured">
                                        <div class="synkk-cell-check">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> 1-Click QR Scan</strong>
                                            <span class="synkk-cell-sub">Scan camera QR from dashboard. Device paired and syncing in seconds.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-warn">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg> Email Login</strong>
                                            <span class="synkk-cell-sub">Log in with email/password and select remote vault to clone.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> Painful</strong>
                                            <span class="synkk-cell-sub">Requires SSH key generation, Personal Access Tokens, or Termux workarounds.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> Tedious</strong>
                                            <span class="synkk-cell-sub">Manual input of S3 endpoint, bucket, access key ID, and secret on mobile.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> Extreme Friction</strong>
                                            <span class="synkk-cell-sub">Manual CouchDB credentials, base64 encryption keys, and sync URI setup.</span>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" class="synkk-col-dim">
                                        <strong>Live Multiplayer Carets</strong>
                                        <p class="synkk-cell-dim-desc">Live cursor presence widgets and simultaneous collaborative typing in Obsidian.</p>
                                    </th>
                                    <td class="synkk-col-featured">
                                        <div class="synkk-cell-check">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Opt-In CM6 Yjs CRDT</strong>
                                            <span class="synkk-cell-sub">Live presence carets over Reverb (off by default in Obsidian plugin for atomic stability; toggleable anytime).</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">No multiplayer cursors or live co-authoring support.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">Asynchronous git commits only; zero live collaboration.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">Blind file pull/push without live presence layer.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-warn">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg> Partial</strong>
                                            <span class="synkk-cell-sub">Document-level replication; no in-editor live presence avatars.</span>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" class="synkk-col-dim">
                                        <strong>Mobile Ghost Files</strong>
                                        <p class="synkk-cell-dim-desc">On-demand hydration of large media and attachments to prevent mobile storage exhaustion.</p>
                                    </th>
                                    <td class="synkk-col-featured">
                                        <div class="synkk-cell-check">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Ghost Hydration</strong>
                                            <span class="synkk-cell-sub">Lightweight metadata stubs; full content hydrates on demand.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">Forces full vault download onto mobile device memory.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">Requires complete git clone; large attachments choke mobile git.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">All-or-nothing bucket synchronization.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">Every document and attachment replicates into CouchDB.</span>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" class="synkk-col-dim">
                                        <strong>Private Local RAG &amp; Copilot</strong>
                                        <p class="synkk-cell-dim-desc">Hybrid FTS5 + vector search, graph backlink traversal, AI Proposal Inbox, and Remote MCP Server.</p>
                                    </th>
                                    <td class="synkk-col-featured">
                                        <div class="synkk-cell-check">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Local Ollama, BYOK &amp; MCP</strong>
                                            <span class="synkk-cell-sub">Local Ollama works even on E2EE vaults; standard vaults add Hybrid Search, AI Inbox, Gardener &amp; Remote MCP.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">No vector indexing or semantic search capabilities.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">Standard git version control only.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">Raw file storage only.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No</strong>
                                            <span class="synkk-cell-sub">No graph reasoning or vector search.</span>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" class="synkk-col-dim">
                                        <strong>Zero-Knowledge Team E2EE</strong>
                                        <p class="synkk-cell-dim-desc">Client-side encryption ensuring the central server only ever stores opaque ciphertext.</p>
                                    </th>
                                    <td class="synkk-col-featured">
                                        <div class="synkk-cell-check">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> AES-256-GCM + PBKDF2</strong>
                                            <span class="synkk-cell-sub">100,000 PBKDF2 iterations; server administrators cannot inspect notes.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-warn">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg> Proprietary E2EE</strong>
                                            <span class="synkk-cell-sub">Closed proprietary cloud infrastructure.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> No E2EE</strong>
                                            <span class="synkk-cell-sub">Raw Markdown and secrets pushed in plaintext to Git servers.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-warn">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg> Optional Plugin</strong>
                                            <span class="synkk-cell-sub">Optional client-side password encryption before upload.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-warn">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg> Optional</strong>
                                            <span class="synkk-cell-sub">Encrypted string storage in CouchDB with complex passphrase setup.</span>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" class="synkk-col-dim">
                                        <strong>Cost for a Team of 15</strong>
                                        <p class="synkk-cell-dim-desc">3-year total software cost for a 15-person engineering or research group.</p>
                                    </th>
                                    <td class="synkk-col-featured">
                                        <div class="synkk-cell-check">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> $0 Self-Host · {{ $cloudBusinessPrice }}/mo Cloud (25 seats) · {{ $proPrice }} LTD</strong>
                                            <span class="synkk-cell-sub">Self-host free (MIT) or {{ $proPrice }} LTD, or run Cloud Business ({{ $cloudBusinessPrice }}/mo workspace = $900/3yr for up to 25 members). Zero per-seat tax.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-cross">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg> $4,320+</strong>
                                            <span class="synkk-cell-sub">$8/seat/mo ($96/yr/seat) × 15 seats × 3 years recurring bill.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-warn">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg> Free (High Dev Time)</strong>
                                            <span class="synkk-cell-sub">Hidden cost in engineer time debugging broken merge commits.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-warn">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg> S3 API Costs</strong>
                                            <span class="synkk-cell-sub">Recurring monthly S3 PUT/GET request fees and bandwidth charges.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="synkk-cell-warn">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg> VPS Overhead</strong>
                                            <span class="synkk-cell-sub">Requires persistent CouchDB clustering and database maintenance.</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="moonshot" class="synkk-moonshot synkk-viewport-section" aria-labelledby="moonshot-heading">
                <div class="synkk-shell">
                    <header class="synkk-section-heading">
                        <div>
                            <p class="synkk-eyebrow">06 / The Moonshot Architecture</p>
                            <h2 id="moonshot-heading">The Synkk Moonshot Engine</h2>
                        </div>
                        <p>Beyond simple file synchronization. Four breakthrough systems engineered to eliminate sync friction, prevent data loss, scale team collaboration, and guarantee uncompromising data sovereignty.</p>
                    </header>

                    <div class="synkk-moonshot-engine">
                        <!-- Pillar 1: Real-Time Multiplayer CRDT -->
                        <article class="synkk-moonshot-card">
                            <div class="synkk-moonshot-card__header">
                                <div class="synkk-moonshot-card__badge">
                                    <span class="synkk-moonshot-step">01</span>
                                    <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                                    <span>REAL-TIME MULTIPLAYER CRDT (Yjs)</span>
                                </div>
                                <span class="synkk-moonshot-phase synkk-moonshot-phase--live">LIVE IN V1.1.1 (OPT-IN)</span>
                            </div>
                            <div class="synkk-moonshot-card__body">
                                <div class="synkk-moonshot-card__content">
                                    <h3>Two users typing in the exact same .md note simultaneously without Git merge hell.</h3>
                                    <p>Traditional sync engines force last-write-wins overwrites or leave ugly Git conflict markers that corrupt your Markdown. Synkk integrates durable Yjs CRDT journals over Laravel Reverb (opt-in inside Obsidian plugin settings for maximum whole-file sync stability) with AES-256-GCM + AAD encryption on E2EE vaults.</p>
                                    <ul class="synkk-moonshot-features">
                                        <li><strong>Zero Merge Hell:</strong> Eliminates <code>&lt;&lt;&lt;&lt;&lt;&lt;&lt; HEAD</code> conflict markers forever.</li>
                                        <li><strong>Real-Time Remote Cursors:</strong> Live CodeMirror 6 caret awareness over Reverb WebSockets.</li>
                                        <li><strong>Durable Journal &amp; Checkpoints:</strong> Catch-up sync and automatic Markdown flush upon session end.</li>
                                    </ul>
                                </div>
                                <div class="synkk-moonshot-visual">
                                    <div class="synkk-crdt-preview">
                                        <div class="synkk-crdt-bar">
                                            <span><i></i><i></i><i></i></span>
                                            <small>collaborative-session.md · 2 peers active</small>
                                            <span class="synkk-crdt-sync-badge">Yjs Active</span>
                                        </div>
                                        <div class="synkk-crdt-editor">
                                            <p class="synkk-crdt-line"><span class="synkk-crdt-line-num">1</span># Q4 Strategic Product Priorities</p>
                                            <p class="synkk-crdt-line"><span class="synkk-crdt-line-num">2</span>We are shipping the sovereign team brain engine.</p>
                                            <p class="synkk-crdt-line"><span class="synkk-crdt-line-num">3</span>- <span class="synkk-user-text synkk-user--alice">Alice: Real-time conflict-free CRDT sync</span><span class="synkk-caret synkk-caret--alice" data-user="Alice"></span></p>
                                            <p class="synkk-crdt-line"><span class="synkk-crdt-line-num">4</span>- <span class="synkk-user-text synkk-user--bob">Bob: Instant 2-second QR mobile pairing</span><span class="synkk-caret synkk-caret--bob" data-user="Bob"></span></p>
                                            <p class="synkk-crdt-line"><span class="synkk-crdt-line-num">5</span>State vector delta: <code class="synkk-crdt-hash">0x4a9f...b27e [synced]</code></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <div class="synkk-moonshot-arrow" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                        </div>

                        <!-- Pillar 2: Instant Zero-Config Mobile Onboarding -->
                        <article class="synkk-moonshot-card">
                            <div class="synkk-moonshot-card__header">
                                <div class="synkk-moonshot-card__badge">
                                    <span class="synkk-moonshot-step">02</span>
                                    <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                                    <span>INSTANT ZERO-CONFIG MOBILE ONBOARDING</span>
                                </div>
                                <span class="synkk-moonshot-phase synkk-moonshot-phase--live">LIVE IN V1.1.1</span>
                            </div>
                            <div class="synkk-moonshot-card__body">
                                <div class="synkk-moonshot-card__content">
                                    <h3>Scan a single QR code on the Synkk web dashboard to link iOS/Android in 2 seconds.</h3>
                                    <p>No more tedious typing of server URLs, port numbers, or long device tokens on touchscreen keyboards. Synkk generates a single-use, 10-minute pairing QR code and <code>obsidian://synkk-pair</code> deep link that auto-provisions desktop and mobile devices in one scan.</p>
                                    <ul class="synkk-moonshot-features">
                                        <li><strong>One-Scan Pairing:</strong> Auto-configures Server API URL, scoped Device Token, Vault slug, and Reverb settings.</li>
                                        <li><strong>Cross-Platform Support:</strong> Native QR scanner, image upload fallback, clipboard paste, and web bridge (<code>/pair</code>).</li>
                                        <li><strong>Cryptographic Safety:</strong> HTTPS origin enforcement and scoped, remotely wipeable device tokens.</li>
                                    </ul>
                                </div>
                                <div class="synkk-moonshot-visual">
                                    <div class="synkk-qr-preview">
                                        <div class="synkk-qr-card">
                                            <div class="synkk-qr-frame">
                                                <svg class="synkk-qr-code-svg" viewBox="0 0 100 100" fill="currentColor">
                                                    <rect x="10" y="10" width="25" height="25" rx="3" fill="#1c2518"/>
                                                    <rect x="15" y="15" width="15" height="15" fill="#ffffff"/>
                                                    <rect x="18" y="18" width="9" height="9" fill="#1c2518"/>
                                                    <rect x="65" y="10" width="25" height="25" rx="3" fill="#1c2518"/>
                                                    <rect x="70" y="15" width="15" height="15" fill="#ffffff"/>
                                                    <rect x="73" y="18" width="9" height="9" fill="#1c2518"/>
                                                    <rect x="10" y="65" width="25" height="25" rx="3" fill="#1c2518"/>
                                                    <rect x="15" y="70" width="15" height="15" fill="#ffffff"/>
                                                    <rect x="18" y="73" width="9" height="9" fill="#1c2518"/>
                                                    <rect x="42" y="12" width="6" height="6" fill="#1c2518"/>
                                                    <rect x="52" y="18" width="6" height="6" fill="#1c2518"/>
                                                    <rect x="42" y="28" width="6" height="6" fill="#1c2518"/>
                                                    <rect x="42" y="42" width="16" height="16" rx="2" fill="#78934b"/>
                                                    <rect x="65" y="45" width="8" height="8" fill="#1c2518"/>
                                                    <rect x="78" y="52" width="12" height="6" fill="#1c2518"/>
                                                    <rect x="42" y="68" width="8" height="8" fill="#1c2518"/>
                                                    <rect x="55" y="75" width="15" height="10" fill="#1c2518"/>
                                                    <rect x="75" y="75" width="15" height="15" fill="#1c2518"/>
                                                </svg>
                                                <div class="synkk-qr-scan-line"></div>
                                            </div>
                                            <div class="synkk-qr-telemetry">
                                                <div class="synkk-qr-telemetry__pill">
                                                    <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                                    <span>Paired in 1.8s</span>
                                                </div>
                                                <small>iOS &amp; Android · Synkk Instant Pairing</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <div class="synkk-moonshot-arrow" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                        </div>

                        <!-- Pillar 3: Agentic Knowledge Graph & RAG Server -->
                        <article class="synkk-moonshot-card">
                            <div class="synkk-moonshot-card__header">
                                <div class="synkk-moonshot-card__badge">
                                    <span class="synkk-moonshot-step">03</span>
                                    <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                                    <span>AGENTIC KNOWLEDGE GRAPH &amp; RAG SERVER</span>
                                </div>
                                <span class="synkk-moonshot-phase synkk-moonshot-phase--live">LIVE IN V1.1.1</span>
                            </div>
                            <div class="synkk-moonshot-card__body">
                                <div class="synkk-moonshot-card__content">
                                    <h3>Self-hosted vector embeddings &amp; local LLM chat answering questions from your vault.</h3>
                                    <p>Transform your notes into an opt-in reasoning engine—inside Obsidian's AI Sidebar, the web workspace, or external agents via Synkk's permissioned Remote MCP Server (<code>POST /api/v1/mcp</code>). Run locally with Ollama (even on E2EE vaults), connect BYOK keys (OpenAI, Anthropic, Gemini, OpenRouter), or enable the Managed Cloud AI Add-On.</p>
                                    <ul class="synkk-moonshot-features">
                                        <li><strong>Hybrid Search &amp; Graph RAG:</strong> Combines SQLite FTS5 BM25, float32 vector cosine similarity, and <code>[[wikilink]]</code> graph traversal with strict path ACL filtering.</li>
                                        <li><strong>Human-Approved AI Proposals:</strong> Vault AI Agent, Vault Gardener, and Team Digests stage changes in a visual diff Proposal Inbox for 1-click approval.</li>
                                        <li><strong>Remote MCP Server:</strong> Exposes 7 permission-checked vault tools to Claude Desktop, Cursor, and Windsurf using scoped MCP tokens.</li>
                                    </ul>
                                </div>
                                <div class="synkk-moonshot-visual">
                                    <div class="synkk-rag-preview">
                                        <div class="synkk-rag-header">
                                            <span><i></i> Vault AI &amp; MCP Node</span>
                                            <span class="synkk-rag-status">Ollama · BYOK · MCP Live</span>
                                        </div>
                                        <div class="synkk-rag-query">
                                            <span class="synkk-rag-prompt">&gt; Query:</span>
                                            <p>"Summarize our team's sync protocol and security boundaries."</p>
                                        </div>
                                        <div class="synkk-rag-context">
                                            <small>Retrieved 3 nodes via Hybrid FTS5 + [[wikilink]] graph:</small>
                                            <div class="synkk-rag-chips">
                                                <span>[[Architecture/DLP-Shield.md]] (98%)</span>
                                                <span>[[Security/Path-ACLs.md]] (95%)</span>
                                                <span>[[API/Sha256-Hash.md]] (91%)</span>
                                            </div>
                                        </div>
                                        <div class="synkk-rag-answer">
                                            <p>Synkk enforces path-level ACLs per member, verifies uploads with SHA-256 checksums, flags secrets on plaintext uploads, and stages AI edits in the Proposal Inbox.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <div class="synkk-moonshot-arrow" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                        </div>

                        <!-- Pillar 4: Zero-Knowledge Team E2EE -->
                        <article class="synkk-moonshot-card">
                            <div class="synkk-moonshot-card__header">
                                <div class="synkk-moonshot-card__badge">
                                    <span class="synkk-moonshot-step">04</span>
                                    <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                                    <span>ZERO-KNOWLEDGE TEAM E2EE (CLIENT-SIDE ENCRYPTION)</span>
                                </div>
                                <span class="synkk-moonshot-phase synkk-moonshot-phase--live">LIVE IN V1.1.1</span>
                            </div>
                            <div class="synkk-moonshot-card__body">
                                <div class="synkk-moonshot-card__content">
                                    <h3>Server stores encrypted blobs; supported clients decrypt with WebCrypto.</h3>
                                    <p>Files, attachments, and Yjs collaboration updates are encrypted on your device using WebCrypto AES-256-GCM (PBKDF2-SHA-256, 100,000 iterations) before transmission. Passphrases stay in volatile RAM only. File paths and sizes remain plaintext metadata for sync routing, while server-side search, Portals, DLP scanning, MCP, and cloud RAG are disabled on E2EE vaults (use Local Ollama AI instead).</p>
                                    <ul class="synkk-moonshot-features">
                                        <li><strong>Zero Server Content Knowledge:</strong> Plaintext note bodies and passphrases never touch server disks or plugin <code>data.json</code>.</li>
                                        <li><strong>WebCrypto AES-256-GCM:</strong> Standard browser and Obsidian WebCrypto with AAD binding on Yjs collab payloads.</li>
                                        <li><strong>Local E2EE AI Support:</strong> Query encrypted vaults privately using local Ollama or an OpenAI-compatible local server inside Obsidian.</li>
                                    </ul>
                                </div>
                                <div class="synkk-moonshot-visual">
                                    <div class="synkk-e2ee-preview">
                                        <div class="synkk-e2ee-flow">
                                            <div class="synkk-e2ee-stage">
                                                <div class="synkk-e2ee-badge">DEVICE (LOCAL)</div>
                                                <div class="synkk-e2ee-box">Plaintext Note</div>
                                                <small>Markdown / Images</small>
                                            </div>
                                            <div class="synkk-e2ee-arrow">
                                                <span>AES-GCM</span>
                                                <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                            </div>
                                            <div class="synkk-e2ee-stage synkk-e2ee-stage--server">
                                                <div class="synkk-e2ee-badge">SYNKK SERVER</div>
                                                <div class="synkk-e2ee-box synkk-e2ee-box--locked">
                                                    <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                                                    <span>Opaque Blob</span>
                                                </div>
                                                <small>Zero Knowledge</small>
                                            </div>
                                            <div class="synkk-e2ee-arrow">
                                                <span>WebCrypto</span>
                                                <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                            </div>
                                            <div class="synkk-e2ee-stage">
                                                <div class="synkk-e2ee-badge">WEB VIEWER</div>
                                                <div class="synkk-e2ee-box">Decrypted Note</div>
                                                <small>In-Browser RAM</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <!-- Spotlight 4: Zero-Knowledge E2EE & High-Entropy Fleet DLP Shield -->
            <section class="synkk-spotlight synkk-spotlight--alt synkk-viewport-section synkk-shell" aria-labelledby="spotlight-e2ee-heading">
                <div class="synkk-spotlight__ambient" aria-hidden="true"></div>
                <div class="synkk-spotlight__grid synkk-spotlight__grid--reverse">
                    <div class="synkk-spotlight__copy">
                        <div class="synkk-spotlight-badge">
                            <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                            <span>Client-Side Encryption · AES-256-GCM</span>
                        </div>
                        <p class="synkk-eyebrow">Enterprise Vault Protection</p>
                        <h2 id="spotlight-e2ee-heading">Enterprise privacy on infrastructure you own.</h2>
                        <p>When E2EE is enabled, notes and attachments are encrypted on the supported client using AES-256-GCM with PBKDF2 key derivation before transmission. The Synkk server stores opaque ciphertext. DLP scanning applies to plaintext uploads and cannot inspect encrypted vault content.</p>
                        <ul class="synkk-feature-card__list">
                            <li><strong>Client-Side AES-256-GCM:</strong> 100,000 PBKDF2 iterations keep passphrases in RAM only—never written to disk.</li>
                            <li><strong>Plaintext DLP Secret Scanner:</strong> Flags AWS, GitHub, Slack, Stripe, OpenAI, Anthropic, and PEM keys on plaintext uploads and redacts them before external AI calls.</li>
                            <li><strong>1-Click Fleet Remote Wipe:</strong> Revoke compromised device tokens and issue HTTP 410 so the plugin clears local credentials and sync state.</li>
                        </ul>
                        <div class="synkk-spotlight__actions">
                            <a href="{{ route('public.docs') }}#remote-wipe" class="synkk-button synkk-button--accent">Read Security Spec <span aria-hidden="true">→</span></a>
                        </div>
                    </div>

                    <div class="synkk-spotlight-terminal" aria-label="E2EE and fleet DLP scanner preview">
                        <div class="synkk-spotlight-terminal__header">
                            <div class="synkk-spotlight-terminal__controls" aria-hidden="true">
                                <span class="synkk-dot synkk-dot--red"></span>
                                <span class="synkk-dot synkk-dot--amber"></span>
                                <span class="synkk-dot synkk-dot--green"></span>
                            </div>
                            <span class="synkk-spotlight-terminal__title">synkk-fleet-security.sh</span>
                            <span class="synkk-spotlight-terminal__badge">E2EE &amp; DLP Interceptor</span>
                        </div>
                        <div class="synkk-spotlight-terminal__body">
                            <div class="synkk-code-line"><span class="synkk-prompt">&gt;</span> <span class="synkk-cmd">GET /api/v1/vaults/engineering/e2ee/status</span></div>
                            <div class="synkk-code-output">
                                <span class="synkk-status-dot" aria-hidden="true"></span>
                                <span>AES-256-GCM Active · PBKDF2 100k iterations · Ciphertext only on server</span>
                            </div>
                            <div class="p-2.5 rounded-lg bg-red-950/40 border border-red-500/30 text-xs text-red-200 space-y-1">
                                <div class="flex items-center justify-between font-mono text-[0.65rem]">
                                    <span class="text-red-400 font-bold">⚠️ PLAINTEXT DLP SECRET FLAGGED</span>
                                    <span class="synkk-terminal-tag synkk-terminal-tag--red">AUDIT ALERT</span>
                                </div>
                                <div class="font-mono text-[0.65rem] text-zinc-300">Standard Vault: Config/Environment.md</div>
                                <div class="font-mono text-[0.62rem] text-red-300">Detected: OPENAI_API_KEY (flagged in dashboard &amp; redacted from AI)</div>
                            </div>
                            <div class="synkk-code-line synkk-code-line--comment"># Plaintext uploads are scanned server-side; E2EE vaults skip server DLP</div>
                            <div class="synkk-code-line"><span class="synkk-prompt">&gt;</span> <span class="synkk-cmd">Dashboard → Devices → Remote Wipe "Lost-MacBook-Pro"</span></div>
                            <div class="synkk-code-output">
                                <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                                <span>HTTP 410 issued · Device token revoked · Local sync state &amp; token purged</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="pricing" class="synkk-pricing synkk-viewport-section" aria-labelledby="pricing-heading">
                <div class="synkk-shell">
                    <header class="synkk-section-heading">
                        <div>
                            <p class="synkk-eyebrow">07 / Packaging &amp; Pricing Architecture</p>
                            <h2 id="pricing-heading">Choose how your team runs Synkk.</h2>
                        </div>
                        <p>Self-host Synkk for free, choose a paid zero-config Cloud workspace with a 30-day card-verified trial, or unlock the commercial self-hosted engine.</p>
                    </header>

                    <div class="synkk-pricing-grid">
                        <article>
                            <div class="synkk-price-heading">
                                <span>FREE &amp; OPEN SOURCE · SELF-HOSTED</span>
                                <p><strong>$0</strong><small>free forever · full source code</small></p>
                            </div>
                            <h3>Synkk Community</h3>
                            <p class="synkk-price-subtitle">MIT open-source server &amp; Obsidian plugin for developers, homelabs &amp; independent teams.</p>
                            <ul>
                                <li>Full MIT source code (Laravel 13 server + Obsidian plugin)</li>
                                <li>Whole-file sync, SHA-256 verification, E2EE &amp; opt-in CRDT</li>
                                <li>Version history (25 versions/file), web editor, Graph &amp; Portals</li>
                                <li>BYOK / Local Ollama AI core &amp; Remote MCP Server included</li>
                                <li>Default Community Free tier: 1 vault, 3 members, 3 devices, 1 GB</li>
                                <li>Self-hosted admin deployments control their own server capacity</li>
                                <li>Public community support via GitHub Discussions</li>
                            </ul>
                            <div class="synkk-pricing-actions">
                                <a href="https://github.com/tawandajosephmutsena/synkk" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--accent">Clone on GitHub <span aria-hidden="true">↗</span></a>
                                <a href="{{ route('public.docs') }}" class="synkk-button synkk-button--ink">Self-Hosting Docs <span aria-hidden="true">→</span></a>
                            </div>
                        </article>

                        <article class="is-featured">
                            <div class="synkk-price-featured-badge">
                                <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                                <span>ZERO-CONFIG SAAS · 30-DAY TRIAL</span>
                            </div>
                            <div class="synkk-price-heading">
                                <span>SYNKK CLOUD PRO</span>
                                <p><strong>{{ $cloudProPrice }}</strong><small>per workspace / month</small></p>
                            </div>
                            <div class="synkk-price-commercial-toggle">
                                30-Day Free Trial (Card Required) · <strong>5 GB</strong> storage · 5 vaults · 5 members · 25 devices
                            </div>
                            <h3>Synkk Cloud Pro</h3>
                            <p class="synkk-price-subtitle">For individuals and small teams that want managed sync without running servers.</p>
                            <ul>
                                <li><strong>30-day free trial:</strong> Card required upfront to prevent abuse. Cancel anytime before day 30 with zero charge.</li>
                                <li>5 GB managed cloud storage · 5 vaults · 5 members · 25 devices</li>
                                <li>Full sync, web editor, Graph View, Livewire Portals &amp; version history</li>
                                <li>Path ACLs, IP allowlisting, read-only tokens, DLP &amp; remote wipe</li>
                                <li>AI Smart Merge, Vault Gardener, Team Digests, Portal AI &amp; MCP</li>
                                <li>Includes $2 one-time Managed Cloud AI trial credit (optional {{ $aiAddonPrice }}/mo AI Add-On)</li>
                            </ul>
                            @auth
                                <a href="{{ $dashboardUrl }}" class="synkk-button synkk-button--accent">Go to Dashboard <span aria-hidden="true">→</span></a>
                            @else
                                <a href="{{ route('register') }}" class="synkk-button synkk-button--accent">Choose Cloud Pro <span aria-hidden="true">→</span></a>
                            @endauth
                        </article>

                        <article class="synkk-pricing-grid__team">
                            <div class="synkk-price-heading">
                                <span>ZERO-CONFIG SAAS FOR TEAMS</span>
                                <p><strong>{{ $cloudBusinessPrice }}</strong><small>per workspace / month</small></p>
                            </div>
                            <h3>Synkk Cloud Business</h3>
                            <p class="synkk-price-subtitle">More room for growing teams that need higher storage, vault and device limits.</p>
                            <ul>
                                <li>25 GB managed cloud storage</li>
                                <li>Up to 25 vaults and 100 connected devices</li>
                                <li>Up to 25 team members (no per-seat fee)</li>
                                <li>Everything in Cloud Pro, including Path ACLs, DLP, Remote Wipe &amp; AI automation</li>
                                <li>Includes $2 one-time Managed Cloud AI trial credit (optional {{ $aiAddonPrice }}/mo AI Add-On)</li>
                            </ul>
                            @auth
                                <a href="{{ $dashboardUrl }}" class="synkk-button synkk-button--ink">Go to Dashboard <span aria-hidden="true">→</span></a>
                            @else
                                <a href="{{ route('register') }}" class="synkk-button synkk-button--ink">Choose Business <span aria-hidden="true">→</span></a>
                            @endauth
                        </article>
                    </div>

                    <div class="synkk-pricing-ltd">
                        <div class="synkk-pricing-ltd__copy">
                            <h4>Self-Host Pro Commercial License · {{ $proPrice }} Lifetime Deal</h4>
                            <p>Unlock commercial team controls on your own server or hosted workspace: Path-Level ACLs, IP Allowlisting, Read-Only Tokens, Remote Device Wipe, AI Smart Merge, Vault Gardener, Team Digests, and Portal AI Assistant (default plan limits: 15 vaults, 10 members, 25 devices, 15 GB; self-hosted admins can customize quotas). Optional <strong>Managed Cloud AI Add-On ({{ $aiAddonPrice }}/workspace/mo)</strong> is available if you prefer turnkey hosted LLMs over BYOK or local Ollama.</p>
                        </div>
                        @if ($storeReady && filled($storeUrl))
                            <a href="{{ $storeUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--accent">Get Pro LTD ({{ $proPrice }}) <span aria-hidden="true">→</span></a>
                        @elseif ($userTeam && filled(config('services.dodo.api_key')) && filled(config('services.dodo.pro_product_id')))
                            <form method="POST" action="{{ route('billing.dodo.checkout', ['current_team' => $userTeam->slug]) }}" class="inline">
                                @csrf
                                <input type="hidden" name="plan" value="pro_ltd">
                                <button type="submit" class="synkk-button synkk-button--accent">Get Pro LTD ({{ $proPrice }}) <span aria-hidden="true">→</span></button>
                            </form>
                        @elseif ($storeReady)
                            <a href="{{ route('register') }}" class="synkk-button synkk-button--accent">Get Pro LTD ({{ $proPrice }}) <span aria-hidden="true">→</span></a>
                        @else
                            <button type="button" class="synkk-button synkk-button--pending" disabled>Checkout opens after launch checks</button>
                        @endif
                    </div>

                    <div class="synkk-pricing-enterprise">
                        <div class="synkk-pricing-enterprise__copy">
                            <strong>Need custom Kubernetes clusters, air-gapped deployments, or support for your compliance program?</strong>
                            <p>We provide dedicated support channels, IP subnet audit logging, and custom SLAs for enterprise organizations.</p>
                        </div>
                        <a href="https://book-it.ottomate.space" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--outline">Book a meeting <span aria-hidden="true">↗</span></a>
                    </div>

                    <div class="synkk-pricing-trust">
                        <div class="synkk-trust-item">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                            <span>100% Self-Hosted or Managed Cloud</span>
                        </div>
                        <div class="synkk-trust-item">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
                            <span>Instant License &amp; Cloud Activation</span>
                        </div>
                        <div class="synkk-trust-item">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                            <span>Dodo Payments · Zero Recurring Seat Tax</span>
                        </div>
                        <div class="synkk-trust-item">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v1h8v-1zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 16v-1a4.978 4.978 0 00-1.552-3.619A6.974 6.974 0 0118 15v1h-2zM4 16v-1c0-.853.21-1.657.58-2.368A4.978 4.978 0 003 15v1h1z"/></svg>
                            <span>Obsidian Plugin Always Free</span>
                        </div>
                    </div>

                    <p class="synkk-launch-channels"><span>LAUNCH CHANNELS</span> GitHub hosts the public open-source plugin and server code. Dodo Payments powers both monthly Cloud subscriptions and one-time Pro lifetime licenses. Enterprise consultations are booked directly at <a href="https://book-it.ottomate.space" target="_blank" rel="noopener noreferrer" class="underline">book-it.ottomate.space</a>.</p>
                </div>
            </section>

            <section id="roadmap" class="synkk-roadmap synkk-viewport-section" aria-labelledby="roadmap-heading">
                <div class="synkk-shell synkk-roadmap__grid">
                    <header class="synkk-roadmap__intro">
                        <p class="synkk-eyebrow">08 / Built in the open</p>
                        <h2 id="roadmap-heading">Plugin now. Server release next.</h2>
                        <p>The public plugin is downloadable today. The rows separate what is live, what must clear launch, and what follows.</p>
                        <a href="{{ $pluginUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--paper">See the public repository <span aria-hidden="true">↗</span></a>

                        <div class="synkk-roadmap-monitor" aria-label="Release telemetry">
                            <div class="synkk-roadmap-monitor__header">
                                <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                                <strong>RELEASE MONITOR</strong>
                            </div>
                            <div class="synkk-roadmap-monitor__grid">
                                <div><small>STABLE PLUGIN</small><span>v1.1.1 Community</span></div>
                                <div><small>SERVER ENGINE</small><span>Laravel 13 · SQLite WAL</span></div>
                                <div><small>COLLAB SPEC</small><span>CRDT / Yjs (Opt-In)</span></div>
                                <div><small>AI &amp; AGENTS</small><span>RAG · Inbox · MCP</span></div>
                                <div><small>NEXT UP</small><span>Webhooks &amp; Domains</span></div>
                            </div>
                        </div>
                    </header>

                    <ol class="synkk-roadmap-list">
                        <li class="is-live">
                            <span class="synkk-roadmap-status"><span class="synkk-beacon" aria-hidden="true"><i></i></span>LIVE NOW</span>
                            <div>
                                <strong>Obsidian Community Plugin v1.1.1</strong>
                                <p>Official community release directly installable from Obsidian's Community Plugin catalog (Obsidian 1.7.2+, Desktop &amp; Mobile). Features whole-file SHA-256 sync, Safety Shield (10% default), AI Sidebar, Proposal Inbox, Local E2EE AI, Ghost Files, and conflict forking.</p>
                                <div class="synkk-roadmap-chips">
                                    <span>Obsidian Catalog</span>
                                    <span>SHA-256 Checksums</span>
                                    <span>Safety Shield 10%</span>
                                    <span>AI Sidebar &amp; Inbox</span>
                                    <span>Desktop + Mobile</span>
                                </div>
                            </div>
                        </li>
                        <li class="is-live">
                            <span class="synkk-roadmap-status"><span class="synkk-beacon" aria-hidden="true"><i></i></span>LIVE NOW</span>
                            <div>
                                <strong>Foundation Server Release</strong>
                                <p>Self-hosted Laravel 13 + PHP 8.5 server with SQLite WAL + FTS5, web Markdown workspace, 2D visual graph view, Passkeys &amp; 2FA, path permissions matrix, 25-version rollback, ZIP export, and Docker / VPS Caddy orchestration.</p>
                                <div class="synkk-roadmap-chips">
                                    <span>Docker Compose</span>
                                    <span>SQLite WAL + FTS5</span>
                                    <span>Passkeys &amp; 2FA</span>
                                    <span>2D Graph Canvas</span>
                                    <span>Permissions Matrix</span>
                                </div>
                            </div>
                        </li>
                        <li class="is-live">
                            <span class="synkk-roadmap-status"><span class="synkk-beacon" aria-hidden="true"><i></i></span>SHIPPED</span>
                            <div>
                                <strong>Interactive Livewire Vault Portals</strong>
                                <p>Publish non-E2EE team vaults as interactive Livewire 4 web portals at <code>/p/{slug}</code> with zero build steps, 4 layouts, 5 color themes, password protection, Graph View, Ask This Portal AI, and <code>/llms.txt</code>.</p>
                                <div class="synkk-roadmap-chips">
                                    <span>Vault Portals</span>
                                    <span>Livewire 4</span>
                                    <span>4 Layouts · 5 Themes</span>
                                    <span>Portal AI &amp; /llms.txt</span>
                                    <span>Wikilinks &amp; Backlinks</span>
                                </div>
                            </div>
                        </li>
                        <li class="is-live">
                            <span class="synkk-roadmap-status"><span class="synkk-beacon" aria-hidden="true"><i></i></span>SHIPPED</span>
                            <div>
                                <strong>First-Sync Pre-Flight &amp; Migration Engine</strong>
                                <p>Deterministic 4-phase local diagnostic scan, friction trap detection (<code>.trash/</code>, <code>.git/</code>, &gt;25 MB), 1-click cross-platform path sanitizer, dry-run quota simulation API, and configurable Atomic Safety Shield.</p>
                                <div class="synkk-roadmap-chips">
                                    <span>Pre-Flight Wizard</span>
                                    <span>Category Rollup</span>
                                    <span>Path Sanitizer</span>
                                    <span>Quota Simulation</span>
                                    <span>Safety Shield</span>
                                </div>
                            </div>
                        </li>
                        <li class="is-live">
                            <span class="synkk-roadmap-status"><span class="synkk-beacon" aria-hidden="true"><i></i></span>SHIPPED</span>
                            <div>
                                <strong>Full CodeMirror 6 Web CRDT Collaborative Editor</strong>
                                <p>Real-time collaborative Markdown editing powered by durable Yjs journals over Laravel Reverb, live remote carets, AES-256-GCM + AAD support for E2EE rooms, and opt-in plugin toggle.</p>
                                <div class="synkk-roadmap-chips">
                                    <span>CodeMirror 6</span>
                                    <span>Live Remote Carets</span>
                                    <span>Yjs CRDT + Reverb</span>
                                    <span>E2EE AAD Support</span>
                                </div>
                            </div>
                        </li>
                        <li class="is-live">
                            <span class="synkk-roadmap-status"><span class="synkk-beacon" aria-hidden="true"><i></i></span>SHIPPED</span>
                            <div>
                                <strong>Vault AI Agent, Proposal Inbox, Gardener &amp; Remote MCP Server</strong>
                                <p>Hybrid SQLite FTS5 + vector search, streaming Vault Copilot, human-approved AI Proposal Diff Inbox, AI Smart Merge, Vault Gardener, Team Digests, Local E2EE Ollama AI, and 7-tool permissioned Remote MCP Server (<code>POST /api/v1/mcp</code>).</p>
                                <div class="synkk-roadmap-chips">
                                    <span>Remote MCP Server</span>
                                    <span>Hybrid FTS5 + Vector</span>
                                    <span>AI Proposal Inbox</span>
                                    <span>Vault Gardener</span>
                                    <span>Local E2EE Ollama</span>
                                </div>
                            </div>
                        </li>
                        <li class="is-next">
                            <span class="synkk-roadmap-status"><span class="synkk-beacon synkk-beacon--amber" aria-hidden="true"><i></i></span>PLANNED</span>
                            <div>
                                <strong>Outbound Webhooks, Custom Portal Domains &amp; Trash Bin UI</strong>
                                <p>Signed HMAC-SHA256 outbound webhooks (Slack/Discord/Zapier), custom domain routing for Vault Portals, and an interactive Trash / Recycle Bin UI for browsing and restoring soft-deleted files before the 30-day prune.</p>
                                <div class="synkk-roadmap-chips">
                                    <span>Outbound Webhooks</span>
                                    <span>Custom Portal Domains</span>
                                    <span>Trash / Undelete UI</span>
                                    <span>Signed HMAC-SHA256</span>
                                </div>
                            </div>
                        </li>
                        <li class="is-next">
                            <span class="synkk-roadmap-status"><span class="synkk-beacon synkk-beacon--amber" aria-hidden="true"><i></i></span>PLANNED</span>
                            <div>
                                <strong>Headless CLI Sync Client &amp; Native SSO / OIDC</strong>
                                <p>Open sync contract CLI for CI/headless servers, plus enterprise OAuth / OIDC / SAML single sign-on alongside existing Passkeys and TOTP 2FA.</p>
                                <div class="synkk-roadmap-chips">
                                    <span>Headless CLI Client</span>
                                    <span>OIDC / SAML SSO</span>
                                    <span>Open Sync Spec</span>
                                </div>
                            </div>
                        </li>
                    </ol>
                </div>
            </section>

            <section id="faq" class="synkk-faq synkk-viewport-section synkk-shell" aria-labelledby="faq-heading">
                <header class="synkk-section-heading">
                    <div>
                        <p class="synkk-eyebrow">09 / Before you install</p>
                        <h2 id="faq-heading">Clear answers before you sync.</h2>
                    </div>
                    <p>The public plugin, current product, and future roadmap are labelled separately so you can choose the right starting point.</p>
                </header>

                <div class="synkk-faq-list">
                    <details open>
                        <summary>
                            <span>01</span>What can I install today?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>You can install the official Synkk Obsidian plugin (<code>v1.1.1</code>, requires Obsidian <code>1.7.2+</code>) directly from Obsidian's Community Plugins catalog on macOS, Windows, Linux, iOS, and Android right now. You can also self-host the Synkk server using Docker Compose or our VPS Caddy installer, or sign up for a 30-day card-verified trial on Synkk Cloud Pro.</p>
                        </div>
                    </details>
                    <details>
                        <summary>
                            <span>02</span>Does Synkk replace my Markdown files?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>No. Your vault remains portable files and folders. Synkk adds controlled sync, access, and recovery around the notes you own.</p>
                        </div>
                    </details>
                    <details>
                        <summary>
                            <span>03</span>Is character-level CRDT sync available?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>Yes. Synkk provides real-time character-level CRDT multiplayer editing powered by Yjs over Laravel Reverb, featuring remote cursor awareness, shared undo history, and durable journal catch-up across both the web editor and Obsidian. Inside the Obsidian plugin, real-time collaboration is opt-in (disabled by default to prioritize atomic whole-file sync stability) and can be toggled on anytime in settings.</p>
                        </div>
                    </details>
                    <details>
                        <summary>
                            <span>04</span>How do the pricing and commercial licenses work?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>Synkk Community and the Obsidian plugin are MIT open-source ($0; default hosted Community Free tier includes 1 vault, 3 members, 3 devices, and 1 GB). Synkk Cloud Pro ({{ $cloudProPrice }}/mo) and Cloud Business ({{ $cloudBusinessPrice }}/mo) offer managed workspaces via Dodo Payments with a 30-day card-verified trial. For self-hosters wanting commercial team controls, the {{ $proPrice }} Pro Lifetime Deal (LTD) is a one-time purchase. Connecting your own AI keys (BYOK) or local Ollama is free; an optional {{ $aiAddonPrice }}/mo Managed Cloud AI Add-On is available for turnkey hosted AI credits.</p>
                        </div>
                    </details>
                    <details>
                        <summary>
                            <span>05</span>Can I self-host Synkk?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>Yes. Synkk is MIT open-source on GitHub. You can deploy from a repository checkout using <code>install.sh</code> (auto-configures Docker + Caddy HTTPS on Ubuntu/Debian/AlmaLinux/Rocky) or run <code>docker compose -f docker-compose.prod.yml up -d --build</code> behind your own reverse proxy.</p>
                        </div>
                    </details>
                    <details>
                        <summary>
                            <span>06</span>What happens when two devices edit the same note?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>Synkk preserves both versions using non-destructive conflict copies and opens the Visual Conflict Sandbox (with optional AI Smart Merge) for safe reconciliation—or merges live when opt-in Yjs CRDT collaboration is enabled.</p>
                        </div>
                    </details>
                    <details>
                        <summary>
                            <span>07</span>Can I sync only selected folders?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>Yes. The plugin supports per-device include/exclude folder rules, configurable plugin/theme/snippet syncing (with mobile desktop-only plugin filtering), plus on-demand Ghost Files for attachments larger than 5 MB (configurable 2–50 MB).</p>
                        </div>
                    </details>
                    <details>
                        <summary>
                            <span>08</span>Does Synkk read my private vault?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>Synkk only receives the files and paths your configured device and team permissions allow. When End-to-End Encryption (E2EE) is enabled, note contents, attachments, and real-time collaboration updates are encrypted and decrypted strictly on your device using WebCrypto AES-256-GCM before transmission, so the server only ever stores opaque ciphertext blobs (file paths and sizes remain metadata for sync routing). For E2EE vaults, server-side search and RAG indexing are disabled to guarantee zero server knowledge—you can still query encrypted vaults privately via Local Ollama AI in the Obsidian plugin.</p>
                        </div>
                    </details>
                </div>
            </section>

            <section class="synkk-final-cta synkk-viewport-section synkk-shell" aria-labelledby="final-heading">
                <div class="synkk-final-cta__ambient" aria-hidden="true"></div>
                <div class="synkk-final-cta__grid">
                    <div class="synkk-final-cta__copy">
                        <div class="synkk-cta-badge">
                            <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                            <span>v1.1.1 Community Release · Obsidian Directory Ready</span>
                        </div>
                        <p class="synkk-eyebrow">Your next chapter</p>
                        <h2 id="final-heading">Install the Obsidian plugin.</h2>
                        <p>Install v1.1.1 directly inside Obsidian (Settings &gt; Community Plugins &gt; Browse &gt; Synkk Team Vault Sync), self-host the server, or launch your managed Cloud workspace.</p>
                        <div class="synkk-final-cta__actions">
                            <a href="{{ $pluginObsidianUri }}" class="synkk-button synkk-button--accent">Install in Obsidian <span aria-hidden="true">↗</span></a>
                            <a href="{{ route('public.docs') }}" class="synkk-button synkk-button--paper">Read the docs <span aria-hidden="true">→</span></a>
                        </div>
                        <div class="synkk-cta-guarantees">
                            <span><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> 100% Local Files</span>
                            <span><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Zero Telemetry</span>
                            <span><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Self-Hosted Server</span>
                        </div>
                    </div>

                    <div class="synkk-cta-terminal" aria-label="Quick setup terminal commands">
                        <div class="synkk-cta-terminal__header">
                            <div class="synkk-cta-terminal__controls" aria-hidden="true">
                                <span class="synkk-dot synkk-dot--red"></span>
                                <span class="synkk-dot synkk-dot--amber"></span>
                                <span class="synkk-dot synkk-dot--green"></span>
                            </div>
                            <span class="synkk-cta-terminal__title">synkk-quickstart.sh</span>
                            <span class="synkk-cta-terminal__badge">Docker / Compose</span>
                        </div>
                        <div class="synkk-cta-terminal__body">
                            <div class="synkk-code-line"><span class="synkk-prompt">$</span> <span class="synkk-cmd">git clone --recurse-submodules https://github.com/tawandajosephmutsena/synkk.git &amp;&amp; cd synkk</span></div>
                            <div class="synkk-code-line"><span class="synkk-prompt">$</span> <span class="synkk-cmd">cp .env.production.example .env.production &amp;&amp; docker compose -f docker-compose.prod.yml up -d --build</span></div>
                            <div class="synkk-code-output"><span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span> Synkk vault server online at http://127.0.0.1:8000</div>
                            <div class="synkk-code-line synkk-code-line--comment"># Pair Obsidian plugin with QR code or device token: synkk_...</div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="synkk-footer">
            <div class="synkk-shell">
                <div class="synkk-footer-telemetry">
                    <div class="synkk-footer-telemetry__status">
                        <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                        <strong>SYSTEM STATUS:</strong>
                        <span>Obsidian Plugin v1.1.1 · Laravel 13 · Local-First Architecture</span>
                    </div>
                    <a href="https://github.com/tawandajosephmutsena/synk-obsidian-plugin" target="_blank" rel="noopener noreferrer" class="synkk-footer-telemetry__link">Public Repository ↗</a>
                </div>

                <div class="synkk-footer__top">
                    <div>
                        <img src="/images/synkk-logo.svg" alt="Synkk — Obsidian everywhere" width="689" height="270" loading="lazy">
                        <p>Portable notes. Visible safety. Infrastructure you control.</p>
                    </div>
                    <nav aria-label="Footer navigation">
                        <a href="#product">Product</a>
                        <a href="#workflow">How it works</a>
                        <a href="#safety">Safety</a>
                        <a href="#comparison">Why Synkk</a>
                        <a href="#moonshot">Moonshot</a>
                        <a href="#pricing">Pricing</a>
                        <a href="#roadmap">Roadmap</a>
                        <a href="#faq">FAQ</a>
                        <a href="{{ route('about') }}">About</a>
                        <a href="{{ route('public.docs') }}">Documentation</a>
                        <a href="{{ route('privacy') }}">Privacy</a>
                        <a href="{{ route('terms') }}">Terms</a>
                        <a href="{{ route('security') }}">Security</a>
                        <a href="{{ $pluginUrl }}" target="_blank" rel="noopener noreferrer">GitHub ↗</a>
                    </nav>
                </div>
                <div class="synkk-footer__bottom">
                    <span>© {{ now()->year }} Synkk</span>
                    <span>Obsidian everywhere</span>
                    <span>Built in public</span>
                </div>
            </div>
        </footer>
    </body>
</html>
