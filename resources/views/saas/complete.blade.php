<x-layouts::auth :title="__('Finish your Synkk Cloud account')">
    <div class="flex flex-col gap-6">
        @if ($pending->isReadyToComplete())
            <x-auth-header
                :title="__('Payment confirmed')"
                :description="__('Create your password to finish setting up your paid Synkk Cloud workspace.')"
            />

            <form method="POST" action="{{ $completionUrl }}" class="flex flex-col gap-5">
                @csrf
                <input type="hidden" name="pending_token" value="{{ $pendingToken }}">

                <div class="rounded-lg bg-zinc-50 p-3 text-sm text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                    <strong>{{ $pending->workspace_name }}</strong>
                    <span class="block">{{ $pending->email }} · {{ config('synkk.plans.'.$pending->plan.'.name') }}</span>
                </div>

                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Password')"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />

                <flux:input
                    name="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Confirm password')"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />

                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Create paid workspace') }}
                </flux:button>
            </form>
        @else
            <x-auth-header
                :title="__('Payment is being confirmed')"
                :description="__('Dodo has returned you to Synkk, but the payment confirmation webhook has not arrived yet.')"
            />

            <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                {{ __('Refresh this page in a moment. No account has been created yet.') }}
            </div>

            <flux:button href="{{ url()->full() }}" variant="primary" class="w-full" wire:navigate>
                {{ __('Check payment status') }}
            </flux:button>
        @endif
    </div>
</x-layouts::auth>
