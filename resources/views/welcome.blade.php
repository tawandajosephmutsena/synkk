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
                            <a href="#pricing">Pricing <span>04</span></a>
                            <a href="#roadmap">Roadmap <span>05</span></a>
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

            <section id="product-proof" class="synkk-product-proof synkk-viewport-section" aria-labelledby="product-proof-heading">
                <div class="synkk-shell">
                    @php
                        $surfaces = [
                            ['key' => 'overview', 'label' => 'Overview', 'heading' => 'See the vault at a glance.', 'description' => 'Vault health, recent sync activity, notes, storage, and the connected fleet in one place.', 'benefits' => ['Live health score', 'Storage and file counts'], 'image' => 'dashboard-overview.webp', 'alt' => 'The real Synkk dashboard showing vaults, sync events, notes, and activity', 'width' => 2300, 'height' => 1294],
                            ['key' => 'write', 'label' => 'Markdown editor', 'heading' => 'Stay in the note.', 'description' => 'Use the Markdown workspace for outline, preview, version awareness, and focused editing.', 'benefits' => ['Split source and preview', 'Version-aware saves'], 'image' => 'editor-full.webp', 'alt' => 'The real Synkk Markdown editor showing note navigation, source, outline, and split preview', 'width' => 1280, 'height' => 720],
                            ['key' => 'connect', 'label' => 'Graph view', 'heading' => 'Follow the thinking.', 'description' => 'Inspect linked notes, search the graph, then open an accessible note in context.', 'benefits' => ['Interactive link map', 'Searchable note index'], 'image' => 'graph-full.webp', 'alt' => 'The real Synkk Graph View showing connected notes and an accessible note index', 'width' => 1280, 'height' => 720],
                            ['key' => 'control', 'label' => 'Permissions', 'heading' => 'Set the boundary before you share.', 'description' => 'Use member defaults and granular path rules to decide what stays read-only or hidden.', 'benefits' => ['Per-path permissions', 'Read, write, or hidden'], 'image' => 'permissions-full.webp', 'alt' => 'The real Synkk Permissions Matrix showing default access, path-rule inheritance, hidden paths, and Add Path Rule controls', 'width' => 1280, 'height' => 720],
                        ];
                    @endphp
                    <div class="synkk-surfaces" data-surface-showcase data-active-surface="overview">
                        <div class="synkk-surfaces__copy">
                            <header class="synkk-surfaces__heading">
                                <p class="synkk-eyebrow">02 / A closer look</p>
                                <h2 id="product-proof-heading">Four working surfaces. <span>One vault.</span></h2>
                                <p>Write, connect, and stay in control. A different perspective on the same notes.</p>
                            </header>

                            <div class="synkk-surface-tabs" role="tablist" aria-label="Explore Synkk surfaces">
                                @foreach ($surfaces as $surface)
                                    <button type="button" role="tab" id="surface-tab-{{ $surface['key'] }}" data-surface-tab="{{ $surface['key'] }}" aria-controls="surface-copy-{{ $surface['key'] }} surface-image-{{ $surface['key'] }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}"><span>0{{ $loop->iteration }}</span>{{ $surface['label'] }}<i aria-hidden="true">↗</i></button>
                                @endforeach
                            </div>

                            <div class="synkk-surfaces__details">
                                @foreach ($surfaces as $surface)
                                    <div id="surface-copy-{{ $surface['key'] }}" data-surface-copy="{{ $surface['key'] }}" role="tabpanel" aria-labelledby="surface-tab-{{ $surface['key'] }}" tabindex="0" @if (! $loop->first) hidden @endif>
                                        <h3>{{ $surface['heading'] }}</h3>
                                        <p>{{ $surface['description'] }}</p>
                                        <ul>@foreach ($surface['benefits'] as $benefit)<li>{{ $benefit }}</li>@endforeach</ul>
                                    </div>
                                @endforeach
                            </div>

                            <div class="synkk-surfaces__actions">
                                @auth
                                    <a href="{{ $dashboardUrl }}" class="synkk-button synkk-button--ink">Open your workspace <span aria-hidden="true">↗</span></a>
                                @else
                                    @if (Route::has('register'))
                                        <a href="{{ route('register') }}" class="synkk-button synkk-button--ink">Create workspace <span aria-hidden="true">↗</span></a>
                                    @endif
                                @endauth
                                <a href="{{ route('docs.redirect') }}" class="synkk-surface-docs">Explore the docs <span aria-hidden="true">→</span></a>
                            </div>
                            <p class="synkk-surfaces__principle"><span aria-hidden="true">✳</span> Your files. Your team. Your infrastructure.</p>
                        </div>

                        <div class="synkk-surfaces__stage">
                            <div class="synkk-surface-glow" aria-hidden="true"></div>
                            <div class="synkk-surface-orbit synkk-surface-orbit--one" aria-hidden="true"><i></i></div>
                            <div class="synkk-surface-orbit synkk-surface-orbit--two" aria-hidden="true"><i></i></div>
                            <div class="synkk-surface-images">
                                @foreach ($surfaces as $surface)
                                    <figure id="surface-image-{{ $surface['key'] }}" data-surface-image="{{ $surface['key'] }}" aria-labelledby="surface-tab-{{ $surface['key'] }}" @if (! $loop->first) hidden @endif>
                                        <div class="synkk-surface-window">
                                            <div class="synkk-window-bar" aria-hidden="true"><span><i></i><i></i><i></i></span><small>synkk / {{ $surface['key'] }}</small><span>↗</span></div>
                                            <img src="/images/showcase/{{ $surface['image'] }}" alt="{{ $surface['alt'] }}" width="{{ $surface['width'] }}" height="{{ $surface['height'] }}" loading="lazy" decoding="async">
                                        </div>
                                    </figure>
                                @endforeach
                            </div>
                            <div class="synkk-surface-seal" aria-hidden="true"><svg viewBox="0 0 60 60" fill="none"><path d="M18 20a16 16 0 0 1 27 8m0-10v11H34M42 40a16 16 0 0 1-27-8m0 10V31h11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
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

            <section id="pricing" class="synkk-pricing synkk-viewport-section" aria-labelledby="pricing-heading">
                <div class="synkk-shell">
                    <header class="synkk-section-heading">
                        <div>
                            <p class="synkk-eyebrow">05 / Simple pricing</p>
                            <h2 id="pricing-heading">Get the plugin free. Pro follows at $49.</h2>
                        </div>
                        <p>Plugin v1.0.0 is public on GitHub. Pro checkout opens after the server release and license flow pass their launch checks.</p>
                    </header>

                    <div class="synkk-pricing-grid">
                        <article>
                            <div class="synkk-price-heading"><span>OBSIDIAN PLUGIN</span><p><strong>$0</strong><small>public release</small></p></div>
                            <h3>Install the public plugin today.</h3>
                            <ul><li>Version 1.0.0 on GitHub</li><li>Startup, scheduled, and manual sync</li><li>Selective folder and config rules</li><li>Community release notes</li></ul>
                            <a href="{{ $pluginReleaseUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--quiet">Download v1.0.0 <span aria-hidden="true">↗</span></a>
                        </article>

                        <article class="is-featured">
                            <div class="synkk-price-heading"><span>PRO · LAUNCH LICENSE</span><p><strong>$49</strong><small>one-time</small></p></div>
                            <h3>Own the commercial server release.</h3>
                            <ul><li>Perpetual access to the purchased version</li><li>One year of product updates</li><li>One year of direct support</li><li>License delivery through Lemon Squeezy</li></ul>
                            @if ($storeReady)
                                <a href="{{ $storeUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--accent">Get Synkk Pro <span aria-hidden="true">↗</span></a>
                            @else
                                <button type="button" class="synkk-button synkk-button--pending" disabled>Checkout opens after launch checks</button>
                            @endif
                        </article>

                        <article class="synkk-pricing-grid__team">
                            <div class="synkk-price-heading"><span>SELF-HOSTED TEAM</span><p><strong>Team</strong><small>self-hosted</small></p></div>
                            <h3>Plan the server rollout around your team.</h3>
                            <ul><li>Run Synkk in your own environment</li><li>Review member and path access</li><li>Follow the deployment documentation</li><li>Track launch gates in public</li></ul>
                            <a href="{{ route('docs.redirect') }}" class="synkk-button synkk-button--quiet">Read setup docs <span aria-hidden="true">→</span></a>
                        </article>
                    </div>

                    <p class="synkk-launch-channels"><span>LAUNCH CHANNELS</span> GitHub hosts the public plugin. Lemon Squeezy opens after checkout verification. AppSumo follows the full public launch.</p>
                </div>
            </section>

            <section id="roadmap" class="synkk-roadmap synkk-viewport-section" aria-labelledby="roadmap-heading">
                <div class="synkk-shell synkk-roadmap__grid">
                    <header class="synkk-roadmap__intro">
                        <p class="synkk-eyebrow">06 / Built in the open</p>
                        <h2 id="roadmap-heading">Plugin now. Server release next.</h2>
                        <p>The public plugin is downloadable today. The rows separate what is live, what must clear launch, and what follows.</p>
                        <a href="{{ $pluginUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--paper">See the public repository <span aria-hidden="true">↗</span></a>
                    </header>

                    <ol class="synkk-roadmap-list">
                        <li class="is-live"><span>LIVE</span><div><strong>Obsidian plugin v1.0.0</strong><p>The installable plugin release is public on GitHub now.</p></div></li>
                        <li><span>LAUNCH GATE</span><div><strong>Foundation server release</strong><p>Public packaging, clean-install proof, verified checkout, and license activation UI.</p></div></li>
                        <li><span>NEXT</span><div><strong>Safety and collaboration</strong><p>Deletion guard release, local snapshots, CRDT convergence, and a visual conflict sandbox.</p></div></li>
                        <li><span>PLANNED</span><div><strong>Selective and private transport</strong><p>Ghost files, folder profiles, QR pairing, client-side encryption, relays, and background sync.</p></div></li>
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
                        <summary><span>01</span>What can I install today?</summary>
                        <p>The Obsidian plugin v1.0.0 is public on GitHub. The Synkk web product currently provides authenticated sync, the editor, Graph View, member path permissions, versions, and restores.</p>
                    </details>
                    <details>
                        <summary><span>02</span>Does Synkk replace my Markdown files?</summary>
                        <p>No. Your vault remains portable files and folders. Synkk adds controlled sync, access, and recovery around the notes you own.</p>
                    </details>
                    <details>
                        <summary><span>03</span>Is character-level CRDT sync available?</summary>
                        <p>Not yet. CRDT collaboration and the visual conflict sandbox are the next public milestone after launch.</p>
                    </details>
                    <details>
                        <summary><span>04</span>When does the $49 Pro license launch?</summary>
                        <p>After the public server package, Lemon Squeezy checkout, and license activation screen are verified end to end. Until then, the page does not accept payment.</p>
                    </details>
                    <details>
                        <summary><span>05</span>Can I self-host Synkk?</summary>
                        <p>Yes. The web product is designed for a server you control. The documentation includes the current environment requirements, Docker direction, storage locations, and the production checks still required before the server package is called ready.</p>
                    </details>
                    <details>
                        <summary><span>06</span>What happens when two devices edit the same note?</summary>
                        <p>The Foundation release preserves the stale upload as a conflict copy instead of silently overwriting the current file. Character-level CRDT merging and an in-editor conflict sandbox are planned after launch.</p>
                    </details>
                    <details>
                        <summary><span>07</span>Can I sync only selected folders?</summary>
                        <p>Yes. The plugin supports selective folder and configuration rules today. On-demand ghost files for large attachments are a later roadmap milestone for mobile storage control.</p>
                    </details>
                    <details>
                        <summary><span>08</span>Does Synkk read my private vault?</summary>
                        <p>Synkk only receives the files and paths your configured device and team permissions allow. Client-side zero-knowledge encryption is future work; review the current access and hashing model in the documentation before production use.</p>
                    </details>
                </div>
            </section>

            <section class="synkk-final-cta synkk-shell" aria-labelledby="final-heading">
                <div>
                    <p class="synkk-eyebrow">Your next chapter</p>
                    <h2 id="final-heading">Install the Obsidian plugin.</h2>
                    <p>Download v1.0.0 from GitHub, then follow the setup guide to connect it to Synkk.</p>
                </div>
                <div class="synkk-final-cta__actions">
                    <a href="{{ $pluginReleaseUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--ink">Download v1.0.0 <span aria-hidden="true">↗</span></a>
                    <a href="{{ route('docs.redirect') }}" class="synkk-button synkk-button--paper">Read the docs <span aria-hidden="true">→</span></a>
                </div>
            </section>
        </main>

        <footer class="synkk-footer">
            <div class="synkk-shell synkk-footer__top">
                <div><img src="/images/synkk-logo.svg" alt="Synkk — Obsidian everywhere" width="689" height="270" loading="lazy"><p>Portable notes. Visible safety. Infrastructure you control.</p></div>
                <nav aria-label="Footer navigation"><a href="#product">Product</a><a href="#workflow">How it works</a><a href="#pricing">Pricing</a><a href="#roadmap">Roadmap</a><a href="#faq">FAQ</a><a href="{{ route('docs.redirect') }}">Documentation</a><a href="{{ $pluginUrl }}" target="_blank" rel="noopener noreferrer">GitHub ↗</a></nav>
            </div>
            <div class="synkk-shell synkk-footer__bottom"><span>© {{ now()->year }} Synkk</span><span>Obsidian everywhere</span><span>Built in public</span></div>
        </footer>
    </body>
</html>
