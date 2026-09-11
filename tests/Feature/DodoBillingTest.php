<?php

use App\Enums\TeamRole;
use App\Models\DodoWebhookEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

test('team owners are redirected to a Dodo Cloud checkout session', function () {
    config()->set([
        'services.dodo.api_key' => 'test-api-key',
        'services.dodo.base_url' => 'https://test.dodopayments.com',
        'services.dodo.cloud_product_id' => 'pdt_cloud_monthly',
        'services.dodo.currency' => 'USD',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://test.dodopayments.com/checkouts' => Http::response([
            'session_id' => 'cks_test_123',
            'checkout_url' => 'https://test.checkout.dodopayments.com/session/cks_test_123',
        ]),
    ]);

    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'free']);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->post(route('billing.dodo.checkout', ['current_team' => $team->slug]));

    $response->assertRedirect('https://test.checkout.dodopayments.com/session/cks_test_123');

    Http::assertSent(function (HttpRequest $request) use ($team, $user): bool {
        $data = $request->data();

        return $request->url() === 'https://test.dodopayments.com/checkouts'
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && $data['product_cart'][0]['product_id'] === 'pdt_cloud_monthly'
            && $data['billing_currency'] === 'USD'
            && $data['metadata']['team_id'] === (string) $team->id
            && $data['metadata']['user_id'] === (string) $user->id;
    });
});

test('team members cannot start a Dodo checkout for a workspace', function () {
    Http::fake();

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($member)
        ->post(route('billing.dodo.checkout', ['current_team' => $team->slug]));

    $response->assertForbidden();
    Http::assertNothingSent();
});

test('team owners can open the Dodo customer portal', function () {
    config()->set([
        'services.dodo.api_key' => 'test-api-key',
        'services.dodo.base_url' => 'https://test.dodopayments.com',
        'services.dodo.cloud_product_id' => 'pdt_cloud_monthly',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://test.dodopayments.com/customers/cus_123/customer-portal/session*' => Http::response([
            'link' => 'https://test.customer.dodopayments.com/session/portal_123',
        ]),
    ]);

    $user = User::factory()->create();
    $team = Team::factory()->create([
        'billing_provider' => 'dodo',
        'billing_customer_id' => 'cus_123',
        'billing_subscription_id' => 'sub_123',
        'billing_status' => 'active',
    ]);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->post(route('billing.dodo.portal', ['current_team' => $team->slug]));

    $response->assertRedirect('https://test.customer.dodopayments.com/session/portal_123');
});

test('a signed Dodo subscription webhook activates the linked workspace once', function () {
    $secret = 'dodo-test-secret';
    config()->set([
        'services.dodo.webhook_key' => 'whsec_'.base64_encode($secret),
        'services.dodo.webhook_tolerance_seconds' => 300,
    ]);

    $team = Team::factory()->create(['plan' => 'free']);
    $payload = [
        'business_id' => 'biz_123',
        'timestamp' => now()->toIso8601String(),
        'type' => 'subscription.active',
        'data' => [
            'subscription_id' => 'sub_123',
            'status' => 'active',
            'product_id' => 'pdt_cloud_monthly',
            'next_billing_date' => now()->addMonth()->toIso8601String(),
            'metadata' => ['team_id' => (string) $team->id],
            'customer' => ['customer_id' => 'cus_123'],
        ],
    ];
    $request = signedDodoWebhookRequest($payload, 'msg_123', $secret);

    $response = $this->call('POST', route('api.billing.dodo.webhook'), [], [], [], $request['headers'], $request['raw']);

    $response->assertOk()->assertJson(['received' => true]);
    $freshTeam = $team->fresh();
    expect($freshTeam->plan)->toBe('cloud')
        ->and($freshTeam->billing_provider)->toBe('dodo')
        ->and($freshTeam->billing_customer_id)->toBe('cus_123')
        ->and($freshTeam->billing_subscription_id)->toBe('sub_123')
        ->and($freshTeam->billing_status)->toBe('active');
    expect(DodoWebhookEvent::query()->count())->toBe(1);

    $expiredPayload = $payload;
    $expiredPayload['type'] = 'subscription.expired';
    $expiredPayload['data']['status'] = 'expired';
    $duplicate = signedDodoWebhookRequest($expiredPayload, 'msg_123', $secret);

    $this->call('POST', route('api.billing.dodo.webhook'), [], [], [], $duplicate['headers'], $duplicate['raw'])
        ->assertOk();

    expect($team->fresh()->plan)->toBe('cloud');
    expect(DodoWebhookEvent::query()->count())->toBe(1);
});

