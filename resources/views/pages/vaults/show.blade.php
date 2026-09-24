<?php

use App\Actions\Vaults\ResolveConflictAction;
use App\Actions\Vaults\RestoreFileVersionAction;
use App\Actions\Vaults\SyncUploadAction;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultChangeLog;
use App\Models\VaultCollaborationDocument;
use App\Models\VaultFile;
use App\Models\VaultFileVersion;
use App\Models\VaultPermission;
use App\Services\CrdtCollabService;
use App\Services\E2eeVaultService;
use App\Services\GhostFileService;
use App\Services\KnowledgeGraphService;
use App\Services\PlanService;
use App\Services\ThreeWayDiffService;
use App\Services\VaultAnalyticsService;
use App\Services\VaultRagService;
use App\ValueObjects\VaultContentEnvelope;
use Flux\Flux;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new #[Title('Vault Details')] class extends Component
{
    public Vault $vault;

    #[Url(as: 'tab')]
    public string $activeTab = 'editor';

    #[Url]
    public ?string $path = null;

    #[Url]
    public ?int $file = null;

    // Active Editor File State
    public ?int $activeFileId = null;

    public ?VaultFile $activeFile = null;

    public string $editorContent = '';

    public string $editorTitle = '';

    public string $editorViewMode = 'split'; // 'split', 'source', 'preview'

    public ?int $editorBaseVersion = null;

    public bool $editorIsDirty = false;

    // 3-Way Conflict Sandbox State
    public ?string $conflictCanonicalPath = null;

    public ?string $conflictPath = null;

    public ?int $conflictCanonicalVersion = null;

    public array $conflictHunks = [];

    public bool $conflictHasConflicts = false;

    public int $conflictCount = 0;

    public string $conflictReconciledContent = '';

    public string $conflictCanonicalContent = '';

    public string $conflictTheirsContent = '';

    // CRDT Multiplayer State
    public ?int $collaborationDocumentId = null;

    public string $collabPeerId = '';

    public array $collabActivePeers = [];

    public int $collabClock = 0;

    // New Note Modal State
    public string $newNotePath = '';

    // New Permission Rule Form
    public ?int $ruleUserId = null;

    public string $rulePath = '';

    public string $rulePermission = 'read_only';

    public bool $ruleIsFolder = true;

    // Vault Edit Form
    public string $editName = '';

    public string $editDescription = '';

    public string $editDefaultPermission = 'read_write';

    // Search & Explorer
    public string $fileSearch = '';

    public string $fileViewMode = 'list'; // 'list', 'grid'

    public string $fileCategory = 'all'; // 'all', 'markdown', 'canvas', 'attachments', 'conflicts'

    public string $fileSort = 'recent'; // 'recent', 'oldest', 'name_asc', 'name_desc', 'size_desc', 'size_asc'

    public ?int $inspectedFileId = null;

    public ?int $renamingFileId = null;

    public string $renamingNewPath = '';

    public ?int $deletingFileId = null;

    public string $editorSearch = '';

    // Vault Intelligence & Analytics
    public string $analyticsTimeframe = '30d';

    public string $analyticsSearchQuery = '';

    public string $analyticsActionFilter = 'all';

    // Version History Modal State
    public ?int $selectedFileId = null;

    public ?VaultFile $selectedFile = null;

    // Vault Copilot & RAG State
    public string $copilotQuery = '';

    public array $copilotMessages = [];

    public ?string $copilotStatusMessage = null;

    public function mount(Vault $vault): void
    {
        abort_unless(Auth::user()->can('view', $vault), 403);

        $this->vault = $vault;
        $this->editName = $vault->name;
        $this->editDescription = $vault->description ?? '';
        $this->editDefaultPermission = $vault->default_permission;

        $this->initializeActiveFile();
    }

    public function initializeActiveFile(): void
    {
        $user = Auth::user();

        if ($this->file) {
            $fileRecord = $this->vault->files()->where('is_deleted', false)->find($this->file);
            if ($fileRecord && $this->vault->permissionForPath($user, $fileRecord->path) !== 'hidden') {
                $this->selectFile($fileRecord->id);

                return;
            }
        }

        if ($this->path) {
            $fileRecord = $this->vault->files()->where('is_deleted', false)->where('path', $this->path)->first();
            if ($fileRecord && $this->vault->permissionForPath($user, $fileRecord->path) !== 'hidden') {
                $this->selectFile($fileRecord->id);

                return;
            }
        }

        // Default to first accessible markdown note
        $firstAccessible = $this->accessibleMarkdownFiles->first();
        if ($firstAccessible) {
            $this->selectFile($firstAccessible->id);
        } else {
            if ($this->activeTab === 'editor' && $this->accessibleFiles->isEmpty()) {
                $this->activeTab = 'files';
            }
        }
    }

    public function selectFile(int $fileId): bool
    {
        $file = $this->vault->files()->where('is_deleted', false)->find($fileId);
        if (! $file) {
            return false;
        }

        $user = Auth::user();
        $permission = $this->vault->permissionForPath($user, $file->path);
        if ($permission === 'hidden') {
            Flux::toast(variant: 'danger', text: __('You do not have permission to view this note.'));

            return false;
        }

        $this->activeFileId = $file->id;
        $this->activeFile = $file;
        $this->file = $file->id;
        $this->path = $file->path;
        $raw = $file->getContents() ?? '';
        if ($file->is_encrypted || $this->vault->is_e2ee) {
            $this->editorContent = base64_encode($raw);
        } else {
            $this->editorContent = $raw;
        }
        $this->editorTitle = pathinfo($file->path, PATHINFO_FILENAME);
        $this->editorBaseVersion = $file->version;
        $this->editorIsDirty = false;

        $collabDoc = VaultCollaborationDocument::firstOrCreate(
            ['vault_id' => $this->vault->id, 'path' => $file->path],
            ['latest_sequence' => 0, 'is_active' => true]
        );
        $this->collaborationDocumentId = $collabDoc->id;

        return true;
    }

    public function openFileInEditor(int $fileId): void
    {
        if ($this->selectFile($fileId)) {
            $this->activeTab = 'editor';
        }
    }

    public function saveFile(SyncUploadAction $uploader): void
    {
        if (! $this->activeFileId) {
            return;
        }

        $activeFile = $this->vault->files()
            ->where('is_deleted', false)
            ->findOrFail($this->activeFileId);

        $user = Auth::user();
        $permission = $this->vault->permissionForPath($user, $activeFile->path);

        if ($permission !== 'read_write') {
            Flux::toast(variant: 'danger', text: __('You have read-only permissions for this note. Changes cannot be saved.'));

            return;
        }

        $result = $uploader->execute(
            vault: $this->vault,
            user: $user,
            deviceName: 'Web Editor',
            path: $activeFile->path,
            contents: $this->editorContent,
            baseVersion: $this->editorBaseVersion ?? $activeFile->version,
        );

        if ($result['status'] === 'conflict') {
            $conflictFile = $this->vault->files()
                ->where('is_deleted', false)
                ->where('path', $result['path'])
                ->first();

            if ($conflictFile) {
                $this->selectFile($conflictFile->id);
            }

            Flux::toast(variant: 'warning', text: __('A newer revision already exists. Your edit was preserved as :path.', ['path' => $result['path']]));

            return;
        }

        $this->activeFile = $this->vault->files()->findOrFail($activeFile->id);
        $this->editorBaseVersion = $this->activeFile->version;
        $this->editorIsDirty = false;

        if ($result['status'] === 'identical') {
            Flux::toast(variant: 'info', text: __('No changes to save. This note is already current.'));

            return;
        }

        Flux::toast(variant: 'success', text: __('Note saved as revision v:version.', ['version' => $this->activeFile->version]));
    }

    /**
     * Save client-side WebCrypto AES-GCM encrypted note without the server ever seeing plaintext.
     */
    public function saveEncryptedFile(string $ciphertextBase64, string $ivHex, string $tagHex, int $plaintextSize = 0, ?SyncUploadAction $uploader = null): void
    {
        $uploader ??= app(SyncUploadAction::class);

        if (! $this->activeFileId) {
            return;
        }

        $activeFile = $this->vault->files()
            ->where('is_deleted', false)
            ->findOrFail($this->activeFileId);

        $user = Auth::user();
        $permission = $this->vault->permissionForPath($user, $activeFile->path);

        if ($permission !== 'read_write') {
            Flux::toast(variant: 'danger', text: __('You have read-only permissions for this note. Changes cannot be saved.'));

            return;
        }

        // Use the validated envelope factory to enforce strict Base64 decoding,
        // IV/tag hex length rules, and format version requirements (P1-04).
        $envelope = VaultContentEnvelope::fromValidated(
            data: [
                'content_base64' => $ciphertextBase64,
                'encrypted' => true,
                'iv' => $ivHex,
                'tag' => $tagHex,
                'plaintext_size' => $plaintextSize,
                'mime_type' => 'text/markdown',
                'format_version' => 2,
            ],
            vault: $this->vault,
        );

        $rawCiphertext = $envelope->payload;

        $result = $uploader->execute(
            vault: $this->vault,
            user: $user,
            deviceName: 'Web Editor (E2EE)',
            path: $activeFile->path,
            contents: $rawCiphertext,
            baseVersion: $this->editorBaseVersion ?? $activeFile->version,
            envelope: $envelope,
        );

        $savedPath = (isset($result['path']) && is_string($result['path'])) ? $result['path'] : $activeFile->path;
        $savedFile = $this->vault->files()->where('path', $savedPath)->where('is_deleted', false)->first();
        if ($savedFile) {
            $savedFile->update([
                'is_encrypted' => true,
                'encryption_iv' => $ivHex,
                'encryption_tag' => $tagHex,
            ]);
        }

        if ($result['status'] === 'conflict') {
            if ($savedFile) {
                $this->selectFile($savedFile->id);
            }

            Flux::toast(variant: 'warning', text: __('A newer revision already exists. Your edit was preserved as :path.', ['path' => $savedPath]));

            return;
        }

        $this->activeFile = $this->vault->files()->findOrFail($activeFile->id);
        $this->editorBaseVersion = $this->activeFile->version;
        $this->editorIsDirty = false;

        Flux::toast(variant: 'success', text: __('Note encrypted & saved with client-side Zero-Knowledge WebCrypto.'));
    }

    public function enableE2ee(string $salt, string $testCipher, E2eeVaultService $e2eeService): void
    {
        abort_unless(Auth::user()->can('update', $this->vault), 403);

        $e2eeService->enable($this->vault, $salt, $testCipher);
        $this->vault->refresh();

        Flux::toast(variant: 'success', text: __('Zero-Knowledge End-to-End Encryption enabled successfully!'));
    }

    public function disableE2ee(E2eeVaultService $e2eeService): void
    {
        abort_unless(Auth::user()->can('update', $this->vault), 403);

        $e2eeService->disable($this->vault);
        $this->vault->refresh();

        Flux::toast(variant: 'warning', text: __('Zero-Knowledge Encryption disabled.'));
    }

    public function createNewNote(SyncUploadAction $uploader): void
    {
        $this->validate([
            'newNotePath' => ['required', 'string', 'max:500'],
        ]);

        $cleanPath = str_replace('\\', '/', trim($this->newNotePath, '/'));
        if (! str_ends_with(strtolower($cleanPath), '.md')) {
            $cleanPath .= '.md';
        }

        $pathSegments = explode('/', $cleanPath);
        $hasUnsafeSegment = collect($pathSegments)->contains(fn (string $segment): bool => $segment === '' || $segment === '.' || $segment === '..');

        if ($hasUnsafeSegment || preg_match('/[\x00-\x1F\x7F]/u', $cleanPath) === 1) {
            $this->addError('newNotePath', __('Use a relative vault path without empty, dot, or parent-directory segments.'));

            return;
        }

        $noteAlreadyExists = $this->vault->files()
            ->where('is_deleted', false)
            ->whereRaw('LOWER(path) = ?', [Str::lower($cleanPath)])
            ->exists();

        if ($noteAlreadyExists) {
            $this->addError('newNotePath', __('A note already exists at this path. Open it from the note list instead.'));

            return;
        }

        $user = Auth::user();
        $permission = $this->vault->permissionForPath($user, $cleanPath);

        if ($permission !== 'read_write') {
            Flux::toast(variant: 'danger', text: __('You do not have write permission to create files at this path.'));

            return;
        }

        $initialTitle = pathinfo($cleanPath, PATHINFO_FILENAME);
        $initialContent = "# {$initialTitle}\n\nStart typing your note here...\n";

        $uploader->execute(
            vault: $this->vault,
            user: $user,
            deviceName: 'Web Editor',
            path: $cleanPath,
            contents: $initialContent,
            baseVersion: 0,
        );

        $file = $this->vault->files()->where('path', $cleanPath)->first();
        $this->reset('newNotePath');
        $this->resetValidation('newNotePath');
        $this->dispatch('modal-close', name: 'new-note-modal');

        if ($file) {
            $this->selectFile($file->id);
            $this->activeTab = 'editor';
        }

        Flux::toast(variant: 'success', text: __('Note ":path" created successfully.', ['path' => $cleanPath]));
    }

    public function showFileHistory(int $fileId): void
    {
        $file = $this->vault->files()
            ->where('is_deleted', false)
            ->with(['versions.creator', 'lastModifier'])
            ->find($fileId);

        abort_unless($file && $this->vault->permissionForPath(Auth::user(), $file->path) !== 'hidden', 404);

        $this->selectedFileId = $fileId;
        $this->selectedFile = $file;
        $this->dispatch('modal-show', name: 'file-history');
    }

    public function restoreVersion(int $versionId, RestoreFileVersionAction $restoreAction): void
    {
        $versionRecord = VaultFileVersion::query()
            ->where('vault_id', $this->vault->id)
            ->with('file')
            ->findOrFail($versionId);

        abort_unless($this->vault->permissionForPath(Auth::user(), $versionRecord->file->path) === 'read_write', 403);

        $restoreAction->execute($versionRecord, Auth::user());

        $this->selectedFile = $this->selectedFileId
            ? $this->vault->files()->with(['versions.creator', 'lastModifier'])->find($this->selectedFileId)
            : null;

        if ($this->activeFileId === $this->selectedFileId) {
            $this->activeFile = $this->selectedFile;
            $this->editorContent = $this->selectedFile->getContents() ?? '';
            $this->editorBaseVersion = $this->selectedFile->version;
            $this->editorIsDirty = false;
        }

        Flux::toast(variant: 'success', text: __('Version v:version restored as current active note.', ['version' => $versionRecord->version]));
    }

    #[Computed]
    public function currentFileConflicts(): Collection
    {
        if (! $this->activeFile) {
            return collect();
        }

        $basePath = preg_replace('/(\.conflict-[^.]+|\.sync-conflict-[^.]+)(\.[^.]+)$/', '$2', $this->activeFile->path);
        if ($basePath === $this->activeFile->path) {
            $basePath = preg_replace('/(\.conflict-[^.]+|\.sync-conflict-[^.]+)$/', '', $this->activeFile->path);
        }

        $info = pathinfo($basePath);
        $dirname = (isset($info['dirname']) && $info['dirname'] !== '.') ? $info['dirname'].'/' : '';
        $prefix = $dirname.($info['filename'] ?? '');

        return $this->vault->files()
            ->where('is_deleted', false)
            ->where(function ($query) use ($prefix) {
                $query->where('path', 'like', "{$prefix}.conflict-%")
                    ->orWhere('path', 'like', "{$prefix}.sync-conflict-%");
            })
            ->get();
    }

    public function openConflictSandbox(string $conflictPath, ?string $canonicalPath = null, ?ThreeWayDiffService $diffService = null): void
    {
        $diffService ??= app(ThreeWayDiffService::class);
        $this->conflictPath = ltrim(str_replace('\\', '/', $conflictPath), '/');

        $derived = preg_replace('/(\.conflict-[^.]+|\.sync-conflict-[^.]+)(\.[^.]+)$/', '$2', $this->conflictPath);
        if ($derived === $this->conflictPath) {
            $derived = preg_replace('/(\.conflict-[^.]+|\.sync-conflict-[^.]+)$/', '', $this->conflictPath);
        }

        $canonical = $canonicalPath && ! str_contains($canonicalPath, '.conflict-') && ! str_contains($canonicalPath, '.sync-conflict-')
            ? ltrim(str_replace('\\', '/', $canonicalPath), '/')
            : $derived;

        if ($canonical !== $derived) {
            $canonical = $derived;
        }

        $this->conflictCanonicalPath = $canonical;

        $conflictFile = $this->vault->files()->where('path', $this->conflictPath)->where('is_deleted', false)->firstOrFail();
        $canonicalFile = $this->vault->files()->where('path', $canonical)->where('is_deleted', false)->first();

        $this->conflictCanonicalVersion = $canonicalFile?->version;

        $disk = config('synkk.storage_disk', 'local');
        $this->conflictTheirsContent = Storage::disk($disk)->exists($conflictFile->storage_path)
            ? Storage::disk($disk)->get($conflictFile->storage_path)
            : '';

        $this->conflictCanonicalContent = ($canonicalFile && Storage::disk($disk)->exists($canonicalFile->storage_path))
            ? Storage::disk($disk)->get($canonicalFile->storage_path)
            : '';

        $baseContent = '';
        if ($canonicalFile) {
            $ancestorVersion = $canonicalFile->versions()->where('version', '<', $canonicalFile->version)->latest('version')->first();
            if ($ancestorVersion && Storage::disk($disk)->exists($ancestorVersion->storage_path)) {
                $baseContent = Storage::disk($disk)->get($ancestorVersion->storage_path);
            }
        }

        $diff = $diffService->merge($baseContent, $this->conflictCanonicalContent, $this->conflictTheirsContent);
        $this->conflictHunks = $diff['hunks'];
        $this->conflictHasConflicts = $diff['has_conflicts'];
        $this->conflictCount = $diff['conflict_count'];
        $this->conflictReconciledContent = $diff['merged_content'];

        $this->dispatch('modal-show', name: 'conflict-sandbox-modal');
    }

    public function setHunkResolution(int $hunkId, string $choice, ?ThreeWayDiffService $diffService = null): void
    {
        $diffService ??= app(ThreeWayDiffService::class);
        if (isset($this->conflictHunks[$hunkId])) {
            $this->conflictHunks[$hunkId]['choice'] = $choice;
            $this->conflictReconciledContent = $diffService->assemble($this->conflictHunks);
        }
    }

    public function executeConflictResolution(ResolveConflictAction $resolver): void
    {
        if (! $this->conflictCanonicalPath || ! $this->conflictPath) {
            return;
        }

        try {
            $result = $resolver->execute(
                vault: $this->vault,
                user: Auth::user(),
                canonicalPath: $this->conflictCanonicalPath,
                conflictPath: $this->conflictPath,
                resolvedContent: $this->conflictReconciledContent,
                deviceName: 'Web Visual Sandbox'
            );

            $this->dispatch('modal-close', name: 'conflict-sandbox-modal');
            $this->selectFile($result['file']->id);

            $this->conflictCanonicalPath = null;
            $this->conflictPath = null;
            $this->conflictCanonicalVersion = null;
            $this->conflictHunks = [];
            $this->conflictHasConflicts = false;
            $this->conflictCount = 0;
            $this->conflictReconciledContent = '';
            $this->conflictCanonicalContent = '';
            $this->conflictTheirsContent = '';

            Flux::toast(variant: 'success', text: __('Conflict reconciled and note updated to revision v:version.', ['version' => $result['version']]));
        } catch (\Throwable $e) {
            report($e);
            Flux::toast(variant: 'danger', text: __('Failed to reconcile conflict: :message', ['message' => $e->getMessage()]));
        }
    }

    public function collabSyncPulse(?CrdtCollabService $collabService = null): void
    {
        if (! $this->activeFile) {
            return;
        }

        $collabService ??= app(CrdtCollabService::class);
        $res = $collabService->sync(
            vault: $this->vault,
            user: Auth::user(),
            path: $this->activeFile->path,
            peerId: $this->collabPeerId,
            localDeltas: [],
            cursor: null,
            sinceClock: $this->collabClock
        );

        $this->collabActivePeers = array_values(array_filter($res['peers'], fn ($p) => ($p['peer_id'] ?? '') !== $this->collabPeerId));
        $this->collabClock = $res['clock'];
    }

    public function updateVaultSettings(): void
    {
        $this->authorize('update', $this->vault);

        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editDescription' => ['nullable', 'string', 'max:1000'],
            'editDefaultPermission' => ['required', 'in:read_write,read_only,hidden'],
        ]);

        $this->vault->update([
            'name' => $this->editName,
            'description' => $this->editDescription,
            'default_permission' => $this->editDefaultPermission,
        ]);

        Flux::toast(variant: 'success', text: __('Vault settings updated.'));
    }

    public function addPathPermission(): void
    {
        $this->authorize('managePermissions', $this->vault);

        $planService = app(PlanService::class);
        if (! $planService->hasFeature($this->vault->team, 'path_acls')) {
            $this->dispatch('modal-close', name: 'add-path-permission');
            Flux::toast(
                variant: 'danger',
                text: __('Granular Path ACLs require a Pro Lifetime or Synkk Cloud plan. Upgrade to unlock path-level permissions.'),
            );

            return;
        }

        $this->validate([
            'rulePath' => ['required', 'string', 'max:500'],
            'rulePermission' => ['required', 'in:read_write,read_only,hidden'],
            'ruleUserId' => ['nullable', 'exists:users,id'],
        ]);

        $cleanPath = trim($this->rulePath, '/');

        $existing = $this->vault->permissions()
            ->where('path', $cleanPath)
            ->where('user_id', $this->ruleUserId ?: null)
            ->first();

        if ($existing) {
            $existing->update([
                'permission' => $this->rulePermission,
                'is_folder' => $this->ruleIsFolder,
            ]);
            Flux::toast(variant: 'success', text: __('Rule for ":path" updated.', ['path' => $cleanPath]));
        } else {
            $this->vault->permissions()->create([
                'user_id' => $this->ruleUserId ?: null,
                'path' => $cleanPath,
                'permission' => $this->rulePermission,
                'is_folder' => $this->ruleIsFolder,
            ]);
            Flux::toast(variant: 'success', text: __('Permission rule added for ":path".', ['path' => $cleanPath]));
        }

        $this->reset('rulePath', 'ruleUserId');
        $this->rulePermission = 'read_only';
        $this->ruleIsFolder = true;
        $this->dispatch('modal-close', name: 'add-path-permission');
    }

    public function deletePermission(int $permissionId): void
    {
        $this->authorize('managePermissions', $this->vault);

        $this->vault->permissions()->where('id', $permissionId)->delete();
        Flux::toast(variant: 'info', text: __('Permission rule removed.'));
    }

    public function deleteVault(): void
    {
        $this->authorize('delete', $this->vault);

        $name = $this->vault->name;
        $this->vault->delete();

        Flux::toast(variant: 'warning', text: __('Vault ":name" deleted.', ['name' => $name]));
        $this->redirectRoute('vaults.index', navigate: true);
    }

    public function hydrateGhostFile(int $fileId): void
    {
        $file = $this->vault->files()->findOrFail($fileId);
        app(GhostFileService::class)->hydrateFile($file);
        Flux::toast(variant: 'success', text: __("Note ':path' successfully hydrated.", ['path' => $file->path]));
    }

    public function dehydrateGhostFile(int $fileId): void
    {
        $file = $this->vault->files()->findOrFail($fileId);
        app(GhostFileService::class)->dehydrateFile($file);
        Flux::toast(variant: 'info', text: __("Attachment ':path' converted to on-demand ghost stub.", ['path' => $file->path]));
    }

    public function exportVaultZip(): BinaryFileResponse|Response
    {
        $user = Auth::user();
        abort_unless($user->can('view', $this->vault), 403);

        $files = $this->vault->files()
            ->where('is_deleted', false)
            ->get()
            ->filter(fn (VaultFile $file) => $this->vault->permissionForPath($user, $file->path) !== 'hidden');

        if ($files->isEmpty()) {
            Flux::toast(variant: 'warning', text: __('This vault contains no accessible files to export.'));

            return response()->noContent();
        }

        $disk = config('synkk.storage_disk', 'local');
        $tempFile = tempnam(sys_get_temp_dir(), 'synkk_vault_zip_');
        $zip = new ZipArchive;

        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Flux::toast(variant: 'danger', text: __('Failed to initialize zip archive on server.'));

            return response()->noContent();
        }

        foreach ($files as $file) {
            if (Storage::disk($disk)->exists($file->storage_path)) {
                $contents = Storage::disk($disk)->get($file->storage_path);
                $zip->addFromString($file->path, (string) $contents);
            }
        }

        $zip->close();

        $safeSlug = Str::slug($this->vault->name) ?: 'vault';
        $filename = "{$safeSlug}-export-".now()->format('Ymd-His').'.zip';

        Flux::toast(variant: 'success', text: __('Vault exported successfully. Download starting...'));

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    public function duplicateFile(int $fileId, SyncUploadAction $uploader): void
    {
        $file = $this->vault->files()
            ->where('is_deleted', false)
            ->findOrFail($fileId);

        $user = Auth::user();
        $dir = pathinfo($file->path, PATHINFO_DIRNAME);
        $filename = pathinfo($file->path, PATHINFO_FILENAME);
        $ext = pathinfo($file->path, PATHINFO_EXTENSION);
        $dirPrefix = ($dir === '.' || $dir === '') ? '' : $dir.'/';

        $copyIndex = 1;
        do {
            $suffix = $copyIndex === 1 ? ' (Copy)' : " (Copy {$copyIndex})";
            $copyPath = $dirPrefix.$filename.$suffix.($ext ? '.'.$ext : '');
            $exists = $this->vault->files()
                ->where('is_deleted', false)
                ->whereRaw('LOWER(path) = ?', [Str::lower($copyPath)])
                ->exists();
            $copyIndex++;
        } while ($exists);

        if ($this->vault->permissionForPath($user, $copyPath) !== 'read_write') {
            Flux::toast(variant: 'danger', text: __('You do not have write permission to duplicate files in this folder.'));

            return;
        }

        $contents = $file->getContents() ?? '';

        $uploader->execute(
            vault: $this->vault,
            user: $user,
            deviceName: 'Web Explorer',
            path: $copyPath,
            contents: $contents,
            baseVersion: 0,
        );

        $duplicatedFile = $this->vault->files()->where('path', $copyPath)->first();
        if ($duplicatedFile && $duplicatedFile->isMarkdown()) {
            $this->selectFile($duplicatedFile->id);
        }

        Flux::toast(variant: 'success', text: __('Note duplicated as ":path".', ['path' => $copyPath]));
    }

    public function openRenameModal(int $fileId): void
    {
        $file = $this->vault->files()
            ->where('is_deleted', false)
            ->findOrFail($fileId);

        $this->renamingFileId = $file->id;
        $this->renamingNewPath = $file->path;
        $this->resetValidation('renamingNewPath');
        $this->dispatch('modal-show', name: 'rename-file-modal');
    }

    public function executeRename(SyncUploadAction $uploader): void
    {
        if (! $this->renamingFileId) {
            return;
        }

        $file = $this->vault->files()
            ->where('is_deleted', false)
            ->findOrFail($this->renamingFileId);

        $user = Auth::user();
        if ($this->vault->permissionForPath($user, $file->path) !== 'read_write') {
            Flux::toast(variant: 'danger', text: __('You do not have permission to rename this note.'));

            return;
        }

        $this->validate([
            'renamingNewPath' => ['required', 'string', 'max:500'],
        ]);

        $cleanNewPath = str_replace('\\', '/', trim($this->renamingNewPath, '/'));
        if ($file->isMarkdown() && ! str_ends_with(strtolower($cleanNewPath), '.md')) {
            $cleanNewPath .= '.md';
        }

        $pathSegments = explode('/', $cleanNewPath);
        $hasUnsafeSegment = collect($pathSegments)->contains(fn (string $segment): bool => $segment === '' || $segment === '.' || $segment === '..');

        if ($hasUnsafeSegment || preg_match('/[\x00-\x1F\x7F]/u', $cleanNewPath) === 1) {
            $this->addError('renamingNewPath', __('Use a relative vault path without empty, dot, or parent-directory segments.'));

            return;
        }

        if ($cleanNewPath === $file->path) {
            $this->dispatch('modal-close', name: 'rename-file-modal');

            return;
        }

        $exists = $this->vault->files()
            ->where('is_deleted', false)
            ->where('id', '!=', $file->id)
            ->whereRaw('LOWER(path) = ?', [Str::lower($cleanNewPath)])
            ->exists();

        if ($exists) {
            $this->addError('renamingNewPath', __('A note or file already exists at this destination path.'));

            return;
        }

        if ($this->vault->permissionForPath($user, $cleanNewPath) !== 'read_write') {
            Flux::toast(variant: 'danger', text: __('You do not have write permission for the destination path.'));

            return;
        }

        $contents = $file->getContents() ?? '';

        $uploader->execute(
            vault: $this->vault,
            user: $user,
            deviceName: 'Web Explorer',
            path: $cleanNewPath,
            contents: $contents,
            baseVersion: 0,
        );

        $file->update(['is_deleted' => true]);
        VaultChangeLog::create([
            'vault_id' => $this->vault->id,
            'user_id' => $user->id,
            'device_name' => 'Web Explorer',
            'path' => $file->path,
            'action' => 'deleted',
            'version' => $this->vault->latestVersion() + 1,
            'size' => 0,
        ]);

        if ($this->activeFileId === $file->id) {
            $newFile = $this->vault->files()->where('path', $cleanNewPath)->first();
            if ($newFile) {
                $this->selectFile($newFile->id);
            }
        }

        $oldPath = $file->path;
        $this->reset('renamingFileId', 'renamingNewPath');
        $this->dispatch('modal-close', name: 'rename-file-modal');
        Flux::toast(variant: 'success', text: __('Renamed ":old" to ":new".', ['old' => $oldPath, 'new' => $cleanNewPath]));
    }

    public function confirmDeleteFile(int $fileId): void
    {
        $file = $this->vault->files()
            ->where('is_deleted', false)
            ->findOrFail($fileId);

        $this->deletingFileId = $file->id;
        $this->dispatch('modal-show', name: 'delete-file-modal');
    }

    public function executeDeleteFile(): void
    {
        if (! $this->deletingFileId) {
            return;
        }

        $file = $this->vault->files()
            ->where('is_deleted', false)
            ->findOrFail($this->deletingFileId);

        $user = Auth::user();
        if ($this->vault->permissionForPath($user, $file->path) !== 'read_write') {
            Flux::toast(variant: 'danger', text: __('You do not have permission to delete this note.'));

            return;
        }

        $file->update(['is_deleted' => true]);

        VaultChangeLog::create([
            'vault_id' => $this->vault->id,
            'user_id' => $user->id,
            'device_name' => 'Web Explorer',
            'path' => $file->path,
            'action' => 'deleted',
            'version' => $this->vault->latestVersion() + 1,
            'size' => 0,
        ]);

        if ($this->activeFileId === $file->id) {
            $this->activeFileId = null;
            $this->activeFile = null;
            $this->editorContent = '';
            $this->editorTitle = '';
        }

        if ($this->inspectedFileId === $file->id) {
            $this->inspectedFileId = null;
            $this->dispatch('modal-close', name: 'inspect-file-modal');
        }

        $deletedPath = $file->path;
        $this->reset('deletingFileId');
        $this->dispatch('modal-close', name: 'delete-file-modal');
        Flux::toast(variant: 'success', text: __('Note ":path" deleted.', ['path' => $deletedPath]));
    }

    public function downloadFile(int $fileId): BinaryFileResponse|Response
    {
        $file = $this->vault->files()
            ->where('is_deleted', false)
            ->findOrFail($fileId);

        $user = Auth::user();
        if ($this->vault->permissionForPath($user, $file->path) === 'hidden') {
            abort(403);
        }

        if (! $file->existsOnDisk()) {
            Flux::toast(variant: 'danger', text: __('File content is not stored on disk.'));

            return response()->noContent();
        }

        $downloadFilename = basename($file->path);

        return response()->download($file->disk_path, $downloadFilename);
    }

    public function inspectFile(int $fileId): void
    {
        $file = $this->vault->files()
            ->where('is_deleted', false)
            ->with(['lastModifier', 'versions'])
            ->find($fileId);

        if ($file) {
            $this->inspectedFileId = $file->id;
            $this->dispatch('modal-show', name: 'inspect-file-modal');
        }
    }

    public function copyWikilinkNotice(string $filename): void
    {
        Flux::toast(variant: 'success', text: __("Copied [[:filename]] to clipboard.", ['filename' => $filename]));
    }

    #[Computed]
    public function inspectedFile(): ?VaultFile
    {
        if (! $this->inspectedFileId) {
            return null;
        }

        return $this->vault->files()
            ->where('is_deleted', false)
            ->with(['lastModifier', 'versions'])
            ->find($this->inspectedFileId);
    }

    #[Computed]
    public function inspectedFileMetadata(): array
    {
        $file = $this->inspectedFile;
        if (! $file) {
            return [
                'words' => 0,
                'lines' => 0,
                'chars' => 0,
                'read_time' => '1 min read',
                'wikilinks' => [],
                'tags' => [],
                'excerpt' => '',
            ];
        }

        $content = $file->getContents() ?? '';
        $words = $file->isMarkdown() ? str_word_count(strip_tags($content)) : 0;
        $lines = $content !== '' ? substr_count($content, "\n") + 1 : 0;
        $chars = mb_strlen($content);
        $readMinutes = max(1, (int) ceil($words / 200));

        preg_match_all('/\[\[(.*?)\]\]/', $content, $wikiMatches);
        $wikilinks = array_slice(array_unique($wikiMatches[1] ?? []), 0, 8);

        preg_match_all('/(?:^|\s)#([a-zA-Z0-9_\-\/]+)/', $content, $tagMatches);
        $tags = array_slice(array_unique($tagMatches[1] ?? []), 0, 8);

        $excerpt = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($content))), 0, 300);

        return [
            'words' => $words,
            'lines' => $lines,
            'chars' => $chars,
            'read_time' => "{$readMinutes} min read",
            'wikilinks' => $wikilinks,
            'tags' => $tags,
            'excerpt' => $excerpt,
        ];
    }

    #[Computed]
    public function markdownFilesCount(): int
    {
        return $this->accessibleFiles->filter(fn (VaultFile $f) => $f->isMarkdown())->count();
    }

    #[Computed]
    public function canvasFilesCount(): int
    {
        return $this->accessibleFiles->filter(fn (VaultFile $f) => str_ends_with(strtolower($f->path), '.canvas'))->count();
    }

    #[Computed]
    public function attachmentFilesCount(): int
    {
        return $this->accessibleFiles->filter(fn (VaultFile $f) => ! $f->isMarkdown() && ! str_ends_with(strtolower($f->path), '.canvas'))->count();
    }

    #[Computed]
    public function conflictFilesCount(): int
    {
        return $this->accessibleFiles->filter(fn (VaultFile $f) => str_contains($f->path, '.conflict-') || str_contains($f->path, '.sync-conflict-'))->count();
    }

    #[Computed]
    public function accessibleFiles(): Collection
    {
        $user = Auth::user();

        return $this->vault->files()
            ->where('is_deleted', false)
            ->with('lastModifier')
            ->latest('updated_at')
            ->get()
            ->filter(fn (VaultFile $f) => $this->vault->permissionForPath($user, $f->path) !== 'hidden')
            ->values();
    }

    #[Computed]
    public function accessibleMarkdownFiles(): Collection
    {
        $user = Auth::user();

        return $this->accessibleFiles
            ->filter(fn (VaultFile $f) => $f->isMarkdown())
            ->values();
    }

    #[Computed]
    public function activeFilePermission(): string
    {
        if (! $this->activeFile) {
            return 'hidden';
        }

        return $this->vault->permissionForPath(Auth::user(), $this->activeFile->path);
    }

    #[Computed]
    public function canEditActiveFile(): bool
    {
        return $this->activeFilePermission === 'read_write';
    }

    #[Computed]
    public function teamMembers(): Collection
    {
        return $this->vault->team->members()->get();
    }

    #[Computed]
    public function permissionRules(): Collection
    {
        return $this->vault->permissions()
            ->with('user')
            ->orderBy('path')
            ->get();
    }

    #[Computed]
    public function files(): Collection
    {
        $query = $this->accessibleFiles;

        if ($this->fileCategory === 'markdown') {
            $query = $query->filter(fn (VaultFile $f) => $f->isMarkdown());
        } elseif ($this->fileCategory === 'canvas') {
            $query = $query->filter(fn (VaultFile $f) => str_ends_with(strtolower($f->path), '.canvas'));
        } elseif ($this->fileCategory === 'attachments') {
            $query = $query->filter(fn (VaultFile $f) => ! $f->isMarkdown() && ! str_ends_with(strtolower($f->path), '.canvas'));
        } elseif ($this->fileCategory === 'conflicts') {
            $query = $query->filter(fn (VaultFile $f) => str_contains($f->path, '.conflict-') || str_contains($f->path, '.sync-conflict-'));
        }

        if (! empty($this->fileSearch)) {
            $search = strtolower($this->fileSearch);
            $query = $query->filter(fn (VaultFile $f) => str_contains(strtolower($f->path), $search));
        }

        $query = match ($this->fileSort) {
            'name_asc' => $query->sortBy(fn (VaultFile $f) => strtolower($f->path)),
            'name_desc' => $query->sortByDesc(fn (VaultFile $f) => strtolower($f->path)),
            'size_desc' => $query->sortByDesc('size'),
            'size_asc' => $query->sortBy('size'),
            'oldest' => $query->sortBy('updated_at'),
            default => $query->sortByDesc('updated_at'),
        };

        return $query->values()->take(150);
    }

    #[Computed]
    public function filteredEditorNotes(): Collection
    {
        $query = $this->accessibleMarkdownFiles;

        if (! empty($this->editorSearch)) {
            $search = strtolower($this->editorSearch);
            $query = $query->filter(fn (VaultFile $f) => str_contains(strtolower($f->path), $search));
        }

        return $query;
    }

    #[Computed]
    public function activities(): Collection
    {
        return $this->vault->changeLogs()
            ->with('user')
            ->latest('created_at')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function totalSizeFormatted(): string
    {
        $bytes = $this->vault->totalStorageBytes();

        return $bytes > 0 ? Number::fileSize($bytes, precision: 1) : '0 B';
    }

    #[Computed]
    public function graphData(): array
    {
        return app(KnowledgeGraphService::class)->getInteractiveGraph(
            $this->vault,
            $this->accessibleMarkdownFiles
        );
    }

    #[Computed]
    public function renderedPreviewHtml(): string
    {
        if (empty($this->editorContent)) {
            return '<p class="text-zinc-400 italic text-xs">'.__('Empty note. Start typing to preview...').'</p>';
        }

        $content = $this->editorContent;
        $placeholders = [];
        $index = 0;

        // Convert Obsidian-style callouts: > [!TIP], > Tip:, etc.
        $content = preg_replace_callback('/^>\s*(?:\[!(TIP|NOTE|WARNING|IMPORTANT|CAUTION)\]|Tip:)\s*(.*?)$(?:\n(>(?:.*))*)?/mi', function ($matches) use (&$placeholders, &$index) {
            $type = ! empty($matches[1]) ? strtoupper($matches[1]) : 'TIP';
            $title = ! empty(trim($matches[2])) && $type !== 'TIP' ? trim($matches[2]) : ($type === 'TIP' ? 'Tip' : ucfirst(strtolower($type)));
            $firstLineBody = ($type === 'TIP' && ! empty(trim($matches[2])) && trim($matches[2]) !== 'Tip') ? trim($matches[2]) : '';
            $body = '';
            if (! empty($matches[3])) {
                $bodyLines = explode("\n", trim($matches[3]));
                $cleanLines = array_map(fn ($line) => preg_replace('/^>\s?/', '', $line), $bodyLines);
                $body = implode("\n", $cleanLines);
            }
            if (! empty($firstLineBody)) {
                $body = $firstLineBody.(! empty($body) ? "\n".$body : '');
            }

            $containerClass = match ($type) {
                'TIP' => 'border-purple-600/50 bg-[#1E1630] text-purple-200',
                'WARNING', 'CAUTION' => 'border-amber-800/60 bg-[#2B2319] text-amber-200',
                'IMPORTANT' => 'border-red-800/60 bg-[#2B1919] text-red-200',
                default => 'border-emerald-800/60 bg-[#162B21] text-emerald-200',
            };

            $titleColor = match ($type) {
                'TIP' => 'text-[#C084FC]',
                'WARNING', 'CAUTION' => 'text-amber-300',
                'IMPORTANT' => 'text-red-300',
                default => 'text-emerald-300',
            };

            $safeTitle = e($title);
            $safeBody = nl2br(e($body));

            $key = "%%SYNKK_CALLOUT_{$index}%%";
            $placeholders[$key] = "<div class=\"callout-box my-5 rounded-2xl border p-5 {$containerClass} shadow-lg\"><div class=\"flex items-center gap-2 font-bold text-xs mb-2 {$titleColor}\"><span class=\"text-sm\">💡</span> <span class=\"tracking-wide\">{$safeTitle}</span></div><div class=\"text-xs leading-relaxed text-zinc-300\">{$safeBody}</div></div>";
            $index++;

            return "\n\n{$key}\n\n";
        }, $content);

        // Convert [[Wiki Links]]
        $content = preg_replace_callback('/\[\[(.*?)\]\]/', function ($matches) use (&$placeholders, &$index) {
            $parts = explode('|', $matches[1]);
            $target = trim($parts[0]);
            $label = isset($parts[1]) ? trim($parts[1]) : $target;
            $safeLabel = e($label);

            $key = "%%SYNKK_WIKI_{$index}%%";
            $placeholders[$key] = "<span class=\"inline-flex items-center rounded bg-emerald-500/10 px-1.5 py-0.5 text-xs font-semibold text-emerald-400 border border-emerald-500/20\">[[{$safeLabel}]]</span>";
            $index++;

            return $key;
        }, $content);

        $html = Str::markdown($content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return strtr($html, $placeholders);
    }

    #[Computed]
    public function analyticsStats(): array
    {
        return app(VaultAnalyticsService::class)->getVaultStats($this->vault, $this->analyticsTimeframe);
    }

    #[Computed]
    public function analyticsWikiGraph(): array
    {
        return app(VaultAnalyticsService::class)->getWikiLinkGraphStats($this->vault->id);
    }

    #[Computed]
    public function analyticsLeaderboard(): array
    {
        return app(VaultAnalyticsService::class)->getContributorLeaderboard($this->vault->id, $this->analyticsTimeframe);
    }

    #[Computed]
    public function analyticsVelocity(): array
    {
        return app(VaultAnalyticsService::class)->getActivityVelocityTimeline($this->vault->id, 14);
    }

    #[Computed]
    public function analyticsRecentFeed(): Collection
    {
        return app(VaultAnalyticsService::class)->getRecentChangeFeed(
            $this->vault->id,
            $this->analyticsActionFilter !== 'all' ? $this->analyticsActionFilter : null,
            null,
            filled($this->analyticsSearchQuery) ? $this->analyticsSearchQuery : null,
            30
        );
    }

    public function askCopilot(?string $customQuery = null): void
    {
        $queryText = trim($customQuery ?? $this->copilotQuery);
        if ($queryText === '') {
            return;
        }

        abort_unless(Auth::user()->can('view', $this->vault), 403);

        $this->copilotMessages[] = [
            'role' => 'user',
            'content' => $queryText,
            'time' => now()->format('H:i'),
        ];

        $this->copilotQuery = '';

        try {
            $ragService = app(VaultRagService::class);
            $response = $ragService->query($this->vault, $queryText, [
                'expand_graph' => true,
                'max_citations' => 4,
            ]);

            $this->copilotMessages[] = [
                'role' => 'assistant',
                'content' => $response['answer'],
                'citations' => $response['citations'],
                'graph_nodes' => $response['graph_nodes'],
                'model' => $response['model'],
                'duration_ms' => $response['duration_ms'],
                'time' => now()->format('H:i'),
            ];
        } catch (Throwable $e) {
            $this->copilotMessages[] = [
                'role' => 'assistant',
                'content' => 'Error querying Vault Copilot: '.$e->getMessage(),
                'citations' => [],
                'graph_nodes' => [],
                'model' => 'error',
                'time' => now()->format('H:i'),
            ];
        }
    }

    public function reindexVaultEmbeddings(): void
    {
        abort_unless(Auth::user()->can('update', $this->vault), 403);

        $ragService = app(VaultRagService::class);
        $result = $ragService->indexVault($this->vault, force: true);

        $this->copilotStatusMessage = "Re-indexed {$result['files_indexed']} files ({$result['chunks_count']} chunks) in {$result['duration_ms']}ms.";
    }

    public function openCitationNote(string $notePath): void
    {
        $targetFile = $this->vault->files()->where('path', $notePath)->where('is_deleted', false)->first();
        if ($targetFile) {
            $this->selectFile($targetFile->id);
            $this->activeTab = 'editor';
        }
    }

    #[Computed]
    public function ragTelemetry(): array
    {
        return app(VaultRagService::class)->getStatus($this->vault);
    }

    #[Computed]
    public function preflightStorageTelemetry(): array
    {
        $planService = app(PlanService::class);
        $team = $this->vault->team;

        $currentBytes = $planService->getTotalStorageBytes($team);
        $limitMb = $planService->getStorageLimitMb($team);
        $limitBytes = $limitMb * 1024 * 1024;
        $usagePercent = $limitBytes > 0 ? min(100, round(($currentBytes / $limitBytes) * 100, 1)) : 0;

        $vaultFiles = $this->vault->files()->where('is_deleted', false)->get(['id', 'path', 'size', 'is_ghost']);
        $totalVaultBytes = (int) $vaultFiles->sum('size');
        $totalVaultFiles = $vaultFiles->count();

        $markdownBytes = (int) $vaultFiles->filter(fn ($f) => str_ends_with(strtolower($f->path), '.md'))->sum('size');
        $imageBytes = (int) $vaultFiles->filter(fn ($f) => preg_match('/\.(png|jpe?g|gif|svg|webp)$/i', $f->path))->sum('size');
        $ghostCount = $vaultFiles->where('is_ghost', true)->count();

        return [
            'team_storage_used_bytes' => $currentBytes,
            'team_storage_used_formatted' => Number::fileSize($currentBytes),
            'team_storage_limit_bytes' => $limitBytes,
            'team_storage_limit_formatted' => Number::fileSize($limitBytes),
            'team_storage_remaining_bytes' => max(0, $limitBytes - $currentBytes),
            'team_storage_remaining_formatted' => Number::fileSize(max(0, $limitBytes - $currentBytes)),
            'team_storage_usage_percent' => $usagePercent,
            'vault_total_bytes' => $totalVaultBytes,
            'vault_total_formatted' => Number::fileSize($totalVaultBytes),
            'vault_total_files' => $totalVaultFiles,
            'markdown_bytes_formatted' => Number::fileSize($markdownBytes),
            'image_bytes_formatted' => Number::fileSize($imageBytes),
            'ghost_files_count' => $ghostCount,
            'atomic_shield_active' => true,
            'shield_deletion_limit_percent' => 20,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Breadcrumb & Top Bar -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:breadcrumbs class="mb-1">
                <flux:breadcrumbs.item href="{{ route('vaults.index') }}">{{ __('Vaults') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $vault->name }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div class="flex items-center gap-3">
                <flux:heading size="xl" level="1" class="tracking-tight">{{ $vault->name }}</flux:heading>
                <code class="rounded bg-zinc-100 px-2 py-0.5 font-mono text-xs text-zinc-600 dark:bg-white/10 dark:text-zinc-300">{{ $vault->slug }}</code>

                @if ($vault->default_permission === 'read_write')
                    <flux:badge color="emerald" size="sm">{{ __('Default: Read & Write') }}</flux:badge>
                @elseif ($vault->default_permission === 'read_only')
                    <flux:badge color="amber" size="sm">{{ __('Default: Read Only') }}</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">{{ __('Default: Restricted') }}</flux:badge>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            <flux:modal.trigger name="new-note-modal">
                <flux:button variant="primary" size="sm" icon="plus" class="!bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90 !rounded-full px-4 font-bold text-xs shadow-xs">
                    {{ __('New Note') }}
                </flux:button>
            </flux:modal.trigger>

            @if ($activeTab === 'permissions')
                <flux:modal.trigger name="add-path-permission">
                    <flux:button variant="primary" size="sm" icon="plus" class="!rounded-full px-4 text-xs font-bold">
                        {{ __('Add Path Rule') }}
                    </flux:button>
                </flux:modal.trigger>
            @endif

            <flux:dropdown>
                <flux:button variant="subtle" size="sm" icon="ellipsis-horizontal" aria-label="Vault actions" class="!rounded-full" />
                <flux:menu>
                    <flux:menu.item icon="arrow-path" wire:click="$refresh">{{ __('Refresh Data') }}</flux:menu.item>
                    <flux:menu.item icon="cog" wire:click="$set('activeTab', 'settings')">{{ __('Vault Settings') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    <!-- Mini Stat Summary Row -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card variant="soft" class="py-3 px-4 rounded-2xl border-gray-200/80 dark:border-zinc-800">
            <div class="flex items-center justify-between">
                <flux:text class="text-xs text-zinc-400">{{ __('Accessible Notes') }}</flux:text>
                <flux:icon icon="document-text" class="size-4 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($this->accessibleMarkdownFiles->count()) }}</span>
                <span class="text-xs text-zinc-400">{{ __('notes') }} ({{ number_format($this->accessibleFiles->count()) }} {{ __('total') }})</span>
            </div>
        </flux:card>

        <flux:card variant="soft" class="py-3 px-4 rounded-2xl border-gray-200/80 dark:border-zinc-800">
            <div class="flex items-center justify-between">
                <flux:text class="text-xs text-zinc-400">{{ __('Storage Footprint') }}</flux:text>
                <flux:icon icon="server-stack" class="size-4 text-zinc-400" />
            </div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->totalSizeFormatted }}</span>
                <span class="text-xs text-zinc-400">{{ __('synced storage') }}</span>
            </div>
        </flux:card>

        <flux:card variant="soft" class="py-3 px-4 rounded-2xl border-gray-200/80 dark:border-zinc-800">
            <div class="flex items-center justify-between">
                <flux:text class="text-xs text-zinc-400">{{ __('Current Revision') }}</flux:text>
                <flux:icon icon="clock" class="size-4 text-zinc-400" />
            </div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-xl font-bold text-zinc-900 dark:text-zinc-100">v{{ $vault->latestVersion() }}</span>
                <span class="text-xs text-emerald-600 dark:text-emerald-400">{{ __('Latest sync state') }}</span>
            </div>
        </flux:card>

        <flux:card variant="soft" class="py-3 px-4 rounded-2xl border-gray-200/80 dark:border-zinc-800">
            <div class="flex items-center justify-between">
                <flux:text class="text-xs text-zinc-400">{{ __('Safety & DLP Guard') }}</flux:text>
                <flux:icon icon="shield-check" class="size-4 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ __('Active') }}
                </span>
                <span class="text-[11px] text-zinc-400">{{ __('10% guard · Secret scan') }}</span>
            </div>
        </flux:card>
    </div>

    <!-- Donezo Signature Segmented Navigation -->
    <div class="flex items-center gap-2 border-b border-gray-200 pb-3 dark:border-zinc-800">
        <flux:radio.group wire:model.live="activeTab" variant="segmented" size="sm">
            <flux:radio value="editor">
                <span class="flex items-center gap-1.5 font-bold text-xs">
                    <flux:icon icon="document-text" class="size-4" />
                    <span>{{ __('Markdown Editor') }}</span>
                </span>
            </flux:radio>
            <flux:radio value="graph">
                <span class="flex items-center gap-1.5 font-bold text-xs">
                    <flux:icon icon="squares-2x2" class="size-4" />
                    <span>{{ __('Graph View') }}</span>
                </span>
            </flux:radio>
            <flux:radio value="files">
                <span class="flex items-center gap-1.5 font-bold text-xs">
                    <flux:icon icon="folder-open" class="size-4" />
                    <span>{{ __('Files Explorer') }}</span>
                </span>
            </flux:radio>
            <flux:radio value="permissions">
                <span class="flex items-center gap-1.5 font-bold text-xs">
                    <flux:icon icon="shield-check" class="size-4" />
                    <span>{{ __('Permissions Matrix') }}</span>
                    <flux:badge size="sm" color="zinc" rounded inset>{{ $this->permissionRules->count() }}</flux:badge>
                </span>
            </flux:radio>
            <flux:radio value="activity">
                <span class="flex items-center gap-1.5 font-bold text-xs">
                    <flux:icon icon="clock" class="size-4" />
                    <span>{{ __('Sync Audit Trail') }}</span>
                </span>
            </flux:radio>
            <flux:radio value="analytics">
                <span class="flex items-center gap-1.5 font-bold text-xs">
                    <flux:icon icon="chart-bar-square" class="size-4 text-emerald-600 dark:text-emerald-400" />
                    <span>{{ __('Analytics & Intelligence') }}</span>
                </span>
            </flux:radio>
            <flux:radio value="copilot">
                <span class="flex items-center gap-1.5 font-bold text-xs">
                    <flux:icon icon="sparkles" class="size-4 text-amber-500" />
                    <span>{{ __('Vault Copilot (RAG)') }}</span>
                </span>
            </flux:radio>
            <flux:radio value="settings">
                <span class="flex items-center gap-1.5 font-bold text-xs">
                    <flux:icon icon="cog" class="size-4" />
                    <span>{{ __('Settings') }}</span>
                </span>
            </flux:radio>
        </flux:radio.group>
    </div>

    <!-- TAB 1: MARKDOWN EDITOR (Strictly matching user's Pandocs inspiration image) -->
    @if ($activeTab === 'editor')
        <div
            x-data="markdownEditor({
                content: {{ Js::from($editorContent) }},
                activeFileId: {{ Js::from($this->activeFileId) }},
                viewMode: {{ Js::from($editorViewMode) }},
                canEdit: {{ Js::from($this->canEditActiveFile) }},
                initiallyDirty: {{ Js::from($editorIsDirty) }},
                unsavedPrompt: {{ Js::from(__('Discard unsaved changes to this note?')) }},
                vaultSlug: {{ Js::from($vault->slug) }},
                isEncrypted: {{ Js::from((bool) ($this->activeFile?->is_encrypted || $vault->is_e2ee)) }},
                encryptionIv: {{ Js::from($this->activeFile?->encryption_iv) }},
                encryptionTag: {{ Js::from($this->activeFile?->encryption_tag) }},
                vaultSalt: {{ Js::from($vault->e2ee_salt) }},
                vaultTestCipher: {{ Js::from($vault->e2ee_test_cipher) }},
                documentId: {{ Js::from($this->collaborationDocumentId) }},
                filePath: {{ Js::from($this->activeFile?->path) }},
                collabEnabled: {{ Js::from(
                    ! str_contains($this->activeFile?->path ?? '', '.conflict-')
                    && ! str_contains($this->activeFile?->path ?? '', '.sync-conflict-')
                    && $this->currentFileConflicts->isEmpty()
                ) }},
                user: {
                    id: {{ Js::from(auth()->id()) }},
                    name: {{ Js::from(auth()->user()?->name) }}
                }
            })"
            wire:key="vault-editor-{{ $this->activeFileId ?? 'empty' }}"
            data-vault-editor
            @keydown.window="handleWindowKeydown($event)"
            class="relative flex min-h-[680px] flex-col overflow-hidden rounded-[1.75rem] border border-[#303543] bg-[#12151d] text-zinc-100 shadow-2xl sm:min-h-[760px]"
        >
            <!-- ZERO-KNOWLEDGE E2EE UNLOCK OVERLAY -->
            <div
                x-show="!isUnlocked"
                x-cloak
                class="absolute inset-0 z-40 flex flex-col items-center justify-center bg-[#12151d]/95 backdrop-blur-md p-6 text-center"
            >
                <div class="max-w-md w-full rounded-2xl border border-emerald-500/30 bg-[#181A22] p-6 shadow-2xl space-y-4 text-left">
                    <div class="flex items-center gap-3">
                        <div class="size-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0">
                            <flux:icon icon="lock-closed" class="size-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-white text-base">{{ __('Encrypted Note Locked') }}</h3>
                            <p class="text-xs text-zinc-400">{{ __('Enter your vault passphrase to decrypt in-browser via WebCrypto.') }}</p>
                        </div>
                    </div>

                    <div x-show="unlockError" x-cloak class="p-2.5 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-xs flex items-center gap-2">
                        <flux:icon icon="exclamation-triangle" class="size-4 shrink-0" />
                        <span x-text="unlockError"></span>
                    </div>

                    <form @submit.prevent="unlockWithPassphrase()" class="space-y-3">
                        <flux:input
                            type="password"
                            x-model="passphraseInput"
                            placeholder="{{ __('Vault passphrase...') }}"
                            required
                            autofocus
                            class="w-full"
                        />
                        <flux:button
                            type="submit"
                            variant="primary"
                            color="emerald"
                            class="w-full justify-center"
                            ::disabled="isDerivingKey || !passphraseInput"
                        >
                            <span x-show="!isDerivingKey">{{ __('Unlock & Decrypt Note') }}</span>
                            <span x-show="isDerivingKey" class="flex items-center gap-2">
                                <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                                {{ __('Deriving AES-256 Key (100k rounds)...') }}
                            </span>
                        </flux:button>
                    </form>
                </div>
            </div>
            <!-- TOP HEADER (Pandocs style: Dropdown Title, Date, Collaborators, Save/Submit Button) -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#252836] bg-[#181A22] px-5 py-3">
                <div class="flex items-center gap-3 min-w-0">
                    <!-- Note Selector Dropdown -->
                    <flux:dropdown position="bottom" align="start">
                        <button type="button" class="group flex items-center gap-2 hover:bg-zinc-800/90 px-2.5 py-1.5 rounded-xl transition-all cursor-pointer text-left border border-transparent hover:border-zinc-700">
                            <flux:icon icon="document-text" class="size-4 text-zinc-300 group-hover:text-emerald-400 transition-colors" />
                            <span class="font-extrabold text-sm text-white tracking-tight truncate max-w-[180px] sm:max-w-xs">
                                {{ $this->editorTitle ?: __('Select or Create a Note') }}
                            </span>
                            <flux:icon icon="chevron-down" class="size-3 text-zinc-400 group-hover:text-white transition-colors" />
                        </button>
                        <flux:menu class="min-w-64 max-h-72 overflow-y-auto">
                            <flux:menu.heading class="text-[10px] font-bold uppercase tracking-wider text-zinc-400">{{ __('Vault Notes') }}</flux:menu.heading>
                            @foreach ($this->accessibleMarkdownFiles as $f)
                                <flux:menu.item @click="openFile({{ $f->id }})" class="cursor-pointer py-1.5">
                                    <div class="flex w-full items-center justify-between gap-3 text-left">
                                        <span class="min-w-0">
                                            <span class="block truncate text-xs {{ $this->activeFileId === $f->id ? 'font-bold text-emerald-400' : 'text-zinc-200' }}">{{ pathinfo($f->path, PATHINFO_FILENAME) }}</span>
                                            <span class="block truncate font-mono text-[9px] text-zinc-500">{{ $f->path }}</span>
                                        </span>
                                        <span class="font-mono text-[10px] text-zinc-500">v{{ $f->version }}</span>
                                    </div>
                                </flux:menu.item>
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>

                    @if ($this->activeFile)
                        <!-- Last Modified Date Pill -->
                        <div class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-zinc-800/90 px-3 py-1 text-[11px] text-zinc-300 border border-zinc-700/60 shadow-xs">
                            <flux:icon icon="calendar" class="size-3 text-zinc-400" />
                            <span>{{ $this->activeFile->updated_at?->format('M d, Y') }}</span>
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-2.5">
                    <!-- CRDT Multiplayer Collaborators Stack & Relay Indicator -->
                    <div class="hidden sm:flex items-center gap-2">
                        <!-- Real-time client awareness indicator -->
                        <div x-show="activePeers.length > 0" x-cloak class="flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-950/80 border border-emerald-500/50 text-emerald-300 text-[10px] font-semibold animate-pulse shadow-xs" title="{{ __('Active CRDT Multiplayer Peers') }}">
                            <span class="size-1.5 rounded-full bg-emerald-400"></span>
                            <span x-text="`${activePeers.length} live`"></span>
                        </div>
                        @if (! empty($collabActivePeers))
                            <div x-show="activePeers.length === 0" class="flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-950/70 border border-emerald-500/40 text-emerald-300 text-[10px] font-semibold animate-pulse" title="{{ __('Active CRDT Multiplayer Peers') }}">
                                <span class="size-1.5 rounded-full bg-emerald-400"></span>
                                <span>{{ count($collabActivePeers) }} {{ __('live') }}</span>
                            </div>
                            <div class="flex items-center -space-x-1.5 overflow-hidden">
                                @foreach ($collabActivePeers as $p)
                                    <span class="inline-flex size-6 items-center justify-center rounded-full ring-2 ring-[#181A22] text-[10px] font-bold text-white shadow-xs" style="background-color: {{ $p['color'] ?? '#10B981' }}" title="{{ $p['name'] }} (Live)">
                                        {{ strtoupper(substr($p['name'], 0, 2)) }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <div class="flex items-center -space-x-1.5 overflow-hidden" title="{{ __('Collaborators') }}">
                                @foreach ($this->teamMembers->take(3) as $m)
                                    <span class="inline-flex size-6 items-center justify-center rounded-full bg-[#0D3B29] ring-2 ring-[#181A22] text-[10px] font-bold text-emerald-200" title="{{ $m->name }}">
                                        {{ $m->initials() }}
                                    </span>
                                @endforeach
                                @if ($this->teamMembers->count() > 3)
                                    <span class="inline-flex size-6 items-center justify-center rounded-full bg-zinc-700 ring-2 ring-[#181A22] text-[9px] font-bold text-white">
                                        +{{ $this->teamMembers->count() - 3 }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if ($this->activeFile)
                        <!-- Share Button -->
                        <flux:dropdown position="bottom" align="end">
                            <button
                                type="button"
                                class="hidden sm:flex items-center gap-1.5 rounded-full border border-zinc-700/80 bg-zinc-800/80 px-3 py-1.5 text-xs font-semibold text-zinc-300 hover:bg-zinc-700 hover:text-white transition-colors cursor-pointer shadow-xs"
                            >
                                <flux:icon icon="share" class="size-3.5" />
                                <span>{{ __('Share') }}</span>
                            </button>
                            <flux:menu class="w-56">
                                <flux:menu.item icon="clipboard-document" @click="navigator.clipboard.writeText(window.location.href); $dispatch('toast', { text: '{{ __('Link copied to clipboard') }}', variant: 'success' })">
                                    {{ __('Copy Note URL') }}
                                </flux:menu.item>
                                <flux:menu.item icon="qr-code" wire:click="$set('activeTab', 'settings')">
                                    {{ __('Vault Settings') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>

                        <!-- Permission Indicator Pill -->
                        @if ($this->canEditActiveFile)
                            <span class="hidden md:inline-flex items-center gap-1.5 rounded-full bg-emerald-950/70 border border-emerald-500/40 px-2.5 py-1 text-[10px] font-bold text-emerald-300">
                                <span class="size-1.5 rounded-full bg-emerald-400"></span>
                                <span>{{ __('Editing enabled') }}</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-950/70 border border-amber-600/40 px-2.5 py-1 text-[10px] font-bold text-amber-400">
                                <flux:icon icon="lock-closed" class="size-3 text-amber-400" />
                                <span>{{ __('Read-Only') }}</span>
                            </span>
                        @endif

                        <!-- View Changes History Button -->
                        <button
                            type="button"
                            wire:click="showFileHistory({{ $this->activeFile->id }})"
                            class="flex items-center gap-1.5 rounded-full border border-zinc-700/80 bg-zinc-800/80 px-3 py-1.5 text-xs font-semibold text-zinc-300 hover:bg-zinc-700 hover:text-white transition-colors cursor-pointer shadow-xs"
                        >
                            <flux:icon icon="clock" class="size-3.5" />
                            <span>{{ __('View changes') }}</span>
                        </button>

                        <!-- Primary Save Button -->
                        <button
                            type="button"
                            @click="saveEditor()"
                            :disabled="!canEdit || !isDirty || isSaving"
                            class="flex items-center gap-2 rounded-full bg-[#0D3B29] px-4 py-1.5 text-xs font-bold text-white shadow-md transition-all hover:bg-[#0D3B29]/90 dark:bg-emerald-600 dark:hover:bg-emerald-500 active:scale-98 disabled:cursor-not-allowed disabled:border disabled:border-zinc-700 disabled:bg-zinc-800 disabled:text-zinc-500 disabled:shadow-none sm:px-5"
                            title="{{ $this->canEditActiveFile ? __('Save Note (Cmd+S / Ctrl+S)') : __('You have read-only access to this file') }}"
                        >
                            <flux:icon icon="arrow-up-tray" class="size-3.5" />
                            <span x-show="!isSaving">{{ __('Save changes') }}</span>
                            <span x-show="isSaving" x-cloak>{{ __('Saving…') }}</span>
                            <span x-show="isDirty && !isSaving" class="size-2 rounded-full bg-amber-300"></span>
                        </button>

                        <!-- Close Button -->
                        <button
                            type="button"
                            @click="closeEditor()"
                            class="size-7 flex items-center justify-center rounded-lg text-zinc-400 hover:bg-zinc-800 hover:text-white transition-colors cursor-pointer"
                            title="{{ __('Close Editor') }}"
                        >
                            <flux:icon icon="x-mark" class="size-4" />
                        </button>
                    @endif
                </div>
            </div>

            <!-- Conflict Banner -->
            @if ($this->activeFile && $this->currentFileConflicts->isNotEmpty())
                @php $activeConflict = $this->currentFileConflicts->first(); @endphp
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-500/30 bg-amber-950/40 px-5 py-2.5 text-amber-200">
                    <div class="flex items-center gap-2 text-xs">
                        <flux:icon icon="exclamation-triangle" class="size-4 text-amber-400 shrink-0" />
                        <span>
                            <strong class="font-semibold text-amber-300">{{ __('Concurrent Conflict Detected') }}:</strong>
                            <span class="font-mono text-[11px] text-amber-200/90">{{ $activeConflict->path }}</span>
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="openConflictSandbox('{{ $activeConflict->path }}')"
                            class="flex items-center gap-1.5 rounded-full bg-amber-500 px-3 py-1 text-xs font-bold text-zinc-950 hover:bg-amber-400 transition-colors shadow-xs cursor-pointer"
                        >
                            <flux:icon icon="arrows-right-left" class="size-3.5 text-zinc-950" />
                            <span>{{ __('Launch 3-Way Diff Sandbox') }}</span>
                        </button>
                    </div>
                </div>
            @endif

            <!-- FORMATTING TOOLBAR (Matching Pandocs) -->
            <div class="flex flex-col gap-2 border-b border-[#252836] bg-[#161821] px-3 py-2 sm:flex-row sm:items-center sm:justify-between sm:px-4">
                <div class="overflow-x-auto pb-1 sm:pb-0">
                <fieldset :disabled="!canEdit" class="flex min-w-max items-center gap-1 text-zinc-300 disabled:opacity-45">
                    <!-- Text Formatting -->
                    <button type="button" @click="insertFormat('**', '**', 'bold text')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 font-black text-xs" title="Bold (Ctrl+B)">B</button>
                    <button type="button" @click="insertFormat('*', '*', 'italic text')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 italic font-serif text-xs" title="Italic (Ctrl+I)">I</button>
                    <button type="button" @click="insertFormat('~~', '~~', 'strikethrough text')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 line-through text-xs" title="Strikethrough">S</button>

                    <span class="h-4 w-px bg-zinc-700 mx-1"></span>

                    <!-- Headings -->
                    <button type="button" @click="insertLinePrefix('# ')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 font-bold text-xs" title="H1 Heading">H1</button>
                    <button type="button" @click="insertLinePrefix('## ')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 font-bold text-xs" title="H2 Heading">H2</button>
                    <button type="button" @click="insertLinePrefix('### ')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 font-bold text-xs" title="H3 Heading">H3</button>

                    <span class="h-4 w-px bg-zinc-700 mx-1"></span>

                    <!-- Lists -->
                    <button type="button" @click="insertLinePrefix('- ')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Bullet List">•≡</button>
                    <button type="button" @click="insertLinePrefix('1. ')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs font-mono" title="Numbered List">1≡</button>
                    <button type="button" @click="insertLinePrefix('- [ ] ')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Task List">☑</button>

                    <span class="h-4 w-px bg-zinc-700 mx-1"></span>

                    <!-- Insert Links & Media -->
                    <button type="button" @click="insertFormat('[', '](https://)', 'Link Title')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Insert Link">🔗</button>
                    <button type="button" @click="insertFormat('![', '](image-url.png)', 'Alt Text')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Insert Image">🖼️</button>
                    <button type="button" @click="insertFormat('[📎 ', '](attachment.pdf)', 'Document')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Attach File">📎</button>
                    <button type="button" @click="insertFormat('[[', ']]', 'Wiki Note Name')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs font-bold text-emerald-400" title="Wiki Link [[Note]]">[[ ]]</button>

                    <span class="h-4 w-px bg-zinc-700 mx-1"></span>

                    <!-- Code & Block elements -->
                    <button type="button" @click="insertFormat('`', '`', 'code')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs font-mono text-cyan-400" title="Inline Code">&lt;/&gt;</button>
                    <button type="button" @click="insertFormat('```javascript\n', '\n```', '// your code here')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs font-mono" title="Code Block">{ }</button>
                    <button type="button" @click="insertTable()" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Insert Table">⊞</button>
                    <button type="button" @click="insertFormat('> [!TIP]\n> ', '', 'Add a useful tip')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs text-emerald-400" title="Tip Callout Box">💡</button>
                    <button type="button" @click="insertFormat('\n---\n')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Horizontal Rule">—</button>
                </fieldset>
                </div>

                <!-- View Mode Switcher -->
                <div class="flex items-center rounded-lg bg-zinc-900/90 p-0.5 border border-zinc-800">
                    <button
                        type="button"
                        @click="viewMode = 'split'"
                        :class="viewMode === 'split' ? 'bg-zinc-700 text-white font-bold' : 'text-zinc-400 hover:text-white'"
                        class="px-2.5 py-1 text-[11px] rounded transition-colors cursor-pointer"
                    >
                        {{ __('Split') }}
                    </button>
                    <button
                        type="button"
                        @click="viewMode = 'source'"
                        :class="viewMode === 'source' ? 'bg-zinc-700 text-white font-bold' : 'text-zinc-400 hover:text-white'"
                        class="px-2.5 py-1 text-[11px] rounded transition-colors cursor-pointer"
                    >
                        {{ __('Source') }}
                    </button>
                    <button
                        type="button"
                        @click="viewMode = 'preview'"
                        :class="viewMode === 'preview' ? 'bg-zinc-700 text-white font-bold' : 'text-zinc-400 hover:text-white'"
                        class="px-2.5 py-1 text-[11px] rounded transition-colors cursor-pointer"
                    >
                        {{ __('Preview') }}
                    </button>
                </div>
            </div>

            <!-- READ ONLY NOTICE BANNER -->
            @if (! $this->canEditActiveFile && $this->activeFile)
                <div class="flex items-center gap-2 bg-amber-950/40 border-b border-amber-800/40 px-5 py-2 text-xs text-amber-300">
                    <flux:icon icon="lock-closed" class="size-4 text-amber-400 shrink-0" />
                    <span>{{ __('You have Read-Only permissions for this note. You may view and navigate the content, but changes cannot be saved.') }}</span>
                </div>
            @endif

            <!-- MAIN WORKSPACE -->
            <div class="flex flex-1 min-h-[580px] overflow-hidden relative">
                <!-- FLOATING EXPAND SIDEBAR BUTTON (Shown when outline sidebar is collapsed) -->
                <button
                    type="button"
                    x-show="!sidebarOpen"
                    @click="sidebarOpen = true"
                    class="absolute top-3 left-3 z-30 flex items-center gap-1.5 rounded-xl bg-zinc-900/95 border border-zinc-700 px-2.5 py-1.5 text-xs text-zinc-300 hover:text-white hover:border-purple-500 shadow-xl backdrop-blur transition-all cursor-pointer"
                    title="{{ __('Expand Outline & Notes') }}"
                >
                    <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.21 5.23a.75.75 0 011.06-.02l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.04-1.08L8.168 10 4.23 6.29a.75.75 0 01-.02-1.06zm6 0a.75.75 0 011.06-.02l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.04-1.08L14.168 10l-3.938-3.71a.75.75 0 01-.02-1.06z" clip-rule="evenodd" />
                    </svg>
                    <span class="font-medium text-[11px]">{{ __('Outline') }}</span>
                </button>

                <!-- LEFT COLLAPSIBLE SIDEBAR: Back to Docs, Search, Outline, Notes -->
                <div
                    x-show="sidebarOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="-translate-x-full opacity-0"
                    x-transition:enter-end="translate-x-0 opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="translate-x-0 opacity-100"
                    x-transition:leave-end="-translate-x-full opacity-0"
                    class="w-64 shrink-0 border-r border-[#252836] bg-[#12141C] flex flex-col justify-between hidden md:flex"
                >
                    <div class="p-3.5 space-y-3.5 overflow-y-auto max-h-[600px]">
                        <!-- Back to All Documents -->
                        <button
                            type="button"
                            wire:click="$set('activeTab', 'files')"
                            class="flex items-center gap-2 text-xs font-semibold text-zinc-400 hover:text-white transition-colors cursor-pointer w-full text-left"
                        >
                            <span>←</span>
                            <span>{{ __('Back to All Documents') }}</span>
                        </button>

                        <!-- Search Notes Input -->
                        <div class="relative">
                            <flux:icon icon="magnifying-glass" class="size-3.5 text-zinc-500 absolute left-2.5 top-2.5" />
                            <input
                                x-ref="editorSearch"
                                wire:model.live.debounce.250ms="editorSearch"
                                type="text"
                                placeholder="{{ __('Search...') }}"
                                class="w-full rounded-xl bg-zinc-900/90 border border-zinc-800 pl-8 pr-8 py-1.5 text-xs text-white placeholder:text-zinc-500 focus:outline-none focus:border-purple-500"
                            />
                            <span class="absolute right-2 top-2 text-[10px] font-mono text-zinc-500 bg-zinc-800 px-1 rounded">⌘K</span>
                        </div>

                        <!-- LIVE DOCUMENT OUTLINE (Pandocs signature headings navigator) -->
                        <div>
                            <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-zinc-500 mb-2">
                                <span>{{ __('Document Outline') }}</span>
                                <span class="text-[9px] font-mono" x-text="headings.length"></span>
                            </div>
                            <div class="space-y-0.5">
                                <template x-for="(h, idx) in headings" :key="idx">
                                    <button
                                        type="button"
                                        @click="scrollToHeading(h)"
                                        :class="activeHeading === h.title ? 'bg-purple-950/40 text-purple-300 font-bold border-l-2 border-purple-500 pl-2' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/40 pl-2.5 border-l-2 border-transparent'"
                                        class="block w-full text-left truncate text-xs transition-colors py-1 rounded-r-md cursor-pointer"
                                    >
                                        <span class="text-zinc-600 mr-1 font-mono text-[10px]" x-text="'#'.repeat(h.level)"></span>
                                        <span x-text="h.title"></span>
                                    </button>
                                </template>
                                <div x-show="headings.length === 0" class="text-[11px] text-zinc-600 italic py-1 pl-2">
                                    {{ __('Add headings (# H1, ## H2) to build outline') }}
                                </div>
                            </div>
                        </div>

                        <!-- VAULT NOTES LIST -->
                        <div class="pt-3 border-t border-[#252836]">
                            <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-zinc-500 mb-2">
                                <span>{{ __('Vault Notes') }}</span>
                                <span class="text-[10px]">{{ $this->filteredEditorNotes->count() }}</span>
                            </div>
                            <div class="space-y-1">
                                @forelse ($this->filteredEditorNotes as $f)
                                    <button
                                        type="button"
                                        @click="openFile({{ $f->id }})"
                                        class="{{ $this->activeFileId === $f->id ? 'bg-[#0D3B29]/40 text-emerald-300 font-bold border-emerald-600/40' : 'text-zinc-400 hover:bg-zinc-800/50 hover:text-white border-transparent' }} flex items-center justify-between w-full rounded-lg px-2.5 py-1.5 text-xs text-left border transition-all cursor-pointer"
                                    >
                                        <div class="flex items-center gap-2 truncate">
                                            <flux:icon icon="document-text" class="size-3.5 shrink-0" />
                                            <span class="truncate">{{ pathinfo($f->path, PATHINFO_FILENAME) }}</span>
                                        </div>
                                        <span class="text-[10px] text-zinc-600 shrink-0 font-mono">v{{ $f->version }}</span>
                                    </button>
                                @empty
                                    <div class="py-3 text-center text-xs text-zinc-500">
                                        {{ __('No notes found.') }}
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Bar: Collapse Button + New Note Action -->
                    <div class="p-2.5 border-t border-[#252836] bg-[#0E1015] flex items-center justify-between gap-2">
                        <!-- Collapse Sidebar Toggle (Matching ⇤ in screenshot) -->
                        <button
                            type="button"
                            @click="sidebarOpen = false"
                            class="p-1.5 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800 transition-colors cursor-pointer"
                            title="{{ __('Collapse Outline Sidebar') }}"
                        >
                            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M15.79 14.77a.75.75 0 01-1.06.02l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 111.04 1.08L11.832 10l3.938 3.71a.75.75 0 01.02 1.06zm-6 0a.75.75 0 01-1.06.02l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 111.04 1.08L5.832 10l3.938 3.71a.75.75 0 01.02 1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <flux:modal.trigger name="new-note-modal">
                            <button type="button" class="flex-1 flex items-center justify-center gap-1.5 rounded-xl bg-zinc-800 hover:bg-zinc-700 py-1.5 px-3 text-xs font-bold text-white transition-colors cursor-pointer">
                                <flux:icon icon="plus" class="size-3" />
                                <span>{{ __('New Note') }}</span>
                            </button>
                        </flux:modal.trigger>
                    </div>
                </div>

                <!-- SPLIT EDITOR CONTENT AREA -->
                <div class="flex flex-1 overflow-hidden relative">
                    <!-- LEFT PANE: SOURCE CODE EDITOR -->
                    <div
                        x-show="viewMode === 'split' || viewMode === 'source'"
                        :class="viewMode === 'split' ? 'w-full lg:w-1/2' : 'w-full'"
                        class="flex flex-1 border-r border-[#252836] bg-[#0E1015] overflow-hidden"
                    >
                        <!-- Code Minimap Strip on Far Left (Pandocs design with authentic syntax-colored bars) -->
                        <div class="w-8 shrink-0 border-r border-zinc-800/60 bg-[#0B0D12] py-3 px-1 flex flex-col gap-0.5 overflow-hidden opacity-80">
                            <template x-for="(type, idx) in lineTypes" :key="idx">
                                <div
                                    :class="{
                                        'bg-rose-500/90 h-[3px]': type === 'h1',
                                        'bg-emerald-400/90 h-[3px]': type === 'h2',
                                        'bg-teal-400/80 h-[2.5px]': type === 'h3',
                                        'bg-emerald-400/90 h-[2px]': type === 'code',
                                        'bg-purple-400/90 h-[2.5px]': type === 'tip',
                                        'bg-amber-400/70 h-[2px]': type === 'list',
                                        'bg-zinc-500/40 h-[2px]': type === 'text',
                                        'bg-transparent h-[2px]': type === 'empty',
                                        'bg-zinc-600/60 h-[1px]': type === 'hr',
                                        'bg-zinc-600/40 h-[2px]': type === 'quote'
                                    }"
                                    :style="`width: ${type === 'h1' ? '90%' : (type === 'h2' ? '75%' : (type === 'empty' ? '0%' : Math.max(30, (idx * 23) % 95) + '%'))}; border-radius: 1px; margin-bottom: 2px;`"
                                ></div>
                            </template>
                        </div>

                        <!-- Line Numbers Gutter (Fallback when CodeMirror not loaded) -->
                        <div x-show="!editorInstance" class="w-10 shrink-0 py-3 text-right pr-2 text-zinc-600 font-mono text-xs select-none border-r border-zinc-800/60 bg-[#0C0E13]">
                            <template x-for="n in lineCount" :key="n">
                                <div class="leading-relaxed text-[11px]" x-text="n"></div>
                            </template>
                        </div>

                        <!-- CodeMirror 6 Root Host Surface with Textarea Fallback -->
                        <div class="flex-1 relative overflow-hidden bg-[#0E1015] flex flex-col">
                            <div
                                x-ref="editorContainer"
                                class="cm-synkk-host w-full h-full min-h-[580px] overflow-auto focus:outline-none"
                            ></div>
                            <textarea
                                x-ref="editorTextarea"
                                x-show="!editorInstance"
                                x-model="content"
                                @keydown="handleEditorKeydown($event)"
                                @if (! $this->canEditActiveFile) readonly @endif
                                placeholder="{{ __('Write your note in Markdown...') }}"
                                class="w-full h-full min-h-[580px] bg-transparent p-4 font-mono text-xs sm:text-sm leading-relaxed text-zinc-100 placeholder:text-zinc-600 focus:outline-none resize-none selection:bg-purple-900/60"
                                spellcheck="false"
                            >{{ $editorContent }}</textarea>
                        </div>
                    </div>

                    <!-- FLOATING QUICK-INSERT GUTTER (Iconic center strip in Pandocs) -->
                    <div
                        x-show="canEdit && viewMode === 'split'"
                        class="absolute left-1/2 -translate-x-1/2 top-16 z-20 hidden lg:flex flex-col items-center gap-2"
                    >
                        <!-- Purple Toggle Button -->
                        <button
                            type="button"
                            @click="quickInsertOpen = !quickInsertOpen"
                            class="size-6 rounded-full bg-[#7C3AED] hover:bg-[#6D28D9] text-white flex items-center justify-center shadow-lg transition-transform cursor-pointer"
                            :class="quickInsertOpen ? 'rotate-45' : ''"
                            title="{{ __('Toggle quick insert tools') }}"
                        >
                            <flux:icon icon="plus" class="size-3" />
                        </button>

                        <div
                            x-show="quickInsertOpen"
                            x-transition
                            class="flex flex-col items-center gap-1.5 rounded-2xl border border-zinc-700/80 bg-zinc-900/95 p-1.5 shadow-2xl backdrop-blur"
                        >
                            <button type="button" @click="insertFormat('[', '](https://)', 'Link Title')" class="size-7 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800 flex items-center justify-center text-xs" title="Link">🔗</button>
                            <button type="button" @click="insertFormat('![', '](image.png)', 'Alt')" class="size-7 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800 flex items-center justify-center text-xs" title="Image">🖼️</button>
                            <button type="button" @click="insertFormat('[📎 ', '](file.pdf)', 'File')" class="size-7 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800 flex items-center justify-center text-xs" title="Attachment">📎</button>
                            <button type="button" @click="insertFormat('`', '`', 'code')" class="size-7 rounded-lg text-cyan-400 hover:bg-zinc-800 flex items-center justify-center text-xs font-mono" title="Code">&lt;&gt;</button>
                            <button type="button" @click="insertTable()" class="size-7 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800 flex items-center justify-center text-xs" title="Table">⊞</button>
                            <button type="button" @click="insertFormat('> [!TIP]\n> ', '', 'Add a useful tip')" class="size-7 rounded-lg text-emerald-400 hover:bg-zinc-800 flex items-center justify-center text-xs" title="Tip Box">💡</button>
                        </div>
                    </div>

                    <!-- RIGHT PANE: LIVE RENDERED PREVIEW (Pandocs style typography & Callouts) -->
                    <div
                        x-show="viewMode === 'split' || viewMode === 'preview'"
                        x-ref="previewPane"
                        :class="viewMode === 'split' ? 'hidden w-full lg:block lg:w-1/2' : 'w-full'"
                        class="flex-1 overflow-y-auto bg-[#12131A] p-6 lg:p-10"
                    >
                        <!-- Active block focus indicator line (matching Pandocs purple accent line) -->
                        <div class="h-0.5 w-full bg-gradient-to-r from-purple-500/80 via-purple-500/30 to-transparent mb-6"></div>

                        <div class="synkk-markdown-preview max-w-none text-zinc-200" x-html="previewHtml">
                            {!! $this->renderedPreviewHtml !!}
                        </div>
                    </div>
                </div>
            </div>

            <!-- BOTTOM STATUS BAR (Matching Pandocs author avatar & date) -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#252836] bg-[#101218] px-5 py-2.5 text-[11px] text-zinc-400">
                <div class="flex items-center gap-3">
                    @if ($this->activeFile)
                        <div class="flex items-center gap-2">
                            <flux:icon icon="clock" class="size-3.5 text-zinc-500" />
                            <span>{{ __('Last updated on') }} {{ $this->activeFile->updated_at?->format('M d, Y') }} {{ __('by') }}</span>
                            <div class="flex items-center gap-1.5">
                                <span class="inline-flex size-4 items-center justify-center rounded-full bg-purple-900 text-[9px] font-bold text-purple-200">
                                    {{ substr($this->activeFile->lastModifier?->name ?? 'S', 0, 1) }}
                                </span>
                                <strong class="text-white font-semibold">{{ $this->activeFile->lastModifier?->name ?? 'Sync' }}</strong>
                            </div>
                        </div>
                    @else
                        <span>{{ __('No active file selected') }}</span>
                    @endif
                </div>

                <div class="flex items-center gap-4 font-mono text-[10px]">
                    <span x-text="`${lineCount} {{ __('lines') }}`"></span>
                    <span class="text-zinc-600">•</span>
                    <span x-text="`${wordCount} {{ __('words') }}`"></span>
                    <span class="text-zinc-600">•</span>
                    <span x-text="`${charCount} {{ __('characters') }}`"></span>
                    <span class="text-zinc-600">•</span>
                    <span x-show="!isDirty" class="text-emerald-400 flex items-center gap-1">✓ {{ __('Saved') }}</span>
                    <span x-show="isDirty" class="text-amber-400 flex items-center gap-1">● {{ __('Unsaved') }}</span>
                    <span x-show="saveFailed" x-cloak class="text-rose-400 flex items-center gap-1">! {{ __('Save failed') }}</span>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 2: VAULT GRAPH VIEW (Interactive Obsidian-style Force-Directed Network Graph) -->
    @if ($activeTab === 'graph')
        <div
            x-data="vaultGraph({
                nodes: {{ Js::from($this->graphData['nodes']) }},
                edges: {{ Js::from($this->graphData['edges']) }},
                theme: 'emerald'
            })"
            wire:key="vault-graph-{{ $vault->id }}"
            class="w-full overflow-hidden rounded-[1.75rem] border border-zinc-800 bg-[#0c0f12] shadow-2xl shadow-black/20"
            aria-labelledby="vault-graph-title"
        >
            <header class="border-b border-white/10 bg-gradient-to-r from-emerald-500/[0.08] via-transparent to-cyan-500/[0.06] px-5 py-5 sm:px-7 sm:py-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <div class="mb-2 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-400">
                            <span class="size-1.5 rounded-full bg-emerald-400"></span>
                            {{ __('Vault knowledge map') }}
                        </div>
                        <h2 id="vault-graph-title" class="text-xl font-semibold tracking-tight text-white sm:text-2xl">
                            {{ __('Explore how your notes connect') }}
                        </h2>
                        <p id="vault-graph-instructions" class="mt-2 max-w-xl text-sm leading-6 text-zinc-400">
                            {{ __('Drag nodes to reorganize the map, scroll to zoom, or choose any note from the accessible index to open it in the Markdown Editor.') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-300" aria-live="polite">
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-black/20 px-3 py-1.5">
                            <span class="size-1.5 rounded-full bg-emerald-400"></span>
                            <span x-text="`${nodes.length} {{ __('notes') }}`"></span>
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-black/20 px-3 py-1.5">
                            <flux:icon icon="link" class="size-3.5 text-cyan-400" />
                            <span x-text="`${edges.length} {{ __('connections') }}`"></span>
                        </span>
                    </div>
                </div>
            </header>

            <div class="grid xl:h-[660px] xl:grid-cols-[minmax(0,1fr)_19rem]">
                <div class="relative min-h-[500px] overflow-hidden bg-[radial-gradient(circle_at_center,rgba(16,185,129,0.08),transparent_55%)] sm:min-h-[600px] xl:h-full xl:min-h-0">
                    <canvas
                        x-ref="graphCanvas"
                        role="img"
                        aria-label="{{ __('Interactive vault graph showing notes as nodes and wiki links as connections.') }}"
                        aria-describedby="vault-graph-instructions"
                        class="absolute inset-0 size-full cursor-grab active:cursor-grabbing"
                    >
                        {{ __('Your browser cannot render the interactive vault graph. Use the accessible note index beside the graph to open a note.') }}
                    </canvas>

                    <!-- Search and graph status -->
                    <div class="absolute inset-x-3 top-3 z-20 flex flex-col gap-2 sm:inset-x-4 sm:top-4 sm:flex-row sm:items-center sm:justify-between">
                        <label class="flex min-h-10 items-center rounded-xl border border-white/10 bg-zinc-950/90 px-3 shadow-xl shadow-black/20 backdrop-blur-md sm:w-64">
                            <span class="sr-only">{{ __('Filter graph nodes') }}</span>
                            <flux:icon icon="magnifying-glass" class="mr-2 size-4 shrink-0 text-zinc-400" />
                            <input
                                x-model="search"
                                type="search"
                                autocomplete="off"
                                placeholder="{{ __('Filter graph nodes...') }}"
                                class="min-w-0 flex-1 bg-transparent text-xs text-white placeholder:text-zinc-500 focus:outline-none"
                            />
                            <button
                                x-show="search"
                                x-cloak
                                @click="search = ''"
                                type="button"
                                class="ml-2 rounded-md px-1.5 py-0.5 text-sm text-zinc-400 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
                                aria-label="{{ __('Clear graph filter') }}"
                            >
                                ×
                            </button>
                        </label>

                        <div class="self-start rounded-xl border border-white/10 bg-zinc-950/85 px-3 py-2 text-[11px] text-zinc-400 shadow-lg backdrop-blur-md sm:self-auto">
                            {{ __('Select a node to edit its note') }}
                        </div>
                    </div>

                    <!-- Empty states remain useful even when canvas cannot draw a match. -->
                    <div
                        x-show="nodes.length === 0"
                        x-cloak
                        class="absolute inset-0 z-10 grid place-items-center px-6 text-center"
                    >
                        <div class="max-w-sm rounded-2xl border border-white/10 bg-zinc-950/90 p-6 shadow-2xl backdrop-blur-md">
                            <div class="mx-auto grid size-11 place-items-center rounded-full bg-emerald-400/10 text-emerald-400">
                                <flux:icon icon="document-plus" class="size-5" />
                            </div>
                            <h3 class="mt-3 font-semibold text-white">{{ __('Your graph is ready for its first connection') }}</h3>
                            <p class="mt-1 text-xs leading-5 text-zinc-400">{{ __('Add Markdown notes and connect them with wiki links to build this map.') }}</p>
                        </div>
                    </div>

                    <!-- Floating hover tooltip -->
                    <div
                        x-show="hoveredNode"
                        x-cloak
                        :style="`left: ${tooltipX + 15}px; top: ${tooltipY + 15}px;`"
                        class="pointer-events-none absolute z-30 hidden max-w-xs rounded-2xl border border-zinc-700/80 bg-zinc-950/95 p-3.5 text-xs text-white shadow-2xl backdrop-blur-md sm:block"
                    >
                        <div class="text-sm font-extrabold text-emerald-400" x-text="hoveredNode?.name"></div>
                        <div class="mt-0.5 truncate font-mono text-[10px] text-zinc-400" x-text="hoveredNode?.path"></div>
                        <div class="mt-2.5 flex items-center gap-3 text-[11px] text-zinc-300">
                            <span>{{ __('Connections') }}: <strong class="font-bold text-white" x-text="hoveredNode?.linksCount"></strong></span>
                            <span>{{ __('Revision') }}: <strong class="font-bold text-white" x-text="`v${hoveredNode?.version}`"></strong></span>
                        </div>
                        <div class="mt-1.5 text-[10px] font-medium text-emerald-400/90">
                            {{ __('Select to open in Markdown Editor ↗') }}
                        </div>
                    </div>

                    <!-- Navigation and zoom controls -->
                    <div class="absolute bottom-4 right-4 z-20 flex items-center gap-1 rounded-xl border border-white/10 bg-zinc-950/90 p-1.5 shadow-xl shadow-black/20 backdrop-blur-md">
                        <button
                            @click="zoomIn()"
                            type="button"
                            class="rounded-lg p-2 text-zinc-300 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
                            title="{{ __('Zoom In') }}"
                            aria-label="{{ __('Zoom in graph') }}"
                        >
                            <flux:icon icon="plus" class="size-4" />
                        </button>
                        <button
                            @click="zoomOut()"
                            type="button"
                            class="rounded-lg p-2 text-zinc-300 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
                            title="{{ __('Zoom Out') }}"
                            aria-label="{{ __('Zoom out graph') }}"
                        >
                            <flux:icon icon="minus" class="size-4" />
                        </button>
                        <button
                            @click="resetView()"
                            type="button"
                            class="rounded-lg p-2 text-zinc-300 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
                            title="{{ __('Reset View') }}"
                            aria-label="{{ __('Reset graph view') }}"
                        >
                            <flux:icon icon="arrow-path" class="size-4" />
                        </button>
                    </div>
                </div>

                <!-- Keyboard and assistive-technology equivalent for the canvas. -->
                <aside class="flex min-h-0 flex-col border-t border-white/10 bg-zinc-950/60 xl:h-full xl:border-l xl:border-t-0" aria-labelledby="vault-graph-index-title">
                    <div class="border-b border-white/10 px-5 py-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-zinc-500">{{ __('Accessible note index') }}</p>
                                <h3 id="vault-graph-index-title" class="mt-1 text-sm font-semibold text-white">{{ __('Open a connected note') }}</h3>
                            </div>
                            <kbd class="hidden rounded-md border border-white/10 bg-white/5 px-2 py-1 font-mono text-[10px] text-zinc-400 sm:inline">Enter</kbd>
                        </div>
                    </div>

                    <div class="max-h-72 min-h-0 overflow-y-auto p-3 xl:max-h-none xl:flex-1" role="region" aria-label="{{ __('Graph notes') }}" tabindex="0">
                        <ul class="space-y-1" role="list">
                            <template
                                x-for="node in nodes.filter((candidate) => !search || (candidate.name || '').toLowerCase().includes(search.toLowerCase()))"
                                :key="node.id"
                            >
                                <li>
                                    <button
                                        type="button"
                                        @click="selectNode(node)"
                                        @keydown="handleNodeKeydown($event, node)"
                                        class="group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-white/[0.06] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
                                        :aria-label="`{{ __('Open') }} ${node.name} {{ __('in the Markdown Editor') }}`"
                                    >
                                        <span class="relative flex size-8 shrink-0 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] text-zinc-400 transition group-hover:border-emerald-400/30 group-hover:bg-emerald-400/10 group-hover:text-emerald-400">
                                            <flux:icon icon="document-text" class="size-4" />
                                            <span
                                                class="absolute -right-1 -top-1 size-2 rounded-full border-2 border-zinc-950"
                                                :class="(node.linksCount || 0) > 0 ? 'bg-emerald-400' : 'bg-zinc-600'"
                                            ></span>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-xs font-medium text-zinc-200 group-hover:text-white" x-text="node.name"></span>
                                            <span class="mt-0.5 block truncate font-mono text-[10px] text-zinc-500" x-text="node.path"></span>
                                        </span>
                                        <span class="shrink-0 rounded-full bg-white/5 px-2 py-1 text-[9px] font-medium text-zinc-500" x-text="`${node.linksCount || 0} {{ __('links') }}`"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>

                        <div
                            x-show="nodes.length > 0 && nodes.filter((candidate) => !search || (candidate.name || '').toLowerCase().includes(search.toLowerCase())).length === 0"
                            x-cloak
                            class="px-4 py-8 text-center"
                            role="status"
                        >
                            <flux:icon icon="magnifying-glass" class="mx-auto size-5 text-zinc-600" />
                            <p class="mt-2 text-xs font-medium text-zinc-300">{{ __('No notes match this filter') }}</p>
                            <button @click="search = ''" type="button" class="mt-2 text-[11px] font-medium text-emerald-400 hover:text-emerald-300 focus-visible:outline-none focus-visible:underline">
                                {{ __('Clear filter') }}
                            </button>
                        </div>
                    </div>

                    <div class="border-t border-white/10 px-5 py-4 text-[11px] leading-5 text-zinc-500">
                        {{ __('The list mirrors the visual graph so every note remains reachable without a mouse or canvas support.') }}
                    </div>
                </aside>
            </div>
        </div>
    @endif

    <!-- TAB 3: Permissions Matrix (Granular Folder/File rules) -->
    @if ($activeTab === 'permissions')
        <div class="space-y-4">
            @if ($vault->team->plan === 'free')
                <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-900/40 dark:bg-amber-950/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300 shrink-0">
                            <flux:icon icon="lock-closed" class="size-5" />
                        </div>
                        <div>
                            <div class="font-bold text-xs text-amber-900 dark:text-amber-200">{{ __('Granular Path ACLs (Pro Lifetime & Cloud Feature)') }}</div>
                            <div class="text-[11px] text-amber-700 dark:text-amber-400 mt-0.5">{{ __('Upgrade your workspace to set custom folder and note permissions, hide sensitive subdirectories, and create read-only paths.') }}</div>
                        </div>
                    </div>
                    <a href="{{ route('home') }}#pricing" target="_blank" class="shrink-0 rounded-full bg-[#0D3B29] px-4 py-1.5 text-xs font-bold text-white hover:bg-[#0D3B29]/90 dark:bg-emerald-600">
                        {{ __('Upgrade Workspace →') }}
                    </a>
                </div>
            @endif

            <!-- How it works Callout -->
            <flux:card variant="soft" class="border-emerald-200/50 bg-emerald-50/20 dark:border-emerald-900/30 dark:bg-emerald-950/20">
                <div class="flex items-start gap-3">
                    <flux:icon icon="information-circle" class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
                    <div class="text-xs leading-relaxed text-zinc-600 dark:text-zinc-300">
                        <strong class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Hierarchical Folder & File Access Rules:') }}</strong>
                        {{ __('Rules cascade downwards into subfolders and notes unless a more specific child rule exists. Specific member rules override whole-team defaults. When a path is set to "Hidden", it is omitted from that member\'s sync manifest entirely and will never touch their device.') }}
                    </div>
                </div>
            </flux:card>

            <!-- Table of Rules -->
            <flux:card class="p-0 overflow-hidden">
                @if ($this->permissionRules->isEmpty())
                    <div class="p-12 text-center">
                        <div class="flex size-12 mx-auto items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-400">
                            <flux:icon icon="shield-check" class="size-6" />
                        </div>
                        <flux:heading size="md" class="mt-4">{{ __('No Custom Path Rules Configured') }}</flux:heading>
                        <flux:subheading class="max-w-md mx-auto text-xs mt-1">{{ __('All notes in this vault follow the default permission (:permission). Add granular rules to lock down sensitive folders or grant write access.', ['permission' => $vault->default_permission]) }}</flux:subheading>
                        <flux:modal.trigger name="add-path-permission">
                            <flux:button variant="primary" size="sm" icon="plus" class="mt-4">
                                {{ __('Add Path Rule') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column class="py-3.5 text-xs font-semibold uppercase tracking-wider text-zinc-400"><div class="ps-6 sm:ps-8 pe-2">{{ __('Path / Folder') }}</div></flux:table.column>
                            <flux:table.column class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Target Scope') }}</flux:table.column>
                            <flux:table.column class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Access Level') }}</flux:table.column>
                            <flux:table.column align="end" class="py-3.5 text-xs font-semibold uppercase tracking-wider text-zinc-400"><div class="ps-2 pe-6 sm:pe-8">{{ __('Actions') }}</div></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->permissionRules as $rule)
                                <flux:table.row :key="$rule->id" class="hover:bg-zinc-50/50 dark:hover:bg-white/5 transition-colors">
                                    <flux:table.cell class="py-3.5 font-mono text-xs font-semibold">
                                        <div class="flex items-center gap-2.5 ps-6 sm:ps-8 pe-2">
                                            @if ($rule->is_folder)
                                                <flux:icon icon="folder" class="size-4 text-amber-500 shrink-0" />
                                            @else
                                                <flux:icon icon="document-text" class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                            @endif
                                            <span class="text-zinc-900 dark:text-zinc-100">{{ $rule->path }}</span>
                                            @if ($rule->is_folder)
                                                <span class="text-[10px] text-zinc-400 font-sans font-normal">({{ __('recursive') }})</span>
                                            @endif
                                        </div>
                                    </flux:table.cell>

                                    <flux:table.cell class="py-3.5 px-4">
                                        @if ($rule->user)
                                            <div class="flex items-center gap-2">
                                                <flux:avatar :name="$rule->user->name" size="xs" />
                                                <span class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">{{ $rule->user->name }}</span>
                                            </div>
                                        @else
                                            <flux:badge color="zinc" size="sm" class="font-medium">{{ __('All Team Members') }}</flux:badge>
                                        @endif
                                    </flux:table.cell>

                                    <flux:table.cell class="py-3.5 px-4">
                                        @if ($rule->permission === 'read_write')
                                            <flux:badge color="emerald" size="sm" icon="pencil-square" class="font-medium">{{ __('Read & Write (Full Sync)') }}</flux:badge>
                                        @elseif ($rule->permission === 'read_only')
                                            <flux:badge color="amber" size="sm" icon="eye" class="font-medium">{{ __('Read-Only (Download Only)') }}</flux:badge>
                                        @else
                                            <flux:badge color="red" size="sm" icon="eye-slash" class="font-medium">{{ __('Hidden (No Access / Omitted)') }}</flux:badge>
                                        @endif
                                    </flux:table.cell>

                                    <flux:table.cell align="end" class="py-3.5">
                                        <div class="flex items-center justify-end ps-2 pe-6 sm:pe-8">
                                            <flux:button
                                                variant="subtle"
                                                size="sm"
                                                icon="trash"
                                                wire:click="deletePermission({{ $rule->id }})"
                                                wire:confirm="Remove this path permission rule?"
                                                class="text-red-500 hover:text-red-600"
                                            />
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>
        </div>
    @endif

    <!-- TAB 2: Files Explorer (Modern Obsidian/macOS Finder-Grade File Browser) -->
    @if ($activeTab === 'files')
        <div class="space-y-4">
            <!-- File Explorer Header & Action Bar -->
            <div class="flex flex-col gap-4 rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-xs backdrop-blur-md dark:border-zinc-800/80 dark:bg-zinc-900/80 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <flux:icon icon="folder-open" class="size-5 text-emerald-600 dark:text-emerald-400" />
                        <h3 class="text-base font-bold text-slate-900 dark:text-white tracking-tight">{{ __('Vault File Browser') }}</h3>
                        <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-semibold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                            {{ $vault->name }} /
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">
                        {{ __(':count total files stored · :size total consumption', ['count' => $this->accessibleFiles->count(), 'size' => $this->totalSizeFormatted]) }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <flux:modal.trigger name="new-note-modal">
                        <flux:button variant="primary" size="sm" icon="plus" class="!bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90 font-semibold cursor-pointer">
                            {{ __('New Note') }}
                        </flux:button>
                    </flux:modal.trigger>
                    <flux:button wire:click="exportVaultZip" variant="subtle" size="sm" icon="arrow-down-tray" class="cursor-pointer">
                        {{ __('Export (.zip)') }}
                    </flux:button>
                </div>
            </div>

            <!-- Controls & Category Filters -->
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white p-3 shadow-xs dark:border-zinc-800/80 dark:bg-zinc-900">
                <!-- Category Filter Pills -->
                <div class="flex flex-wrap items-center gap-1.5 border-b border-slate-100 pb-2.5 dark:border-zinc-800">
                    <button
                        type="button"
                        wire:click="$set('fileCategory', 'all')"
                        class="px-3 py-1 text-xs font-semibold rounded-lg transition-all flex items-center gap-1.5 cursor-pointer {{ $fileCategory === 'all' ? 'bg-[#0D3B29] text-white shadow-xs dark:bg-emerald-600' : 'text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                    >
                        <flux:icon icon="squares-2x2" class="size-3.5" />
                        <span>{{ __('All Files') }}</span>
                        <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $fileCategory === 'all' ? 'bg-white/20 text-white' : 'bg-slate-200/80 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300' }}">{{ $this->accessibleFiles->count() }}</span>
                    </button>

                    <button
                        type="button"
                        wire:click="$set('fileCategory', 'markdown')"
                        class="px-3 py-1 text-xs font-semibold rounded-lg transition-all flex items-center gap-1.5 cursor-pointer {{ $fileCategory === 'markdown' ? 'bg-[#0D3B29] text-white shadow-xs dark:bg-emerald-600' : 'text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                    >
                        <flux:icon icon="document-text" class="size-3.5 text-emerald-500" />
                        <span>{{ __('Notes (.md)') }}</span>
                        <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $fileCategory === 'markdown' ? 'bg-white/20 text-white' : 'bg-slate-200/80 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300' }}">{{ $this->markdownFilesCount }}</span>
                    </button>

                    <button
                        type="button"
                        wire:click="$set('fileCategory', 'canvas')"
                        class="px-3 py-1 text-xs font-semibold rounded-lg transition-all flex items-center gap-1.5 cursor-pointer {{ $fileCategory === 'canvas' ? 'bg-[#0D3B29] text-white shadow-xs dark:bg-emerald-600' : 'text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                    >
                        <flux:icon icon="paint-brush" class="size-3.5 text-violet-500" />
                        <span>{{ __('Canvas (.canvas)') }}</span>
                        <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $fileCategory === 'canvas' ? 'bg-white/20 text-white' : 'bg-slate-200/80 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300' }}">{{ $this->canvasFilesCount }}</span>
                    </button>

                    <button
                        type="button"
                        wire:click="$set('fileCategory', 'attachments')"
                        class="px-3 py-1 text-xs font-semibold rounded-lg transition-all flex items-center gap-1.5 cursor-pointer {{ $fileCategory === 'attachments' ? 'bg-[#0D3B29] text-white shadow-xs dark:bg-emerald-600' : 'text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                    >
                        <flux:icon icon="paper-clip" class="size-3.5 text-sky-500" />
                        <span>{{ __('Attachments') }}</span>
                        <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $fileCategory === 'attachments' ? 'bg-white/20 text-white' : 'bg-slate-200/80 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300' }}">{{ $this->attachmentFilesCount }}</span>
                    </button>

                    @if ($this->conflictFilesCount > 0)
                        <button
                            type="button"
                            wire:click="$set('fileCategory', 'conflicts')"
                            class="px-3 py-1 text-xs font-semibold rounded-lg transition-all flex items-center gap-1.5 cursor-pointer {{ $fileCategory === 'conflicts' ? 'bg-amber-500 text-zinc-950 font-bold shadow-xs' : 'text-amber-600 bg-amber-50 dark:bg-amber-950/40 dark:text-amber-400 hover:bg-amber-100' }}"
                        >
                            <flux:icon icon="exclamation-triangle" class="size-3.5 text-amber-600 dark:text-amber-400" />
                            <span>{{ __('Conflicts') }}</span>
                            <span class="rounded-full px-1.5 py-0.2 text-[10px] bg-amber-200 text-amber-900 font-bold dark:bg-amber-800 dark:text-amber-100">{{ $this->conflictFilesCount }}</span>
                        </button>
                    @endif
                </div>

                <!-- Search, Sort & View Mode Switcher -->
                <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full sm:max-w-md">
                        <flux:input
                            wire:model.live.debounce.250ms="fileSearch"
                            size="sm"
                            icon="magnifying-glass"
                            placeholder="{{ __('Search notes by title, folder path, extension...') }}"
                            clearable
                        />
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <!-- Sort Select -->
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-slate-400 dark:text-zinc-500 font-medium">{{ __('Sort:') }}</span>
                            <select
                                wire:model.live="fileSort"
                                class="text-xs rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 font-medium text-slate-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 outline-none focus:ring-1 focus:ring-emerald-500 cursor-pointer"
                            >
                                <option value="recent">{{ __('Recently Modified') }}</option>
                                <option value="oldest">{{ __('Oldest Modified') }}</option>
                                <option value="name_asc">{{ __('Name (A to Z)') }}</option>
                                <option value="name_desc">{{ __('Name (Z to A)') }}</option>
                                <option value="size_desc">{{ __('Size (Largest First)') }}</option>
                                <option value="size_asc">{{ __('Size (Smallest First)') }}</option>
                            </select>
                        </div>

                        <!-- View Mode Switcher -->
                        <div class="flex items-center rounded-lg border border-slate-200 bg-slate-100 p-0.5 dark:border-zinc-800 dark:bg-zinc-800">
                            <button
                                type="button"
                                wire:click="$set('fileViewMode', 'list')"
                                class="p-1.5 rounded-md transition-colors cursor-pointer {{ $fileViewMode === 'list' ? 'bg-white shadow-xs text-slate-900 dark:bg-zinc-700 dark:text-white' : 'text-slate-400 hover:text-slate-600 dark:text-zinc-400 dark:hover:text-white' }}"
                                title="{{ __('List / Table View') }}"
                            >
                                <flux:icon icon="bars-3" class="size-3.5" />
                            </button>
                            <button
                                type="button"
                                wire:click="$set('fileViewMode', 'grid')"
                                class="p-1.5 rounded-md transition-colors cursor-pointer {{ $fileViewMode === 'grid' ? 'bg-white shadow-xs text-slate-900 dark:bg-zinc-700 dark:text-white' : 'text-slate-400 hover:text-slate-600 dark:text-zinc-400 dark:hover:text-white' }}"
                                title="{{ __('Grid Cards View') }}"
                            >
                                <flux:icon icon="squares-2x2" class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Presentation (List vs Grid) -->
            @if ($this->files->isEmpty())
                <flux:card class="p-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                    <div class="mx-auto max-w-sm space-y-3">
                        <flux:icon icon="document-magnifying-glass" class="mx-auto size-10 text-zinc-300 dark:text-zinc-600" />
                        <h4 class="font-bold text-slate-800 dark:text-zinc-200">{{ __('No notes found matching your filters') }}</h4>
                        <p class="text-xs text-slate-500 dark:text-zinc-400">
                            {{ __('Try clearing your search query or switching categories. You can also create a new note directly.') }}
                        </p>
                        <flux:modal.trigger name="new-note-modal">
                            <flux:button variant="primary" size="sm" icon="plus" class="!bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90 font-semibold cursor-pointer">
                                {{ __('Create New Note') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                </flux:card>
            @elseif ($fileViewMode === 'list')
                <!-- LIST / TABLE VIEW -->
                <flux:card class="p-0 overflow-hidden shadow-xs border-slate-200/80 dark:border-zinc-800">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column class="py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400"><div class="ps-6 sm:ps-8 pe-2">{{ __('Note / Path') }}</div></flux:table.column>
                            <flux:table.column class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">{{ __('Size') }}</flux:table.column>
                            <flux:table.column class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">{{ __('Revision') }}</flux:table.column>
                            <flux:table.column class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">{{ __('Modified By') }}</flux:table.column>
                            <flux:table.column class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">{{ __('Last Synced') }}</flux:table.column>
                            <flux:table.column align="end" class="py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400"><div class="ps-2 pe-6 sm:pe-8">{{ __('Actions') }}</div></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->files as $file)
                                @php
                                    $isMd = $file->isMarkdown();
                                    $isCanvas = str_ends_with(strtolower($file->path), '.canvas');
                                    $isConflict = str_contains($file->path, '.conflict-') || str_contains($file->path, '.sync-conflict-');
                                    $filename = basename($file->path);
                                    $dirname = dirname($file->path);
                                @endphp
                                <flux:table.row :key="$file->id" class="group hover:bg-slate-50/80 dark:hover:bg-zinc-900/50 transition-colors">
                                    <flux:table.cell class="py-3.5 font-mono text-xs font-medium">
                                        <div class="flex items-center gap-2.5 ps-6 sm:ps-8 pe-2">
                                            <div class="size-8 rounded-lg flex items-center justify-center shrink-0 {{ $isMd ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : ($isCanvas ? 'bg-violet-500/10 text-violet-600 dark:text-violet-400' : 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-400') }}">
                                                @if ($isMd)
                                                    <flux:icon icon="document-text" class="size-4" />
                                                @elseif ($isCanvas)
                                                    <flux:icon icon="paint-brush" class="size-4" />
                                                @else
                                                    <flux:icon icon="paper-clip" class="size-4" />
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5">
                                                    @if ($isMd)
                                                        <button
                                                            type="button"
                                                            wire:click="openFileInEditor({{ $file->id }})"
                                                            class="font-sans font-bold text-xs text-slate-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors truncate max-w-xs text-left cursor-pointer"
                                                            title="{{ __('Open in Markdown Editor') }}"
                                                        >
                                                            {{ $filename }}
                                                        </button>
                                                    @else
                                                        <button
                                                            type="button"
                                                            wire:click="inspectFile({{ $file->id }})"
                                                            class="font-sans font-bold text-xs text-slate-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors truncate max-w-xs text-left cursor-pointer"
                                                            title="{{ __('Inspect details') }}"
                                                        >
                                                            {{ $filename }}
                                                        </button>
                                                    @endif

                                                    @if ($file->is_ghost)
                                                        <flux:badge color="purple" size="sm" class="shrink-0 text-[10px]" title="{{ __('Ghost file stub: content streamable on demand') }}">
                                                             👻 {{ __('Ghost') }}
                                                        </flux:badge>
                                                    @endif
                                                    @if ($file->is_encrypted || $vault->is_e2ee)
                                                        <flux:badge color="emerald" size="sm" class="shrink-0 text-[10px]" title="{{ __('Zero-Knowledge E2EE encrypted') }}">
                                                            🔒 {{ __('E2EE') }}
                                                        </flux:badge>
                                                    @endif
                                                    @if ($isConflict)
                                                        <flux:badge color="amber" size="sm" class="shrink-0 text-[10px] font-bold">{{ __('Conflict') }}</flux:badge>
                                                    @endif
                                                </div>
                                                @if ($dirname !== '.' && $dirname !== '')
                                                    <div class="font-mono text-[10px] text-slate-400 dark:text-zinc-500 truncate max-w-xs">
                                                        {{ $dirname }}/
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </flux:table.cell>

                                    <flux:table.cell class="px-4 py-3.5 text-xs text-zinc-500">
                                        @if ($file->is_ghost && $file->original_size > 0)
                                            <span title="{{ __('Original size before ghost stubbing') }}">{{ Number::fileSize($file->original_size, precision: 1) }}</span>
                                            <span class="text-[10px] text-zinc-400">({{ __('stub') }})</span>
                                        @else
                                            {{ Number::fileSize($file->size, precision: 1) }}
                                        @endif
                                    </flux:table.cell>

                                    <flux:table.cell class="px-4 py-3.5">
                                        <flux:badge color="zinc" size="sm">v{{ $file->version }}</flux:badge>
                                    </flux:table.cell>

                                    <flux:table.cell class="px-4 py-3.5">
                                        <div class="flex items-center gap-1.5">
                                            <div class="size-5 rounded-full bg-slate-200 dark:bg-zinc-700 text-[10px] font-bold text-slate-700 dark:text-zinc-200 flex items-center justify-center shrink-0">
                                                {{ $file->lastModifier ? $file->lastModifier->initials() : 'SY' }}
                                            </div>
                                            <span class="text-xs text-zinc-700 dark:text-zinc-300 truncate max-w-[100px]">{{ $file->lastModifier?->name ?? __('Sync Engine') }}</span>
                                        </div>
                                    </flux:table.cell>

                                    <flux:table.cell class="px-4 py-3.5 text-xs text-zinc-400">
                                        {{ $file->updated_at->diffForHumans() }}
                                    </flux:table.cell>

                                    <flux:table.cell align="end" class="py-3.5">
                                        <div class="flex items-center justify-end gap-1 ps-2 pe-6 sm:pe-8">
                                            @if ($isMd)
                                                <flux:button
                                                    variant="subtle"
                                                    size="xs"
                                                    icon="pencil-square"
                                                    wire:click="openFileInEditor({{ $file->id }})"
                                                    title="{{ __('Open in Editor') }}"
                                                    class="cursor-pointer text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
                                                />
                                            @endif

                                            <flux:button
                                                variant="subtle"
                                                size="xs"
                                                icon="information-circle"
                                                wire:click="inspectFile({{ $file->id }})"
                                                title="{{ __('File Details & Inspector') }}"
                                                class="cursor-pointer text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                                            />

                                            <!-- Dropdown Actions Menu -->
                                            <flux:dropdown position="bottom" align="end">
                                                <flux:button
                                                    variant="subtle"
                                                    size="xs"
                                                    icon="ellipsis-vertical"
                                                    class="cursor-pointer text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200"
                                                />

                                                <flux:menu class="min-w-48">
                                                    @if ($isMd)
                                                        <flux:menu.item icon="pencil-square" wire:click="openFileInEditor({{ $file->id }})">
                                                            {{ __('Edit in Editor') }}
                                                        </flux:menu.item>
                                                    @endif

                                                    <flux:menu.item icon="information-circle" wire:click="inspectFile({{ $file->id }})">
                                                        {{ __('View File Info') }}
                                                    </flux:menu.item>

                                                    <flux:menu.item icon="document-duplicate" wire:click="duplicateFile({{ $file->id }})">
                                                        {{ __('Duplicate Note') }}
                                                    </flux:menu.item>

                                                    <flux:menu.item icon="pencil" wire:click="openRenameModal({{ $file->id }})">
                                                        {{ __('Rename Path') }}
                                                    </flux:menu.item>

                                                    <flux:menu.item icon="arrow-down-tray" wire:click="downloadFile({{ $file->id }})">
                                                        {{ __('Download File') }}
                                                    </flux:menu.item>

                                                    <flux:menu.item
                                                        icon="link"
                                                        x-on:click="navigator.clipboard.writeText('[[' + '{{ addslashes(pathinfo($file->path, PATHINFO_FILENAME)) }}' + ']]'); $wire.copyWikilinkNotice('{{ addslashes(pathinfo($file->path, PATHINFO_FILENAME)) }}')"
                                                    >
                                                        {{ __('Copy Wikilink [[...]]') }}
                                                    </flux:menu.item>

                                                    <flux:menu.item icon="clock" wire:click="showFileHistory({{ $file->id }})">
                                                        {{ __('Revision History') }}
                                                    </flux:menu.item>

                                                    @if ($isConflict)
                                                        <flux:menu.item icon="arrows-right-left" wire:click="openConflictSandbox('{{ $file->path }}')" class="text-amber-600 font-bold">
                                                            {{ __('Reconcile Conflict') }}
                                                        </flux:menu.item>
                                                    @endif

                                                    @if ($file->is_ghost)
                                                        <flux:menu.item icon="arrow-down-tray" wire:click="hydrateGhostFile({{ $file->id }})">
                                                            {{ __('Hydrate Full Content') }}
                                                        </flux:menu.item>
                                                    @elseif (! $file->isMarkdown() && $file->size > 1024)
                                                        <flux:menu.item icon="cloud-arrow-up" wire:click="dehydrateGhostFile({{ $file->id }})">
                                                            {{ __('Convert to Ghost Stub') }}
                                                        </flux:menu.item>
                                                    @endif

                                                    <flux:menu.separator />

                                                    <flux:menu.item icon="trash" wire:click="confirmDeleteFile({{ $file->id }})" variant="danger">
                                                        {{ __('Delete Note') }}
                                                    </flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </flux:card>
            @else
                <!-- GRID / CARD VIEW -->
                <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->files as $file)
                        @php
                            $isMd = $file->isMarkdown();
                            $isCanvas = str_ends_with(strtolower($file->path), '.canvas');
                            $isConflict = str_contains($file->path, '.conflict-') || str_contains($file->path, '.sync-conflict-');
                            $filename = basename($file->path);
                            $dirname = dirname($file->path);
                            $excerpt = $isMd ? mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($file->getContents() ?? ''))), 0, 140) : null;
                        @endphp
                        <div class="group relative flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs hover:shadow-md hover:border-emerald-500/40 transition-all dark:border-zinc-800/80 dark:bg-zinc-900">
                            <div>
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <div class="size-8 rounded-lg flex items-center justify-center shrink-0 {{ $isMd ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : ($isCanvas ? 'bg-violet-500/10 text-violet-600 dark:text-violet-400' : 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-400') }}">
                                            @if ($isMd)
                                                <flux:icon icon="document-text" class="size-4" />
                                            @elseif ($isCanvas)
                                                <flux:icon icon="paint-brush" class="size-4" />
                                            @else
                                                <flux:icon icon="paper-clip" class="size-4" />
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            @if ($isMd)
                                                <button
                                                    type="button"
                                                    wire:click="openFileInEditor({{ $file->id }})"
                                                    class="font-sans font-bold text-xs text-slate-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors truncate block text-left cursor-pointer"
                                                    title="{{ $filename }}"
                                                >
                                                    {{ $filename }}
                                                </button>
                                            @else
                                                <button
                                                    type="button"
                                                    wire:click="inspectFile({{ $file->id }})"
                                                    class="font-sans font-bold text-xs text-slate-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors truncate block text-left cursor-pointer"
                                                    title="{{ $filename }}"
                                                >
                                                    {{ $filename }}
                                                </button>
                                            @endif
                                            @if ($dirname !== '.' && $dirname !== '')
                                                <span class="font-mono text-[10px] text-slate-400 dark:text-zinc-500 truncate block">
                                                    {{ $dirname }}/
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- 3-dots Menu -->
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button
                                            variant="subtle"
                                            size="xs"
                                            icon="ellipsis-vertical"
                                            class="cursor-pointer text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200"
                                        />

                                        <flux:menu class="min-w-48">
                                            @if ($isMd)
                                                <flux:menu.item icon="pencil-square" wire:click="openFileInEditor({{ $file->id }})">
                                                    {{ __('Edit in Editor') }}
                                                </flux:menu.item>
                                            @endif

                                            <flux:menu.item icon="information-circle" wire:click="inspectFile({{ $file->id }})">
                                                {{ __('View File Info') }}
                                            </flux:menu.item>

                                            <flux:menu.item icon="document-duplicate" wire:click="duplicateFile({{ $file->id }})">
                                                {{ __('Duplicate Note') }}
                                            </flux:menu.item>

                                            <flux:menu.item icon="pencil" wire:click="openRenameModal({{ $file->id }})">
                                                {{ __('Rename Path') }}
                                            </flux:menu.item>

                                            <flux:menu.item icon="arrow-down-tray" wire:click="downloadFile({{ $file->id }})">
                                                {{ __('Download File') }}
                                            </flux:menu.item>

                                            <flux:menu.item
                                                icon="link"
                                                x-on:click="navigator.clipboard.writeText('[[' + '{{ addslashes(pathinfo($file->path, PATHINFO_FILENAME)) }}' + ']]'); $wire.copyWikilinkNotice('{{ addslashes(pathinfo($file->path, PATHINFO_FILENAME)) }}')"
                                            >
                                                {{ __('Copy Wikilink [[...]]') }}
                                            </flux:menu.item>

                                            <flux:menu.item icon="clock" wire:click="showFileHistory({{ $file->id }})">
                                                {{ __('Revision History') }}
                                            </flux:menu.item>

                                            @if ($isConflict)
                                                <flux:menu.item icon="arrows-right-left" wire:click="openConflictSandbox('{{ $file->path }}')" class="text-amber-600 font-bold">
                                                    {{ __('Reconcile Conflict') }}
                                                </flux:menu.item>
                                            @endif

                                            <flux:menu.separator />

                                            <flux:menu.item icon="trash" wire:click="confirmDeleteFile({{ $file->id }})" variant="danger">
                                                {{ __('Delete Note') }}
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>

                                <!-- Note Excerpt Preview -->
                                @if ($isMd && $excerpt)
                                    <div class="mt-3 rounded-xl bg-slate-50/80 p-2.5 dark:bg-zinc-800/40">
                                        <p class="line-clamp-3 text-[11px] leading-relaxed text-slate-600 dark:text-zinc-400">
                                            {{ $excerpt }}
                                        </p>
                                    </div>
                                @elseif ($isCanvas)
                                    <div class="mt-3 rounded-xl bg-violet-50/50 p-2.5 dark:bg-violet-950/20 text-[11px] text-violet-700 dark:text-violet-300">
                                        {{ __('Obsidian visual infinite canvas diagram') }}
                                    </div>
                                @else
                                    <div class="mt-3 rounded-xl bg-slate-50 p-2.5 dark:bg-zinc-800/40 text-[11px] text-slate-500 dark:text-zinc-400">
                                        {{ __('Binary asset / attachment') }}
                                    </div>
                                @endif

                                <!-- Status Badges -->
                                <div class="mt-3 flex flex-wrap items-center gap-1">
                                    <flux:badge color="zinc" size="sm" class="text-[10px]">v{{ $file->version }}</flux:badge>
                                    @if ($file->is_ghost)
                                        <flux:badge color="purple" size="sm" class="text-[10px]">👻 {{ __('Ghost') }}</flux:badge>
                                    @endif
                                    @if ($file->is_encrypted || $vault->is_e2ee)
                                        <flux:badge color="emerald" size="sm" class="text-[10px]">🔒 {{ __('E2EE') }}</flux:badge>
                                    @endif
                                    @if ($isConflict)
                                        <flux:badge color="amber" size="sm" class="text-[10px] font-bold">{{ __('Conflict') }}</flux:badge>
                                    @endif
                                </div>
                            </div>

                            <!-- Card Footer -->
                            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 dark:border-zinc-800/80 text-[11px] text-slate-400 dark:text-zinc-500">
                                <div class="flex items-center gap-1.5">
                                    <div class="size-4.5 rounded-full bg-slate-200 dark:bg-zinc-700 text-[9px] font-bold text-slate-700 dark:text-zinc-200 flex items-center justify-center shrink-0">
                                        {{ $file->lastModifier ? $file->lastModifier->initials() : 'SY' }}
                                    </div>
                                    <span>{{ $file->updated_at->diffForHumans(short: true) }}</span>
                                </div>
                                <span class="font-mono">{{ Number::fileSize($file->size, precision: 1) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <!-- TAB 3: Activity Logs -->
    @if ($activeTab === 'activity')
        <flux:card class="p-0 overflow-hidden">
            @if ($this->activities->isEmpty())
                <div class="p-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No activity recorded for this vault yet.') }}
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column><div class="ps-6 sm:ps-8 pe-2">{{ __('Action') }}</div></flux:table.column>
                        <flux:table.column>{{ __('File Path') }}</flux:table.column>
                        <flux:table.column>{{ __('Member & Device') }}</flux:table.column>
                        <flux:table.column>{{ __('Revision') }}</flux:table.column>
                        <flux:table.column align="end"><div class="ps-2 pe-6 sm:pe-8">{{ __('Timestamp') }}</div></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->activities as $act)
                            <flux:table.row :key="$act->id">
                                <flux:table.cell>
                                    <div class="flex flex-wrap items-center gap-1.5 ps-6 sm:ps-8 pe-2">
                                        @if ($act->action === 'created')
                                            <flux:badge color="emerald" size="sm">{{ __('Created') }}</flux:badge>
                                        @elseif ($act->action === 'updated')
                                            <flux:badge color="teal" size="sm">{{ __('Updated') }}</flux:badge>
                                        @elseif ($act->action === 'deleted')
                                            <flux:badge color="red" size="sm">{{ __('Deleted') }}</flux:badge>
                                        @elseif ($act->action === 'conflict')
                                            <flux:badge color="amber" size="sm">{{ __('Conflict Branched') }}</flux:badge>
                                        @endif

                                        @if ($act->has_secrets)
                                            <flux:badge color="red" size="sm" icon="exclamation-triangle" class="font-bold">
                                                {{ __('DLP Secret Detected') }}: {{ implode(', ', $act->detected_secrets ?? [__('Credential')]) }}
                                            </flux:badge>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="font-mono text-xs">
                                    {{ $act->path }}
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:avatar :name="$act->user?->name ?? 'Device'" size="xs" />
                                        <span class="text-xs font-medium">{{ $act->user?->name ?? __('System') }}</span>
                                        @if ($act->device_name)
                                            <span class="text-xs text-zinc-400">({{ $act->device_name }})</span>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="text-xs font-mono text-zinc-400">
                                    v{{ $act->version }}
                                </flux:table.cell>

                                <flux:table.cell align="end" class="text-xs text-zinc-400">
                                    <div class="ps-2 pe-6 sm:pe-8">{{ $act->created_at->diffForHumans() }}</div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif

    <!-- TAB: VAULT INTELLIGENCE & ANALYTICS -->
    @if ($activeTab === 'analytics')
        <div class="flex flex-col gap-6">
            <!-- Header & Timeframe Switcher -->
            <div class="flex flex-col gap-4 rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Vault Intelligence & Deep Analytics') }}</h2>
                    <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Real-time telemetry, note metrics, graph connectivity, and collaborator change stream.') }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ __('Timeframe:') }}</span>
                    <div class="flex items-center rounded-xl bg-slate-100 p-1 dark:bg-zinc-800">
                        @foreach (['24h' => '24h', '7d' => '7d', '30d' => '30d', '90d' => '90d', 'all' => 'All'] as $tfKey => $tfLabel)
                            <button
                                type="button"
                                wire:click="$set('analyticsTimeframe', '{{ $tfKey }}')"
                                class="rounded-lg px-2.5 py-1 text-xs font-semibold transition-all cursor-pointer {{ $analyticsTimeframe === $tfKey ? 'bg-white text-[#0D3B29] shadow-2xs dark:bg-zinc-900 dark:text-emerald-400' : 'text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white' }}"
                            >
                                {{ $tfLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- 4 KPI Summary Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Notes & Words -->
                <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ __('Notes & Knowledge') }}</span>
                        <span class="flex size-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
                            <flux:icon icon="document-text" class="size-4" />
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ number_format($this->analyticsStats['notes_count']) }}</span>
                        <span class="text-xs text-slate-500 dark:text-zinc-400">{{ __('markdown notes') }}</span>
                    </div>
                    <div class="mt-2.5 flex items-center justify-between border-t border-gray-100 pt-2 text-[11px] text-slate-500 dark:border-zinc-800/80 dark:text-zinc-400">
                        <span>~{{ number_format($this->analyticsStats['estimated_words']) }} {{ __('words') }}</span>
                        <span class="font-medium text-emerald-700 dark:text-emerald-400">~{{ $this->analyticsStats['reading_time_minutes'] }} {{ __('min read') }}</span>
                    </div>
                </div>

                <!-- Storage & Files -->
                <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ __('Total Storage & Files') }}</span>
                        <span class="flex size-7 items-center justify-center rounded-lg bg-teal-50 text-teal-700 dark:bg-teal-950/50 dark:text-teal-400">
                            <flux:icon icon="circle-stack" class="size-4" />
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $this->analyticsStats['total_storage_formatted'] }}</span>
                        <span class="text-xs text-slate-500 dark:text-zinc-400">({{ number_format($this->analyticsStats['total_files']) }} {{ __('files') }})</span>
                    </div>
                    <div class="mt-2.5 flex items-center justify-between border-t border-gray-100 pt-2 text-[11px] text-slate-500 dark:border-zinc-800/80 dark:text-zinc-400">
                        <span>{{ number_format($this->analyticsStats['images_count']) }} {{ __('images') }} ({{ $this->analyticsStats['images_size_formatted'] }})</span>
                        <span class="font-medium text-teal-700 dark:text-teal-400">{{ $this->analyticsStats['canvas_count'] }} {{ __('canvases') }}</span>
                    </div>
                </div>

                <!-- Wiki-Links & Density -->
                <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ __('Wiki-Links & Network') }}</span>
                        <span class="flex size-7 items-center justify-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400">
                            <flux:icon icon="share" class="size-4" />
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ number_format($this->analyticsWikiGraph['total_links']) }}</span>
                        <span class="text-xs text-slate-500 dark:text-zinc-400">{{ __('connections') }}</span>
                    </div>
                    <div class="mt-2.5 flex items-center justify-between border-t border-gray-100 pt-2 text-[11px] text-slate-500 dark:border-zinc-800/80 dark:text-zinc-400">
                        <span>{{ $this->analyticsWikiGraph['density'] }} {{ __('links/note') }}</span>
                        <span class="font-medium text-amber-700 dark:text-amber-400">{{ $this->analyticsWikiGraph['orphan_notes_count'] }} {{ __('orphans') }}</span>
                    </div>
                </div>

                <!-- Mutation Velocity -->
                <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ __('Sync Velocity (:tf)', ['tf' => $analyticsTimeframe]) }}</span>
                        <span class="flex size-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
                            <flux:icon icon="bolt" class="size-4" />
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ number_format($this->analyticsStats['period_changes']) }}</span>
                        <span class="text-xs text-slate-500 dark:text-zinc-400">{{ __('mutations') }}</span>
                    </div>
                    <div class="mt-2.5 flex items-center justify-between border-t border-gray-100 pt-2 text-[11px] text-slate-500 dark:border-zinc-800/80 dark:text-zinc-400">
                        <span>+{{ $this->analyticsStats['period_creations'] }} ~{{ $this->analyticsStats['period_updates'] }} -{{ $this->analyticsStats['period_deletions'] }}</span>
                        <span class="font-medium text-emerald-700 dark:text-emerald-400">{{ count($this->analyticsLeaderboard) }} {{ __('collaborators') }}</span>
                    </div>
                </div>
            </div>

            <!-- 14-Day Velocity Chart & Format Composition -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- 14-Day Velocity Histogram -->
                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs lg:col-span-2 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('14-Day Vault Mutation Velocity') }}</h3>
                            <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Daily note creations, edits, and deletions synced across connected devices.') }}</p>
                        </div>
                        <div class="flex items-center gap-3 text-[11px]">
                            <span class="flex items-center gap-1 text-slate-600 dark:text-zinc-300">
                                <span class="size-2.5 rounded-full bg-emerald-500"></span> {{ __('Created') }}
                            </span>
                            <span class="flex items-center gap-1 text-slate-600 dark:text-zinc-300">
                                <span class="size-2.5 rounded-full bg-teal-500"></span> {{ __('Updated') }}
                            </span>
                            <span class="flex items-center gap-1 text-slate-600 dark:text-zinc-300">
                                <span class="size-2.5 rounded-full bg-rose-400"></span> {{ __('Deleted') }}
                            </span>
                        </div>
                    </div>

                    @php
                        $maxVel = collect($this->analyticsVelocity)->max('total') ?: 1;
                    @endphp

                    <div class="mt-6 flex h-44 items-end gap-2 border-b border-gray-200/80 pb-2 dark:border-zinc-800">
                        @foreach ($this->analyticsVelocity as $vPoint)
                            @php
                                $heightPct = max(6, min(100, round(($vPoint['total'] / $maxVel) * 100)));
                            @endphp
                            <div class="flex flex-1 flex-col items-center gap-1.5 h-full justify-end group relative" title="{{ $vPoint['date'] }}: {{ $vPoint['total'] }} mutations ({{ $vPoint['created'] }} created, {{ $vPoint['updated'] }} updated, {{ $vPoint['deleted'] }} deleted)">
                                <div class="w-full max-w-[28px] rounded-t-md bg-slate-100 flex flex-col justify-end overflow-hidden dark:bg-zinc-800" style="height: {{ $heightPct }}%;">
                                    @if ($vPoint['created'] > 0)
                                        <div class="w-full bg-emerald-500" style="height: {{ round(($vPoint['created'] / max(1, $vPoint['total'])) * 100) }}%;"></div>
                                    @endif
                                    @if ($vPoint['updated'] > 0)
                                        <div class="w-full bg-teal-500" style="height: {{ round(($vPoint['updated'] / max(1, $vPoint['total'])) * 100) }}%;"></div>
                                    @endif
                                    @if ($vPoint['deleted'] > 0)
                                        <div class="w-full bg-rose-400" style="height: {{ round(($vPoint['deleted'] / max(1, $vPoint['total'])) * 100) }}%;"></div>
                                    @endif
                                </div>
                                <span class="text-[10px] text-slate-400 group-hover:text-slate-900 dark:group-hover:text-white transition-colors">{{ $vPoint['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Asset & File Breakdown -->
                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 flex flex-col justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">{{ __('Vault File Composition') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 mb-4">{{ __('Format breakdown of all notes and media assets.') }}</p>

                        <!-- Segmented Bar -->
                        @php
                            $totalF = max(1, $this->analyticsStats['total_files']);
                            $notesPct = round(($this->analyticsStats['notes_count'] / $totalF) * 100);
                            $imagesPct = round(($this->analyticsStats['images_count'] / $totalF) * 100);
                            $canvasPct = round(($this->analyticsStats['canvas_count'] / $totalF) * 100);
                            $othersPct = max(0, 100 - $notesPct - $imagesPct - $canvasPct);
                        @endphp

                        <div class="h-3.5 w-full rounded-full bg-slate-100 flex overflow-hidden dark:bg-zinc-800 mb-4">
                            <div class="bg-emerald-600 transition-all" style="width: {{ $notesPct }}%;" title="Markdown Notes: {{ $notesPct }}%"></div>
                            <div class="bg-teal-500 transition-all" style="width: {{ $imagesPct }}%;" title="Images: {{ $imagesPct }}%"></div>
                            <div class="bg-amber-500 transition-all" style="width: {{ $canvasPct }}%;" title="Canvases: {{ $canvasPct }}%"></div>
                            <div class="bg-slate-400 transition-all" style="width: {{ $othersPct }}%;" title="Other Files: {{ $othersPct }}%"></div>
                        </div>

                        <!-- Image Format Breakdown Pills -->
                        <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-2">{{ __('Image Attachments by Format') }}</h4>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($this->analyticsStats['image_breakdown'] as $ext => $imgData)
                                @if ($imgData['count'] > 0)
                                    <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/80 p-2 text-xs dark:border-zinc-800 dark:bg-zinc-800/40">
                                        <span class="font-mono font-bold uppercase text-slate-700 dark:text-zinc-300">.{{ $ext }}</span>
                                        <div class="text-right">
                                            <div class="font-bold text-slate-900 dark:text-white">{{ $imgData['count'] }}</div>
                                            <div class="text-[10px] text-slate-400">{{ $imgData['size_formatted'] }}</div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4 border-t border-gray-100 pt-3 text-[11px] text-slate-500 dark:border-zinc-800 dark:text-zinc-400">
                        {{ __('Avg note length: ~:words words (:chars characters)', [
                            'words' => $this->analyticsStats['notes_count'] > 0 ? round($this->analyticsStats['estimated_words'] / $this->analyticsStats['notes_count']) : 0,
                            'chars' => $this->analyticsStats['notes_count'] > 0 ? round($this->analyticsStats['total_characters'] / $this->analyticsStats['notes_count']) : 0,
                        ]) }}
                    </div>
                </div>
            </div>

            <!-- Knowledge Hubs & Collaborator Leaderboard Grid -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Top Authority Hub Notes -->
                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="link" class="size-4 text-emerald-600 dark:text-emerald-400" />
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Knowledge Hubs (Top Inbound Linked Notes)') }}</h3>
                        </div>
                        <span class="rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                            {{ $this->analyticsWikiGraph['unique_targets_count'] }} {{ __('connected targets') }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-zinc-400 mb-4">{{ __('Central nodes linked most frequently from other notes in this vault.') }}</p>

                    <div class="flex flex-col divide-y divide-gray-100 dark:divide-zinc-800">
                        @forelse ($this->analyticsWikiGraph['top_hubs'] as $hub)
                            <div class="flex items-center justify-between py-2.5">
                                <div class="flex items-center gap-2.5 truncate">
                                    <span class="flex size-6 shrink-0 items-center justify-center rounded-md bg-emerald-100 text-xs font-bold text-[#0D3B29] dark:bg-emerald-950/80 dark:text-emerald-300">
                                        #{{ $loop->iteration }}
                                    </span>
                                    <span class="font-mono text-xs font-semibold text-slate-800 truncate dark:text-zinc-200">
                                        [[{{ $hub['title'] }}]]
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                                        {{ $hub['inbound_links'] }} {{ __('inbound links') }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="py-6 text-center text-xs text-slate-400">{{ __('No internal [[wiki-links]] found in this vault yet.') }}</div>
                        @endforelse
                    </div>
                </div>

                <!-- Active Collaborators Leaderboard -->
                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="user-group" class="size-4 text-teal-600 dark:text-teal-400" />
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Collaborator Activity Leaderboard') }}</h3>
                        </div>
                        <span class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Timeframe: :tf', ['tf' => $analyticsTimeframe]) }}</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-zinc-400 mb-4">{{ __('Team members actively modifying files and syncing changes.') }}</p>

                    <div class="flex flex-col divide-y divide-gray-100 dark:divide-zinc-800">
                        @forelse ($this->analyticsLeaderboard as $contributor)
                            <div class="flex items-center justify-between py-2.5">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-xs font-bold text-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
                                        {{ substr($contributor['user_name'], 0, 2) }}
                                    </span>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900 dark:text-white">{{ $contributor['user_name'] }}</div>
                                        <div class="text-[10px] text-slate-400">
                                            {{ $contributor['device_name'] ?? 'Obsidian Sync' }} • {{ $contributor['last_active_human'] }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 text-right">
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">{{ $contributor['mutations_count'] }} {{ __('edits') }}</div>
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-mono text-slate-600 dark:bg-zinc-800 dark:text-zinc-400">
                                        +{{ $contributor['creations'] }} ~{{ $contributor['updates'] }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="py-6 text-center text-xs text-slate-400">{{ __('No member activity recorded in this timeframe.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Live Change & Mutation Stream Table -->
            <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between border-b border-gray-200/80 dark:border-zinc-800">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Real-Time Vault Mutation Stream') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Detailed history of note updates, deletions, and sync changes.') }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="relative w-48 sm:w-64">
                            <flux:icon icon="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-400" />
                            <input
                                wire:model.live.debounce.250ms="analyticsSearchQuery"
                                type="text"
                                placeholder="Filter path or device..."
                                class="w-full rounded-xl border border-gray-200/90 bg-white py-1.5 pl-8 pr-3 text-xs text-slate-900 shadow-2xs focus:border-[#0D3B29] focus:outline-none dark:border-zinc-800 dark:bg-zinc-800 dark:text-white"
                            />
                        </div>

                        <select
                            wire:model.live="analyticsActionFilter"
                            class="rounded-xl border border-gray-200/90 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs focus:outline-none dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-200 cursor-pointer"
                        >
                            <option value="all">{{ __('All Actions') }}</option>
                            <option value="created">{{ __('Created') }}</option>
                            <option value="updated">{{ __('Updated') }}</option>
                            <option value="deleted">{{ __('Deleted') }}</option>
                            <option value="conflict">{{ __('Conflict') }}</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-gray-200/80 bg-slate-50/75 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:border-zinc-800 dark:bg-zinc-800/50 dark:text-zinc-400">
                            <tr>
                                <th class="py-3 px-4">{{ __('Timestamp') }}</th>
                                <th class="py-3 px-4">{{ __('File Path') }}</th>
                                <th class="py-3 px-4">{{ __('Action') }}</th>
                                <th class="py-3 px-4">{{ __('Contributor & Device') }}</th>
                                <th class="py-3 px-4">{{ __('Size') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800/80">
                            @forelse ($this->analyticsRecentFeed as $feedItem)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="py-3 px-4 whitespace-nowrap text-slate-500 dark:text-zinc-400 text-[11px]">
                                        {{ $feedItem->created_at->format('M d, H:i:s') }}
                                        <span class="block text-[10px] text-slate-400">({{ $feedItem->created_at->diffForHumans() }})</span>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-slate-700 dark:text-zinc-300 max-w-xs truncate" title="{{ $feedItem->path }}">
                                        {{ $feedItem->path }}
                                    </td>
                                    <td class="py-3 px-4">
                                        @if ($feedItem->action === 'created')
                                            <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">{{ __('CREATED') }}</span>
                                        @elseif ($feedItem->action === 'updated')
                                            <span class="rounded bg-teal-100 px-1.5 py-0.5 text-[10px] font-bold text-teal-800 dark:bg-teal-950/60 dark:text-teal-300">{{ __('UPDATED') }}</span>
                                        @elseif ($feedItem->action === 'conflict')
                                            <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">{{ __('CONFLICT') }}</span>
                                        @else
                                            <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">{{ __('DELETED') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-slate-600 dark:text-zinc-300">
                                        <div class="font-medium text-slate-900 dark:text-white">{{ $feedItem->user?->name ?? 'Obsidian Sync' }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $feedItem->device_name ?? 'Desktop Client' }}</div>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-[11px] text-slate-500 dark:text-zinc-400">
                                        {{ $feedItem->file_size ? \Illuminate\Support\Number::fileSize($feedItem->file_size, precision: 1) : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-xs text-slate-400">{{ __('No change records match the filter criteria.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pre-Flight Storage & Atomic Safety Shield Card -->
            <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
                            <flux:icon icon="shield-check" class="size-6" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Pre-Flight Shield & Storage Inspector') }}</h3>
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                                    {{ __('Atomic Shield Active') }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Real-time team cloud storage quota, payload distribution, and bulk-deletion safety thresholds.') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <code class="rounded bg-slate-100 px-2.5 py-1 text-[11px] font-mono text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                            {{ __('Protocol v2 • Preflight API Ready') }}
                        </code>
                    </div>
                </div>

                <!-- Storage Quota Progress Bar -->
                <div class="mb-5 rounded-xl bg-slate-50 p-4 border border-gray-100 dark:bg-zinc-800/40 dark:border-zinc-800">
                    <div class="flex items-center justify-between text-xs mb-2">
                        <span class="font-medium text-slate-700 dark:text-zinc-300">{{ __('Team Storage Quota Allocation') }}</span>
                        <span class="font-bold text-slate-900 dark:text-white">
                            {{ $this->preflightStorageTelemetry['team_storage_used_formatted'] }} / {{ $this->preflightStorageTelemetry['team_storage_limit_formatted'] }} ({{ $this->preflightStorageTelemetry['team_storage_usage_percent'] }}%)
                        </span>
                    </div>

                    <div class="w-full h-3 rounded-full bg-slate-200 overflow-hidden dark:bg-zinc-700">
                        <div
                            class="h-full rounded-full transition-all duration-500 {{ $this->preflightStorageTelemetry['team_storage_usage_percent'] > 90 ? 'bg-rose-500' : ($this->preflightStorageTelemetry['team_storage_usage_percent'] > 70 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                            style="width: {{ max(1, $this->preflightStorageTelemetry['team_storage_usage_percent']) }}%;"
                        ></div>
                    </div>

                    <div class="mt-2.5 flex items-center justify-between text-[11px] text-slate-500 dark:text-zinc-400">
                        <span>{{ __('Available remaining:') }} <strong class="text-slate-700 dark:text-zinc-200">{{ $this->preflightStorageTelemetry['team_storage_remaining_formatted'] }}</strong></span>
                        <span>{{ __('Vault Footprint:') }} <strong class="text-slate-700 dark:text-zinc-200">{{ $this->preflightStorageTelemetry['vault_total_formatted'] }} ({{ number_format($this->preflightStorageTelemetry['vault_total_files']) }} files)</strong></span>
                    </div>
                </div>

                <!-- 3 Metric Blocks -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-gray-100 bg-white p-3.5 dark:border-zinc-800 dark:bg-zinc-900/60">
                        <div class="text-[11px] font-medium text-slate-500 dark:text-zinc-400">{{ __('Markdown Notes') }}</div>
                        <div class="mt-1 text-base font-bold text-slate-900 dark:text-white">{{ $this->preflightStorageTelemetry['markdown_bytes_formatted'] }}</div>
                        <div class="mt-1 text-[10px] text-slate-400">{{ __('Zero-Knowledge E2EE & CRDT ready') }}</div>
                    </div>

                    <div class="rounded-xl border border-gray-100 bg-white p-3.5 dark:border-zinc-800 dark:bg-zinc-900/60">
                        <div class="text-[11px] font-medium text-slate-500 dark:text-zinc-400">{{ __('Attachments & Media') }}</div>
                        <div class="mt-1 text-base font-bold text-slate-900 dark:text-white">{{ $this->preflightStorageTelemetry['image_bytes_formatted'] }}</div>
                        <div class="mt-1 text-[10px] text-slate-400">{{ $this->preflightStorageTelemetry['ghost_files_count'] }} {{ __('mobile ghost stubs active') }}</div>
                    </div>

                    <div class="rounded-xl border border-gray-100 bg-white p-3.5 dark:border-zinc-800 dark:bg-zinc-900/60">
                        <div class="text-[11px] font-medium text-slate-500 dark:text-zinc-400">{{ __('Atomic Safety Guard') }}</div>
                        <div class="mt-1 text-base font-bold text-emerald-600 dark:text-emerald-400">≤ {{ $this->preflightStorageTelemetry['shield_deletion_limit_percent'] }}% {{ __('Threshold') }}</div>
                        <div class="mt-1 text-[10px] text-slate-400">{{ __('Guards against accidental bulk deletion') }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB: Vault Copilot (Agentic Knowledge Graph & RAG Server) -->
    @if ($activeTab === 'copilot')
        <div class="space-y-6">
            <!-- Copilot Header & Telemetry Card -->
            <flux:card class="space-y-4">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">{{ __('Vault Copilot & Agentic RAG Server') }}</flux:heading>
                            <flux:badge color="emerald" size="sm">
                                <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse mr-1.5"></span>
                                {{ __('Local RAG Active') }}
                            </flux:badge>
                        </div>
                        <flux:subheading class="text-xs mt-1">
                            {{ __('Private, zero-cloud-leakage semantic reasoning engine pairing 2D [[wikilink]] graph traversal with local vector embeddings.') }}
                        </flux:subheading>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="rounded-lg bg-zinc-100 dark:bg-zinc-800/80 px-3 py-1.5 text-xs text-zinc-600 dark:text-zinc-300 font-mono flex items-center gap-2">
                            <flux:icon icon="cpu-chip" class="size-3.5 text-amber-500" />
                            <span>{{ $this->ragTelemetry['total_chunks'] }} {{ __('chunks') }} / {{ $this->ragTelemetry['total_files'] }} {{ __('notes') }}</span>
                        </div>
                        <flux:button size="xs" variant="subtle" icon="arrow-path" wire:click="reindexVaultEmbeddings" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="reindexVaultEmbeddings">{{ __('Re-index Vault') }}</span>
                            <span wire:loading wire:target="reindexVaultEmbeddings">{{ __('Indexing...') }}</span>
                        </flux:button>
                    </div>
                </div>

                @if ($copilotStatusMessage)
                    <div class="rounded-lg bg-emerald-500/10 border border-emerald-500/20 px-3.5 py-2 text-xs text-emerald-400 font-medium flex items-center justify-between">
                        <span>{{ $copilotStatusMessage }}</span>
                        <button wire:click="$set('copilotStatusMessage', null)" class="text-emerald-400/60 hover:text-emerald-400">&times;</button>
                    </div>
                @endif

                <!-- Prompt Suggestions -->
                <div class="pt-1">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400">{{ __('Suggested Prompts') }}:</span>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <button
                            type="button"
                            wire:click="askCopilot('Summarize our team\'s sync protocol and security boundaries')"
                            class="rounded-full border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/60 px-3 py-1 text-xs text-zinc-700 dark:text-zinc-300 hover:border-amber-500/50 hover:bg-amber-500/10 transition-colors"
                        >
                            ⚡ "Summarize our sync protocol and security boundaries"
                        </button>
                        <button
                            type="button"
                            wire:click="askCopilot('Find orphaned concepts and missing links in this vault')"
                            class="rounded-full border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/60 px-3 py-1 text-xs text-zinc-700 dark:text-zinc-300 hover:border-amber-500/50 hover:bg-amber-500/10 transition-colors"
                        >
                            🕸️ "Find orphaned concepts and missing links"
                        </button>
                        <button
                            type="button"
                            wire:click="askCopilot('Explain how encryption and device tokens are verified')"
                            class="rounded-full border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/60 px-3 py-1 text-xs text-zinc-700 dark:text-zinc-300 hover:border-amber-500/50 hover:bg-amber-500/10 transition-colors"
                        >
                            🔒 "Explain how encryption and device tokens work"
                        </button>
                    </div>
                </div>
            </flux:card>

            <!-- Interactive Conversation Thread -->
            <div class="space-y-4">
                @if (empty($copilotMessages))
                    <flux:card class="py-12 text-center border-dashed">
                        <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-amber-500/10 text-amber-500">
                            <flux:icon icon="sparkles" class="size-6" />
                        </div>
                        <h3 class="mt-4 font-semibold text-zinc-900 dark:text-white">{{ __('Ready to query your vault') }}</h3>
                        <p class="mt-1.5 max-w-md mx-auto text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            {{ __('Ask any question above or type below. Synkk will search local vector embeddings, traverse note backlinks, and synthesize an exact answer with citations.') }}
                        </p>
                    </flux:card>
                @else
                    @foreach ($copilotMessages as $msg)
                        @if ($msg['role'] === 'user')
                            <div class="flex justify-end">
                                <div class="max-w-xl rounded-2xl bg-zinc-800 border border-zinc-700 px-4 py-3 text-sm text-white shadow-sm">
                                    <div class="flex items-center justify-between gap-4 mb-1 text-[11px] text-zinc-400">
                                        <span class="font-semibold">{{ __('You') }}</span>
                                        <span>{{ $msg['time'] }}</span>
                                    </div>
                                    <p class="text-zinc-100">{{ $msg['content'] }}</p>
                                </div>
                            </div>
                        @else
                            <div class="flex justify-start">
                                <div class="w-full max-w-3xl rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-5 shadow-sm space-y-4">
                                    <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800/80 pb-3">
                                        <div class="flex items-center gap-2">
                                            <flux:icon icon="sparkles" class="size-4 text-amber-500" />
                                            <span class="text-xs font-bold text-zinc-900 dark:text-white">{{ __('Vault Copilot') }}</span>
                                            <flux:badge size="sm" color="zinc" class="font-mono text-[10px]">{{ $msg['model'] }}</flux:badge>
                                        </div>
                                        @if (isset($msg['duration_ms']))
                                            <span class="text-[11px] font-mono text-zinc-400">{{ $msg['duration_ms'] }}ms</span>
                                        @endif
                                    </div>

                                    <!-- Graph Traversal Chips -->
                                    @if (! empty($msg['graph_nodes']))
                                        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800/40 p-3 border border-zinc-200/60 dark:border-zinc-700/50 space-y-2">
                                            <div class="flex items-center gap-1.5 text-[11px] font-semibold text-zinc-500 dark:text-zinc-400">
                                                <flux:icon icon="arrows-pointing-out" class="size-3.5 text-amber-500" />
                                                <span>{{ __('Context retrieved via [[wikilink]] graph traversal') }}:</span>
                                            </div>
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach ($msg['graph_nodes'] as $node)
                                                    <button
                                                        type="button"
                                                        wire:click="openCitationNote('{{ $node['path'] }}')"
                                                        class="inline-flex items-center gap-1 rounded bg-amber-500/10 px-2 py-0.5 text-xs font-mono text-amber-700 dark:text-amber-300 hover:bg-amber-500/20 transition-colors"
                                                        title="{{ __('Open note in editor') }}"
                                                    >
                                                        <span>[[{{ $node['title'] }}]]</span>
                                                        <span class="text-[10px] opacity-75">({{ $node['relationship'] }})</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Markdown Answer -->
                                    <div class="prose prose-sm dark:prose-invert max-w-none text-zinc-800 dark:text-zinc-200 leading-relaxed">
                                        {!! \Illuminate\Support\Str::markdown($msg['content'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                    </div>

                                    <!-- Verified Citations -->
                                    @if (! empty($msg['citations']))
                                        <div class="border-t border-zinc-100 dark:border-zinc-800/80 pt-3 space-y-2">
                                            <span class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400">{{ __('Verified Note Citations') }}:</span>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                @foreach ($msg['citations'] as $cit)
                                                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30 p-2.5 flex flex-col justify-between">
                                                        <div>
                                                            <div class="flex items-center justify-between gap-1 mb-1">
                                                                <span class="text-xs font-mono font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                                                                    {{ $cit['note'] }}{{ $cit['heading'] ? ' #' . $cit['heading'] : '' }}
                                                                </span>
                                                                <span class="text-[10px] font-mono px-1.5 py-0.2 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold">
                                                                    {{ $cit['score_pct'] }}%
                                                                </span>
                                                            </div>
                                                            <p class="text-[11px] text-zinc-500 dark:text-zinc-400 line-clamp-2 italic">
                                                                "{{ $cit['excerpt'] }}"
                                                            </p>
                                                        </div>
                                                        <div class="mt-2 pt-1 border-t border-zinc-200/50 dark:border-zinc-700/50 flex justify-end">
                                                            <button
                                                                type="button"
                                                                wire:click="openCitationNote('{{ $cit['note'] }}')"
                                                                class="text-[11px] font-medium text-amber-600 dark:text-amber-400 hover:underline flex items-center gap-1"
                                                            >
                                                                <span>{{ __('Open in Editor') }}</span>
                                                                <flux:icon icon="arrow-top-right-on-square" class="size-3" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif
            </div>

            <!-- Query Input Bar -->
            <flux:card>
                <form wire:submit.prevent="askCopilot" class="flex gap-2">
                    <div class="flex-1">
                        <flux:input
                            wire:model="copilotQuery"
                            placeholder="{{ __('Ask Vault Copilot anything about this vault (e.g. Summarize architecture)...') }}"
                            autocomplete="off"
                        />
                    </div>
                    <flux:button type="submit" variant="primary" icon="sparkles" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="askCopilot">{{ __('Ask') }}</span>
                        <span wire:loading wire:target="askCopilot">{{ __('Thinking...') }}</span>
                    </flux:button>
                </form>
            </flux:card>
        </div>
    @endif

    <!-- TAB 4: Settings -->
    @if ($activeTab === 'settings')
        <div class="max-w-2xl space-y-6">
            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Vault Configuration') }}</flux:heading>
                    <flux:subheading>{{ __('Update name, description, or baseline team permissions.') }}</flux:subheading>
                </div>

                <form wire:submit="updateVaultSettings" class="space-y-4">
                    <flux:input wire:model="editName" :label="__('Vault Name')" required />

                    <flux:textarea wire:model="editDescription" :label="__('Description')" rows="3" />

                    <flux:select wire:model="editDefaultPermission" :label="__('Default Team Permission')">
                        <flux:select.option value="read_write">{{ __('Read & Write (Full Two-Way Sync)') }}</flux:select.option>
                        <flux:select.option value="read_only">{{ __('Read-Only (Download Only)') }}</flux:select.option>
                        <flux:select.option value="hidden">{{ __('Restricted (Hidden unless specific rule granted)') }}</flux:select.option>
                    </flux:select>

                    <div class="pt-2">
                        <flux:button variant="primary" size="sm" type="submit">{{ __('Save Changes') }}</flux:button>
                    </div>
                </form>
            </flux:card>

            <!-- Zero-Knowledge E2EE & Private Transport Configuration -->
            <flux:card class="space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">{{ __('Zero-Knowledge End-to-End Encryption') }}</flux:heading>
                            @if ($vault->is_e2ee)
                                <flux:badge color="emerald" size="sm">🔒 {{ __('Active (AES-256-GCM)') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Standard TLS In-Transit') }}</flux:badge>
                            @endif
                        </div>
                        <flux:subheading class="text-xs mt-1">
                            {{ __('When enabled, all note contents and attachment binaries are encrypted and decrypted strictly on your client devices with WebCrypto AES-256-GCM and PBKDF2 (100,000 rounds). The Synkk server only ever sees opaque ciphertext.') }}
                        </flux:subheading>
                    </div>
                </div>

                @if ($vault->is_e2ee)
                    <div
                        x-data="{
                            slug: {{ Js::from($vault->slug) }},
                            salt: {{ Js::from($vault->e2ee_salt) }},
                            testCipher: {{ Js::from($vault->e2ee_test_cipher) }},
                            isUnlocked: Boolean(window.VaultCrypto) && window.VaultCrypto.hasSessionKey({{ Js::from($vault->slug) }}),
                            passphrase: '',
                            isDeriving: false,
                            error: '',
                            async unlock() {
                                if (!this.passphrase || this.isDeriving || !window.VaultCrypto) return;
                                this.isDeriving = true;
                                this.error = '';
                                try {
                                    const res = await window.VaultCrypto.verifyPassphrase(this.passphrase, this.salt, this.testCipher);
                                    if (!res.success) {
                                        this.error = res.error || 'Incorrect passphrase.';
                                        return;
                                    }
                                    window.VaultCrypto.setSessionKey(this.slug, res.key);
                                    this.isUnlocked = true;
                                    this.passphrase = '';
                                } catch (e) {
                                    this.error = e.message || 'Unlock failed.';
                                } finally {
                                    this.isDeriving = false;
                                }
                            },
                            lock() {
                                if (window.VaultCrypto) {
                                    window.VaultCrypto.clearSessionKey(this.slug);
                                }
                                this.isUnlocked = false;
                            }
                        }"
                        class="rounded-xl bg-emerald-500/10 border border-emerald-500/20 p-4 space-y-4"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                <flux:icon icon="shield-check" class="size-4" />
                                <span>{{ __('Zero-Knowledge Encryption Verified') }}</span>
                            </div>
                            <template x-if="isUnlocked">
                                <flux:badge color="emerald" size="sm">🔓 {{ __('Browser Session Unlocked') }}</flux:badge>
                            </template>
                            <template x-if="!isUnlocked">
                                <flux:badge color="amber" size="sm">🔒 {{ __('Browser Session Locked') }}</flux:badge>
                            </template>
                        </div>

                        <div class="space-y-1 text-xs font-mono text-zinc-600 dark:text-zinc-400 break-all bg-emerald-500/5 p-2.5 rounded-lg border border-emerald-500/10">
                            <div><span class="text-zinc-400 font-sans font-medium">{{ __('Algorithm: ') }}</span>PBKDF2-HMAC-SHA256 (100k rounds) + AES-GCM-256</div>
                            <div><span class="text-zinc-400 font-sans font-medium">{{ __('Key Salt: ') }}</span>{{ $vault->e2ee_salt }}</div>
                        </div>

                        <!-- Session Lock/Unlock Controls -->
                        <div class="pt-2 border-t border-emerald-500/15">
                            <template x-if="isUnlocked">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs text-zinc-400">
                                        {{ __('Notes in this vault are actively decrypted in this browser tab.') }}
                                    </p>
                                    <flux:button size="xs" variant="ghost" @click="lock()" class="text-zinc-400 hover:text-white">
                                        <flux:icon icon="lock-closed" class="size-3.5 mr-1" />
                                        {{ __('Lock Browser Session') }}
                                    </flux:button>
                                </div>
                            </template>

                            <template x-if="!isUnlocked">
                                <form @submit.prevent="unlock()" class="space-y-2.5">
                                    <p class="text-xs text-zinc-400">
                                        {{ __('Unlock this vault in your browser to view and edit encrypted notes:') }}
                                    </p>
                                    <template x-if="error">
                                        <div class="text-xs text-red-400 flex items-center gap-1.5" x-text="error"></div>
                                    </template>
                                    <div class="flex items-center gap-2">
                                        <flux:input
                                            type="password"
                                            x-model="passphrase"
                                            placeholder="{{ __('Vault passphrase...') }}"
                                            size="sm"
                                            class="flex-1"
                                            required
                                        />
                                        <flux:button
                                            type="submit"
                                            size="sm"
                                            variant="primary"
                                            color="emerald"
                                            ::disabled="isDeriving || !passphrase"
                                        >
                                            <span x-show="!isDeriving">{{ __('Unlock Tab') }}</span>
                                            <span x-show="isDeriving">{{ __('Deriving...') }}</span>
                                        </flux:button>
                                    </div>
                                </form>
                            </template>
                        </div>

                        @can('update', $vault)
                            <div class="pt-2 border-t border-emerald-500/15 flex justify-end">
                                <flux:button
                                    size="xs"
                                    variant="subtle"
                                    color="red"
                                    wire:click="disableE2ee"
                                    wire:confirm="Disable Zero-Knowledge E2EE for this vault? Existing notes will remain encrypted with the previous passphrase until re-synced."
                                >
                                    {{ __('Disable Encryption') }}
                                </flux:button>
                            </div>
                        @endcan
                    </div>
                @else
                    <div
                        x-data="{
                            slug: {{ Js::from($vault->slug) }},
                            passphrase: '',
                            confirmPassphrase: '',
                            isDeriving: false,
                            error: '',
                            async enable() {
                                this.error = '';
                                if (!this.passphrase || this.passphrase.length < 8) {
                                    this.error = 'Passphrase must be at least 8 characters long.';
                                    return;
                                }
                                if (this.passphrase !== this.confirmPassphrase) {
                                    this.error = 'Passphrases do not match.';
                                    return;
                                }
                                if (!window.VaultCrypto) {
                                    this.error = 'WebCrypto engine is not supported in this browser.';
                                    return;
                                }

                                this.isDeriving = true;
                                try {
                                    const salt = window.VaultCrypto.generateSalt();
                                    const key = await window.VaultCrypto.deriveKey(this.passphrase, salt);
                                    const testCipher = await window.VaultCrypto.createVerificationCipher(key);

                                    window.VaultCrypto.setSessionKey(this.slug, key);
                                    await $wire.enableE2ee(salt, testCipher);
                                } catch (e) {
                                    this.error = e.message || 'Key derivation failed.';
                                } finally {
                                    this.isDeriving = false;
                                }
                            }
                        }"
                        class="rounded-xl bg-zinc-50 dark:bg-zinc-800/60 p-4 border border-zinc-200 dark:border-zinc-700 space-y-4"
                    >
                        <div class="flex items-center gap-2 text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                            <flux:icon icon="lock-closed" class="size-4 text-emerald-500" />
                            <span>{{ __('Enable Zero-Knowledge E2EE in Browser') }}</span>
                        </div>

                        <p class="text-xs text-zinc-500">
                            {{ __('Configure a shared passphrase to activate AES-256-GCM encryption. Synkk runs PBKDF2 (100,000 rounds) directly in your browser using the W3C WebCrypto API. Plaintext notes and your passphrase will never touch the server.') }}
                        </p>

                        <template x-if="error">
                            <div class="p-2.5 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-xs flex items-center gap-2">
                                <flux:icon icon="exclamation-triangle" class="size-4 shrink-0" />
                                <span x-text="error"></span>
                            </div>
                        </template>

                        @can('update', $vault)
                            <form @submit.prevent="enable()" class="space-y-3 pt-1">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <flux:input
                                        type="password"
                                        x-model="passphrase"
                                        :label="__('Vault Passphrase')"
                                        placeholder="{{ __('Min 8 characters...') }}"
                                        size="sm"
                                        required
                                    />
                                    <flux:input
                                        type="password"
                                        x-model="confirmPassphrase"
                                        :label="__('Confirm Passphrase')"
                                        placeholder="{{ __('Repeat passphrase...') }}"
                                        size="sm"
                                        required
                                    />
                                </div>
                                <div class="flex justify-end pt-1">
                                    <flux:button
                                        type="submit"
                                        size="sm"
                                        variant="primary"
                                        color="emerald"
                                        ::disabled="isDeriving || !passphrase || !confirmPassphrase"
                                    >
                                        <span x-show="!isDeriving" class="flex items-center gap-1.5">
                                            <flux:icon icon="lock-closed" class="size-4" />
                                            {{ __('Derive Key & Enable E2EE') }}
                                        </span>
                                        <span x-show="isDeriving" class="flex items-center gap-2">
                                            <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                                            {{ __('Deriving (100,000 PBKDF2 rounds)...') }}
                                        </span>
                                    </flux:button>
                                </div>
                            </form>
                        @endcan
                    </div>
                @endif
            </flux:card>

            <!-- Vault Archive & Backup -->
            <flux:card class="space-y-3">
                <div class="flex items-start justify-between">
                    <div>
                        <flux:heading size="md">{{ __('Vault Archive & Backup') }}</flux:heading>
                        <flux:subheading class="text-xs">
                            {{ __('Download a standalone .zip archive of all active notes and folders in this vault.') }}
                        </flux:subheading>
                    </div>
                    <flux:button
                        variant="subtle"
                        size="sm"
                        icon="arrow-down-tray"
                        wire:click="exportVaultZip"
                    >
                        {{ __('Export Entire Vault (.zip)') }}
                    </flux:button>
                </div>
            </flux:card>

            <!-- Danger Zone -->
            <flux:card variant="soft" class="border-red-200/50 bg-red-50/20 dark:border-red-900/30 dark:bg-red-950/10 space-y-3">
                <flux:heading size="md" class="text-red-600 dark:text-red-400">{{ __('Danger Zone') }}</flux:heading>
                <flux:subheading class="text-xs">{{ __('Permanently remove this vault and all server-side sync logs.') }}</flux:subheading>

                <flux:button
                    variant="danger"
                    size="sm"
                    wire:click="deleteVault"
                    wire:confirm="Permanently delete this vault? All synced notes on the server will be removed."
                >
                    {{ __('Delete This Vault') }}
                </flux:button>
            </flux:card>
        </div>
    @endif

    <!-- Add Path Permission Modal -->
    <flux:modal name="add-path-permission" focusable class="max-w-md">
        <form wire:submit="addPathPermission" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Add Folder or File Rule') }}</flux:heading>
                <flux:subheading class="text-xs">{{ __('Configure path-level permissions for your team members.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:select wire:model="ruleUserId" :label="__('Target Member')">
                    <flux:select.option value="">{{ __('🌐 All Team Members (Default Scope)') }}</flux:select.option>
                    @foreach ($this->teamMembers as $member)
                        <flux:select.option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model="rulePath"
                    :label="__('Vault Path')"
                    placeholder="e.g. 01 - Projects or 02 - Finance/Salaries.md"
                    required
                />

                <flux:checkbox wire:model="ruleIsFolder" :label="__('Apply recursively to all notes in this directory')" />

                <flux:select wire:model="rulePermission" :label="__('Access Right')">
                    <flux:select.option value="read_write">{{ __('✅ Read & Write (Full Sync)') }}</flux:select.option>
                    <flux:select.option value="read_only">{{ __('👁️ Read-Only (Download Only)') }}</flux:select.option>
                    <flux:select.option value="hidden">{{ __('🔒 Hidden (Omitted from Sync Completely)') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save Permission') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Note Revision History Modal -->
    <flux:modal name="file-history" focusable class="max-w-xl">
        @if ($selectedFile)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg" class="flex items-center gap-2">
                        <flux:icon icon="clock" class="size-5 text-teal-600 dark:text-teal-400" />
                        <span>{{ __('Note Revision History') }}</span>
                    </flux:heading>
                    <flux:subheading class="font-mono text-xs text-zinc-500 truncate mt-1">
                        {{ $selectedFile->path }}
                    </flux:subheading>
                </div>

                <div class="rounded-lg border border-zinc-200 p-3 bg-zinc-50 dark:border-white/10 dark:bg-white/5 space-y-1">
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                        {{ __('Current Active Version:') }} <flux:badge color="emerald" size="sm">v{{ $selectedFile->version }}</flux:badge>
                    </div>
                    <div class="text-xs text-zinc-500">
                        {{ __('Last modified:') }} {{ $selectedFile->updated_at?->diffForHumans() }} {{ __('by') }} {{ $selectedFile->lastModifier?->name ?? __('Sync') }}
                    </div>
                </div>

                <div>
                    <flux:heading size="sm" class="mb-2">{{ __('Historical Version Snapshots') }}</flux:heading>

                    @if ($selectedFile->versions->isEmpty())
                        <div class="py-8 text-center text-xs text-zinc-500 dark:text-zinc-400 border rounded-lg border-dashed">
                            {{ __('No previous historical versions stored for this note yet. Modifying this file will generate automatic snapshot revisions.') }}
                        </div>
                    @else
                        <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                            @foreach ($selectedFile->versions as $version)
                                <div class="flex items-center justify-between p-3 rounded-lg border border-zinc-200 dark:border-white/10 bg-white dark:bg-zinc-900">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <flux:badge color="zinc" size="sm">v{{ $version->version }}</flux:badge>
                                            <span class="text-xs font-mono text-zinc-600 dark:text-zinc-400">{{ Number::fileSize($version->size, precision: 1) }}</span>
                                        </div>
                                        <div class="text-[11px] text-zinc-400 mt-0.5">
                                            {{ $version->created_at?->format('M j, Y g:i A') }} ({{ $version->created_at?->diffForHumans() }})
                                        </div>
                                    </div>

                                    <flux:button
                                        variant="subtle"
                                        size="xs"
                                        icon="arrow-path"
                                        wire:click="restoreVersion({{ $version->id }})"
                                        wire:confirm="Restore version v{{ $version->version }} as the active version of this note?"
                                    >
                                        {{ __('Restore') }}
                                    </flux:button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex justify-end pt-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Create New Note Modal -->
    <flux:modal name="new-note-modal" focusable class="max-w-md">
        <form wire:submit="createNewNote" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Create New Note') }}</flux:heading>
                <flux:subheading class="text-xs">{{ __('Create a new markdown note in this vault. Markdown will open in the editor immediately.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input
                    wire:model="newNotePath"
                    :label="__('Note Path / Name')"
                    placeholder="e.g. Daily/2026-09-05.md or Ideas.md"
                    description="Subdirectories will be created automatically. Extension (.md) is added if omitted."
                    required
                />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" class="!bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90">{{ __('Create Note') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Rename Note / File Modal -->
    <flux:modal name="rename-file-modal" focusable class="max-w-md">
        <form wire:submit="executeRename" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Rename Note / Path') }}</flux:heading>
                <flux:subheading class="text-xs">{{ __('Change the filename or move this note into another folder. Edits will synchronize across all paired Obsidian clients.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input
                    wire:model="renamingNewPath"
                    :label="__('Target Vault Path')"
                    placeholder="e.g. Archives/OldNotes.md"
                    description="Include the full relative path from vault root."
                    required
                />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" class="!bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90 font-semibold">{{ __('Rename') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Delete Note Confirmation Modal -->
    <flux:modal name="delete-file-modal" focusable class="max-w-md">
        <form wire:submit="executeDeleteFile" class="space-y-5">
            <div class="flex items-start gap-3">
                <div class="size-10 rounded-xl bg-rose-500/10 text-rose-600 flex items-center justify-center shrink-0">
                    <flux:icon icon="trash" class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg" class="text-rose-600 dark:text-rose-400">{{ __('Delete Note / File') }}</flux:heading>
                    <flux:subheading class="text-xs mt-1">
                        {{ __('Are you sure you want to delete this file? It will be marked as deleted and removed across connected devices upon next sync.') }}
                    </flux:subheading>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" type="submit">{{ __('Confirm Delete') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- File Inspector / Info Modal -->
    <flux:modal name="inspect-file-modal" focusable class="max-w-2xl">
        @if ($this->inspectedFile)
            @php
                $meta = $this->inspectedFileMetadata;
                $f = $this->inspectedFile;
                $isMd = $f->isMarkdown();
            @endphp
            <div class="space-y-5">
                <!-- Inspector Header -->
                <div class="flex items-start justify-between gap-3 border-b border-slate-200/80 pb-4 dark:border-zinc-800">
                    <div class="flex items-center gap-3">
                        <div class="size-10 rounded-xl flex items-center justify-center shrink-0 {{ $isMd ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-400' }}">
                            @if ($isMd)
                                <flux:icon icon="document-text" class="size-6" />
                            @else
                                <flux:icon icon="paper-clip" class="size-6" />
                            @endif
                        </div>
                        <div>
                            <flux:heading size="lg" class="truncate max-w-sm">{{ basename($f->path) }}</flux:heading>
                            <flux:subheading class="font-mono text-xs text-slate-400 dark:text-zinc-500 truncate max-w-sm mt-0.5">
                                {{ $f->path }}
                            </flux:subheading>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        @if ($isMd)
                            <flux:button
                                variant="primary"
                                size="sm"
                                icon="pencil-square"
                                wire:click="openFileInEditor({{ $f->id }})"
                                class="!bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90 font-semibold cursor-pointer"
                            >
                                {{ __('Open Editor') }}
                            </flux:button>
                        @endif
                        <flux:button
                            variant="subtle"
                            size="sm"
                            icon="arrow-down-tray"
                            wire:click="downloadFile({{ $f->id }})"
                            class="cursor-pointer"
                            title="{{ __('Download') }}"
                        />
                    </div>
                </div>

                @if ($isMd)
                    <!-- Text Metrics Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 p-3 dark:border-zinc-800 dark:bg-zinc-900/50 text-center">
                            <span class="text-[10px] uppercase tracking-wider font-semibold text-slate-400 dark:text-zinc-500">{{ __('Words') }}</span>
                            <p class="text-lg font-extrabold text-slate-900 dark:text-white mt-0.5">{{ number_format($meta['words']) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 p-3 dark:border-zinc-800 dark:bg-zinc-900/50 text-center">
                            <span class="text-[10px] uppercase tracking-wider font-semibold text-slate-400 dark:text-zinc-500">{{ __('Lines') }}</span>
                            <p class="text-lg font-extrabold text-slate-900 dark:text-white mt-0.5">{{ number_format($meta['lines']) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 p-3 dark:border-zinc-800 dark:bg-zinc-900/50 text-center">
                            <span class="text-[10px] uppercase tracking-wider font-semibold text-slate-400 dark:text-zinc-500">{{ __('Characters') }}</span>
                            <p class="text-lg font-extrabold text-slate-900 dark:text-white mt-0.5">{{ number_format($meta['chars']) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 p-3 dark:border-zinc-800 dark:bg-zinc-900/50 text-center">
                            <span class="text-[10px] uppercase tracking-wider font-semibold text-slate-400 dark:text-zinc-500">{{ __('Read Time') }}</span>
                            <p class="text-lg font-extrabold text-slate-900 dark:text-white mt-0.5">{{ $meta['read_time'] }}</p>
                        </div>
                    </div>
                @endif

                <!-- File Specs & Integrity Details -->
                <div class="rounded-xl border border-slate-200/80 bg-slate-50/50 p-4 dark:border-zinc-800 dark:bg-zinc-900/50 space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400">{{ __('File Properties & Sync Integrity') }}</h4>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-slate-400 dark:text-zinc-500">{{ __('File Size:') }}</span>
                            <span class="font-semibold text-slate-800 dark:text-zinc-200 ml-1.5">{{ Number::fileSize($f->size, precision: 2) }} ({{ number_format($f->size) }} bytes)</span>
                        </div>

                        <div>
                            <span class="text-slate-400 dark:text-zinc-500">{{ __('Active Version:') }}</span>
                            <span class="font-semibold text-slate-800 dark:text-zinc-200 ml-1.5">v{{ $f->version }} ({{ $f->versions->count() }} snapshot revisions)</span>
                        </div>

                        <div>
                            <span class="text-slate-400 dark:text-zinc-500">{{ __('Last Modified:') }}</span>
                            <span class="font-semibold text-slate-800 dark:text-zinc-200 ml-1.5">{{ $f->updated_at?->format('M j, Y g:i A') }} ({{ $f->updated_at?->diffForHumans() }})</span>
                        </div>

                        <div>
                            <span class="text-slate-400 dark:text-zinc-500">{{ __('Modifier:') }}</span>
                            <span class="font-semibold text-slate-800 dark:text-zinc-200 ml-1.5">{{ $f->lastModifier?->name ?? 'Sync Engine' }}</span>
                        </div>
                    </div>

                    <!-- SHA-256 Hash -->
                    <div class="pt-2 border-t border-slate-200/60 dark:border-zinc-800">
                        <span class="text-[11px] font-semibold text-slate-500 dark:text-zinc-400">{{ __('SHA-256 Checksum:') }}</span>
                        <div class="mt-1 flex items-center gap-2">
                            <code class="font-mono text-[11px] bg-slate-200/60 dark:bg-zinc-800 px-2 py-1 rounded-md text-slate-800 dark:text-zinc-200 truncate flex-1 select-all">
                                {{ $f->sha256 }}
                            </code>
                            <button
                                type="button"
                                x-on:click="navigator.clipboard.writeText('{{ $f->sha256 }}'); $wire.copyWikilinkNotice('SHA-256 Hash')"
                                class="px-2 py-1 text-xs rounded-md bg-slate-200/80 hover:bg-slate-300 dark:bg-zinc-800 dark:hover:bg-zinc-700 font-semibold cursor-pointer shrink-0"
                            >
                                {{ __('Copy') }}
                            </button>
                        </div>
                    </div>
                </div>

                @if (! empty($meta['wikilinks']) || ! empty($meta['tags']))
                    <!-- Wikilinks & Tags detected -->
                    <div class="space-y-2.5">
                        @if (! empty($meta['wikilinks']))
                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">{{ __('Outbound Wikilinks:') }}</span>
                                <div class="flex flex-wrap gap-1.5 mt-1.5">
                                    @foreach ($meta['wikilinks'] as $link)
                                        <span class="rounded-lg bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                            [[{{ $link }}]]
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if (! empty($meta['tags']))
                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">{{ __('Tags:') }}</span>
                                <div class="flex flex-wrap gap-1.5 mt-1.5">
                                    @foreach ($meta['tags'] as $tag)
                                        <span class="rounded-lg bg-indigo-500/10 border border-indigo-500/20 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:text-indigo-300">
                                            #{{ $tag }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if ($meta['excerpt'])
                    <!-- Excerpt Preview -->
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">{{ __('Content Preview:') }}</span>
                        <div class="mt-1.5 rounded-xl border border-slate-200/80 bg-white p-3 font-mono text-xs text-slate-700 leading-relaxed dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300 max-h-36 overflow-y-auto">
                            {{ $meta['excerpt'] }}...
                        </div>
                    </div>
                @endif

                <!-- Inspector Footer Actions -->
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200/80 pt-4 dark:border-zinc-800">
                    <div class="flex items-center gap-1.5">
                        <flux:button size="sm" variant="subtle" icon="document-duplicate" wire:click="duplicateFile({{ $f->id }})" class="cursor-pointer">
                            {{ __('Duplicate') }}
                        </flux:button>
                        <flux:button size="sm" variant="subtle" icon="pencil" wire:click="openRenameModal({{ $f->id }})" class="cursor-pointer">
                            {{ __('Rename') }}
                        </flux:button>
                        <flux:button size="sm" variant="subtle" icon="clock" wire:click="showFileHistory({{ $f->id }})" class="cursor-pointer">
                            {{ __('History') }}
                        </flux:button>
                    </div>

                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- 3-Way Visual Conflict Sandbox Modal -->
    <flux:modal name="conflict-sandbox-modal" focusable class="max-w-5xl">
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-800 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg" class="text-white flex items-center gap-2">
                            <flux:icon icon="arrows-right-left" class="size-5 text-amber-400" />
                            {{ __('3-Way Visual Conflict Sandbox') }}
                        </flux:heading>
                        @if ($conflictHasConflicts)
                            <flux:badge color="amber" size="sm">{{ $conflictCount }} {{ __('conflicts') }}</flux:badge>
                        @else
                            <flux:badge color="emerald" size="sm">{{ __('All clean / resolved') }}</flux:badge>
                        @endif
                    </div>
                    <flux:subheading class="text-xs text-zinc-400 mt-1">
                        {{ __('Side-by-side reconciliation between your canonical note and the incoming conflict copy.') }}
                    </flux:subheading>
                </div>
                <div class="flex flex-col sm:items-end text-xs text-zinc-400 font-mono">
                    <span class="text-zinc-200 truncate max-w-xs">{{ $conflictCanonicalPath }}</span>
                    <span class="text-amber-400/80 truncate max-w-xs text-[11px]">{{ $conflictPath }}</span>
                </div>
            </div>

            <!-- Side by Side Preview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Left: Current Note (Ours) -->
                <div class="rounded-xl border border-zinc-700/60 bg-zinc-900/90 p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-400 flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-emerald-400"></span>
                            {{ __('Current Note (Ours)') }}
                        </span>
                        <span class="text-[10px] font-mono text-zinc-500">v{{ $conflictCanonicalVersion ?? $activeFile?->version ?? 1 }}</span>
                    </div>
                    <pre class="text-xs font-mono text-zinc-300 max-h-48 overflow-y-auto bg-black/40 p-3 rounded-lg border border-zinc-800/80 whitespace-pre-wrap">{{ $conflictCanonicalContent ?: __('(Empty note)') }}</pre>
                </div>

                <!-- Right: Conflict Note (Theirs) -->
                <div class="rounded-xl border border-zinc-700/60 bg-zinc-900/90 p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-amber-400"></span>
                            {{ __('Incoming Conflict Revision (Theirs)') }}
                        </span>
                        <span class="text-[10px] font-mono text-zinc-500">{{ __('Unmerged') }}</span>
                    </div>
                    <pre class="text-xs font-mono text-zinc-300 max-h-48 overflow-y-auto bg-black/40 p-3 rounded-lg border border-zinc-800/80 whitespace-pre-wrap">{{ $conflictTheirsContent ?: __('(Empty note)') }}</pre>
                </div>
            </div>

            <!-- Hunks Resolution Controls -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Section-by-Section Resolution') }}</h4>
                    <span class="text-[11px] text-zinc-500">{{ count($conflictHunks) }} {{ __('total sections') }}</span>
                </div>

                <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
                    @foreach ($conflictHunks as $hunk)
                        @if ($hunk['is_conflict'])
                            <div class="rounded-xl border border-amber-500/40 bg-amber-950/20 p-4 space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-amber-500/20 pb-2">
                                    <span class="text-xs font-bold text-amber-300 flex items-center gap-1.5">
                                        <flux:icon icon="exclamation-circle" class="size-4 text-amber-400" />
                                        {{ __('Conflict Section #') }}{{ $hunk['id'] + 1 }}
                                    </span>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <button
                                            type="button"
                                            wire:click="setHunkResolution({{ $hunk['id'] }}, 'ours')"
                                            class="px-2.5 py-1 rounded text-[11px] font-semibold transition-colors cursor-pointer {{ ($hunk['choice'] ?? '') === 'ours' ? 'bg-emerald-600 text-white font-bold' : 'bg-zinc-800 text-zinc-300 hover:bg-zinc-700' }}"
                                        >
                                            {{ __('Keep Ours') }}
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="setHunkResolution({{ $hunk['id'] }}, 'theirs')"
                                            class="px-2.5 py-1 rounded text-[11px] font-semibold transition-colors cursor-pointer {{ ($hunk['choice'] ?? '') === 'theirs' ? 'bg-amber-600 text-white font-bold' : 'bg-zinc-800 text-zinc-300 hover:bg-zinc-700' }}"
                                        >
                                            {{ __('Keep Theirs') }}
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="setHunkResolution({{ $hunk['id'] }}, 'both_ours_first')"
                                            class="px-2.5 py-1 rounded text-[11px] font-semibold transition-colors cursor-pointer {{ ($hunk['choice'] ?? '') === 'both_ours_first' ? 'bg-indigo-600 text-white font-bold' : 'bg-zinc-800 text-zinc-300 hover:bg-zinc-700' }}"
                                        >
                                            {{ __('Both (Ours 1st)') }}
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="setHunkResolution({{ $hunk['id'] }}, 'both_theirs_first')"
                                            class="px-2.5 py-1 rounded text-[11px] font-semibold transition-colors cursor-pointer {{ ($hunk['choice'] ?? '') === 'both_theirs_first' ? 'bg-indigo-600 text-white font-bold' : 'bg-zinc-800 text-zinc-300 hover:bg-zinc-700' }}"
                                        >
                                            {{ __('Both (Theirs 1st)') }}
                                        </button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs font-mono">
                                    <div class="bg-emerald-950/30 border border-emerald-500/20 p-2.5 rounded text-emerald-200">
                                        <div class="text-[10px] text-emerald-400 font-bold mb-1">{{ __('Ours:') }}</div>
                                        {{ implode("\n", $hunk['our_lines']) }}
                                    </div>
                                    <div class="bg-amber-950/30 border border-amber-500/20 p-2.5 rounded text-amber-200">
                                        <div class="text-[10px] text-amber-400 font-bold mb-1">{{ __('Theirs:') }}</div>
                                        {{ implode("\n", $hunk['their_lines']) }}
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-zinc-900/60 border border-zinc-800 text-xs text-zinc-400">
                                <span class="flex items-center gap-1.5">
                                    <flux:icon icon="check" class="size-3.5 text-emerald-400" />
                                    <span>{{ $hunk['type'] === 'clean' ? __('Clean identical section') : ($hunk['type'] === 'ours' ? __('Clean change from ours') : __('Clean change from theirs')) }}</span>
                                </span>
                                <span class="text-[10px] font-mono text-zinc-500">{{ count($hunk['resolved_lines'] ?? $hunk['our_lines']) }} {{ __('lines') }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            <!-- Unified Reconciled Note Preview (Editable) -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-bold uppercase tracking-wider text-emerald-400 flex items-center gap-1.5">
                        <flux:icon icon="sparkles" class="size-3.5 text-emerald-400" />
                        {{ __('Final Reconciled Note Preview (Editable)') }}
                    </label>
                    <span class="text-[11px] text-zinc-400">{{ __('Edits made here will be written directly as the new note revision.') }}</span>
                </div>
                <textarea
                    wire:model="conflictReconciledContent"
                    rows="8"
                    class="w-full rounded-xl border border-zinc-700/80 bg-zinc-950 p-3 font-mono text-xs text-zinc-100 placeholder-zinc-500 focus:border-emerald-500 focus:outline-hidden focus:ring-1 focus:ring-emerald-500"
                    placeholder="{{ __('Reconciled content...') }}"
                ></textarea>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between pt-2 border-t border-zinc-800">
                <div class="text-xs text-zinc-400">
                    {{ __('Reconciling will archive the conflict copy and save this note as the next active version.') }}
                </div>
                <div class="flex items-center gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button
                        variant="primary"
                        wire:click="executeConflictResolution"
                        class="!bg-emerald-600 hover:!bg-emerald-500 !text-white font-bold"
                    >
                        <flux:icon icon="check" class="size-4 mr-1" />
                        {{ __('Reconcile & Merge Note') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
