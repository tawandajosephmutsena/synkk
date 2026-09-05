<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\User;
use App\Models\Vault;

class VaultPolicy
{
    /**
     * Determine whether the user can view any vaults in the team.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the vault.
     */
    public function view(User $user, Vault $vault): bool
    {
        return $user->belongsToTeam($vault->team);
    }

    /**
     * Determine whether the user can create vaults in the team.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the vault settings.
     */
    public function update(User $user, Vault $vault): bool
    {
        if (! $user->belongsToTeam($vault->team)) {
            return false;
        }

        $role = $user->teamRole($vault->team);

        return ($role !== null && $role->level() >= TeamRole::Admin->level())
            || $vault->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete the vault.
     */
    public function delete(User $user, Vault $vault): bool
    {
        if (! $user->belongsToTeam($vault->team)) {
            return false;
        }

        $role = $user->teamRole($vault->team);

        return $role !== null && $role->level() >= TeamRole::Admin->level();
    }

    /**
     * Determine whether the user can manage path permissions on the vault.
     */
    public function managePermissions(User $user, Vault $vault): bool
    {
        if (! $user->belongsToTeam($vault->team)) {
            return false;
        }

        $role = $user->teamRole($vault->team);

        return $role !== null && $role->level() >= TeamRole::Admin->level();
    }
}
