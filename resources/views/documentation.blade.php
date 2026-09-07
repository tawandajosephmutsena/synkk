<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth dark" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Documentation — Synkk Obsidian Sync</title>
        <meta name="description" content="Complete step-by-step documentation and operating guide for Synkk local-first Obsidian sync, path security, REST API, Docker self-hosting, and Safety Shield.">
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
    <body class="synkk-site font-sans antialiased">
        @php
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

        <!-- Sticky Header (Matching Welcome Page Branding) -->
        <header class="synkk-header">
            <div class="synkk-shell synkk-header__inner">
                <a href="{{ route('home') }}" class="synkk-brand" aria-label="Synkk home">
                    <img src="/images/synkk-logo.svg" alt="Synkk — Obsidian everywhere" width="689" height="270" fetchpriority="high">
                </a>

                <nav class="synkk-nav" aria-label="Primary navigation">
                    <a href="{{ route('home') }}#product">Product</a>
                    <a href="{{ route('home') }}#workflow">How it works</a>
                    <a href="{{ route('home') }}#safety">Safety</a>
                    <a href="{{ route('home') }}#pricing">Pricing</a>
                    <a href="{{ route('public.docs') }}" class="font-bold text-emerald-950">Docs</a>
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
                            <a href="{{ route('public.docs') }}">Documentation <span>↗</span></a>
                            @guest
                                <a href="{{ route('login') }}">Log in <span>↗</span></a>
                            @endguest
                        </nav>
                    </details>
                </div>
            </div>
        </header>

        <!-- Documentation Hero Section -->
        <section class="synkk-docs-hero">
            <div class="synkk-shell synkk-docs-hero__inner">
                <div class="synkk-docs-hero__meta">
                    <p class="synkk-eyebrow">Technical Reference &amp; Setup Guide</p>
                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-200 text-emerald-950 font-mono text-xs font-semibold">v1.1 Foundation Release</span>
                    <span class="px-2.5 py-0.5 rounded-full bg-zinc-200 text-zinc-800 font-mono text-xs font-medium">Obsidian Plugin v1.0.0</span>
                </div>
                <div>
                    <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-zinc-900 mb-3">
                        Mastering Synkk. <span class="text-emerald-800 font-serif italic">Step by step.</span>
                    </h1>
                    <p class="text-lg text-zinc-700 max-w-3xl leading-relaxed">
                        Complete documentation for setting up local-first Obsidian sync, configuring path permissions, deploying with Docker, utilizing the REST API, and relying on Safety Shield.
                    </p>
                </div>

                <!-- Interactive Section Filter Search -->
                <div class="synkk-docs-search">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    <input type="text" id="docs-search-input" placeholder="Search documentation (e.g., QR pairing, REST API, Docker, Safety Shield)..." aria-label="Search documentation">
                </div>
            </div>
        </section>

        <!-- Main Content Area with Sticky Sidebar -->
        <main id="main-content" class="synkk-shell">
            <div class="synkk-docs-grid">
                
                <!-- Left Navigation Sidebar -->
                <aside class="synkk-docs-sidebar" aria-label="Documentation navigation">
                    
                    <div class="synkk-docs-sidebar__group">
                        <div class="synkk-docs-sidebar__title">
                            <span>01 / GETTING STARTED</span>
                        </div>
                        <div class="synkk-docs-sidebar__nav">
                            <a href="#quickstart" class="synkk-docs-sidebar__link">Quickstart Guide</a>
                            <a href="#installation" class="synkk-docs-sidebar__link">Plugin Installation</a>
                            <a href="#pairing" class="synkk-docs-sidebar__link">Instant QR Pairing</a>
                        </div>
                    </div>

                    <div class="synkk-docs-sidebar__group">
                        <div class="synkk-docs-sidebar__title">
                            <span>02 / ARCHITECTURE</span>
                        </div>
                        <div class="synkk-docs-sidebar__nav">
                            <a href="#architecture" class="synkk-docs-sidebar__link">Plain Markdown Storage</a>
                            <a href="#manifests" class="synkk-docs-sidebar__link">SHA-256 Manifests</a>
                            <a href="#conflict-engine" class="synkk-docs-sidebar__link">Conflict Engine</a>
                        </div>
                    </div>

                    <div class="synkk-docs-sidebar__group">
                        <div class="synkk-docs-sidebar__title">
                            <span>03 / ACCESS CONTROL</span>
                        </div>
                        <div class="synkk-docs-sidebar__nav">
                            <a href="#roles" class="synkk-docs-sidebar__link">Member Roles</a>
                            <a href="#permissions" class="synkk-docs-sidebar__link">Path-Based Rules</a>
                            <a href="#dlp-security" class="synkk-docs-sidebar__link">DLP Secret Scanner</a>
                        </div>
                    </div>

                    <div class="synkk-docs-sidebar__group">
                        <div class="synkk-docs-sidebar__title">
                            <span>04 / DATA PROTECTION</span>
                        </div>
                        <div class="synkk-docs-sidebar__nav">
                            <a href="#safety-shield" class="synkk-docs-sidebar__link">Safety Shield Guard</a>
                            <a href="#snapshots" class="synkk-docs-sidebar__link">Snapshots &amp; Rollbacks</a>
                        </div>
                    </div>

                    <div class="synkk-docs-sidebar__group">
                        <div class="synkk-docs-sidebar__title">
                            <span>05 / DEVELOPER &amp; OPS</span>
                        </div>
                        <div class="synkk-docs-sidebar__nav">
                            <a href="#rest-api" class="synkk-docs-sidebar__link">REST API Reference</a>
                            <a href="#docker" class="synkk-docs-sidebar__link">Docker Self-Hosting</a>
                            <a href="#remote-wipe" class="synkk-docs-sidebar__link">Remote Device Wipe</a>
                        </div>
                    </div>

                    <div class="synkk-docs-sidebar__group">
                        <div class="synkk-docs-sidebar__title">
                            <span>06 / ECOSYSTEM</span>
                        </div>
                        <div class="synkk-docs-sidebar__nav">
                            <a href="#roadmap" class="synkk-docs-sidebar__link">Roadmap &amp; E2EE Specs</a>
                        </div>
                    </div>

                </aside>

                <!-- Documentation Main Sections -->
                <div class="synkk-docs-content">

                    <!-- SECTION 1: QUICKSTART GUIDE -->
                    <section id="quickstart" class="synkk-docs-section">
                        <h2><span>01 /</span> Quickstart Guide</h2>
                        <p>
                            Synkk is a local-first, self-hostable sync server and Markdown workspace for Obsidian. Follow these steps to connect your first vault and device in under 2 minutes.
                        </p>

                        <!-- Step 1 -->
                        <div class="synkk-step-box">
                            <div class="synkk-step-box__num">1</div>
                            <div class="synkk-step-box__body">
                                <h3>Create a Team &amp; Vault</h3>
                                <p>
                                    Log in to your Synkk web workspace. Navigate to <strong>Vaults</strong> and click <strong>Create Vault</strong>. Name your vault (for example, <code>engineering-brain</code>) and select your default access policy (Read-Write, Read-Only, or Restricted).
                                </p>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div class="synkk-step-box">
                            <div class="synkk-step-box__num">2</div>
                            <div class="synkk-step-box__body">
                                <h3>Generate a Scoped Device Token</h3>
                                <p>
                                    Go to <strong>Devices &amp; Tokens</strong> in your dashboard. Click <strong>Add Device Token</strong>, assign a label representing your physical hardware (e.g., <code>MacBook Pro M3</code> or <code>Work iPhone 15</code>), and generate the secure key. The key will start with <code>synkk_...</code>.
                                </p>
                                <div class="synkk-callout synkk-callout--tip">
                                    <span class="synkk-callout__icon">💡</span>
                                    <div class="synkk-callout__body">
                                        <h4>Security Best Practice</h4>
                                        <p>Always issue a unique token per device. If a device is ever lost or stolen, you can revoke its individual token from the dashboard without affecting your other devices.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3 -->
                        <div class="synkk-step-box">
                            <div class="synkk-step-box__num">3</div>
                            <div class="synkk-step-box__body">
                                <h3>Install the Obsidian Plugin</h3>
                                <p>
                                    Download the latest release bundle (<code>main.js</code>, <code>manifest.json</code>, and <code>styles.css</code>) from the official GitHub releases page. Copy these three files into your vault's plugin directory:
                                </p>
                                <div class="synkk-code-box">
                                    <div class="synkk-code-box__header">
                                        <span><i></i> Directory Structure</span>
                                        <button class="synkk-code-box__copy" onclick="navigator.clipboard.writeText('<your-vault>/.obsidian/plugins/synkk-sync/')">Copy path</button>
                                    </div>
                                    <pre><code>&lt;your-vault&gt;/.obsidian/plugins/synkk-sync/