test('an invalid Dodo webhook signature is rejected without changing a workspace', function () {
    config()->set([
        'services.dodo.webhook_key' => 'whsec_'.base64_encode('dodo-test-secret'),
        'services.dodo.webhook_tolerance_seconds' => 300,
    ]);

    $team = Team::factory()->create(['plan' => 'free']);
    $payload = [
        'business_id' => 'biz_123',
        'timestamp' => now()->toIso8601String(),
        'type' => 'subscription.active',
        'data' => [
            'subscription_id' => 'sub_123',
            'metadata' => ['team_id' => (string) $team->id],
        ],
    ];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);

    $response = $this->call('POST', route('api.billing.dodo.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_WEBHOOK_ID' => 'msg_invalid',
        'HTTP_WEBHOOK_SIGNATURE' => 'v1,invalid',
        'HTTP_WEBHOOK_TIMESTAMP' => (string) now()->timestamp,
    ], $raw);

    $response->assertUnauthorized();
    expect($team->fresh()->plan)->toBe('free');
    expect(DodoWebhookEvent::query()->count())->toBe(0);
});

test('an expired Dodo subscription restores the previous lifetime plan', function () {
    $secret = 'dodo-test-secret';
    config()->set([
        'services.dodo.webhook_key' => 'whsec_'.base64_encode($secret),
        'services.dodo.webhook_tolerance_seconds' => 300,
    ]);

    $team = Team::factory()->create([
        'plan' => 'pro_ltd',
        'license_key' => 'SYNK-PRO-ABCD-EFGH-IJKL',
        'license_status' => 'active',
        'billing_provider' => 'dodo',
        'billing_customer_id' => 'cus_123',
        'billing_subscription_id' => 'sub_123',
        'billing_status' => 'active',
        'billing_previous_plan' => 'pro_ltd',
    ]);
    $payload = [
        'business_id' => 'biz_123',
        'timestamp' => now()->toIso8601String(),
        'type' => 'subscription.expired',
        'data' => [
            'subscription_id' => 'sub_123',
            'status' => 'expired',
            'customer' => ['customer_id' => 'cus_123'],
        ],
    ];
    $request = signedDodoWebhookRequest($payload, 'msg_expired', $secret);

    $this->call('POST', route('api.billing.dodo.webhook'), [], [], [], $request['headers'], $request['raw'])
        ->assertOk();

    $freshTeam = $team->fresh();
    expect($freshTeam->plan)->toBe('pro_ltd')
        ->and($freshTeam->license_status)->toBe('active')
        ->and($freshTeam->billing_previous_plan)->toBeNull();
});

/**
 * @return array{raw: string, headers: array<string, string>}
 */
function signedDodoWebhookRequest(array $payload, string $webhookId, string $secret): array
{
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = (string) now()->timestamp;
    $signature = base64_encode(hash_hmac('sha256', $webhookId.'.'.$timestamp.'.'.$raw, $secret, true));

    return [
        'raw' => $raw,
        'headers' => [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_ID' => $webhookId,
            'HTTP_WEBHOOK_SIGNATURE' => 'v1,'.$signature,
            'HTTP_WEBHOOK_TIMESTAMP' => $timestamp,
        ],
    ];
}
