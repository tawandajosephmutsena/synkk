<?php

namespace App\Http\Controllers\Api;

use App\Actions\Vaults\BatchSyncAction;
use App\Actions\Vaults\ResolveConflictAction;
use App\Actions\Vaults\SyncUploadAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BatchSyncRequest;
use App\Http\Requests\Api\UploadVaultFileRequest;
use App\Jobs\IndexVaultRagJob;
use App\Models\DeviceToken;
use App\Models\Vault;
use App\Models\VaultFile;
use App\Services\CrdtCollabService;
use App\Services\DeviceVaultAccess;
use App\Services\E2eeVaultService;
use App\Services\GhostFileService;
use App\Services\PlanService;
use App\Services\ThreeWayDiffService;
use App\Services\VaultProtocol;
use App\Services\VaultRagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VaultSyncController extends Controller
{
    public function __construct(
        protected SyncUploadAction $uploadAction,
        protected BatchSyncAction $batchSyncAction,
        protected ThreeWayDiffService $diffService,
        protected ResolveConflictAction $resolveConflictAction,
        protected CrdtCollabService $collabService,
        protected GhostFileService $ghostFileService,
        protected E2eeVaultService $e2eeService,
        protected VaultRagService $ragService,
        protected DeviceVaultAccess $vaultAccess,
        protected VaultProtocol $protocol
    ) {}

    /**
     * Authorize that the device token belongs to the vault's team and has permission to access the vault.
     */
    protected function authorizeDeviceForVault(?DeviceToken $deviceToken, Vault $vault): ?JsonResponse
    {
        if (! $deviceToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $this->vaultAccess->authorizeVaultAccess($deviceToken, $vault);

            return null;
        } catch (HttpException $e) {
            return response()->json([
                'error' => $e->getStatusCode() === 404 ? 'Vault not found in current team or access not allowed for this device token' : $e->getMessage(),
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

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
        $this->protocol->validateProtocolVersion($request, $vault);

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
            'protocol_version' => VaultProtocol::CURRENT_PROTOCOL_VERSION,
            'minimum_protocol_version' => VaultProtocol::MINIMUM_PROTOCOL_VERSION,
            'capabilities' => $this->protocol->manifestCapabilities($vault)['capabilities'],
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
    public function upload(UploadVaultFileRequest $request, Vault $vault): JsonResponse
    {
        $this->protocol->validateProtocolVersion($request, $vault);

        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        $user = $deviceToken->user;

        $path = trim((string) $request->input('path'), '/');

        try {
            $this->vaultAccess->authorizeWrite($deviceToken, $vault, $path);
        } catch (HttpException $e) {
            $statusCode = $e->getStatusCode();
            $message = $e->getMessage();
            $error = match ($statusCode) {
                404 => 'Vault not found in current team or access not allowed for this device token',
                403 => str_contains($message, 'suspended') ? 'Team Suspended' : 'Permission Denied',
                default => $message,
            };

            return response()->json([
                'error' => $error,
                'message' => $message,
            ], $statusCode);
        }

        $envelope = $request->toEnvelope($vault);

        $planService = app(PlanService::class);
        $fileSize = strlen($envelope->payload);
        if (! $planService->canUploadStorage($deviceToken->team, $fileSize)) {
            return response()->json([
                'error' => 'Quota Exceeded',
                'code' => 'STORAGE_QUOTA_EXCEEDED',
                'message' => 'Team storage quota exceeded. Upgrade to Synkk Pro or Cloud to increase capacity.',
                'storage_limit_mb' => $planService->getStorageLimitMb($deviceToken->team),
            ], 402);
        }

        $baseVersion = (int) $request->input('base_version', 0);

        $res = $this->uploadAction->execute(
            $vault,
            $user,
            $deviceToken->name,
            $path,
            $envelope->payload,
            $baseVersion,
            $envelope
        );

        $statusCode = ($res['status'] ?? '') === 'created' ? 201 : 200;

        return response()->json($res, $statusCode);
    }

    /**
     * Process bulk batch file sync.
     */
    public function batchSync(BatchSyncRequest $request, Vault $vault): JsonResponse
    {
        $this->protocol->validateProtocolVersion($request, $vault);

        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        $user = $deviceToken->user;

        try {
            $this->vaultAccess->authorizeVaultAccess($deviceToken, $vault);

            if ($deviceToken->access_scope === 'read_only') {
                abort(403, 'This device token has read-only access and cannot execute batch sync modifications.');
            }
        } catch (HttpException $e) {
            $statusCode = $e->getStatusCode();
            $message = $e->getMessage();
            $error = match ($statusCode) {
                404 => 'Vault not found in current team or access not allowed for this device token',
                403 => str_contains($message, 'suspended') ? 'Team Suspended' : 'Permission Denied',
                default => $message,
            };

            return response()->json([
                'error' => $error,
                'message' => $message,
            ], $statusCode);
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

        $items = $request->extractBatchItems($vault);

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

        $request->validate([
            'path' => ['required', 'string', 'not_regex:/\.\./'],
        ]);

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
                'permission' => $permission,
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
            'last_modified_device' => $deviceToken->name,
        ]);

        // Record change log
        $vault->changeLogs()->create([
            'vault_file_id' => $file->id,
            'user_id' => $user->id,
            'device_name' => $deviceToken->name,
            'path' => $path,
            'action' => 'deleted',
            'version' => $nextVersion,
            'sha256' => $file->sha256,
            'size' => 0,
        ]);

        return response()->json([
            'status' => 'deleted',
            'path' => $path,
            'version' => $nextVersion,
        ]);
    }

    /**
     * List active conflict files in the vault.
     */
    public function conflicts(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken|null $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($authError = $this->authorizeDeviceForVault($deviceToken, $vault)) {
            return $authError;
        }

        $user = $deviceToken->user;

        $conflicts = $vault->files()
            ->where('is_deleted', false)
            ->where(function ($query) {
                $query->where('path', 'like', '%.conflict-%')
                    ->orWhere('path', 'like', '%.sync-conflict-%');
            })
            ->with(['lastModifier'])
            ->get()
            ->filter(fn (VaultFile $file) => $vault->permissionForPath($user, $file->path) !== 'hidden')
            ->map(function (VaultFile $file) {
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
                    'last_modified_by' => $file->lastModifier ? $file->lastModifier->name : 'Unknown',
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
        /** @var DeviceToken|null $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($authError = $this->authorizeDeviceForVault($deviceToken, $vault)) {
            return $authError;
        }

        $validated = $request->validate([
            'conflict_path' => ['required', 'string', 'not_regex:/\.\./'],
            'canonical_path' => ['nullable', 'string', 'not_regex:/\.\./'],
        ]);

        $conflictPath = ltrim(str_replace('\\', '/', $validated['conflict_path']), '/');
        $canonicalPath = isset($validated['canonical_path']) && filled($validated['canonical_path'])
            ? ltrim(str_replace('\\', '/', $validated['canonical_path']), '/')
            : preg_replace('/(\.conflict-[^.]+|\.sync-conflict-[^.]+)(\.[^.]+)$/', '$2', $conflictPath);

        if ($canonicalPath === $conflictPath) {
            $canonicalPath = preg_replace('/(\.conflict-[^.]+|\.sync-conflict-[^.]+)$/', '', $conflictPath);
        }

        try {
            $this->vaultAccess->authorizeRead($deviceToken, $vault, $conflictPath);
            $this->vaultAccess->authorizeRead($deviceToken, $vault, $canonicalPath);
            $this->vaultAccess->validateConflictRelationship($canonicalPath, $conflictPath);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
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
            'canonical_version' => $canonicalFile ? $canonicalFile->version : 0,
            'conflict_version' => $conflictFile->version,
        ]);
    }

    /**
     * Resolve a conflict note by applying reconciled content and cleaning up conflict file.
     */
    public function resolveConflict(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken|null $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($authError = $this->authorizeDeviceForVault($deviceToken, $vault)) {
            return $authError;
        }

        $validated = $request->validate([
            'canonical_path' => ['required', 'string'],
            'conflict_path' => ['required', 'string'],
            'resolved_content' => ['required', 'string'],
        ]);

        try {
            $this->vaultAccess->authorizeWrite($deviceToken, $vault, $validated['canonical_path']);
            $this->vaultAccess->authorizeWrite($deviceToken, $vault, $validated['conflict_path']);
            $this->vaultAccess->validateConflictRelationship($validated['canonical_path'], $validated['conflict_path']);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

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
     * Hydrate an on-demand ghost file to fetch full binary payload.
     */
    public function hydrateFile(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken|null $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($authError = $this->authorizeDeviceForVault($deviceToken, $vault)) {
            return $authError;
        }

        $validated = $request->validate([
            'path' => ['required', 'string'],
        ]);

        try {
            $this->vaultAccess->authorizeRead($deviceToken, $vault, $validated['path']);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
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
        /** @var DeviceToken|null $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($authError = $this->authorizeDeviceForVault($deviceToken, $vault)) {
            return $authError;
        }

        $validated = $request->validate([
            'path' => ['required', 'string'],
        ]);

        try {
            $this->vaultAccess->authorizeWrite($deviceToken, $vault, $validated['path']);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

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
        /** @var DeviceToken|null $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($authError = $this->authorizeDeviceForVault($deviceToken, $vault)) {
            return $authError;
        }

        try {
            $this->vaultAccess->authorizeAdmin($deviceToken, $vault);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
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
        /** @var DeviceToken|null $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($authError = $this->authorizeDeviceForVault($deviceToken, $vault)) {
            return $authError;
        }

        return response()->json($this->e2eeService->getStatus($vault));
    }

    /**
     * Mobile background transport heartbeat and delta check.
     */
    public function transportStatus(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken|null $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($authError = $this->authorizeDeviceForVault($deviceToken, $vault)) {
            return $authError;
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

    /**
     * Agentic RAG query with graph-augmented retrieval and verified citations.
     */
    public function ragQuery(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken|null $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($authError = $this->authorizeDeviceForVault($deviceToken, $vault)) {
            return $authError;
        }

        $validated = $request->validate([
            'query' => ['required', 'string', 'max:1000'],
            'expand_graph' => ['nullable', 'boolean'],
            'max_citations' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $result = $this->ragService->query($vault, $validated['query'], [
            'expand_graph' => $validated['expand_graph'] ?? true,
            'max_citations' => $validated['max_citations'] ?? 4,
        ]);

        return response()->json([
            'status' => 'ok',
            ...$result,
        ]);
    }

    /**
     * Fast hybrid semantic search returning top matching note chunks and similarity scores.
     */
    public function ragSearch(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken|null $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($authError = $this->authorizeDeviceForVault($deviceToken, $vault)) {
            return $authError;
        }

        $validated = $request->validate([
            'query' => ['required', 'string', 'max:1000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $results = $this->ragService->search($vault, $validated['query'], $validated['limit'] ?? 5);

        return response()->json([
            'status' => 'ok',
            'query' => $validated['query'],
            'count' => count($results),
            'results' => $results,
        ]);
    }

    /**
     * Re-index vault markdown notes into vector embeddings via background queue.
     */
    public function ragIndex(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken|null $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($authError = $this->authorizeDeviceForVault($deviceToken, $vault)) {
            return $authError;
        }

        try {
            $this->vaultAccess->authorizeAdmin($deviceToken, $vault);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

        $force = (bool) $request->input('force', false);
        $sync = $request->boolean('sync', false);

        if ($sync) {
            $telemetry = $this->ragService->indexVault($vault, $force);

            return response()->json([
                'status' => 'indexed',
                ...$telemetry,
            ]);
        }

        IndexVaultRagJob::dispatch($vault, $force);
        $progress = $this->ragService->getProgress($vault);

        // When executed under synchronous queue runner
        if (($progress['status'] ?? '') === 'completed') {
            return response()->json([
                'status' => 'indexed',
                'files_indexed' => $progress['indexed_files'] ?? 0,
                'chunks_count' => $progress['chunks_count'] ?? 0,
                'duration_ms' => $progress['duration_ms'] ?? 0,
                'progress' => $progress,
            ]);
        }

        return response()->json([
            'status' => 'queued',
            'message' => 'Vault RAG indexing dispatched to background queue worker.',
            'vault' => $vault->slug,
            'progress' => $progress,
            'progress_url' => route('api.vaults.rag.progress', ['vault' => $vault->slug]),
        ], 202);
    }

    /**
     * Report real-time RAG indexing progress percentage and metrics.
     */
    public function ragProgress(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken|null $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($authError = $this->authorizeDeviceForVault($deviceToken, $vault)) {
            return $authError;
        }

        $progress = $this->ragService->getProgress($vault);

        return response()->json([
            'status' => 'ok',
            'vault' => $vault->slug,
            ...$progress,
        ]);
    }

    /**
     * Report RAG indexing health and status.
     */
    public function ragStatus(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken|null $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($authError = $this->authorizeDeviceForVault($deviceToken, $vault)) {
            return $authError;
        }

        $status = $this->ragService->getStatus($vault);
        $progress = $this->ragService->getProgress($vault);

        return response()->json([
            'status' => 'ok',
            ...$status,
            'progress' => $progress,
        ]);
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
