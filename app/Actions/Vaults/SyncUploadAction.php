<?php

namespace App\Actions\Vaults;

use App\Models\User;
use App\Models\Vault;
use App\Models\VaultFileVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SyncUploadAction
{
    /**
     * Handle single file upload/update logic.
     *
     * @return array<string, mixed>
     */
    public function execute(
        Vault $vault,
        User $user,
        string $deviceName,
        string $path,
        string $contents,
        int $baseVersion = 0
    ): array {
        $disk = config('synkk.storage_disk', 'local');
        $sha256 = hash('sha256', $contents);
        $size = strlen($contents);
        $cleanPath = trim($path, '/');

        return DB::transaction(function () use ($vault, $user, $deviceName, $cleanPath, $contents, $sha256, $size, $baseVersion, $disk) {
            $existing = $vault->files()->where('path', $cleanPath)->lockForUpdate()->first();
            $latestVaultVersion = $vault->latestVersion();
            $nextVersion = $latestVaultVersion + 1;

            // Scenario 1: Identical file hash - no update needed
            if ($existing && ! $existing->is_deleted && $existing->sha256 === $sha256) {
                return [
                    'status' => 'identical',
                    'path' => $existing->path,
                    'version' => $existing->version,
                    'sha256' => $existing->sha256,
                    'message' => 'File is already up-to-date.',
                ];
            }

            // Scenario 2: Concurrent Conflict Detection
            $isConflict = false;
            $savePath = $cleanPath;

            if ($existing && ! $existing->is_deleted && $baseVersion > 0 && $baseVersion < $existing->version) {
                $isConflict = true;
                $savePath = $this->generateConflictPath($cleanPath, $user->name);
            }

            // Write content to configured storage disk
            $storageFolder = "vaults/{$vault->id}";
            $storageFileName = Str::random(40);
            $storagePath = "{$storageFolder}/{$storageFileName}";
            Storage::disk($disk)->put($storagePath, $contents);

            if ($isConflict) {
                $conflictFile = $vault->files()->create([
                    'path' => $savePath,
                    'storage_path' => $storagePath,
                    'sha256' => $sha256,
                    'size' => $size,
                    'version' => $nextVersion,
                    'is_deleted' => false,
                    'last_modified_by' => $user->id,
                ]);

                $vault->changeLogs()->create([
                    'user_id' => $user->id,
                    'device_name' => $deviceName,
                    'path' => $savePath,
                    'action' => 'conflict',
                    'version' => $nextVersion,
                    'sha256' => $sha256,
                    'size' => $size,
                ]);

                return [
                    'status' => 'conflict',
                    'is_conflict' => true,
                    'original_path' => $cleanPath,
                    'path' => $savePath,
                    'version' => $nextVersion,
                    'sha256' => $sha256,
                    'size' => $size,
                    'message' => "Concurrent change detected. Your version was safely preserved as '{$savePath}'.",
                ];
            }

            // Scenario 3: Update existing file (Archive previous version)
            if ($existing) {
                if (! $existing->is_deleted && Storage::disk($disk)->exists($existing->storage_path)) {
                    $versionFolder = "vaults/{$vault->id}/versions";
                    $versionFileName = "file_{$existing->id}_v{$existing->version}_".Str::random(10);
                    $versionStoragePath = "{$versionFolder}/{$versionFileName}";

                    // Copy previous version to version storage folder
                    Storage::disk($disk)->copy($existing->storage_path, $versionStoragePath);

                    // Create VaultFileVersion record
                    VaultFileVersion::create([
                        'vault_file_id' => $existing->id,
                        'vault_id' => $vault->id,
                        'version' => $existing->version,
                        'storage_path' => $versionStoragePath,
                        'sha256' => $existing->sha256,
                        'size' => $existing->size,
                        'created_by' => $existing->last_modified_by ?: $user->id,
                    ]);

                    // Clean up old storage file if necessary
                    Storage::disk($disk)->delete($existing->storage_path);

                    // Prune old versions if limit exceeded
                    $retentionLimit = config('synkk.version_retention_limit', 25);
                    $oldVersions = $existing->versions()
                        ->orderBy('version', 'desc')
                        ->skip($retentionLimit)
                        ->take(100)
                        ->get();

                    foreach ($oldVersions as $oldVer) {
                        if (Storage::disk($disk)->exists($oldVer->storage_path)) {
                            Storage::disk($disk)->delete($oldVer->storage_path);
                        }
                        $oldVer->delete();
                    }
                }

                $existing->update([
                    'storage_path' => $storagePath,
                    'sha256' => $sha256,
                    'size' => $size,
                    'version' => $nextVersion,
                    'is_deleted' => false,
                    'last_modified_by' => $user->id,
                ]);

                $vault->changeLogs()->create([
                    'user_id' => $user->id,
                    'device_name' => $deviceName,
                    'path' => $cleanPath,
                    'action' => 'updated',
                    'version' => $nextVersion,
                    'sha256' => $sha256,
                    'size' => $size,
                ]);

                return [
                    'status' => 'updated',
                    'path' => $cleanPath,
                    'version' => $nextVersion,
                    'sha256' => $sha256,
                    'size' => $size,
                ];
            }

            // Scenario 4: Create new file record
            $vault->files()->create([
                'path' => $cleanPath,
                'storage_path' => $storagePath,
                'sha256' => $sha256,
                'size' => $size,
                'version' => $nextVersion,
                'is_deleted' => false,
                'last_modified_by' => $user->id,
            ]);

            $vault->changeLogs()->create([
                'user_id' => $user->id,
                'device_name' => $deviceName,
                'path' => $cleanPath,
                'action' => 'created',
                'version' => $nextVersion,
                'sha256' => $sha256,
                'size' => $size,
            ]);

            return [
                'status' => 'created',
                'path' => $cleanPath,
                'version' => $nextVersion,
                'sha256' => $sha256,
                'size' => $size,
            ];
        });
    }

    protected function generateConflictPath(string $path, string $userName): string
    {
        $info = pathinfo($path);
        $dirname = ($info['dirname'] && $info['dirname'] !== '.') ? $info['dirname'].'/' : '';
        $filename = $info['filename'];
        $extension = isset($info['extension']) ? '.'.$info['extension'] : '';
        $safeUser = Str::slug($userName);
        $timestamp = now()->format('Ymd-His');

        return "{$dirname}{$filename}.conflict-{$safeUser}-{$timestamp}{$extension}";
    }
}
