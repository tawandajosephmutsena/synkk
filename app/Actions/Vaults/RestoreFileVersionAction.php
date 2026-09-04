<?php

namespace App\Actions\Vaults;

use App\Models\User;
use App\Models\VaultFile;
use App\Models\VaultFileVersion;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RestoreFileVersionAction
{
    public function __construct(
        protected SyncUploadAction $uploadAction
    ) {}

    /**
     * Restore a historical file version as the current active version.
     */
    public function execute(VaultFileVersion $versionRecord, User $user): VaultFile
    {
        $disk = config('synkk.storage_disk', 'local');

        if (! Storage::disk($disk)->exists($versionRecord->storage_path)) {
            throw new RuntimeException('Historical version file snapshot not found on storage.');
        }

        $contents = Storage::disk($disk)->get($versionRecord->storage_path);
        $file = $versionRecord->file;
        $vault = $versionRecord->vault;

        $res = $this->uploadAction->execute(
            $vault,
            $user,
            'Web Dashboard (Version Restore)',
            $file->path,
            $contents,
            $file->version
        );

        $file->refresh();

        return $file;
    }
}
