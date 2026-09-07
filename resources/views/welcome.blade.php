<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Synkk — Obsidian everywhere</title>
        <meta name="description" content="Self-hosted Obsidian sync with a real Markdown workspace, a visual vault graph, trusted devices, path permissions, verified changes, and recoverable versions.">
        <meta name="theme-color" content="#f7f8f3">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <script>
            (function() {
                const t = localStorage.getItem('synkk-theme') || 'system';
                const isDark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (isDark) {
                    document.documentElement.classList.add('dark');
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            })();
        </script>
        @fonts
        @vite(['resources/css/landing.css', 'resources/js/landing.js'])
    </head>
    <body class="synkk-site font-sans antialiased" data-motion="on">
        @php
            $pluginUrl = 'https://github.com/tawandajosephmutsena/synk-obsidian-plugin';
            $pluginReleaseUrl = 'https://github.com/tawandajosephmutsena/synk-obsidian-plugin/releases/tag/1.0.0';
            $storeUrl = config('synkk.lemon_squeezy.store_url');
            $storeReady = filled($storeUrl)
                && filled(config('synkk.lemon_squeezy.store_id'))
                && filled(config('synkk.lemon_squeezy.product_id'));
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
                    <a href="{{ route('public.docs') }}">Docs</a>
                </nav>

                <div class="synkk-header__actions">
                    <div class="synkk-theme-switcher" data-theme-switcher aria-label="Theme switcher">
                        <button type="button" data-theme-set="light" title="Light mode" aria-label="Light mode">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        </button>
                        <button type="button" data-theme-set="dark" title="Dark mode" aria-label="Dark mode">
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
                <p><span class="synkk-status-dot" aria-hidden="true"></span> Obsidian plugin v1.0.0 is live on GitHub</p>
                <a href="{{ $pluginReleaseUrl }}" target="_blank" rel="noopener noreferrer">View the release <span aria-hidden="true">↗</span></a>
            </div>
        </div>

        <main id="main-content">
            <section class="synkk-hero synkk-shell" aria-labelledby="hero-heading">
                <div class="synkk-hero__frame">
                    <div class="synkk-hero__copy">
                        <p class="synkk-eyebrow synkk-reveal synkk-reveal--one">Self-hosted sync for Obsidian</p>
                        <h1 id="hero-heading" class="synkk-reveal--two"><span class="synkk-hero__line"><span>Your vault.</span></span> <span class="synkk-hero__line"><span>On every</span></span> <span class="synkk-hero__line synkk-hero__line--accent"><span>device.</span><svg viewBox="0 0 100 100" fill="none" aria-hidden="true"><path d="M18 51h64M53 22l29 29-29 29" stroke="currentColor" stroke-width="5"/></svg></span></h1>
                        <p class="synkk-hero__lede synkk-reveal synkk-reveal--three">A home for your notes. A connection between your devices. All on infrastructure you control.</p>
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
                                    <a href="{{ $pluginReleaseUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--ink">Download Plugin v1.0.0 <span aria-hidden="true">↗</span></a>
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
                                <p>Deploy Synkk with a single Docker command using embedded high-performance SQLite WAL. Retain 100% data ownership on your own infrastructure. Every device authenticates using scoped, revocable API tokens. If a laptop or phone is lost or stolen, administrators can remotely wipe the device token with one click.</p>
                                <ul class="synkk-feature-card__list">
                                    <li><strong>Single Docker container:</strong> Production-ready with Nginx, PHP 8.4, and SQLite WAL out of the box.</li>
                                    <li><strong>Instant remote token wipe:</strong> Immediately sever and invalidate stolen device tokens.</li>
                                    <li><strong>Zero telemetry:</strong> No tracking, no third-party analytics, and no external data exposure.</li>
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
                                        <small>docker-compose.yml</small>
                                    </div>
                                    <pre class="synkk-docker-code"><code>services:
  synkk:
    image: synkk/server:latest
    ports: ["8000:80"]
    volumes:
      - ./storage:/var/www/html/storage/app/private
      - ./database:/var/www/html/database
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - DB_CONNECTION=sqlite</code></pre>
                                    <div class="synkk-graphic-wipe-tag">
                                        <span>🔒 Remote Device Wipe</span>
                                        <small>HTTP 410 auto-clears local tokens instantly</small>
                                    </div>
                                </div>
                            </div>
                        </article>
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

            <section id="comparison" class="synkk-comparison synkk-viewport-section" aria-labelledby="comparison-heading">
                <div class="synkk-shell">
                    <header class="synkk-section-heading">
                        <div>
                            <p class="synkk-eyebrow">05 / The Sovereign Standard</p>
                            <h2 id="comparison-heading">What makes Synkk better than existing Obsidian sync tools?</h2>
                        </div>
                        <p>Most sync alternatives force a painful compromise: fragile DIY git setups, complex CouchDB database maintenance, or closed proprietary cloud silos with recurring monthly seat taxes. Synkk gives your team a local-first brain with path-level security, secret leak prevention, and 100% data sovereignty on hardware you own.</p>
                    </header>

                    <div class="synkk-comparison-table-wrapper">
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
                                            <span class="synkk-cell-sub">Private SQLite WAL engine. Your data never touches third-party clouds.</span>
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
                                        <p class="synkk-cell-dim-desc">Automatic secret &amp; credential scanning before notes leave local storage.</p>
                                    </th>
                                    <td class="synkk-col-featured">
                                        <div class="synkk-cell-check">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> In-App DLP Shield</strong>
                                            <span class="synkk-cell-sub">Detects AWS, OpenAI, Stripe, and private keys. Warns and blocks leaks locally.</span>
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
                                        <strong>Cost for a Team of 15</strong>
                                        <p class="synkk-cell-dim-desc">3-year total software cost for a 15-person engineering or research group.</p>
                                    </th>
                                    <td class="synkk-col-featured">
                                        <div class="synkk-cell-check">
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> $0 Free / $79 LTD</strong>
                                            <span class="synkk-cell-sub">100% free open-source, or $79 lifetime team server (up to 10 users). Zero per-seat tax.</span>
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
                                <span class="synkk-moonshot-phase">STAGE 2 COLLAB SPEC</span>
                            </div>
                            <div class="synkk-moonshot-card__body">
                                <div class="synkk-moonshot-card__content">
                                    <h3>Two users typing in the exact same .md note simultaneously without Git merge hell.</h3>
                                    <p>Traditional sync engines force last-write-wins overwrites or leave ugly Git conflict markers that corrupt your Markdown. Synkk's CRDT engine integrates Yjs binary state vectors to merge concurrent keystrokes deterministically at the sub-character level.</p>
                                    <ul class="synkk-moonshot-features">
                                        <li><strong>Zero Merge Hell:</strong> Eliminates <code>&lt;&lt;&lt;&lt;&lt;&lt;&lt; HEAD</code> conflict markers forever.</li>
                                        <li><strong>Real-Time Remote Cursors:</strong> Live multi-caret awareness across desktop, mobile, and web.</li>
                                        <li><strong>Offline-First Resilience:</strong> Edit without internet; changes converge smoothly upon reconnect.</li>
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
                                <span class="synkk-moonshot-phase synkk-moonshot-phase--live">LIVE IN V1.0</span>
                            </div>
                            <div class="synkk-moonshot-card__body">
                                <div class="synkk-moonshot-card__content">
                                    <h3>Scan a single QR code on the Synkk web dashboard to link iOS/Android in 2 seconds.</h3>
                                    <p>No more tedious typing of server URLs, port numbers, long device tokens, or complex base64 keys on touchscreen keyboards. Synkk generates a secure, instant-pairing QR code on your dashboard that auto-provisions iOS and Android devices in one camera scan.</p>
                                    <ul class="synkk-moonshot-features">
                                        <li><strong>2-Second Pairing:</strong> Auto-configures Server API URL, Device Token, and Vault slug.</li>
                                        <li><strong>Cross-Platform Support:</strong> One-tap connect for iPhone, iPad, and Android devices.</li>
                                        <li><strong>Cryptographic Safety:</strong> Scoped, revocable device tokens with remote wipe capability.</li>
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
                                <span class="synkk-moonshot-phase">FUTURE HORIZON</span>
                            </div>
                            <div class="synkk-moonshot-card__body">
                                <div class="synkk-moonshot-card__content">
                                    <h3>Self-hosted vector embeddings &amp; local LLM chat answering questions from your vault.</h3>
                                    <p>Transform your static notes into an active reasoning engine. Synkk pairs your 2D <code>[[wikilink]]</code> knowledge graph with local vector embeddings. Run private LLM queries with Ollama or vLLM directly over your notes without exposing proprietary knowledge to third-party AI APIs.</p>
                                    <ul class="synkk-moonshot-features">
                                        <li><strong>Graph-Augmented RAG:</strong> Traverses note backlinks to retrieve deeply connected contextual memory.</li>
                                        <li><strong>100% Private Embeddings:</strong> Local vector generation ensures zero confidential vault leaks.</li>
                                        <li><strong>Agentic Retrieval:</strong> Synthesizes verified answers with exact line and note citations.</li>
                                    </ul>
                                </div>
                                <div class="synkk-moonshot-visual">
                                    <div class="synkk-rag-preview">
                                        <div class="synkk-rag-header">
                                            <span><i></i> Local RAG Node</span>
                                            <span class="synkk-rag-status">Ollama / Llama-3 Active</span>
                                        </div>
                                        <div class="synkk-rag-query">
                                            <span class="synkk-rag-prompt">&gt; Query:</span>
                                            <p>"Summarize our team's sync protocol and security boundaries."</p>
                                        </div>
                                        <div class="synkk-rag-context">
                                            <small>Retrieved 3 nodes via [[wikilink]] graph traversal:</small>
                                            <div class="synkk-rag-chips">
                                                <span>[[Architecture/DLP-Shield.md]] (98%)</span>
                                                <span>[[Security/Path-ACLs.md]] (95%)</span>
                                                <span>[[API/Sha256-Hash.md]] (91%)</span>
                                            </div>
                                        </div>
                                        <div class="synkk-rag-answer">
                                            <p>Synkk enforces path-level ACLs per member and verifies every revision with SHA-256 checksums before accepting writes. Confidential secrets are caught by in-app DLP scanning.</p>
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
                                <span class="synkk-moonshot-phase">FUTURE HORIZON</span>
                            </div>
                            <div class="synkk-moonshot-card__body">
                                <div class="synkk-moonshot-card__content">
                                    <h3>Server stores encrypted blobs; web viewer decrypts via WebAssembly/WebCrypto.</h3>
                                    <p>Maximum cryptographic privacy for regulated and security-sensitive teams. Files are encrypted on your device using AES-256-GCM before transmission. The Synkk server only ever sees opaque ciphertext blobs, while the web workspace decrypts files locally in your browser memory via WebAssembly.</p>
                                    <ul class="synkk-moonshot-features">
                                        <li><strong>Zero Server Knowledge:</strong> Plaintext notes and encryption keys never touch server disks.</li>
                                        <li><strong>WebAssembly Decryptor:</strong> High-performance client-side decryption right in your browser.</li>
                                        <li><strong>Team Key Governance:</strong> Asymmetric key exchange protocols for secure multi-seat sharing.</li>
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
                                                <span>Wasm</span>
                                                <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                            </div>
                                            <div class="synkk-e2ee-stage">
                                                <div class="synkk-e2ee-badge">WEB VIEWER</div>
                                                <div class="synkk-e2ee-box">Decrypted Note</div>
                                                <small>In-Browser DOM</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section id="pricing" class="synkk-pricing synkk-viewport-section" aria-labelledby="pricing-heading">
                <div class="synkk-shell">
                    <header class="synkk-section-heading">
                        <div>
                            <p class="synkk-eyebrow">07 / Packaging &amp; Pricing Architecture</p>
                            <h2 id="pricing-heading">Own your team brain. No recurring seat tax.</h2>
                        </div>
                        <p>Choose the model that fits your workflow: 100% free open-source self-hosting, lifetime commercial team server ownership, or zero-config managed cloud.</p>
                    </header>

                    <div class="synkk-pricing-grid">
                        <!-- Tier 1: Synkk Community -->
                        <article class="synkk-pricing-card synkk-pricing-card--community">
                            <div class="synkk-price-heading">
                                <span>FREE &amp; OPEN SOURCE · SELF-HOSTED</span>
                                <p><strong>$0</strong><small>free forever · full source code</small></p>
                            </div>
                            <h3>Synkk Community</h3>
                            <p class="synkk-price-subtitle">Full source code for developers, homelabs &amp; independent creators.</p>
                            <ul>
                                <li>Full source code (Laravel backend + Obsidian plugin)</li>
                                <li>Whole-file sync &amp; cryptographic SHA-256 verification</li>
                                <li>Note version history &amp; 1-click snapshot restore</li>
                                <li>In-browser Markdown web editor &amp; document outline</li>
                                <li>Basic folder scoping &amp; path exclusions</li>
                                <li>Unlimited notes, unlimited vaults &amp; unlimited devices</li>
                                <li>Public community support via GitHub Discussions</li>
                            </ul>
                            <a href="{{ $pluginUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--ink">Clone on GitHub <span aria-hidden="true">↗</span></a>
                        </article>

                        <!-- Tier 2: Synkk Pro / Team (Featured) -->
                        <article class="synkk-pricing-card synkk-pricing-card--pro is-featured">
                            <div class="synkk-price-featured-badge">
                                <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                                <span>APPSUMO LAUNCH DEAL · BEST VALUE</span>
                            </div>
                            <div class="synkk-price-heading">
                                <span>SELF-HOSTED COMMERCIAL LICENSE</span>
                                <p><strong>$79</strong><small>one-time lifetime deal (launch phase $59–$99)</small></p>
                            </div>
                            <div class="synkk-price-commercial-toggle">
                                <span>Standard commercial: <strong>$8</strong> / user / month or <strong>$79</strong> / year / seat</span>
                            </div>
                            <h3>Synkk Pro / Team</h3>
                            <p class="synkk-price-subtitle">Self-hosted commercial server with full governance (up to 10 users).</p>
                            <ul>
                                <li>Everything in Community, plus:</li>
                                <li>Lifetime self-hosted team server (up to 10 users with launch deal)</li>
                                <li>In-App DLP Secret Scanning (intercepts leaked OpenAI / AWS keys)</li>
                                <li>IP Whitelisting &amp; Subnet restriction rules</li>
                                <li>1-Click Instant Remote Device Wipe (HTTP 410 token purge)</li>
                                <li>Webhook automation &amp; event relays (Slack, Discord, Zapier)</li>
                                <li>Granular Path-Level ACLs (Inbox vs Client vs Internal)</li>
                                <li>Priority Support directly from core maintainers</li>
                            </ul>
                            @if ($storeReady)
                                <a href="{{ $storeUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--accent">Get Lifetime License <span aria-hidden="true">↗</span></a>
                            @else
                                <button type="button" class="synkk-button synkk-button--pending" disabled>Checkout opens after launch checks</button>
                            @endif
                        </article>

                        <!-- Tier 3: Synkk Cloud -->
                        <article class="synkk-pricing-card synkk-pricing-card--cloud">
                            <div class="synkk-price-heading">
                                <span>ZERO-CONFIG MANAGED SAAS</span>
                                <p><strong>$12</strong><small>/ user / month</small></p>
                            </div>
                            <h3>Synkk Cloud</h3>
                            <p class="synkk-price-subtitle">For teams that love Obsidian but do not want to manage Docker or servers.</p>
                            <ul>
                                <li>Zero DevOps: no Docker, PHP, SSL certs, or database backups</li>
                                <li>1-Click team setup — hosted in Frankfurt (GDPR) or US-East</li>
                                <li>Automated hourly offsite encrypted backups &amp; failover</li>
                                <li>Full Pro feature suite: DLP scanning, remote wipe &amp; webhooks</li>
                                <li>Automated updates, security patches, and zero maintenance</li>
                                <li>99.99% uptime SLA &amp; dedicated priority cloud support</li>
                                <li>Multi-device sync with instant 2-second QR pairing</li>
                            </ul>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="synkk-button synkk-button--ink">Start Cloud Workspace <span aria-hidden="true">→</span></a>
                            @else
                                <a href="{{ route('login') }}" class="synkk-button synkk-button--ink">Start Cloud Workspace <span aria-hidden="true">→</span></a>
                            @endif
                        </article>
                    </div>

                    <div class="synkk-pricing-enterprise">
                        <div class="synkk-pricing-enterprise__copy">
                            <strong>Need custom Kubernetes clusters, air-gapped deployments, or SOC2 compliance?</strong>
                            <p>We provide dedicated support channels, IP subnet audit logging, and custom SLAs for enterprise organizations.</p>
                        </div>
                        <a href="https://book-it.ottomate.space" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--quiet">Book a meeting <span aria-hidden="true">↗</span></a>
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
                            <span>AppSumo Lifetime Deal · Zero Recurring Seat Tax</span>
                        </div>
                        <div class="synkk-trust-item">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v1h8v-1zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 16v-1a4.978 4.978 0 00-1.552-3.619A6.974 6.974 0 0118 15v1h-2zM4 16v-1c0-.853.21-1.657.58-2.368A4.978 4.978 0 003 15v1h1z"/></svg>
                            <span>Obsidian Plugin Always Free</span>
                        </div>
                    </div>

                    <p class="synkk-launch-channels"><span>LAUNCH CHANNELS</span> GitHub hosts the public open-source plugin and server code. Lemon Squeezy and AppSumo manage commercial licenses. Enterprise consultations are booked directly at <a href="https://book-it.ottomate.space" target="_blank" rel="noopener noreferrer" class="underline hover:text-zinc-900">book-it.ottomate.space</a>.</p>
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
                                <div><small>STABLE PLUGIN</small><span>v1.0.0 Public</span></div>
                                <div><small>SERVER ENGINE</small><span>SQLite WAL</span></div>
                                <div><small>COLLAB SPEC</small><span>CRDT / Yjs</span></div>
                                <div><small>INTEGRITY</small><span>SHA-256 Verified</span></div>
                            </div>
                        </div>
                    </header>

                    <ol class="synkk-roadmap-list">
                        <li class="is-live">
                            <span class="synkk-roadmap-status"><span class="synkk-beacon" aria-hidden="true"><i></i></span>LIVE NOW</span>
                            <div>
                                <strong>Obsidian plugin v1.0.0</strong>
                                <p>The installable plugin release is public on GitHub now with hash-based sync, Safety Shield deletion protection, path exclusions, and automatic conflict forking.</p>
                                <div class="synkk-roadmap-chips">
                                    <span>SHA-256 Checksums</span>
                                    <span>Safety Shield 10%</span>
                                    <span>Conflict Forking</span>
                                    <span>Path Exclusions</span>
                                </div>
                            </div>
                        </li>
                        <li class="is-live">
                            <span class="synkk-roadmap-status"><span class="synkk-beacon" aria-hidden="true"><i></i></span>LIVE NOW</span>
                            <div>
                                <strong>Foundation server release</strong>
                                <p>Self-hosted server with SQLite WAL, web Markdown workspace, 2D visual graph view, member path permissions matrix, note version rollback, and Docker orchestration.</p>
                                <div class="synkk-roadmap-chips">
                                    <span>Docker Compose</span>
                                    <span>SQLite WAL Engine</span>
                                    <span>Markdown Workspace</span>
                                    <span>2D Graph Canvas</span>
                                    <span>Permissions Matrix</span>
                                </div>
                            </div>
                        </li>
                        <li class="is-live">
                            <span class="synkk-roadmap-status"><span class="synkk-beacon" aria-hidden="true"><i></i></span>SHIPPED</span>
                            <div>
                                <strong>Safety and collaboration</strong>
                                <p>Character-level CRDT multiplayer editing directly in Obsidian and web, and an in-editor visual conflict sandbox for side-by-side 3-way note reconciliation.</p>
                                <div class="synkk-roadmap-chips">
                                    <span>CRDT Multiplayer</span>
                                    <span>3-Way Diff Sandbox</span>
                                    <span>Side-by-Side Visuals</span>
                                    <span>Real-time Relays</span>
                                </div>
                            </div>
                        </li>
                        <li class="is-live">
                            <span class="synkk-roadmap-status"><span class="synkk-beacon" aria-hidden="true"><i></i></span>SHIPPED</span>
                            <div>
                                <strong>Selective and private transport</strong>
                                <p>On-demand ghost files for large media attachments, client-side zero-knowledge End-to-End Encryption (E2EE), native mobile background sync relays, and 2-second QR pairing.</p>
                                <div class="synkk-roadmap-chips">
                                    <span>Zero-Knowledge E2EE</span>
                                    <span>Mobile Ghost Files</span>
                                    <span>2s QR Pairing</span>
                                    <span>Background Sync</span>
                                </div>
                            </div>
                        </li>
                        <li class="is-live">
                            <span class="synkk-roadmap-status"><span class="synkk-beacon" aria-hidden="true"><i></i></span>SHIPPED</span>
                            <div>
                                <strong>Agentic Knowledge Graph &amp; RAG Server</strong>
                                <p>Self-hosted vector embeddings, hybrid semantic search, [[wikilink]] graph traversal, and private local LLM copilots querying your vault with zero cloud leakage.</p>
                                <div class="synkk-roadmap-chips">
                                    <span>Vector Embeddings</span>
                                    <span>Semantic Search</span>
                                    <span>Local RAG</span>
                                    <span>Private AI</span>
                                </div>
                            </div>
                        </li>
                        <li class="is-next">
                            <span class="synkk-roadmap-status"><span class="synkk-beacon synkk-beacon--amber" aria-hidden="true"><i></i></span>NEXT UP</span>
                            <div>
                                <strong>Autonomous Note Agents &amp; Visual Canvas</strong>
                                <p>Autonomous background research agents synthesizing new notes, periodic health audits, and visual Obsidian .canvas synthesis.</p>
                                <div class="synkk-roadmap-chips">
                                    <span>Note Agents</span>
                                    <span>Visual Canvas</span>
                                    <span>Health Audits</span>
                                    <span>Autonomous AI</span>
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
                            <p>The Obsidian plugin v1.0.0 is public on GitHub. The Synkk web product currently provides authenticated sync, the editor, Graph View, member path permissions, versions, and restores.</p>
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
                            <p>Not yet. CRDT collaboration and the visual conflict sandbox are the next public milestone after launch.</p>
                        </div>
                    </details>
                    <details>
                        <summary>
                            <span>04</span>When does the $79 commercial license checkout open?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>After the public server package, Lemon Squeezy and AppSumo checkout flows, and license activation screens are verified end to end. Until then, the page does not accept payment.</p>
                        </div>
                    </details>
                    <details>
                        <summary>
                            <span>05</span>Can I self-host Synkk?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>Yes. Synkk Community is 100% free and open-source on GitHub. You can self-host using single-command Docker Compose on your own VPS or homelab hardware.</p>
                        </div>
                    </details>
                    <details>
                        <summary>
                            <span>06</span>What happens when two devices edit the same note?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>Synkk features an in-editor visual 3-way diff sandbox for side-by-side note reconciliation, as well as real-time character-level CRDT multiplayer synchronization across web and Obsidian.</p>
                        </div>
                    </details>
                    <details>
                        <summary>
                            <span>07</span>Can I sync only selected folders?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>Yes. The plugin supports selective folder and configuration rules, plus on-demand ghost files for large media attachments so mobile devices stay lean and fast.</p>
                        </div>
                    </details>
                    <details>
                        <summary>
                            <span>08</span>Does Synkk read my private vault?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>Synkk only receives the files and paths your configured device and team permissions allow. With client-side zero-knowledge E2EE (WebCrypto AES-256-GCM), all notes are encrypted on your device and the server only ever sees opaque ciphertext.</p>
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
                            <span>v1.0.0 Public Release · GitHub Ready</span>
                        </div>
                        <p class="synkk-eyebrow">Your next chapter</p>
                        <h2 id="final-heading">Install the Obsidian plugin.</h2>
                        <p>Download v1.0.0 from GitHub, then follow the setup guide to connect it to Synkk.</p>
                        <div class="synkk-final-cta__actions">
                            <a href="{{ $pluginReleaseUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--accent">Download v1.0.0 <span aria-hidden="true">↗</span></a>
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
                            <div class="synkk-code-line"><span class="synkk-prompt">$</span> <span class="synkk-cmd">docker pull ghcr.io/synkk/server:latest</span></div>
                            <div class="synkk-code-line"><span class="synkk-prompt">$</span> <span class="synkk-cmd">docker run -d -p 8000:8000 -v ~/vaults:/vaults synkk/server</span></div>
                            <div class="synkk-code-output"><span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span> Synkk vault server online at http://127.0.0.1:8000</div>
                            <div class="synkk-code-line synkk-code-line--comment"># Pair Obsidian plugin with token: synkk_live_sec_89f...</div>
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
                        <span>Sync Engine v1.0.0 · Local-First Architecture · Systems Operational</span>
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
                        <a href="{{ route('public.docs') }}">Documentation</a>
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
