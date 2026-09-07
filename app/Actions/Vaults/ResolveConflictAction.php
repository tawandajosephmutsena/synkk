<?php

namespace App\Actions\Vaults;

use App\Models\User;
use App\Models\Vault;
use App\Models\VaultFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ResolveConflictAction
{
    public function __construct(
        protected SyncUploadAction $uploadAction
    ) {}

    /**
     * Atomically resolve a conflict between a canonical file and its conflict copy.
     *
     * @return array{
     *     status: string,
     *     canonical_path: string,
     *     conflict_path: string,
     *     version: int,
     *     file: VaultFile
     * }
     */
    public function execute(
        Vault $vault,
        User $user,
        string $canonicalPath,
        string $conflictPath,
        string $resolvedContent,
        string $deviceName = 'Visual Conflict Sandbox'
    ): array {
        $canonicalPath = ltrim(str_replace('\\', '/', $canonicalPath), '/');
        $conflictPath = ltrim(str_replace('\\', '/', $conflictPath), '/');

        $permission = $vault->permissionForPath($user, $canonicalPath);
        if ($permission !== 'read_write') {
            throw new RuntimeException("You do not have write permissions for '{$canonicalPath}'.");
        }

        return DB::transaction(function () use ($vault, $user, $canonicalPath, $conflictPath, $resolvedContent, $deviceName) {
            $canonicalFile = $vault->files()
                ->where('path', $canonicalPath)
                ->where('is_deleted', false)
                ->first();

            $baseVersion = $canonicalFile ? $canonicalFile->version : 0;

            // 1. Upload the reconciled content as the canonical note
            $uploadResult = $this->uploadAction->execute(
                vault: $vault,
                user: $user,
                deviceName: $deviceName,
                path: $canonicalPath,
                contents: $resolvedContent,
                baseVersion: $baseVersion
            );

            // If an identical status is returned because content didn't change, re-fetch canonical
            $canonicalFile = $vault->files()
                ->where('path', $canonicalPath)
                ->where('is_deleted', false)
                ->firstOrFail();

            // 2. Mark the conflict file as deleted and record tombstone in changelog
            $conflictFile = $vault->files()
                ->where('path', $conflictPath)
                ->where('is_deleted', false)
                ->first();

            if ($conflictFile) {
                $conflictFile->update([
                    'is_deleted' => true,
                    'version' => $canonicalFile->version,
                    'last_modified_by' => $user->id,
                ]);

                // Create changelog tombstone so clients delete their local conflict file on next sync
                $vault->changeLogs()->create([
                    'user_id' => $user->id,
                    'device_name' => $deviceName,
                    'path' => $conflictPath,
                    'action' => 'deleted',
                    'version' => $canonicalFile->version,
                    'sha256' => null,
                    'size' => 0,
                    'has_secrets' => false,
                ]);
            }

            // 3. Record changelog audit for reconciliation
            $vault->changeLogs()->create([
                'user_id' => $user->id,
                'device_name' => $deviceName,
                'path' => $canonicalPath,
                'action' => 'reconciled',
                'version' => $canonicalFile->version,
                'sha256' => $canonicalFile->sha256,
                'size' => $canonicalFile->size,
                'has_secrets' => false,
            ]);

            return [
                'status' => 'resolved',
                'canonical_path' => $canonicalPath,
                'conflict_path' => $conflictPath,
                'version' => $canonicalFile->version,
                'file' => $canonicalFile,
            ];
        });
    }
}
