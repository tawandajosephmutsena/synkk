<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-zinc-950 dark:text-zinc-100 antialiased selection:bg-indigo-500 selection:text-white">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.header class="pb-2">
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <livewire:team-switcher />

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="folder" :href="route('vaults.index')" :current="request()->routeIs('vaults.*')" wire:navigate>
                        {{ __('Vaults') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="device-phone-mobile" :href="route('devices.index')" :current="request()->routeIs('devices.*')" wire:navigate>
                        {{ __('Devices & Tokens') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="book-open-text" :href="route('docs')" :current="request()->routeIs('docs')" wire:navigate>
                        {{ __('Documentation') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://ottomate.space" target="_blank">
                    {{ __('Repository') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
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


