<?php

namespace App\Http\Controllers\Api;

use App\Actions\Vaults\BatchSyncAction;
use App\Actions\Vaults\SyncUploadAction;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Vault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VaultSyncController extends Controller
{
    public function __construct(
        protected SyncUploadAction $uploadAction,
        protected BatchSyncAction $batchSyncAction
    ) {}

    /**
     * List all vaults accessible to the current team member.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        $user = $deviceToken->user;
        $team = $deviceToken->team;

        $vaults = $team->vaults()
            ->withCount(['files' => fn ($q) => $q->where('is_deleted', false)])
            ->get()
            ->map(function (Vault $vault) use ($user) {
                return [
                    'id' => $vault->id,
                    'name' => $vault->name,
                    'slug' => $vault->slug,
                    'description' => $vault->description,
                    'default_permission' => $vault->default_permission,
                    'user_root_permission' => $vault->permissionForPath($user, ''),
                    'total_files' => $vault->files_count,
                    'total_bytes' => $vault->totalStorageBytes(),
                    'latest_version' => $vault->latestVersion(),
                    'updated_at' => $vault->updated_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'status' => 'ok',
            'vaults' => $vaults,
        ]);
    }

    /**
     * Get the full file manifest for a vault, respecting user folder/file permissions.
     */
    public function manifest(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        $user = $deviceToken->user;

        // Verify vault belongs to team
        if ($vault->team_id !== $deviceToken->team_id) {
            return response()->json(['error' => 'Vault not found in current team'], 404);
        }

        $sinceVersion = (int) $request->query('since_version', 0);

        // Fetch active files
        $filesQuery = $vault->files()->where('is_deleted', false);
        if ($sinceVersion > 0) {
            $filesQuery->where('version', '>', $sinceVersion);
        }
        $files = $filesQuery->get();

        $allowedFiles = [];
        foreach ($files as $file) {
            $permission = $vault->permissionForPath($user, $file->path);

            // Skip hidden files completely
            if ($permission === 'hidden') {
                continue;
            }

            $allowedFiles[] = [
                'path' => $file->path,
                'sha256' => $file->sha256,
                'size' => $file->size,
                'version' => $file->version,
                'permission' => $permission, // 'read_write' or 'read_only'
                'updated_at' => $file->updated_at?->toIso8601String(),
            ];
        }

        // Fetch deleted tombstones if syncing incrementally
        $deletedPaths = [];
        if ($sinceVersion > 0) {
            $deletedFiles = $vault->files()
                ->where('is_deleted', true)
                ->where('version', '>', $sinceVersion)
                ->get();

            foreach ($deletedFiles as $deleted) {
                // If user can view or could view this path, notify them to delete locally
                $permission = $vault->permissionForPath($user, $deleted->path);
                if ($permission !== 'hidden') {
                    $deletedPaths[] = [
                        'path' => $deleted->path,
                        'version' => $deleted->version,
                    ];
                }
            }
        }

        return response()->json([
            'status' => 'ok',
            'vault' => [
                'id' => $vault->id,
                'name' => $vault->name,
                'slug' => $vault->slug,
                'latest_version' => $vault->latestVersion(),
            ],
            'files' => $allowedFiles,
            'deleted' => $deletedPaths,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Incremental sync changes feed.
     */
    public function changes(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        $user = $deviceToken->user;

        if ($vault->team_id !== $deviceToken->team_id) {
            return response()->json(['error' => 'Vault not found'], 404);
        }

        $sinceVersion = (int) $request->query('since_version', 0);

        $changes = $vault->changeLogs()
            ->where('version', '>', $sinceVersion)
            ->orderBy('version', 'asc')
            ->limit(500)
            ->get();

        $filteredChanges = [];
        foreach ($changes as $change) {
            $permission = $vault->permissionForPath($user, $change->path);

            if ($permission === 'hidden') {
                continue;
            }

            $filteredChanges[] = [
                'id' => $change->id,
                'path' => $change->path,
                'action' => $change->action,
                'version' => $change->version,
                'sha256' => $change->sha256,
                'size' => $change->size,
                'permission' => $permission,
                'device_name' => $change->device_name,
                'created_at' => $change->created_at->toIso8601String(),
            ];
        }

        return response()->json([
            'status' => 'ok',
            'since_version' => $sinceVersion,
            'latest_version' => $vault->latestVersion(),
            'changes' => $filteredChanges,
        ]);
    }

    /**
     * Download a file from the vault.
     */
    public function download(Request $request, Vault $vault): Response
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        $user = $deviceToken->user;
        $disk = config('synkk.storage_disk', 'local');

        if ($vault->team_id !== $deviceToken->team_id) {
            return response()->json(['error' => 'Vault not found'], 404);
        }

        $path = $request->query('path');
        if (! $path) {
            return response()->json(['error' => 'Path parameter is required'], 400);
        }

        $permission = $vault->permissionForPath($user, $path);
        if ($permission === 'hidden') {
            return response()->json(['error' => 'File not found or permission denied'], 404);
        }

        $file = $vault->files()
            ->where('path', $path)
            ->where('is_deleted', false)
            ->first();

        if (! $file || ! Storage::disk($disk)->exists($file->storage_path)) {
            return response()->json(['error' => 'File not found on storage'], 404);
        }

        $fullDiskPath = Storage::disk($disk)->path($file->storage_path);

        return response()->file($fullDiskPath, [
            'Content-Type' => $this->guessMimeType($file->path),
            'X-Synkk-Sha256' => $file->sha256,
            'X-Synkk-Version' => (string) $file->version,
        ]);
    }

    /**
     * Upload or update a file in the vault.
     */
    public function upload(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        $user = $deviceToken->user;

        if ($vault->team_id !== $deviceToken->team_id) {
            return response()->json(['error' => 'Vault not found'], 404);
        }

        $request->validate([
            'path' => 'required|string',
            'base_version' => 'nullable|integer',
        ]);

        $path = trim($request->input('path'), '/');

        // Check permission
        $permission = $vault->permissionForPath($user, $path);
        if ($permission !== 'read_write') {
            return response()->json([
                'error' => 'Permission Denied',
                'message' => "You have '{$permission}' permission on '{$path}'. Changes cannot be pushed.",
                'permission' => $permission,
            ], 403);
        }

        // Get file contents (supports multipart 'file' or raw base64 / text)
        if ($request->hasFile('file')) {
            $uploadedFile = $request->file('file');
            $contents = file_get_contents($uploadedFile->getRealPath());
        } elseif ($request->has('content_base64')) {
            $contents = base64_decode($request->input('content_base64'));
        } elseif ($request->has('content')) {
            $contents = $request->input('content');
        } else {
            return response()->json(['error' => 'No file or content provided'], 400);
        }

        $baseVersion = (int) $request->input('base_version', 0);

        $res = $this->uploadAction->execute(
            $vault,
            $user,
            $deviceToken->name,
            $path,
            $contents,
            $baseVersion
        );

        $statusCode = ($res['status'] ?? '') === 'created' ? 201 : 200;

        return response()->json($res, $statusCode);
    }

    /**
     * Process bulk batch file sync.
     */
    public function batchSync(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        $user = $deviceToken->user;

        if ($vault->team_id !== $deviceToken->team_id) {
            return response()->json(['error' => 'Vault not found'], 404);
        }

        $maxBatch = config('synkk.max_batch_size', 100);

        $request->validate([
            'items' => "required|array|max:{$maxBatch}",
            'items.*.path' => 'required|string',
            'items.*.action' => 'nullable|string|in:upload,delete',
            'items.*.base_version' => 'nullable|integer',
        ]);

        $items = $request->input('items', []);

        $result = $this->batchSyncAction->execute(
            $vault,
            $user,
            $deviceToken->name,
            $items
        );

        return response()->json($result);
    }

    /**
     * Soft-delete a file from the vault (creates a tombstone for sync).
     */
    public function delete(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        $user = $deviceToken->user;

        if ($vault->team_id !== $deviceToken->team_id) {
            return response()->json(['error' => 'Vault not found'], 404);
        }

        $path = trim($request->input('path'), '/');
        if (! $path) {
            return response()->json(['error' => 'Path parameter is required'], 400);
        }

        // Check permission
        $permission = $vault->permissionForPath($user, $path);
        if ($permission !== 'read_write') {
            return response()->json([
                'error' => 'Permission Denied',
                'message' => "You do not have write permissions to delete '{$path}'.",
            ], 403);
        }

        $file = $vault->files()->where('path', $path)->where('is_deleted', false)->first();
        if (! $file) {
            return response()->json(['status' => 'already_deleted', 'path' => $path]);
        }

        $nextVersion = $vault->latestVersion() + 1;

        $file->update([
            'is_deleted' => true,
            'version' => $nextVersion,
            'last_modified_by' => $user->id,
        ]);

        $vault->changeLogs()->create([
            'user_id' => $user->id,
            'device_name' => $deviceToken->name,
            'path' => $path,
            'action' => 'deleted',
            'version' => $nextVersion,
        ]);

        return response()->json([
            'status' => 'deleted',
            'path' => $path,
            'version' => $nextVersion,
        ]);
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

    protected function guessMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'md' => 'text/markdown; charset=utf-8',
            'txt' => 'text/plain; charset=utf-8',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'canvas' => 'application/json',
            default => 'application/octet-stream',
        };
    }
}
