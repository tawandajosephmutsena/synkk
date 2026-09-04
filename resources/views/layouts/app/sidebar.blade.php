<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-100/60 text-slate-900 dark:bg-[#070A12] dark:text-slate-100 antialiased selection:bg-indigo-500 selection:text-white">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-slate-200/90 bg-white/95 backdrop-blur-2xl dark:border-slate-800/80 dark:bg-[#0D121F]/95 shadow-sm">
            <flux:sidebar.header class="pb-2 border-b border-slate-100 dark:border-slate-800/60">
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <div class="py-2">
                <livewire:team-switcher />
            </div>

            <flux:sidebar.nav class="space-y-1">
                <flux:sidebar.group :heading="__('Platform Core')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate class="font-semibold">
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="folder" :href="route('vaults.index')" :current="request()->routeIs('vaults.*')" wire:navigate class="font-semibold">
                        {{ __('Vaults') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="device-phone-mobile" :href="route('devices.index')" :current="request()->routeIs('devices.*')" wire:navigate class="font-semibold">
                        {{ __('Devices & Tokens') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="book-open-text" :href="route('docs')" :current="request()->routeIs('docs')" wire:navigate class="font-semibold">
                        {{ __('Documentation') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://ottomate.space" target="_blank" class="font-semibold text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                    {{ __('Repository') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <div class="pt-2 border-t border-slate-100 dark:border-slate-800/60">
                <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
            </div>
        </flux:sidebar>

        <!-- Mobile User Menu Header -->
        <flux:header class="lg:hidden border-b border-slate-200/90 bg-white/95 dark:border-slate-800 dark:bg-[#0D121F]">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                    class="ring-2 ring-indigo-500/30"
                />

                <flux:menu class="min-w-60 p-2 rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-[#0D121F]">
                    <div class="p-1 text-sm font-normal">
                        <div class="flex items-center gap-2.5 px-2 py-2 text-start text-xs rounded-xl bg-slate-50 dark:bg-white/5">
                            <flux:avatar
                                :name="auth()->user()->name"
                                :initials="auth()->user()->initials()"
                            />

                            <div class="grid flex-1 text-start text-xs leading-tight">
                                <flux:heading class="truncate font-extrabold text-slate-900 dark:text-white">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>

                    <flux:menu.separator class="my-2 border-slate-100 dark:border-slate-800" />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate class="text-xs font-semibold py-2">
                            {{ __('Settings') }}
                        </flux:menu.item>
                        <flux:menu.item :href="route('security.edit')" icon="shield-check" wire:navigate class="text-xs font-semibold py-2">
                            {{ __('Security & 2FA') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator class="my-2 border-slate-100 dark:border-slate-800" />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer text-xs font-bold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 rounded-xl"
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

