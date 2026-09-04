<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Synkk — Self-hosted Obsidian vault sync for teams</title>
        <meta name="description" content="Keep shared Obsidian vaults synchronized across macOS, Windows, Linux, iOS, and Android with revocable device tokens and path-aware team permissions.">
        <meta name="theme-color" content="#f7f5ed">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts
        @vite('resources/css/landing.css')
    </head>
    <body class="synkk-surface font-sans text-[#14213d] antialiased">
        <a href="#main-content" class="sr-only z-[100] rounded-full bg-[#14213d] px-5 py-3 text-sm font-semibold text-white focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
            Skip to content
        </a>

        <div class="synkk-grid pointer-events-none fixed inset-0 z-0 opacity-50" aria-hidden="true"></div>

        <header class="sticky top-0 z-50 border-b border-[#14213d]/8 bg-[#fbfaf5]/90 backdrop-blur-xl">
            <div class="mx-auto flex h-18 max-w-7xl items-center justify-between gap-6 px-5 sm:px-8 lg:px-10">
                <a href="{{ route('home') }}" class="group flex items-center gap-3" aria-label="Synkk home">
                    <span class="grid size-10 place-items-center rounded-2xl bg-[#d7ff3f] text-[#14213d] shadow-[inset_0_0_0_1px_rgba(20,33,61,.12)] transition-transform group-hover:-rotate-3">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="m13.4 2.8-8 11h6.2l-1 7.4 8-11h-6.2l1-7.4Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <span>
                        <span class="block text-lg font-semibold tracking-[-0.03em]">synkk</span>
                        <span class="block text-[10px] font-semibold uppercase tracking-[0.18em] text-[#566078]">Obsidian vault sync</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-7 text-sm font-medium text-[#4a5570] md:flex" aria-label="Primary navigation">
                    <a href="#workflow" class="transition-colors hover:text-[#0b6cff]">Workflow</a>
                    <a href="#trust" class="transition-colors hover:text-[#0b6cff]">Trust & control</a>
                    <a href="#setup" class="transition-colors hover:text-[#0b6cff]">Setup</a>
                    <a href="{{ route('docs.redirect') }}" class="transition-colors hover:text-[#0b6cff]">Docs</a>
                </nav>

                <div class="flex items-center gap-2 sm:gap-3">
                    @auth
                        @php
                            $userTeam = auth()->user()->currentTeam ?? auth()->user()->personalTeam() ?? auth()->user()->teams->first();
                            $dashboardUrl = $userTeam ? route('dashboard', ['current_team' => $userTeam->slug]) : route('home');
                        @endphp
                        <a href="{{ $dashboardUrl }}" class="synkk-primary-action inline-flex min-h-11 items-center justify-center rounded-full bg-[#0b6cff] px-5 text-sm font-semibold text-white">
                            Open dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="hidden min-h-11 items-center px-3 text-sm font-semibold text-[#33405d] transition-colors hover:text-[#0b6cff] sm:inline-flex">
                            Log in
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="synkk-primary-action inline-flex min-h-11 items-center justify-center rounded-full bg-[#0b6cff] px-5 text-sm font-semibold text-white">
                                Create workspace
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </header>

        <main id="main-content" class="relative z-10">
            <section class="mx-auto grid min-h-[calc(100svh-4.5rem)] max-w-7xl items-center gap-12 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-[1.02fr_.98fr] lg:px-10 lg:py-24">
                <div class="max-w-2xl">
                    <div class="mb-7 inline-flex items-center gap-2 rounded-full border border-[#14213d]/10 bg-white/70 px-3 py-1.5 text-xs font-semibold text-[#47536e] shadow-sm">
                        <span class="synkk-device-dot size-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                        Self-hosted sync, shaped for Obsidian
                    </div>

                    <h1 class="text-[clamp(3.5rem,8vw,7rem)] font-semibold leading-[0.88] tracking-[-0.07em] text-[#14213d]">
                        Your vault,<br>
                        <span class="text-[#0b6cff]">in step.</span>
                    </h1>

                    <p class="mt-8 max-w-xl text-lg leading-8 text-[#4b5771] sm:text-xl">
                        Keep a team Obsidian vault synchronized across every trusted device—without handing the workflow to someone else’s cloud.
                    </p>

                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        @auth
                            <a href="{{ $dashboardUrl }}" class="synkk-primary-action inline-flex min-h-13 items-center justify-center gap-2 rounded-full bg-[#0b6cff] px-7 text-sm font-semibold text-white">
                                Go to your vaults
                                <span aria-hidden="true">→</span>
                            </a>
                        @else
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="synkk-primary-action inline-flex min-h-13 items-center justify-center gap-2 rounded-full bg-[#0b6cff] px-7 text-sm font-semibold text-white">
                                    Start your workspace
                                    <span aria-hidden="true">→</span>
                                </a>
                            @endif
                        @endauth
                        <a href="#workflow" class="inline-flex min-h-13 items-center justify-center rounded-full border border-[#14213d]/15 bg-white/70 px-7 text-sm font-semibold text-[#14213d] transition-colors hover:border-[#14213d]/30 hover:bg-white">
                            See the workflow
                        </a>
                    </div>

                    <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm font-medium text-[#59647b]" aria-label="Product highlights">
                        <li class="flex items-center gap-2"><span class="size-1.5 rounded-full bg-[#0b6cff]"></span>Revocable device tokens</li>
                        <li class="flex items-center gap-2"><span class="size-1.5 rounded-full bg-[#0b6cff]"></span>Path-aware permissions</li>
                        <li class="flex items-center gap-2"><span class="size-1.5 rounded-full bg-[#0b6cff]"></span>Conflict copies preserved</li>
                    </ul>
                </div>

                <div class="relative mx-auto w-full max-w-[650px] lg:mx-0">
                    <div class="synkk-mascot-stage relative aspect-[1.04/1] overflow-hidden rounded-[2.5rem] p-4 sm:rounded-[3.25rem] sm:p-8">
                        <div class="absolute inset-x-10 top-8 flex items-center justify-between text-[10px] font-semibold uppercase tracking-[0.18em] text-white/75 sm:top-10">
                            <span>Vault current</span>
                            <span class="flex items-center gap-2"><span class="size-1.5 rounded-full bg-[#d7ff3f]"></span>3 devices</span>
                        </div>

                        <img
                            src="/images/character/synkk-diver-premium.webp"
                            alt="Synkk’s blue octopus diver guiding a vault between devices"
                            width="1536"
                            height="1024"
                            fetchpriority="high"
                            decoding="async"
                            class="synkk-mascot absolute -bottom-[3%] left-1/2 z-10 w-[118%] max-w-none -translate-x-1/2 sm:w-[112%]"
                        >

                        <div class="synkk-status-card synkk-glass absolute left-4 top-[22%] z-20 rounded-2xl border border-white/60 px-4 py-3 shadow-lg shadow-[#13275b]/15 sm:left-7">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#68728a]">Obsidian</p>
                            <p class="mt-1 flex items-center gap-2 text-sm font-semibold text-[#14213d]"><span class="size-2 rounded-full bg-emerald-500"></span>Note verified</p>
                        </div>

                        <div class="synkk-status-card synkk-glass absolute bottom-6 right-4 z-20 rounded-2xl border border-white/60 px-4 py-3 shadow-lg shadow-[#13275b]/15 sm:bottom-9 sm:right-7">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#68728a]">Trusted device</p>
                            <p class="mt-1 text-sm font-semibold text-[#14213d]">Ready to pull <span class="text-[#0b6cff]">v28</span></p>
                        </div>

                        <span class="absolute bottom-10 left-12 size-3 rounded-full bg-[#d7ff3f] shadow-[0_0_0_8px_rgba(215,255,63,.16)]" aria-hidden="true"></span>
                        <span class="absolute right-16 top-20 size-2 rounded-full bg-white shadow-[0_0_0_7px_rgba(255,255,255,.14)]" aria-hidden="true"></span>
                    </div>

                    <p class="mt-4 text-center text-xs leading-5 text-[#68728a]">
                        Sync happens on startup, on your schedule, or whenever you choose.
                    </p>
                </div>
            </section>

            <section class="border-y border-[#14213d]/8 bg-white/55" aria-labelledby="platform-heading">
                <div class="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:px-10">
                    <div class="flex flex-col gap-7 lg:flex-row lg:items-center lg:justify-between">
                        <div class="max-w-sm">
                            <h2 id="platform-heading" class="text-lg font-semibold tracking-[-0.02em] text-[#14213d]">One Obsidian workflow. Every daily device.</h2>
                            <p class="mt-1 text-sm leading-6 text-[#647087]">The same vault model across desktop and mobile.</p>
                        </div>

                        <ul class="grid grid-cols-3 gap-2 sm:grid-cols-6" aria-label="Supported platforms">
                            @foreach ([
                                ['obsidian', 'Obsidian'],
                                ['macos', 'macOS'],
                                ['windows', 'Windows'],
                                ['linux', 'Linux'],
                                ['ios', 'iOS'],
                                ['android', 'Android'],
                            ] as [$icon, $platform])
                                <li class="synkk-platform-mark flex min-w-24 flex-col items-center justify-center gap-2 rounded-2xl border border-[#14213d]/8 bg-white/65 px-3 py-4">
                                    <img src="/images/platforms/{{ $icon }}.svg" alt="" width="24" height="24" class="size-6" loading="eager" decoding="async">
                                    <span class="text-xs font-semibold text-[#38445f]">{{ $platform }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </section>

            <section id="workflow" class="synkk-deferred mx-auto max-w-7xl px-5 py-24 sm:px-8 sm:py-32 lg:px-10">
                <div class="mx-auto max-w-3xl text-center">
                    <h2 class="text-4xl font-semibold tracking-[-0.045em] text-[#14213d] sm:text-6xl">From local note to every trusted device</h2>
                    <p class="mx-auto mt-5 max-w-2xl text-lg leading-8 text-[#59657d]">
                        Synkk keeps the familiar local-first Obsidian experience, then adds a controlled route for team changes.
                    </p>
                </div>

                <ol class="relative mt-16 grid gap-5 lg:grid-cols-3" aria-label="Obsidian synchronization workflow">
                    <li class="relative flex min-h-[440px] flex-col rounded-[2rem] border border-[#14213d]/10 bg-white p-5 shadow-[0_24px_70px_-48px_rgba(20,33,61,.45)] sm:p-7">
                        <span class="synkk-step-number absolute -top-3 left-7 grid size-8 place-items-center rounded-full bg-[#14213d] text-xs font-semibold text-white">1</span>
                        <div class="mt-5 overflow-hidden rounded-2xl border border-[#14213d]/10 bg-[#f4f1e9]">
                            <div class="flex items-center gap-1.5 border-b border-[#14213d]/8 bg-white px-3 py-2.5">
                                <span class="size-2 rounded-full bg-[#ff7b72]"></span>
                                <span class="size-2 rounded-full bg-[#f2cc60]"></span>
                                <span class="size-2 rounded-full bg-[#56d364]"></span>
                                <span class="ml-2 text-[10px] font-semibold text-[#737d91]">Obsidian · Product vault</span>
                            </div>
                            <div class="grid min-h-56 grid-cols-[76px_1fr]">
                                <div class="border-r border-[#14213d]/8 bg-[#ece8df] p-3">
                                    <div class="h-2 rounded bg-[#6c31e3]/25"></div>
                                    <div class="mt-3 h-2 w-4/5 rounded bg-[#14213d]/10"></div>
                                    <div class="mt-2 h-2 w-3/5 rounded bg-[#14213d]/10"></div>
                                </div>
                                <div class="p-4">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#6c31e3]">Launch notes.md</p>
                                    <p class="mt-4 text-sm font-semibold text-[#26334f]">Release checklist</p>
                                    <div class="mt-4 space-y-3 text-xs text-[#647087]">
                                        <p>✓ Confirm navigation</p>
                                        <p>✓ Review mobile layout</p>
                                        <p class="rounded-md bg-[#d7ff3f]/55 px-2 py-1.5 text-[#26334f]">• Share final notes</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <h3 class="mt-6 text-xl font-semibold tracking-[-0.025em] text-[#14213d]">Work in Obsidian</h3>
                        <p class="mt-2 text-sm leading-6 text-[#606c83]">Edit Markdown, Canvas, and supporting files inside the vault you already know.</p>
                    </li>

                    <li class="relative flex min-h-[440px] flex-col rounded-[2rem] border border-[#14213d]/10 bg-[#14213d] p-5 text-white shadow-[0_28px_80px_-45px_rgba(20,33,61,.75)] sm:p-7">
                        <span class="synkk-step-number absolute -top-3 left-7 grid size-8 place-items-center rounded-full bg-[#d7ff3f] text-xs font-semibold text-[#14213d]">2</span>
                        <div class="mt-5 rounded-2xl border border-white/12 bg-white/6 p-5">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-semibold uppercase tracking-[0.15em] text-white/55">Change gate</span>
                                <span class="rounded-full bg-[#d7ff3f] px-2 py-1 text-[9px] font-bold uppercase tracking-[0.1em] text-[#14213d]">accepted</span>
                            </div>
                            <div class="mt-6 space-y-3">
                                <div class="flex items-center justify-between rounded-xl bg-white/7 px-3 py-3">
                                    <span class="text-xs text-white/65">Device token</span>
                                    <span class="text-xs font-semibold text-white">verified</span>
                                </div>
                                <div class="flex items-center justify-between rounded-xl bg-white/7 px-3 py-3">
                                    <span class="text-xs text-white/65">Path access</span>
                                    <span class="text-xs font-semibold text-white">read + write</span>
                                </div>
                                <div class="rounded-xl bg-white/7 px-3 py-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-white/65">SHA-256</span>
                                        <span class="text-[10px] font-semibold text-[#8bdcff]">change detected</span>
                                    </div>
                                    <div class="synkk-flow-line mt-3 h-1.5 rounded-full bg-gradient-to-r from-[#0b6cff] via-[#65dbff] to-[#d7ff3f]"></div>
                                </div>
                            </div>
                        </div>
                        <h3 class="mt-6 text-xl font-semibold tracking-[-0.025em]">Synkk verifies the change</h3>
                        <p class="mt-2 text-sm leading-6 text-white/68">The server authenticates the device, applies vault permissions, compares the content hash, and records the accepted change.</p>
                    </li>

                    <li class="relative flex min-h-[440px] flex-col rounded-[2rem] border border-[#14213d]/10 bg-white p-5 shadow-[0_24px_70px_-48px_rgba(20,33,61,.45)] sm:p-7">
                        <span class="synkk-step-number absolute -top-3 left-7 grid size-8 place-items-center rounded-full bg-[#0b6cff] text-xs font-semibold text-white">3</span>
                        <div class="mt-5 grid min-h-56 grid-cols-2 gap-3 rounded-2xl bg-[#f1f6ff] p-4">
                            <div class="col-span-2 flex items-center justify-between rounded-xl border border-[#0b6cff]/12 bg-white px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img src="/images/platforms/obsidian.svg" alt="" class="size-6" width="24" height="24">
                                    <div>
                                        <p class="text-xs font-semibold text-[#26334f]">Product vault</p>
                                        <p class="text-[10px] text-[#738098]">Version 28 available</p>
                                    </div>
                                </div>
                                <span class="synkk-device-dot size-2 rounded-full bg-emerald-500"></span>
                            </div>
                            <div class="rounded-xl border border-[#14213d]/8 bg-white p-4">
                                <img src="/images/platforms/macos.svg" alt="" class="size-5" width="20" height="20">
                                <p class="mt-8 text-xs font-semibold text-[#26334f]">Studio Mac</p>
                                <p class="mt-1 text-[10px] text-emerald-700">Ready to pull</p>
                            </div>
                            <div class="rounded-xl border border-[#14213d]/8 bg-white p-4">
                                <img src="/images/platforms/ios.svg" alt="" class="size-5" width="20" height="20">
                                <p class="mt-8 text-xs font-semibold text-[#26334f]">Travel phone</p>
                                <p class="mt-1 text-[10px] text-emerald-700">Ready to pull</p>
                            </div>
                        </div>
                        <h3 class="mt-6 text-xl font-semibold tracking-[-0.025em] text-[#14213d]">Your team receives the update</h3>
                        <p class="mt-2 text-sm leading-6 text-[#606c83]">Trusted devices fetch the latest allowed changes at startup, on schedule, or after a manual sync.</p>
                    </li>
                </ol>

                <div class="mt-6 flex flex-col items-start justify-between gap-4 rounded-2xl border border-[#0b6cff]/15 bg-[#eaf3ff] px-5 py-4 sm:flex-row sm:items-center sm:px-6">
                    <p class="text-sm font-semibold text-[#263a63]">Role and path rules stay attached to the vault</p>
                    <p class="text-sm text-[#536681]">If edits collide, Synkk keeps a conflict copy for a human decision.</p>
                </div>
            </section>

            <section id="trust" class="synkk-deferred bg-[#14213d] text-white">
                <div class="mx-auto max-w-7xl px-5 py-24 sm:px-8 sm:py-28 lg:px-10">
                    <div class="grid gap-10 lg:grid-cols-[.8fr_1.2fr] lg:items-end">
                        <div>
                            <h2 class="max-w-lg text-4xl font-semibold tracking-[-0.045em] sm:text-5xl">Control that feels calm, not complicated.</h2>
                        </div>
                        <p class="max-w-2xl text-lg leading-8 text-white/65 lg:justify-self-end">
                            Synkk gives owners a readable record of devices, permissions, and vault activity while keeping the everyday work inside Obsidian.
                        </p>
                    </div>

                    <div class="mt-14 grid gap-4 lg:grid-cols-3">
                        <article class="rounded-[1.75rem] border border-white/12 bg-white/6 p-7">
                            <div class="grid size-11 place-items-center rounded-2xl bg-[#d7ff3f] text-[#14213d]">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 5 6v5c0 4.7 2.9 8.2 7 10 4.1-1.8 7-5.3 7-10V6l-7-3Z"/><path d="m9.4 12 1.7 1.7 3.7-4"/></svg>
                            </div>
                            <h3 class="mt-7 text-xl font-semibold">One-time device secrets</h3>
                            <p class="mt-3 text-sm leading-6 text-white/62">The full token is shown once. Synkk stores its SHA-256 hash and lets an owner revoke the device later.</p>
                        </article>

                        <article class="rounded-[1.75rem] border border-white/12 bg-white/6 p-7">
                            <div class="grid size-11 place-items-center rounded-2xl bg-[#6ddcff] text-[#14213d]">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6.5h6l1.5 2H20v9H4v-11Z"/><path d="M8 13h8M8 16h5"/></svg>
                            </div>
                            <h3 class="mt-7 text-xl font-semibold">Permissions down to a path</h3>
                            <p class="mt-3 text-sm leading-6 text-white/62">Set vault defaults, then make a folder or file read-write, read-only, or hidden for the people who need it.</p>
                        </article>

                        <article class="rounded-[1.75rem] border border-white/12 bg-white/6 p-7">
                            <div class="grid size-11 place-items-center rounded-2xl bg-[#9f8cff] text-white">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 7h11a3 3 0 0 1 3 3v7"/><path d="m16 14 3 3 3-3M19 17H8a3 3 0 0 1-3-3V7"/><path d="m8 10-3-3-3 3"/></svg>
                            </div>
                            <h3 class="mt-7 text-xl font-semibold">A recoverable conflict path</h3>
                            <p class="mt-3 text-sm leading-6 text-white/62">When two edits cannot safely become one, the server creates a separate conflict file instead of pretending it merged them.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="setup" class="synkk-deferred mx-auto max-w-7xl px-5 py-24 sm:px-8 sm:py-32 lg:px-10">
                <div class="grid gap-14 lg:grid-cols-[.8fr_1.2fr]">
                    <div class="lg:sticky lg:top-28 lg:self-start">
                        <h2 class="text-4xl font-semibold tracking-[-0.045em] text-[#14213d] sm:text-5xl">Connect once. Keep writing.</h2>
                        <p class="mt-5 max-w-md text-lg leading-8 text-[#5c6880]">A deliberate setup creates the boundary between your server, your vault, and each trusted device.</p>
                        <a href="{{ route('docs.redirect') }}" class="mt-7 inline-flex min-h-11 items-center gap-2 rounded-full border border-[#14213d]/15 bg-white px-5 text-sm font-semibold text-[#14213d] transition-colors hover:border-[#0b6cff]/40 hover:text-[#0b6cff]">
                            Open setup guide <span aria-hidden="true">→</span>
                        </a>
                    </div>

                    <ol class="space-y-3" aria-label="Setup steps">
                        @foreach ([
                            ['01', 'Host the Synkk server', 'Deploy this Laravel application at an HTTPS address your devices can reach. Your chosen infrastructure stores the vault files.'],
                            ['02', 'Create the team vault', 'Name the vault, invite teammates, and set the default and path-specific permissions that fit the work.'],
                            ['03', 'Connect each device', 'Generate a dedicated token, then paste the Server API URL and Device Sync Token into Synkk Vault Sync in Obsidian.'],
                            ['04', 'Choose the sync rhythm', 'Verify and load vaults, select the Target Vault, then sync manually, at startup, or on a schedule.'],
                        ] as [$number, $title, $description])
                            <li class="grid gap-4 rounded-[1.5rem] border border-[#14213d]/10 bg-white/75 p-5 sm:grid-cols-[64px_1fr] sm:p-6">
                                <span class="text-sm font-semibold text-[#0b6cff]">{{ $number }}</span>
                                <div>
                                    <h3 class="text-lg font-semibold tracking-[-0.02em] text-[#14213d]">{{ $title }}</h3>
                                    <p class="mt-2 text-sm leading-6 text-[#626e85]">{{ $description }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            <section class="synkk-deferred mx-auto max-w-7xl px-5 pb-24 sm:px-8 sm:pb-32 lg:px-10">
                <div class="relative overflow-hidden rounded-[2.5rem] bg-[#0b6cff] px-6 py-16 text-center text-white shadow-[0_34px_90px_-50px_rgba(11,108,255,.85)] sm:px-12 sm:py-20">
                    <div class="pointer-events-none absolute -left-24 -top-28 size-72 rounded-full border border-white/15" aria-hidden="true"></div>
                    <div class="pointer-events-none absolute -bottom-32 -right-20 size-80 rounded-full border border-white/15" aria-hidden="true"></div>
                    <h2 class="relative mx-auto max-w-3xl text-4xl font-semibold tracking-[-0.05em] sm:text-6xl">Make the vault feel shared—not rented.</h2>
                    <p class="relative mx-auto mt-5 max-w-2xl text-base leading-7 text-white/75 sm:text-lg">Give the team a clear route between local notes and the devices you trust.</p>
                    <div class="relative mt-9 flex flex-col justify-center gap-3 sm:flex-row">
                        @auth
                            <a href="{{ $dashboardUrl }}" class="inline-flex min-h-12 items-center justify-center rounded-full bg-[#d7ff3f] px-7 text-sm font-semibold text-[#14213d] transition-transform hover:-translate-y-0.5">Open dashboard</a>
                        @else
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="inline-flex min-h-12 items-center justify-center rounded-full bg-[#d7ff3f] px-7 text-sm font-semibold text-[#14213d] transition-transform hover:-translate-y-0.5">Create workspace</a>
                            @endif
                            <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center justify-center rounded-full border border-white/25 bg-white/10 px-7 text-sm font-semibold text-white transition-colors hover:bg-white/16">Log in</a>
                        @endauth
                    </div>
                </div>
            </section>

            <section class="synkk-deferred border-t border-[#14213d]/8 bg-white/55">
                <div class="mx-auto grid max-w-7xl gap-12 px-5 py-20 sm:px-8 lg:grid-cols-[.72fr_1.28fr] lg:px-10">
                    <div>
                        <h2 class="text-3xl font-semibold tracking-[-0.04em] text-[#14213d]">A few honest answers.</h2>
                        <p class="mt-3 text-sm leading-6 text-[#647087]">The important details, without the fog machine.</p>
                    </div>
                    <div class="divide-y divide-[#14213d]/10 border-y border-[#14213d]/10">
                        <details class="group py-5">
                            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-5 text-base font-semibold text-[#14213d]">
                                Is Synkk end-to-end encrypted?
                                <svg class="size-5 shrink-0 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                            </summary>
                            <p class="max-w-2xl pb-2 pr-9 text-sm leading-6 text-[#626e85]">No. Synkk authenticates devices and stores files on the server you operate. Use HTTPS in production and secure that server as you would any private team system.</p>
                        </details>
                        <details class="group py-5">
                            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-5 text-base font-semibold text-[#14213d]">
                                Does it merge two edits automatically?
                                <svg class="size-5 shrink-0 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                            </summary>
                            <p class="max-w-2xl pb-2 pr-9 text-sm leading-6 text-[#626e85]">It does not claim to be a semantic merge engine. When Synkk detects a collision, it creates a conflict copy so a person can compare the edits.</p>
                        </details>
                        <details class="group py-5">
                            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-5 text-base font-semibold text-[#14213d]">
                                Which devices can connect?
                                <svg class="size-5 shrink-0 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                            </summary>
                            <p class="max-w-2xl pb-2 pr-9 text-sm leading-6 text-[#626e85]">The plugin and token model support Obsidian on macOS, Windows, Linux, iOS, and Android. Each device receives its own revocable token.</p>
                        </details>
                    </div>
                </div>
            </section>
        </main>

        <footer class="relative z-10 border-t border-[#14213d]/8 bg-[#f7f5ed]">
            <div class="mx-auto flex max-w-7xl flex-col gap-8 px-5 py-10 sm:px-8 md:flex-row md:items-center md:justify-between lg:px-10">
                <div class="flex items-center gap-3">
                    <span class="grid size-9 place-items-center rounded-xl bg-[#d7ff3f] text-[#14213d]">
                        <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m13.4 2.8-8 11h6.2l-1 7.4 8-11h-6.2l1-7.4Z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-[#14213d]">synkk</p>
                        <p class="text-xs text-[#6a758b]">Local notes. Trusted routes.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-x-6 gap-y-3 text-sm font-medium text-[#5c6880]">
                    <a href="#workflow" class="hover:text-[#0b6cff]">Workflow</a>
                    <a href="#trust" class="hover:text-[#0b6cff]">Trust & control</a>
                    <a href="{{ route('docs.redirect') }}" class="hover:text-[#0b6cff]">Documentation</a>
                    <a href="{{ route('login') }}" class="hover:text-[#0b6cff]">Log in</a>
                </div>
                <p class="text-xs text-[#7a8498]">© {{ date('Y') }} Synkk</p>
            </div>
        </footer>
    </body>
</html>
