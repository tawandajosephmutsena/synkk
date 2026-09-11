<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $webhook_id
 * @property string $event_type
 * @property string $payload_hash
 * @property Carbon|null $event_at
 * @property Carbon|null $processed_at
 */
#[Fillable([
    'webhook_id',
    'event_type',
    'payload_hash',
    'event_at',
    'processed_at',
])]
class DodoWebhookEvent extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
