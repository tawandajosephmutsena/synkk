<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $vault_id
 * @property int $user_id
 * @property string $title
 * @property bool $is_shared_with_team
 * @property int|null $saved_vault_file_id
 * @property Carbon|null $last_message_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Vault $vault
 * @property-read User $user
 * @property-read VaultFile|null $savedFile
 * @property-read Collection<int, AiMessage> $messages
 * @property-read Collection<int, AiRun> $runs
 */
#[Fillable([
    'team_id',
    'vault_id',
    'user_id',
    'title',
    'is_shared_with_team',
    'saved_vault_file_id',
    'last_message_at',
])]
class AiThread extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_shared_with_team' => 'boolean',
            'last_message_at' => 'datetime',
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

    /**
     * @return BelongsTo<VaultFile, $this>
     */
    public function savedFile(): BelongsTo
    {
        return $this->belongsTo(VaultFile::class, 'saved_vault_file_id');
    }

    /**
     * @return HasMany<AiMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'thread_id');
    }

    /**
     * @return HasMany<AiRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AiRun::class, 'thread_id');
    }
}
