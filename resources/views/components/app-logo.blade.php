@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'Synkk')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-xl bg-lime-300 text-zinc-950 shadow-[inset_0_0_0_1px_rgba(20,33,61,.12)]">
            <x-app-logo-icon class="size-5 fill-current text-zinc-950" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'Synkk')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-xl bg-lime-300 text-zinc-950 shadow-[inset_0_0_0_1px_rgba(20,33,61,.12)]">
            <x-app-logo-icon class="size-5 fill-current text-zinc-950" />
        </x-slot>
    </flux:brand>
@endif
