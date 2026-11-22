<?php

namespace App\Services\Ai;

use App\Actions\Vaults\SyncUploadAction;
use App\Exceptions\AiException;
use App\Models\AiProposal;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultFileVersion;
use App\Services\ThreeWayDiffService;
use Illuminate\Support\Facades\Storage;

class AiProposalService
{
    public function __construct(
        protected SyncUploadAction $syncUploadAction,
        protected ThreeWayDiffService $diffService,
    ) {}

    /**
     * Normalize and validate a vault-relative Markdown path.
     *
     * @throws AiException
     */
    public function normalizePath(string $rawPath): string
    {
        $clean = trim(str_replace('\\', '/', $rawPath), '/');
        if ($clean === '' || str_contains($clean, "\0")) {
            throw new AiException('Target note path cannot be empty.', 'INVALID_PATH', 422);
        }

        $segments = explode('/', $clean);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new AiException("Invalid path traversal or segment in '{$rawPath}'.", 'INVALID_PATH', 422);
            }
        }

        if (pathinfo($clean, PATHINFO_EXTENSION) === '') {
            $clean .= '.md';
        }

        return $clean;
    }

    /**
     * Stage a new or updated Markdown note inside the Vault Diff Approval Inbox.
     *
     * @param array{
     *     target_path: string,
     *     proposed_content: string,
     *     source?: string,
     *     action?: string,
     *     destination_path?: string|null,
     *     rationale?: string|null,
     *     metadata?: array<string, mixed>|null
     * } $data
     *
     * @throws AiException
     */
    public function stageProposal(Vault $vault, ?User $creator, array $data): AiProposal
    {
        if ($vault->is_e2ee) {
            throw new AiException(
                'Zero-Knowledge E2EE vaults do not allow server-side AI proposals.',
                'E2EE_BLOCKED',
                403,
            );
        }

        $targetPath = $this->normalizePath($data['target_path']);

        if ($vault->aiSetting?->isPathExcluded($targetPath)) {
            throw new AiException(
                "Path '{$targetPath}' is excluded from AI access.",
                'PATH_EXCLUDED',
                403,
            );
        }

        if ($creator !== null && $vault->permissionForPath($creator, $targetPath) !== 'read_write') {
            throw new AiException(
                "You do not have write permission to stage changes for '{$targetPath}'.",
                'FORBIDDEN',
                403,
            );
        }

        $existingFile = $vault->files()
            ->where('path', $targetPath)
            ->where('is_deleted', false)
            ->first();

        $action = (string) ($data['action'] ?? ($existingFile ? 'update' : 'create'));
        if (! in_array($action, ['create', 'update', 'append', 'move'], true)) {
            $action = $existingFile ? 'update' : 'create';
        }

        $proposedContent = $data['proposed_content'];
        if ($action === 'append' && $existingFile !== null) {
            $currentContents = (string) ($existingFile->getContents() ?? '');
            $proposedContent = $currentContents !== ''
                ? rtrim($currentContents)."\n\n".ltrim($proposedContent)
                : $proposedContent;
        }

        $destinationPath = isset($data['destination_path']) && trim($data['destination_path']) !== ''
            ? $this->normalizePath($data['destination_path'])
            : null;

        return AiProposal::query()->create([
            'team_id' => $vault->team_id,
            'vault_id' => $vault->id,
            'vault_file_id' => $existingFile?->id,
            'created_by_user_id' => $creator?->id,
            'source' => (string) ($data['source'] ?? 'copilot'),
            'action' => $action,
            'target_path' => $targetPath,
            'destination_path' => $destinationPath,
            'base_version' => $existingFile?->version,
            'base_content_hash' => $existingFile?->sha256,
            'proposed_content' => $proposedContent,
            'rationale' => $data['rationale'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'status' => 'pending',
        ]);
    }

    /**
     * Build a 3-way diff preview comparing base version, current vault content, and proposed content.
     *
     * @return array{
     *     is_stale: bool,
     *     base_version: int|null,
     *     current_version: int|null,
     *     current_content: string,
     *     proposed_content: string,
     *     diff: array{
     *         has_conflicts: bool,
     *         conflict_count: int,
     *         clean_count: int,
     *         merged_content: string,
     *         hunks: array<int, array<string, mixed>>
     *     }
     * }
     */
    public function buildDiffPreview(AiProposal $proposal): array
    {
        $vault = $proposal->vault;
        $currentFile = $vault->files()
            ->where('path', $proposal->target_path)
            ->where('is_deleted', false)
            ->first();

        $currentContent = $currentFile !== null ? (string) ($currentFile->getContents() ?? '') : '';
        $isStale = $currentFile !== null
            && $proposal->base_version !== null
            && $currentFile->version !== $proposal->base_version;

        $baseContent = $currentContent;

        if ($isStale) {
            $ancestorVersion = VaultFileVersion::query()
                ->where('vault_file_id', $currentFile->id)
                ->where('version', $proposal->base_version)
                ->first();

            $disk = (string) config('synkk.storage_disk', 'local');
            $baseContent = ($ancestorVersion !== null && Storage::disk($disk)->exists($ancestorVersion->storage_path))
                ? (string) Storage::disk($disk)->get($ancestorVersion->storage_path)
                : '';
        } elseif ($currentFile === null) {
            $baseContent = '';
        }

        $diff = $this->diffService->merge($baseContent, $currentContent, $proposal->proposed_content);

        return [
            'is_stale' => $isStale,
            'base_version' => $proposal->base_version,
            'current_version' => $currentFile?->version,
            'current_content' => $currentContent,
            'proposed_content' => $proposal->proposed_content,
            'diff' => $diff,
        ];
    }

    /**
     * Approve and materialize a staged proposal into the vault as a versioned sync commit.
     *
     * @return array{
     *     proposal: AiProposal,
     *     sync: array<string, mixed>,
     *     stale_merged: bool,
     *     has_conflicts: bool
     * }
     *
     * @throws AiException
     */
    public function approve(AiProposal $proposal, User $reviewer, ?string $overrideContent = null): array
    {
        if ($proposal->status !== 'pending') {
            throw new AiException('This proposal has already been reviewed.', 'INVALID_STATE', 409);
        }

        $vault = $proposal->vault;

        if ($vault->is_e2ee) {
            throw new AiException('Zero-Knowledge E2EE vaults cannot apply server-side AI proposals.', 'E2EE_BLOCKED', 403);
        }

        if ($vault->aiSetting?->isPathExcluded($proposal->target_path)) {
            throw new AiException("Path '{$proposal->target_path}' is excluded from AI access.", 'PATH_EXCLUDED', 403);
        }

        if ($vault->permissionForPath($reviewer, $proposal->target_path) !== 'read_write') {
            throw new AiException("Write permission denied for '{$proposal->target_path}'.", 'FORBIDDEN', 403);
        }

        $preview = $this->buildDiffPreview($proposal);
        $staleMerged = false;

        if ($overrideContent !== null) {
            $finalContent = $overrideContent;
        } elseif ($preview['is_stale']) {
            $finalContent = $preview['diff']['merged_content'];
            $staleMerged = true;
        } else {
            $finalContent = $proposal->proposed_content;
        }

        $syncResult = $this->syncUploadAction->execute(
            vault: $vault,
            user: $reviewer,
            deviceName: "Synkk AI ({$proposal->source})",
            path: $proposal->target_path,
            contents: $finalContent,
            baseVersion: $preview['current_version'] ?? 0,
        );

        $savedFile = $vault->files()
            ->where('path', $proposal->target_path)
            ->where('is_deleted', false)
            ->first();

        $proposal->forceFill([
            'status' => 'approved',
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'vault_file_id' => $savedFile !== null ? $savedFile->id : $proposal->vault_file_id,
        ])->save();

        /** @var AiProposal $freshProposal */
        $freshProposal = $proposal->fresh();

        return [
            'proposal' => $freshProposal,
            'sync' => $syncResult,
            'stale_merged' => $staleMerged,
            'has_conflicts' => $preview['diff']['has_conflicts'],
        ];
    }

    /**
     * Reject and archive a pending proposal without modifying any vault file.
     *
     * @throws AiException
     */
    public function reject(AiProposal $proposal, User $reviewer): AiProposal
    {
        if ($proposal->status !== 'pending') {
            throw new AiException('This proposal has already been reviewed.', 'INVALID_STATE', 409);
        }

        $proposal->forceFill([
            'status' => 'rejected',
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();

        /** @var AiProposal $freshProposal */
        $freshProposal = $proposal->fresh();

        return $freshProposal;
    }
}
