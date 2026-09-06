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
                    <a href="#pricing">Pricing</a>
                    <a href="#roadmap">Roadmap</a>
                    <a href="{{ route('docs.redirect') }}">Docs</a>
                </nav>

                <div class="synkk-header__actions">
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
                            <a href="#pricing">Pricing <span>05</span></a>
                            <a href="#roadmap">Roadmap <span>06</span></a>
                            <a href="{{ route('docs.redirect') }}">Documentation <span>↗</span></a>
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
                                    <a href="{{ route('docs.redirect') }}" class="synkk-button synkk-button--quiet">Read architecture <span aria-hidden="true">→</span></a>
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
                                    <a href="{{ route('docs.redirect') }}" class="synkk-button synkk-button--quiet">Read editor docs <span aria-hidden="true">→</span></a>
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
                                    <a href="{{ route('docs.redirect') }}" class="synkk-button synkk-button--quiet">Read graph docs <span aria-hidden="true">→</span></a>
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
                                    <a href="{{ route('docs.redirect') }}" class="synkk-button synkk-button--quiet">Permissions guide <span aria-hidden="true">→</span></a>
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
                                    <a href="{{ route('docs.redirect') }}" class="synkk-button synkk-button--quiet">Safety documentation <span aria-hidden="true">→</span></a>
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
                                    <a href="{{ route('docs.redirect') }}" class="synkk-button synkk-button--ink">Read self-hosting docs <span aria-hidden="true">→</span></a>
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
                                            <strong><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> $65 Total</strong>
                                            <span class="synkk-cell-sub">Buy server license once. Unlimited seats, unlimited devices, zero subscriptions.</span>
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

            <section id="pricing" class="synkk-pricing synkk-viewport-section" aria-labelledby="pricing-heading">
                <div class="synkk-shell">
                    <header class="synkk-section-heading">
                        <div>
                            <p class="synkk-eyebrow">06 / Zero Seat Tax</p>
                            <h2 id="pricing-heading">Own your team brain. No recurring seat tax.</h2>
                        </div>
                        <p>Obsidian Sync charges $48–$96/year per seat for a closed cloud sync. Synkk gives your entire team local-first speed, path ACLs, and 100% data sovereignty on your private server. Pay once, own it forever.</p>
                    </header>

                    <div class="synkk-pricing-grid">
                        <article>
                            <div class="synkk-price-heading">
                                <span>1-YEAR UPDATE LICENSE</span>
                                <p><strong>$45</strong><small>1-year update license</small></p>
                            </div>
                            <h3>Self-host your personal knowledge engine.</h3>
                            <ul>
                                <li>1 year of software updates &amp; new feature releases</li>
                                <li>Perpetual access to the purchased version forever</li>
                                <li>Self-host on your own infrastructure or Docker</li>
                                <li>Local-first sync with instant QR mobile pairing</li>
                                <li>Web Markdown workspace &amp; 2D interactive graph</li>
                                <li>Atomic Safety Shield (aborts on &gt;10% mass wipe)</li>
                                <li>Unlimited vaults, notes, and local devices</li>
                                <li>Obsidian plugin v1.0.0 integration included</li>
                            </ul>
                            @if ($storeReady)
                                <a href="{{ $storeUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--ink">Get 1-Year License <span aria-hidden="true">↗</span></a>
                            @else
                                <button type="button" class="synkk-button synkk-button--pending" disabled>Checkout opens after launch checks</button>
                            @endif
                        </article>

                        <article class="is-featured">
                            <div class="synkk-price-featured-badge">
                                <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                                <span>LIFETIME LICENSE · SOVEREIGN TEAM BRAIN</span>
                            </div>
                            <div class="synkk-price-heading">
                                <span>LIFETIME LICENSE</span>
                                <p><strong>$65</strong><small>lifetime license · pay once, own forever</small></p>
                            </div>
                            <h3>The complete team knowledge base. Zero seat fees.</h3>
                            <ul>
                                <li>Lifetime software updates — never pay a renewal fee</li>
                                <li>Zero recurring per-seat fees — save $1,440/yr vs SaaS</li>
                                <li>Granular Path-Level ACLs (Inbox vs Client vs Internal)</li>
                                <li>In-app DLP Secret Scanner (blocks leaked API keys)</li>
                                <li>Multi-member Web Workspace &amp; full interactive graph</li>
                                <li>1-Click Quick Connect QR code mobile onboarding</li>
                                <li>Priority access to CRDT live collaboration &amp; E2EE betas</li>
                                <li>Direct priority support from core maintainers</li>
                            </ul>
                            @if ($storeReady)
                                <a href="{{ $storeUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--accent">Get Lifetime License <span aria-hidden="true">↗</span></a>
                            @else
                                <button type="button" class="synkk-button synkk-button--pending" disabled>Checkout opens after launch checks</button>
                            @endif
                        </article>

                        <article class="synkk-pricing-grid__team">
                            <div class="synkk-price-heading">
                                <span>ENTERPRISE &amp; TEAMS</span>
                                <p><strong>Enterprise</strong><small>teams &amp; organizations</small></p>
                            </div>
                            <h3>Deploy Synkk across your team or company.</h3>
                            <ul>
                                <li>Custom Kubernetes &amp; air-gapped Docker deployments</li>
                                <li>Enterprise fleet governance &amp; 1-click device remote wipe</li>
                                <li>IP subnet restriction &amp; read-only contractor tokens</li>
                                <li>Audit trail logging &amp; SOC2 compliance assistance</li>
                                <li>Dedicated support channel with core maintainers</li>
                                <li>Custom SLA, invoice billing, and security audit review</li>
                            </ul>
                            <a href="https://book-it.ottomate.space" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--accent">Book a meeting <span aria-hidden="true">↗</span></a>
                        </article>
                    </div>

                    <div class="synkk-pricing-trust">
                        <div class="synkk-trust-item">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                            <span>100% Self-Hosted &amp; Local-First</span>
                        </div>
                        <div class="synkk-trust-item">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
                            <span>Instant Software License Activation</span>
                        </div>
                        <div class="synkk-trust-item">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                            <span>No Subscription Trap · Pay Once</span>
                        </div>
                        <div class="synkk-trust-item">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v1h8v-1zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 16v-1a4.978 4.978 0 00-1.552-3.619A6.974 6.974 0 0118 15v1h-2zM4 16v-1c0-.853.21-1.657.58-2.368A4.978 4.978 0 003 15v1h1z"/></svg>
                            <span>Obsidian Plugin Always Free</span>
                        </div>
                    </div>

                    <p class="synkk-launch-channels"><span>LAUNCH CHANNELS</span> GitHub hosts the public plugin. Lemon Squeezy manages license checkout. Enterprise consultations are booked directly at <a href="https://book-it.ottomate.space" target="_blank" rel="noopener noreferrer" class="underline hover:text-zinc-900">book-it.ottomate.space</a>.</p>
                </div>
            </section>

            <section id="roadmap" class="synkk-roadmap synkk-viewport-section" aria-labelledby="roadmap-heading">
                <div class="synkk-shell synkk-roadmap__grid">
                    <header class="synkk-roadmap__intro">
                        <p class="synkk-eyebrow">06 / Built in the open</p>
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
                        <li class="is-next">
                            <span class="synkk-roadmap-status"><span class="synkk-beacon synkk-beacon--amber" aria-hidden="true"><i></i></span>NEXT UP</span>
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
                        <li class="is-planned">
                            <span class="synkk-roadmap-status"><span class="synkk-beacon synkk-beacon--slate" aria-hidden="true"><i></i></span>PLANNED</span>
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
                    </ol>
                </div>
            </section>

            <section id="faq" class="synkk-faq synkk-viewport-section synkk-shell" aria-labelledby="faq-heading">
                <header class="synkk-section-heading">
                    <div>
                        <p class="synkk-eyebrow">07 / Before you install</p>
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
                            <span>04</span>When does the $45 / $65 license checkout open?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>After the public server package, Lemon Squeezy checkout, and license activation screen are verified end to end. Until then, the page does not accept payment.</p>
                        </div>
                    </details>
                    <details>
                        <summary>
                            <span>05</span>Can I self-host Synkk?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>Yes. The web product is designed for a server you control. The documentation includes the current environment requirements, Docker direction, storage locations, and the production checks still required before the server package is called ready.</p>
                        </div>
                    </details>
                    <details>
                        <summary>
                            <span>06</span>What happens when two devices edit the same note?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>The Foundation release preserves the stale upload as a conflict copy instead of silently overwriting the current file. Character-level CRDT merging and an in-editor conflict sandbox are planned after launch.</p>
                        </div>
                    </details>
                    <details>
                        <summary>
                            <span>07</span>Can I sync only selected folders?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>Yes. The plugin supports selective folder and configuration rules today. On-demand ghost files for large attachments are a later roadmap milestone for mobile storage control.</p>
                        </div>
                    </details>
                    <details>
                        <summary>
                            <span>08</span>Does Synkk read my private vault?
                            <span class="synkk-faq-toggle" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </summary>
                        <div class="synkk-faq-answer">
                            <p>Synkk only receives the files and paths your configured device and team permissions allow. Client-side zero-knowledge encryption is future work; review the current access and hashing model in the documentation before production use.</p>
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
                            <a href="{{ route('docs.redirect') }}" class="synkk-button synkk-button--paper">Read the docs <span aria-hidden="true">→</span></a>
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
                        <a href="#pricing">Pricing</a>
                        <a href="#roadmap">Roadmap</a>
                        <a href="#faq">FAQ</a>
                        <a href="{{ route('docs.redirect') }}">Documentation</a>
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
