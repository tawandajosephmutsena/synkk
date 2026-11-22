<?php

namespace App\Services\Ai;

use App\Actions\Vaults\SyncUploadAction;
use App\Exceptions\AiException;
use App\Jobs\RunVaultGardenerJob;
use App\Models\TeamNotification;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultAiSetting;
use App\Models\VaultFile;
use App\Services\KnowledgeGraphService;
use App\Services\PlanService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class VaultGardenerService
{
    /** @var array<int, string> */
    public const DEFAULT_INBOX_FOLDERS = ['Inbox'];

    public function __construct(
        protected AiGateway $aiGateway,
        protected AiProposalService $proposalService,
        protected HybridVaultSearchService $hybridSearch,
        protected KnowledgeGraphService $graphService,
        protected SyncUploadAction $syncUploadAction,
        protected PlanService $planService,
    ) {}

    /**
     * Normalize inbox folders list cleanly over safe defaults.
     *
     * @param  array<int, string>|null  $folders
     * @return array<int, string>
     */
    public function normalizeInboxFolders(?array $folders): array
    {
        if (! is_array($folders) || empty($folders)) {
            return self::DEFAULT_INBOX_FOLDERS;
        }

        $clean = [];
        foreach ($folders as $folder) {
            $trimmed = trim(str_replace('\\', '/', trim((string) $folder)), '/');
            if ($trimmed === '' || str_contains($trimmed, '..') || str_contains($trimmed, "\0")) {
                continue;
            }

            $clean[] = $trimmed;
        }

        return ! empty($clean) ? array_values(array_unique($clean)) : self::DEFAULT_INBOX_FOLDERS;
    }

    /**
     * Determine whether a note resides inside any configured inbox folder.
     *
     * @param  array<int, string>  $inboxFolders
     */
    public function isInInboxFolder(string $path, array $inboxFolders): bool
    {
        $cleanPath = trim(str_replace('\\', '/', $path), '/');
        if ($cleanPath === '') {
            return false;
        }

        foreach ($inboxFolders as $folder) {
            $cleanFolder = trim(str_replace('\\', '/', $folder), '/');
            if ($cleanFolder !== '' && stripos($cleanPath, $cleanFolder.'/') === 0) {
                return true;
            }
        }

        return false;
    }

    public function debounceCacheKey(int $vaultId, int $vaultFileId): string
    {
        return "ai_gardener_debounce:{$vaultId}:{$vaultFileId}";
    }

    /**
     * Determine whether a synced note upload should schedule a debounced Gardener run.
     */
    public function shouldDebounceGarden(Vault $vault, string $path, string $deviceName = ''): bool
    {
        if ($vault->is_e2ee) {
            return false;
        }

        if (str_starts_with($deviceName, 'Synkk AI')) {
            return false;
        }

        if (! str_ends_with(strtolower($path), '.md')) {
            return false;
        }

        if (str_contains($path, '.conflict-') || str_contains($path, '.sync-conflict-')) {
            return false;
        }

        $aiConfig = $vault->team->aiConfig;
        if ($aiConfig === null || ! $aiConfig->is_enabled || $aiConfig->provider_mode === 'off') {
            return false;
        }

        $setting = $vault->aiSetting;
        if (
            $setting === null
            || $setting->ai_mode !== 'server'
            || ! $setting->gardener_enabled
            || $setting->gardener_schedule !== 'debounced'
        ) {
            return false;
        }

        if (! $this->planService->hasFeature($vault->team, 'ai_gardener')) {
            return false;
        }

        $cleanPath = trim(str_replace('\\', '/', $path), '/');
        if ($cleanPath === '' || $setting->isPathExcluded($cleanPath)) {
            return false;
        }

        $digestFolder = $this->sanitizeFolder($setting->digest_folder, 'Digests');
        if (strcasecmp($cleanPath, $digestFolder) === 0 || stripos($cleanPath, $digestFolder.'/') === 0) {
            return false;
        }

        $inboxFolders = $this->normalizeInboxFolders($setting->inbox_folders);

        return $this->isInInboxFolder($cleanPath, $inboxFolders)
            || $setting->gardener_graph_linker
            || $setting->gardener_contradiction_check;
    }

    /**
     * Store a fresh debounce token and dispatch a delayed background job on `ai-background`.
     */
    public function scheduleDebouncedRun(Vault $vault, VaultFile $file, string $deviceName = ''): bool
    {
        if (! $this->shouldDebounceGarden($vault, $file->path, $deviceName)) {
            return false;
        }

        $setting = $vault->aiSetting;
        if ($setting === null) {
            return false;
        }

        $inboxFolders = $this->normalizeInboxFolders($setting->inbox_folders);
        $inInbox = $this->isInInboxFolder($file->path, $inboxFolders);

        $configuredMinutes = $setting->gardener_debounce_minutes > 0 ? $setting->gardener_debounce_minutes : 5;
        $delayMinutes = $inInbox
            ? max(1, min(60, $configuredMinutes))
            : max(10, min(60, $configuredMinutes));

        $debounceToken = (string) Str::ulid();
        Cache::put(
            $this->debounceCacheKey($vault->id, $file->id),
            $debounceToken,
            now()->addMinutes($delayMinutes + 15),
        );

        RunVaultGardenerJob::dispatch($vault->id, $file->id, $debounceToken)
            ->delay(now()->addMinutes($delayMinutes));

        return true;
    }

    /**
     * Run enabled Gardener automations (Inbox Triage, Graph Linker, Drift Detector) on one note.
     *
     * @return array{
     *     path: string,
     *     skipped: bool,
     *     reason: string|null,
     *     proposals_created: int,
     *     direct_updates: int,
     *     drift_alerts: int,
     *     proposal_ids: array<int, int>
     * }
     *
     * @throws AiException
     */
    public function runForFile(Vault $vault, VaultFile $file, ?User $triggeredBy = null, bool $force = false): array
    {
        $setting = $this->assertGardenerReady($vault, $triggeredBy, $force);
        $vault->loadMissing('permissions');

        $cleanPath = trim(str_replace('\\', '/', $file->path), '/');
        $digestFolder = $this->sanitizeFolder($setting->digest_folder, 'Digests');

        if (
            ! $file->isMarkdown()
            || $file->is_deleted
            || $file->is_encrypted
            || str_contains($cleanPath, '.conflict-')
            || str_contains($cleanPath, '.sync-conflict-')
            || $setting->isPathExcluded($cleanPath)
            || strcasecmp($cleanPath, $digestFolder) === 0
            || stripos($cleanPath, $digestFolder.'/') === 0
            || $this->isRestrictedByHiddenAcl($vault, $cleanPath)
            || ($triggeredBy !== null && $vault->permissionForPath($triggeredBy, $cleanPath) !== 'read_write')
        ) {
            return $this->skippedFileResult($file->path, 'ineligible_or_restricted');
        }

        $content = (string) ($file->getContents() ?? '');
        if (trim($content) === '') {
            return $this->skippedFileResult($file->path, 'empty_note');
        }

        $proposalsCreated = 0;
        $directUpdates = 0;
        $driftAlerts = 0;
        $proposalIds = [];

        $inboxFolders = $this->normalizeInboxFolders($setting->inbox_folders);

        // Task A: Inbox Auto-Triage
        if ($this->isInInboxFolder($cleanPath, $inboxFolders)) {
            $triageResult = $this->runInboxTriage(
                vault: $vault,
                file: $file,
                content: $content,
                setting: $setting,
                inboxFolders: $inboxFolders,
                digestFolder: $digestFolder,
                triggeredBy: $triggeredBy,
            );

            $proposalsCreated += $triageResult['proposals_created'];
            $directUpdates += $triageResult['direct_updates'];
            $content = $triageResult['content'];
            $file = $triageResult['file'];
            foreach ($triageResult['proposal_ids'] as $id) {
                $proposalIds[] = $id;
            }
        }

        $candidates = [];
        if ($setting->gardener_graph_linker || $setting->gardener_contradiction_check) {
            $candidates = $this->findCandidateRelatedNotes(
                vault: $vault,
                sourceFile: $file,
                sourceContent: $content,
                setting: $setting,
                digestFolder: $digestFolder,
                triggeredBy: $triggeredBy,
                excludeAlreadyLinked: false,
                limit: 8,
            );
        }

        // Task B: Graph Linker
        if ($setting->gardener_graph_linker) {
            $unlinkedCandidates = array_values(array_filter(
                $candidates,
                fn (array $candidate): bool => ! $this->isAlreadyLinked($content, $candidate['path']),
            ));

            if (! empty($unlinkedCandidates)) {
                $linkerResult = $this->runGraphLinker(
                    vault: $vault,
                    file: $file,
                    content: $content,
                    candidates: array_slice($unlinkedCandidates, 0, 5),
                    triggeredBy: $triggeredBy,
                );

                $proposalsCreated += $linkerResult['proposals_created'];
                foreach ($linkerResult['proposal_ids'] as $id) {
                    $proposalIds[] = $id;
                }
            }
        }

        // Task C: Contradiction / Drift Detector
        if ($setting->gardener_contradiction_check) {
            $driftNeighbors = $this->findDriftNeighbors(
                vault: $vault,
                sourceFile: $file,
                candidates: $candidates,
                setting: $setting,
                digestFolder: $digestFolder,
                triggeredBy: $triggeredBy,
                limit: 4,
            );

            if (! empty($driftNeighbors)) {
                $driftResult = $this->runDriftDetection(
                    vault: $vault,
                    file: $file,
                    content: $content,
                    neighbors: $driftNeighbors,
                    triggeredBy: $triggeredBy,
                );

                $proposalsCreated += $driftResult['proposals_created'];
                $driftAlerts += $driftResult['drift_alerts'];
                foreach ($driftResult['proposal_ids'] as $id) {
                    $proposalIds[] = $id;
                }
            }
        }

        return [
            'path' => $file->path,
            'skipped' => false,
            'reason' => null,
            'proposals_created' => $proposalsCreated,
            'direct_updates' => $directUpdates,
            'drift_alerts' => $driftAlerts,
            'proposal_ids' => $proposalIds,
        ];
    }

    /**
     * Sweep opted-in vault notes prioritized by Inbox location and recent updates.
     *
     * @return array{
     *     status: string,
     *     files_scanned: int,
     *     proposals_created: int,
     *     direct_updates: int,
     *     drift_alerts: int,
     *     proposal_ids: array<int, int>
     * }
     *
     * @throws AiException
     */
    public function runForVault(Vault $vault, ?User $triggeredBy = null, int $maxFiles = 10, bool $force = false): array
    {
        $setting = $this->assertGardenerReady($vault, $triggeredBy, $force);
        $vault->loadMissing('permissions');

        $inboxFolders = $this->normalizeInboxFolders($setting->inbox_folders);
        $digestFolder = $this->sanitizeFolder($setting->digest_folder, 'Digests');
        $since = (! $force && $setting->last_gardened_at !== null) ? $setting->last_gardened_at : null;

        $query = $vault->files()
            ->where('is_deleted', false)
            ->where('is_encrypted', false)
            ->where('path', 'like', '%.md')
            ->orderByDesc('updated_at');

        if ($since !== null) {
            $query->where('updated_at', '>=', $since);
        }

        $rawFiles = $query->limit(max(25, $maxFiles * 4))->get();

        $eligibleFiles = $rawFiles->filter(function (VaultFile $file) use ($vault, $setting, $digestFolder, $triggeredBy): bool {
            $cleanPath = trim(str_replace('\\', '/', $file->path), '/');
            if ($cleanPath === '' || str_contains($cleanPath, '.conflict-') || str_contains($cleanPath, '.sync-conflict-')) {
                return false;
            }

            if ($setting->isPathExcluded($cleanPath)) {
                return false;
            }

            if (strcasecmp($cleanPath, $digestFolder) === 0 || stripos($cleanPath, $digestFolder.'/') === 0) {
                return false;
            }

            if ($this->isRestrictedByHiddenAcl($vault, $cleanPath)) {
                return false;
            }

            if ($triggeredBy !== null && $vault->permissionForPath($triggeredBy, $cleanPath) !== 'read_write') {
                return false;
            }

            return true;
        });

        // Prioritize Inbox notes first, then most recently updated files
        $sortedFiles = $eligibleFiles->sort(function (VaultFile $a, VaultFile $b) use ($inboxFolders): int {
            $aInInbox = $this->isInInboxFolder($a->path, $inboxFolders) ? 1 : 0;
            $bInInbox = $this->isInInboxFolder($b->path, $inboxFolders) ? 1 : 0;

            if ($aInInbox !== $bInInbox) {
                return $bInInbox <=> $aInInbox;
            }

            return ($b->updated_at?->getTimestamp() ?? 0) <=> ($a->updated_at?->getTimestamp() ?? 0);
        })->take(max(1, min(50, $maxFiles)))->values();

        $filesScanned = 0;
        $proposalsCreated = 0;
        $directUpdates = 0;
        $driftAlerts = 0;
        $proposalIds = [];

        foreach ($sortedFiles as $file) {
            $result = $this->runForFile($vault, $file, $triggeredBy, $force);
            if ($result['skipped']) {
                continue;
            }

            $filesScanned++;
            $proposalsCreated += $result['proposals_created'];
            $directUpdates += $result['direct_updates'];
            $driftAlerts += $result['drift_alerts'];
            foreach ($result['proposal_ids'] as $id) {
                $proposalIds[] = $id;
            }
        }

        $setting->forceFill(['last_gardened_at' => now()])->save();

        return [
            'status' => 'completed',
            'files_scanned' => $filesScanned,
            'proposals_created' => $proposalsCreated,
            'direct_updates' => $directUpdates,
            'drift_alerts' => $driftAlerts,
            'proposal_ids' => $proposalIds,
        ];
    }

    /**
     * @throws AiException
     */
    protected function assertGardenerReady(Vault $vault, ?User $triggeredBy, bool $force): VaultAiSetting
    {
        if ($vault->is_e2ee) {
            throw new AiException(
                'Vault Gardener is disabled on Zero-Knowledge E2EE vaults.',
                'E2EE_BLOCKED',
                403,
            );
        }

        if ($triggeredBy !== null) {
            $isMember = $vault->team_id === $triggeredBy->current_team_id
                || $vault->team->members()->where('user_id', $triggeredBy->id)->exists();

            if (! $isMember) {
                throw new AiException('You do not have access to this vault.', 'FORBIDDEN', 403);
            }
        }

        if (! $this->planService->hasFeature($vault->team, 'ai_gardener')) {
            throw new AiException(
                'Your workspace plan does not include Vault Gardener.',
                'AI_DISABLED',
                403,
            );
        }

        $aiConfig = $vault->team->aiConfig;
        if ($aiConfig === null || ! $aiConfig->is_enabled || $aiConfig->provider_mode === 'off') {
            throw new AiException('Synkk AI Add-On is disabled for this workspace.', 'AI_DISABLED', 403);
        }

        $setting = $vault->aiSetting;
        if ($setting === null || $setting->ai_mode !== 'server') {
            throw new AiException('Server-side AI is not enabled on this vault.', 'AI_DISABLED', 403);
        }

        if (! $force && ! $setting->gardener_enabled) {
            throw new AiException('Vault Gardener is disabled on this vault.', 'AI_DISABLED', 403);
        }

        return $setting;
    }

    /**
     * @param  array<int, string>  $inboxFolders
     * @return array{
     *     proposals_created: int,
     *     direct_updates: int,
     *     proposal_ids: array<int, int>,
     *     content: string,
     *     file: VaultFile
     * }
     */
    protected function runInboxTriage(
        Vault $vault,
        VaultFile $file,
        string $content,
        VaultAiSetting $setting,
        array $inboxFolders,
        string $digestFolder,
        ?User $triggeredBy,
    ): array {
        $existingFolders = $this->collectEligibleFolders($vault, $setting, $inboxFolders, $digestFolder);

        $systemPrompt = <<<'PROMPT'
You are Synkk Vault Gardener performing Inbox Auto-Triage on an Obsidian Markdown note.
Suggest clean YAML tags, a crisp 1-sentence summary, and (when appropriate) an organized destination folder in the vault.

Return ONLY valid JSON matching this exact schema:
{
  "needs_triage": true,
  "tags": ["tag-one", "tag-two"],
  "summary": "Concise 1-sentence note summary",
  "suggested_destination_folder": "Folder/Subfolder or null if it should stay put",
  "rationale": "Brief reason for tags and folder placement"
}
PROMPT;

        $userPayload = [
            'current_path' => $file->path,
            'existing_vault_folders' => $existingFolders,
            'content' => Str::limit($content, 4000),
        ];

        $response = $this->aiGateway->chat($vault->team, [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => (string) json_encode($userPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)],
        ], [
            'feature' => 'gardener',
            'fast' => true,
            'vault' => $vault,
            'user_id' => $triggeredBy?->id,
            'temperature' => 0.1,
            'max_tokens' => 1200,
            'response_format' => ['type' => 'json_object'],
        ]);

        $parsed = $this->decodeJsonPayload($response->content);
        if ($parsed === null || ($parsed['needs_triage'] ?? true) === false) {
            return [
                'proposals_created' => 0,
                'direct_updates' => 0,
                'proposal_ids' => [],
                'content' => $content,
                'file' => $file,
            ];
        }

        $tags = [];
        if (is_array($parsed['tags'] ?? null)) {
            foreach ($parsed['tags'] as $rawTag) {
                if (! is_string($rawTag)) {
                    continue;
                }
                $cleanTag = ltrim(trim($rawTag), '#');
                $cleanTag = preg_replace('/[^a-zA-Z0-9_\-\/]/', '-', $cleanTag) ?? '';
                $cleanTag = trim($cleanTag, '-');
                if ($cleanTag !== '' && ! in_array($cleanTag, $tags, true)) {
                    $tags[] = $cleanTag;
                }
            }
        }
        $tags = array_slice($tags, 0, 8);

        $summary = is_string($parsed['summary'] ?? null) && trim($parsed['summary']) !== ''
            ? Str::limit(str_replace(["\r", "\n"], ' ', trim($parsed['summary'])), 240, '')
            : null;

        $rationale = is_string($parsed['rationale'] ?? null) && trim($parsed['rationale']) !== ''
            ? trim($parsed['rationale'])
            : 'Inbox Auto-Triage metadata and organization suggestion.';

        $destinationFolder = is_string($parsed['suggested_destination_folder'] ?? null)
            ? $this->sanitizeDestinationFolder($parsed['suggested_destination_folder'], $file->path, $vault, $setting, $inboxFolders, $digestFolder)
            : null;

        $destinationPath = $destinationFolder !== null
            ? $this->proposalService->normalizePath($destinationFolder.'/'.basename($file->path))
            : null;

        if ($destinationPath !== null) {
            if (
                $destinationPath === $file->path
                || $setting->isPathExcluded($destinationPath)
                || $this->isRestrictedByHiddenAcl($vault, $destinationPath)
                || ($triggeredBy !== null && $vault->permissionForPath($triggeredBy, $destinationPath) !== 'read_write')
            ) {
                $destinationPath = null;
                $destinationFolder = null;
            }
        }

        if (empty($tags) && $summary === null && $destinationPath === null) {
            return [
                'proposals_created' => 0,
                'direct_updates' => 0,
                'proposal_ids' => [],
                'content' => $content,
                'file' => $file,
            ];
        }

        $enrichedContent = $this->mergeYamlFrontmatter($content, $tags, $summary);
        $proposalsCreated = 0;
        $directUpdates = 0;
        $proposalIds = [];

        if ($setting->allow_direct_inbox_frontmatter) {
            if ($enrichedContent !== $content) {
                $actor = $this->resolveWriteActor($vault, $triggeredBy);
                $this->syncUploadAction->execute(
                    vault: $vault,
                    user: $actor,
                    deviceName: 'Synkk AI Gardener',
                    path: $file->path,
                    contents: $enrichedContent,
                    baseVersion: $file->version,
                );
                $directUpdates++;
                $file = $file->fresh() ?? $file;
                $content = $enrichedContent;
            }

            if ($destinationPath !== null) {
                $this->proposalService->supersedePendingForPath($vault, $file->path, 'gardener_triage');
                $proposal = $this->proposalService->stageProposal($vault, $triggeredBy, [
                    'target_path' => $file->path,
                    'action' => 'move',
                    'destination_path' => $destinationPath,
                    'proposed_content' => $content,
                    'source' => 'gardener_triage',
                    'rationale' => $rationale,
                    'metadata' => [
                        'tags' => $tags,
                        'summary' => $summary,
                        'suggested_destination_folder' => $destinationFolder,
                    ],
                ]);
                $proposalsCreated++;
                $proposalIds[] = $proposal->id;
            }

            return [
                'proposals_created' => $proposalsCreated,
                'direct_updates' => $directUpdates,
                'proposal_ids' => $proposalIds,
                'content' => $content,
                'file' => $file,
            ];
        }

        if ($enrichedContent !== $content || $destinationPath !== null) {
            $this->proposalService->supersedePendingForPath($vault, $file->path, 'gardener_triage');
            $proposal = $this->proposalService->stageProposal($vault, $triggeredBy, [
                'target_path' => $file->path,
                'action' => $destinationPath !== null ? 'move' : 'update',
                'destination_path' => $destinationPath,
                'proposed_content' => $enrichedContent,
                'source' => 'gardener_triage',
                'rationale' => $rationale,
                'metadata' => [
                    'tags' => $tags,
                    'summary' => $summary,
                    'suggested_destination_folder' => $destinationFolder,
                ],
            ]);
            $proposalsCreated++;
            $proposalIds[] = $proposal->id;
        }

        return [
            'proposals_created' => $proposalsCreated,
            'direct_updates' => $directUpdates,
            'proposal_ids' => $proposalIds,
            'content' => $content,
            'file' => $file,
        ];
    }

    /**
     * @param  array<int, array{path: string, excerpt: string}>  $candidates
     * @return array{proposals_created: int, proposal_ids: array<int, int>}
     */
    protected function runGraphLinker(
        Vault $vault,
        VaultFile $file,
        string $content,
        array $candidates,
        ?User $triggeredBy,
    ): array {
        $systemPrompt = <<<'PROMPT'
You are Synkk Vault Gardener Graph Linker.
Identify genuinely relevant existing notes from the provided candidates that should be cross-linked from the target note.
STRICT RULE: Only choose paths from the provided candidate list. Never invent note paths.

Return ONLY valid JSON:
{
  "links": [
    {
      "path": "Exact/Candidate/Path.md",
      "rationale": "Short reason why these notes relate"
    }
  ]
}
Return an empty "links" array if none are strongly relevant.
PROMPT;

        $userPayload = [
            'source_path' => $file->path,
            'source_excerpt' => Str::limit($content, 2500),
            'candidates' => $candidates,
        ];

        $response = $this->aiGateway->chat($vault->team, [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => (string) json_encode($userPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)],
        ], [
            'feature' => 'gardener',
            'fast' => true,
            'vault' => $vault,
            'user_id' => $triggeredBy?->id,
            'temperature' => 0.1,
            'max_tokens' => 800,
            'response_format' => ['type' => 'json_object'],
        ]);

        $parsed = $this->decodeJsonPayload($response->content);
        if ($parsed === null || ! is_array($parsed['links'] ?? null)) {
            return ['proposals_created' => 0, 'proposal_ids' => []];
        }

        /** @var array<string, string> $candidateByLowerPath */
        $candidateByLowerPath = [];
        foreach ($candidates as $candidate) {
            $candidateByLowerPath[strtolower($candidate['path'])] = $candidate['path'];
        }

        /** @var array<int, array{path: string, rationale: string}> $verifiedLinks */
        $verifiedLinks = [];
        $seen = [];

        foreach ($parsed['links'] as $entry) {
            if (! is_array($entry) || ! is_string($entry['path'] ?? null)) {
                continue;
            }

            $rawPath = trim(str_replace('\\', '/', $entry['path']), '/');
            $lower = strtolower($rawPath);
            if (! isset($candidateByLowerPath[$lower]) && isset($candidateByLowerPath[$lower.'.md'])) {
                $lower .= '.md';
            }

            if (! isset($candidateByLowerPath[$lower]) || isset($seen[$lower])) {
                continue;
            }

            $seen[$lower] = true;
            $reason = is_string($entry['rationale'] ?? null) && trim($entry['rationale']) !== ''
                ? trim($entry['rationale'])
                : 'Related context';

            $verifiedLinks[] = [
                'path' => $candidateByLowerPath[$lower],
                'rationale' => $reason,
            ];
        }

        if (empty($verifiedLinks)) {
            return ['proposals_created' => 0, 'proposal_ids' => []];
        }

        $linkedContent = $this->appendRelatedLinksSection($content, $verifiedLinks);
        if ($linkedContent === $content) {
            return ['proposals_created' => 0, 'proposal_ids' => []];
        }

        $this->proposalService->supersedePendingForPath($vault, $file->path, 'gardener_links');
        $proposal = $this->proposalService->stageProposal($vault, $triggeredBy, [
            'target_path' => $file->path,
            'action' => 'update',
            'proposed_content' => $linkedContent,
            'source' => 'gardener_links',
            'rationale' => 'Suggested '.count($verifiedLinks).' related wikilink(s) from vault graph.',
            'metadata' => ['links' => $verifiedLinks],
        ]);

        return [
            'proposals_created' => 1,
            'proposal_ids' => [$proposal->id],
        ];
    }

    /**
     * @param  array<int, array{path: string, excerpt: string}>  $neighbors
     * @return array{proposals_created: int, drift_alerts: int, proposal_ids: array<int, int>}
     */
    protected function runDriftDetection(
        Vault $vault,
        VaultFile $file,
        string $content,
        array $neighbors,
        ?User $triggeredBy,
    ): array {
        $systemPrompt = <<<'PROMPT'
You are Synkk Vault Gardener Contradiction & Knowledge Drift Detector.
Compare the target note against closely related notes in the vault to spot factual contradictions, outdated API/process claims, or conflicting decisions.
Only report real contradictions when evidence directly conflicts; minor differences are NOT drift.

Return ONLY valid JSON:
{
  "has_drift": false,
  "severity": "low",
  "summary": "Short explanation of the contradiction or drift",
  "conflicting_paths": ["Path/To/ConflictingNote.md"],
  "proposed_callout": "Short warning callout body naming the discrepancy",
  "rationale": "Why human review is recommended"
}
Allowed severity: "low", "medium", "high".
PROMPT;

        $userPayload = [
            'target_path' => $file->path,
            'target_excerpt' => Str::limit($content, 2500),
            'related_notes' => $neighbors,
        ];

        $response = $this->aiGateway->chat($vault->team, [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => (string) json_encode($userPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)],
        ], [
            'feature' => 'gardener',
            'fast' => true,
            'vault' => $vault,
            'user_id' => $triggeredBy?->id,
            'temperature' => 0.1,
            'max_tokens' => 900,
            'response_format' => ['type' => 'json_object'],
        ]);

        $parsed = $this->decodeJsonPayload($response->content);
        if ($parsed === null || ! ((bool) ($parsed['has_drift'] ?? false))) {
            return ['proposals_created' => 0, 'drift_alerts' => 0, 'proposal_ids' => []];
        }

        /** @var array<string, string> $neighborByLower */
        $neighborByLower = [];
        foreach ($neighbors as $neighbor) {
            $neighborByLower[strtolower($neighbor['path'])] = $neighbor['path'];
        }

        $conflictingPaths = [];
        if (is_array($parsed['conflicting_paths'] ?? null)) {
            foreach ($parsed['conflicting_paths'] as $rawPath) {
                if (! is_string($rawPath)) {
                    continue;
                }
                $lower = strtolower(trim(str_replace('\\', '/', $rawPath), '/'));
                if (isset($neighborByLower[$lower]) && ! in_array($neighborByLower[$lower], $conflictingPaths, true)) {
                    $conflictingPaths[] = $neighborByLower[$lower];
                }
            }
        }

        $severity = is_string($parsed['severity'] ?? null) && in_array($parsed['severity'], ['low', 'medium', 'high'], true)
            ? $parsed['severity']
            : 'medium';

        $summary = is_string($parsed['summary'] ?? null) && trim($parsed['summary']) !== ''
            ? trim($parsed['summary'])
            : 'Potential contradiction detected against related vault documentation.';

        $calloutBody = is_string($parsed['proposed_callout'] ?? null) && trim($parsed['proposed_callout']) !== ''
            ? trim($parsed['proposed_callout'])
            : $summary;

        $rationale = is_string($parsed['rationale'] ?? null) && trim($parsed['rationale']) !== ''
            ? trim($parsed['rationale'])
            : $summary;

        if (str_contains($content, $summary)) {
            return ['proposals_created' => 0, 'drift_alerts' => 0, 'proposal_ids' => []];
        }

        $refs = ! empty($conflictingPaths)
            ? ' (see '.implode(', ', array_map(fn (string $p): string => '[['.$this->stripMdExtension($p).']]', $conflictingPaths)).')'
            : '';

        $calloutLines = array_map(
            fn (string $line): string => '> '.$line,
            explode("\n", str_replace(["\r\n", "\r"], "\n", $calloutBody)),
        );
        $calloutBlock = "> [!warning] Potential Knowledge Drift{$refs}\n".implode("\n", $calloutLines);

        $calloutContent = $this->insertCalloutAfterFrontmatter($content, $calloutBlock);
        if ($calloutContent === $content) {
            return ['proposals_created' => 0, 'drift_alerts' => 0, 'proposal_ids' => []];
        }

        $this->proposalService->supersedePendingForPath($vault, $file->path, 'gardener_drift');
        $proposal = $this->proposalService->stageProposal($vault, $triggeredBy, [
            'target_path' => $file->path,
            'action' => 'update',
            'proposed_content' => $calloutContent,
            'source' => 'gardener_drift',
            'rationale' => $rationale,
            'metadata' => [
                'severity' => $severity,
                'conflicting_paths' => $conflictingPaths,
            ],
        ]);

        TeamNotification::query()->create([
            'team_id' => $vault->team_id,
            'user_id' => $triggeredBy?->id,
            'type' => 'ai_gardener',
            'title' => 'Knowledge Drift Alert: '.basename($file->path),
            'message' => Str::limit($summary, 480),
            'action_url' => route('vaults.show', [
                'current_team' => $vault->team->slug,
                'vault' => $vault->slug,
                'tab' => 'ai-inbox',
            ]),
        ]);

        return [
            'proposals_created' => 1,
            'drift_alerts' => 1,
            'proposal_ids' => [$proposal->id],
        ];
    }

    /**
     * @param  array<int, string>  $inboxFolders
     * @return array<int, string>
     */
    protected function collectEligibleFolders(
        Vault $vault,
        VaultAiSetting $setting,
        array $inboxFolders,
        string $digestFolder,
    ): array {
        $paths = $vault->files()
            ->where('is_deleted', false)
            ->where('path', 'like', '%.md')
            ->limit(250)
            ->pluck('path');

        $folders = [];
        foreach ($paths as $rawPath) {
            $cleanPath = trim(str_replace('\\', '/', (string) $rawPath), '/');
            $dir = pathinfo($cleanPath, PATHINFO_DIRNAME);
            if ($dir === '.' || $dir === '') {
                continue;
            }

            if (
                $this->isInInboxFolder($dir.'/placeholder.md', $inboxFolders)
                || strcasecmp($dir, $digestFolder) === 0
                || stripos($dir, $digestFolder.'/') === 0
                || $setting->isPathExcluded($dir.'/note.md')
                || $this->isRestrictedByHiddenAcl($vault, $dir.'/note.md')
            ) {
                continue;
            }

            $folders[$dir] = true;
        }

        $list = array_keys($folders);
        sort($list);

        return array_slice($list, 0, 25);
    }

    /**
     * @return array<int, array{path: string, excerpt: string}>
     */
    protected function findCandidateRelatedNotes(
        Vault $vault,
        VaultFile $sourceFile,
        string $sourceContent,
        VaultAiSetting $setting,
        string $digestFolder,
        ?User $triggeredBy,
        bool $excludeAlreadyLinked,
        int $limit,
    ): array {
        $queryText = trim(pathinfo($sourceFile->path, PATHINFO_FILENAME).' '.Str::limit($sourceContent, 240, ''));
        $searchHits = $this->hybridSearch->search($vault, $queryText, max(8, $limit), $triggeredBy);

        $candidates = [];
        $seenPaths = [];

        foreach ($searchHits as $hit) {
            $path = trim(str_replace('\\', '/', $hit['path']), '/');
            if (! $this->isEligibleRelatedPath($vault, $path, $sourceFile->path, $setting, $digestFolder, $triggeredBy)) {
                continue;
            }

            if ($excludeAlreadyLinked && $this->isAlreadyLinked($sourceContent, $path)) {
                continue;
            }

            $lower = strtolower($path);
            if (isset($seenPaths[$lower])) {
                continue;
            }

            $seenPaths[$lower] = true;
            $candidates[] = [
                'path' => $path,
                'excerpt' => Str::limit((string) $hit['content'], 500),
            ];

            if (count($candidates) >= $limit) {
                return $candidates;
            }
        }

        // Supplement with direct vault markdown scan when vector/FTS index is partial
        $fallbackFiles = $vault->files()
            ->where('is_deleted', false)
            ->where('is_encrypted', false)
            ->where('path', 'like', '%.md')
            ->where('id', '!=', $sourceFile->id)
            ->orderByDesc('updated_at')
            ->limit(40)
            ->get();

        foreach ($fallbackFiles as $candidateFile) {
            $path = trim(str_replace('\\', '/', $candidateFile->path), '/');
            $lower = strtolower($path);
            if (isset($seenPaths[$lower])) {
                continue;
            }

            if (! $this->isEligibleRelatedPath($vault, $path, $sourceFile->path, $setting, $digestFolder, $triggeredBy)) {
                continue;
            }

            if ($excludeAlreadyLinked && $this->isAlreadyLinked($sourceContent, $path)) {
                continue;
            }

            $excerpt = (string) ($candidateFile->getContents() ?? '');
            if (trim($excerpt) === '') {
                continue;
            }

            $seenPaths[$lower] = true;
            $candidates[] = [
                'path' => $path,
                'excerpt' => Str::limit($excerpt, 500),
            ];

            if (count($candidates) >= $limit) {
                break;
            }
        }

        return $candidates;
    }

    /**
     * @param  array<int, array{path: string, excerpt: string}>  $candidates
     * @return array<int, array{path: string, excerpt: string}>
     */
    protected function findDriftNeighbors(
        Vault $vault,
        VaultFile $sourceFile,
        array $candidates,
        VaultAiSetting $setting,
        string $digestFolder,
        ?User $triggeredBy,
        int $limit,
    ): array {
        $neighbors = [];
        $seen = [];

        $graph = $this->graphService->getInteractiveGraph($vault);
        $sourceIndex = null;
        foreach ($graph['nodes'] as $idx => $node) {
            if (strcasecmp($node['path'], $sourceFile->path) === 0) {
                $sourceIndex = $idx;
                break;
            }
        }

        if ($sourceIndex !== null) {
            $neighborPaths = [];
            foreach ($graph['edges'] as $edge) {
                if ($edge['source'] === $sourceIndex && isset($graph['nodes'][$edge['target']])) {
                    $neighborPaths[] = $graph['nodes'][$edge['target']]['path'];
                } elseif ($edge['target'] === $sourceIndex && isset($graph['nodes'][$edge['source']])) {
                    $neighborPaths[] = $graph['nodes'][$edge['source']]['path'];
                }
            }

            if (! empty($neighborPaths)) {
                $files = $vault->files()
                    ->where('is_deleted', false)
                    ->where('is_encrypted', false)
                    ->whereIn('path', array_values(array_unique($neighborPaths)))
                    ->get();

                foreach ($files as $neighborFile) {
                    $path = trim(str_replace('\\', '/', $neighborFile->path), '/');
                    if (! $this->isEligibleRelatedPath($vault, $path, $sourceFile->path, $setting, $digestFolder, $triggeredBy)) {
                        continue;
                    }

                    $excerpt = (string) ($neighborFile->getContents() ?? '');
                    if (trim($excerpt) === '') {
                        continue;
                    }

                    $seen[strtolower($path)] = true;
                    $neighbors[] = [
                        'path' => $path,
                        'excerpt' => Str::limit($excerpt, 600),
                    ];

                    if (count($neighbors) >= $limit) {
                        return $neighbors;
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            $lower = strtolower($candidate['path']);
            if (isset($seen[$lower])) {
                continue;
            }

            $seen[$lower] = true;
            $neighbors[] = $candidate;

            if (count($neighbors) >= $limit) {
                break;
            }
        }

        return $neighbors;
    }

    protected function isEligibleRelatedPath(
        Vault $vault,
        string $path,
        string $sourcePath,
        VaultAiSetting $setting,
        string $digestFolder,
        ?User $triggeredBy,
    ): bool {
        if ($path === '' || strcasecmp($path, $sourcePath) === 0 || ! str_ends_with(strtolower($path), '.md')) {
            return false;
        }

        if (str_contains($path, '.conflict-') || str_contains($path, '.sync-conflict-')) {
            return false;
        }

        if ($setting->isPathExcluded($path)) {
            return false;
        }

        if (strcasecmp($path, $digestFolder) === 0 || stripos($path, $digestFolder.'/') === 0) {
            return false;
        }

        if ($this->isRestrictedByHiddenAcl($vault, $path)) {
            return false;
        }

        if ($triggeredBy !== null && $vault->permissionForPath($triggeredBy, $path) === 'hidden') {
            return false;
        }

        return true;
    }

    protected function isAlreadyLinked(string $content, string $candidatePath): bool
    {
        $withoutExt = $this->stripMdExtension($candidatePath);
        $basename = pathinfo($candidatePath, PATHINFO_FILENAME);

        $needles = [
            '[['.$withoutExt.']]',
            '[['.$withoutExt.'|',
            '[['.$withoutExt.'#',
            '[['.$candidatePath.']]',
            '[['.$basename.']]',
            '[['.$basename.'|',
            '[['.$basename.'#',
            '('.$candidatePath.')',
        ];

        foreach ($needles as $needle) {
            if (stripos($content, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Merge suggested tags and summary into YAML frontmatter cleanly without stripping existing keys.
     *
     * @param  array<int, string>  $tags
     */
    protected function mergeYamlFrontmatter(string $content, array $tags, ?string $summary): string
    {
        if (empty($tags) && $summary === null) {
            return $content;
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        $escapedSummary = $summary !== null ? '"'.str_replace('"', '\"', $summary).'"' : null;
        $inlineTags = '['.implode(', ', $tags).']';

        if (preg_match('/^---\n(.*?)\n---\n?(.*)$/s', $normalized, $matches) === 1) {
            $yamlBlock = $matches[1];
            $body = $matches[2];
            $lines = $yamlBlock !== '' ? explode("\n", $yamlBlock) : [];

            $hasTags = false;
            $hasSummary = false;

            foreach ($lines as $idx => $line) {
                if (preg_match('/^tags\s*:/i', $line) === 1 && ! empty($tags)) {
                    $hasTags = true;
                    if (preg_match('/^tags\s*:\s*\[(.*)\]\s*$/i', $line, $tagMatch) === 1) {
                        $existing = array_filter(array_map(
                            fn (string $t): string => trim($t, " \t'\""),
                            explode(',', $tagMatch[1]),
                        ), fn (string $t): bool => $t !== '');
                        $merged = array_values(array_unique(array_merge($existing, $tags)));
                        $lines[$idx] = 'tags: ['.implode(', ', $merged).']';
                    }
                } elseif (preg_match('/^summary\s*:/i', $line) === 1 && $escapedSummary !== null) {
                    $hasSummary = true;
                }
            }

            if (! $hasTags && ! empty($tags)) {
                $lines[] = "tags: {$inlineTags}";
            }

            if (! $hasSummary && $escapedSummary !== null) {
                $lines[] = "summary: {$escapedSummary}";
            }

            return "---\n".implode("\n", $lines)."\n---\n".ltrim($body, "\n");
        }

        $yamlLines = ['---'];
        if (! empty($tags)) {
            $yamlLines[] = "tags: {$inlineTags}";
        }
        if ($escapedSummary !== null) {
            $yamlLines[] = "summary: {$escapedSummary}";
        }
        $yamlLines[] = '---';

        return implode("\n", $yamlLines)."\n\n".ltrim($normalized, "\n");
    }

    /**
     * @param  array<int, array{path: string, rationale: string}>  $links
     */
    protected function appendRelatedLinksSection(string $content, array $links): string
    {
        $bulletLines = [];
        foreach ($links as $link) {
            $target = $this->stripMdExtension($link['path']);
            $bullet = "- [[{$target}]] — {$link['rationale']}";
            if (! str_contains($content, "[[{$target}]]")) {
                $bulletLines[] = $bullet;
            }
        }

        if (empty($bulletLines)) {
            return $content;
        }

        $sectionBullets = implode("\n", $bulletLines);
        $trimmed = rtrim($content);

        if (preg_match('/^##\s+Related Notes\s*$/im', $trimmed) === 1) {
            return $trimmed."\n".$sectionBullets."\n";
        }

        return $trimmed."\n\n## Related Notes\n".$sectionBullets."\n";
    }

    protected function insertCalloutAfterFrontmatter(string $content, string $calloutBlock): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        if (preg_match('/^(---\n.*?\n---\n*)(.*)$/s', $normalized, $matches) === 1) {
            $frontmatter = rtrim($matches[1])."\n\n";
            $body = ltrim($matches[2], "\n");

            return $frontmatter.$calloutBlock."\n\n".$body;
        }

        return $calloutBlock."\n\n".ltrim($normalized, "\n");
    }

    /**
     * @param  array<int, string>  $inboxFolders
     */
    protected function sanitizeDestinationFolder(
        string $rawFolder,
        string $currentPath,
        Vault $vault,
        VaultAiSetting $setting,
        array $inboxFolders,
        string $digestFolder,
    ): ?string {
        $clean = trim(str_replace('\\', '/', $rawFolder), '/');
        if ($clean === '' || strtolower($clean) === 'null' || str_contains($clean, "\0")) {
            return null;
        }

        foreach (explode('/', $clean) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        $currentDir = pathinfo($currentPath, PATHINFO_DIRNAME);
        if (strcasecmp($clean, $currentDir) === 0) {
            return null;
        }

        if (
            $this->isInInboxFolder($clean.'/note.md', $inboxFolders)
            || strcasecmp($clean, $digestFolder) === 0
            || stripos($clean, $digestFolder.'/') === 0
            || $setting->isPathExcluded($clean.'/note.md')
            || $this->isRestrictedByHiddenAcl($vault, $clean.'/note.md')
        ) {
            return null;
        }

        return $clean;
    }

    protected function sanitizeFolder(string $rawFolder, string $fallback): string
    {
        $clean = trim(str_replace('\\', '/', $rawFolder), '/');
        if ($clean === '' || str_contains($clean, "\0")) {
            return $fallback;
        }

        foreach (explode('/', $clean) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return $fallback;
            }
        }

        return $clean;
    }

    protected function isRestrictedByHiddenAcl(Vault $vault, string $cleanPath): bool
    {
        $hasMatchingAccessibleRule = false;

        foreach ($vault->permissions as $rule) {
            $rulePath = trim(str_replace('\\', '/', $rule->path), '/');
            if ($rulePath === '') {
                continue;
            }

            $matches = $rulePath === $cleanPath;
            if (! $matches && ($rule->is_folder || str_contains($rule->path, '/'))) {
                $matches = str_starts_with($cleanPath, $rulePath.'/');
            }

            if (! $matches) {
                continue;
            }

            if ($rule->permission === 'hidden') {
                return true;
            }

            $hasMatchingAccessibleRule = true;
        }

        return ($vault->default_permission ?: 'read_write') === 'hidden' && ! $hasMatchingAccessibleRule;
    }

    protected function stripMdExtension(string $path): string
    {
        return preg_replace('/\.md$/i', '', $path) ?? $path;
    }

    /**
     * @throws AiException
     */
    protected function resolveWriteActor(Vault $vault, ?User $triggeredBy): User
    {
        if ($triggeredBy !== null) {
            return $triggeredBy;
        }

        $owner = $vault->team->owner();
        if ($owner instanceof User) {
            return $owner;
        }

        if ($vault->creator instanceof User) {
            return $vault->creator;
        }

        $firstMember = $vault->team->members()->first();
        if ($firstMember instanceof User) {
            return $firstMember;
        }

        throw new AiException('No workspace member available to attribute Gardener update.', 'INVALID_INPUT', 422);
    }

    /**
     * @return array{
     *     path: string,
     *     skipped: bool,
     *     reason: string|null,
     *     proposals_created: int,
     *     direct_updates: int,
     *     drift_alerts: int,
     *     proposal_ids: array<int, int>
     * }
     */
    protected function skippedFileResult(string $path, string $reason): array
    {
        return [
            'path' => $path,
            'skipped' => true,
            'reason' => $reason,
            'proposals_created' => 0,
            'direct_updates' => 0,
            'drift_alerts' => 0,
            'proposal_ids' => [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeJsonPayload(string $raw): ?array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^```(?:json)?\s*\n(.*?)\n```$/s', $trimmed, $matches) === 1) {
            $trimmed = trim($matches[1]);
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $firstBrace = strpos($trimmed, '{');
        $lastBrace = strrpos($trimmed, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $slice = substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1);
            $decoded = json_decode($slice, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
