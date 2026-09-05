@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'Synkk')" {{ $attributes }}>
        <x-slot name="logo" class="flex items-center justify-center">
            <img src="{{ asset('images/synkk-logo.svg') }}" alt="Synkk" class="h-8 w-auto max-w-32 object-contain dark:invert" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'Synkk')" {{ $attributes }}>
        <x-slot name="logo" class="flex items-center justify-center">
            <img src="{{ asset('images/synkk-logo.svg') }}" alt="Synkk" class="h-8 w-auto max-w-32 object-contain dark:invert" />
        </x-slot>
    </flux:brand>
@endif

