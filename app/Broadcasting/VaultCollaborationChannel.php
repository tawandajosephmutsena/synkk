<?php

namespace App\Broadcasting;

use App\Models\DeviceToken;
use App\Models\User;
use App\Models\VaultCollaborationDocument;
use App\Services\DeviceVaultAccess;

class VaultCollaborationChannel
{
    public function __construct(
        protected DeviceVaultAccess $vaultAccess
    ) {}

    /**
     * Authenticate the user's access to the collaboration channel.
     *
     * @return array<string, mixed>|bool
     */
    public function join(User $user, int|string $documentId): array|bool
    {
        $document = VaultCollaborationDocument::with('vault.team')->find((int) $documentId);
        if (! $document) {
            return false;
        }

        $vault = $document->vault;

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
                $this->vaultAccess->authorizeRead($deviceToken, $vault, $document->path);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'device_token_id' => $deviceToken->id,
                ];
            } catch (\Throwable) {
                return false;
            }
        }

        // Web user session access
        $isMember = ($vault->team_id === $user->current_team_id)
            || $vault->team->members()->where('user_id', $user->id)->exists();

        if (! $isMember) {
            return false;
        }

        if ($vault->team->isSuspended()) {
            return false;
        }

        $permission = $vault->permissionForPath($user, $document->path);
        if ($permission === 'hidden' || ($permission !== 'read_write' && $permission !== 'read_only')) {
            return false;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }
}
