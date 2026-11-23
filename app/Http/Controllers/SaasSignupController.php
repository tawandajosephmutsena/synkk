<?php

namespace App\Http\Controllers;

use App\Exceptions\DodoPaymentsException;
use App\Models\User;
use App\Rules\TeamName;
use App\Services\DodoPaymentsService;
use App\Services\SaasSignupService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaasSignupController extends Controller
{
    /**
     * Show the paid SaaS plan selector.
     */
    public function create(Request $request, DodoPaymentsService $payments): View
    {
        $configuredPlans = config('synkk.saas_signup.plans', []);
        $plans = [];

        if (is_array($configuredPlans)) {
            foreach ($configuredPlans as $plan) {
                if (! is_string($plan)) {
                    continue;
                }

                $config = config('synkk.plans.'.$plan, []);
                $pricing = config('synkk.pricing.'.$plan, []);
                $config = is_array($config) ? $config : [];
                $pricing = is_array($pricing) ? $pricing : [];

                $plans[$plan] = [
                    ...$config,
                    'price' => ($pricing['currency_symbol'] ?? '$').number_format((float) ($pricing['amount'] ?? 0), 0),
                    'billing_label' => $pricing['billing_label'] ?? 'per workspace / month',
                    'configured' => $payments->isConfigured($plan),
                ];
            }
        }

        $selectedPlan = $request->query('plan');
        if (! is_string($selectedPlan) || ! array_key_exists($selectedPlan, $plans)) {
            $selectedPlan = 'cloud';
        }

        return view('saas.subscribe', compact('plans', 'selectedPlan'));
    }

    /**
     * Create a Dodo checkout without creating a local account.
     */
    public function checkout(Request $request, SaasSignupService $signup): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'workspace_name' => ['required', 'string', 'max:255', new TeamName],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'plan' => ['required', 'string', Rule::in(config('synkk.saas_signup.plans', []))],
        ]);

        try {
            $result = $signup->startCheckout($validated);
        } catch (DodoPaymentsException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['billing' => __('Secure payment is not available yet. Please try again shortly.')]);
        }

        return redirect()->away($result['checkout']['checkout_url']);
    }

    /**
     * Show the post-payment account completion screen.
     */
    public function checkoutReturn(Request $request, SaasSignupService $signup): View
    {
        $token = $request->string('pending_token')->toString();
        $pending = $signup->findByToken($token);

        abort_unless($pending !== null, 404);

        return view('saas.complete', [
            'pending' => $pending,
            'pendingToken' => $token,
            'completionUrl' => route('saas.subscribe.complete'),
        ]);
    }

    /**
     * Create the user and paid workspace after the webhook confirms payment.
     */
    public function complete(Request $request, SaasSignupService $signup): RedirectResponse
    {
        $validated = $request->validate([
            'pending_token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        $pending = $signup->findByToken($validated['pending_token']);
        if (! $pending || ! $pending->isReadyToComplete()) {
            return back()->withErrors([
                'billing' => __('Your payment is still being confirmed. Please refresh and try again.'),
            ]);
        }

        $user = $signup->completeSignup($pending, $validated);
        try {
            event(new Registered($user));
        } catch (\Throwable $exception) {
            report($exception);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $team = $user->personalTeam();

        return redirect()->route('dashboard', ['current_team' => $team->slug]);
    }
}
