<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property bool $is_enabled
 * @property string $provider_mode
 * @property bool $fallback_to_byok
 * @property string|null $chat_provider
 * @property string|null $chat_base_url
 * @property string|null $chat_model
 * @property string|null $fast_model
 * @property string|null $chat_api_key
 * @property string|null $embedding_provider
 * @property string|null $embedding_base_url
 * @property string|null $embedding_model
 * @property string|null $embedding_api_key
 * @property bool $dlp_redaction_enabled
 * @property int|null $monthly_budget_cents
 * @property int $trial_credits_microcents
 * @property Carbon|null $trial_granted_at
 * @property int $managed_credits_microcents
 * @property int $managed_credits_used_microcents
 * @property Carbon|null $managed_cycle_resets_at
 * @property string|null $ai_billing_subscription_id
 * @property string|null $ai_billing_product_id
 * @property string|null $ai_billing_status
 * @property Carbon|null $ai_billing_access_until
 * @property Carbon|null $ai_billing_cancelled_at
 * @property Carbon|null $ai_billing_last_event_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable([
    'team_id',
    'is_enabled',
    'provider_mode',
    'fallback_to_byok',
    'chat_provider',
    'chat_base_url',
    'chat_model',
    'fast_model',
    'chat_api_key',
    'embedding_provider',
    'embedding_base_url',
    'embedding_model',
    'embedding_api_key',
    'dlp_redaction_enabled',
    'monthly_budget_cents',
    'trial_credits_microcents',
    'trial_granted_at',
    'managed_credits_microcents',
    'managed_credits_used_microcents',
    'managed_cycle_resets_at',
    'ai_billing_subscription_id',
    'ai_billing_product_id',
    'ai_billing_status',
    'ai_billing_access_until',
    'ai_billing_cancelled_at',
    'ai_billing_last_event_at',
])]
class TeamAiConfig extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'fallback_to_byok' => 'boolean',
            'chat_api_key' => 'encrypted',
            'embedding_api_key' => 'encrypted',
            'dlp_redaction_enabled' => 'boolean',
            'monthly_budget_cents' => 'integer',
            'trial_credits_microcents' => 'integer',
            'trial_granted_at' => 'datetime',
            'managed_credits_microcents' => 'integer',
            'managed_credits_used_microcents' => 'integer',
            'managed_cycle_resets_at' => 'datetime',
            'ai_billing_access_until' => 'datetime',
            'ai_billing_cancelled_at' => 'datetime',
            'ai_billing_last_event_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function hasActiveManagedSubscription(): bool
    {
        if (blank($this->ai_billing_subscription_id)) {
            return false;
        }

        if ($this->ai_billing_access_until?->isPast()) {
            return false;
        }

        if (in_array($this->ai_billing_status, ['cancelled', 'paused'], true) && $this->ai_billing_access_until === null) {
            return false;
        }

        return in_array($this->ai_billing_status, [
            'pending',
            'active',
            'past_due',
            'on_hold',
            'paused',
            'cancelled',
        ], true);
    }

    public function remainingManagedCreditsMicrocents(): int
    {
        return max(0, (int) $this->managed_credits_microcents - (int) $this->managed_credits_used_microcents);
    }

    public function totalAvailableManagedCreditsMicrocents(): int
    {
        return $this->remainingManagedCreditsMicrocents() + max(0, (int) $this->trial_credits_microcents);
    }

    public function hasByokConfigured(): bool
    {
        if (blank($this->chat_provider) || $this->chat_provider === 'ollama' || blank($this->chat_model)) {
            return false;
        }

        if ($this->chat_provider === 'openai_compatible' && filled($this->chat_base_url)) {
            return true;
        }

        return filled($this->chat_api_key);
    }

    public function hasOllamaConfigured(): bool
    {
        return filled($this->chat_model);
    }
}
