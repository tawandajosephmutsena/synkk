<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property int $team_id
 * @property string $name
 * @property string $token_hash
 * @property string $token_preview
 * @property Carbon|null $last_used_at
 * @property string|null $last_ip
 * @property string|null $client_platform
 * @property string $access_scope
 * @property array<string>|null $allowed_ip_subnets
 * @property array<int>|null $allowed_vault_ids
 * @property bool $is_wiped
 * @property Carbon|null $wiped_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Team|null $team
 */
#[Fillable(['user_id', 'team_id', 'name', 'token_hash', 'token_preview', 'last_used_at', 'last_ip', 'client_platform', 'access_scope', 'allowed_ip_subnets', 'allowed_vault_ids', 'is_wiped', 'wiped_at'])]
class DeviceToken extends Model
{
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'wiped_at' => 'datetime',
            'is_wiped' => 'boolean',
            'allowed_ip_subnets' => 'array',
            'allowed_vault_ids' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function triggerRemoteWipe(): void
    {
        $this->update([
            'is_wiped' => true,
            'wiped_at' => now(),
        ]);
    }

    public function isIpAllowed(?string $clientIp): bool
    {
        if (empty($this->allowed_ip_subnets) || empty($clientIp)) {
            return true;
        }

        foreach ($this->allowed_ip_subnets as $allowed) {
            if ($clientIp === $allowed || str_starts_with($clientIp, rtrim($allowed, '*'))) {
                return true;
            }
        }

        return false;
    }

    public function canAccessVault(int $vaultId): bool
    {
        if (empty($this->allowed_vault_ids)) {
            return true;
        }

        return in_array($vaultId, $this->allowed_vault_ids, true);
    }

    /**
     * Generate a new device token.
     *
     * @return array{plain_token: string, device_token: self}
     */
    public static function createToken(
        User $user,
        Team $team,
        string $name,
        ?string $platform = null,
        string $accessScope = 'full_access',
        ?array $allowedIpSubnets = null
    ): array {
        $plainText = 'synkk_'.Str::random(40);
        $hash = hash('sha256', $plainText);
        $preview = substr($plainText, 0, 12).'...';

        $model = static::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'name' => $name,
            'token_hash' => $hash,
            'token_preview' => $preview,
            'client_platform' => $platform,
            'access_scope' => $accessScope,
            'allowed_ip_subnets' => $allowedIpSubnets,
        ]);

        return [
            'plain_token' => $plainText,
            'device_token' => $model,
        ];
    }

    /**
     * Find a device token by its plain text value.
     */
    public static function findToken(string $plainText): ?self
    {
        $hash = hash('sha256', $plainText);

        return static::where('token_hash', $hash)->first();
    }
}
