<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth dark" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Security &amp; Vulnerability Disclosure — Synkk</title>
        <meta name="description" content="Synkk Security Architecture, Zero-Knowledge cryptographic standards, Vulnerability Disclosure Policy, and Safe Harbor guidelines.">
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
        <a href="#main-content" class="synkk-skip-link">Skip to content</a>

        <!-- Header -->
        <header class="synkk-header">
            <div class="synkk-shell synkk-header__inner">
                <a href="{{ route('home') }}" class="synkk-brand" aria-label="Synkk home">
                    <img src="/images/synkk-logo.svg" alt="Synkk — Obsidian everywhere" width="689" height="270" fetchpriority="high">
                </a>

                <nav class="synkk-nav" aria-label="Primary navigation">
                    <a href="{{ route('home') }}#product">Product</a>
                    <a href="{{ route('home') }}#pricing">Pricing</a>
                    <a href="{{ route('public.docs') }}">Docs</a>
                    <a href="{{ route('about') }}">About</a>
                    <a href="{{ route('security') }}">Security</a>
                </nav>

                <div class="synkk-header__actions">
                    <a href="{{ route('login') }}" class="synkk-button synkk-button--paper">Sign in</a>
                    <a href="{{ route('home') }}#pricing" class="synkk-button synkk-button--accent">Get Started</a>
                </div>
            </div>
        </header>

        <main id="main-content" class="synkk-shell py-16 md:py-24 max-w-4xl mx-auto">
            <div class="space-y-4 mb-12">
                <div class="synkk-spotlight-badge inline-flex items-center gap-2">
                    <span class="synkk-status-dot synkk-status-dot--pulse" aria-hidden="true"></span>
                    <span>Security &amp; Cryptography</span>
                </div>
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-black tracking-tight text-zinc-900 dark:text-white">Security &amp; Vulnerability Disclosure</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Responsible disclosure program, architecture standards, and safe harbor policy.</p>
            </div>

            <div class="synkk-prose space-y-10 text-zinc-700 dark:text-zinc-300 leading-relaxed">
                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">1. Security Architecture</h2>
                    <p>Synkk is built from the ground up on local-first, zero-knowledge principles:</p>
                    <ul class="list-disc pl-6 space-y-2">
                        <li><strong>Client-Side Cryptography:</strong> All encrypted notes and binary attachments are transformed using <strong>AES-256-GCM</strong> with authenticated associated data (AAD) via standard WebCrypto primitives before leaving your device.</li>
                        <li><strong>Key Material Isolation:</strong> Master passphrases reside exclusively in volatile client memory for the active session. Passphrases are never written to disk (`data.json`) or transmitted over HTTP.</li>
                        <li><strong>Tenant Isolation &amp; Continuous Authorization:</strong> Every API endpoint and Livewire portal interaction re-verifies tenant membership and cryptographic session grants on every single lifecycle invocation.</li>
                        <li><strong>Token Lifecycle &amp; Remote Wipe:</strong> Device tokens are hashed using SHA-256. Offboarding a team member instantly and transactionally revokes all active sync tokens.</li>
                    </ul>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">2. Vulnerability Disclosure Program (VDP)</h2>
                    <p>We welcome security researchers and community auditors to inspect and report vulnerabilities. We commit to working collaboratively with researchers who practice responsible disclosure.</p>
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/60 space-y-2">
                        <p class="font-bold text-zinc-900 dark:text-white">Safe Harbor Guarantee:</p>
                        <p>If you conduct vulnerability research in good faith in accordance with these guidelines, we consider your research authorized, will not initiate legal action against you, and will work with you to understand and resolve the issue quickly.</p>
                    </div>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">3. Research Guidelines</h2>
                    <ul class="list-disc pl-6 space-y-2">
                        <li>Do not access, modify, delete, or exfiltrate another customer's personal data or vault content.</li>
                        <li>Do not execute denial of service (DoS/DDoS) attacks or degrade service availability for other users.</li>
                        <li>Do not engage in physical attacks, social engineering, or spam against Ottomate employees or contractors.</li>
                        <li>Allow us a reasonable window of <strong>90 days</strong> to investigate and remediate reported findings before publishing any public disclosure.</li>
                    </ul>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">4. How to Report a Finding</h2>
                    <p>To report a security issue or vulnerability:</p>
                    <div class="space-y-3">
                        <p><strong>Primary Channel:</strong> Email our security team directly at <a href="mailto:security@synkk.space" class="text-amber-500 underline font-semibold">security@synkk.space</a>. Please include detailed steps to reproduce, HTTP request/response transcripts, and proof-of-concept code.</p>
                        <p><strong>GitHub Security Advisories:</strong> You may also submit confidential security advisories directly through our GitHub repositories:</p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li><a href="https://github.com/tawandajosephmutsena/synkk/security/advisories" target="_blank" rel="noopener noreferrer" class="text-amber-500 underline">Synkk Server Security Advisories ↗</a></li>
                            <li><a href="https://github.com/tawandajosephmutsena/synk-obsidian-plugin/security/advisories" target="_blank" rel="noopener noreferrer" class="text-amber-500 underline">Obsidian Plugin Security Advisories ↗</a></li>
                        </ul>
                    </div>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">5. Response Timelines</h2>
                    <ul class="list-disc pl-6 space-y-1.5">
                        <li><strong>Initial Acknowledgment:</strong> Within 24 hours of receipt.</li>
                        <li><strong>Triage &amp; Severity Assessment:</strong> Within 72 hours.</li>
                        <li><strong>Patch Release:</strong> Critical and high-severity vulnerabilities prioritized for hotfix within 7 business days.</li>
                    </ul>
                </section>
            </div>
        </main>

        <footer class="synkk-footer">
            <div class="synkk-shell">
                <div class="synkk-footer__top">
                    <div>
                        <img src="/images/synkk-logo.svg" alt="Synkk — Obsidian everywhere" width="689" height="270" loading="lazy">
                        <p>Portable notes. Visible safety. Infrastructure you control.</p>
                    </div>
                    <nav aria-label="Footer navigation">
                        <a href="{{ route('home') }}#product">Product</a>
                        <a href="{{ route('home') }}#pricing">Pricing</a>
                        <a href="{{ route('public.docs') }}">Documentation</a>
                        <a href="{{ route('privacy') }}">Privacy Policy</a>
                        <a href="{{ route('terms') }}">Terms of Service</a>
                        <a href="{{ route('security') }}">Security</a>
                    </nav>
                </div>
                <div class="synkk-footer__bottom">
                    <span>© {{ now()->year }} Synkk</span>
                    <span>All rights reserved</span>
                </div>
            </div>
        </footer>
    </body>
</html>
