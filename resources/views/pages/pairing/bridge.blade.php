<x-layouts::auth.simple :title="__('Pair Device with Obsidian')">
    <div class="flex flex-col gap-6 text-center">
        @if ($sessionStatus['status'] === 'pending')
            <div class="flex flex-col items-center gap-3">
                <div class="relative flex size-16 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-500 ring-1 ring-amber-500/20">
                    <span class="absolute size-14 animate-ping rounded-full bg-amber-400/20"></span>
                    <flux:icon icon="qr-code" class="size-8" />
                </div>
                <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ __('Connecting to Obsidian...') }}
                </h1>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-xs mx-auto">
                    {{ __('Your one-scan pairing session is active. Opening Obsidian to link your device...') }}
                </p>
            </div>

            <div class="space-y-3" x-data="{
                copied: false,
                obsidianUrl: @js($obsidianUrl),
                async copyUrl() {
                    try {
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            await navigator.clipboard.writeText(this.obsidianUrl);
                        } else {
                            const ta = document.createElement('textarea');
                            ta.value = this.obsidianUrl;
                            document.body.appendChild(ta);
                            ta.select();
                            document.execCommand('copy');
                            document.body.removeChild(ta);
                        }
                        this.copied = true;
                        setTimeout(() => this.copied = false, 3000);
                    } catch (e) {}
                }
            }" x-init="
                // Auto-trigger obsidian scheme on page load
                setTimeout(() => {
                    if (obsidianUrl) {
                        window.location.href = obsidianUrl;
                    }
                }, 300);
            ">
                <a
                    href="{{ $obsidianUrl }}"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-amber-500 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-amber-400 active:bg-amber-600 transition-colors"
                >
                    <flux:icon icon="arrow-top-right-on-square" class="size-4" />
                    {{ __('Open in Obsidian App') }}
                </a>

                <button
                    type="button"
                    x-on:click="copyUrl()"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-xs font-medium text-zinc-700 shadow-xs hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 transition-colors"
                >
                    <flux:icon icon="clipboard-document" class="size-3.5" />
                    <span x-text="copied ? '{{ __('Copied to Clipboard!') }}' : '{{ __('Copy Pairing Link') }}'"></span>
                </button>
            </div>

            <div class="rounded-xl border border-zinc-200/80 bg-zinc-50/50 p-4 text-left text-xs text-zinc-500 dark:border-zinc-800/80 dark:bg-zinc-900/50 dark:text-zinc-400 space-y-2">
                <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('Troubleshooting Quick Steps:') }}</p>
                <ol class="list-decimal list-inside space-y-1 text-[11px] leading-relaxed">
                    <li>{{ __('Ensure Obsidian is installed on your mobile phone or tablet.') }}</li>
                    <li>{{ __('Install and enable the "Synkk Sync" plugin in Obsidian settings.') }}</li>
                    <li>{{ __('If the app does not open automatically, tap "Open in Obsidian App" above or paste the pairing link into Synkk Settings.') }}</li>
                </ol>
            </div>
        @elseif ($sessionStatus['status'] === 'paired')
            <div class="flex flex-col items-center gap-3">
                <div class="flex size-16 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-500 ring-1 ring-emerald-500/20">
                    <flux:icon icon="check-circle" class="size-8" />
                </div>
                <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ __('Device Already Paired!') }}
                </h1>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-xs mx-auto">
                    {{ __('This pairing session was successfully claimed by :device.', ['device' => $sessionStatus['claimed_device_name'] ?: 'your mobile device']) }}
                </p>
            </div>

            <a
                href="{{ route('home') }}"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-xs font-semibold text-zinc-700 shadow-xs hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 transition-colors"
            >
                {{ __('Return to Synkk') }}
            </a>
        @else
            <div class="flex flex-col items-center gap-3">
                <div class="flex size-16 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-500 ring-1 ring-rose-500/20">
                    <flux:icon icon="exclamation-triangle" class="size-8" />
                </div>
                <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ __('Session Expired or Invalid') }}
                </h1>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-xs mx-auto">
                    {{ __('For security, Synkk pairing sessions expire after 10 minutes. Please generate a new QR code from your desktop dashboard.') }}
                </p>
            </div>

            <a
                href="{{ route('login') }}"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100 transition-colors"
            >
                {{ __('Go to Sign In / Dashboard') }}
            </a>
        @endif
    </div>
</x-layouts::auth.simple>
