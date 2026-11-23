<?php

use App\Models\Team;
use App\Services\PlanService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Billing')] class extends Component {
    #[Computed]
    public function team(): ?Team
    {
        return Auth::user()->currentTeam
            ?? Auth::user()->personalTeam()
            ?? Auth::user()->teams()->first();
    }

    #[Computed]
    public function planSummary(): array
    {
        return $this->team
            ? app(PlanService::class)->getUsageSummary($this->team)
            : [];
    }

    #[Computed]
    public function canManageBilling(): bool
    {
        return $this->team !== null && Auth::user()->ownsTeam($this->team);
    }

    #[Computed]
    public function hasDodoSubscription(): bool
    {
        return $this->team?->billing_provider === 'dodo'
            && filled($this->team?->billing_subscription_id);
    }

    #[Computed]
    public function nextBillingDate(): ?string
    {
        return $this->team?->billing_next_billing_at
            ?->timezone(config('app.timezone'))
            ?->format('M j, Y');
    }

    #[Computed]
    public function billingStatus(): string
    {
        return str($this->team?->billing_status ?? 'not connected')
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    #[Computed]
    public function currentPlanPrice(): ?string
    {
        $pricing = config('synkk.pricing.'.($this->team?->plan ?? ''), []);

        if (! is_array($pricing) || ! array_key_exists('amount', $pricing)) {
            return null;
        }

        return ($pricing['currency_symbol'] ?? '$').number_format((float) $pricing['amount'], 0)
            .' '.($pricing['billing_label'] ?? '');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Billing settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Billing')" :subheading="__('Manage your Synkk Cloud subscription, payment details, and invoices.')">
        @if (! $this->team)
            <flux:text>{{ __('No workspace is available for billing yet.') }}</flux:text>
        @else
            <div class="space-y-6">
                @if ($errors->has('billing'))
                    <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
                        {{ $errors->first('billing') }}
                    </div>
                @endif

                @if (session('status'))
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="rounded-2xl border border-gray-100 bg-[#FBFBFA] p-5 shadow-2xs dark:border-zinc-800/80 dark:bg-zinc-800/40">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading size="lg">{{ $this->team->planName() }}</flux:heading>
                                <flux:badge color="emerald" size="sm" class="rounded-full font-bold">{{ $this->planSummary['plan_badge'] ?? 'Free CE' }}</flux:badge>
                            </div>
                            <flux:subheading class="mt-1">
                                {{ __('Workspace: :name', ['name' => $this->team->name]) }}
                                @if ($this->currentPlanPrice)
                                    · {{ $this->currentPlanPrice }}
                                @endif
                            </flux:subheading>
                        </div>

                        @if ($this->hasDodoSubscription && $this->canManageBilling)
                            <form method="POST" action="{{ route('billing.dodo.portal', ['current_team' => $this->team->slug]) }}">
                                @csrf
                                <flux:button type="submit" variant="primary" class="!rounded-full !bg-[#0D3B29] !text-white hover:!bg-[#09261b] text-xs font-bold">
                                    {{ __('Payment details & invoices') }}
                                </flux:button>
                            </form>
                        @endif
                    </div>

                    @if ($this->hasDodoSubscription)
                        <dl class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-gray-100 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-400">{{ __('Status') }}</dt>
                                <dd class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $this->billingStatus }}</dd>
                            </div>
                            <div class="rounded-xl border border-gray-100 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-400">{{ __('Next payment') }}</dt>
                                <dd class="mt-1 text-sm font-bold text-gray-900 dark:text-white">
                                    {{ $this->nextBillingDate ?? __('Pending provider confirmation') }}
                                </dd>
                            </div>
                            <div class="rounded-xl border border-gray-100 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-400">{{ __('Billing cycle') }}</dt>
                                <dd class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ __('Monthly') }}</dd>
                            </div>
                        </dl>

                        <p class="mt-4 text-xs leading-5 text-gray-500 dark:text-zinc-400">
                            {{ __('Use the secure Dodo portal to update your saved payment method, view or download invoices, edit billing information, cancel, or manage your subscription.') }}
                        </p>

                        @if ($this->team->plan === 'cloud' && $this->canManageBilling)
                            <div class="mt-5 flex flex-col gap-3 rounded-xl border border-indigo-200 bg-indigo-50/60 p-4 dark:border-indigo-900/60 dark:bg-indigo-950/20 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="text-sm font-bold text-indigo-950 dark:text-indigo-200">{{ __('Need more capacity? Upgrade to Cloud Business.') }}</div>
                                    <p class="mt-1 text-xs leading-5 text-indigo-900/70 dark:text-indigo-200/70">
                                        {{ __('Business is $25/month with 25,000 MB storage, 25 vaults, 100 devices, and 25 team seats. Dodo will use your existing payment method and apply the prorated difference immediately.') }}
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('billing.dodo.change-plan', ['current_team' => $this->team->slug]) }}" class="shrink-0" onsubmit='return window.confirm(@js(__('Upgrade this workspace to Cloud Business now? Dodo will charge the prorated difference using your saved payment method.')))'>
                                    @csrf
                                    <input type="hidden" name="plan" value="business">
                                    <flux:button
                                        type="submit"
                                        variant="primary"
                                        class="!rounded-full !bg-indigo-700 !text-white hover:!bg-indigo-800 text-xs font-bold"
                                    >
                                        {{ __('Upgrade to Business') }}
                                    </flux:button>
                                </form>
                            </div>
                        @endif
                    @elseif ($this->team->plan === 'free' && $this->canManageBilling && filled(config('services.dodo.api_key')) && filled(config('services.dodo.cloud_product_id')))
                        <div class="mt-6 rounded-xl border border-teal-200 bg-teal-50/60 p-4 dark:border-teal-900/60 dark:bg-teal-950/20">
                            <div class="text-sm font-bold text-teal-950 dark:text-teal-200">{{ __('Start Synkk Cloud Pro') }}</div>
                            <p class="mt-1 text-xs leading-5 text-teal-900/70 dark:text-teal-200/70">{{ __('Subscribe for $12/month and unlock 500 MB storage, 5 vaults, and 25 connected devices.') }}</p>
                            <form method="POST" action="{{ route('billing.dodo.checkout', ['current_team' => $this->team->slug]) }}" class="mt-3">
                                @csrf
                                <input type="hidden" name="plan" value="cloud">
                                <flux:button type="submit" variant="primary" class="!rounded-full !bg-teal-700 !text-white hover:!bg-teal-800 text-xs font-bold">
                                    {{ __('Subscribe with Dodo') }}
                                </flux:button>
                            </form>
                        </div>
                    @elseif ($this->team->plan === 'free')
                        <p class="mt-6 text-xs text-gray-500 dark:text-zinc-400">{{ __('Cloud billing is not configured yet. Please contact support.') }}</p>
                    @else
                        <p class="mt-6 text-xs leading-5 text-gray-500 dark:text-zinc-400">
                            {{ __('This workspace is using a non-subscription entitlement. Recurring payment details are managed by the provider that issued that entitlement.') }}
                        </p>
                    @endif
                </div>

                <div>
                    <flux:heading>{{ __('Plan limits') }}</flux:heading>
                    <flux:subheading class="mt-1">{{ __('Current usage for :name', ['name' => $this->team->name]) }}</flux:subheading>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-100 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="text-xs font-semibold text-gray-500 dark:text-zinc-400">{{ __('Cloud storage') }}</div>
                        <div class="mt-1 text-lg font-black text-gray-900 dark:text-white">{{ $this->planSummary['storage']['used_mb'] ?? 0 }} / {{ $this->planSummary['storage']['limit_mb'] ?? 0 }} MB</div>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="text-xs font-semibold text-gray-500 dark:text-zinc-400">{{ __('Vaults') }}</div>
                        <div class="mt-1 text-lg font-black text-gray-900 dark:text-white">{{ $this->planSummary['vaults']['used'] ?? 0 }} / {{ $this->planSummary['vaults']['limit'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="text-xs font-semibold text-gray-500 dark:text-zinc-400">{{ __('Connected devices') }}</div>
                        <div class="mt-1 text-lg font-black text-gray-900 dark:text-white">{{ $this->planSummary['devices']['used'] ?? 0 }} / {{ $this->planSummary['devices']['limit'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="text-xs font-semibold text-gray-500 dark:text-zinc-400">{{ __('Team seats') }}</div>
                        <div class="mt-1 text-lg font-black text-gray-900 dark:text-white">{{ $this->planSummary['members']['used'] ?? 0 }} / {{ $this->planSummary['members']['limit'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
        @endif
    </x-pages::settings.layout>
</section>
