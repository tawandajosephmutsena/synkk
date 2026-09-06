<?php

namespace App\Services;

use App\Models\Team;
use App\Models\VaultFile;
use App\Models\VaultFileVersion;

class PlanService
{
    /**
     * Get plan configuration array for a team.
     *
     * @return array{name: string, badge: string, max_devices: int, max_vaults: int, max_members: int, storage_limit_mb: int, features: array<int, string>}
     */
    public function getPlanConfig(Team $team): array
    {
        $plans = config('synkk.plans', []);
        $planKey = $team->plan ?? 'free';

        return $plans[$planKey] ?? $plans['free'] ?? [
            'name' => 'Community Free',
            'badge' => 'Free CE',
            'max_devices' => 3,
            'max_vaults' => 1,
            'max_members' => 3,
            'storage_limit_mb' => 1000,
            'features' => ['basic_sync', 'web_editor', 'interactive_graph'],
        ];
    }

    /**
     * Get the max devices limit for a team.
     */
    public function getDeviceLimit(Team $team): int
    {
        return $team->max_devices ?? (int) ($this->getPlanConfig($team)['max_devices'] ?? 3);
    }

    /**
     * Get the max vaults limit for a team.
     */
    public function getVaultLimit(Team $team): int
    {
        return $team->max_vaults ?? (int) ($this->getPlanConfig($team)['max_vaults'] ?? 1);
    }

    /**
     * Get the max team members limit for a team.
     */
    public function getMemberLimit(Team $team): int
    {
        return $team->max_members ?? (int) ($this->getPlanConfig($team)['max_members'] ?? 3);
    }

    /**
     * Get storage limit in megabytes.
     */
    public function getStorageLimitMb(Team $team): int
    {
        return $team->storage_limit_mb ?? (int) ($this->getPlanConfig($team)['storage_limit_mb'] ?? 1000);
    }

    /**
     * Check if team can add another device token.
     */
    public function canAddDevice(Team $team): bool
    {
        if ($team->isSuspended()) {
            return false;
        }

        return $team->deviceTokens()->count() < $this->getDeviceLimit($team);
    }

    /**
     * Check if team can create another vault.
     */
    public function canCreateVault(Team $team): bool
    {
        if ($team->isSuspended()) {
            return false;
        }

        return $team->vaults()->count() < $this->getVaultLimit($team);
    }

    /**
     * Check if team can invite another member.
     */
    public function canInviteMember(Team $team): bool
    {
        if ($team->isSuspended()) {
            return false;
        }

        $currentCount = $team->members()->count() + $team->invitations()->count();

        return $currentCount < $this->getMemberLimit($team);
    }

    /**
     * Calculate total bytes consumed by a team across all vaults and versions.
     */
    public function getTotalStorageBytes(Team $team): int
    {
        $vaultIds = $team->vaults()->pluck('id');

        if ($vaultIds->isEmpty()) {
            return 0;
        }

        $activeFilesBytes = (int) VaultFile::whereIn('vault_id', $vaultIds)
            ->where('is_deleted', false)
            ->sum('size');

        $versionBytes = (int) VaultFileVersion::whereIn('vault_id', $vaultIds)->sum('size');

        return $activeFilesBytes + $versionBytes;
    }

    /**
     * Check if team has enough remaining storage quota for an upload.
     */
    public function canUploadStorage(Team $team, int $additionalBytes = 0): bool
    {
        if ($team->isSuspended()) {
            return false;
        }

        $limitBytes = $this->getStorageLimitMb($team) * 1024 * 1024;
        $currentBytes = $this->getTotalStorageBytes($team);

        return ($currentBytes + $additionalBytes) <= $limitBytes;
    }

    /**
     * Check if team's plan includes a specific feature flag.
     */
    public function hasFeature(Team $team, string $feature): bool
    {
        if ($team->isSuspended()) {
            return false;
        }

        $config = $this->getPlanConfig($team);
        $features = $config['features'] ?? [];

        return in_array($feature, $features, true);
    }

    /**
     * Get a comprehensive usage summary for dashboard telemetry.
     *
     * @return array<string, mixed>
     */
    public function getUsageSummary(Team $team): array
    {
        $config = $this->getPlanConfig($team);
        $deviceLimit = $this->getDeviceLimit($team);
        $devicesUsed = $team->deviceTokens()->count();

        $vaultLimit = $this->getVaultLimit($team);
        $vaultsUsed = $team->vaults()->count();

        $memberLimit = $this->getMemberLimit($team);
        $membersUsed = $team->members()->count() + $team->invitations()->count();

        $storageLimitMb = $this->getStorageLimitMb($team);
        $totalBytes = $this->getTotalStorageBytes($team);
        $totalMb = round($totalBytes / (1024 * 1024), 2);

        return [
            'plan_key' => $team->plan ?? 'free',
            'plan_name' => $config['name'] ?? 'Community Free',
            'plan_badge' => $config['badge'] ?? 'Free CE',
            'is_suspended' => $team->isSuspended(),
            'devices' => [
                'used' => $devicesUsed,
                'limit' => $deviceLimit,
                'percentage' => $deviceLimit > 0 ? min(100, round(($devicesUsed / $deviceLimit) * 100)) : 0,
                'can_add' => $devicesUsed < $deviceLimit,
            ],
            'vaults' => [
                'used' => $vaultsUsed,
                'limit' => $vaultLimit,
                'percentage' => $vaultLimit > 0 ? min(100, round(($vaultsUsed / $vaultLimit) * 100)) : 0,
                'can_add' => $vaultsUsed < $vaultLimit,
            ],
            'members' => [
                'used' => $membersUsed,
                'limit' => $memberLimit,
                'percentage' => $memberLimit > 0 ? min(100, round(($membersUsed / $memberLimit) * 100)) : 0,
                'can_add' => $membersUsed < $memberLimit,
            ],
            'storage' => [
                'used_mb' => $totalMb,
                'limit_mb' => $storageLimitMb,
                'percentage' => $storageLimitMb > 0 ? min(100, round(($totalMb / $storageLimitMb) * 100)) : 0,
                'can_upload' => $totalMb < $storageLimitMb,
            ],
            'features' => $config['features'] ?? [],
        ];
    }
}
