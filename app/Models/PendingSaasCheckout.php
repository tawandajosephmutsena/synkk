<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $token_hash
 * @property string $name
 * @property string $workspace_name
 * @property string $email
 * @property string $plan
 * @property string $status
 * @property string|null $checkout_session_id
 * @property string|null $billing_customer_id
 * @property string|null $billing_subscription_id
 * @property string|null $billing_product_id
 * @property string|null $billing_status
 * @property Carbon|null $billing_next_billing_at
 * @property Carbon|null $billing_last_event_at
 * @property int|null $user_id
 * @property int|null $team_id
 * @property Carbon|null $paid_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $consumed_at
 */
#[Fillable([
    'token_hash',
    'name',
    'workspace_name',
    'email',
    'plan',
    'status',
    'checkout_session_id',
    'billing_customer_id',
    'billing_subscription_id',
    'billing_product_id',
    'billing_status',
    'billing_next_billing_at',
    'billing_last_event_at',
    'user_id',
    'team_id',
    'paid_at',
    'expires_at',
    'consumed_at',
])]
class PendingSaasCheckout extends Model
{
    public static function findByToken(string $token): ?self
    {
        return static::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    public function isReadyToComplete(): bool
    {
        return $this->status === 'active'
            && filled($this->billing_subscription_id)
            && $this->consumed_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_next_billing_at' => 'datetime',
            'billing_last_event_at' => 'datetime',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
