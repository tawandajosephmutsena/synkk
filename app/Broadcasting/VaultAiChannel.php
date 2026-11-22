<?php

namespace App\Broadcasting;

use App\Models\DeviceToken;
use App\Models\User;
use App\Models\Vault;
use App\Services\DeviceVaultAccess;

class VaultAiChannel
{
    public function __construct(
        protected DeviceVaultAccess $vaultAccess,
    ) {}

    /**
     * Authorize subscription to a user's private Vault AI stream channel.
     */
    public function join(User $user, int|string $vaultId, int|string $userId): bool
    {
        if ((int) $user->id !== (int) $userId) {
            return false;
        }

        $vault = Vault::with('team')->find((int) $vaultId);
        if (! $vault || $vault->team->isSuspended()) {
            return false;
        }

        $request = request();
        /** @var DeviceToken|null $deviceToken */
        $deviceToken = $request->attributes->get('device_token');

        if (! $deviceToken && $request->bearerToken()) {
            $deviceToken = DeviceToken::with(['user', 'team'])
                ->where('token_hash', hash('sha256', $request->bearerToken()))
                ->first();
        }

        if ($deviceToken) {
            try {
                $this->vaultAccess->authorizeVaultAccess($deviceToken, $vault);

                return (int) $deviceToken->user_id === (int) $user->id;
            } catch (\Throwable) {
                return false;
            }
        }

        return $vault->team_id === $user->current_team_id
            || $vault->team->members()->where('user_id', $user->id)->exists();
    }
}
