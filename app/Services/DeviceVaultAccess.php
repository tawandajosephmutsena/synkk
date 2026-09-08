<?php

namespace App\Services;

use App\Enums\TeamRole;
use App\Models\DeviceToken;
use App\Models\Vault;

final class DeviceVaultAccess
{
    /**
     * Authorize that the device token is allowed access to the given vault.
     */
    public function authorizeVaultAccess(DeviceToken $token, Vault $vault): void
    {
        if ($vault->team_id !== $token->team_id || ! $token->canAccessVault($vault->id)) {
            abort(404, 'Vault not found in current team or access not allowed for this device token.');
        }

        if ($token->is_wiped) {
            abort(410, 'This device token has been remotely wiped.');
        }

        if ($token->team && $token->team->isSuspended()) {
            abort(403, 'This team workspace is currently suspended.');
        }
    }

    /**
     * Authorize read access to a specific path within a vault.
     */
    public function authorizeRead(DeviceToken $token, Vault $vault, string $path): void
    {
        $this->authorizeVaultAccess($token, $vault);

        $cleanPath = trim(str_replace('\\', '/', $path), '/');
        $permission = $vault->permissionForPath($token->user, $cleanPath);

        if ($permission === 'hidden') {
            abort(404, 'Resource not found.');
        }

        if ($permission !== 'read_write' && $permission !== 'read_only') {
            abort(403, "You do not have read permission for '{$cleanPath}'.");
        }
    }

    /**
     * Authorize write/mutation access to a specific path within a vault.
     */
    public function authorizeWrite(DeviceToken $token, Vault $vault, string $path): void
    {
        $this->authorizeVaultAccess($token, $vault);

        if ($token->access_scope === 'read_only') {
            abort(403, 'This device token has read-only access and cannot modify vault resources.');
        }

        $cleanPath = trim(str_replace('\\', '/', $path), '/');
        $permission = $vault->permissionForPath($token->user, $cleanPath);

        if ($permission === 'hidden') {
            abort(404, 'Resource not found.');
        }

        if ($permission !== 'read_write') {
            abort(403, "You do not have write permission for '{$cleanPath}'.");
        }
    }

    /**
     * Authorize administrative access to a vault (e.g. security config, RAG indexing).
     */
    public function authorizeAdmin(DeviceToken $token, Vault $vault): void
    {
        $this->authorizeVaultAccess($token, $vault);

        if ($token->access_scope === 'read_only') {
            abort(403, 'Read-only device tokens cannot perform administrative actions.');
        }

        $user = $token->user;
        $user->loadMissing('teamMemberships');
        $membership = $user->teamMemberships->firstWhere('team_id', $vault->team_id);
        $role = $membership?->role;

        $isAdmin = ($role === TeamRole::Owner || $role === TeamRole::Admin);

        if (! $isAdmin) {
            abort(403, 'Vault administrator authority required.');
        }
    }

    /**
     * Validate that a conflict path correctly corresponds to the canonical path.
     */
    public function validateConflictRelationship(string $canonicalPath, string $conflictPath): void
    {
        $canonical = trim(str_replace('\\', '/', $canonicalPath), '/');
        $conflict = trim(str_replace('\\', '/', $conflictPath), '/');

        $derived = preg_replace('/(\.conflict-[^.]+|\.sync-conflict-[^.]+)(\.[^.]+)$/', '$2', $conflict);
        if ($derived === $conflict) {
            $derived = preg_replace('/(\.conflict-[^.]+|\.sync-conflict-[^.]+)$/', '', $conflict);
        }

        if ($derived !== $canonical) {
            abort(403, 'The conflict file path does not correspond to the specified canonical path.');
        }
    }
}
