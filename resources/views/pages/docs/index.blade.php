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
                <flux:badge color="lime" size="sm">v1.1 Foundation</flux:badge>
                <flux:badge color="zinc" size="sm">GitHub Public Beta</flux:badge>
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
            wire:click="setSection('roadmap')"
            :variant="$activeSection === 'roadmap' ? 'filled' : 'subtle'"
            size="sm"
            icon="map"
        >
            Safety & Roadmap
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
                            Install the current GitHub public beta from its tagged manual release bundle. An official Obsidian Community Plugins listing and BRAT distribution are planned, not yet available.
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
                        <flux:heading size="sm" class="text-zinc-900 dark:text-white font-bold">Scheduled Sync While Obsidian Runs</flux:heading>
                        <flux:text size="sm" class="text-zinc-700 dark:text-zinc-300">
                            By default, Synkk checks for local and remote changes every 5 minutes and on startup while Obsidian is running. You can adjust the sync interval from 1 to 30 minutes in plugin settings. Native mobile background execution is roadmap work.
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
                    <flux:heading size="lg">Mobile Installation (iOS & Android)</flux:heading>
                    <flux:text class="mt-1">
                        How to install the Foundation release on your team's phones and tablets from a tagged GitHub bundle.
                    </flux:text>
                </div>

                <!-- Method A -->
                <div class="p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <flux:badge color="lime">Recommended</flux:badge>
                            <flux:heading size="base">Method 1: GitHub Public Beta</flux:heading>
                        </div>
                    </div>
                    <flux:text size="sm">
                        Use the tagged GitHub release when you need a stable manual installation bundle:
                    </flux:text>
                    <ol class="list-decimal list-inside space-y-1 text-sm text-zinc-700 dark:text-zinc-300 font-mono">
                        <li>Download <code>main.js</code>, <code>manifest.json</code>, and <code>styles.css</code> from the tagged release.</li>
                        <li>Place them in <code>&lt;vault&gt;/.obsidian/plugins/synkk-sync/</code>.</li>
                        <li>Open <strong>Settings → Community plugins</strong> and enable Synkk Team Vault Sync.</li>
                        <li>Configure the plugin with an HTTPS Synkk server URL and a device token.</li>
                    </ol>
                </div>

                <div class="p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <flux:badge color="zinc">Planned distribution</flux:badge>
                            <flux:heading size="base">BRAT & Community Plugins</flux:heading>
                        </div>
                    </div>
                    <flux:text size="sm">
                        BRAT and an official Community Plugins listing are not configured for this repository structure yet. Use the tagged manual bundle above until a dedicated plugin distribution repository and release path are published.
                    </flux:text>
                </div>

                <div class="p-5 rounded-2xl border border-amber-500/30 bg-amber-500/10 space-y-3">
                    <div class="flex items-center gap-2">
                        <flux:badge color="amber">Mobile note</flux:badge>
                        <flux:heading size="base">Background execution is not guaranteed</flux:heading>
                    </div>
                    <flux:text size="sm">
                        Use the <strong>Instant QR Pairing</strong> code generated in your web dashboard to pair mobile devices in seconds. The Foundation plugin syncs while Obsidian is open. iOS and Android may suspend background execution when inactive, so verify your latest sync pulse upon opening the app.
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
                                <th class="py-3 px-4">Role</th>
                                <th class="py-3 px-4">Read Notes</th>
                                <th class="py-3 px-4">Push / Edit Notes</th>
                                <th class="py-3 px-4">Manage Permissions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 font-mono text-xs">
                            <tr>
                                <td class="py-3 px-4 font-bold text-zinc-900 dark:text-white">Admin</td>
                                <td class="py-3 px-4 text-emerald-500">✓ Full Access</td>
                                <td class="py-3 px-4 text-emerald-500">✓ Full Access</td>
                                <td class="py-3 px-4 text-emerald-500">✓ Full Access</td>
                            </tr>
                            <tr>
                                <td class="py-3 px-4 font-bold text-zinc-900 dark:text-white">Editor</td>
                                <td class="py-3 px-4 text-emerald-500">✓ Allowed</td>
                                <td class="py-3 px-4 text-emerald-500">✓ Allowed</td>
                                <td class="py-3 px-4 text-rose-500">✗ Denied</td>
                            </tr>
                            <tr>
                                <td class="py-3 px-4 font-bold text-zinc-900 dark:text-white">Reader</td>
                                <td class="py-3 px-4 text-emerald-500">✓ Allowed</td>
                                <td class="py-3 px-4 text-rose-500">✗ Denied (Pulls only)</td>
                                <td class="py-3 px-4 text-rose-500">✗ Denied</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 space-y-2">
                    <flux:heading size="sm">Path-Level Rules Example</flux:heading>
                    <flux:text size="sm">
                        Under any vault's settings, you can define path-scoped restrictions:
                    </flux:text>
                    <pre class="font-mono text-xs bg-zinc-900 text-emerald-400 p-3 rounded-lg overflow-x-auto"><code>/finance/*          -> Admin only (Restricted from general team)
/management/hr/*    -> Admin only
/engineering/*      -> Editor (Team can read & edit)
/handbook/*         -> Reader (Team can read, only Admins edit)</code></pre>
                </div>
            </flux:card>
        @endif

        <!-- ============================================================= -->
        <!-- SECTION 4: CONFLICT & HASHING ENGINE                          -->
        <!-- ============================================================= -->
        @if ($activeSection === 'conflict')
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">File Hashing, Safety & Conflict Copies</flux:heading>
                    <flux:text class="mt-1">
                        How the Foundation release tracks whole-file changes and preserves conflicts without claiming automatic text merging.
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
                        <flux:heading size="sm">2. Versioned Manifests</flux:heading>
                        <flux:text size="sm">
                            The server records file versions and checks a client's base version before accepting an update. The dashboard can restore a historical file version as a new current revision.
                        </flux:text>
                    </div>

                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 space-y-2">
                        <flux:heading size="sm">3. Safe Conflict Forks</flux:heading>
                        <flux:text size="sm">
                            If two devices upload a stale version of the same file, Synkk saves the conflicting upload as <code>note.sync-conflict-[timestamp].md</code>. CRDT character-level merging is future work.
                        </flux:text>
                    </div>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/40">
                    <flux:heading size="sm">How to review a conflict</flux:heading>
                    <flux:text size="sm" class="mt-2">Open the current note and its <code>.sync-conflict-[timestamp].md</code> sibling, compare the headings and links, then copy the intended blocks into the canonical note. The current release preserves both files; it does not claim automatic character-level merging.</flux:text>
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
                            <span class="px-2 py-0.5 rounded bg-sky-500 text-white font-bold text-[10px]">POST</span>
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
                </div>
            </flux:card>
        @endif

        <!-- ============================================================= -->
        <!-- SECTION 6: DOCKER & SELF-HOSTING                              -->
        <!-- ============================================================= -->
        @if ($activeSection === 'docker')
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Self-Hosting with Docker</flux:heading>
                    <flux:text class="mt-1">
                        Deploy Synkk on any Linux VPS, Raspberry Pi, or local network with one command.
                    </flux:text>
                </div>

                <div class="space-y-2">
                    <flux:heading size="sm">Quick Run Command</flux:heading>
                    <pre class="font-mono text-xs bg-zinc-900 text-emerald-400 p-4 rounded-xl overflow-x-auto"><code>docker run -d \
  --name synkk-server \
  -p 8080:80 \
  -v synkk_storage:/var/www/html/storage/app/private \
  -v synkk_database:/var/www/html/database \
  -e APP_KEY="$(php artisan key:generate --show)" \
  -e DB_CONNECTION=sqlite \
  synkk/synkk:latest</code></pre>
                </div>

                <div class="space-y-2">
                    <flux:heading size="sm">Docker Compose Example</flux:heading>
                    <pre class="font-mono text-xs bg-zinc-900 text-sky-300 p-4 rounded-xl overflow-x-auto"><code>version: '3.8'

services:
  synkk:
    image: synkk/synkk:latest
    container_name: synkk-app
    restart: unless-stopped
    ports:
      - "8080:80"
    environment:
      - APP_NAME=Synkk
      - APP_ENV=production
      - APP_DEBUG=false
      - APP_URL=https://synkk.yourdomain.com
      - DB_CONNECTION=sqlite
    volumes:
      - synkk_database:/var/www/html/database
      - synkk_storage:/var/www/html/storage/app/private

volumes:
  synkk_database:
  synkk_storage:</code></pre>
                </div>

                <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-5">
                    <flux:heading size="sm">Before calling the deployment production-ready</flux:heading>
                    <flux:text size="sm" class="mt-2">Use HTTPS, set <code>APP_DEBUG=false</code>, rotate the application key through your secret manager, back up both the database and private storage volume, and verify that device tokens can be revoked. The example is a starting point, not a hosted service or an official container registry release.</flux:text>
                </div>
            </flux:card>
        @endif

        <!-- ============================================================= -->
        <!-- SECTION 7: SAFETY & ROADMAP                                  -->
        <!-- ============================================================= -->
        @if ($activeSection === 'roadmap')
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Foundation Safety & Public Roadmap</flux:heading>
                    <flux:text class="mt-1">What is available in the GitHub public beta today, and what remains intentionally deferred.</flux:text>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-lime-500/30 bg-lime-500/10 p-5">
                        <flux:badge color="lime">Available now</flux:badge>
                        <flux:heading size="sm" class="mt-3">Safety Shield, DLP & Instant QR</flux:heading>
                        <flux:text size="sm" class="mt-2">Instant QR mobile pairing, real-time DLP secret scanning, 10% mass-deletion guard, selective folder sync, and 1-click enterprise remote wipe.</flux:text>
                    </div>
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/40">
                        <flux:badge color="zinc">Next milestone</flux:badge>
                        <flux:heading size="sm" class="mt-3">Zero-Knowledge E2EE</flux:heading>
                        <flux:text size="sm" class="mt-2">Client-side encryption using device keys (XChaCha20-Poly1305) and zero-knowledge encrypted transport options for compliance-heavy teams.</flux:text>
                    </div>
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/40">
                        <flux:badge color="zinc">Viral moonshot</flux:badge>
                        <flux:heading size="sm" class="mt-3">Yjs CRDT & Agentic Graph</flux:heading>
                        <flux:text size="sm" class="mt-2">Real-time character-level multiplayer editing via Yjs, MCP AI server endpoints, and agentic knowledge retrieval over your team graph.</flux:text>
                    </div>
                </div>
            </flux:card>
        @endif

    </div>
</div>
