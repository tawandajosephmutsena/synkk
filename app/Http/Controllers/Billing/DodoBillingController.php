<?php

namespace App\Http\Controllers\Billing;

use App\Exceptions\DodoPaymentsException;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\DodoPaymentsService;
use App\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DodoBillingController extends Controller
{
    /**
     * Start the team's Managed AI Add-On subscription checkout.
     */
    public function aiAddonCheckout(
        Request $request,
        Team $current_team,
        DodoPaymentsService $payments,
        PlanService $planService,
    ): RedirectResponse {
        Gate::authorize('update', $current_team);

        if (! $planService->hasFeature($current_team, 'ai_managed_cloud')) {
            abort(403, 'Synkk Managed Cloud AI requires an active Synkk Cloud plan.');
        }

        if ($current_team->aiConfig?->hasActiveManagedSubscription()) {
            return back()->with('status', __('This workspace already has an active Managed AI Add-On subscription.'));
        }

        try {
            /** @var User $user */
            $user = $request->user();
            $checkoutUrl = $payments->createAiAddonCheckoutSession($current_team, $user);
        } catch (DodoPaymentsException $exception) {
            report($exception);

            return back()->withErrors([
                'billing' => __('Managed AI checkout is not available yet. Please try again shortly.'),
            ]);
        }

        return redirect()->away($checkoutUrl);
    }

    /**
     * Start the team's Cloud subscription or Pro Lifetime checkout.
     */
    public function checkout(Request $request, Team $current_team, DodoPaymentsService $payments): RedirectResponse
    {
        Gate::authorize('update', $current_team);

        $validated = $request->validate([
            'plan' => ['nullable', 'string', Rule::in(['cloud', 'cloud_pro', 'cloud_business', 'pro_ltd'])],
        ]);
        $plan = is_string($validated['plan'] ?? null) ? $validated['plan'] : 'cloud';

        if ($plan !== 'pro_ltd' && $current_team->hasActiveDodoSubscription()) {
            return back()->with('status', __('This workspace already has a Dodo subscription.'));
        }

        if ($plan === 'pro_ltd' && $current_team->plan === 'pro_ltd' && $current_team->license_status === 'active') {
            return back()->with('status', __('This workspace already has an active Pro Lifetime license.'));
        }

        try {
            $checkoutUrl = $payments->createCheckoutSession($current_team, $request->user(), $plan);
        } catch (DodoPaymentsException $exception) {
            report($exception);

            return back()->withErrors([
                'billing' => __('Cloud checkout is not available yet. Please try again shortly.'),
            ]);
        }

        return redirect()->away($checkoutUrl);
    }

    /**
     * Open the team's hosted Dodo billing portal.
     */
    public function portal(Team $current_team, DodoPaymentsService $payments): RedirectResponse
    {
        Gate::authorize('update', $current_team);

        try {
            $portalUrl = $payments->createCustomerPortalSession($current_team);
        } catch (DodoPaymentsException $exception) {
            report($exception);

            return back()->withErrors([
                'billing' => __('The billing portal is not available yet. Please try again shortly.'),
            ]);
        }

        return redirect()->away($portalUrl);
    }

    /**
     * Return to Synkk after Dodo has completed or cancelled checkout.
     */
    public function checkoutReturn(Team $current_team): RedirectResponse
    {
        Gate::authorize('view', $current_team);

        return redirect()
            ->route('dashboard', ['current_team' => $current_team->slug])
            ->with('status', __('Thanks. We are confirming your Cloud subscription now.'));
    }
}
