<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth dark" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Terms of Service — Synkk</title>
        <meta name="description" content="Synkk Terms of Service: licensing tiers, self-hosted and cloud workspace terms, commercial license rights, refund policy, and acceptable use.">
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
                    <span>Legal &amp; Terms</span>
                </div>
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-black tracking-tight text-zinc-900 dark:text-white">Terms of Service</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Effective Date: September 17, 2026 · Version 1.1</p>
            </div>

            <div class="synkk-prose space-y-10 text-zinc-700 dark:text-zinc-300 leading-relaxed">
                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">1. Acceptance of Terms</h2>
                    <p>By using the Synkk software, installing the Synkk Obsidian Plugin, creating a Synkk Cloud account, or purchasing a commercial license, you agree to be bound by these Terms of Service. If you are entering into these terms on behalf of a company or legal entity, you represent that you have the authority to bind such entity.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">2. Licensing and Software Editions</h2>
                    <ul class="space-y-3">
                        <li><strong>Synkk Obsidian Plugin:</strong> Distributed under the open-source MIT License. It is free forever and can be used on unlimited devices.</li>
                        <li><strong>Synkk Community Server (Free &amp; Open Source):</strong> Distributed under the MIT License included with the source code.</li>
                        <li><strong>Self-Host Pro Commercial License ($79 LTD):</strong> Grants a perpetual, non-exclusive license to the separately distributed Pro software for internal business use. Includes 1 year of software updates and support, subject to the commercial license supplied at purchase.</li>
                        <li><strong>Synkk Cloud Workspaces ($12/mo Pro / $25/mo Business):</strong> A managed SaaS subscription providing fully hosted, backed-up sync infrastructure with zero server maintenance. A valid payment card is required upfront to initiate the 30-day trial to protect server pools from abuse.</li>
                    </ul>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">3. Acceptable Use Policy</h2>
                    <p>You agree not to use Synkk or its managed cloud relays to:</p>
                    <ul class="list-disc pl-6 space-y-1.5">
                        <li>Store, transmit, or synchronize unlawful, infringing, or malicious content.</li>
                        <li>Attempt to probe, scan, or breach the security of Synkk Cloud infrastructure without prior authorization under our Security Disclosure program.</li>
                        <li>Circumvent storage quotas, tenant isolation controls, or rate limits.</li>
                    </ul>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">4. Billing, Trials, and Refunds</h2>
                    <p>All commercial transactions are processed securely through <strong>Dodo Payments Inc.</strong> as our Merchant of Record:</p>
                    <ul class="list-disc pl-6 space-y-1.5">
                        <li><strong>30-Day Free Trial:</strong> Cancel anytime before the trial period ends to avoid billing.</li>
                        <li><strong>Lifetime Deal (LTD) Refund Policy:</strong> We provide an unconditional <strong>30-day money-back guarantee</strong> for all direct commercial lifetime licenses.</li>
                        <li><strong>Cloud Subscriptions:</strong> Subscriptions can be canceled at any time via the billing portal and remain active until the end of the current paid billing cycle.</li>
                    </ul>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">5. Intellectual Property &amp; Vault Ownership</h2>
                    <p>You retain 100% intellectual property ownership of your markdown notes, graphs, files, attachments, and data. Ottomate claims zero intellectual property rights over any information you synchronize, store, or publish through Synkk.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">6. Disclaimer of Warranties &amp; Limitation of Liability</h2>
                    <p>Except as expressly set forth in a signed enterprise agreement, Synkk software and cloud services are provided "AS IS" without warranty of any kind. Ottomate shall not be liable for any indirect, incidental, or consequential damages resulting from data loss, network outages, or unauthorized access to local vaults.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">7. Inquiries</h2>
                    <p>Questions regarding these terms may be directed to <a href="mailto:legal@synkk.space" class="text-amber-500 underline font-medium">legal@synkk.space</a>.</p>
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
