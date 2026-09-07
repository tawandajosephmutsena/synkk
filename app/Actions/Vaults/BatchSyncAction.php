<?php

namespace App\Actions\Vaults;

use App\Models\User;
use App\Models\Vault;
use Illuminate\Support\Facades\DB;

class BatchSyncAction
{
    public function __construct(
        protected SyncUploadAction $uploadAction
    ) {}

    /**
     * Process batch of file uploads and deletions atomically.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function execute(
        Vault $vault,
        User $user,
        string $deviceName,
        array $items
    ): array {
        $results = [];
        $pushed = 0;
        $deleted = 0;
        $conflicts = 0;
        $errors = 0;

        DB::transaction(function () use ($vault, $user, $deviceName, $items, &$results, &$pushed, &$deleted, &$conflicts, &$errors) {
            foreach ($items as $item) {
                $action = $item['action'] ?? 'upload';
                $path = trim($item['path'] ?? '', '/');

                if (empty($path)) {
                    $errors++;
                    $results[] = [
                        'path' => $path,
                        'status' => 'error',
                        'message' => 'Path parameter is required.',
                    ];

                    continue;
                }

                // Check permission for path
                $permission = $vault->permissionForPath($user, $path);
                if ($permission !== 'read_write') {
                    $errors++;
                    $results[] = [
                        'path' => $path,
                        'status' => 'forbidden',
                        'message' => "Permission denied for path '{$path}'.",
                    ];

                    continue;
                }

                if ($action === 'delete') {
                    $file = $vault->files()->where('path', $path)->where('is_deleted', false)->first();

                    if (! $file) {
                        $results[] = [
                            'path' => $path,
                            'status' => 'already_deleted',
                        ];

                        continue;
                    }

                    $nextVersion = $vault->latestVersion() + 1;

                    $file->update([
                        'is_deleted' => true,
                        'version' => $nextVersion,
                        'last_modified_by' => $user->id,
                    ]);

                    $vault->changeLogs()->create([
                        'user_id' => $user->id,
                        'device_name' => $deviceName,
                        'path' => $path,
                        'action' => 'deleted',
                        'version' => $nextVersion,
                    ]);

                    $deleted++;
                    $results[] = [
                        'path' => $path,
                        'status' => 'deleted',
                        'version' => $nextVersion,
                    ];

                    continue;
                }

                // Action is upload
                $contents = null;
                if (isset($item['content_base64'])) {
                    $contents = base64_decode($item['content_base64']);
                } elseif (isset($item['content'])) {
                    $contents = (string) $item['content'];
                }

                if ($contents === null) {
                    $errors++;
                    $results[] = [
                        'path' => $path,
                        'status' => 'error',
                        'message' => 'No file content provided.',
                    ];

                    continue;
                }

                $baseVersion = (int) ($item['base_version'] ?? 0);

                $res = $this->uploadAction->execute(
                    $vault,
                    $user,
                    $deviceName,
                    $path,
                    $contents,
                    $baseVersion
                );

                $isEncrypted = ! empty($item['is_encrypted']);
                $isGhost = ! empty($item['is_ghost']);

                if ($isEncrypted || $isGhost) {
                    $savedPath = (isset($res['path']) && is_string($res['path'])) ? $res['path'] : $path;
                    $savedFile = $vault->files()->where('path', $savedPath)->where('is_deleted', false)->first();
                    if ($savedFile) {
                        $updates = [];
                        if ($isEncrypted) {
                            $updates['is_encrypted'] = true;
                            $updates['encryption_iv'] = isset($item['encryption_iv']) ? (string) $item['encryption_iv'] : null;
                            $updates['encryption_tag'] = isset($item['encryption_tag']) ? (string) $item['encryption_tag'] : null;
                        }
                        if ($isGhost) {
                            $updates['is_ghost'] = true;
                            $updates['original_size'] = isset($item['original_size']) ? (int) $item['original_size'] : $savedFile->size;
                            $updates['mime_type'] = isset($item['mime_type']) ? (string) $item['mime_type'] : null;
                        }
                        $savedFile->update($updates);
                    }
                }

                if (($res['status'] ?? '') === 'conflict') {
                    $conflicts++;
                } elseif (($res['status'] ?? '') === 'created' || ($res['status'] ?? '') === 'updated') {
                    $pushed++;
                }

                $results[] = $res;
            }
        });

        return [
            'status' => 'ok',
            'latest_version' => $vault->latestVersion(),
            'summary' => [
                'pushed' => $pushed,
                'deleted' => $deleted,
                'conflicts' => $conflicts,
                'errors' => $errors,
                'total_processed' => count($results),
            ],
            'items' => $results,
        ];
    }
}