├── manifest.json
├── main.js
└── styles.css</code></pre>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4 -->
                        <div class="synkk-step-box">
                            <div class="synkk-step-box__num">4</div>
                            <div class="synkk-step-box__body">
                                <h3>Pair &amp; Synchronize</h3>
                                <p>
                                    Open Obsidian → <strong>Settings → Community Plugins</strong> → Enable <strong>Synkk Team Vault Sync</strong>. Open the plugin settings panel. You can connect automatically using <strong>Instant QR Quick Connect</strong> or enter your Server URL and Device Token manually.
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- SECTION 2: INSTALLATION & MOBILE -->
                    <section id="installation" class="synkk-docs-section">
                        <h2><span>02 /</span> Plugin Installation &amp; Setup</h2>
                        <p>
                            Synkk works across macOS, Windows, Linux, iOS, and Android. Because your vault remains standard Markdown files on disk, your files are never held hostage.
                        </p>

                        <h3>Desktop Setup (macOS, Windows, Linux)</h3>
                        <p>
                            On desktop operating systems, Obsidian stores vault configurations inside the hidden <code>.obsidian</code> folder inside your vault root. Simply ensure community plugins are enabled in Obsidian preferences before copying the plugin files.
                        </p>

                        <div id="pairing">
                            <h3>Instant QR Pairing (Mobile &amp; Tablets)</h3>
                            <p>
                                Typing long API tokens on mobile touchscreens is prone to typos. Synkk simplifies mobile setup with <strong>Instant QR Quick Connect</strong>.
                            </p>
                            <ol>
                                <li>In your Synkk web dashboard, navigate to <strong>Devices &amp; Tokens</strong>.</li>
                                <li>Click <strong>Generate Mobile QR Code</strong> next to your device entry.</li>
                                <li>In Obsidian Mobile on iOS or Android, open Synkk settings and tap <strong>Scan Pairing QR Code</strong>.</li>
                                <li>Your server URL and token will automatically populate and test connection instantly.</li>
                            </ol>
                        </div>

                        <div class="synkk-callout synkk-callout--info">
                            <span class="synkk-callout__icon">ℹ️</span>
                            <div class="synkk-callout__body">
                                <h4>Mobile Background Execution Note</h4>
                                <p>iOS and Android aggressively suspend background network tasks when apps are minimized. Synkk performs an automatic sync pulse whenever Obsidian is launched or brought to the foreground, plus periodic sync checks while active.</p>
                            </div>
                        </div>
                    </section>

                    <!-- SECTION 3: ARCHITECTURE -->
                    <section id="architecture" class="synkk-docs-section">
                        <h2><span>03 /</span> Core Architecture &amp; Storage Engine</h2>
                        <p>
                            Synkk is built on a <strong>local-first</strong> foundation. Your notes are stored as plain UTF-8 Markdown text files both on your local file system and on the server.
                        </p>

                        <h3>Storage Directory Layout</h3>
                        <p>
                            On the server side (Laravel 12 + SQLite WAL), files are organized by tenant, team, and vault:
                        </p>
                        <div class="synkk-code-box">
                            <div class="synkk-code-box__header">
                                <span><i></i> Server Storage Hierarchy</span>
                            </div>
                            <pre><code>storage/app/private/
