<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Synkk — Obsidian everywhere</title>
        <meta name="description" content="Self-hosted Obsidian sync with a real Markdown workspace, a visual vault graph, trusted devices, path permissions, verified changes, and recoverable versions.">
        <meta name="theme-color" content="#f4f2ec">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts
        @vite(['resources/css/landing.css', 'resources/js/app.js'])
    </head>
    <body class="synkk-site font-sans antialiased">
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
                    <div class="synkk-data-rail synkk-data-rail--hero" aria-hidden="true">
                        <span class="synkk-data-rail__line synkk-data-rail__line--horizontal"></span>
                        <span class="synkk-data-rail__line synkk-data-rail__line--vertical"></span>
                        <i class="synkk-data-packet synkk-data-packet--one"></i>
                        <i class="synkk-data-packet synkk-data-packet--two"></i>
                        <i class="synkk-data-packet synkk-data-packet--three"></i>
                    </div>
                    <div class="synkk-hero__copy">
                        <p class="synkk-eyebrow synkk-reveal synkk-reveal--one">Self-hosted sync for Obsidian</p>
                        <h1 id="hero-heading" class="synkk-reveal synkk-reveal--two"><span class="synkk-hero__line">Your vault.</span><span class="synkk-hero__line synkk-hero__line--accent">On every device.</span></h1>
                        <p class="synkk-hero__lede synkk-reveal synkk-reveal--three">Synkk keeps Markdown, permissions, versions, and trusted devices together on infrastructure you control.</p>
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

                    <div class="synkk-hero__media synkk-reveal synkk-reveal--three">
                        <div class="synkk-hero-window">
                            <div class="synkk-hero-window__bar">
                                <span><i aria-hidden="true"></i> Real product capture</span>
                                <strong>Editor → Graph</strong>
                            </div>
                            <video autoplay muted loop playsinline preload="metadata" poster="/images/showcase/editor-full.webp" aria-label="A moving tour of the real Synkk Markdown Editor and Graph View">
                                <source src="/videos/synkk-ui-reel.mp4" type="video/mp4">
                            </video>
                        </div>
                        <div class="synkk-hero-status" aria-label="Revision status shown in the product capture">
                            <span>Current revision</span>
                            <strong><i aria-hidden="true"></i> v78</strong>
                            <small>Latest sync state</small>
                        </div>
                        <img src="/images/character/synkk-mascot-animated.svg" alt="Synkk's animated octopus explorer beside the real product capture" width="800" height="700" class="synkk-hero-character" fetchpriority="high" decoding="async">
                    </div>
                </div>
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
                        <p class="synkk-eyebrow">Inside Synkk</p>
                        <h2 id="product-heading">Edit the note. Follow the graph.</h2>
                    </div>
                    <p>The same vault opens as a full Markdown workspace or a live map of the notes and links you can access.</p>
                </header>

                <div class="synkk-product-viewer" data-product-workbench>
                    <div class="synkk-product-tabs" role="tablist" aria-label="Real Synkk product views">
                        <button type="button" role="tab" data-product-tab="markdown-editor" aria-selected="true" tabindex="0"><span>01</span> Markdown Editor</button>
                        <button type="button" role="tab" data-product-tab="graph" aria-selected="false" tabindex="-1"><span>02</span> Graph View</button>
                        <p><i aria-hidden="true"></i> Real product capture</p>
                    </div>

                    <div class="synkk-product-screen">
                        <figure data-product-view="markdown-editor" role="tabpanel" aria-label="Markdown Editor">
                            <img src="/images/showcase/editor-full.webp" alt="The actual Synkk vault page showing the Markdown editor, note navigator, source with line numbers, and split preview" width="1280" height="720" loading="eager" decoding="async">
                            <figcaption><strong>A complete writing workspace.</strong><span>Formatting tools, document outline, minimap, split preview, sharing, versions, and save state.</span></figcaption>
                        </figure>
                        <figure data-product-view="graph" role="tabpanel" aria-label="Graph View" hidden>
                            <img src="/images/showcase/graph-full.webp" alt="The actual Synkk vault graph showing connected notes, filtering, and an accessible note index" width="1280" height="720" loading="lazy" decoding="async">
                            <figcaption><strong>The same vault, mapped.</strong><span>Search, zoom, inspect links, and open any accessible note directly in the editor.</span></figcaption>
                        </figure>
                    </div>
                </div>
            </section>

            <section id="product-proof" class="synkk-product-proof synkk-viewport-section" aria-labelledby="product-proof-heading">
                <div class="synkk-data-rail synkk-data-rail--proof" aria-hidden="true">
                    <span class="synkk-data-rail__line synkk-data-rail__line--horizontal"></span>
                    <span class="synkk-data-rail__line synkk-data-rail__line--vertical"></span>
                    <i class="synkk-data-packet synkk-data-packet--one"></i>
                    <i class="synkk-data-packet synkk-data-packet--two"></i>
                    <i class="synkk-data-packet synkk-data-packet--three"></i>
                </div>
                <div class="synkk-shell">
                    <header class="synkk-section-heading">
                        <div>
                            <p class="synkk-eyebrow">Real working surfaces</p>
                            <h2 id="product-proof-heading">Four working surfaces. One vault.</h2>
                        </div>
                        <p>These are real Synkk views: monitor vault health, write Markdown, trace the graph, and manage the trusted devices around it.</p>
                    </header>

                    <div class="synkk-product-proof__grid">
                        <article>
                            <figure><img src="/images/showcase/dashboard-overview.webp" alt="The real Synkk dashboard showing vaults, sync events, notes, and activity" width="2300" height="1294" loading="lazy" decoding="async"></figure>
                            <div><span>01 · OVERVIEW</span><h3>See the vault at a glance.</h3><p>Vault health, recent sync activity, notes, and the connected fleet in one place.</p></div>
                        </article>
                        <article>
                            <figure><img src="/images/showcase/editor-full.webp" alt="The real Synkk Markdown editor showing note navigation, source, outline, and split preview" width="1280" height="720" loading="lazy" decoding="async"></figure>
                            <div><span>02 · WRITE</span><h3>Stay in the note.</h3><p>Use the Markdown workspace for outline, preview, version awareness, and focused editing.</p></div>
                        </article>
                        <article>
                            <figure><img src="/images/showcase/graph-full.webp" alt="The real Synkk Graph View showing connected notes and an accessible note index" width="1280" height="720" loading="lazy" decoding="async"></figure>
                            <div><span>03 · CONNECT</span><h3>Follow the thinking.</h3><p>Inspect linked notes, search the graph, then open an accessible note in context.</p></div>
                        </article>
                        <article>
                            <figure><img src="/images/showcase/permissions-full.webp" alt="The real Synkk Permissions Matrix showing default access, path-rule inheritance, hidden paths, and Add Path Rule controls" width="1280" height="720" loading="lazy" decoding="async"></figure>
                            <div><span>04 · CONTROL</span><h3>Set the boundary before you share.</h3><p>Use member defaults and granular path rules to decide what stays read-only or hidden.</p></div>
                        </article>
                    </div>
                </div>
            </section>

            <section id="workflow" class="synkk-workflow synkk-viewport-section" aria-labelledby="workflow-heading">
                <div class="synkk-data-rail synkk-data-rail--dark synkk-data-rail--workflow" aria-hidden="true">
                    <span class="synkk-data-rail__line synkk-data-rail__line--horizontal"></span>
                    <span class="synkk-data-rail__line synkk-data-rail__line--vertical"></span>
                    <i class="synkk-data-packet synkk-data-packet--one"></i>
                    <i class="synkk-data-packet synkk-data-packet--two"></i>
                    <i class="synkk-data-packet synkk-data-packet--three"></i>
                </div>
                <div class="synkk-shell">
                    <header class="synkk-section-heading synkk-section-heading--inverse">
                        <div>
                            <p class="synkk-eyebrow">A change, end to end</p>
                            <h2 id="workflow-heading">Checked before it reaches the vault.</h2>
                        </div>
                        <p>Synkk authenticates the device and path, fingerprints uploaded content, and uses the supplied loaded revision to detect stale edits.</p>
                    </header>

                    <ol class="synkk-workflow-grid">
                        <li>
                            <span class="synkk-step-number">01</span>
                            <div class="synkk-step-icon" aria-hidden="true">MD</div>
                            <h3>Edit locally</h3>
                            <p>Work in Obsidian or the web editor. Your vault stays a folder of portable Markdown files.</p>
                            <small>Local-first authoring</small>
                        </li>
                        <li>
                            <span class="synkk-step-number">02</span>
                            <div class="synkk-step-icon" aria-hidden="true">✓</div>
                            <h3>Verify the change</h3>
                            <p>Device access is checked, content gets a SHA-256 fingerprint, and a supplied base revision can reject stale edits.</p>
                            <small>Guarded updates</small>
                        </li>
                        <li>
                            <span class="synkk-step-number">03</span>
                            <div class="synkk-step-icon" aria-hidden="true">↗</div>
                            <img src="/images/character/synkk-mascot-animated.svg" alt="" width="800" height="700" class="synkk-workflow-mascot" loading="lazy" decoding="async">
                            <h3>Sync trusted devices</h3>
                            <p>Devices pull on startup, on their configured interval, or whenever you run a manual sync.</p>
                            <small>Predictable delivery</small>
                        </li>
                    </ol>
                </div>
            </section>

            <section id="safety" class="synkk-safety synkk-viewport-section synkk-shell" aria-labelledby="safety-heading">
                <header class="synkk-section-heading">
                    <div>
                        <p class="synkk-eyebrow">Access and recovery</p>
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
                            <p class="synkk-eyebrow">Simple pricing</p>
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
                <div class="synkk-data-rail synkk-data-rail--dark synkk-data-rail--roadmap" aria-hidden="true">
                    <span class="synkk-data-rail__line synkk-data-rail__line--horizontal"></span>
                    <span class="synkk-data-rail__line synkk-data-rail__line--vertical"></span>
                    <i class="synkk-data-packet synkk-data-packet--one"></i>
                    <i class="synkk-data-packet synkk-data-packet--two"></i>
                    <i class="synkk-data-packet synkk-data-packet--three"></i>
                </div>
                <div class="synkk-shell synkk-roadmap__grid">
                    <header class="synkk-roadmap__intro">
                        <p class="synkk-eyebrow">Public roadmap</p>
                        <h2 id="roadmap-heading">Plugin now. Server release next.</h2>
                        <p>The public plugin is downloadable today. The rows separate what is live, what must clear launch, and what follows.</p>
                        <a href="{{ $pluginUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--paper">See the public repository <span aria-hidden="true">↗</span></a>
                        <img src="/images/character/synkk-mascot-animated.svg" alt="" width="800" height="700" class="synkk-roadmap-mascot" loading="lazy" decoding="async">
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
                        <p class="synkk-eyebrow">Before you install</p>
                        <h2 id="faq-heading">What is public today.</h2>
                    </div>
                    <p>The public plugin, current product, and future roadmap are labelled separately.</p>
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
                </div>
            </section>

            <section class="synkk-final-cta synkk-shell" aria-labelledby="final-heading">
                <div class="synkk-data-rail synkk-data-rail--dark synkk-data-rail--final" aria-hidden="true">
                    <span class="synkk-data-rail__line synkk-data-rail__line--horizontal"></span>
                    <span class="synkk-data-rail__line synkk-data-rail__line--vertical"></span>
                    <i class="synkk-data-packet synkk-data-packet--one"></i>
                    <i class="synkk-data-packet synkk-data-packet--two"></i>
                </div>
                <div>
                    <p class="synkk-eyebrow">Get the beta</p>
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
