<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="mt-12 pt-8 border-t border-gray-100 dark:border-zinc-800">
    <div class="rounded-2xl border border-red-200/70 bg-red-50/40 p-5 dark:border-red-900/30 dark:bg-red-950/20">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h3 class="font-bold text-sm text-red-900 dark:text-red-300">{{ __('Delete account') }}</h3>
                <p class="text-xs text-red-600/80 dark:text-red-400/80 mt-0.5">{{ __('Permanently delete your account and remove all personal data and sync memberships.') }}</p>
            </div>

            <flux:modal.trigger name="confirm-user-deletion">
                <flux:button variant="danger" data-test="delete-user-button" class="!rounded-full px-5 font-bold text-xs shrink-0">
                    {{ __('Delete account') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <livewire:pages::settings.delete-user-modal />
</section>
