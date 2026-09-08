<?php

namespace App\Actions\Vaults;

use App\Models\User;
use App\Models\VaultFile;
use App\Models\VaultFileVersion;
use App\ValueObjects\VaultContentEnvelope;
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
        if ($contents === null) {
            throw new RuntimeException('Could not read version snapshot content.');
        }

        $file = $versionRecord->file;
        $vault = $versionRecord->vault;

        $envelope = null;
        if ($versionRecord->is_encrypted || $vault->is_e2ee) {
            $envelope = new VaultContentEnvelope(
                payload: $contents,
                payloadSha256: $versionRecord->sha256,
                plaintextSize: (int) ($versionRecord->original_size ?: strlen($contents)),
                encrypted: (bool) $versionRecord->is_encrypted,
                iv: $versionRecord->encryption_iv,
                tag: $versionRecord->encryption_tag,
                ghost: (bool) $versionRecord->is_ghost,
                mimeType: $versionRecord->mime_type,
                formatVersion: (int) ($versionRecord->format_version ?: 2),
            );
        }

        $this->uploadAction->execute(
            $vault,
            $user,
            'Web Dashboard (Version Restore)',
            $file->path,
            $contents,
            $file->version,
            $envelope
        );

        $file->refresh();

        return $file;
    }
}
