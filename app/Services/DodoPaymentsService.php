<?php

namespace App\Services;

use App\Exceptions\DodoPaymentsException;
use App\Models\PendingSaasCheckout;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class DodoPaymentsService
{
    /**
     * Determine whether the server has enough configuration to create Dodo sessions.
     */
    public function isConfigured(?string $plan = null): bool
    {
        return filled(config('services.dodo.api_key'))
            && filled($this->productIdForPlan($plan ?? 'cloud'));
    }

    /**
     * Determine whether the server is configured to sell the Managed AI Add-On via Dodo.
     */
    public function isAiAddonConfigured(): bool
    {
        return filled(config('services.dodo.api_key'))
            && filled(config('services.dodo.ai_addon_product_id'));
    }

    /**
     * Create a hosted checkout session for the workspace Managed AI Add-On.
     *
     * @throws DodoPaymentsException
     */
    public function createAiAddonCheckoutSession(Team $team, User $user): string
    {
        if (! $this->isAiAddonConfigured()) {
            throw new DodoPaymentsException('Dodo Payments AI add-on product is not configured.');
        }

        $productId = (string) config('services.dodo.ai_addon_product_id');

        $customer = filled($team->billing_customer_id)
            ? ['customer_id' => $team->billing_customer_id]
            : [
                'email' => $user->email,
                'name' => $user->name,
            ];

        $response = $this->client()->post('/checkouts', [
            'product_cart' => [[
                'product_id' => $productId,
                'quantity' => 1,
            ]],
            'billing_currency' => config('services.dodo.currency', 'USD'),
            'customer' => $customer,
            'metadata' => [
                'team_id' => (string) $team->id,
                'user_id' => (string) $user->id,
                'addon' => 'ai',
            ],
            'return_url' => route('teams.edit', ['team' => $team->slug]),
            'cancel_url' => route('teams.edit', ['team' => $team->slug]),
        ]);

        if (! $response->successful()) {
            throw new DodoPaymentsException(
                'Dodo Payments rejected the AI add-on checkout request.',
                $response->status(),
            );
        }

        $checkoutUrl = $response->json('checkout_url');

        if (! is_string($checkoutUrl) || ! str_starts_with($checkoutUrl, 'https://')) {
            throw new DodoPaymentsException('Dodo Payments returned an invalid AI add-on checkout URL.');
        }

        return $checkoutUrl;
    }

    /**
     * Determine whether Dodo checkout is configured for a specific plan.
     */
    public function isPlanConfigured(string $plan): bool
    {
        return filled(config('services.dodo.api_key'))
            && filled($this->productIdForPlan($plan));
    }

    /**
     * Resolve the configured Dodo product ID for a specific plan without silent fallback.
     */
    public function productIdForPlan(string $plan): ?string
    {
        $productId = match ($plan) {
            'cloud', 'cloud_pro' => config('services.dodo.cloud_product_id'),
            'business' => config('services.dodo.business_product_id') ?: config('services.dodo.cloud_business_product_id'),
            'cloud_business' => config('services.dodo.cloud_business_product_id') ?: config('services.dodo.business_product_id'),
            'pro_ltd' => config('services.dodo.pro_product_id'),
            default => null,
        };

        return is_string($productId) && filled($productId) ? $productId : null;
    }

    /**
     * Create a hosted checkout session before a SaaS account exists.
     *
     * @return array{session_id: string, checkout_url: string}
     *
     * @throws DodoPaymentsException
     */
    public function createSignupCheckoutSession(
        PendingSaasCheckout $pending,
        string $name,
        string $email,
        string $token,
    ): array {
        if (! filled(config('services.dodo.api_key'))) {
            throw new DodoPaymentsException('Dodo Payments is not configured.');
        }

        $productId = $this->productIdForPlan($pending->plan);

        if ($productId === null) {
            throw new DodoPaymentsException("Dodo Payments product is not configured for plan [{$pending->plan}].");
        }

        $response = $this->client()->post('/checkouts', [
            'product_cart' => [[
                'product_id' => $productId,
                'quantity' => 1,
            ]],
            'billing_currency' => config('services.dodo.currency', 'USD'),
            'customer' => [
                'email' => $email,
                'name' => $name,
            ],
            'metadata' => [
                'pending_checkout_id' => (string) $pending->id,
                'plan' => $pending->plan,
            ],
            'return_url' => route('saas.subscribe.return', ['pending_token' => $token]),
            'cancel_url' => route('saas.subscribe'),
        ]);

        if (! $response->successful()) {
            throw new DodoPaymentsException(
                'Dodo Payments rejected the SaaS checkout request.',
                $response->status(),
            );
        }

        $sessionId = $response->json('session_id');
        $checkoutUrl = $response->json('checkout_url');

        if (! is_string($sessionId) || blank($sessionId)) {
            throw new DodoPaymentsException('Dodo Payments returned an invalid checkout session ID.');
        }

        if (! is_string($checkoutUrl) || ! str_starts_with($checkoutUrl, 'https://')) {
            throw new DodoPaymentsException('Dodo Payments returned an invalid checkout URL.');
        }

        return [
            'session_id' => $sessionId,
            'checkout_url' => $checkoutUrl,
        ];
    }

    /**
     * Create a hosted checkout session for the team's Cloud subscription or Pro license.
     *
     * @throws DodoPaymentsException
     */
    public function createCheckoutSession(Team $team, User $user, string $plan = 'cloud'): string
    {
        if (! filled(config('services.dodo.api_key'))) {
            throw new DodoPaymentsException('Dodo Payments is not configured.');
        }

        $productId = $this->productIdForPlan($plan);

        if ($productId === null) {
            throw new DodoPaymentsException("Dodo Payments product is not configured for plan [{$plan}].");
        }

        $customer = filled($team->billing_customer_id)
            ? ['customer_id' => $team->billing_customer_id]
            : [
                'email' => $user->email,
                'name' => $user->name,
            ];

        $response = $this->client()->post('/checkouts', [
            'product_cart' => [[
                'product_id' => $productId,
                'quantity' => 1,
            ]],
            'billing_currency' => config('services.dodo.currency', 'USD'),
            'customer' => $customer,
            'metadata' => [
                'team_id' => (string) $team->id,
                'user_id' => (string) $user->id,
                'plan' => $plan,
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
        if (! filled(config('services.dodo.api_key'))) {
            throw new DodoPaymentsException('Dodo Payments is not configured.');
        }

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
