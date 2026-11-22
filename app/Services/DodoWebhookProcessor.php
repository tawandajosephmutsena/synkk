<?php

namespace App\Services;

use App\Models\DodoWebhookEvent;
use App\Models\Team;
use App\Models\TeamAiConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use JsonException;
use UnexpectedValueException;

class DodoWebhookProcessor
{
    /**
     * Process one signed Dodo webhook exactly once.
     *
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws UnexpectedValueException
     */
    public function process(
        string $rawBody,
        string $webhookId,
        string $webhookSignature,
        string $webhookTimestamp,
    ): void {
        if (blank($webhookId) || blank($webhookSignature) || blank($webhookTimestamp)) {
            throw new InvalidArgumentException('Dodo webhook headers are incomplete.');
        }

        if (! $this->hasValidSignature($rawBody, $webhookId, $webhookSignature, $webhookTimestamp)) {
            throw new InvalidArgumentException('Dodo webhook signature is invalid.');
        }

        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload) || ! is_string($payload['type'] ?? null) || ! is_array($payload['data'] ?? null)) {
            throw new UnexpectedValueException('Dodo webhook payload is invalid.');
        }

        /** @var array{type: string, timestamp?: string, data: array<string, mixed>} $payload */
        $eventType = $payload['type'];
        $eventAt = $this->parseDate($payload['timestamp'] ?? null) ?? CarbonImmutable::now();

        DB::transaction(function () use ($eventType, $eventAt, $payload, $rawBody, $webhookId): void {
            $event = DodoWebhookEvent::query()->firstOrCreate(
                ['webhook_id' => $webhookId],
                [
                    'event_type' => $eventType,
                    'payload_hash' => hash('sha256', $rawBody),
                    'event_at' => $eventAt,
                ],
            );

            if ($event->processed_at !== null) {
                return;
            }

            $this->applySubscriptionEvent($eventType, $payload['data'], $eventAt);
            $this->applyPaymentEvent($eventType, $payload['data'], $eventAt);

            $event->forceFill(['processed_at' => CarbonImmutable::now()])->save();
        });
    }

    /**
     * Verify the Standard Webhooks signature used by Dodo Payments.
     */
    protected function hasValidSignature(
        string $rawBody,
        string $webhookId,
        string $webhookSignature,
        string $webhookTimestamp,
    ): bool {
        if (! ctype_digit($webhookTimestamp)) {
            return false;
        }

        $tolerance = (int) config('services.dodo.webhook_tolerance_seconds', 300);
        if (abs(CarbonImmutable::now()->getTimestamp() - (int) $webhookTimestamp) > $tolerance) {
            return false;
        }

        $configuredSecret = (string) config('services.dodo.webhook_key');
        if (blank($configuredSecret)) {
            return false;
        }

        $secret = str_starts_with($configuredSecret, 'whsec_')
            ? base64_decode(substr($configuredSecret, 6), true)
            : $configuredSecret;

        if ($secret === false || $secret === '') {
            return false;
        }

        $signedPayload = $webhookId.'.'.$webhookTimestamp.'.'.$rawBody;
        $expectedSignature = base64_encode(hash_hmac('sha256', $signedPayload, $secret, true));

        foreach (preg_split('/\s+/', trim($webhookSignature)) ?: [] as $versionedSignature) {
            [$version, $signature] = array_pad(explode(',', $versionedSignature, 2), 2, null);

            if ($version === 'v1' && is_string($signature) && hash_equals($expectedSignature, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply an event to the linked Synkk workspace.
     *
     * @param  array<string, mixed>  $data
     */
    protected function applySubscriptionEvent(string $eventType, array $data, CarbonImmutable $eventAt): void
    {
        if (! str_starts_with($eventType, 'subscription.')) {
            return;
        }

        $subscriptionId = $data['subscription_id'] ?? null;
        if (! is_string($subscriptionId) || blank($subscriptionId)) {
            return;
        }

        if ($this->isAiAddonSubscriptionEvent($data, $subscriptionId)) {
            $this->applyAiAddonSubscriptionEvent($eventType, $data, $subscriptionId, $eventAt);

            return;
        }

        $team = $this->resolveTeam($data, $subscriptionId);
        if (! $team) {
            Log::warning('Ignoring Dodo subscription webhook without a linked Synkk team.', [
                'event_type' => $eventType,
                'subscription_id' => $subscriptionId,
            ]);

            return;
        }

        $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $customerId = $customer['customer_id'] ?? null;

        if ($team->billing_subscription_id !== null && $team->billing_subscription_id !== $subscriptionId) {
            Log::warning('Ignoring Dodo subscription webhook for a different subscription.', [
                'event_type' => $eventType,
                'team_id' => $team->id,
                'subscription_id' => $subscriptionId,
            ]);

            return;
        }

        if ($team->billing_customer_id !== null
            && is_string($customerId)
            && $team->billing_customer_id !== $customerId) {
            Log::warning('Ignoring Dodo subscription webhook for a different customer.', [
                'event_type' => $eventType,
                'team_id' => $team->id,
                'customer_id' => $customerId,
            ]);

            return;
        }

        if ($team->billing_last_event_at !== null && $eventAt->lessThanOrEqualTo($team->billing_last_event_at)) {
            return;
        }

        $status = is_string($data['status'] ?? null)
            ? $data['status']
            : $this->statusForEvent($eventType);
        $nextBillingAt = $this->parseDate($data['next_billing_date'] ?? null);
        $cancelledAt = $this->parseDate($data['cancelled_at'] ?? null);
        $keepCloud = ! in_array($status, ['failed', 'expired'], true)
            && ! in_array($eventType, ['subscription.failed', 'subscription.expired'], true);

        if ($eventType === 'subscription.cancelled'
            && ($nextBillingAt === null || $nextBillingAt->lessThanOrEqualTo(CarbonImmutable::now()))) {
            $keepCloud = false;
        }

        $accessUntil = match (true) {
            $status === 'past_due' => $this->parseDate($data['past_due_ends_at'] ?? null)
                ?? CarbonImmutable::now()->addDays((int) config('services.dodo.grace_period_days', 7)),
            $status === 'on_hold' => CarbonImmutable::now()->addDays((int) config('services.dodo.grace_period_days', 7)),
            $eventType === 'subscription.cancelled' && $keepCloud => $nextBillingAt,
            $status === 'paused' => $nextBillingAt,
            default => null,
        };

        $updates = [
            'billing_provider' => 'dodo',
            'billing_customer_id' => is_string($customerId) ? $customerId : $team->billing_customer_id,
            'billing_subscription_id' => $subscriptionId,
            'billing_product_id' => is_string($data['product_id'] ?? null) ? $data['product_id'] : $team->billing_product_id,
            'billing_status' => $status,
            'billing_next_billing_at' => $nextBillingAt,
            'billing_access_until' => $accessUntil,
            'billing_cancelled_at' => $cancelledAt,
            'billing_last_event_at' => $eventAt,
        ];

        if ($keepCloud) {
            $updates['plan'] = $this->resolveSubscriptionPlan($data);
            $updates['license_status'] = 'active';
            $updates['license_activated_at'] = $team->license_activated_at ?? CarbonImmutable::now();
            $updates['billing_previous_plan'] = $team->billing_previous_plan
                ?? (! $team->isCloudPlan() ? $team->plan : null);
        } else {
            $restoredPlan = $this->planToRestore($team);
            $updates['plan'] = $restoredPlan;
            $updates['license_status'] = $restoredPlan === 'free' ? 'expired' : 'active';
            $updates['billing_previous_plan'] = null;
            $updates['billing_access_until'] = null;
        }

        $team->forceFill($updates)->save();
    }

    /**
     * Apply a one-time payment event (e.g. Pro Lifetime Deal) to the linked Synkk workspace.
     *
     * @param  array<string, mixed>  $data
     */
    protected function applyPaymentEvent(string $eventType, array $data, CarbonImmutable $eventAt): void
    {
        if ($eventType !== 'payment.succeeded') {
            return;
        }

        // Subscription renewals/invoices are governed by subscription.* webhooks.
        if (filled($data['subscription_id'] ?? null)) {
            return;
        }

        $paymentId = $data['payment_id'] ?? null;
        if (! is_string($paymentId) || blank($paymentId)) {
            return;
        }

        $metadata = $this->mergedMetadata($data);
        $aiProductId = config('services.dodo.ai_addon_product_id');

        if (($metadata['addon'] ?? null) === 'ai'
            || (is_string($aiProductId) && filled($aiProductId) && ($data['product_id'] ?? null) === $aiProductId)) {
            return;
        }

        $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];

        $teamId = $metadata['team_id'] ?? null;
        $plan = $metadata['plan'] ?? 'pro_ltd';

        $team = null;
        if (is_numeric($teamId)) {
            $team = Team::find((int) $teamId);
        }

        if (! $team) {
            $customerId = $customer['customer_id'] ?? null;
            if (is_string($customerId) && filled($customerId)) {
                $team = Team::where('billing_customer_id', $customerId)->first();
            }
        }

        if (! $team) {
            Log::warning('Ignoring Dodo payment webhook without a linked Synkk team.', [
                'event_type' => $eventType,
                'payment_id' => $paymentId,
            ]);

            return;
        }

        if ($team->billing_last_event_at !== null && $eventAt->lessThanOrEqualTo($team->billing_last_event_at)) {
            return;
        }

        $productId = $data['product_id'] ?? null;
        $proProductId = config('services.dodo.pro_product_id');

        if (is_string($productId) && filled($productId) && is_string($proProductId) && filled($proProductId) && $productId === $proProductId) {
            $targetPlan = 'pro_ltd';
        } else {
            $targetPlan = in_array($plan, ['pro_ltd', 'cloud', 'cloud_pro', 'cloud_business'], true) ? $plan : 'pro_ltd';
        }

        $updates = [
            'billing_provider' => 'dodo',
            'billing_customer_id' => is_string($customer['customer_id'] ?? null) ? $customer['customer_id'] : $team->billing_customer_id,
            'billing_status' => 'succeeded',
            'billing_last_event_at' => $eventAt,
            'plan' => $targetPlan,
            'license_status' => 'active',
            'license_activated_at' => $team->license_activated_at ?? $eventAt,
        ];

        if ($targetPlan === 'pro_ltd' && blank($team->license_key)) {
            $hash = strtoupper(hash('sha256', 'dodo:'.$paymentId.':'.$team->id));
            $updates['license_key'] = sprintf(
                'SYNK-PRO-%s-%s-%s',
                substr($hash, 0, 4),
                substr($hash, 4, 4),
                substr($hash, 8, 4),
            );
        }

        $team->forceFill($updates)->save();
    }

    /**
     * Resolve the Cloud subscription tier from Dodo product ID or checkout metadata.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveSubscriptionPlan(array $data): string
    {
        $productId = $data['product_id'] ?? null;
        $businessProductId = config('services.dodo.cloud_business_product_id');

        if (is_string($productId) && filled($productId) && is_string($businessProductId) && filled($businessProductId) && $productId === $businessProductId) {
            return 'cloud_business';
        }

        $metadata = $this->mergedMetadata($data);
        $metadataPlan = $metadata['plan'] ?? null;

        if (is_string($metadataPlan) && in_array($metadataPlan, ['cloud', 'cloud_pro', 'cloud_business'], true)) {
            return $metadataPlan;
        }

        return 'cloud';
    }

    /**
     * Merge Dodo customer metadata with event metadata (event metadata wins).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mergedMetadata(array $data): array
    {
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $customerMetadata = is_array($customer['metadata'] ?? null) ? $customer['metadata'] : [];

        return array_replace($customerMetadata, $metadata);
    }

    /**
     * Resolve the team using server-created metadata, then existing billing IDs.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveTeam(array $data, string $subscriptionId): ?Team
    {
        $metadata = $this->mergedMetadata($data);
        $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $teamId = $metadata['team_id'] ?? null;

        if ((is_int($teamId) || is_string($teamId) && ctype_digit($teamId)) && (int) $teamId > 0) {
            $team = Team::query()->find((int) $teamId);
            if ($team) {
                return $team;
            }
        }

        $team = Team::query()->where('billing_subscription_id', $subscriptionId)->first();
        if ($team) {
            return $team;
        }

        $customerId = $customer['customer_id'] ?? null;
        if (is_string($customerId) && filled($customerId)) {
            return Team::query()->where('billing_customer_id', $customerId)->first();
        }

        return null;
    }

    /**
     * Determine whether a subscription webhook belongs to the Managed AI Add-On rather than the core Cloud plan.
     *
     * @param  array<string, mixed>  $data
     */
    protected function isAiAddonSubscriptionEvent(array $data, string $subscriptionId): bool
    {
        $metadata = $this->mergedMetadata($data);
        if (($metadata['addon'] ?? null) === 'ai') {
            return true;
        }

        $aiProductId = config('services.dodo.ai_addon_product_id');
        if (is_string($aiProductId) && filled($aiProductId) && ($data['product_id'] ?? null) === $aiProductId) {
            return true;
        }

        return TeamAiConfig::query()->where('ai_billing_subscription_id', $subscriptionId)->exists();
    }

    /**
     * Apply a Managed AI Add-On subscription event solely to TeamAiConfig without altering core team plan fields.
     *
     * @param  array<string, mixed>  $data
     */
    protected function applyAiAddonSubscriptionEvent(
        string $eventType,
        array $data,
        string $subscriptionId,
        CarbonImmutable $eventAt,
    ): void {
        $team = $this->resolveAiAddonTeam($data, $subscriptionId);
        if (! $team) {
            Log::warning('Ignoring Dodo AI add-on webhook without a linked Synkk team.', [
                'event_type' => $eventType,
                'subscription_id' => $subscriptionId,
            ]);

            return;
        }

        $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $customerId = $customer['customer_id'] ?? null;

        if ($team->billing_customer_id !== null
            && is_string($customerId)
            && $team->billing_customer_id !== $customerId) {
            Log::warning('Ignoring Dodo AI add-on webhook for a different customer.', [
                'event_type' => $eventType,
                'team_id' => $team->id,
                'customer_id' => $customerId,
            ]);

            return;
        }

        $config = TeamAiConfig::query()->firstOrCreate(['team_id' => $team->id]);

        if ($config->ai_billing_subscription_id !== null
            && $config->ai_billing_subscription_id !== $subscriptionId
            && $config->hasActiveManagedSubscription()) {
            Log::warning('Ignoring Dodo AI add-on webhook for a different active subscription.', [
                'event_type' => $eventType,
                'team_id' => $team->id,
                'subscription_id' => $subscriptionId,
            ]);

            return;
        }

        if ($config->ai_billing_last_event_at !== null && $eventAt->lessThanOrEqualTo($config->ai_billing_last_event_at)) {
            return;
        }

        $status = is_string($data['status'] ?? null)
            ? $data['status']
            : $this->statusForEvent($eventType);
        $nextBillingAt = $this->parseDate($data['next_billing_date'] ?? null);
        $cancelledAt = $this->parseDate($data['cancelled_at'] ?? null);
        $keepAccess = ! in_array($status, ['failed', 'expired'], true)
            && ! in_array($eventType, ['subscription.failed', 'subscription.expired'], true);

        if ($eventType === 'subscription.cancelled'
            && ($nextBillingAt === null || $nextBillingAt->lessThanOrEqualTo(CarbonImmutable::now()))) {
            $keepAccess = false;
        }

        $accessUntil = match (true) {
            $status === 'past_due' => $this->parseDate($data['past_due_ends_at'] ?? null)
                ?? CarbonImmutable::now()->addDays((int) config('services.dodo.grace_period_days', 7)),
            $status === 'on_hold' => CarbonImmutable::now()->addDays((int) config('services.dodo.grace_period_days', 7)),
            $eventType === 'subscription.cancelled' && $keepAccess => $nextBillingAt,
            $status === 'paused' => $nextBillingAt,
            default => null,
        };

        $updates = [
            'ai_billing_subscription_id' => $subscriptionId,
            'ai_billing_product_id' => is_string($data['product_id'] ?? null) ? $data['product_id'] : $config->ai_billing_product_id,
            'ai_billing_status' => $status,
            'ai_billing_access_until' => $accessUntil,
            'ai_billing_cancelled_at' => $cancelledAt,
            'ai_billing_last_event_at' => $eventAt,
        ];

        if ($keepAccess) {
            $updates['is_enabled'] = true;
            $updates['provider_mode'] = 'managed';
            $updates['managed_cycle_resets_at'] = $nextBillingAt;

            if (in_array($eventType, ['subscription.active', 'subscription.renewed'], true) || $config->managed_credits_microcents === 0) {
                $updates['managed_credits_microcents'] = (int) config('synkk.ai.credits.monthly_microcents', 1_200_000_000);
                $updates['managed_credits_used_microcents'] = 0;
            }
        } else {
            $updates['managed_credits_microcents'] = 0;
            $updates['ai_billing_access_until'] = null;
        }

        $config->forceFill($updates)->save();

        if (blank($team->billing_customer_id) && is_string($customerId) && filled($customerId)) {
            $team->forceFill(['billing_customer_id' => $customerId])->save();
        }
    }

    /**
     * Resolve the team owning a Managed AI Add-On subscription webhook.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveAiAddonTeam(array $data, string $subscriptionId): ?Team
    {
        $metadata = $this->mergedMetadata($data);
        $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $teamId = $metadata['team_id'] ?? null;

        if ((is_int($teamId) || is_string($teamId) && ctype_digit($teamId)) && (int) $teamId > 0) {
            $team = Team::query()->find((int) $teamId);
            if ($team) {
                return $team;
            }
        }

        $config = TeamAiConfig::query()->where('ai_billing_subscription_id', $subscriptionId)->first();
        if ($config) {
            return $config->team;
        }

        $customerId = $customer['customer_id'] ?? null;
        if (is_string($customerId) && filled($customerId)) {
            return Team::query()->where('billing_customer_id', $customerId)->first();
        }

        return null;
    }

    /**
     * Restore a previous non-subscription entitlement when a subscription ends.
     */
    protected function planToRestore(Team $team): string
    {
        if (in_array($team->billing_previous_plan, ['free', 'pro_ltd', 'cloud', 'cloud_pro', 'cloud_business'], true)) {
            return $team->billing_previous_plan;
        }

        $licenseKey = strtoupper((string) $team->license_key);
        if (str_starts_with($licenseKey, 'SYNK-CLOUD-')) {
            return 'cloud';
        }

        if ($team->license_status === 'active' && filled($team->license_key)) {
            return 'pro_ltd';
        }

        return 'free';
    }

    /**
     * Map an event to a status when Dodo omits the subscription status field.
     */
    protected function statusForEvent(string $eventType): string
    {
        return match ($eventType) {
            'subscription.failed' => 'failed',
            'subscription.expired' => 'expired',
            'subscription.cancelled' => 'cancelled',
            'subscription.on_hold' => 'on_hold',
            'subscription.past_due' => 'past_due',
            'subscription.paused' => 'paused',
            default => 'active',
        };
    }

    /**
     * Parse provider timestamps without allowing malformed data to abort retries.
     */
    protected function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
