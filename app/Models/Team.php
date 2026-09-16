<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueTeamSlugs;
use App\Enums\TeamRole;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_personal
 * @property string|null $license_key
 * @property string $license_status
 * @property string $plan
 * @property string $status
 * @property string|null $billing_provider
 * @property string|null $billing_customer_id
 * @property string|null $billing_subscription_id
 * @property string|null $billing_product_id
 * @property string|null $billing_status
 * @property string|null $billing_previous_plan
 * @property Carbon|null $billing_next_billing_at
 * @property Carbon|null $billing_access_until
 * @property Carbon|null $billing_cancelled_at
 * @property Carbon|null $billing_last_event_at
 * @property int|null $storage_limit_mb
 * @property int|null $max_devices
 * @property int|null $max_vaults
 * @property int|null $max_members
 * @property Carbon|null $license_activated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, TeamInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 */
#[Fillable([
    'name',
    'slug',
    'is_personal',
    'license_key',
    'license_status',
    'license_activated_at',
    'plan',
    'status',
    'billing_provider',
    'billing_customer_id',
    'billing_subscription_id',
    'billing_product_id',
    'billing_status',
    'billing_previous_plan',
    'billing_next_billing_at',
    'billing_access_until',
    'billing_cancelled_at',
    'billing_last_event_at',
    'storage_limit_mb',
    'max_devices',
    'max_vaults',
    'max_members',
])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, SoftDeletes;

    public function isSuspended(): bool
    {
        return $this->status === 'suspended'
            || ($this->plan === 'cloud' && $this->billing_access_until?->isPast());
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function planName(): string
    {
        return match ($this->plan) {
            'pro_ltd' => 'Pro Lifetime',
            'cloud' => 'Synkk Cloud',
            default => 'Community Free',
        };
    }

    public function hasActiveLicense(): bool
    {
        if ($this->plan === 'free') {
            return true;
        }

        if (! config('synkk.lemon_squeezy.enforce_license', false)) {
            return true;
        }

        return $this->license_status === 'active';
    }

    /**
     * Determine whether the team has a current Dodo subscription or recovery window.
     */
    public function hasActiveDodoSubscription(): bool
    {
        if ($this->billing_provider !== 'dodo' || blank($this->billing_subscription_id)) {
            return false;
        }

        if ($this->billing_access_until?->isPast()) {
            return false;
        }

        return in_array($this->billing_status, [
            'pending',
            'active',
            'past_due',
            'on_hold',
            'paused',
            'cancelled',
        ], true);
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /**
     * Get the team owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this team.
     *
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * Get all vaults belonging to this team.
     *
     * @return HasMany<Vault, $this>
     */
    public function vaults(): HasMany
    {
        return $this->hasMany(Vault::class);
    }

    /**
     * Get all published portals belonging to this team.
     *
     * @return HasMany<VaultPortal, $this>
     */
    public function portals(): HasMany
    {
        return $this->hasMany(VaultPortal::class);
    }

    /**
     * Get all notifications for this team.
     *
     * @return HasMany<TeamNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(TeamNotification::class);
    }

    /**
     * Get all messages for this team.
     *
     * @return HasMany<TeamMessage, $this>
     */
    public function teamMessages(): HasMany
    {
        return $this->hasMany(TeamMessage::class);
    }

    /**
     * Get all device sync tokens for this team.
     *
     * @return HasMany<DeviceToken, $this>
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
            'storage_limit_mb' => 'integer',
            'max_devices' => 'integer',
            'max_vaults' => 'integer',
            'max_members' => 'integer',
            'license_activated_at' => 'datetime',
            'billing_next_billing_at' => 'datetime',
            'billing_access_until' => 'datetime',
            'billing_cancelled_at' => 'datetime',
            'billing_last_event_at' => 'datetime',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
