<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth dark" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Privacy Policy — Synkk</title>
        <meta name="description" content="Synkk Privacy Policy: data protection, zero-knowledge encryption guarantees, GDPR/CCPA compliance, subprocessor transparency, and cookie policy.">
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
                    <span class="synkk-status-dot" aria-hidden="true"></span>
                    <span>Legal &amp; Compliance</span>
                </div>
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-black tracking-tight text-zinc-900 dark:text-white">Privacy Policy</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Effective Date: September 17, 2026 · Version 1.1</p>
            </div>

            <div class="synkk-prose space-y-10 text-zinc-700 dark:text-zinc-300 leading-relaxed">
                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">1. Overview and Scope</h2>
                    <p>Synkk is operated by <strong>Ottomate</strong> ("we", "us", or "our"). This Privacy Policy explains how we collect, handle, protect, and process data when you interact with our websites (<code>synkk.space</code>), our managed cloud services (<strong>Synkk Cloud</strong>), and our synchronization software including the official <strong>Synkk Obsidian Plugin</strong>.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">2. Controller vs. Processor Roles</h2>
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/60 space-y-2">
                        <p><strong>Self-Hosted Deployments (Synkk Community &amp; Self-Host Pro LTD):</strong> When you self-host the Synkk server via Docker or on your own infrastructure, <strong>you are the sole Data Controller and Data Processor</strong>. Zero telemetry, vault content, tokens, or personal identifiers are transmitted to Ottomate servers.</p>
                        <p><strong>Synkk Cloud (Managed SaaS):</strong> When you use our hosted workspaces, you remain the Data Controller of your vault content. Ottomate acts as a <strong>Data Processor</strong> under GDPR Article 28, operating strictly on your documented instructions.</p>
                    </div>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">3. Zero-Knowledge Encryption (E2EE)</h2>
                    <p>When you enable End-to-End Encryption (E2EE) on a vault, all notes, frontmatter, and attachments are encrypted directly on your local client device using <strong>AES-256-GCM</strong> with 100,000 PBKDF2 iterations via the standard <strong>WebCrypto API</strong> before network transmission. Master passphrases are kept in ephemeral memory and <strong>are never saved to disk or transmitted to our servers</strong>. Synkk Cloud servers only ever store and relay opaque binary ciphertext blobs.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">4. Data We Collect and Why</h2>
                    <ul class="list-disc pl-6 space-y-2">
                        <li><strong>Account Identification:</strong> Name, email address, and argon2id-hashed passwords for authentication and account recovery.</li>
                        <li><strong>Payment Information:</strong> Subscription and license transactions are processed through our Merchant of Record, <strong>Dodo Payments Inc.</strong> Ottomate does not collect, process, or store payment card numbers or banking credentials.</li>
                        <li><strong>Device Tokens &amp; Pairing Telemetry:</strong> Cryptographically generated high-entropy bearer tokens (`synkk_live_...`) to authorize individual Obsidian client devices and enforce team access permissions.</li>
                        <li><strong>System Logs:</strong> Aggregated HTTP access logs (IP addresses, request timestamps, user-agent) retained for 14 days solely for rate limiting, DDoS defense, and malicious abuse prevention.</li>
                    </ul>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">5. Cookies and Tracking</h2>
                    <p>Synkk does <strong>not</strong> use third-party tracking pixels, advertising cookies, or behavioral analytics cookies. We use only strictly necessary first-party HTTP-only session cookies (`synkk_session`, CSRF validation tokens, and portal password unlock grants) required for authentication and security.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">6. Third-Party Subprocessors</h2>
                    <p>For Synkk Cloud customers, we utilize the following infrastructure and service providers:</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse border border-zinc-200 dark:border-zinc-800">
                            <thead>
                                <tr class="bg-zinc-100 dark:bg-zinc-900 text-zinc-900 dark:text-white">
                                    <th class="p-3 border border-zinc-200 dark:border-zinc-800">Subprocessor</th>
                                    <th class="p-3 border border-zinc-200 dark:border-zinc-800">Purpose</th>
                                    <th class="p-3 border border-zinc-200 dark:border-zinc-800">Location</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="p-3 border border-zinc-200 dark:border-zinc-800 font-medium">Hetzner Online GmbH</td>
                                    <td class="p-3 border border-zinc-200 dark:border-zinc-800">Core Cloud Server &amp; Encrypted Storage Hosting</td>
                                    <td class="p-3 border border-zinc-200 dark:border-zinc-800">European Union (Germany)</td>
                                </tr>
                                <tr>
                                    <td class="p-3 border border-zinc-200 dark:border-zinc-800 font-medium">Dodo Payments Inc.</td>
                                    <td class="p-3 border border-zinc-200 dark:border-zinc-800">Payment Processing &amp; Tax Compliance (Merchant of Record)</td>
                                    <td class="p-3 border border-zinc-200 dark:border-zinc-800">United States / Global</td>
                                </tr>
                                <tr>
                                    <td class="p-3 border border-zinc-200 dark:border-zinc-800 font-medium">Cloudflare, Inc.</td>
                                    <td class="p-3 border border-zinc-200 dark:border-zinc-800">Edge Network, DDoS Shield &amp; TLS Termination</td>
                                    <td class="p-3 border border-zinc-200 dark:border-zinc-800">Global Anycast Network</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">7. Data Retention &amp; Deletion Rights</h2>
                    <p>When you delete a vault or team workspace in Synkk, all associated files, snapshots, versions, and tokens are permanently removed from production databases and file storage within 24 hours. Under the GDPR, CCPA, and applicable laws, you retain the right to:</p>
                    <ul class="list-disc pl-6 space-y-1.5">
                        <li>Request a portable export of your account and team data.</li>
                        <li>Request immediate and irreversible deletion of your account and personal identifiers.</li>
                        <li>Restrict or object to processing of personal data.</li>
                    </ul>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">8. Privacy Contacts</h2>
                    <p>For privacy inquiries, Data Processing Agreement (DPA) inquiries, or data subject access requests (DSAR), contact our Data Protection Office at: <a href="mailto:privacy@synkk.space" class="text-amber-500 underline font-medium">privacy@synkk.space</a>.</p>
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
