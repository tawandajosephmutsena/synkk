@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'Synkk')" {{ $attributes }}>
        <x-slot name="logo" class="flex items-center justify-center">
            <img src="{{ asset('images/synkk-logo.svg') }}" alt="Synkk" class="h-8 w-auto max-w-32 object-contain dark:invert in-data-flux-sidebar-collapsed-desktop:hidden" />
            <div class="hidden in-data-flux-sidebar-collapsed-desktop:flex size-8 shrink-0 items-center justify-center rounded-xl bg-[#0D3B29] text-white font-bold text-sm shadow-xs dark:bg-emerald-600">
                S
            </div>
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'Synkk')" {{ $attributes }}>
        <x-slot name="logo" class="flex items-center justify-center">
            <img src="{{ asset('images/synkk-logo.svg') }}" alt="Synkk" class="h-8 w-auto max-w-32 object-contain dark:invert" />
        </x-slot>
    </flux:brand>
@endif

