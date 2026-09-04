@props(['showTeam' => true])

<flux:dropdown position="bottom" align="start">
    <button type="button" class="group flex w-full items-center rounded-2xl border border-slate-200/90 bg-slate-100/70 p-2 transition-all hover:bg-slate-200/60 hover:border-slate-300 dark:border-slate-800 dark:bg-[#121724] dark:hover:bg-[#181f30] dark:hover:border-indigo-500/40 shadow-xs" data-test="sidebar-menu-button">
        <div class="relative shrink-0">
            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" size="sm" class="ring-2 ring-indigo-500/30" />
            <span class="absolute bottom-0 right-0 size-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-[#121724]"></span>
        </div>
        <div class="in-data-flux-sidebar-collapsed-desktop:hidden ms-2.5 grid flex-1 text-start text-xs leading-tight min-w-0">
            <div class="flex items-center gap-1.5">
                <span class="truncate font-extrabold text-slate-900 dark:text-slate-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">{{ auth()->user()->name }}</span>
                <span class="rounded bg-indigo-500/10 px-1 py-0.2 text-[9px] font-bold text-indigo-600 dark:bg-indigo-400/20 dark:text-indigo-300">Owner</span>
            </div>
            <span class="truncate text-[10px] text-slate-500 dark:text-slate-400 font-medium mt-0.5">{{ auth()->user()->email }}</span>
        </div>
        <flux:icon name="chevrons-up-down" variant="micro" class="in-data-flux-sidebar-collapsed-desktop:hidden ms-auto size-4 text-slate-400 group-hover:text-slate-700 dark:group-hover:text-slate-200" />
    </button>

    <flux:menu class="min-w-64 p-2 rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-[#0D121F] shadow-xl">
        <div class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-50 dark:bg-white/5 text-start text-xs">
            <div class="relative shrink-0">
                <flux:avatar
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    size="md"
                    class="ring-2 ring-indigo-500/30"
                />
                <span class="absolute bottom-0 right-0 size-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-slate-900"></span>
            </div>
            <div class="grid flex-1 text-start leading-tight min-w-0">
                <div class="flex items-center gap-1.5">
                    <span class="truncate font-extrabold text-slate-900 dark:text-white text-sm">{{ auth()->user()->name }}</span>
                </div>
                <span class="truncate text-slate-500 dark:text-slate-400 text-[11px] font-medium mt-0.5">{{ auth()->user()->email }}</span>
                <div class="mt-1 flex items-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                    <flux:icon name="shield-check" variant="micro" class="size-3" />
                    <span>2FA Protected</span>
                </div>
            </div>
        </div>

        <flux:menu.separator class="my-2 border-slate-100 dark:border-slate-800" />

        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate class="text-xs font-semibold py-2">
                {{ __('Account Settings') }}
            </flux:menu.item>
            <flux:menu.item :href="route('security.edit')" icon="shield-check" wire:navigate class="text-xs font-semibold py-2">
                {{ __('Security & 2FA') }}
            </flux:menu.item>
            <flux:menu.item :href="route('teams.index')" icon="users" wire:navigate class="text-xs font-semibold py-2">
                {{ __('Team Members') }}
            </flux:menu.item>
            <flux:menu.item :href="route('appearance.edit')" icon="swatch" wire:navigate class="text-xs font-semibold py-2">
                {{ __('Appearance & Theme') }}
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
                {{ __('Log Out') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>

