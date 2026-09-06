<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Appearance settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Appearance settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Customize how Synkk looks on your device')">
        <div x-data class="space-y-6">
            <!-- Visual Theme Preview Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Light Theme Option -->
                <button
                    type="button"
                    @click="$flux.appearance = 'light'"
                    :class="$flux.appearance === 'light' ? 'border-[#0D3B29] ring-2 ring-[#0D3B29]/20 bg-[#F4F8F5] dark:border-emerald-500 dark:ring-emerald-500/30' : 'border-gray-200 hover:border-gray-300 bg-white dark:border-zinc-800 dark:bg-zinc-900/50'"
                    class="group relative flex flex-col items-start p-4 rounded-2xl border text-left transition-all duration-200 hover:shadow-xs cursor-pointer"
                >
                    <!-- Mini Light Mockup -->
                    <div class="w-full h-24 rounded-xl border border-gray-200/90 bg-[#F4F6F8] p-2 flex flex-col gap-1.5 overflow-hidden shadow-2xs">
                        <div class="w-full h-3 bg-[#0D3B29] rounded-md flex items-center px-1.5 justify-between">
                            <div class="flex gap-0.5">
                                <span class="size-1 rounded-full bg-white/40"></span>
                                <span class="size-1 rounded-full bg-white/40"></span>
                            </div>
                            <span class="h-1 w-6 bg-white/30 rounded-full"></span>
                        </div>
                        <div class="flex-1 flex gap-1.5">
                            <div class="w-5 bg-white rounded-md border border-gray-200/60 p-1 flex flex-col gap-1">
                                <span class="h-1 w-full bg-gray-200 rounded"></span>
                                <span class="h-1 w-2/3 bg-gray-200 rounded"></span>
                            </div>
                            <div class="flex-1 flex flex-col gap-1">
                                <div class="h-7 bg-white rounded-md border border-gray-200/60 p-1 flex items-center justify-between">
                                    <span class="h-1.5 w-6 bg-emerald-700/80 rounded"></span>
                                    <span class="size-2 rounded-full bg-emerald-100"></span>
                                </div>
                                <div class="flex-1 bg-white rounded-md border border-gray-200/60"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="sun" class="size-4 text-amber-500" />
                            <span class="text-xs font-bold text-gray-900 dark:text-white">{{ __('Light') }}</span>
                        </div>
                        <span
                            x-show="$flux.appearance === 'light'"
                            class="size-4 rounded-full bg-[#0D3B29] text-white flex items-center justify-center text-[9px] font-black shadow-xs dark:bg-emerald-600"
                        >
                            ✓
                        </span>
                    </div>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-zinc-400 leading-snug">{{ __('Crisp, high-contrast forest light mode.') }}</p>
                </button>

                <!-- Dark Theme Option -->
                <button
                    type="button"
                    @click="$flux.appearance = 'dark'"
                    :class="$flux.appearance === 'dark' ? 'border-[#0D3B29] ring-2 ring-[#0D3B29]/20 bg-[#F4F8F5] dark:border-emerald-500 dark:ring-emerald-500/30 dark:bg-emerald-950/20' : 'border-gray-200 hover:border-gray-300 bg-white dark:border-zinc-800 dark:bg-zinc-900/50'"
                    class="group relative flex flex-col items-start p-4 rounded-2xl border text-left transition-all duration-200 hover:shadow-xs cursor-pointer"
                >
                    <!-- Mini Dark Mockup -->
                    <div class="w-full h-24 rounded-xl border border-zinc-700 bg-[#0C0F12] p-2 flex flex-col gap-1.5 overflow-hidden shadow-2xs">
                        <div class="w-full h-3 bg-[#0D3B29] rounded-md flex items-center px-1.5 justify-between">
                            <div class="flex gap-0.5">
                                <span class="size-1 rounded-full bg-white/40"></span>
                                <span class="size-1 rounded-full bg-white/40"></span>
                            </div>
                            <span class="h-1 w-6 bg-white/30 rounded-full"></span>
                        </div>
                        <div class="flex-1 flex gap-1.5">
                            <div class="w-5 bg-zinc-900 rounded-md border border-zinc-800 p-1 flex flex-col gap-1">
                                <span class="h-1 w-full bg-zinc-700 rounded"></span>
                                <span class="h-1 w-2/3 bg-zinc-700 rounded"></span>
                            </div>
                            <div class="flex-1 flex flex-col gap-1">
                                <div class="h-7 bg-zinc-900 rounded-md border border-zinc-800 p-1 flex items-center justify-between">
                                    <span class="h-1.5 w-6 bg-emerald-400 rounded"></span>
                                    <span class="size-2 rounded-full bg-emerald-900"></span>
                                </div>
                                <div class="flex-1 bg-zinc-900 rounded-md border border-zinc-800"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="moon" class="size-4 text-indigo-400" />
                            <span class="text-xs font-bold text-gray-900 dark:text-white">{{ __('Dark') }}</span>
                        </div>
                        <span
                            x-show="$flux.appearance === 'dark'"
                            class="size-4 rounded-full bg-[#0D3B29] text-white flex items-center justify-center text-[9px] font-black shadow-xs dark:bg-emerald-600"
                        >
                            ✓
                        </span>
                    </div>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-zinc-400 leading-snug">{{ __('Obsidian-toned dark workspace.') }}</p>
                </button>

                <!-- System Theme Option -->
                <button
                    type="button"
                    @click="$flux.appearance = 'system'"
                    :class="$flux.appearance === 'system' ? 'border-[#0D3B29] ring-2 ring-[#0D3B29]/20 bg-[#F4F8F5] dark:border-emerald-500 dark:ring-emerald-500/30 dark:bg-emerald-950/20' : 'border-gray-200 hover:border-gray-300 bg-white dark:border-zinc-800 dark:bg-zinc-900/50'"
                    class="group relative flex flex-col items-start p-4 rounded-2xl border text-left transition-all duration-200 hover:shadow-xs cursor-pointer"
                >
                    <!-- Mini Split Mockup -->
                    <div class="w-full h-24 rounded-xl border border-gray-300 dark:border-zinc-700 overflow-hidden flex shadow-2xs">
                        <div class="w-1/2 h-full bg-[#F4F6F8] p-2 flex flex-col gap-1.5 border-r border-gray-200">
                            <div class="w-full h-3 bg-[#0D3B29] rounded-md"></div>
                            <div class="flex-1 bg-white rounded-md border border-gray-200/60"></div>
                        </div>
                        <div class="w-1/2 h-full bg-[#0C0F12] p-2 flex flex-col gap-1.5">
                            <div class="w-full h-3 bg-[#0D3B29] rounded-md"></div>
                            <div class="flex-1 bg-zinc-900 rounded-md border border-zinc-800"></div>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="computer-desktop" class="size-4 text-emerald-600" />
                            <span class="text-xs font-bold text-gray-900 dark:text-white">{{ __('System') }}</span>
                        </div>
                        <span
                            x-show="$flux.appearance === 'system'"
                            class="size-4 rounded-full bg-[#0D3B29] text-white flex items-center justify-center text-[9px] font-black shadow-xs dark:bg-emerald-600"
                        >
                            ✓
                        </span>
                    </div>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-zinc-400 leading-snug">{{ __('Automatically matches device settings.') }}</p>
                </button>
            </div>

            <!-- Segmented Control Fallback & Keyboard Accessibility -->
            <div class="pt-2">
                <flux:radio.group variant="segmented" x-model="$flux.appearance" class="w-full sm:w-auto">
                    <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                    <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                    <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
                </flux:radio.group>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
