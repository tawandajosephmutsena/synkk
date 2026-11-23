<x-layouts::auth :title="__('Choose a Synkk Cloud plan')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Choose your Synkk Cloud plan')"
            :description="__('Start your 30-day free trial. Credit card required upfront to prevent abuse; cancel anytime before day 30 with zero charge.')"
        />

        @if ($errors->has('billing'))
            <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                {{ $errors->first('billing') }}
            </div>
        @endif

        <form method="POST" action="{{ route('saas.subscribe.checkout') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input
                name="name"
                :label="__('Your name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />

            <flux:input
                name="workspace_name"
                :label="__('Workspace name')"
                :value="old('workspace_name')"
                type="text"
                required
                autocomplete="organization"
                :placeholder="__('Your team or company')"
            />

            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <fieldset class="flex flex-col gap-3">
                <legend class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Select a plan') }}</legend>

                @foreach ($plans as $key => $plan)
                    <label class="flex cursor-pointer gap-3 rounded-xl border border-zinc-200 p-4 has-[:checked]:border-emerald-600 has-[:checked]:ring-2 has-[:checked]:ring-emerald-100 dark:border-zinc-700 dark:has-[:checked]:border-emerald-400 dark:has-[:checked]:ring-emerald-900/50 {{ ! $plan['configured'] ? 'cursor-not-allowed opacity-60' : '' }}">
                        <input
                            type="radio"
                            name="plan"
                            value="{{ $key }}"
                            @checked(old('plan', $selectedPlan) === $key)
                            @disabled(! $plan['configured'])
                            class="mt-1"
                        >
                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="flex flex-wrap items-baseline justify-between gap-2">
                                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $plan['name'] }}</span>
                                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $plan['price'] }}<small class="ml-1 font-normal text-zinc-500">{{ $plan['billing_label'] }}</small></span>
                            </span>
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ number_format((int) $plan['storage_limit_mb']) }} MB · {{ $plan['max_vaults'] }} vaults · {{ $plan['max_devices'] }} devices · {{ $plan['max_members'] }} members
                            </span>
                            @unless ($plan['configured'])
                                <span class="text-xs text-amber-700 dark:text-amber-300">{{ __('Payment setup is not configured for this plan yet.') }}</span>
                            @endunless
                        </span>
                    </label>
                @endforeach
            </fieldset>

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Continue to secure payment') }}
            </flux:button>
        </form>

        <p class="text-center text-xs text-zinc-500 dark:text-zinc-400">
            {{ __('You will enter card details on Dodo Payments for your 30-day trial ($0 billed today). No free SaaS account is created.') }}
        </p>

        <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Already have an account?') }}
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
