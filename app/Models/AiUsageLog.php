<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $vault_id
 * @property int|null $user_id
 * @property int|null $portal_id
 * @property string $feature
 * @property string $provider_mode
 * @property string $provider
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cost_microcents
 * @property int $redacted_secrets_count
 * @property Carbon|null $created_at
 * @property-read Team $team
 * @property-read Vault|null $vault
 * @property-read User|null $user
 */
#[Fillable([
    'team_id',
    'vault_id',
    'user_id',
    'portal_id',
    'feature',
    'provider_mode',
    'provider',
    'model',
    'input_tokens',
    'output_tokens',
    'cost_microcents',
    'redacted_secrets_count',
    'created_at',
])]
class AiUsageLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'portal_id' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_microcents' => 'integer',
            'redacted_secrets_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Vault, $this>
     */
    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
