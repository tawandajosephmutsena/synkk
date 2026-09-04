@props(['showTeam' => true])

<flux:dropdown position="bottom" align="start">
    <button type="button" class="group flex w-full items-center rounded-xl p-1.5 transition-all hover:bg-slate-200/50 dark:hover:bg-white/10" data-test="sidebar-menu-button">
        <div class="relative shrink-0">
            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" size="sm" />
            <span class="absolute bottom-0 right-0 size-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-zinc-900"></span>
        </div>
        <div class="in-data-flux-sidebar-collapsed-desktop:hidden ms-2.5 grid flex-1 text-start text-xs leading-tight min-w-0">
            <span class="truncate font-semibold text-slate-800 dark:text-zinc-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">{{ auth()->user()->name }}</span>
            <span class="truncate text-[10px] text-slate-400 font-medium">{{ auth()->user()->email }}</span>
        </div>
        <flux:icon name="chevrons-up-down" variant="micro" class="in-data-flux-sidebar-collapsed-desktop:hidden ms-auto size-4 text-slate-400 group-hover:text-slate-700 dark:group-hover:text-zinc-200" />
    </button>

    <flux:menu class="min-w-56">
        <div class="flex items-center gap-2.5 px-2 py-2 text-start text-xs">
            <flux:avatar
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
                size="sm"
            />
            <div class="grid flex-1 text-start leading-tight min-w-0">
                <span class="truncate font-bold text-slate-900 dark:text-zinc-100">{{ auth()->user()->name }}</span>
                <span class="truncate text-slate-400 text-[11px] font-medium">{{ auth()->user()->email }}</span>
            </div>
        </div>
        <flux:menu.separator />
        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate class="text-xs font-medium">
                {{ __('Account Settings') }}
            </flux:menu.item>
            <flux:menu.item :href="route('security.edit')" icon="shield-check" wire:navigate class="text-xs font-medium">
                {{ __('Security & 2FA') }}
            </flux:menu.item>
            <flux:menu.separator />
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer text-xs font-medium text-red-600 dark:text-red-400"
                    data-test="logout-button"
                >
                    {{ __('Log Out') }}
                </flux:menu.item>
            </form>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
