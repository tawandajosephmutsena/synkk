<div class="rounded-3xl border border-gray-200/80 bg-white p-6 sm:p-8 shadow-[0_2px_12px_rgba(0,0,0,0.02)] dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex items-start max-md:flex-col gap-8">
        <!-- Settings Sidebar Navigation (Donezo Style) -->
        <div class="w-full pb-4 md:w-[230px] shrink-0">
            <nav class="space-y-1.5" aria-label="{{ __('Settings') }}">
                <a
                    href="{{ route('profile.edit') }}"
                    wire:navigate
                    class="{{ request()->routeIs('profile.edit') ? 'bg-[#0D3B29] text-white shadow-xs dark:bg-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' }} flex items-center gap-3 rounded-full px-4 py-2.5 text-xs font-bold transition-colors"
                >
                    <flux:icon icon="user" class="size-4 shrink-0" />
                    <span>{{ __('Profile') }}</span>
                </a>

                <a
                    href="{{ route('security.edit') }}"
                    wire:navigate
                    class="{{ request()->routeIs('security.edit') ? 'bg-[#0D3B29] text-white shadow-xs dark:bg-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' }} flex items-center gap-3 rounded-full px-4 py-2.5 text-xs font-bold transition-colors"
                >
                    <flux:icon icon="shield-check" class="size-4 shrink-0" />
                    <span>{{ __('Security & 2FA') }}</span>
                </a>

                <a
                    href="{{ route('teams.index') }}"
                    wire:navigate
                    class="{{ request()->routeIs('teams.*') ? 'bg-[#0D3B29] text-white shadow-xs dark:bg-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' }} flex items-center gap-3 rounded-full px-4 py-2.5 text-xs font-bold transition-colors"
                >
                    <flux:icon icon="users" class="size-4 shrink-0" />
                    <span>{{ __('Teams') }}</span>
                </a>

                <a
                    href="{{ route('appearance.edit') }}"
                    wire:navigate
                    class="{{ request()->routeIs('appearance.edit') ? 'bg-[#0D3B29] text-white shadow-xs dark:bg-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' }} flex items-center gap-3 rounded-full px-4 py-2.5 text-xs font-bold transition-colors"
                >
                    <flux:icon icon="swatch" class="size-4 shrink-0" />
                    <span>{{ __('Appearance') }}</span>
                </a>
            </nav>
        </div>

        <div class="h-px w-full bg-gray-100 dark:bg-zinc-800 md:hidden"></div>

        <!-- Main Content Area -->
        <div class="flex-1 self-stretch min-w-0">
            @if (isset($heading))
                <h2 class="text-xl font-black tracking-tight text-gray-900 dark:text-white">{{ $heading }}</h2>
            @endif
            @if (isset($subheading))
                <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">{{ $subheading }}</p>
            @endif

            <div class="mt-6 w-full max-w-xl">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
