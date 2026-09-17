<?php

namespace App\Models;

use Database\Factories\TeamNotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property string $type
 * @property string $title
 * @property string $message
 * @property string|null $action_url
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $user
 */
class TeamNotification extends Model
{
    /** @use HasFactory<TeamNotificationFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'user_id',
        'type',
        'title',
        'message',
        'action_url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<TeamNotification>  $query
     * @return Builder<TeamNotification>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * @param  Builder<TeamNotification>  $query
     * @return Builder<TeamNotification>
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function isRead(): bool
    {
        return ! is_null($this->read_at);
    }

    public function getBodyAttribute(): string
    {
        return (string) $this->message;
    }

    public function getLevelAttribute(): string
    {
        return match ($this->type) {
            'security', 'security_dlp' => 'danger',
            'sync', 'conflict' => 'warning',
            'device', 'device_paired' => 'success',
            default => 'info',
        };
    }

    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }
}
