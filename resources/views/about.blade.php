<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth dark" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>About &amp; Philosophy — Synkk Obsidian Everywhere</title>
        <meta name="description" content="The founding philosophy, architectural blueprint, and sovereign mission behind Synkk: self-hosted team sync, path permissions, visual conflict safety, and local RAG for Obsidian.">
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
            $pluginReleaseUrl = 'https://github.com/tawandajosephmutsena/synk-obsidian-plugin/releases/tag/1.0.0';
            $userTeam = auth()->check()
                ? (auth()->user()->currentTeam ?? auth()->user()->personalTeam() ?? auth()->user()->teams->first())
                : null;
            $dashboardUrl = $userTeam
                ? route('dashboard', ['current_team' => $userTeam->slug])
                : route('home');
        @endphp

        <a href="#main-content" class="synkk-skip-link">Skip to content</a>
        <div class="synkk-reading-progress" aria-hidden="true"><span></span></div>

        <!-- Primary Header -->
        <header class="synkk-header">
            <div class="synkk-shell synkk-header__inner">
                <a href="{{ route('home') }}" class="synkk-brand" aria-label="Synkk home">
                    <img src="/images/synkk-logo.svg" alt="Synkk — Obsidian everywhere" width="689" height="270" fetchpriority="high">
                </a>

                <nav class="synkk-nav" aria-label="Primary navigation">
                    <a href="{{ route('home') }}#product">Product</a>
                    <a href="{{ route('home') }}#workflow">How it works</a>
                    <a href="{{ route('home') }}#safety">Safety</a>
                    <a href="{{ route('home') }}#comparison">Why Synkk</a>
                    <a href="{{ route('home') }}#pricing">Pricing</a>
                    <a href="{{ route('home') }}#roadmap">Roadmap</a>
                    <a href="{{ route('about') }}" class="font-bold text-emerald-950 dark:text-emerald-300">About</a>
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
                            <a href="{{ route('home') }}#product">Product <span>01</span></a>
                            <a href="{{ route('home') }}#workflow">How it works <span>02</span></a>
                            <a href="{{ route('home') }}#safety">Safety <span>03</span></a>
                            <a href="{{ route('home') }}#comparison">Why Synkk <span>04</span></a>
                            <a href="{{ route('home') }}#pricing">Pricing <span>05</span></a>
                            <a href="{{ route('home') }}#roadmap">Roadmap <span>06</span></a>
                            <a href="{{ route('about') }}">About &amp; Philosophy <span>07</span></a>
                            <a href="{{ route('public.docs') }}">Documentation <span>↗</span></a>
                            @guest
                                <a href="{{ route('login') }}">Log in <span>↗</span></a>
                            @endguest
                        </nav>
                    </details>
                </div>
            </div>
        </header>

        <main id="main-content">
            <!-- Hero / Manifesto -->
            <section class="synkk-hero synkk-shell" aria-labelledby="about-hero-heading">
                <div class="synkk-hero__frame">
                    <div class="synkk-hero__copy">
                        <p class="synkk-eyebrow synkk-reveal synkk-reveal--one">The Synkk Philosophy — The Sovereign PKM Manifesto</p>
                        <h1 id="about-hero-heading" class="synkk-reveal--two">
                            <span class="synkk-hero__line"><span>Thinking is personal.</span></span>
                            <span class="synkk-hero__line synkk-hero__line--accent"><span>Keep it yours.</span><svg viewBox="0 0 100 100" fill="none" aria-hidden="true"><path d="M18 51h64M53 22l29 29-29 29" stroke="currentColor" stroke-width="5"/></svg></span>
                        </h1>
                        <p class="synkk-hero__lede synkk-reveal synkk-reveal--three">
                            Obsidian is celebrated worldwide because it is local, durable, and free from corporate database silos. We built Synkk so teams, founders, and researchers can collaborate with the full power of Obsidian without surrendering data sovereignty to commercial cloud gatekeepers.
                        </p>
                        <div class="synkk-hero__actions synkk-reveal synkk-reveal--four">
                            <a href="#pillars" class="synkk-button synkk-button--accent">Explore the pillars <span aria-hidden="true">↓</span></a>
                            <a href="#manifesto" class="synkk-button synkk-button--paper">Read our manifesto <span aria-hidden="true">→</span></a>
                            <a href="#comparison" class="synkk-button synkk-button--quiet">Tech comparison <span aria-hidden="true">↗</span></a>
                        </div>
                        <ul class="synkk-hero__proof synkk-reveal synkk-reveal--four" aria-label="Synkk Core Guarantees">
                            <li>100% Plain Markdown (.md)</li>
                            <li>Zero Cloud Silos or Lock-In</li>
                            <li>Self-Hosted SQLite WAL Engine</li>
                            <li>Granular Path-Level ACLs</li>
                        </ul>
                    </div>

                    <div class="synkk-hero__media">
                        <div class="synkk-orbit synkk-orbit--outer" aria-hidden="true"><i></i></div>
                        <div class="synkk-orbit synkk-orbit--inner" aria-hidden="true"><i></i></div>
                        <span class="synkk-stage-label">Sovereignty First</span>
                        
                        <div class="synkk-device-chip synkk-device-chip--desktop" aria-hidden="true">
                            <img src="/images/platforms/obsidian.svg" alt="" width="20" height="20">
                            Local-First Vault <span>↗</span>
                        </div>

                        <!-- Floating Mascot Accent -->
                        <div class="absolute -right-4 -top-8 z-30 hidden sm:block pointer-events-none" aria-hidden="true">
                            <img src="/images/character/synkk-diver-premium.webp" alt="" width="110" height="110" class="drop-shadow-2xl opacity-90 hover:opacity-100 transition-opacity">
                        </div>

                        <div class="synkk-laptop" data-hero-layer aria-label="Synkk dashboard overview">
                            <div class="synkk-laptop__screen">
                                <div class="synkk-window-bar" aria-hidden="true">
                                    <span><i></i><i></i><i></i></span>
                                    <small>synkk / manifest.md</small>
                                    <span>↗</span>
                                </div>
                                <img src="/images/showcase/dashboard-overview.webp" alt="Synkk Dashboard Overview" width="2300" height="1294" fetchpriority="high">
                            </div>
                        </div>
                        
                        <div class="synkk-hero-status" data-hero-layer>
                            <span>Core Philosophy</span>
                            <strong><i aria-hidden="true"></i> No Cloud Silos</strong>
                            <small>Plain .md files forever · AES-256-GCM · SQLite WAL</small>
                        </div>
                        
                        <div class="synkk-stage-footer">
                            <span>YOUR HARDWARE. YOUR INTELLECT.</span>
                            <span aria-hidden="true">↙</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- The Problem Space: The Four Broken Paradigms -->
            <section class="synkk-viewport-section synkk-shell" aria-labelledby="paradigms-heading">
                <header class="synkk-section-heading">
                    <div>
                        <p class="synkk-eyebrow">01 / The Problem Space</p>
                        <h2 id="paradigms-heading">Why existing sync solutions break down for teams.</h2>
                    </div>
                    <p>Personal Knowledge Management (PKM) tools are magical for solo thinkers, but collaboration has historically forced a series of painful, risky compromises.</p>
                </header>

                <div class="synkk-paradigms-grid">
                    <article class="synkk-safety-card">
                        <div class="synkk-safety-card__top"><span>PARADIGM 01</span><strong>ALL-OR-NOTHING</strong></div>
                        <h3>The Cost &amp; Privacy Dilemma of Proprietary Sync</h3>
                        <p>Official Obsidian Sync is well-suited for solo individuals, but escalates to $48–$120 per user each year. Worst of all, it provides <strong>zero granular folder permissions</strong>: an employee or external contractor either gets access to your entire vault, or nothing at all.</p>
                        
                        <div class="synkk-paradigm-contrast">
                            <div class="synkk-paradigm-contrast__broken">
                                <span class="synkk-contrast-label synkk-contrast-label--broken">Proprietary Sync</span>
                                <p>$120/seat/yr · All-or-Nothing vault access · Zero contractor folder scoping</p>
                            </div>
                            <div class="synkk-paradigm-contrast__synkk">
                                <span class="synkk-contrast-label synkk-contrast-label--synkk">Synkk Guarantee</span>
                                <p>Self-Hosted $0 or Lifetime License · Surgical Path ACLs (Read/Write, Read-Only, Hidden)</p>
                            </div>
                        </div>
                    </article>

                    <article class="synkk-safety-card">
                        <div class="synkk-safety-card__top"><span>PARADIGM 02</span><strong>MERGE COLLISION</strong></div>
                        <h3>The Fragility of Community Git Plugins</h3>
                        <p>Git-based sync plugins are notorious for silent merge conflicts. A single conflict marker (<code>&lt;&lt;&lt;&lt;&lt;&lt;&lt; HEAD</code>) written into a Markdown file destroys frontmatter parsing, breaks Dataview queries, and causes severe mobile sync battery drain.</p>
                        
                        <div class="synkk-paradigm-contrast">
                            <div class="synkk-paradigm-contrast__broken">
                                <span class="synkk-contrast-label synkk-contrast-label--broken">Git Sync Plugins</span>
                                <p>Cryptic conflict markers ruin YAML frontmatter · Silent merge failure · Heavy battery drain</p>
                            </div>
                            <div class="synkk-paradigm-contrast__synkk">
                                <span class="synkk-contrast-label synkk-contrast-label--synkk">Synkk Guarantee</span>
                                <p>Optimistic Concurrency Control · Zero silent overwrites · Visual 3-way diff reconciliation</p>
                            </div>
                        </div>
                    </article>

                    <article class="synkk-safety-card">
                        <div class="synkk-safety-card__top"><span>PARADIGM 03</span><strong>SURRENDERED PRIVACY</strong></div>
                        <h3>The Data Exposure of Cloud Monoliths</h3>
                        <p>Teams migrating to Notion or Confluence surrender data sovereignty. Company strategy, customer notes, and API keys are stored in multi-tenant commercial cloud databases vulnerable to vendor outages and third-party AI training scrapers.</p>
                        
                        <div class="synkk-paradigm-contrast">
                            <div class="synkk-paradigm-contrast__broken">
                                <span class="synkk-contrast-label synkk-contrast-label--broken">Cloud SaaS Monoliths</span>
                                <p>Closed database schemas · Data scraped for AI training · Vulnerable to third-party outages</p>
                            </div>
                            <div class="synkk-paradigm-contrast__synkk">
                                <span class="synkk-contrast-label synkk-contrast-label--synkk">Synkk Guarantee</span>
                                <p>100% Plain Markdown on your disk · Zero third-party telemetry · Zero-Knowledge E2EE</p>
                            </div>
                        </div>
                    </article>

                    <article class="synkk-safety-card">
                        <div class="synkk-safety-card__top"><span>PARADIGM 04</span><strong>STORAGE CRUNCH</strong></div>
                        <h3>The Mobile Storage Constraint</h3>
                        <p>Team vaults with PDFs, audio recordings, and canvas boards easily exceed 50GB, quickly overwhelming mobile storage on iPhones and Android devices when traditional sync engines force full vault cloning.</p>
                        
                        <div class="synkk-paradigm-contrast">
                            <div class="synkk-paradigm-contrast__broken">
                                <span class="synkk-contrast-label synkk-contrast-label--broken">Full Vault Cloning</span>
                                <p>50GB media libraries fill mobile storage · Long initial sync freezes device bandwidth</p>
                            </div>
                            <div class="synkk-paradigm-contrast__synkk">
                                <span class="synkk-contrast-label synkk-contrast-label--synkk">Synkk Guarantee</span>
                                <p>Selective Ghost Files: Markdown syncs instantly; heavy media hydrates on-demand</p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <!-- The Five Pillars of Synkk -->
            <section id="pillars" class="synkk-viewport-section synkk-shell" aria-labelledby="pillars-heading">
                <header class="synkk-section-heading">
                    <div>
                        <p class="synkk-eyebrow">02 / The Five Pillars</p>
                        <h2 id="pillars-heading">The foundational principles behind Synkk.</h2>
                    </div>
                    <p>Every line of code in Synkk is written to honor five unbreakable commitments to data sovereignty, safety, and team velocity.</p>
                </header>

                <div class="synkk-feature-cards">
                    <!-- Pillar 1 -->
                    <article class="synkk-feature-card">
                        <div class="synkk-feature-card__copy">
                            <div class="synkk-feature-card__badge">
                                <span class="synkk-status-dot" aria-hidden="true"></span> Pillar 01 · Sovereignty
                            </div>
                            <h3>Local-First &amp; 100% Sovereign.</h3>
                            <p>Your notes remain plain Markdown files (<code>.md</code>) stored on your local disk. If Synkk is turned off tomorrow, every single file, folder, and link in your vault continues working in Obsidian exactly as before. The server runs on your own hardware, home server, or private VPS.</p>
                            <ul class="synkk-feature-card__list">
                                <li><strong>No proprietary database lock-in:</strong> Clean, standard Markdown files with standard YAML frontmatter.</li>
                                <li><strong>Self-hosted autonomy:</strong> Run via Docker Compose or native PHP on your private server.</li>
                                <li><strong>Zero external dependencies:</strong> Complete vault management without commercial cloud silos.</li>
                            </ul>
                        </div>
                        <div class="synkk-feature-card__media">
                            <div class="synkk-window-frame">
                                <div class="synkk-window-bar" aria-hidden="true">
                                    <span><i></i><i></i><i></i></span>
                                    <small>synkk / local-storage</small>
                                    <span>↗</span>
                                </div>
                                <img src="/images/showcase/editor-full.webp" alt="Local-First Markdown Workspace" width="1280" height="720" loading="lazy">
                            </div>
                        </div>
                    </article>

                    <!-- Pillar 2 (Reverse) with Interactive Role Simulator Full Width -->
                    <article class="synkk-feature-card synkk-feature-card--reverse">
                        <div class="synkk-feature-card__copy">
                            <div class="synkk-feature-card__badge">
                                <span class="synkk-status-dot" aria-hidden="true"></span> Pillar 02 · Access Control
                            </div>
                            <h3>Team-Aware with Path-Scoped ACLs.</h3>
                            <p>Collaborate selectively. Assign surgical per-path permissions across team members and contractors. Share public documentation or engineering RFCs with full write access, keep client deliverables read-only, and mark private logs or executive notes as completely hidden.</p>
                            <ul class="synkk-feature-card__list">
                                <li><strong>Read/Write:</strong> Full capability to push edits, create new notes, and sync revisions.</li>
                                <li><strong>Read-Only:</strong> Clients pull and read notes; local write attempts are blocked at the API gateway.</li>
                                <li><strong>Hidden:</strong> The file path is completely redacted from the sync manifest—untrusted devices have zero knowledge it exists.</li>
                            </ul>
                        </div>
                        <div class="synkk-feature-card__media">
                            <div class="synkk-window-frame">
                                <div class="synkk-window-bar" aria-hidden="true">
                                    <span><i></i><i></i><i></i></span>
                                    <small>synkk / permissions-matrix</small>
                                    <span>↗</span>
                                </div>
                                <img src="/images/showcase/permissions-full.webp" alt="Granular Path Permissions Matrix" width="1280" height="720" loading="lazy">
                            </div>
                        </div>

                        <!-- Full Width Interactive ACL Simulator (Below the image and text columns) -->
                        <div class="synkk-feature-card__fullwidth">
                            <div class="synkk-role-simulator" id="role-simulator">
                                <div class="flex items-center justify-between mb-3 pb-2 border-b border-emerald-950/10 dark:border-emerald-500/20">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-400 font-mono">Interactive ACL Simulator</span>
                                    </div>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">Select an organization role to test real-time path boundaries:</span>
                                </div>
                                <div class="synkk-role-simulator__tabs">
                                    <button type="button" class="synkk-role-btn is-active" data-role="lead">Lead Engineer</button>
                                    <button type="button" class="synkk-role-btn" data-role="contractor">External Contractor</button>
                                    <button type="button" class="synkk-role-btn" data-role="client">Client Stakeholder</button>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="synkk-role-simulator__table">
                                        <thead>
                                            <tr>
                                                <th>Vault Folder Path</th>
                                                <th>Enforced Permission</th>
                                                <th>Obsidian Visibility</th>
                                            </tr>
                                        </thead>
                                        <tbody id="role-sim-body">
                                            <!-- Populated via script below -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </article>

                    <!-- Pillar 3 -->
                    <article class="synkk-feature-card">
                        <div class="synkk-feature-card__copy">
                            <div class="synkk-feature-card__badge">
                                <span class="synkk-status-dot" aria-hidden="true"></span> Pillar 03 · Safety
                            </div>
                            <h3>Safe &amp; Deterministic. Zero Silent Loss.</h3>
                            <p>Sync engines must never lose data. Synkk protects your team with the Atomic Safety Shield: an automatic guard that halts sync if a batch attempts to delete more than 10% of your notes. Combined with pre-sync local backups and an interactive 3-way visual conflict sandbox, your work is never overwritten.</p>
                            <ul class="synkk-feature-card__list">
                                <li><strong>Atomic 10% Deletion Threshold:</strong> Halts rogue deletion scripts with a cryptographic override.</li>
                                <li><strong>Pre-Sync Snapshots:</strong> Files are backed up locally before remote overwrites take place.</li>
                                <li><strong>3-Way Visual Sandbox:</strong> Reconcile concurrent edits side-by-side with 1-click hunk merging.</li>
                            </ul>
                        </div>
                        <div class="synkk-feature-card__media">
                            <div class="synkk-feature-graphic synkk-feature-graphic--shield">
                                <div class="synkk-graphic-shield-badge">
                                    <span class="synkk-graphic-shield-icon">🛡️</span>
                                    <strong>Atomic Safety Shield</strong>
                                    <small>Zero silent overwrites</small>
                                </div>
                                <div class="synkk-graphic-shield-items">
                                    <div class="synkk-shield-item">
                                        <span class="synkk-shield-item__badge">SHA-256</span>
                                        <div><strong>Cryptographic Verification</strong><p>Content fingerprints on every accepted revision</p></div>
                                    </div>
                                    <div class="synkk-shield-item">
                                        <span class="synkk-shield-item__badge">SNAPSHOTS</span>
                                        <div><strong>Pre-Sync Backups</strong><p>Local backup saved before remote mutations</p></div>
                                    </div>
                                    <div class="synkk-shield-item">
                                        <span class="synkk-shield-item__badge">3-WAY DIFF</span>
                                        <div><strong>Visual Sandbox</strong><p>Reconcile concurrent changes without Git merge hell</p></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <!-- Pillar 4 (Reverse) -->
                    <article class="synkk-feature-card synkk-feature-card--reverse">
                        <div class="synkk-feature-card__copy">
                            <div class="synkk-feature-card__badge">
                                <span class="synkk-status-dot" aria-hidden="true"></span> Pillar 04 · Private Intelligence
                            </div>
                            <h3>Intelligent &amp; Agentic on Your Hardware.</h3>
                            <p>Turn your personal knowledge base into a private AI assistant. Synkk combines sublinear character TF-IDF vector embeddings and local Ollama LLMs with bidirectional wikilink graph traversal. Ask questions of your vault with verified line citations without leaking a single byte to third-party AI APIs.</p>
                            <ul class="synkk-feature-card__list">
                                <li><strong>Local Ollama Integration:</strong> Run Llama 3.2 or Nomic Embeddings directly on your machine.</li>
                                <li><strong>Graph-Augmented RAG:</strong> Traverses backlink connections (<code>[[wikilinks]]</code>) for deep contextual answers.</li>
                                <li><strong>Zero Cloud Leakage:</strong> 100% private intelligence operating strictly on your hardware.</li>
                            </ul>
                        </div>
                        <div class="synkk-feature-card__media">
                            <div class="synkk-window-frame">
                                <div class="synkk-window-bar" aria-hidden="true">
                                    <span><i></i><i></i><i></i></span>
                                    <small>synkk / graph-traversal</small>
                                    <span>↗</span>
                                </div>
                                <img src="/images/showcase/graph-full.webp" alt="Knowledge Graph and Private Local RAG" width="1280" height="720" loading="lazy">
                            </div>
                        </div>
                    </article>

                    <!-- Pillar 5 -->
                    <article class="synkk-feature-card">
                        <div class="synkk-feature-card__copy">
                            <div class="synkk-feature-card__badge">
                                <span class="synkk-status-dot" aria-hidden="true"></span> Pillar 05 · Cryptography
                            </div>
                            <h3>Zero-Knowledge Cryptography &amp; Fleet DLP.</h3>
                            <p>For organizations with strict compliance requirements, Synkk provides client-side AES-256-GCM encryption with PBKDF2 key derivation. The server stores strictly opaque ciphertext blobs. Leaked credentials (AWS keys, OpenAI tokens, private SSH keys) are caught in real time by the automated DLP scanner before they leave the device.</p>
                            <ul class="synkk-feature-card__list">
                                <li><strong>Client-Side AES-256-GCM:</strong> Keys are derived locally with 100,000 PBKDF2 iterations.</li>
                                <li><strong>In-App DLP Secret Scanner:</strong> Intercepts high-entropy credential leaks automatically.</li>
                                <li><strong>1-Click Remote Device Wipe:</strong> Revoke compromised device tokens instantly (HTTP 410 purge).</li>
                            </ul>
                        </div>
                        <div class="synkk-feature-card__media">
                            <div class="synkk-feature-graphic synkk-feature-graphic--docker">
                                <div class="synkk-graphic-docker-header">
                                    <span><i></i><i></i><i></i></span>
                                    <small>security-telemetry.json</small>
                                </div>
                                <pre class="synkk-docker-code"><code>{
  "vault_e2ee": "aes-256-gcm",
  "key_derivation": "pbkdf2_sha256_100k",
  "dlp_scanner": "active",
  "server_storage": "opaque_ciphertext",
  "remote_wipe_support": true,
  "zero_knowledge": true
}</code></pre>
                                <div class="synkk-graphic-wipe-tag">
                                    <span>🔒 Zero-Knowledge Verified</span>
                                    <small>Server admins cannot read note contents</small>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <!-- Section 03: The Synkk Manifesto -->
            <section id="manifesto" class="synkk-manifesto synkk-viewport-section synkk-shell" aria-labelledby="manifesto-heading">
                <div class="synkk-manifesto__card">
                    <p class="synkk-eyebrow text-emerald-400">03 / The Founding Manifesto</p>
                    <h2 id="manifesto-heading" class="text-3xl sm:text-4xl font-bold tracking-tight text-white mt-2">Why We Will Never Sell Your Thoughts.</h2>
                    
                    <blockquote class="synkk-manifesto__quote">
                        “If software requires cloud servers you do not control to read your own thoughts, you do not own your mind — you are renting it from a landlord.”
                    </blockquote>

                    <p class="text-zinc-300 text-sm sm:text-base leading-relaxed max-w-3xl">
                        In an era where every major tech company is training AI models on private user repositories, selling knowledge graphs to ad exchanges, and locking customers into closed proprietary database schemas, Synkk is a line in the sand. We believe personal and team knowledge is an extension of human cognition. It must be private, sovereign, and portable forever.
                    </p>

                    <div class="synkk-manifesto__commitments">
                        <div class="synkk-manifesto-item">
                            <strong>1. Plain Text Forever</strong>
                            <p>No proprietary schemas, no opaque database blobs. You can delete Synkk at any second and your notes remain untouched on your hard drive.</p>
                        </div>
                        <div class="synkk-manifesto-item">
                            <strong>2. Sustainable Independence</strong>
                            <p>We sell software licenses and optional managed hosting. We will never take venture capital conditioned on monetizing user data or AI scrapers.</p>
                        </div>
                        <div class="synkk-manifesto-item">
                            <strong>3. Strict Zero Telemetry</strong>
                            <p>No Google Analytics, no Mixpanel, no behavioral fingerprinting in the sync engine. What happens in your vault stays strictly in your vault.</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-4 pt-6 border-t border-zinc-800">
                        <div class="flex items-center gap-3">
                            <div class="size-10 rounded-full bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center font-bold text-emerald-300 text-sm font-mono">
                                SK
                            </div>
                            <div>
                                <strong class="text-white text-sm block">The Synkk Core Team</strong>
                                <span class="text-zinc-400 text-xs">Independent Maintainers &amp; PKM Crafters</span>
                            </div>
                        </div>
                        <a href="{{ $pluginUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--paper">
                            Inspect Our Source Code on GitHub <span aria-hidden="true">↗</span>
                        </a>
                    </div>
                </div>
            </section>

            <!-- Section 04: Technical Comparison Matrix -->
            <section id="comparison" class="synkk-viewport-section synkk-shell" aria-labelledby="comp-heading">
                <header class="synkk-section-heading">
                    <div>
                        <p class="synkk-eyebrow">04 / Decisive Architecture</p>
                        <h2 id="comp-heading">How Synkk Compares to Alternative Solutions.</h2>
                    </div>
                    <p>Compare the engineering guarantees of Synkk against Official Obsidian Sync, Community Git Plugins, and Closed Cloud SaaS.</p>
                </header>

                <div class="synkk-comparison-wrap">
                    <table class="synkk-comparison-table">
                        <thead>
                            <tr>
                                <th>Architectural Capability</th>
                                <th class="is-synkk">⚡ Synkk Sovereign</th>
                                <th>Official Obsidian Sync</th>
                                <th>Community Git Plugins</th>
                                <th>Cloud SaaS (Notion / Confluence)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>File Format &amp; Portability</strong></td>
                                <td class="is-synkk"><span class="text-emerald-500 font-bold">✓</span> Plain .md on your disk</td>
                                <td><span class="text-emerald-500 font-bold">✓</span> Plain .md on your disk</td>
                                <td><span class="text-emerald-500 font-bold">✓</span> Plain .md on your disk</td>
                                <td><span class="text-red-500 font-bold">✗</span> Closed proprietary cloud DB</td>
                            </tr>
                            <tr>
                                <td><strong>Granular Folder/Path ACLs</strong></td>
                                <td class="is-synkk"><span class="text-emerald-500 font-bold">✓</span> Surgical Path ACLs</td>
                                <td><span class="text-red-500 font-bold">✗</span> All-or-Nothing Vault</td>
                                <td><span class="text-red-500 font-bold">✗</span> All-or-Nothing Repo</td>
                                <td><span class="text-amber-500 font-bold">~</span> Complex Workspace ACLs</td>
                            </tr>
                            <tr>
                                <td><strong>In-Flight DLP Secret Scanner</strong></td>
                                <td class="is-synkk"><span class="text-emerald-500 font-bold">✓</span> Intercepts AWS / API keys</td>
                                <td><span class="text-red-500 font-bold">✗</span> None</td>
                                <td><span class="text-red-500 font-bold">✗</span> None (Committed to Git)</td>
                                <td><span class="text-red-500 font-bold">✗</span> Scanned for AI Training</td>
                            </tr>
                            <tr>
                                <td><strong>Merge Conflict Safety</strong></td>
                                <td class="is-synkk"><span class="text-emerald-500 font-bold">✓</span> 3-Way Visual Diff Sandbox</td>
                                <td><span class="text-amber-500 font-bold">~</span> Duplicate conflict copies</td>
                                <td><span class="text-red-500 font-bold">✗</span> Destructive Git markers</td>
                                <td><span class="text-red-500 font-bold">✗</span> Last-write-wins overwrites</td>
                            </tr>
                            <tr>
                                <td><strong>Selective Mobile Ghost Storage</strong></td>
                                <td class="is-synkk"><span class="text-emerald-500 font-bold">✓</span> Hydrates media on demand</td>
                                <td><span class="text-red-500 font-bold">✗</span> Full vault cloning only</td>
                                <td><span class="text-red-500 font-bold">✗</span> Full repo clone (Large size)</td>
                                <td><span class="text-amber-500 font-bold">~</span> Cloud streaming</td>
                            </tr>
                            <tr>
                                <td><strong>Local Private RAG &amp; AI Graph</strong></td>
                                <td class="is-synkk"><span class="text-emerald-500 font-bold">✓</span> Local Ollama + Embeddings</td>
                                <td><span class="text-red-500 font-bold">✗</span> None</td>
                                <td><span class="text-red-500 font-bold">✗</span> None</td>
                                <td><span class="text-red-500 font-bold">✗</span> Third-party cloud LLMs</td>
                            </tr>
                            <tr>
                                <td><strong>Self-Hosted Infrastructure</strong></td>
                                <td class="is-synkk"><span class="text-emerald-500 font-bold">✓</span> 1-Click Docker / Compose</td>
                                <td><span class="text-red-500 font-bold">✗</span> Closed Obsidian Cloud</td>
                                <td><span class="text-amber-500 font-bold">~</span> Requires GitHub / GitLab</td>
                                <td><span class="text-red-500 font-bold">✗</span> Closed Multi-Tenant SaaS</td>
                            </tr>
                            <tr>
                                <td><strong>Cost &amp; Licensing Model</strong></td>
                                <td class="is-synkk"><span class="text-emerald-500 font-bold">✓</span> Free CE or Lifetime Deal</td>
                                <td>$48–$120 / seat / year</td>
                                <td>Free (Complex setup)</td>
                                <td>$96–$240 / seat / year</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Architecture & Engineering Blueprint -->
            <section id="architecture" class="synkk-viewport-section synkk-shell" aria-labelledby="arch-heading">
                <header class="synkk-section-heading">
                    <div>
                        <p class="synkk-eyebrow">05 / Engineering Blueprint</p>
                        <h2 id="arch-heading">Built upon a battle-tested, decoupled stack.</h2>
                    </div>
                    <p>Synkk is designed as two tightly synchronized halves: the high-concurrency Core Server and the native Obsidian client plugin.</p>
                </header>

                <div class="synkk-safety-grid">
                    <article class="synkk-safety-card synkk-safety-card--primary">
                        <div class="synkk-safety-card__top"><span>CORE SERVER</span><strong>PHP 8.5 / LARAVEL 12</strong></div>
                        <h3>High-Throughput Sync API &amp; Web Workspace</h3>
                        <p>Powered by Laravel 12 on PHP 8.5 with high-concurrency SQLite WAL engine. Delivers sub-15ms sync responses, atomic cache locks for multiplayer carets, and soft-delete tombstone tracking.</p>
                        <div class="synkk-hash-readout">
                            <span>STORAGE ENGINE</span>
                            <code>SQLite WAL / Postgres 15+</code>
                            <small>Single-command Docker container</small>
                        </div>
                    </article>

                    <article class="synkk-safety-card">
                        <div class="synkk-safety-card__top"><span>NATIVE PLUGIN</span><strong>TYPESCRIPT / CM6</strong></div>
                        <h3>Obsidian ViewPlugin &amp; Live Decorators</h3>
                        <p>Written in TypeScript with CodeMirror 6 ViewPlugins. Injects live presence carets, handles pre-sync backups, renders the visual 3-way diff sandbox, and supports instant 2-second QR pairing.</p>
                        <div class="synkk-rule-list">
                            <p><span>Plugin Release</span><strong>v1.0.0 Public</strong></p>
                            <p><span>Pairing Latency</span><strong>&lt; 2.0s via QR</strong></p>
                            <p><span>Diff Engine</span><strong>3-Way Sandbox</strong></p>
                        </div>
                    </article>

                    <article class="synkk-safety-card">
                        <div class="synkk-safety-card__top"><span>COMMUNITY &amp; OPEN CORE</span><strong>GPL / COMMERCIAL</strong></div>
                        <h3>Sustainable Open Source Innovation</h3>
                        <p>Synkk Community is 100% free and open-source forever. Commercial Pro licenses and managed cloud workspaces fund continuous research without investor pressure to commercialize private data.</p>
                        <ol class="synkk-version-list">
                            <li><i></i><span><strong>Free Community CE</strong><small>Self-hosted, full source</small></span></li>
                            <li><i></i><span><strong>Pro Lifetime Deal</strong><small>Commercial team server</small></span></li>
                            <li><i></i><span><strong>Managed Cloud SaaS</strong><small>Zero-maintenance hosting</small></span></li>
                        </ol>
                    </article>
                </div>

                <!-- Interactive Pipeline Flow Diagram -->
                <div class="mt-8">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-500 font-mono block mb-2">Cryptographic Sync Pipeline (Step-by-Step Flow)</span>
                    <div class="synkk-arch-pipeline">
                        <div class="synkk-pipeline-step">
                            <span class="synkk-pipeline-step__num">01 · CLIENT EDIT</span>
                            <strong>Obsidian Event Trigger</strong>
                            <p>File modification detected locally; pre-sync snapshot archived to .synkk/snapshots/.</p>
                        </div>
                        <div class="synkk-pipeline-step">
                            <span class="synkk-pipeline-step__num">02 · CRYPTO TRANSIT</span>
                            <strong>SHA-256 Fingerprint</strong>
                            <p>Content hashed and encrypted via client-side AES-256-GCM before transport dispatch.</p>
                        </div>
                        <div class="synkk-pipeline-step">
                            <span class="synkk-pipeline-step__num">03 · DLP GATEWAY</span>
                            <strong>In-Flight Secret Inspection</strong>
                            <p>Regex scanner checks for leaked tokens (AWS, GitHub, Slack) to prevent credential propagation.</p>
                        </div>
                        <div class="synkk-pipeline-step">
                            <span class="synkk-pipeline-step__num">04 · OCC VERIFICATION</span>
                            <strong>Atomic WAL Ingestion</strong>
                            <p>Server executes lockForUpdate(); checks baseVersion. Rejects overwrites; creates conflict files.</p>
                        </div>
                        <div class="synkk-pipeline-step">
                            <span class="synkk-pipeline-step__num">05 · VERSION ARCHIVE</span>
                            <strong>Historical Retention</strong>
                            <p>Prior version archived into VaultFileVersion for 1-click historical rollback anytime.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Spotlight Terminal CTA Component -->
            <section class="synkk-final-cta synkk-viewport-section synkk-shell" aria-labelledby="cta-heading">
                <div class="synkk-final-cta__ambient" aria-hidden="true"></div>
                <div class="synkk-final-cta__grid">
                    <div class="synkk-final-cta__copy">
                        <div class="synkk-cta-badge">
                            <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                            <span>Join The Sovereign Sync Movement · v1.0.0 Live</span>
                        </div>
                        <p class="synkk-eyebrow">Your thoughts. Your rules.</p>
                        <h2 id="cta-heading">Own your team brain today.</h2>
                        <p>Deploy your private Synkk server with Docker in 30 seconds, install the open-source Obsidian plugin, and experience team collaboration without cloud compromise.</p>
                        <div class="synkk-final-cta__actions">
                            <a href="{{ $pluginReleaseUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-button synkk-button--accent">Download Plugin v1.0.0 <span aria-hidden="true">↗</span></a>
                            <a href="{{ route('public.docs') }}" class="synkk-button synkk-button--paper">Read Documentation <span aria-hidden="true">→</span></a>
                        </div>
                        <div class="synkk-cta-guarantees">
                            <span><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> 100% Plain Markdown</span>
                            <span><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Zero Telemetry</span>
                            <span><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Self-Hosted Docker</span>
                            <span><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Free Forever CE</span>
                        </div>
                    </div>

                    <div class="synkk-cta-terminal" aria-label="Docker deployment quickstart terminal">
                        <div class="synkk-cta-terminal__header">
                            <div class="synkk-cta-terminal__controls" aria-hidden="true">
                                <span class="synkk-dot synkk-dot--red"></span>
                                <span class="synkk-dot synkk-dot--amber"></span>
                                <span class="synkk-dot synkk-dot--green"></span>
                            </div>
                            <span class="synkk-cta-terminal__title">synkk-sovereign-deploy.sh</span>
                            <button type="button" id="copy-deploy-btn" class="text-[11px] font-mono font-bold bg-zinc-800 hover:bg-zinc-700 text-zinc-300 px-2.5 py-1 rounded-md transition-colors flex items-center gap-1 cursor-pointer">
                                <span>Copy</span>
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        </div>
                        <div class="synkk-cta-terminal__body">
                            <div class="synkk-code-line"><span class="synkk-prompt">$</span> <span class="synkk-cmd">git clone https://github.com/tawandajosephmutsena/synkk.git</span></div>
                            <div class="synkk-code-line"><span class="synkk-prompt">$</span> <span class="synkk-cmd">docker compose up -d</span></div>
                            <div class="synkk-code-output"><span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span> Synkk sovereign server running at http://127.0.0.1:8080</div>
                            <div class="synkk-code-line synkk-code-line--comment"># Scan dashboard QR code in Obsidian mobile to pair in 2 seconds</div>
                            <div class="synkk-code-line"><span class="synkk-prompt">$</span> <span class="synkk-cmd">curl -X GET http://127.0.0.1:8080/api/v1/auth/verify</span></div>
                            <div class="synkk-code-output"><span class="synkk-status-dot" aria-hidden="true"></span> {"status": "authenticated", "mode": "sovereign", "e2ee": true}</div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- Primary Footer -->
        <footer class="synkk-footer">
            <div class="synkk-shell">
                <div class="synkk-footer-telemetry">
                    <div class="synkk-footer-telemetry__status">
                        <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                        <strong>SYSTEM STATUS:</strong>
                        <span>Sync Engine v1.0.0 · Local-First Architecture · Systems Operational</span>
                    </div>
                    <a href="{{ $pluginUrl }}" target="_blank" rel="noopener noreferrer" class="synkk-footer-telemetry__link">Public Repository ↗</a>
                </div>

                <div class="synkk-footer__top">
                    <div>
                        <img src="/images/synkk-logo.svg" alt="Synkk — Obsidian everywhere" width="689" height="270" loading="lazy">
                        <p>Portable notes. Visible safety. Infrastructure you control.</p>
                    </div>
                    <nav aria-label="Footer navigation">
                        <a href="{{ route('home') }}#product">Product</a>
                        <a href="{{ route('home') }}#workflow">How it works</a>
                        <a href="{{ route('home') }}#safety">Safety</a>
                        <a href="{{ route('home') }}#comparison">Why Synkk</a>
                        <a href="{{ route('home') }}#pricing">Pricing</a>
                        <a href="{{ route('home') }}#roadmap">Roadmap</a>
                        <a href="{{ route('about') }}">About</a>
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

        <!-- Interactive Script for Role Simulator & Copy Deployment Button -->
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                // 1. Role & Path Simulator
                const roles = {
                    lead: [
                        { path: 'engineering/rfcs/**', perm: 'read_write', label: 'Read/Write', visibility: 'Full Sync & Push' },
                        { path: 'clients/deliverables/**', perm: 'read_write', label: 'Read/Write', visibility: 'Full Sync & Push' },
                        { path: 'executive/strategy/**', perm: 'read_write', label: 'Read/Write', visibility: 'Full Sync & Push' },
                        { path: 'internal/salaries/**', perm: 'hidden', label: 'Hidden', visibility: 'Redacted from Manifest' }
                    ],
                    contractor: [
                        { path: 'engineering/rfcs/**', perm: 'read_only', label: 'Read-Only', visibility: 'Read Only (Push Blocked)' },
                        { path: 'clients/deliverables/**', perm: 'read_write', label: 'Read/Write', visibility: 'Assigned Scoped Write' },
                        { path: 'executive/strategy/**', perm: 'hidden', label: 'Hidden', visibility: 'Completely Redacted' },
                        { path: 'internal/salaries/**', perm: 'hidden', label: 'Hidden', visibility: 'Completely Redacted' }
                    ],
                    client: [
                        { path: 'engineering/rfcs/**', perm: 'hidden', label: 'Hidden', visibility: 'Completely Redacted' },
                        { path: 'clients/deliverables/**', perm: 'read_only', label: 'Read-Only', visibility: 'Read Only (Review View)' },
                        { path: 'executive/strategy/**', perm: 'hidden', label: 'Hidden', visibility: 'Completely Redacted' },
                        { path: 'internal/salaries/**', perm: 'hidden', label: 'Hidden', visibility: 'Completely Redacted' }
                    ]
                };

                const roleBody = document.getElementById('role-sim-body');
                const roleButtons = document.querySelectorAll('.synkk-role-btn');

                function renderRole(roleKey) {
                    if (!roleBody || !roles[roleKey]) return;
                    const items = roles[roleKey];
                    roleBody.innerHTML = items.map(item => {
                        const badgeClass = item.perm === 'read_write' 
                            ? 'synkk-perm-badge--rw' 
                            : (item.perm === 'read_only' ? 'synkk-perm-badge--ro' : 'synkk-perm-badge--hidden');
                        return `
                            <tr>
                                <td class="font-mono text-xs text-zinc-700 dark:text-zinc-300">${item.path}</td>
                                <td><span class="synkk-perm-badge ${badgeClass}">${item.label}</span></td>
                                <td class="text-xs text-zinc-500 dark:text-zinc-400">${item.visibility}</td>
                            </tr>
                        `;
                    }).join('');
                }

                roleButtons.forEach(btn => {
                    btn.addEventListener('click', () => {
                        roleButtons.forEach(b => b.classList.remove('is-active'));
                        btn.classList.add('is-active');
                        renderRole(btn.dataset.role);
                    });
                });

                renderRole('lead');

                // 2. Terminal Copy Button
                const copyBtn = document.getElementById('copy-deploy-btn');
                if (copyBtn) {
                    copyBtn.addEventListener('click', async () => {
                        const cmd = 'git clone https://github.com/tawandajosephmutsena/synkk.git && cd synkk && docker compose up -d';
                        try {
                            await navigator.clipboard.writeText(cmd);
                            const originalHtml = copyBtn.innerHTML;
                            copyBtn.innerHTML = '<span class="text-emerald-400">Copied! ✓</span>';
                            setTimeout(() => {
                                copyBtn.innerHTML = originalHtml;
                            }, 2000);
                        } catch (err) {
                            console.error('Failed to copy command:', err);
                        }
                    });
                }
            });
        </script>
    </body>
</html>
