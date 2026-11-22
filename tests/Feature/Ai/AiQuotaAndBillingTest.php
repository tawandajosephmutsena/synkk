<?php

use App\Enums\TeamRole;
use App\Exceptions\AiException;
use App\Models\AiPlatformSetting;
use App\Models\Team;
use App\Models\TeamAiConfig;
use App\Models\User;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiQuotaService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

test('Cloud workspaces receive one-time trial credits, deduct usage, hard-stop at 100%, and optionally fall back to BYOK', function () {
    config()->set([
        'synkk.ai.managed.provider' => 'openai_compatible',
        'synkk.ai.managed.base_url' => 'https://openrouter.ai/api/v1',
        'synkk.ai.managed.api_key' => 'managed-secret-key',
        'synkk.ai.managed.chat_model' => 'openai/gpt-4o-mini',
        'synkk.ai.credits.trial_microcents' => 200_000_000,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'model' => 'openai/gpt-4o-mini',
            'choices' => [['message' => ['content' => 'Managed response'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 500],
        ], 200),
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'model' => 'gpt-4o-mini',
            'choices' => [['message' => ['content' => 'BYOK fallback response'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
        ], 200),
    ]);

    $team = Team::factory()->create(['plan' => 'cloud']);
    $config = TeamAiConfig::create([
        'team_id' => $team->id,
        'is_enabled' => true,
        'provider_mode' => 'managed',
    ]);

    $gateway = app(AiGateway::class);
    $res = $gateway->chat($team, [['role' => 'user', 'content' => 'Hi']]);

    expect($res->content)->toBe('Managed response');

    $config->refresh();
    expect($config->trial_granted_at)->not->toBeNull()
        ->and($config->trial_credits_microcents)->toBeLessThan(200_000_000);

    // Exhaust trial credits completely without active subscription
    $config->update(['trial_credits_microcents' => 0, 'managed_credits_microcents' => 0]);

    // Hard-stop at 100% without fallback
    expect(fn () => $gateway->chat($team, [['role' => 'user', 'content' => 'Blocked']]))
        ->toThrow(AiException::class);

    // Ensure trial is NOT granted a second time
    expect($config->fresh()->trial_credits_microcents)->toBe(0);

    // Enable fallback_to_byok -> seamlessly routes to BYOK
    $config->update([
        'fallback_to_byok' => true,
        'chat_provider' => 'openai',
        'chat_model' => 'gpt-4o-mini',
        'chat_api_key' => 'sk-byok-12345678901234567890',
    ]);

    $fallbackRes = $gateway->chat($team, [['role' => 'user', 'content' => 'Fallback test']]);
    expect($fallbackRes->content)->toBe('BYOK fallback response');
});

test('global platform kill switch pauses Managed Cloud AI immediately', function () {
    config()->set('synkk.ai.managed.api_key', 'managed-secret-key');

    AiPlatformSetting::current()->update(['managed_ai_kill_switch' => true]);

    $team = Team::factory()->create(['plan' => 'cloud']);
    TeamAiConfig::create([
        'team_id' => $team->id,
        'is_enabled' => true,
        'provider_mode' => 'managed',
    ]);

    expect(fn () => app(AiQuotaService::class)->resolveChatRoute($team))
        ->toThrow(AiException::class);
});

test('Dodo AI add-on checkout requires owner role and Cloud plan', function () {
    config()->set([
        'services.dodo.api_key' => 'dodo-test-key',
        'services.dodo.base_url' => 'https://test.dodopayments.com',
        'services.dodo.ai_addon_product_id' => 'pdt_ai_addon_monthly',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://test.dodopayments.com/checkouts' => Http::response([
            'session_id' => 'cks_ai_1',
            'checkout_url' => 'https://test.checkout.dodopayments.com/session/cks_ai_1',
        ]),
    ]);

    $owner = User::factory()->create();
    $member = User::factory()->create();

    $freeTeam = Team::factory()->create(['plan' => 'free']);
    $freeTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this->actingAs($owner)
        ->post(route('billing.dodo.ai_addon.checkout', ['current_team' => $freeTeam->slug]))
        ->assertForbidden();

    $cloudTeam = Team::factory()->create(['plan' => 'cloud']);
    $cloudTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $cloudTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member)
        ->post(route('billing.dodo.ai_addon.checkout', ['current_team' => $cloudTeam->slug]))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('billing.dodo.ai_addon.checkout', ['current_team' => $cloudTeam->slug]))
        ->assertRedirect('https://test.checkout.dodopayments.com/session/cks_ai_1');

    Http::assertSent(function (HttpRequest $request): bool {
        $data = $request->data();

        return $request->url() === 'https://test.dodopayments.com/checkouts'
            && $data['product_cart'][0]['product_id'] === 'pdt_ai_addon_monthly'
            && ($data['metadata']['addon'] ?? null) === 'ai';
    });
});

