<?php

namespace App\Actions\Vaults;

use App\Models\User;
use App\Models\Vault;
use App\ValueObjects\VaultContentEnvelope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
                $envelope = $item['envelope'] ?? null;
                if (! $envelope instanceof VaultContentEnvelope) {
                    try {
                        $envelope = VaultContentEnvelope::fromValidated($item, $vault);
                    } catch (ValidationException $e) {
                        $errors++;
                        $results[] = [
                            'path' => $path,
                            'status' => 'error',
                            'message' => $e->getMessage(),
                            'errors' => $e->errors(),
                        ];

                        continue;
                    }
                }

                $baseVersion = (int) ($item['base_version'] ?? 0);

                try {
                    $res = $this->uploadAction->execute(
                        $vault,
                        $user,
                        $deviceName,
                        $path,
                        $envelope->payload,
                        $baseVersion,
                        $envelope
                    );

                    if (($res['status'] ?? '') === 'conflict') {
                        $conflicts++;
                    } elseif (($res['status'] ?? '') === 'created' || ($res['status'] ?? '') === 'updated') {
                        $pushed++;
                    }

                    $results[] = $res;
                } catch (\Throwable $e) {
                    $errors++;
                    $results[] = [
                        'path' => $path,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                }
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
