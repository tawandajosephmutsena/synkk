@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'Synkk')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8.5 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-purple-600 text-white shadow-md shadow-indigo-500/25 ring-1 ring-white/20">
            <x-app-logo-icon class="size-4.5 text-white" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'Synkk')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8.5 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-purple-600 text-white shadow-md shadow-indigo-500/25 ring-1 ring-white/20">
            <x-app-logo-icon class="size-4.5 text-white" />
        </x-slot>
    </flux:brand>
@endif
