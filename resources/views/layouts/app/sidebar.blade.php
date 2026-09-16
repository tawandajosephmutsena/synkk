<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#F4F6F8] text-slate-900 dark:bg-[#0C0F12] dark:text-zinc-100 antialiased selection:bg-[#0D3B29] selection:text-white">
        @if (session()->has('impersonator_id'))
            <div class="sticky top-0 z-50 flex items-center justify-between gap-4 bg-gradient-to-r from-amber-600 via-rose-600 to-amber-700 px-4 py-2.5 text-xs font-semibold text-white shadow-md">
                <div class="flex items-center gap-2">
                    <flux:icon icon="exclamation-triangle" class="size-4 shrink-0 text-amber-200" />
                    <span>
                        {{ __('Support Impersonation Mode: You are viewing Synkk as') }} <strong class="underline">{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}).
                    </span>
                </div>
                <form method="POST" action="{{ route('admin.stop-impersonation') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="rounded-full bg-white px-3 py-1 text-xs font-bold text-rose-700 shadow-sm hover:bg-rose-50 transition-colors cursor-pointer">
                        {{ __('Exit Impersonation') }}
                    </button>
                </form>
            </div>
        @endif

        <flux:sidebar sticky collapsible class="border-e border-gray-200/80 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.header class="pb-2 flex items-center justify-between">
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse />
            </flux:sidebar.header>

            <livewire:team-switcher />

            <flux:sidebar.nav>
                <div class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-zinc-400 in-data-flux-sidebar-collapsed-desktop:hidden">
                    {{ __('MENU') }}
                </div>

                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="folder" :href="route('vaults.index')" :current="request()->routeIs('vaults.*')" wire:navigate>
                    {{ __('Vaults') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="globe-alt" :href="route('portals.index')" :current="request()->routeIs('portals.*')" wire:navigate>
                    {{ __('Portals') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="device-phone-mobile" :href="route('devices.index')" :current="request()->routeIs('devices.*')" wire:navigate>
                    {{ __('Devices & Tokens') }}
                </flux:sidebar.item>

                @if (auth()->user()?->isSuperAdmin())
                    <div class="pt-4 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-zinc-400 in-data-flux-sidebar-collapsed-desktop:hidden">
                        {{ __('PLATFORM') }}
                    </div>
                    <div class="my-2 border-t border-zinc-200/60 dark:border-white/10 not-in-data-flux-sidebar-collapsed-desktop:hidden"></div>

                    <flux:sidebar.item icon="shield-check" :href="route('admin.dashboard')" :current="request()->routeIs('admin.*')" wire:navigate class="text-amber-600 dark:text-amber-400 font-semibold">
                        {{ __('Super Admin') }}
                    </flux:sidebar.item>
                @endif

                <div class="pt-4 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-zinc-400 in-data-flux-sidebar-collapsed-desktop:hidden">
                    {{ __('RESOURCES') }}
                </div>
                <div class="my-2 border-t border-zinc-200/60 dark:border-white/10 not-in-data-flux-sidebar-collapsed-desktop:hidden"></div>

                <flux:sidebar.item icon="book-open-text" :href="route('docs')" :current="request()->routeIs('docs')" wire:navigate>
                    {{ __('Documentation') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="folder-git-2" href="https://github.com/tawandajosephmutsena/synkk" target="_blank">
                    {{ __('Repository') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:spacer />

            <!-- Donezo Style Plugin Promo Card in Sidebar -->
            <div class="in-data-flux-sidebar-collapsed-desktop:hidden relative overflow-hidden rounded-2xl bg-[#0D3B29] p-4 text-white shadow-xs my-2 dark:bg-[#092B1E]">
                <div class="flex items-center gap-2 mb-2">
                    <div class="flex size-7 items-center justify-center rounded-lg bg-white/15 text-white">
                        <flux:icon icon="bolt" class="size-4" />
                    </div>
                    <span class="text-xs font-bold tracking-tight text-white">{{ __('Obsidian Sync') }}</span>
                </div>
                <p class="text-[11px] text-emerald-100/80 leading-snug mb-3">
                    {{ __('Sync your vault notes across trusted devices.') }}
                </p>
                <a
                    href="https://github.com/tawandajosephmutsena/synk-obsidian-plugin"
                    target="_blank"
                    class="block w-full rounded-full bg-white py-1.5 text-center text-xs font-bold text-[#0D3B29] hover:bg-gray-100 transition-colors"
                >
                    {{ __('Download Plugin') }}
                </a>
            </div>

            <!-- Custom Application Card in Sidebar -->
            <div class="in-data-flux-sidebar-collapsed-desktop:hidden relative overflow-hidden rounded-2xl border border-emerald-500/20 bg-gradient-to-br from-[#0D3B29] to-[#041710] p-4 text-white shadow-xs mb-3 dark:border-white/10">
                <div class="flex items-center gap-2 mb-2">
                    <div class="flex size-7 items-center justify-center rounded-lg bg-emerald-500/25 text-emerald-300">
                        <flux:icon icon="sparkles" class="size-4" />
                    </div>
                    <span class="text-xs font-bold tracking-tight text-white">{{ __('Custom Application') }}</span>
                </div>
                <p class="text-[11px] text-emerald-100/90 leading-snug mb-3">
                    {{ __('Need custom sync, tailored private cloud, or bespoke workflows by Ottomate?') }}
                </p>
                <a
                    href="https://book-it.ottomate.space"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="block w-full rounded-full bg-gradient-to-r from-emerald-600 to-teal-700 py-1.5 text-center text-xs font-bold text-white shadow-sm hover:from-emerald-500 hover:to-teal-600 transition-all"
                >
                    {{ __('Book a Meeting') }}
                </a>
            </div>
        </flux:sidebar>

        <!-- Mobile Header -->
        <flux:header class="lg:hidden border-b border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu class="min-w-56">
                    <div class="p-1 text-sm">
                        <div class="flex items-center gap-2 px-2 py-1.5 text-start text-xs rounded-md bg-slate-100 dark:bg-zinc-800">
                            <flux:avatar
                                :name="auth()->user()->name"
                                :initials="auth()->user()->initials()"
                                size="sm"
                            />

                            <div class="grid flex-1 text-start text-xs leading-tight min-w-0">
                                <span class="truncate font-semibold text-slate-900 dark:text-zinc-100">{{ auth()->user()->name }}</span>
                                <span class="truncate text-slate-500 dark:text-zinc-400">{{ auth()->user()->email }}</span>
                            </div>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                        <flux:menu.item :href="route('security.edit')" icon="shield-check" wire:navigate>
                            {{ __('Security & 2FA') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer text-red-600 dark:text-red-400"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        <livewire:create-team-modal />

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>

