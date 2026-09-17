<?php

namespace App\Services;

use App\Models\DodoWebhookEvent;
use App\Models\Team;
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
            $updates['plan'] = 'cloud';
            $updates['license_status'] = 'active';
            $updates['license_activated_at'] = $team->license_activated_at ?? CarbonImmutable::now();
            $updates['billing_previous_plan'] = $team->billing_previous_plan
                ?? ($team->plan !== 'cloud' ? $team->plan : null);
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
     * Resolve the team using server-created metadata, then existing billing IDs.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveTeam(array $data, string $subscriptionId): ?Team
    {
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $customerMetadata = is_array($customer['metadata'] ?? null) ? $customer['metadata'] : [];
        $metadata = array_replace($customerMetadata, $metadata);
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
     * Restore a previous non-subscription entitlement when a subscription ends.
     */
    protected function planToRestore(Team $team): string
    {
        if (in_array($team->billing_previous_plan, ['free', 'pro_ltd', 'cloud'], true)) {
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
