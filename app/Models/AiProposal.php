<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $vault_id
 * @property int|null $vault_file_id
 * @property int|null $created_by_user_id
 * @property int|null $reviewed_by_user_id
 * @property string $source
 * @property string $action
 * @property string $target_path
 * @property string|null $destination_path
 * @property int|null $base_version
 * @property string|null $base_content_hash
 * @property string $proposed_content
 * @property string|null $rationale
 * @property array<string, mixed>|null $metadata
 * @property string $status
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Vault $vault
 * @property-read VaultFile|null $file
 * @property-read User|null $creator
 * @property-read User|null $reviewer
 */
#[Fillable([
    'team_id',
    'vault_id',
    'vault_file_id',
    'created_by_user_id',
    'reviewed_by_user_id',
    'source',
    'action',
    'target_path',
    'destination_path',
    'base_version',
    'base_content_hash',
    'proposed_content',
    'rationale',
    'metadata',
    'status',
    'reviewed_at',
])]
class AiProposal extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_version' => 'integer',
            'metadata' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<AiProposal>  $query
     * @return Builder<AiProposal>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
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
     * @return BelongsTo<VaultFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(VaultFile::class, 'vault_file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
