<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Vault;
use App\Models\VaultFile;
use App\Services\DeviceVaultAccess;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VaultPreflightController extends Controller
{
    public function __construct(
        protected DeviceVaultAccess $vaultAccess,
        protected PlanService $planService
    ) {}

    /**
     * Pre-flight health and migration inspection before initiating batch sync.
     */
    public function preflight(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken|null $deviceToken */
        $deviceToken = $request->attributes->get('device_token');

        if (! $deviceToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $this->vaultAccess->authorizeVaultAccess($deviceToken, $vault);
        } catch (HttpException $e) {
            return response()->json([
                'error' => $e->getStatusCode() === 404 ? 'Vault not found in current team or access denied' : $e->getMessage(),
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }

        $validated = $request->validate([
            'total_files' => ['required', 'integer', 'min:0'],
            'total_bytes' => ['required', 'integer', 'min:0'],
            'categories' => ['nullable', 'array'],
            'files' => ['nullable', 'array'],
            'files.*.path' => ['required_with:files', 'string'],
            'files.*.size' => ['nullable', 'integer', 'min:0'],
            'files.*.sha256' => ['nullable', 'string'],
        ]);

        $team = $vault->team;
        $totalBytes = (int) $validated['total_bytes'];
        $currentBytes = $this->planService->getTotalStorageBytes($team);
        $limitBytes = $this->planService->getStorageLimitMb($team) * 1024 * 1024;
        $remainingBytes = max(0, $limitBytes - $currentBytes);

        // Verify storage quota against plan
        if (! $this->planService->canUploadStorage($team, $totalBytes)) {
            return response()->json([
                'status' => 'quota_exceeded',
                'authorized' => false,
                'message' => 'Vault pre-flight check failed: Proposed sync payload exceeds team storage quota.',
                'quota' => [
                    'allowed' => false,
                    'current_storage_bytes' => $currentBytes,
                    'additional_bytes' => $totalBytes,
                    'storage_limit_bytes' => $limitBytes,
                    'deficit_bytes' => ($currentBytes + $totalBytes) - $limitBytes,
                ],
                'recommendation' => 'Upgrade to Synkk Pro LTD or Synkk Cloud to expand team storage limit.',
            ], 422);
        }

        // Compute existing server files in this vault
        $serverFiles = VaultFile::where('vault_id', $vault->id)
            ->where('is_deleted', false)
            ->get(['id', 'path', 'size', 'sha256', 'version']);

        $serverFileMap = $serverFiles->keyBy('path');
        $serverCount = $serverFiles->count();
        $serverBytes = (int) $serverFiles->sum('size');

        $toUploadCount = (int) $validated['total_files'];
        $toDownloadCount = 0;
        $identicalCount = 0;
        $bandwidthSavedBytes = 0;

        if (! empty($validated['files']) && is_array($validated['files'])) {
            /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $clientFiles */
            $clientFiles = collect($validated['files']);
            $clientPathMap = $clientFiles->keyBy('path');

            $toUploadCount = 0;
            foreach ($clientFiles as $cf) {
                $path = $cf['path'];
                if ($serverFileMap->has($path)) {
                    $sf = $serverFileMap->get($path);
                    $clientSha = $cf['sha256'] ?? null;
                    if ($clientSha && $clientSha === $sf->sha256) {
                        $identicalCount++;
                        $bandwidthSavedBytes += (int) ($cf['size'] ?? $sf->size);
                    } else {
                        $toUploadCount++;
                    }
                } else {
                    $toUploadCount++;
                }
            }

            foreach ($serverFiles as $sf) {
                if (! $clientPathMap->has($sf->path)) {
                    $toDownloadCount++;
                }
            }
        } else {
            $toDownloadCount = $serverCount;
        }

        return response()->json([
            'status' => 'ready',
            'authorized' => true,
            'vault' => [
                'id' => $vault->id,
                'slug' => $vault->slug,
                'name' => $vault->name,
                'version' => $vault->latestVersion(),
                'server_files_count' => $serverCount,
                'server_bytes' => $serverBytes,
            ],
            'quota' => [
                'allowed' => true,
                'current_storage_bytes' => $currentBytes,
                'additional_bytes' => $totalBytes,
                'storage_limit_bytes' => $limitBytes,
                'remaining_bytes' => $remainingBytes,
            ],
            'simulation' => [
                'to_upload_count' => $toUploadCount,
                'to_download_count' => $toDownloadCount,
                'identical_skipped_count' => $identicalCount,
                'bandwidth_saved_bytes' => $bandwidthSavedBytes,
            ],
            'safety' => [
                'atomic_shield_active' => true,
                'max_deletion_threshold_percent' => 20,
                'max_bulk_deletions' => 10,
            ],
        ]);
    }
}
