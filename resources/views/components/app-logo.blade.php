@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand {{ $attributes }}>
        <x-slot name="logo" class="relative flex aspect-square size-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 via-purple-600 to-indigo-700 text-white shadow-lg shadow-indigo-500/30 ring-1 ring-white/30 transition-transform hover:scale-105">
            <x-app-logo-icon class="size-5 text-white drop-shadow-sm" />
            <span class="absolute -top-0.5 -right-0.5 size-2.5 rounded-full bg-emerald-400 ring-2 ring-white dark:ring-zinc-950 animate-pulse"></span>
        </x-slot>
        <div class="flex items-center gap-1.5 font-sans">
            <span class="font-extrabold tracking-tight text-slate-900 dark:text-white text-base">Synkk</span>
            <span class="rounded-full bg-indigo-500/10 px-1.5 py-0.5 text-[9px] font-extrabold text-indigo-600 dark:bg-indigo-400/20 dark:text-indigo-300 ring-1 ring-indigo-500/30">PRO</span>
        </div>
    </flux:sidebar.brand>
@else
    <flux:brand {{ $attributes }}>
        <x-slot name="logo" class="relative flex aspect-square size-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 via-purple-600 to-indigo-700 text-white shadow-lg shadow-indigo-500/30 ring-1 ring-white/30 transition-transform hover:scale-105">
            <x-app-logo-icon class="size-5 text-white drop-shadow-sm" />
            <span class="absolute -top-0.5 -right-0.5 size-2.5 rounded-full bg-emerald-400 ring-2 ring-white dark:ring-zinc-950 animate-pulse"></span>
        </x-slot>
        <div class="flex items-center gap-1.5 font-sans">
            <span class="font-extrabold tracking-tight text-slate-900 dark:text-white text-base">Synkk</span>
            <span class="rounded-full bg-indigo-500/10 px-1.5 py-0.5 text-[9px] font-extrabold text-indigo-600 dark:bg-indigo-400/20 dark:text-indigo-300 ring-1 ring-indigo-500/30">PRO</span>
        </div>
    </flux:brand>
@endif

