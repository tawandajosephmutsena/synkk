<?php

namespace App\Http\Controllers\Billing;

use App\Exceptions\DodoPaymentsException;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\DodoPaymentsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DodoBillingController extends Controller
{
    /**
     * Start the team's Cloud subscription checkout.
     */
    public function checkout(Request $request, Team $current_team, DodoPaymentsService $payments): RedirectResponse
    {
        Gate::authorize('update', $current_team);

        if ($current_team->hasActiveDodoSubscription()) {
            return back()->with('status', __('This workspace already has a Dodo subscription.'));
        }

        try {
            $checkoutUrl = $payments->createCheckoutSession($current_team, $request->user());
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
