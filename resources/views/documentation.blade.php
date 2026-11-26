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
            $pluginReleaseUrl = 'https://github.com/tawandajosephmutsena/synk-obsidian-plugin/releases/latest';
            $pluginObsidianUri = 'obsidian://show-plugin?id=synkk-sync';
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
                    <a href="{{ route('about') }}">About</a>
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
                            <a href="{{ route('about') }}">About &amp; Philosophy <span>04</span></a>
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
                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-200 text-emerald-950 font-mono text-xs font-semibold">Laravel 13 · Livewire 4 · PHP 8.5</span>
                    <span class="px-2.5 py-0.5 rounded-full bg-zinc-200 text-zinc-800 font-mono text-xs font-medium">Obsidian Plugin v1.1.1</span>
                </div>
                <div>
                    <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-zinc-900 mb-3">
                        Mastering Synkk. <span class="text-emerald-800 font-serif italic">Step by step.</span>
                    </h1>
                    <p class="text-lg text-zinc-700 max-w-3xl leading-relaxed">
                        Complete documentation for setting up local-first Obsidian sync, path permissions, E2EE, Livewire 4 Portals, Vault AI &amp; Remote MCP, REST API Protocol v2, and Docker / VPS self-hosting.
                    </p>
                </div>

                <!-- Interactive Section Filter Search -->
                <div class="synkk-docs-search">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    <input type="text" id="docs-search-input" placeholder="Search documentation (e.g., QR pairing, MCP, E2EE, REST API, Docker, Safety Shield)..." aria-label="Search documentation">
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
                            <a href="#preflight-engine" class="synkk-docs-sidebar__link">Pre-Flight Migration Engine</a>
                            <a href="#safety-shield" class="synkk-docs-sidebar__link">Safety Shield Guard</a>
                            <a href="#snapshots" class="synkk-docs-sidebar__link">Snapshots &amp; Rollbacks</a>
                        </div>
                    </div>

                    <div class="synkk-docs-sidebar__group">
                        <div class="synkk-docs-sidebar__title">
                            <span>05 / PUBLISHING &amp; PORTALS</span>
                        </div>
                        <div class="synkk-docs-sidebar__nav">
                            <a href="#portals" class="synkk-docs-sidebar__link">Livewire Vault Portals</a>
                            <a href="#portal-themes" class="synkk-docs-sidebar__link">Theme Presets &amp; Search</a>
                            <a href="{{ url('/p/synkk-docs') }}" target="_blank" class="synkk-docs-sidebar__link font-bold text-emerald-600 dark:text-emerald-400">Live Docs Portal ↗</a>
                        </div>
                    </div>

                    <div class="synkk-docs-sidebar__group">
                        <div class="synkk-docs-sidebar__title">
                            <span>06 / AI &amp; REMOTE MCP</span>
                        </div>
                        <div class="synkk-docs-sidebar__nav">
                            <a href="#ai-mcp" class="synkk-docs-sidebar__link">Vault AI, Local Ollama &amp; Inbox</a>
                            <a href="#mcp-server" class="synkk-docs-sidebar__link">Remote MCP Server</a>
                        </div>
                    </div>

                    <div class="synkk-docs-sidebar__group">
                        <div class="synkk-docs-sidebar__title">
                            <span>07 / DEVELOPER &amp; OPS</span>
                        </div>
                        <div class="synkk-docs-sidebar__nav">
                            <a href="#rest-api" class="synkk-docs-sidebar__link">REST API Reference</a>
                            <a href="#docker" class="synkk-docs-sidebar__link">Docker Self-Hosting</a>
                            <a href="#remote-wipe" class="synkk-docs-sidebar__link">Remote Device Wipe &amp; E2EE</a>
                        </div>
                    </div>

                    <div class="synkk-docs-sidebar__group">
                        <div class="synkk-docs-sidebar__title">
                            <span>08 / ECOSYSTEM</span>
                        </div>
                        <div class="synkk-docs-sidebar__nav">
                            <a href="#roadmap" class="synkk-docs-sidebar__link">Roadmap &amp; Releases</a>
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
                                <h3>Install the Obsidian Plugin (v1.1.1)</h3>
                                <p>
                                    <strong>Recommended (Community Plugins):</strong> Open Obsidian (<code>1.7.2+</code>) → <strong>Settings → Community plugins → Browse</strong>, search for <strong>Synkk Team Vault Sync</strong> (or open <a href="{{ $pluginObsidianUri }}" class="underline font-semibold"><code>obsidian://show-plugin?id=synkk-sync</code></a>), click <strong>Install</strong>, and toggle it <strong>On</strong>.
                                </p>
                                <p>
                                    <strong>Manual GitHub Release:</strong> You can also download <code>main.js</code>, <code>manifest.json</code>, and <code>styles.css</code> from the <a href="{{ $pluginReleaseUrl }}" target="_blank" rel="noopener noreferrer" class="underline font-semibold">latest GitHub release</a> and place them inside your vault's plugin directory:
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
                                    Open Obsidian → <strong>Settings → Synkk Vault Sync</strong>. Use <strong>⚡ One-Scan Quick Connect</strong> (scan QR code, paste a <code>/pair?...</code> or <code>obsidian://synkk-pair?...</code> link, or upload a QR screenshot) or enter your Server API URL (ending in <code>/api/v1</code>) and Device Sync Token manually, click <strong>Verify &amp; Load Vaults</strong>, select your <strong>Target Vault</strong>, and launch the <strong>Migration Wizard</strong> for your first sync.
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
                            On the server side (Laravel 13 + SQLite WAL), files are organized by tenant, team, and vault:
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
                        <p>
                            Synkk separates <strong>Team Roles</strong> (who manages the workspace) from <strong>Vault Path Permissions</strong> (who reads or writes specific folders). Team Owners and Admins always resolve to full <code>read_write</code> access across team vaults.
                        </p>
                        <div class="overflow-x-auto my-4">
                            <table class="w-full text-left text-sm border border-zinc-200 rounded-xl">
                                <thead class="bg-zinc-100 font-mono text-xs text-zinc-700 uppercase">
                                    <tr>
                                        <th class="p-3 border-b">Team Role</th>
                                        <th class="p-3 border-b">Vault Access</th>
                                        <th class="p-3 border-b">Manage Vaults &amp; ACLs</th>
                                        <th class="p-3 border-b">Manage Members &amp; Team</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 font-mono text-xs">
                                    <tr>
                                        <td class="p-3 font-bold text-zinc-900">Owner</td>
                                        <td class="p-3 text-emerald-600 font-bold">✓ Always read_write</td>
                                        <td class="p-3 text-emerald-600 font-bold">✓ Full Access</td>
                                        <td class="p-3 text-emerald-600 font-bold">✓ Full (Invite, Roles, Remove, Delete Team)</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 font-bold text-zinc-900">Admin</td>
                                        <td class="p-3 text-emerald-600 font-bold">✓ Always read_write</td>
                                        <td class="p-3 text-emerald-600 font-bold">✓ Full Access</td>
                                        <td class="p-3 text-amber-600">~ Update Team &amp; Invitations only</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 font-bold text-zinc-900">Member</td>
                                        <td class="p-3 text-emerald-600">Scoped by Vault Path ACLs (<code>read_write</code> / <code>read_only</code> / <code>hidden</code>)</td>
                                        <td class="p-3 text-rose-600">✗ Denied (can update vaults they created)</td>
                                        <td class="p-3 text-rose-600">✗ Denied</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div id="permissions">
                            <h3>Granular Path-Based Access Rules</h3>
                            <p>
                                Path rules assign <code>read_write</code>, <code>read_only</code>, or <code>hidden</code> per member or team-wide using prefix/glob matching. User-specific rules take priority over team-wide rules, and the longest (most specific) prefix wins before falling back to the vault's default permission:
                            </p>
                            <div class="synkk-code-box">
                                <div class="synkk-code-box__header">
                                    <span><i></i> Example Path Rule Configuration</span>
                                </div>
                                <pre><code>Private/**          -> hidden     (Excluded from manifests for non-admins)
Finance/**          -> hidden     (Visible only to Owners/Admins or named users)
Clients/Acme/**     -> read_only  (Member can pull &amp; read; push returns 403)
Projects/**         -> read_write (Two-way sync &amp; collaborative editing)</code></pre>
                            </div>

                            <div class="synkk-callout synkk-callout--info">
                                <span class="synkk-callout__icon">🔒</span>
                                <div class="synkk-callout__body">
                                    <h4>Zero-Leak Architecture</h4>
                                    <p>When a device requests a vault manifest, change log, search result, or MCP tool response, the Synkk server filters out <code>hidden</code> paths <em>before</em> sending the JSON response. Unauthorized devices cannot discover the filenames of hidden subdirectories.</p>
                                </div>
                            </div>
                        </div>

                        <div id="dlp-security">
                            <h3>Data Loss Prevention (DLP) &amp; Secret Scanning</h3>
                            <p>
                                On standard (non-E2EE) vaults, Synkk runs a server-side plaintext secret scanner during upload and flags detected credentials in the vault change log and workspace dashboard threat feed. When sending excerpts to external (non-local) AI providers, Synkk also automatically redacts matched secrets (<code>[REDACTED:...]</code>) before outbound dispatch.
                            </p>
                            <p>Detected credential patterns include:</p>
                            <ul>
                                <li>AWS Access Keys (<code>AKIA...</code>)</li>
                                <li>OpenAI, Anthropic, Stripe, GitHub, Slack &amp; Synkk tokens (<code>sk-...</code>, <code>sk-ant-...</code>, <code>ghp_...</code>, <code>xoxb-...</code>, <code>synkk_...</code>)</li>
                                <li>Private PEM / SSH Keys (<code>-----BEGIN ... PRIVATE KEY-----</code>)</li>
                                <li>High-entropy generic API keys and secrets</li>
                            </ul>
                            <p class="text-sm text-zinc-600"><strong>E2EE Disclosure:</strong> Server-side DLP scanning cannot inspect end-to-end encrypted ciphertext and is intentionally skipped on E2EE vaults.</p>
                        </div>
                    </section>

                    <!-- SECTION 5: PRE-FLIGHT MIGRATION & SAFETY SHIELD -->
                    <section id="safety-shield" class="synkk-docs-section">
                        <h2><span>05 /</span> Pre-Flight Migration Engine, Safety Shield &amp; Data Loss Protection</h2>
                        <p>
                            Migrating an established vault with thousands of notes is the single most delicate moment in a knowledge worker's workflow. Synkk eliminates migration anxiety with deterministic dry-run simulations and active atomic protection.
                        </p>

                        <div id="preflight-engine">
                            <h3>First-Sync Pre-Flight Migration Wizard</h3>
                            <p>
                                Launch <strong>Vault Migration: Run Pre-Flight Diagnostic &amp; Migration Wizard</strong> from the Obsidian Command Palette or Settings before syncing a large vault:
                            </p>
                            <ul>
                                <li><strong>Step 1 — Local Diagnostic Scan:</strong> Categorizes files across Markdown, Canvases, Images, Audio/Video, PDFs, Config, and Other. Flags <code>.trash/</code>, <code>.git/</code>, oversized files (&gt;25 MB), trailing spaces/dots, and illegal cross-platform characters (<code>: * ? " &lt; &gt; | \</code>) with 1-click <strong>Auto-Sanitize</strong>.</li>
                                <li><strong>Step 2 — Exclusion Presets:</strong> Preview storage and bandwidth savings and toggle Mobile Ghost Files.</li>
                                <li><strong>Step 3 — Server Simulation Dry-Run:</strong> Calls <code>POST /api/v1/vaults/{slug}/preflight</code> without uploading file bytes to verify team storage quota and preview exact upload, download, and identical-skip counts.</li>
                                <li><strong>Step 4 — Guarded Migration Sync:</strong> Executes the initial sync under Atomic Safety Shield protection.</li>
                            </ul>
                        </div>

                        <h3>The Atomic Safety Shield &amp; Deletion Threshold</h3>
                        <p>
                            The Obsidian plugin enforces a client-side deletion circuit breaker before applying remote or local deletions. By default, if a sync attempts to delete more than <strong>10% of your tracked vault files</strong> (configurable from <code>1%</code> to <code>100%</code> in Settings → <strong>Atomic Safety Shield</strong>), synchronization halts immediately. The server pre-flight endpoint also reports recommended bulk-deletion guidance (20% / &gt;10 files).
                        </p>
                        <div class="synkk-callout synkk-callout--warning">
                            <span class="synkk-callout__icon">🛡️</span>
                            <div class="synkk-callout__body">
                                <h4>Atomic Safety Shield Active</h4>
                                <p>Halted syncs require explicit one-time confirmation via <strong>Allow next guarded sync</strong> in plugin settings or clicking the status bar prompt. This ensures an accidental local wipe or misconfigured folder never propagates silently.</p>
                            </div>
                        </div>

                        <div id="snapshots">
                            <h3>Pre-Mutation Snapshots &amp; Rollback Engine</h3>
                            <p>
                                Before any local file is overwritten or deleted by a remote change, the Obsidian plugin writes a backup into <code>.synkk/snapshots/&lt;timestamp&gt;/</code>. On the server, every file modification retains up to <strong>25 historical versions</strong> (configurable via <code>SYNKK_VERSION_RETENTION_LIMIT</code>) with 1-click restore from the web workspace.
                            </p>
                        </div>
                    </section>

                    <!-- SECTION 6: INTERACTIVE LIVEWIRE VAULT PORTALS -->
                    <section id="portals" class="synkk-docs-section">
                        <h2><span>06 /</span> Interactive Livewire Vault Portals</h2>
                        <p>
                            Publish any non-E2EE Obsidian vault—or a single subfolder via <code>root_path</code>—as an interactive Livewire 4 web portal at <code>/p/{slug}</code> with zero static build pipelines.
                        </p>

                        <div class="synkk-step-box">
                            <div class="synkk-step-box__num">🌐</div>
                            <div class="synkk-step-box__body">
                                <h3>Zero-Config Publishing, Password Gate, Portal AI &amp; <code>/llms.txt</code></h3>
                                <p>
                                    In the Synkk Web Dashboard, navigate to <strong>Portals</strong> and click <strong>Create Portal</strong>. Toggle public vs. team-only access, optionally add a bcrypt password gate (min 8 characters, rate-limited), scope to a subfolder, enable the interactive Graph View and <strong>Ask This Portal</strong> AI drawer (with daily question limits), and expose an automatic <code>/p/{slug}/llms.txt</code> index for AI agents.
                                </p>
                            </div>
                        </div>

                        <div id="portal-themes">
                            <h3>4 Layouts &amp; 5 Color Themes</h3>
                            <p>
                                Combine any of 4 structural layouts with 5 color themes (<strong>Obsidian Noir</strong>, <strong>Slate Luxe</strong>, <strong>Midnight Emerald</strong>, <strong>Paper Craft</strong>, <strong>Amber Gold</strong>):
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 my-4">
                                <div class="p-3.5 rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                    <div class="font-bold text-sm text-zinc-900 dark:text-white">Docs Layout (<code>docs</code>)</div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Technical documentation layout with folder sidebar, center article view, breadcrumbs, and table of contents.</p>
                                </div>
                                <div class="p-3.5 rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                    <div class="font-bold text-sm text-zinc-900 dark:text-white">Bento Layout (<code>bento</code>)</div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Card-based knowledge showcase ideal for digital gardens, hubs, and curated wikis.</p>
                                </div>
                                <div class="p-3.5 rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                    <div class="font-bold text-sm text-zinc-900 dark:text-white">Dashboard Layout (<code>dashboard</code>)</div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Structured workspace layout with quick search (<code>Cmd+K</code>), tag filters, backlinks, and graph exploration.</p>
                                </div>
                                <div class="p-3.5 rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                    <div class="font-bold text-sm text-zinc-900 dark:text-white">Minimal Layout (<code>minimal</code>)</div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Typography-first reading experience for essays, changelogs, and long-form notes.</p>
                                </div>
                            </div>
                        </div>

                        <div class="synkk-callout synkk-callout--tip">
                            <span class="synkk-callout__icon">🚀</span>
                            <div class="synkk-callout__body">
                                <h4>Experience a Live Synkk Portal</h4>
                                <p>Explore our official interactive documentation portal rendered directly from a Synkk Obsidian vault: <a href="{{ url('/p/synkk-docs') }}" target="_blank" class="font-bold underline text-emerald-600 dark:text-emerald-400">View Synkk Documentation Portal ↗</a> (also available at <code>/p/synkk-docs/llms.txt</code>). <em>Note: Custom domain mapping is on the roadmap; portals are currently served at <code>/p/{slug}</code>.</em></p>
                            </div>
                        </div>
                    </section>

                    <!-- SECTION 7: VAULT AI, LOCAL OLLAMA & PERMISSIONED REMOTE MCP -->
                    <section id="ai-mcp" class="synkk-docs-section">
                        <h2><span>07 /</span> Vault AI Suite, Local E2EE AI &amp; Remote MCP Server</h2>
                        <p>
                            Synkk AI is <strong>100% opt-in</strong> at both the workspace and per-vault level, with strict path ACL enforcement, per-vault excluded folder lists, and automatic DLP redaction before sending excerpts to external models.
                        </p>

                        <h3>Provider Modes (No Vendor Lock-In)</h3>
                        <ul>
                            <li><strong>Local Ollama / OpenAI-Compatible (Works with E2EE):</strong> On E2EE vaults, server AI is automatically blocked; the Obsidian plugin's <strong>Synkk AI</strong> sidebar runs local BM25 + 1-hop <code>[[wikilink]]</code> graph retrieval and calls your local Ollama (<code>http://localhost:11434</code>, e.g., <code>llama3.2</code>) or LM Studio directly from your device.</li>
                            <li><strong>Bring Your Own Key (BYOK — Included Free):</strong> Connect OpenAI, Anthropic, Google Gemini, OpenRouter, Ollama, or any OpenAI-compatible server in <strong>Team Settings → AI Add-On</strong> with optional monthly budget caps.</li>
                            <li><strong>Managed Cloud AI Add-On ($12/workspace/mo, optional):</strong> Turnkey hosted AI credits ($12/mo pool; Cloud plans include a one-time $2 trial credit) using OpenRouter chat models + OpenAI <code>text-embedding-3-small</code> embeddings.</li>
                        </ul>

                        <h3>Human-in-the-Loop AI Workflows</h3>
                        <ul>
                            <li><strong>Dockable AI Sidebar &amp; Streaming Vault Copilot:</strong> Multi-turn tool-calling agent (<code>search_notes</code>, <code>read_note</code>, <code>traverse_graph</code>, <code>recent_activity</code>, <code>propose_note</code>) streaming over Reverb in both Obsidian and the web workspace.</li>
                            <li><strong>AI Proposal Diff Inbox:</strong> Proposed note creations, edits, appends, and moves stage with line-by-line diffs and staleness checks until a human clicks <strong>Approve &amp; Sync</strong> (with optional inline override editing) or <strong>Reject</strong>.</li>
                            <li><strong>Vault Gardener &amp; Team Digests:</strong> Automated inbox triage, wikilink/contradiction suggestions, and scheduled daily/weekly team digests delivered to Markdown notes, the team feed, or opt-in email.</li>
                            <li><strong>AI Smart Merge:</strong> Semantic 3-way conflict merge preview inside the Visual Conflict Sandbox (uses server AI on standard vaults, Local AI on E2EE vaults).</li>
                        </ul>

                        <div id="mcp-server">
                            <h3>Permissioned Remote MCP Server (<code>POST /api/v1/mcp</code>)</h3>
                            <p>
                                Connect Claude Desktop, Cursor, Windsurf, or Antigravity directly to your team's non-E2EE vaults over JSON-RPC 2.0 Streamable HTTP. Create a dedicated MCP token in <strong>Team Settings → AI Add-On</strong> (MCP tokens do <strong>not</strong> consume Obsidian device seats) with one of three scopes: <code>mcp_read</code>, <code>mcp_propose</code>, or <code>mcp_write</code> (direct writes also require enabling <em>Allow direct MCP writes</em> per vault).
                            </p>
                            <p><strong>7 Native MCP Tools:</strong> <code>synkk_list_vaults</code>, <code>synkk_search_notes</code>, <code>synkk_read_note</code>, <code>synkk_traverse_graph</code>, <code>synkk_recent_activity</code>, <code>synkk_propose_note</code>, <code>synkk_write_note</code>.</p>
                            <div class="synkk-code-box">
                                <div class="synkk-code-box__header">
                                    <span><i></i> MCP Client Config (Claude Desktop / Cursor / Windsurf)</span>
                                </div>
                                <pre><code>{
  "mcpServers": {
    "synkk": {
      "url": "{{ url('/api/v1/mcp') }}",
      "headers": {
        "Authorization": "Bearer synkk_your_mcp_token"
      }
    }
  }
}</code></pre>
                            </div>
                        </div>
                    </section>

                    <!-- SECTION 8: REST API REFERENCE -->
                    <section id="rest-api" class="synkk-docs-section">
                        <h2><span>08 /</span> REST API Reference</h2>
                        <p>
                            Base API Endpoint: <code class="font-mono bg-zinc-200 px-2 py-0.5 rounded text-zinc-900">{{ url('/api/v1') }}</code>
                        </p>
                        <p>
                            All authenticated API requests require <code>Authorization: Bearer synkk_...</code> and protocol header <code>X-Synkk-Protocol: 2</code> (rate-limited to 300 req/min).
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

                        <!-- API Endpoint 17: Pre-Flight Simulation & Quota Verification -->
                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--post">POST</span>
                                <span class="synkk-api-path">/vaults/{slug}/preflight</span>
                            </div>
                            <p class="text-sm text-zinc-700">Validates initial sync payload against team storage limits, matches file hashes without transferring data, and returns simulation metrics (upload, download, identical skip counts, bandwidth saved).</p>
                            <div class="synkk-code-box">
                                <div class="synkk-code-box__header">
                                    <span>JSON Request Payload</span>
                                </div>
                                <pre><code>{
  "total_files": 140,
  "total_bytes": 24800000,
  "categories": { "markdown": { "count": 120, "bytes": 4800000 } },
  "files": [ { "path": "Index.md", "size": 1200, "sha256": "4b22..." } ]
}</code></pre>
                            </div>
                        </div>

                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--post">POST</span>
                                <span class="synkk-api-path">/vaults/{slug}/conflicts/smart-merge</span>
                            </div>
                            <p class="text-sm text-zinc-700">Generates a semantic AI merge preview reconciling a canonical note and conflict copy without mutating disk files.</p>
                        </div>

                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--post">POST</span>
                                <span class="synkk-api-path">/vaults/{slug}/delete</span>
                            </div>
                            <p class="text-sm text-zinc-700">Soft-deletes a file at the given path, incrementing vault version and recording a tombstone (pruned after 30 days).</p>
                        </div>

                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--post">POST</span>
                                <span class="synkk-api-path">/vaults/{slug}/ai/threads · /messages · /proposals · /gardener/run · /digests/generate</span>
                            </div>
                            <p class="text-sm text-zinc-700">Vault AI Agent conversation threads, streaming Reverb runs, Proposal Diff Inbox approval/rejection, Vault Gardener, and Team Digests.</p>
                        </div>

                        <div class="synkk-api-endpoint">
                            <div class="synkk-api-endpoint__title">
                                <span class="synkk-api-method synkk-api-method--post">POST</span>
                                <span class="synkk-api-path">/mcp</span>
                            </div>
                            <p class="text-sm text-zinc-700">JSON-RPC 2.0 Streamable HTTP Remote MCP Server (protocol <code>2025-03-26</code>) exposing 7 permissioned vault tools and note resources to external AI clients.</p>
                        </div>

                        <h3>HTTP Status Code Reference</h3>
                        <ul class="font-mono text-sm space-y-1">
                            <li><strong class="text-emerald-600">200 / 201 OK:</strong> Request succeeded cleanly (or upload returned <code>status: "conflict"</code> with conflict copy created).</li>
                            <li><strong class="text-amber-600">401 Unauthorized:</strong> Invalid or revoked device/MCP token.</li>
                            <li><strong class="text-amber-600">402 Payment Required:</strong> Team storage quota exceeded on upload/batch-sync.</li>
                            <li><strong class="text-amber-600">403 Forbidden:</strong> Token lacks required path ACL, scope, IP allowlist, or vault is E2EE-blocked for server AI/MCP.</li>
                            <li><strong class="text-rose-600">410 Gone:</strong> Pairing session expired/consumed, or device token was remotely wiped by admin (triggers local token/state purge).</li>
                            <li><strong class="text-rose-600">422 Unprocessable Entity:</strong> Validation failure, pre-flight quota deficit, or unencrypted payload rejected on an E2EE vault.</li>
                            <li><strong class="text-rose-600">426 Upgrade Required:</strong> Client missing <code>X-Synkk-Protocol: 2</code> header.</li>
                            <li><strong class="text-amber-600">429 Too Many Requests:</strong> Rate limit hit (plugin retries automatically honoring <code>Retry-After</code>).</li>
                        </ul>
                    </section>

                    <!-- SECTION 9: DOCKER & SELF-HOSTING -->
                    <section id="docker" class="synkk-docs-section">
                        <h2><span>09 /</span> Docker &amp; Self-Hosting Guide</h2>
                        <p>
                            Synkk ships as a single all-in-one Docker container (<strong>Nginx + PHP 8.5-FPM + Laravel 13 + Reverb WebSockets + Queue Worker + Scheduler</strong> managed by Supervisord) backed by embedded <strong>SQLite WAL + FTS5</strong>. Deploy on any Linux VPS, homelab server, or local machine.
                        </p>

                        <h3>Option A: VPS Turnkey Installer (Ubuntu, Debian, AlmaLinux, Rocky)</h3>
                        <p>
                            Point your DNS A/AAAA record to your VPS and allow ports <code>80</code> and <code>443</code>. Clone the repository and run <code>install.sh</code> as root — it installs Docker if needed, configures automatic Let's Encrypt HTTPS via Caddy, and bootstraps your admin account:
                        </p>
                        <div class="synkk-code-box">
                            <div class="synkk-code-box__header">
                                <span>VPS Installer (Caddy HTTPS + Docker)</span>
                                <button class="synkk-code-box__copy" onclick="navigator.clipboard.writeText('git clone --recurse-submodules https://github.com/tawandajosephmutsena/synkk.git && cd synkk && sudo SYNKK_DOMAIN=vault.example.com SYNKK_ADMIN_EMAIL=you@example.com bash install.sh')">Copy</button>
                            </div>
                            <pre><code>git clone --recurse-submodules https://github.com/tawandajosephmutsena/synkk.git
cd synkk
sudo SYNKK_DOMAIN=vault.example.com SYNKK_ADMIN_EMAIL=you@example.com bash install.sh</code></pre>
                        </div>

                        <h3>Option B: Production Docker Compose</h3>
                        <p>
                            Clone the repo, populate <code>.env.production</code> from <code>.env.production.example</code> (with generated <code>APP_KEY</code>, <code>REVERB_APP_KEY</code>, <code>REVERB_APP_SECRET</code>, and first-boot <code>SYNKK_BOOTSTRAP_EMAIL</code> / <code>SYNKK_BOOTSTRAP_PASSWORD</code>), then build and start the container on loopback port <code>8000</code> behind your HTTPS reverse proxy (forwarding WebSocket upgrades on <code>/app/</code>):
                        </p>
                        <div class="synkk-code-box">
                            <div class="synkk-code-box__header">
                                <span>docker-compose.prod.yml</span>
                            </div>
                            <pre><code>services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    image: synkk/app:latest
    restart: unless-stopped
    ports:
      - "127.0.0.1:8000:80"
    env_file:
      - .env.production
    volumes:
      - synkk_storage:/var/www/html/storage

volumes:
  synkk_storage:</code></pre>
                        </div>

                        <h3>Key Environment Variables</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left font-mono text-xs border border-zinc-200">
                                <thead>
                                    <tr class="bg-zinc-100">
                                        <th class="p-2 border-b">Variable</th>
                                        <th class="p-2 border-b">Default / Example</th>
                                        <th class="p-2 border-b">Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b">
                                        <td class="p-2 font-bold">APP_KEY</td>
                                        <td class="p-2"><code>base64:...</code></td>
                                        <td class="p-2">Required 32-byte key (container refuses to start without a valid key).</td>
                                    </tr>
                                    <tr class="border-b">
                                        <td class="p-2 font-bold">APP_URL</td>
                                        <td class="p-2"><code>https://vault.example.com</code></td>
                                        <td class="p-2">Canonical public HTTPS URL (use <code>http://localhost:8000</code> for local testing).</td>
                                    </tr>
                                    <tr class="border-b">
                                        <td class="p-2 font-bold">REVERB_APP_KEY / SECRET</td>
                                        <td class="p-2"><code>openssl rand -hex</code></td>
                                        <td class="p-2">Unique credentials for internal Laravel Reverb WebSockets (CRDT &amp; AI streaming).</td>
                                    </tr>
                                    <tr class="border-b">
                                        <td class="p-2 font-bold">SYNKK_STORAGE_DISK</td>
                                        <td class="p-2"><code>local</code></td>
                                        <td class="p-2">Vault blob storage driver: <code>local</code> (<code>storage/app/private</code>) or <code>s3</code>.</td>
                                    </tr>
                                    <tr>
                                        <td class="p-2 font-bold">SYNKK_BOOTSTRAP_EMAIL / PASSWORD</td>
                                        <td class="p-2">First boot only</td>
                                        <td class="p-2">Creates initial Super Admin account once if DB is empty; remove after first login.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div id="remote-wipe" class="mt-8">
                            <h3>Remote Device Wipe &amp; E2EE Security Model</h3>
                            <p>
                                If a laptop or phone is lost or stolen, open <strong>Devices</strong> in your Synkk dashboard and click <strong>Remote Wipe</strong>. The server immediately revokes the device token and responds with <strong>HTTP 410</strong> (<code>action: "remote_wipe"</code>) on the device's next request, causing the Obsidian plugin to purge its stored token and local sync state file.
                            </p>
                            <p>
                                For vaults requiring zero-knowledge content protection, enable <strong>E2EE</strong> in plugin settings (WebCrypto <code>AES-256-GCM</code> + <code>PBKDF2-SHA-256</code>, 100,000 iterations). Passphrases remain strictly in volatile RAM and are never written to disk; note that filenames/paths stay plaintext metadata, and E2EE vaults disable server-side search, Portals, DLP, MCP, and cloud AI (use Local Ollama AI instead).
                            </p>
                        </div>
                    </section>

                    <!-- SECTION 10: ROADMAP -->
                    <section id="roadmap" class="synkk-docs-section">
                        <h2><span>10 /</span> Ecosystem Roadmap</h2>
                        <div class="grid gap-4 md:grid-cols-2 my-4">
                            <div class="p-4 rounded-xl border border-emerald-500/30 bg-emerald-50 text-emerald-950">
                                <span class="px-2 py-0.5 rounded bg-emerald-700 text-white font-mono text-[10px] uppercase font-bold">Phase 1 · Live</span>
                                <h4 class="font-bold text-base mt-2 mb-1">Foundation &amp; Security Core</h4>
                                <p class="text-xs text-emerald-900 leading-relaxed">
                                    Whole-file SHA-256 sync, Safety Shield (10% default), Pre-Flight Wizard, One-Scan QR pairing, Path ACLs, Passkeys &amp; 2FA, Remote Wipe, Livewire 4 Portals, and Docker / Caddy VPS self-hosting.
                                </p>
                            </div>
                            <div class="p-4 rounded-xl border border-emerald-500/30 bg-emerald-50 text-emerald-950">
                                <span class="px-2 py-0.5 rounded bg-emerald-700 text-white font-mono text-[10px] uppercase font-bold">Phase 2 · Live</span>
                                <h4 class="font-bold text-base mt-2 mb-1">Collab, E2EE &amp; Ghost Files</h4>
                                <p class="text-xs text-emerald-900 leading-relaxed">
                                    Visual Conflict Sandbox + AI Smart Merge, opt-in Yjs CRDT multiplayer over Reverb, WebCrypto AES-256-GCM E2EE (with AAD on collab), and mobile Ghost Files.
                                </p>
                            </div>
                            <div class="p-4 rounded-xl border border-emerald-500/30 bg-emerald-50 text-emerald-950">
                                <span class="px-2 py-0.5 rounded bg-emerald-700 text-white font-mono text-[10px] uppercase font-bold">Phase 3 · Live</span>
                                <h4 class="font-bold text-base mt-2 mb-1">Vault AI Suite &amp; Remote MCP</h4>
                                <p class="text-xs text-emerald-900 leading-relaxed">
                                    Hybrid SQLite FTS5 + vector search, streaming AI Sidebar, human-approved Proposal Diff Inbox, Vault Gardener, Team Digests, Local E2EE Ollama AI, Portal AI + <code>/llms.txt</code>, and 7-tool Remote MCP Server.
                                </p>
                            </div>
                            <div class="p-4 rounded-xl border border-amber-500/30 bg-amber-50 text-amber-950">
                                <span class="px-2 py-0.5 rounded bg-amber-700 text-white font-mono text-[10px] uppercase font-bold">Phase 4 · Planned</span>
                                <h4 class="font-bold text-base mt-2 mb-1">Webhooks, Custom Domains, Trash UI &amp; CLI</h4>
                                <p class="text-xs text-amber-900 leading-relaxed">
                                    Signed outbound webhooks (Slack/Discord/Zapier), custom domain mapping for Vault Portals, interactive Trash / Undelete UI, headless CLI sync client, and native OIDC/SAML SSO.
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
                        <span>Obsidian Plugin v1.1.1 · Laravel 13 · Local-First Architecture</span>
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
                        <a href="{{ route('about') }}">About</a>
                        <a href="{{ route('public.docs') }}">Documentation</a>
                        <a href="{{ route('privacy') }}">Privacy</a>
                        <a href="{{ route('terms') }}">Terms</a>
                        <a href="{{ route('security') }}">Security</a>
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
