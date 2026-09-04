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
