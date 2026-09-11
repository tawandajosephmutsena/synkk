<?php

namespace App\Services;

use App\Exceptions\DodoPaymentsException;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class DodoPaymentsService
{
    /**
     * Determine whether the server has enough configuration to create Dodo sessions.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.dodo.api_key'))
            && filled(config('services.dodo.cloud_product_id'));
    }

    /**
     * Create a hosted checkout session for the team's Cloud subscription.
     *
     * @throws DodoPaymentsException
     */
    public function createCheckoutSession(Team $team, User $user): string
    {
        $this->ensureConfigured();

        $customer = filled($team->billing_customer_id)
            ? ['customer_id' => $team->billing_customer_id]
            : [
                'email' => $user->email,
                'name' => $user->name,
            ];

        $response = $this->client()->post('/checkouts', [
            'product_cart' => [[
                'product_id' => config('services.dodo.cloud_product_id'),
                'quantity' => 1,
            ]],
            'billing_currency' => config('services.dodo.currency', 'USD'),
            'customer' => $customer,
            'metadata' => [
                'team_id' => (string) $team->id,
                'user_id' => (string) $user->id,
                'plan' => 'cloud',
            ],
            'return_url' => route('billing.dodo.return', ['current_team' => $team->slug]),
            'cancel_url' => route('dashboard', ['current_team' => $team->slug]),
        ]);

        if (! $response->successful()) {
            throw new DodoPaymentsException(
                'Dodo Payments rejected the checkout request.',
                $response->status(),
            );
        }

        $checkoutUrl = $response->json('checkout_url');

        if (! is_string($checkoutUrl) || ! str_starts_with($checkoutUrl, 'https://')) {
            throw new DodoPaymentsException('Dodo Payments returned an invalid checkout URL.');
        }

        return $checkoutUrl;
    }

    /**
     * Create a short-lived customer portal session for billing management.
     *
     * @throws DodoPaymentsException
     */
    public function createCustomerPortalSession(Team $team): string
    {
        $this->ensureConfigured();

        if (blank($team->billing_customer_id)) {
            throw new DodoPaymentsException('No Dodo customer is linked to this workspace.');
        }

        $path = '/customers/'.rawurlencode($team->billing_customer_id).'/customer-portal/session';
        $url = $path.'?'.http_build_query([
            'return_url' => route('dashboard', ['current_team' => $team->slug]),
        ]);

        $response = $this->client()->post($url);

        if (! $response->successful()) {
            throw new DodoPaymentsException(
                'Dodo Payments rejected the customer portal request.',
                $response->status(),
            );
        }

        $portalUrl = $response->json('link');

        if (! is_string($portalUrl) || ! str_starts_with($portalUrl, 'https://')) {
            throw new DodoPaymentsException('Dodo Payments returned an invalid customer portal URL.');
        }

        return $portalUrl;
    }

    /**
     * Build the authenticated Dodo API client.
     */
    protected function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.dodo.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withToken((string) config('services.dodo.api_key'))
            ->connectTimeout(3)
            ->timeout(10);
    }

    /**
     * Ensure the checkout integration cannot make an unauthenticated API call.
     *
     * @throws DodoPaymentsException
     */
    protected function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new DodoPaymentsException('Dodo Payments is not configured.');
        }
    }
}
