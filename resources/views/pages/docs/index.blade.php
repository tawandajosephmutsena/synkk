<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Documentation & Setup Guide')] class extends Component {
    public string $activeSection = 'quickstart';

    public function setSection(string $section): void
    {
        $this->activeSection = $section;
    }
};
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-700">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <flux:badge color="lime" size="sm">Laravel 13 · Livewire 4</flux:badge>
                <flux:badge color="zinc" size="sm">Obsidian Plugin v1.1.1</flux:badge>
            </div>
            <flux:heading size="xl" level="1">Synkk Documentation</flux:heading>
            <flux:subheading>
                Complete technical and operational reference for Synkk and the Obsidian Sync plugin.
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:button href="https://github.com/tawandajosephmutsena/synkk" target="_blank" icon="folder-git-2" variant="subtle">
                GitHub Repository
            </flux:button>
            <flux:button href="{{ route('devices.index') }}" icon="device-phone-mobile" variant="primary">
                Generate Device Token
            </flux:button>
        </div>
    </div>

    <!-- Navigation Tabs / Section Selector -->
    <div class="flex flex-wrap gap-2 border-b border-zinc-200 dark:border-zinc-700 pb-2">
        <flux:button 
            wire:click="setSection('quickstart')" 
            :variant="$activeSection === 'quickstart' ? 'filled' : 'subtle'" 
            size="sm"
            icon="bolt"
        >
            Quickstart Guide
        </flux:button>

        <flux:button 
            wire:click="setSection('mobile')" 
            :variant="$activeSection === 'mobile' ? 'filled' : 'subtle'" 
            size="sm"
            icon="device-phone-mobile"
        >
            Mobile & Community Plugins
        </flux:button>

        <flux:button 
            wire:click="setSection('permissions')" 
            :variant="$activeSection === 'permissions' ? 'filled' : 'subtle'" 
            size="sm"
            icon="lock-closed"
        >
            Roles & Path Permissions
        </flux:button>

        <flux:button 
            wire:click="setSection('conflict')" 
            :variant="$activeSection === 'conflict' ? 'filled' : 'subtle'" 
            size="sm"
            icon="shield-check"
        >
            Conflict & Hashing Engine
        </flux:button>

        <flux:button 
            wire:click="setSection('api')" 
            :variant="$activeSection === 'api' ? 'filled' : 'subtle'" 
            size="sm"
            icon="command-line"
        >
            REST API Reference
        </flux:button>

        <flux:button 
            wire:click="setSection('docker')" 
            :variant="$activeSection === 'docker' ? 'filled' : 'subtle'" 
            size="sm"
            icon="server"
        >
            Docker & Self-Hosting
        </flux:button>

        <flux:button 
            wire:click="setSection('migration')" 
            :variant="$activeSection === 'migration' ? 'filled' : 'subtle'" 
            size="sm"
            icon="arrows-up-down"
        >
            Pre-Flight & Migration
        </flux:button>

        <flux:button 
            wire:click="setSection('portals')" 
            :variant="$activeSection === 'portals' ? 'filled' : 'subtle'" 
            size="sm"
            icon="globe-alt"
        >
            Livewire Vault Portals
        </flux:button>

        <flux:button
            wire:click="setSection('ai')"
            :variant="$activeSection === 'ai' ? 'filled' : 'subtle'"
            size="sm"
            icon="sparkles"
        >
            AI, Local Ollama &amp; MCP
        </flux:button>

        <flux:button
            wire:click="setSection('roadmap')"
            :variant="$activeSection === 'roadmap' ? 'filled' : 'subtle'"
            size="sm"
            icon="map"
        >
            Safety &amp; Roadmap
        </flux:button>
    </div>

    <!-- Content Sections -->
    <div class="grid grid-cols-1 gap-6">

        <!-- ============================================================= -->
        <!-- SECTION 1: QUICKSTART GUIDE                                   -->
        <!-- ============================================================= -->
        @if ($activeSection === 'quickstart')
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Getting Started with Synkk in 4 Steps</flux:heading>
                    <flux:text class="mt-1">
                        Connect your Obsidian desktop and mobile vaults to your Synkk team in under 2 minutes.
                    </flux:text>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <!-- Step 1 -->
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="flex size-6 items-center justify-center rounded-full bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 font-mono text-xs font-bold">1</span>
                            <flux:heading size="sm">Create a Team Vault</flux:heading>
                        </div>
                        <flux:text size="sm">
                            Go to <a href="{{ route('vaults.index') }}" class="underline font-medium text-zinc-900 dark:text-white">Vaults</a> and click <strong>Create Vault</strong>. Name your vault (e.g. <code>team-brain</code>) and set default permissions.
                        </flux:text>
                    </div>

                    <!-- Step 2 -->
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="flex size-6 items-center justify-center rounded-full bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 font-mono text-xs font-bold">2</span>
                            <flux:heading size="sm">Generate Device Token</flux:heading>
                        </div>
                        <flux:text size="sm">
                            Navigate to <a href="{{ route('devices.index') }}" class="underline font-medium text-zinc-900 dark:text-white">Devices & Tokens</a>. Click <strong>Add Device Token</strong>, enter a label (e.g., <code>MacBook M3</code>), and copy the <code>synkk_...</code> key.
                        </flux:text>
                    </div>

                    <!-- Step 3 -->
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="flex size-6 items-center justify-center rounded-full bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 font-mono text-xs font-bold">3</span>
                            <flux:heading size="sm">Install Obsidian Plugin</flux:heading>
                        </div>
                        <flux:text size="sm">
                            Install <strong>Synkk Team Vault Sync (v1.1.1)</strong> directly from Obsidian's Community Plugins browser (Obsidian <code>1.7.2+</code>), or download <code>main.js</code>, <code>manifest.json</code>, and <code>styles.css</code> from the <a href="https://github.com/tawandajosephmutsena/synk-obsidian-plugin/releases/latest" target="_blank" class="underline font-medium text-zinc-900 dark:text-white">latest GitHub release</a>.
                        </flux:text>
                    </div>

                    <!-- Step 4 -->
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="flex size-6 items-center justify-center rounded-full bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 font-mono text-xs font-bold">4</span>
                            <flux:heading size="sm">Connect & Sync (Instant QR or Token)</flux:heading>
                        </div>
                        <flux:text size="sm">
                            Open Obsidian Settings → <strong>Synkk Vault Sync</strong>. Either scan or paste into <strong>⚡ Instant Quick Connect</strong> for 1-second automated pairing, or enter Server URL <code>{{ url('/api/v1') }}</code> and your Device Token!
                        </flux:text>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <flux:badge color="lime">Sync scope</flux:badge>
                        <flux:heading size="sm" class="mt-3">Choose what each device carries</flux:heading>
                        <flux:text size="sm" class="mt-2">Start with text notes and selected folders. Plugin state and community CSS can be enabled separately; device-specific layout caches should stay local.</flux:text>
                    </div>
                    <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <flux:badge color="zinc">Integrity</flux:badge>
                        <flux:heading size="sm" class="mt-3">Every accepted file is fingerprinted</flux:heading>
                        <flux:text size="sm" class="mt-2">Synkk compares SHA-256 content hashes and the client's base revision before writing. A stale upload is preserved as a conflict copy instead of silently replacing the current note.</flux:text>
                    </div>
                    <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <flux:badge color="zinc">Recovery</flux:badge>
                        <flux:heading size="sm" class="mt-3">Keep a rollback path</flux:heading>
                        <flux:text size="sm" class="mt-2">Before a remote mutation, review the change list and keep local snapshots available. Version restore creates a known-good current revision without rewriting unrelated files.</flux:text>
                    </div>
                </div>

                <div class="p-4 rounded-xl border border-lime-500/30 bg-lime-500/10 flex items-start gap-3">
                    <span class="text-xl">💡</span>
                    <div>
                        <flux:heading size="sm" class="text-zinc-900 dark:text-white font-bold">Automatic Sync &amp; Adaptive Background Relay</flux:heading>
                        <flux:text size="sm" class="text-zinc-700 dark:text-zinc-300">
                            By default, Synkk syncs on startup, on file changes (2.5s debounce), and every 5 minutes (configurable 1–30 minutes), plus an adaptive 60-second transport status relay while Obsidian is active or returns to foreground on mobile.
                        </flux:text>
                    </div>
                </div>
            </flux:card>
        @endif

        <!-- ============================================================= -->
        <!-- SECTION 2: MOBILE & COMMUNITY PLUGINS                         -->
        <!-- ============================================================= -->
        @if ($activeSection === 'mobile')
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Mobile &amp; Community Plugin Installation (iOS &amp; Android)</flux:heading>
                    <flux:text class="mt-1">
                        Synkk Team Vault Sync (v1.1.1) supports macOS, Windows, Linux, iOS, and Android (<code>isDesktopOnly: false</code>, requires Obsidian 1.7.2+).
                    </flux:text>
                </div>

                <!-- Method A -->
                <div class="p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <flux:badge color="lime">Recommended</flux:badge>
                            <flux:heading size="base">Method 1: Obsidian Community Plugins</flux:heading>
                        </div>
                    </div>
                    <ol class="list-decimal list-inside space-y-1 text-sm text-zinc-700 dark:text-zinc-300">
                        <li>Open Obsidian on desktop or mobile → <strong>Settings → Community plugins</strong> → disable Restricted Mode if needed.</li>
                        <li>Tap <strong>Browse</strong>, search for <strong>Synkk Team Vault Sync</strong>, tap <strong>Install</strong>, then toggle it <strong>On</strong>.</li>
                        <li>Open <strong>Settings → Synkk Vault Sync</strong> and tap <strong>📷 Scan QR Code</strong> (or paste a pairing link from your Devices page).</li>
                    </ol>
                </div>

                <div class="p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <flux:badge color="zinc">Manual / Air-Gapped</flux:badge>
                            <flux:heading size="base">Method 2: GitHub Release Bundle</flux:heading>
                        </div>
                    </div>
                    <ol class="list-decimal list-inside space-y-1 text-sm text-zinc-700 dark:text-zinc-300 font-mono">
                        <li>Download <code>main.js</code>, <code>manifest.json</code>, and <code>styles.css</code> from the latest GitHub release.</li>
                        <li>Place them in <code>&lt;vault&gt;/.obsidian/plugins/synkk-sync/</code>.</li>
                        <li>Open <strong>Settings → Community plugins</strong> and enable <strong>Synkk Team Vault Sync</strong>.</li>
                        <li>Pair via QR/deep link or enter your HTTPS API URL (<code>/api/v1</code>) and device token.</li>
                    </ol>
                </div>

                <div class="p-5 rounded-2xl border border-amber-500/30 bg-amber-500/10 space-y-3">
                    <div class="flex items-center gap-2">
                        <flux:badge color="amber">Mobile note</flux:badge>
                        <flux:heading size="base">Background execution is not guaranteed</flux:heading>
                    </div>
                    <flux:text size="sm">
                        iOS and Android suspend background network tasks when apps are minimized. Synkk's Adaptive Background Relay polls immediately whenever Obsidian returns to the foreground and every 60 seconds while active. Mobile Ghost Files automatically stub non-Markdown attachments over 5 MB (configurable 2–50 MB) so large vaults fit comfortably on phones.
                    </flux:text>
                </div>
            </flux:card>
        @endif

        <!-- ============================================================= -->
        <!-- SECTION 3: ROLES & PATH PERMISSIONS                           -->
        <!-- ============================================================= -->
        @if ($activeSection === 'permissions')
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Role & Path-Based Access Control</flux:heading>
                    <flux:text class="mt-1">
                        Granularly control who can read, write, and manage specific subdirectories inside your vaults.
                    </flux:text>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-zinc-200 dark:border-zinc-700 text-xs font-mono uppercase text-zinc-500">
                            <tr>
                                <th class="py-3 px-4">Team Role</th>
                                <th class="py-3 px-4">Vault Access</th>
                                <th class="py-3 px-4">Manage Vaults &amp; Path ACLs</th>
                                <th class="py-3 px-4">Manage Team &amp; Members</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 font-mono text-xs">
                            <tr>
                                <td class="py-3 px-4 font-bold text-zinc-900 dark:text-white">Owner</td>
                                <td class="py-3 px-4 text-emerald-500">✓ Always read_write</td>
                                <td class="py-3 px-4 text-emerald-500">✓ Full Access</td>
                                <td class="py-3 px-4 text-emerald-500">✓ Full (Invite, Roles, Remove, Delete)</td>
                            </tr>
                            <tr>
                                <td class="py-3 px-4 font-bold text-zinc-900 dark:text-white">Admin</td>
                                <td class="py-3 px-4 text-emerald-500">✓ Always read_write</td>
                                <td class="py-3 px-4 text-emerald-500">✓ Full Access</td>
                                <td class="py-3 px-4 text-amber-500">~ Update Team &amp; Invitations</td>
                            </tr>
                            <tr>
                                <td class="py-3 px-4 font-bold text-zinc-900 dark:text-white">Member</td>
                                <td class="py-3 px-4 text-emerald-500">Scoped by Path ACLs (read_write / read_only / hidden)</td>
                                <td class="py-3 px-4 text-rose-500">✗ Denied</td>
                                <td class="py-3 px-4 text-rose-500">✗ Denied</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 space-y-2">
                    <flux:heading size="sm">Path-Level Rules Example</flux:heading>
                    <flux:text size="sm">
                        Under any vault's Permissions tab, assign <code>read_write</code>, <code>read_only</code>, or <code>hidden</code> per member or team-wide (user rules override team rules; longest prefix wins):
                    </flux:text>
                    <pre class="font-mono text-xs bg-zinc-900 text-emerald-400 p-3 rounded-lg overflow-x-auto"><code>Private/**          -> hidden     (Excluded from manifests for non-admins)
Finance/**          -> hidden     (Visible to Owners/Admins or explicit users)
Clients/Acme/**     -> read_only  (Pulls allowed; pushes return HTTP 403)
Projects/**         -> read_write (Two-way sync &amp; collaborative editing)</code></pre>
                </div>
            </flux:card>
        @endif

        <!-- ============================================================= -->
        <!-- SECTION 4: CONFLICT & HASHING ENGINE                          -->
        <!-- ============================================================= -->
        @if ($activeSection === 'conflict')
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">File Hashing, Visual Conflict Sandbox &amp; Opt-In CRDT</flux:heading>
                    <flux:text class="mt-1">
                        How Synkk tracks whole-file changes with SHA-256, reconciles concurrent offline edits safely, and supports opt-in Yjs CRDT collaboration.
                    </flux:text>
                </div>

                <div class="grid md:grid-cols-3 gap-4">
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <flux:heading size="sm">1. SHA-256 Fingerprinting</flux:heading>
                        <flux:text size="sm">
                            Every markdown note and binary asset is hashed locally before transmission. If content matches the remote hash, zero bytes are uploaded.
                        </flux:text>
                    </div>

                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <flux:heading size="sm">2. Versioned Manifests &amp; Rollback</flux:heading>
                        <flux:text size="sm">
                            The server records up to 25 historical versions per file and checks the client's <code>base_version</code> before writing. Restore any historical snapshot from the vault web workspace in one click.
                        </flux:text>
                    </div>

                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <flux:heading size="sm">3. Safe Conflict Forks &amp; Opt-In CRDT</flux:heading>
                        <flux:text size="sm">
                            Offline collisions fork cleanly into <code>.sync-conflict-[timestamp].md</code> (or <code>.conflict-*</code> on server push) so nothing is overwritten. Enable <strong>Real-Time Collaboration (CRDT)</strong> in Obsidian settings whenever you want live Yjs co-editing over Reverb.
                        </flux:text>
                    </div>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/40">
                    <flux:heading size="sm">How to review a conflict</flux:heading>
                    <flux:text size="sm" class="mt-2">Click the split icon in Obsidian's left ribbon or run <strong>Visual Conflict Sandbox: Reconcile Notes</strong> to compare the canonical note and conflict copy side-by-side. Choose <strong>Keep Canonical</strong>, <strong>Keep Conflict</strong>, <strong>Combine Both</strong>, or <strong>✨ Smart Merge with AI</strong> (uses server AI on standard vaults or Local Ollama on E2EE vaults), edit the preview if needed, and click <strong>Reconcile &amp; Clean Up Conflict</strong>.</flux:text>
                </div>
            </flux:card>
        @endif

        <!-- ============================================================= -->
        <!-- SECTION 5: REST API REFERENCE                                 -->
        <!-- ============================================================= -->
        @if ($activeSection === 'api')
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Synkk REST API Reference</flux:heading>
                    <flux:text class="mt-1">
                        Base Endpoint: <code class="font-mono bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded">{{ url('/api/v1') }}</code>
                    </flux:text>
                </div>

                <div class="space-y-4 font-mono text-xs">
                    <!-- Endpoint 1 -->
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-emerald-500 text-white font-bold text-[10px]">GET</span>
                            <span class="font-bold text-zinc-900 dark:text-white">/auth/verify</span>
                        </div>
                        <flux:text size="sm">Validates the <code>Authorization: Bearer synkk_...</code> header and returns the user, team, and device info.</flux:text>
                    </div>

                    <!-- Endpoint 2 -->
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-emerald-500 text-white font-bold text-[10px]">GET</span>
                            <span class="font-bold text-zinc-900 dark:text-white">/vaults</span>
                        </div>
                        <flux:text size="sm">Returns all team vaults accessible to the authenticated device token.</flux:text>
                    </div>

                    <!-- Endpoint 3 -->
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-emerald-500 text-white font-bold text-[10px]">GET</span>
                            <span class="font-bold text-zinc-900 dark:text-white">/vaults/{slug}/manifest</span>
                        </div>
                        <flux:text size="sm">Returns the complete cryptographic manifest (paths, SHA-256 hashes, timestamps, and sizes) for the vault.</flux:text>
                    </div>

                    <!-- Endpoint 4 -->
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-emerald-500 text-white font-bold text-[10px]">GET</span>
                            <span class="font-bold text-zinc-900 dark:text-white">/vaults/{slug}/changes?since_version={v}</span>
                        </div>
                        <flux:text size="sm">Returns incremental changes and revisions since a specific vault version for efficient catch-up sync.</flux:text>
                    </div>

                    <!-- Endpoint 5 -->
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-emerald-500 text-white font-bold text-[10px]">GET</span>
                            <span class="font-bold text-zinc-900 dark:text-white">/vaults/{slug}/download?path={path}</span>
                        </div>
                        <flux:text size="sm">Streams the binary or markdown contents of a file with SHA-256 validation headers.</flux:text>
                    </div>

                    <!-- Endpoint 6 -->
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-emerald-600 text-white font-bold text-[10px]">POST</span>
                            <span class="font-bold text-zinc-900 dark:text-white">/vaults/{slug}/upload</span>
                        </div>
                        <flux:text size="sm">Uploads a single note or asset with base64 content, SHA-256 verification, and DLP scanning.</flux:text>
                    </div>

                    <!-- Endpoint 7 -->
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-red-500 text-white font-bold text-[10px]">POST</span>
                            <span class="font-bold text-zinc-900 dark:text-white">/vaults/{slug}/delete</span>
                        </div>
                        <flux:text size="sm">Soft-deletes a file, recording a deletion tombstone in the vault changelog.</flux:text>
                    </div>

                    <!-- Endpoint 8 -->
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-purple-600 text-white font-bold text-[10px]">POST</span>
                            <span class="font-bold text-zinc-900 dark:text-white">/vaults/{slug}/batch-sync</span>
                        </div>
                        <flux:text size="sm">Atomic transaction executing batch file uploads and deletions in a single network round-trip.</flux:text>
                    </div>

                    <!-- Endpoint 9 -->
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-blue-600 text-white font-bold text-[10px]">POST</span>
                            <span class="font-bold text-zinc-900 dark:text-white">/vaults/{slug}/preflight</span>
                        </div>
                        <flux:text size="sm">Pre-flight dry-run simulation. Validates client file manifest against server, checks team storage quotas, and previews upload/download/identical-skip counts without modifying files.</flux:text>
                    </div>

                    <!-- Endpoint 10 -->
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-amber-600 text-white font-bold text-[10px]">POST</span>
                            <span class="font-bold text-zinc-900 dark:text-white">/vaults/{slug}/conflicts/diff · /smart-merge · /resolve</span>
                        </div>
                        <flux:text size="sm">Generates 3-way conflict diffs, optional semantic AI Smart Merge previews, and commits reconciled notes while cleaning up conflict copies.</flux:text>
                    </div>

                    <!-- Endpoint 11 -->
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-indigo-600 text-white font-bold text-[10px]">POST</span>
                            <span class="font-bold text-zinc-900 dark:text-white">/vaults/{slug}/ai/threads · /proposals · /gardener/run · /digests/generate</span>
                        </div>
                        <flux:text size="sm">Vault AI Agent conversations, Proposal Diff Inbox approvals, Vault Gardener runs, and Team Digests (blocked on E2EE vaults).</flux:text>
                    </div>

                    <!-- Endpoint 12 -->
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-teal-600 text-white font-bold text-[10px]">POST</span>
                            <span class="font-bold text-zinc-900 dark:text-white">/mcp</span>
                        </div>
                        <flux:text size="sm">Permissioned Remote MCP Server (JSON-RPC 2.0 Streamable HTTP) exposing 7 vault tools and note resources to Claude Desktop, Cursor, and Windsurf.</flux:text>
                    </div>
                </div>
            </flux:card>
        @endif

        <!-- ============================================================= -->
        <!-- SECTION 6: DOCKER & SELF-HOSTING                              -->
        <!-- ============================================================= -->
        @if ($activeSection === 'docker')
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Self-Hosting with Docker &amp; VPS Installer</flux:heading>
                    <flux:text class="mt-1">
                        Synkk runs in a single all-in-one Docker container (Nginx + PHP 8.5-FPM + Laravel 13 + Reverb + Queue Worker + Scheduler) with embedded SQLite WAL + FTS5.
                    </flux:text>
                </div>

                <div class="space-y-2">
                    <flux:heading size="sm">VPS Installer (Automatic Caddy HTTPS)</flux:heading>
                    <pre class="font-mono text-xs bg-zinc-900 text-emerald-400 p-4 rounded-xl overflow-x-auto"><code>git clone --recurse-submodules https://github.com/tawandajosephmutsena/synkk.git
cd synkk
sudo SYNKK_DOMAIN=vault.example.com SYNKK_ADMIN_EMAIL=you@example.com bash install.sh</code></pre>
                </div>

                <div class="space-y-2">
                    <flux:heading size="sm">Production Docker Compose (docker-compose.prod.yml)</flux:heading>
                    <pre class="font-mono text-xs bg-zinc-900 text-emerald-300 p-4 rounded-xl overflow-x-auto"><code>cp .env.production.example .env.production
# Set APP_KEY, APP_URL, REVERB_APP_KEY, REVERB_APP_SECRET, and first-boot SYNKK_BOOTSTRAP_* credentials
docker compose -f docker-compose.prod.yml up -d --build
# Serves HTTP on 127.0.0.1:8000 (put HTTPS reverse proxy with /app/ WebSocket upgrade in front)</code></pre>
                </div>

                <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-5">
                    <flux:heading size="sm">Before calling the deployment production-ready</flux:heading>
                    <flux:text size="sm" class="mt-2">Serve behind HTTPS with WebSocket upgrade forwarding for <code>/app/</code>, set <code>APP_DEBUG=false</code>, persist and back up the <code>synkk_storage</code> volume (<code>/var/www/html/storage</code>, which holds both the SQLite database and private vault files), configure SMTP for invitations/password resets, and remove <code>SYNKK_BOOTSTRAP_*</code> variables after your first admin login.</flux:text>
                </div>
            </flux:card>
        @endif

        <!-- ============================================================= -->
        <!-- SECTION 7: PRE-FLIGHT MIGRATION & SAFETY SHIELD               -->
        <!-- ============================================================= -->
        @if ($activeSection === 'migration')
            <flux:card class="space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <flux:badge color="lime">Live in v1.1.1</flux:badge>
                            <flux:badge color="zinc">Deterministic Dry-Run</flux:badge>
                        </div>
                        <flux:heading size="lg">First-Sync Pre-Flight &amp; Migration Engine</flux:heading>
                        <flux:text class="mt-1">
                            Migrating 5,000+ notes into a sync server is daunting. Synkk replaces blind syncing with pre-flight inspection and atomic safety shields.
                        </flux:text>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div class="p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-3">
                        <div class="flex items-center gap-2">
                            <flux:badge color="lime">Step 1</flux:badge>
                            <flux:heading size="base">Local Inspection &amp; Rollup</flux:heading>
                        </div>
                        <flux:text size="sm">
                            The Obsidian plugin inspects your local vault prior to network transmission, grouping items into Markdown, Canvases, Images, Audio/Video, PDFs, Config, and Other with precise size and count breakdowns.
                        </flux:text>
                    </div>

                    <div class="p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-3">
                        <div class="flex items-center gap-2">
                            <flux:badge color="amber">Step 2</flux:badge>
                            <flux:heading size="base">Friction Trap &amp; Path Sanitizer</flux:heading>
                        </div>
                        <flux:text size="sm">
                            Flags <code>.trash/</code>, <code>.git/</code>, files over 25 MB, trailing spaces/dots, and illegal cross-platform characters (<code>: * ? " &lt; &gt; | \</code>) with a 1-click Auto-Sanitize action, plus Ghost Files toggles.
                        </flux:text>
                    </div>

                    <div class="p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-3">
                        <div class="flex items-center gap-2">
                            <flux:badge color="blue">Step 3</flux:badge>
                            <flux:heading size="base">Simulation Dry-Run (API)</flux:heading>
                        </div>
                        <flux:text size="sm">
                            Sends your local manifest to <code>POST /api/v1/vaults/{slug}/preflight</code>. Compares remote SHA-256 hashes without transferring bytes, checking team storage quota and previewing uploads, downloads, and skips.
                        </flux:text>
                    </div>

                    <div class="p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-3">
                        <div class="flex items-center gap-2">
                            <flux:badge color="red">Step 4</flux:badge>
                            <flux:heading size="base">Atomic Safety Shield Circuit Breaker</flux:heading>
                        </div>
                        <flux:text size="sm">
                            The plugin halts sync if more than <strong>10%</strong> (configurable 1–100%) of tracked files are queued for deletion until you grant a one-time override. Local backups are archived in <code>.synkk/snapshots/</code> before remote overwrites.
                        </flux:text>
                    </div>
                </div>

                <div class="space-y-3">
                    <flux:heading size="sm">Pre-Flight Simulation API Request</flux:heading>
                    <pre class="font-mono text-xs bg-zinc-900 text-emerald-300 p-4 rounded-xl overflow-x-auto"><code>// Request: POST /api/v1/vaults/{slug}/preflight
{
  "total_files": 2,
  "total_bytes": 5120,
  "files": [
    { "path": "Welcome.md", "sha256": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855", "size": 1024 },
    { "path": "Canvas/Architecture.canvas", "sha256": "a1b2c3d4...", "size": 4096 }
  ]
}</code></pre>
                </div>
            </flux:card>
        @endif

        <!-- ============================================================= -->
        <!-- SECTION 8: INTERACTIVE LIVEWIRE VAULT PORTALS                 -->
        <!-- ============================================================= -->
        @if ($activeSection === 'portals')
            <flux:card class="space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <flux:badge color="lime">Live</flux:badge>
                            <flux:badge color="zinc">Interactive Livewire 4</flux:badge>
                        </div>
                        <flux:heading size="lg">Interactive Livewire Vault Portals</flux:heading>
                        <flux:text class="mt-1">
                            Publish any non-E2EE team vault (or subfolder) as an interactive web portal at <code>/p/{slug}</code> with zero build pipelines, optional password protection, Graph View, Portal AI Q&amp;A, and <code>/llms.txt</code>.
                        </flux:text>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:button href="{{ url('/p/synkk-docs') }}" target="_blank" icon="arrow-top-right-on-square" variant="primary">
                            Explore Live Docs Portal
                        </flux:button>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <flux:heading size="sm">⚡ Instant Publishing &amp; Access Controls</flux:heading>
                        <flux:text size="sm">
                            No static site generators or CI builds. Sync a note in Obsidian and it updates live on <code>/p/{slug}</code>. Restrict to team members, add a bcrypt password gate, or scope to a single <code>root_path</code> folder.
                        </flux:text>
                    </div>

                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <flux:heading size="sm">🔍 Instant Search, Graph &amp; Backlinks</flux:heading>
                        <flux:text size="sm">
                            Includes <code>Cmd+K</code> search, tag filtering, Obsidian callouts, <code>[[wikilink]]</code> hover popovers, bidirectional backlinks, and an interactive force-directed Graph View.
                        </flux:text>
                    </div>

                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <flux:heading size="sm">🤖 Ask This Portal AI &amp; <code>/llms.txt</code></flux:heading>
                        <flux:text size="sm">
                            Enable the rate-limited Portal AI assistant drawer (with configurable daily question limits) and expose a clean <code>/p/{slug}/llms.txt</code> index for AI agents.
                        </flux:text>
                    </div>

                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <flux:heading size="sm">🎨 4 Layouts &amp; 5 Color Themes</flux:heading>
                        <flux:text size="sm">
                            Mix 4 layouts (<strong>Docs</strong>, <strong>Bento</strong>, <strong>Dashboard</strong>, <strong>Minimal</strong>) with 5 color themes (<strong>Obsidian Noir</strong>, <strong>Slate Luxe</strong>, <strong>Midnight Emerald</strong>, <strong>Paper Craft</strong>, <strong>Amber Gold</strong>).
                        </flux:text>
                    </div>
                </div>

                <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <flux:heading size="sm">Live Example: Synkk Official Documentation Vault</flux:heading>
                        <flux:text size="sm">Our technical architecture and user guides are hosted in a Synkk vault and served live via the Portal engine (including <code>/p/synkk-docs/llms.txt</code>).</flux:text>
                    </div>
                    <flux:button href="{{ url('/p/synkk-docs') }}" target="_blank" icon="arrow-top-right-on-square" variant="filled">
                        Open /p/synkk-docs ↗
                    </flux:button>
                </div>
            </flux:card>
        @endif

        <!-- ============================================================= -->
        <!-- SECTION 9: AI, LOCAL OLLAMA & REMOTE MCP                      -->
        <!-- ============================================================= -->
        @if ($activeSection === 'ai')
            <flux:card class="space-y-6">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <flux:badge color="lime">100% Opt-In</flux:badge>
                        <flux:badge color="zinc">BYOK · Local Ollama · Managed AI · MCP</flux:badge>
                    </div>
                    <flux:heading size="lg">Vault AI Suite, Local E2EE AI &amp; Permissioned MCP</flux:heading>
                    <flux:text class="mt-1">
                        Configure workspace AI in <strong>Team Settings → AI Add-On</strong>, opt in per vault, and keep full control over provider choice, excluded folders, and human diff approvals.
                    </flux:text>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <flux:heading size="sm">🧠 Dockable AI Sidebar &amp; Proposal Inbox</flux:heading>
                        <flux:text size="sm">
                            Chat with your vault in Obsidian's <strong>Synkk AI</strong> sidebar or web Copilot tab. Proposed note creations, edits, appends, and moves stage in the <strong>AI Change Inbox</strong> with line diffs until you click <strong>Approve &amp; Sync</strong>.
                        </flux:text>
                    </div>

                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <flux:heading size="sm">🔒 Local E2EE AI (Ollama / LM Studio)</flux:heading>
                        <flux:text size="sm">
                            On E2EE vaults, server AI and MCP are blocked automatically. The plugin switches to <strong>E2EE Local</strong> mode, running BM25 + 1-hop <code>[[wikilink]]</code> retrieval on-device and querying your local Ollama (<code>llama3.2</code>) or OpenAI-compatible endpoint.
                        </flux:text>
                    </div>

                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <flux:heading size="sm">🌿 Vault Gardener &amp; Team Digests</flux:heading>
                        <flux:text size="sm">
                            Run <strong>Vault Gardener</strong> for inbox triage, wikilink suggestions, and contradiction checks (staged as proposals), and schedule daily or weekly <strong>Team Digests</strong> to a vault note, team feed, or opt-in email.
                        </flux:text>
                    </div>

                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <flux:heading size="sm">🔌 Remote MCP Server (<code>/api/v1/mcp</code>)</flux:heading>
                        <flux:text size="sm">
                            Mint scoped MCP tokens (<code>mcp_read</code>, <code>mcp_propose</code>, <code>mcp_write</code> — never counted as Obsidian device seats) to connect Claude Desktop, Cursor, or Windsurf across 7 ACL-checked vault tools.
                        </flux:text>
                    </div>
                </div>
            </flux:card>
        @endif

        <!-- ============================================================= -->
        <!-- SECTION 10: SAFETY & ROADMAP                                  -->
        <!-- ============================================================= -->
        @if ($activeSection === 'roadmap')
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Security Architecture &amp; Public Roadmap</flux:heading>
                    <flux:text class="mt-1">What is shipped and live today across Synkk Server and Obsidian Plugin v1.1.1, and what is planned next.</flux:text>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-lime-500/30 bg-lime-500/10 p-5">
                        <flux:badge color="lime">Live now</flux:badge>
                        <flux:heading size="sm" class="mt-3">Sync, E2EE, CRDT &amp; Portals</flux:heading>
                        <flux:text size="sm" class="mt-2">Obsidian Community Plugin v1.1.1 (Desktop &amp; Mobile), QR pairing, Pre-Flight Wizard, Safety Shield (10% default), WebCrypto AES-256-GCM E2EE, Ghost Files, opt-in Yjs CRDT, Passkeys &amp; 2FA, and Livewire 4 Portals.</flux:text>
                    </div>
                    <div class="rounded-2xl border border-lime-500/30 bg-lime-500/10 p-5">
                        <flux:badge color="lime">Live now</flux:badge>
                        <flux:heading size="sm" class="mt-3">Vault AI Suite &amp; Remote MCP</flux:heading>
                        <flux:text size="sm" class="mt-2">Hybrid FTS5 + vector search, streaming AI Sidebar, Proposal Diff Inbox, AI Smart Merge, Vault Gardener, Team Digests, Local E2EE Ollama AI, Portal AI + <code>/llms.txt</code>, and 7-tool Remote MCP Server.</flux:text>
                    </div>
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/40">
                        <flux:badge color="zinc">Planned next</flux:badge>
                        <flux:heading size="sm" class="mt-3">Webhooks, Domains, Trash UI &amp; CLI</flux:heading>
                        <flux:text size="sm" class="mt-2">Signed outbound webhooks (Slack/Discord/Zapier), custom portal domain routing, interactive Trash / Undelete UI, headless CLI sync client, and native OIDC/SAML SSO.</flux:text>
                    </div>
                </div>
            </flux:card>
        @endif

    </div>
</div>
