<?php

namespace App\Http\Controllers\Api;

use App\Actions\Vaults\BatchSyncAction;
use App\Actions\Vaults\ResolveConflictAction;
use App\Actions\Vaults\SyncUploadAction;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Vault;
use App\Services\CrdtCollabService;
use App\Services\E2eeVaultService;
use App\Services\GhostFileService;
use App\Services\PlanService;
use App\Services\ThreeWayDiffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VaultSyncController extends Controller
{
    public function __construct(
        protected SyncUploadAction $uploadAction,
        protected BatchSyncAction $batchSyncAction,
        protected ThreeWayDiffService $diffService,
        protected ResolveConflictAction $resolveConflictAction,
        protected CrdtCollabService $collabService,
        protected GhostFileService $ghostFileService,
        protected E2eeVaultService $e2eeService
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
            ->filter(fn (Vault $vault) => $deviceToken->canAccessVault($vault->id))
            ->values()
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

        // Verify vault belongs to team and device has permission
        if ($vault->team_id !== $deviceToken->team_id || ! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Vault not found in current team or access not allowed for this device token'], 404);
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
                'is_ghost' => (bool) $file->is_ghost,
                'original_size' => (int) ($file->original_size ?: $file->size),
                'mime_type' => $file->mime_type,
                'is_encrypted' => (bool) $file->is_encrypted,
                'encryption_iv' => $file->encryption_iv,
                'encryption_tag' => $file->encryption_tag,
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
                'is_e2ee' => (bool) $vault->is_e2ee,
                'e2ee_salt' => $vault->e2ee_salt,
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

        if ($vault->team_id !== $deviceToken->team_id || ! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Vault not found in current team or access not allowed for this device token'], 404);
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

        if ($vault->team_id !== $deviceToken->team_id || ! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Vault not found in current team or access not allowed for this device token'], 404);
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

        $asGhost = $request->boolean('ghost');
        if ($asGhost || ($file->is_ghost && ! $request->boolean('hydrate'))) {
            $stub = $this->ghostFileService->makeGhostStub($file);

            return response($stub, 200, [
                'Content-Type' => 'text/markdown; charset=utf-8',
                'X-Synkk-Ghost' => '1',
                'X-Synkk-Original-Size' => (string) ($file->original_size ?: $file->size),
                'X-Synkk-Sha256' => $file->sha256,
                'X-Synkk-Version' => (string) $file->version,
            ]);
        }

        $headers = [
            'Content-Type' => $this->guessMimeType($file->path),
            'X-Synkk-Sha256' => $file->sha256,
            'X-Synkk-Version' => (string) $file->version,
        ];

        if ($file->is_encrypted) {
            $headers['X-Synkk-Encrypted'] = '1';
            $headers['X-Synkk-IV'] = (string) $file->encryption_iv;
            $headers['X-Synkk-Tag'] = (string) $file->encryption_tag;
        }

        return Storage::disk($disk)->response($file->storage_path, basename($file->path), $headers);
    }

    /**
     * Upload or update a file in the vault.
     */
    public function upload(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        $user = $deviceToken->user;

        if ($vault->team_id !== $deviceToken->team_id || ! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Vault not found in current team or access not allowed for this device token'], 404);
        }

        if ($deviceToken->access_scope === 'read_only') {
            return response()->json([
                'error' => 'Permission Denied',
                'message' => 'This device token has read-only access and cannot upload, modify, or delete vault files.',
            ], 403);
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

        if ($deviceToken->team->isSuspended()) {
            return response()->json([
                'error' => 'Team Suspended',
                'message' => 'This team workspace is currently suspended. Please contact your platform administrator.',
            ], 403);
        }

        $planService = app(PlanService::class);
        $fileSize = $request->hasFile('file') ? (int) $request->file('file')->getSize() : strlen((string) $request->input('content', ''));
        if (! $planService->canUploadStorage($deviceToken->team, $fileSize)) {
            return response()->json([
                'error' => 'Quota Exceeded',
                'code' => 'STORAGE_QUOTA_EXCEEDED',
                'message' => 'Team storage quota exceeded. Upgrade to Synkk Pro or Cloud to increase capacity.',
                'storage_limit_mb' => $planService->getStorageLimitMb($deviceToken->team),
            ], 402);
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

        $isEncrypted = $request->boolean('is_encrypted');
        $iv = $request->input('encryption_iv');
        $tag = $request->input('encryption_tag');

        $isGhost = $request->boolean('is_ghost');
        $originalSize = (int) $request->input('original_size', 0);

        if ($isGhost && $originalSize === 0) {
            $parsedStub = $this->ghostFileService->parseGhostStub($contents);
            if ($parsedStub) {
                $originalSize = $parsedStub['size'] ?? 0;
            }
        }

        $res = $this->uploadAction->execute(
            $vault,
            $user,
            $deviceToken->name,
            $path,
            $contents,
            $baseVersion
        );

        if ($isEncrypted || $isGhost) {
            $savedFile = $vault->files()->where('path', $res['path'] ?? $path)->where('is_deleted', false)->first();
            if ($savedFile) {
                $updates = [];
                if ($isEncrypted) {
                    $updates['is_encrypted'] = true;
                    $updates['encryption_iv'] = $iv;
                    $updates['encryption_tag'] = $tag;
                }
                if ($isGhost) {
                    $updates['is_ghost'] = true;
                    $updates['original_size'] = $originalSize ?: $savedFile->size;
                    $updates['mime_type'] = $this->guessMimeType($path);
                }
                $savedFile->update($updates);
            }
        }

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

        if ($vault->team_id !== $deviceToken->team_id || ! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Vault not found in current team or access not allowed for this device token'], 404);
        }

        if ($deviceToken->access_scope === 'read_only') {
            return response()->json([
                'error' => 'Permission Denied',
                'message' => 'This device token has read-only access and cannot execute batch sync modifications.',
            ], 403);
        }

        if ($deviceToken->team->isSuspended()) {
            return response()->json([
                'error' => 'Team Suspended',
                'message' => 'This team workspace is currently suspended. Please contact your platform administrator.',
            ], 403);
        }

        $planService = app(PlanService::class);
        if (! $planService->canUploadStorage($deviceToken->team)) {
            return response()->json([
                'error' => 'Quota Exceeded',
                'code' => 'STORAGE_QUOTA_EXCEEDED',
                'message' => 'Team storage quota exceeded. Upgrade to Synkk Pro or Cloud to increase capacity.',
                'storage_limit_mb' => $planService->getStorageLimitMb($deviceToken->team),
            ], 402);
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

        if ($vault->team_id !== $deviceToken->team_id || ! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Vault not found in current team or access not allowed for this device token'], 404);
        }

        if ($deviceToken->access_scope === 'read_only') {
            return response()->json([
                'error' => 'Permission Denied',
                'message' => 'This device token has read-only access and cannot delete vault files.',
            ], 403);
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

    /**
     * List all pending conflict copies in the vault.
     */
    public function conflicts(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if (! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Device not authorized for this vault'], Response::HTTP_FORBIDDEN);
        }

        $user = $deviceToken->user;

        $conflicts = $vault->files()
            ->where('is_deleted', false)
            ->where(function ($query) {
                $query->where('path', 'like', '%.conflict-%')
                    ->orWhere('path', 'like', '%.sync-conflict-%');
            })
            ->get()
            ->filter(fn ($file) => $vault->permissionForPath($user, $file->path) !== 'hidden')
            ->map(function ($file) {
                $canonicalPath = preg_replace('/(\.conflict-[^.]+|\.sync-conflict-[^.]+)(\.[^.]+)$/', '$2', $file->path);
                if ($canonicalPath === $file->path) {
                    $canonicalPath = preg_replace('/(\.conflict-[^.]+|\.sync-conflict-[^.]+)$/', '', $file->path);
                }

                return [
                    'id' => $file->id,
                    'conflict_path' => $file->path,
                    'canonical_path' => $canonicalPath,
                    'size' => $file->size,
                    'version' => $file->version,
                    'last_modified_by' => $file->lastModifiedBy?->name ?? 'Unknown',
                    'updated_at' => $file->updated_at?->toIso8601String(),
                ];
            })
            ->values();

        return response()->json([
            'status' => 'ok',
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Generate structured 3-way diff between canonical note and conflict copy.
     */
    public function diffConflict(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if (! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Device not authorized for this vault'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'conflict_path' => ['required', 'string'],
            'canonical_path' => ['nullable', 'string'],
        ]);

        $conflictPath = ltrim(str_replace('\\', '/', $validated['conflict_path']), '/');
        $canonicalPath = isset($validated['canonical_path']) && filled($validated['canonical_path'])
            ? ltrim(str_replace('\\', '/', $validated['canonical_path']), '/')
            : preg_replace('/(\.conflict-[^.]+|\.sync-conflict-[^.]+)(\.[^.]+)$/', '$2', $conflictPath);

        if ($canonicalPath === $conflictPath) {
            $canonicalPath = preg_replace('/(\.conflict-[^.]+|\.sync-conflict-[^.]+)$/', '', $conflictPath);
        }

        $conflictFile = $vault->files()->where('path', $conflictPath)->where('is_deleted', false)->firstOrFail();
        $canonicalFile = $vault->files()->where('path', $canonicalPath)->where('is_deleted', false)->first();

        $disk = config('synkk.storage_disk', 'local');

        $conflictContent = Storage::disk($disk)->exists($conflictFile->storage_path)
            ? Storage::disk($disk)->get($conflictFile->storage_path)
            : '';

        $canonicalContent = ($canonicalFile && Storage::disk($disk)->exists($canonicalFile->storage_path))
            ? Storage::disk($disk)->get($canonicalFile->storage_path)
            : '';

        // Attempt to find base ancestor version snapshot
        $baseContent = '';
        if ($canonicalFile) {
            $ancestorVersion = $canonicalFile->versions()->where('version', '<', $canonicalFile->version)->latest('version')->first();
            if ($ancestorVersion && Storage::disk($disk)->exists($ancestorVersion->storage_path)) {
                $baseContent = Storage::disk($disk)->get($ancestorVersion->storage_path);
            }
        }

        $diff = $this->diffService->merge($baseContent, $canonicalContent, $conflictContent);

        return response()->json([
            'status' => 'ok',
            'canonical_path' => $canonicalPath,
            'conflict_path' => $conflictPath,
            'has_conflicts' => $diff['has_conflicts'],
            'conflict_count' => $diff['conflict_count'],
            'clean_count' => $diff['clean_count'],
            'merged_content' => $diff['merged_content'],
            'hunks' => $diff['hunks'],
            'canonical_version' => $canonicalFile?->version ?? 0,
            'conflict_version' => $conflictFile->version,
        ]);
    }

    /**
     * Resolve a conflict note by applying reconciled content and cleaning up conflict file.
     */
    public function resolveConflict(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if (! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Device not authorized for this vault'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'canonical_path' => ['required', 'string'],
            'conflict_path' => ['required', 'string'],
            'resolved_content' => ['required', 'string'],
        ]);

        $res = $this->resolveConflictAction->execute(
            vault: $vault,
            user: $deviceToken->user,
            canonicalPath: $validated['canonical_path'],
            conflictPath: $validated['conflict_path'],
            resolvedContent: $validated['resolved_content'],
            deviceName: $deviceToken->name
        );

        return response()->json([
            'status' => 'resolved',
            'canonical_path' => $res['canonical_path'],
            'conflict_path' => $res['conflict_path'],
            'version' => $res['version'],
            'sha256' => $res['file']->sha256,
        ]);
    }

    /**
     * Join a note collaboration room.
     */
    public function collabJoin(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if (! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Device not authorized for this vault'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'path' => ['required', 'string'],
            'peer_id' => ['required', 'string'],
        ]);

        $res = $this->collabService->join(
            vault: $vault,
            user: $deviceToken->user,
            path: $validated['path'],
            peerId: $validated['peer_id']
        );

        return response()->json($res);
    }

    /**
     * Sync CRDT deltas and cursor position with note collaboration room.
     */
    public function collabSync(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if (! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Device not authorized for this vault'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'path' => ['required', 'string'],
            'peer_id' => ['required', 'string'],
            'deltas' => ['nullable', 'array'],
            'cursor' => ['nullable', 'array'],
            'since_clock' => ['nullable', 'integer'],
        ]);

        $res = $this->collabService->sync(
            vault: $vault,
            user: $deviceToken->user,
            path: $validated['path'],
            peerId: $validated['peer_id'],
            localDeltas: $validated['deltas'] ?? [],
            cursor: $validated['cursor'] ?? null,
            sinceClock: (int) ($validated['since_clock'] ?? 0)
        );

        return response()->json($res);
    }

    /**
     * Leave a note collaboration room.
     */
    public function collabLeave(Request $request, Vault $vault): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
            'peer_id' => ['required', 'string'],
        ]);

        $this->collabService->leave($vault, $validated['path'], $validated['peer_id']);

        return response()->json(['status' => 'left']);
    }

    /**
     * Get active collaborator presence list for a note.
     */
    public function collabPresence(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if (! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Device not authorized for this vault'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $peers = $this->collabService->getPresence($vault, $validated['path']);

        return response()->json([
            'status' => 'ok',
            'peers' => $peers,
        ]);
    }

    /**
     * Hydrate an on-demand ghost file to fetch full binary payload.
     */
    public function hydrateFile(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if (! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Device not authorized for this vault'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $permission = $vault->permissionForPath($deviceToken->user, $validated['path']);
        if ($permission === 'hidden') {
            return response()->json(['error' => 'File not found or permission denied'], 404);
        }

        try {
            $hydrated = $this->ghostFileService->hydrate($vault, $validated['path']);

            return response()->json([
                'status' => 'hydrated',
                'is_ghost' => false,
                'path' => $hydrated['path'],
                'size' => $hydrated['size'],
                'sha256' => $hydrated['sha256'],
                'mime_type' => $hydrated['mime_type'],
                'content_base64' => base64_encode($hydrated['contents']),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Dehydrate a local/server file back to a lightweight ghost placeholder.
     */
    public function dehydrateFile(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if (! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Device not authorized for this vault'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'path' => ['required', 'string'],
        ]);

        try {
            $file = $this->ghostFileService->dehydrate($vault, $validated['path']);

            return response()->json([
                'status' => 'dehydrated',
                'path' => $file->path,
                'is_ghost' => true,
                'original_size' => $file->original_size,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Enable client-side zero-knowledge End-to-End Encryption on a vault.
     */
    public function enableE2ee(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if (! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Device not authorized for this vault'], Response::HTTP_FORBIDDEN);
        }

        if ($deviceToken->access_scope === 'read_only') {
            return response()->json(['error' => 'Read-only device tokens cannot change vault security settings'], 403);
        }

        $validated = $request->validate([
            'salt' => ['required', 'string', 'min:16'],
            'test_cipher' => ['required', 'string'],
        ]);

        $this->e2eeService->enable($vault, $validated['salt'], $validated['test_cipher']);

        return response()->json([
            'status' => 'enabled',
            'is_e2ee' => true,
            'vault_slug' => $vault->slug,
            'salt' => $vault->e2ee_salt,
        ]);
    }

    /**
     * Get vault E2EE encryption status and key derivation configuration.
     */
    public function e2eeStatus(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if (! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Device not authorized for this vault'], Response::HTTP_FORBIDDEN);
        }

        return response()->json($this->e2eeService->getStatus($vault));
    }

    /**
     * Mobile background transport heartbeat and delta check.
     */
    public function transportStatus(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if (! $deviceToken->canAccessVault($vault->id)) {
            return response()->json(['error' => 'Device not authorized for this vault'], Response::HTTP_FORBIDDEN);
        }

        $clientVersion = (int) $request->query('client_version', 0);
        $pendingChanges = $vault->changeLogs()->where('version', '>', $clientVersion)->count();

        return response()->json([
            'status' => 'healthy',
            'vault' => $vault->name,
            'vault_slug' => $vault->slug,
            'latest_version' => $vault->latestVersion(),
            'is_e2ee' => (bool) $vault->is_e2ee,
            'e2ee_salt' => $vault->e2ee_salt,
            'active_collaborators' => $this->collabService->getRoomPeers($vault, 'general')->count(),
            'total_files' => $vault->files()->where('is_deleted', false)->count(),
            'server_time' => now()->toIso8601String(),
            'client_version' => $clientVersion,
            'pending_changes' => $pendingChanges,
            'recommended_sync_interval_seconds' => 300,
            'relay' => [
                'online' => true,
                'heartbeat_at' => now()->timestamp,
            ],
        ]);
    }

    protected function generateConflictPath(string $path, string $userName): string
    {
        $info = pathinfo($path);
        $dirname = (isset($info['dirname']) && $info['dirname'] !== '.') ? $info['dirname'].'/' : '';
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