└── vaults/
    └── {team_id}/
        └── {vault_slug}/
            ├── 00-Inbox/
            │   └── QuickNote.md
            ├── Projects/
            │   └── ArchitectureSpec.md
            └── .synkk/
                └── snapshots/</code></pre>
                        </div>

                        <div id="manifests">
                            <h3>Cryptographic SHA-256 Manifests</h3>
                            <p>
                                Every sync operation begins by comparing cryptographic state. When a device requests a sync, Synkk generates or evaluates a vault <strong>Manifest</strong>: a JSON mapping of every file path, its SHA-256 content checksum, file size in bytes, and last modification timestamp.
                            </p>
                            <div class="synkk-code-box">
                                <div class="synkk-code-box__header">
                                    <span><i></i> Example Manifest JSON Payload</span>
                                </div>
                                <pre><code>{
  "vault": "engineering-brain",
  "revision": 78,
  "files": {
    "Projects/Roadmap.md": {
      "hash": "f2a8c1d7e3b9a04f21e5c89731d4e28a9b6e5f1a2b3c4d5e6f7a8b9c0d1e2f3a",
      "size": 4210,
      "mtime": 1725712400
    }
  }
}</code></pre>
                            </div>
                        </div>

                        <div id="conflict-engine">
                            <h3>Conflict Engine &amp; Revision Control</h3>
                            <p>
                                If two devices modify the exact same note while offline and later attempt to sync, Synkk detects the revision mismatch using the client's base version header.
                            </p>
                            <ul>
                                <li><strong>Base Match:</strong> The edit is written as the new canonical file version.</li>
                                <li><strong>Stale Revision:</strong> The incoming upload is automatically preserved as a sibling conflict file: <code>Note.sync-conflict-[timestamp].md</code>.</li>
                            </ul>
                            <div class="synkk-callout synkk-callout--tip">
                                <span class="synkk-callout__icon">🛡️</span>
                                <div class="synkk-callout__body">
                                    <h4>No Lost Edits</h4>
                                    <p>Synkk never silently overwrites concurrent edits. Both notes remain available in your vault so you can review differences and merge manually.</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- SECTION 4: ACCESS CONTROL & PERMISSIONS -->
                    <section id="roles" class="synkk-docs-section">
                        <h2><span>04 /</span> Roles &amp; Granular Path Permissions</h2>
                        <p>
                            Unlike traditional sync solutions that require all-or-nothing access to an entire vault, Synkk allows team leaders to enforce path-scoped boundary rules.
                        </p>

                        <h3>Member Roles &amp; Capabilities</h3>
                        <div class="overflow-x-auto my-4">
                            <table class="w-full text-left text-sm border border-zinc-200 rounded-xl">
                                <thead class="bg-zinc-100 font-mono text-xs text-zinc-700 uppercase">
                                    <tr>
                                        <th class="p-3 border-b">Role</th>
                                        <th class="p-3 border-b">Read Files</th>
                                        <th class="p-3 border-b">Upload / Edit</th>
                                        <th class="p-3 border-b">Manage Members &amp; Tokens</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 font-mono text-xs">
                                    <tr>
                                        <td class="p-3 font-bold text-zinc-900">Admin</td>
                                        <td class="p-3 text-emerald-600 font-bold">✓ Full Access</td>
                                        <td class="p-3 text-emerald-600 font-bold">✓ Full Access</td>
                                        <td class="p-3 text-emerald-600 font-bold">✓ Full Access</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 font-bold text-zinc-900">Editor</td>
                                        <td class="p-3 text-emerald-600">✓ Allowed</td>
                                        <td class="p-3 text-emerald-600">✓ Allowed</td>
                                        <td class="p-3 text-rose-600">✗ Denied</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 font-bold text-zinc-900">Reader</td>
                                        <td class="p-3 text-emerald-600">✓ Allowed</td>
                                        <td class="p-3 text-rose-600">✗ Denied (Pulls only)</td>
                                        <td class="p-3 text-rose-600">✗ Denied</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div id="permissions">
                            <h3>Granular Path-Based Access Rules</h3>
                            <p>
                                Path rules use glob syntax to define access per member or per device group:
                            </p>
                            <div class="synkk-code-box">
                                <div class="synkk-code-box__header">
                                    <span><i></i> Example Path Rule Configuration</span>
                                </div>
                                <pre><code>/finance/*          -> Admin Only (Hidden from general team)
/management/hr/*    -> Admin Only (Hidden)
/engineering/*      -> Editor (Team can read & edit)
/handbook/*         -> Reader (Team can read, Admins edit)</code></pre>
                            </div>

                            <div class="synkk-callout synkk-callout--info">
                                <span class="synkk-callout__icon">🔒</span>
                                <div class="synkk-callout__body">
                                    <h4>Zero-Leak Architecture</h4>
                                    <p>When a device requests a vault manifest, the Synkk server filters out hidden paths <em>before</em> sending the JSON response. Untrusted devices cannot even discover the file names of restricted subdirectories.</p>
                                </div>
                            </div>
                        </div>

                        <div id="dlp-security">
                            <h3>Data Loss Prevention (DLP) &amp; Secret Scanning</h3>
                            <p>
                                Accidental commits of API keys or credentials can compromise entire organizations. Synkk includes an active DLP scanner that intercepts note uploads containing credentials.
                            </p>
                            <p>Scanned secret patterns include:</p>
                            <ul>
                                <li>AWS Access &amp; Secret Keys (<code>AKIA...</code>)</li>
                                <li>OpenAI &amp; Anthropic API Keys (<code>sk-...</code>)</li>
                                <li>Private SSH / RSA Keys (<code>-----BEGIN RSA PRIVATE KEY-----</code>)</li>
                                <li>JSON Web Tokens (JWT) &amp; Database Connection Strings</li>
                            </ul>
                        </div>
                    </section>

                    <!-- SECTION 5: SAFETY SHIELD & RECOVERY -->
                    <section id="safety-shield" class="synkk-docs-section">
                        <h2><span>05 /</span> Safety Shield &amp; Data Loss Protection</h2>
                        <p>
                            Catastrophic data loss during sync usually happens when a script or device accidentally deletes hundreds of files and syncs that mass deletion across all devices.
                        </p>

                        <h3>The 10% Mass-Deletion Guard</h3>
                        <p>
                            Synkk introduces <strong>Safety Shield</strong>. If an incoming sync transaction attempts to delete more than <strong>10% of the total notes</strong> in a vault, the server automatically halts the operation and places the transaction on hold.
                        </p>
                        <div class="synkk-callout synkk-callout--warning">
                            <span class="synkk-callout__icon">⚠️</span>
                            <div class="synkk-callout__body">
                                <h4>Action Required on Deletion Guard Trigger</h4>
                                <p>An administrator receives an immediate notification in the Synkk dashboard. The deletion remains blocked until manually authorized with a one-time override or rejected.</p>
                            </div>
                        </div>

                        <div id="snapshots">
                            <h3>Pre-Mutation Snapshots &amp; Rollback Engine</h3>
                            <p>
                                Before any file is modified or soft-deleted on the server, Synkk creates a local snapshot copy. You can review complete version histories and restore any file version with a single click.
                            </p>
                        </div>
                    </section>

                    <!-- SECTION 6: REST API REFERENCE -->
                    <section id="rest-api" class="synkk-docs-section">
                        <h2><span>06 /</span> REST API Reference</h2>
                        <p>
                            Base API Endpoint: <code class="font-mono bg-zinc-200 px-2 py-0.5 rounded text-zinc-900">{{ url('/api/v1') }}</code>
                        </p>
                        <p>
                            All API requests require a valid Bearer token header: <code>Authorization: Bearer synkk_...</code>.
                        </p>

                        <!-- API Endpoint 1 -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--get">GET</span>
                                <span class="synkk-api-path">/auth/verify</span>
                            </div>
                            <p class="text-sm text-zinc-700">Verifies device token authenticity and returns user, team, and device metadata.</p>
                            <div class="synkk-code-box">
                                <div class="synkk-code-box__header">
                                    <span>curl example</span>
                                    <button class="synkk-code-box__copy" onclick="navigator.clipboard.writeText('curl -H &quot;Authorization: Bearer synkk_token123&quot; {{ url('/api/v1/auth/verify') }}')">Copy</button>
                                </div>
                                <pre><code>curl -X GET "{{ url('/api/v1/auth/verify') }}" \
  -H "Authorization: Bearer synkk_token123" \
  -H "Accept: application/json"</code></pre>
                            </div>
                        </div>

                        <!-- API Endpoint 2 -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--get">GET</span>
                                <span class="synkk-api-path">/vaults</span>
                            </div>
                            <p class="text-sm text-zinc-700">Lists all vaults accessible to the authenticated device token.</p>
                        </div>

                        <!-- API Endpoint 3 -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--get">GET</span>
                                <span class="synkk-api-path">/vaults/{slug}/manifest</span>
                            </div>
                            <p class="text-sm text-zinc-700">Returns the full cryptographic manifest (paths, SHA-256 hashes, sizes, and timestamps) for the specified vault.</p>
                        </div>

                        <!-- API Endpoint 4 -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--get">GET</span>
                                <span class="synkk-api-path">/vaults/{slug}/changes?since_version={v}</span>
                            </div>
                            <p class="text-sm text-zinc-700">Returns incremental file additions, modifications, and deletions recorded since vault version <code>{v}</code>.</p>
                        </div>

                        <!-- API Endpoint 5 -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--post">POST</span>
                                <span class="synkk-api-path">/vaults/{slug}/upload</span>
                            </div>
                            <p class="text-sm text-zinc-700">Uploads a single note or asset using base64 encoded content and SHA-256 validation.</p>
                            <div class="synkk-code-box">
                                <div class="synkk-code-box__header">
                                    <span>JSON Request Payload</span>
                                </div>
                                <pre><code>{
  "path": "Projects/Architecture.md",
  "content": "IyBBcmNoaXRlY3R1cmUgU3BlY2lmaWNhdGlvbg==",
  "hash": "f2a8c1d7e3b9a04f21e5c89731d4e28a9b6e5f1a2b3c4d5e6f7a8b9c0d1e2f3a",
  "base_version": 77
}</code></pre>
                            </div>
                        </div>

                        <!-- API Endpoint 6 -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--post">POST</span>
                                <span class="synkk-api-path">/vaults/{slug}/batch-sync</span>
                            </div>
                            <p class="text-sm text-zinc-700">Executes multiple uploads and deletions in a single atomic database transaction.</p>
                        </div>

                        <!-- API Endpoint 7: Conflict Resolution & 3-Way Diff -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--post">POST</span>
                                <span class="synkk-api-path">/vaults/{slug}/conflicts/diff</span>
                            </div>
                            <p class="text-sm text-zinc-700">Performs an algorithmic 3-way line diff (LCS) between canonical base note, local changes, and incoming remote conflict copy, returning structured hunks.</p>
                        </div>

                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--post">POST</span>
                                <span class="synkk-api-path">/vaults/{slug}/conflicts/resolve</span>
                            </div>
                            <p class="text-sm text-zinc-700">Applies user-selected resolutions across diff hunks, updates canonical note version, creates audit log, and deletes the conflict copy.</p>
                        </div>

                        <!-- API Endpoint 8: CRDT Multiplayer -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--post">POST</span>
                                <span class="synkk-api-path">/vaults/{slug}/collab/sync</span>
                            </div>
                            <p class="text-sm text-zinc-700">Dispatches character-level CRDT insertion and deletion deltas to the note room, updating presence and returning peer operations.</p>
                        </div>

                        <!-- API Endpoint 9: Ghost Files -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--post">POST</span>
                                <span class="synkk-api-path">/vaults/{slug}/files/hydrate</span>
                            </div>
                            <p class="text-sm text-zinc-700">Fetches and streams full binary payload for a lightweight ghost file stub on-demand, marking the file as active.</p>
                        </div>

                        <!-- API Endpoint 10: Zero-Knowledge E2EE -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--post">POST</span>
                                <span class="synkk-api-path">/vaults/{slug}/e2ee/enable</span>
                            </div>
                            <p class="text-sm text-zinc-700">Enables zero-knowledge encryption for the vault with client-provided PBKDF2 salt and verification cipher token.</p>
                        </div>

                        <!-- API Endpoint 11: 2-Second QR Handshake -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--post">POST</span>
                                <span class="synkk-api-path">/pairing/exchange</span>
                            </div>
                            <p class="text-sm text-zinc-700">Mobile client completes QR pairing handshake by exchanging a temporary session ID for a persistent device token.</p>
                        </div>

                        <!-- API Endpoint 12: Transport Relay Status -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--get">GET</span>
                                <span class="synkk-api-path">/vaults/{slug}/transport/status</span>
                            </div>
                            <p class="text-sm text-zinc-700">Lightweight heartbeat endpoint returning vault revision number, active collaborator count, and E2EE state for mobile background polling.</p>
                        </div>

                        <!-- API Endpoint 13: Agentic RAG Query -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--post">POST</span>
                                <span class="synkk-api-path">/vaults/{slug}/rag/query</span>
                            </div>
                            <p class="text-sm text-zinc-700">Graph-augmented agentic query synthesizing accurate answers from vault chunks, traversing [[wikilinks]] backlinks, and returning exact note citations.</p>
                        </div>

                        <!-- API Endpoint 14: Hybrid Semantic Search -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--post">POST</span>
                                <span class="synkk-api-path">/vaults/{slug}/rag/search</span>
                            </div>
                            <p class="text-sm text-zinc-700">Dense vector cosine similarity and sparse lexical search returning ranked note snippets and similarity percentages.</p>
                        </div>

                        <!-- API Endpoint 15: Vector Embeddings Re-indexing -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--post">POST</span>
                                <span class="synkk-api-path">/vaults/{slug}/rag/index</span>
                            </div>
                            <p class="text-sm text-zinc-700">Incrementally indexes markdown notes into 128-dimensional hyperspheres with SHA-256 caching and deleted file cleanup.</p>
                        </div>

                        <!-- API Endpoint 16: RAG Status & Telemetry -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--get">GET</span>
                                <span class="synkk-api-path">/vaults/{slug}/rag/status</span>
                            </div>
                            <p class="text-sm text-zinc-700">Returns vector indexing status, chunk counts, indexed files, embedding provider, and active local LLM health.</p>
                        </div>

                        <h3>HTTP Status Code Reference</h3>
                        <ul class="font-mono text-sm space-y-1">
                            <li><strong class="text-emerald-600">200 OK:</strong> Request succeeded cleanly.</li>
                            <li><strong class="text-amber-600">401 Unauthorized:</strong> Invalid or expired device token.</li>
                            <li><strong class="text-amber-600">403 Forbidden:</strong> Token lacks required path permission.</li>
                            <li><strong class="text-rose-600">409 Conflict:</strong> Version collision detected (conflict copy generated).</li>
                            <li><strong class="text-rose-600">410 Gone / Revoked:</strong> Device token was remotely wiped by admin.</li>
                            <li><strong class="text-rose-600">422 Unprocessable Entity:</strong> SHA-256 checksum mismatch or DLP secret detected.</li>
                        </ul>
                    </section>

                    <!-- SECTION 7: DOCKER & SELF-HOSTING -->
                    <section id="docker" class="synkk-docs-section">
                        <h2><span>07 /</span> Docker &amp; Self-Hosting Guide</h2>
                        <p>
                            Synkk is designed for zero-fuss self-hosting on any Linux VPS, Home Lab server, or Raspberry Pi.
                        </p>
                        <p>
                            The production-ready <code>docker-compose.yml</code> includes the Nginx edge proxy, PHP 8.4-FPM runtime, PostgreSQL database, and Redis cache.
                        </p>

                        <div class="synkk-code-box">
                            <div class="synkk-code-box__header">
                                <span>1-Line Quick Deploy</span>
                                <button class="synkk-code-box__copy" onclick="navigator.clipboard.writeText('curl -fsSL https://synkk.it/install.sh | bash')">Copy</button>
                            </div>
                            <pre><code>curl -fsSL https://synkk.it/install.sh | bash</code></pre>
                        </div>

                        <h3>Docker Compose Architecture</h3>
                        <div class="synkk-code-box">
                            <div class="synkk-code-box__header">
                                <span>docker-compose.yml</span>
                            </div>
                            <pre><code>version: '3.8'

services:
  app:
    image: ghcr.io/tawandajosephmutsena/synkk:latest
    restart: unless-stopped
    environment:
      APP_ENV: production
      APP_KEY: base64:...
      DB_CONNECTION: pgsql
      DB_HOST: postgres
      REDIS_HOST: redis
    volumes:
      - synkk-storage:/var/www/html/storage/app/vaults
    depends_on:
      - postgres
      - redis

  postgres:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: synkk
      POSTGRES_USER: synkk
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - pgdata:/var/lib/postgresql/data

  redis:
    image: redis:alpine
    restart: unless-stopped
    volumes:
      - redisdata:/data

volumes:
  synkk-storage:
  pgdata:
  redisdata:</code></pre>
                        </div>

                        <h3>Environment Variables Checklist</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left font-mono text-xs border border-zinc-200">
                                <thead>
                                    <tr class="bg-zinc-100">
                                        <th class="p-2 border-b">Variable</th>
                                        <th class="p-2 border-b">Default</th>
                                        <th class="p-2 border-b">Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b">
                                        <td class="p-2 font-bold">APP_KEY</td>
                                        <td class="p-2">-</td>
                                        <td class="p-2">32-char encryption key generated with <code>php artisan key:generate</code></td>
                                    </tr>
                                    <tr class="border-b">
                                        <td class="p-2 font-bold">SYNKK_STORAGE_DISK</td>
                                        <td class="p-2">local</td>
                                        <td class="p-2">Vault blob storage driver: <code>local</code> or <code>s3</code></td>
                                    </tr>
                                    <tr class="border-b">
                                        <td class="p-2 font-bold">DLP_ENABLED</td>
                                        <td class="p-2">true</td>
                                        <td class="p-2">Prevents accidental pushes of API keys and AWS secrets</td>
                                    </tr>
                                    <tr>
                                        <td class="p-2 font-bold">RATE_LIMIT_PER_MINUTE</td>
                                        <td class="p-2">120</td>
                                        <td class="p-2">API throttle ceiling per device token</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- SECTION 8: ROADMAP -->
                    <section id="roadmap" class="synkk-docs-section">
                        <h2><span>08 /</span> Ecosystem Roadmap</h2>
                        <div class="grid gap-4 md:grid-cols-3 my-4">
                            <div class="p-4 rounded-xl border border-emerald-500/30 bg-emerald-50 text-emerald-950">
                                <span class="px-2 py-0.5 rounded bg-emerald-700 text-white font-mono text-[10px] uppercase font-bold">Phase 1 · Live</span>
                                <h4 class="font-bold text-base mt-2 mb-1">Foundation Release</h4>
                                <p class="text-xs text-emerald-900 leading-relaxed">
                                    Local-first Markdown sync engine, SHA-256 manifests, Instant QR pairing, Safety Shield 10% guard, path permissions, and Docker deployment.
                                </p>
                            </div>
                            <div class="p-4 rounded-xl border border-emerald-500/30 bg-emerald-50 text-emerald-950">
                                <span class="px-2 py-0.5 rounded bg-emerald-700 text-white font-mono text-[10px] uppercase font-bold">Phase 2 · Live</span>
                                <h4 class="font-bold text-base mt-2 mb-1">Safety, Collab &amp; Transport</h4>
                                <p class="text-xs text-emerald-900 leading-relaxed">
                                    3-way diff sandbox, CRDT multiplayer editing, zero-knowledge E2EE (AES-256-GCM), on-demand ghost files, and 2-second QR pairing.
                                </p>
                            </div>
                            <div class="p-4 rounded-xl border border-emerald-500/30 bg-emerald-50 text-emerald-950">
                                <span class="px-2 py-0.5 rounded bg-emerald-700 text-white font-mono text-[10px] uppercase font-bold">Phase 3 · Live</span>
                                <h4 class="font-bold text-base mt-2 mb-1">Agentic Knowledge &amp; RAG</h4>
                                <p class="text-xs text-emerald-900 leading-relaxed">
                                    Self-hosted vector embeddings, hybrid semantic search, [[wikilink]] graph traversal, and private local LLM copilots querying your vault with zero cloud leakage.
                                </p>
                            </div>
                            <div class="p-4 rounded-xl border border-amber-500/30 bg-amber-50 text-amber-950">
                                <span class="px-2 py-0.5 rounded bg-amber-700 text-white font-mono text-[10px] uppercase font-bold">Phase 4 · Next Up</span>
                                <h4 class="font-bold text-base mt-2 mb-1">Autonomous Note Agents &amp; Canvas</h4>
                                <p class="text-xs text-amber-900 leading-relaxed">
                                    Autonomous background research agents synthesizing new notes, periodic health audits, and visual Obsidian .canvas synthesis.
                                </p>
                            </div>
                        </div>
                    </section>

                </div>

            </div>
        </main>

        <!-- Footer -->
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
                        <a href="{{ route('home') }}" aria-label="Synkk home">
                            <img src="/images/synkk-logo.svg" alt="Synkk — Obsidian everywhere" width="689" height="270" loading="lazy">
                        </a>
                        <p>Portable notes. Visible safety. Infrastructure you control.</p>
                    </div>
                    <nav aria-label="Footer navigation">
                        <a href="{{ route('home') }}#product">Product</a>
                        <a href="{{ route('home') }}#workflow">How it works</a>
                        <a href="{{ route('home') }}#safety">Safety</a>
                        <a href="{{ route('home') }}#comparison">Why Synkk</a>
                        <a href="{{ route('home') }}#moonshot">Moonshot</a>
                        <a href="{{ route('home') }}#pricing">Pricing</a>
                        <a href="{{ route('home') }}#roadmap">Roadmap</a>
                        <a href="{{ route('home') }}#faq">FAQ</a>
                        <a href="{{ route('public.docs') }}">Documentation</a>
                        <a href="{{ $pluginReleaseUrl }}" target="_blank" rel="noopener noreferrer">GitHub ↗</a>
                    </nav>
                </div>
                <div class="synkk-footer__bottom">
                    <span>© {{ date('Y') }} Synkk</span>
                    <span>Obsidian everywhere</span>
                    <span>Built in public</span>
                </div>
            </div>
        </footer>

        <!-- Interactive Filter Script for Documentation Search & Smooth Navigation -->
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const searchInput = document.getElementById('docs-search-input');
                const sections = document.querySelectorAll('.synkk-docs-section');
                const navLinks = document.querySelectorAll('.synkk-docs-sidebar__link');

                if (searchInput) {
                    searchInput.addEventListener('input', (e) => {
                        const query = e.target.value.toLowerCase().trim();

                        sections.forEach(section => {
                            const text = section.textContent.toLowerCase();
                            if (query === '' || text.includes(query)) {
                                section.style.display = 'block';
                            } else {
                                section.style.display = 'none';
                            }
                        });
                    });
                }

                // Active nav highlight on scroll
                const observerOptions = {
                    root: null,
                    rootMargin: '-20% 0px -70% 0px',
                    threshold: 0
                };

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const id = entry.target.getAttribute('id');
                            navLinks.forEach(link => {
                                if (link.getAttribute('href') === `#${id}`) {
                                    link.classList.add('is-active');
                                } else {
                                    link.classList.remove('is-active');
                                }
                            });
                        }
                    });
                }, observerOptions);

                sections.forEach(section => observer.observe(section));
            });
        </script>
    </body>
</html>
