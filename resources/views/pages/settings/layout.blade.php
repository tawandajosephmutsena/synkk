<div class="flex items-start max-md:flex-col gap-8">
    <div class="w-full md:w-[240px] shrink-0 rounded-2xl border border-slate-200/90 bg-white p-3.5 dark:border-slate-800/80 dark:bg-[#0D121F] shadow-sm">
        <div class="px-2 py-1.5 text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            {{ __('Account & System') }}
        </div>
        <flux:navlist aria-label="{{ __('Settings') }}" class="mt-2 space-y-1">
            <flux:navlist.item icon="cog" :href="route('profile.edit')" wire:navigate class="font-bold text-xs py-2">{{ __('Profile') }}</flux:navlist.item>
            <flux:navlist.item icon="shield-check" :href="route('security.edit')" wire:navigate class="font-bold text-xs py-2">{{ __('Security & 2FA') }}</flux:navlist.item>
            <flux:navlist.item icon="users" :href="route('teams.index')" :current="request()->routeIs('teams.*')" wire:navigate class="font-bold text-xs py-2">{{ __('Teams') }}</flux:navlist.item>
            <flux:navlist.item icon="swatch" :href="route('appearance.edit')" wire:navigate class="font-bold text-xs py-2">{{ __('Appearance') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-2 rounded-2xl border border-slate-200/90 bg-white p-6 sm:p-8 dark:border-slate-800/80 dark:bg-[#0D121F] shadow-sm">
        <flux:heading size="lg" class="font-black text-slate-950 dark:text-white">{{ $heading ?? '' }}</flux:heading>
        <flux:subheading class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-1">{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-6 w-full max-w-xl">
            {{ $slot }}
        </div>
    </div>
</div>