test('Dodo AI add-on webhooks manage TeamAiConfig independently without mutating core team subscription or plan', function () {
    $secret = 'dodo-ai-webhook-secret';
    config()->set([
        'services.dodo.webhook_key' => 'whsec_'.base64_encode($secret),
        'services.dodo.webhook_tolerance_seconds' => 300,
        'services.dodo.ai_addon_product_id' => 'pdt_ai_addon_monthly',
        'synkk.ai.credits.monthly_microcents' => 1_200_000_000,
    ]);

    $team = Team::factory()->create([
        'plan' => 'cloud',
        'license_status' => 'active',
        'billing_provider' => 'dodo',
        'billing_customer_id' => 'cus_main_123',
        'billing_subscription_id' => 'sub_main_cloud_999',
        'billing_status' => 'active',
    ]);

    // 1. Activate AI add-on subscription
    $activeReq = aiSignedDodoWebhookRequest([
        'timestamp' => now()->toIso8601String(),
        'type' => 'subscription.active',
        'data' => [
            'subscription_id' => 'sub_ai_addon_777',
            'product_id' => 'pdt_ai_addon_monthly',
            'status' => 'active',
            'next_billing_date' => now()->addMonth()->toIso8601String(),
            'customer' => ['customer_id' => 'cus_main_123'],
            'metadata' => ['team_id' => (string) $team->id, 'addon' => 'ai'],
        ],
    ], 'msg_ai_active', $secret);

    $this->call('POST', route('api.billing.dodo.webhook'), [], [], [], $activeReq['headers'], $activeReq['raw'])
        ->assertOk();

    $team->refresh();
    $config = $team->aiConfig()->firstOrFail();

    expect($team->plan)->toBe('cloud')
        ->and($team->billing_subscription_id)->toBe('sub_main_cloud_999')
        ->and($team->billing_status)->toBe('active')
        ->and($config->is_enabled)->toBeTrue()
        ->and($config->provider_mode)->toBe('managed')
        ->and($config->ai_billing_subscription_id)->toBe('sub_ai_addon_777')
        ->and($config->managed_credits_microcents)->toBe(1_200_000_000)
        ->and($config->managed_credits_used_microcents)->toBe(0);

    // 2. One-time payment.succeeded event for addon=ai does NOT overwrite team plan to pro_ltd
    $payReq = aiSignedDodoWebhookRequest([
        'timestamp' => now()->addMinute()->toIso8601String(),
        'type' => 'payment.succeeded',
        'data' => [
            'payment_id' => 'pay_ai_1',
            'product_id' => 'pdt_ai_addon_monthly',
            'customer' => ['customer_id' => 'cus_main_123'],
            'metadata' => ['team_id' => (string) $team->id, 'addon' => 'ai'],
        ],
    ], 'msg_ai_pay', $secret);

    $this->call('POST', route('api.billing.dodo.webhook'), [], [], [], $payReq['headers'], $payReq['raw'])
        ->assertOk();

    expect($team->fresh()->plan)->toBe('cloud');

    // 3. Simulate consumption + renewal resets monthly counter
    $config->update(['managed_credits_used_microcents' => 450_000_000]);

    $renewReq = aiSignedDodoWebhookRequest([
        'timestamp' => now()->addMinutes(5)->toIso8601String(),
        'type' => 'subscription.renewed',
        'data' => [
            'subscription_id' => 'sub_ai_addon_777',
            'product_id' => 'pdt_ai_addon_monthly',
            'status' => 'active',
            'next_billing_date' => now()->addMonths(2)->toIso8601String(),
            'customer' => ['customer_id' => 'cus_main_123'],
        ],
    ], 'msg_ai_renew', $secret);

    $this->call('POST', route('api.billing.dodo.webhook'), [], [], [], $renewReq['headers'], $renewReq['raw'])
        ->assertOk();

    expect($config->fresh()->managed_credits_used_microcents)->toBe(0);

    // 4. AI add-on expiration clears managed AI credits while leaving main Cloud subscription active
    $expiredReq = aiSignedDodoWebhookRequest([
        'timestamp' => now()->addMinutes(10)->toIso8601String(),
        'type' => 'subscription.expired',
        'data' => [
            'subscription_id' => 'sub_ai_addon_777',
            'status' => 'expired',
            'customer' => ['customer_id' => 'cus_main_123'],
        ],
    ], 'msg_ai_expired', $secret);

    $this->call('POST', route('api.billing.dodo.webhook'), [], [], [], $expiredReq['headers'], $expiredReq['raw'])
        ->assertOk();

    $team->refresh();
    $config->refresh();

    expect($team->plan)->toBe('cloud')
        ->and($team->billing_subscription_id)->toBe('sub_main_cloud_999')
        ->and($team->billing_status)->toBe('active')
        ->and($config->ai_billing_status)->toBe('expired')
        ->and($config->managed_credits_microcents)->toBe(0)
        ->and($config->hasActiveManagedSubscription())->toBeFalse();
});

/**
 * @param  array<string, mixed>  $payload
 * @return array{raw: string, headers: array<string, string>}
 */
function aiSignedDodoWebhookRequest(array $payload, string $webhookId, string $secret): array
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
