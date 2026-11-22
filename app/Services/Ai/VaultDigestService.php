<?php

namespace App\Services\Ai;

use App\Actions\Vaults\SyncUploadAction;
use App\Exceptions\AiException;
use App\Mail\VaultDigestMail;
use App\Models\TeamMessage;
use App\Models\TeamNotification;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultAiSetting;
use App\Models\VaultChangeLog;
use App\Models\VaultFile;
use App\Services\PlanService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VaultDigestService
{
    public const DEFAULT_DELIVERY = [
        'vault_note' => true,
        'via_proposal' => true,
        'team_feed' => true,
        'email' => false,
    ];

    public function __construct(
        protected AiGateway $aiGateway,
        protected AiProposalService $proposalService,
        protected SyncUploadAction $syncUploadAction,
        protected PlanService $planService,
    ) {}

    /**
     * Normalize stored delivery flags over safe defaults.
     *
     * @param  array<string, mixed>|null  $delivery
     * @return array{vault_note: bool, via_proposal: bool, team_feed: bool, email: bool}
     */
    public function normalizeDelivery(?array $delivery): array
    {
        $source = is_array($delivery) ? $delivery : [];

        return [
            'vault_note' => (bool) ($source['vault_note'] ?? self::DEFAULT_DELIVERY['vault_note']),
            'via_proposal' => (bool) ($source['via_proposal'] ?? self::DEFAULT_DELIVERY['via_proposal']),
            'team_feed' => (bool) ($source['team_feed'] ?? self::DEFAULT_DELIVERY['team_feed']),
            'email' => (bool) ($source['email'] ?? self::DEFAULT_DELIVERY['email']),
        ];
    }

    /**
     * Determine whether a vault's scheduled digest is due to run.
     */
    public function isDue(VaultAiSetting $setting, ?CarbonInterface $now = null): bool
    {
        $now ??= now();

        return match ($setting->digest_schedule) {
            'daily' => $setting->last_digest_at === null
                || $setting->last_digest_at->lte($now->copy()->subHours(23)),
            'weekly' => $setting->last_digest_at === null
                || $setting->last_digest_at->lte($now->copy()->subDays(6)->subHours(23)),
            default => false,
        };
    }

    /**
     * Synthesize and deliver a vault activity digest from filtered sync changelogs and revisions.
     *
     * @return array{
     *     status: string,
     *     period: string,
     *     changes_count: int,
     *     files_touched: int,
     *     headline: string,
     *     summary: string,
     *     markdown: string,
     *     note_path: string|null,
     *     proposal_id: int|null,
     *     vault_file_version: int|null,
     *     team_notification_id: int|null,
     *     team_message_id: int|null,
     *     emails_queued: int,
     *     model: string|null
     * }
     *
     * @throws AiException
     */
    public function generateDigest(
        Vault $vault,
        ?User $triggeredBy = null,
        ?string $period = null,
        ?CarbonInterface $since = null,
        bool $force = false,
    ): array {
        if ($vault->is_e2ee) {
            throw new AiException(
                'Automated Team Digests are disabled on Zero-Knowledge E2EE vaults.',
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

        if (! $this->planService->hasFeature($vault->team, 'ai_team_digests')) {
            throw new AiException(
                'Your workspace plan does not include Automated Team Digests.',
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

        $effectivePeriod = in_array($period, ['daily', 'weekly'], true)
            ? $period
            : ($setting->digest_schedule === 'weekly' ? 'weekly' : 'daily');

        $until = now();
        $defaultStart = $effectivePeriod === 'weekly'
            ? $until->copy()->subDays(7)
            : $until->copy()->subDay();

        $windowStart = $since !== null
            ? $since->copy()
            : ((! $force && $setting->last_digest_at !== null) ? $setting->last_digest_at->copy() : $defaultStart);

        $minStart = $until->copy()->subDays(30);
        if ($windowStart->lt($minStart)) {
            $windowStart = $minStart;
        }

        $digestFolder = $this->sanitizeDigestFolder($setting->digest_folder);
        $vault->loadMissing('permissions');

        /** @var Collection<int, VaultChangeLog> $rawLogs */
        $rawLogs = $vault->changeLogs()
            ->with('user')
            ->where('created_at', '>=', $windowStart)
            ->where('created_at', '<=', $until)
            ->orderByDesc('created_at')
            ->limit(250)
            ->get();

        /** @var Collection<int, VaultChangeLog> $filteredLogs */
        $filteredLogs = $rawLogs->filter(function (VaultChangeLog $log) use ($vault, $setting, $digestFolder, $triggeredBy): bool {
            $cleanPath = trim(str_replace('\\', '/', $log->path), '/');
            if ($cleanPath === '') {
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

            if ($triggeredBy !== null && $vault->permissionForPath($triggeredBy, $cleanPath) === 'hidden') {
                return false;
            }

            return true;
        })->values();

        if ($filteredLogs->isEmpty()) {
            return [
                'status' => 'no_changes',
                'period' => $effectivePeriod,
                'changes_count' => 0,
                'files_touched' => 0,
                'headline' => '',
                'summary' => '',
                'markdown' => '',
                'note_path' => null,
                'proposal_id' => null,
                'vault_file_version' => null,
                'team_notification_id' => null,
                'team_message_id' => null,
                'emails_queued' => 0,
                'model' => null,
            ];
        }

        $groupedByPath = $filteredLogs->groupBy(fn (VaultChangeLog $log): string => trim(str_replace('\\', '/', $log->path), '/'));
        $changesCount = $filteredLogs->count();
        $filesTouched = $groupedByPath->count();

        $activityContext = $this->buildBoundedActivityContext($vault, $groupedByPath, $windowStart);

        $messages = $this->buildDigestMessages(
            vault: $vault,
            period: $effectivePeriod,
            windowStart: $windowStart,
            until: $until,
            changesCount: $changesCount,
            filesTouched: $filesTouched,
            activityContext: $activityContext,
        );

        $response = $this->aiGateway->chat($vault->team, $messages, [
            'feature' => 'digest',
            'fast' => true,
            'vault' => $vault,
            'user_id' => $triggeredBy?->id,
            'temperature' => 0.2,
            'max_tokens' => 2500,
            'response_format' => ['type' => 'json_object'],
        ]);

        [$headline, $summary, $markdownBody] = $this->parseDigestResponse(
            raw: $response->content,
            vault: $vault,
            period: $effectivePeriod,
            changesCount: $changesCount,
            filesTouched: $filesTouched,
        );

        $fullMarkdown = $this->formatDigestNote(
            vault: $vault,
            period: $effectivePeriod,
            generatedAt: $until,
            changesCount: $changesCount,
            filesTouched: $filesTouched,
            headline: $headline,
            markdownBody: $markdownBody,
        );

        $delivery = $this->normalizeDelivery($setting->digest_delivery);

        $notePath = null;
        $proposalId = null;
        $vaultFileVersion = null;
        $teamNotificationId = null;
        $teamMessageId = null;
        $emailsQueued = 0;

        if ($delivery['vault_note']) {
            $suffix = $effectivePeriod === 'weekly' ? '-weekly' : '';
            $notePath = $this->proposalService->normalizePath($digestFolder.'/'.$until->format('Y-m-d').$suffix.'.md');

            if ($delivery['via_proposal']) {
                $proposal = $this->proposalService->stageProposal($vault, $triggeredBy, [
                    'target_path' => $notePath,
                    'proposed_content' => $fullMarkdown,
                    'source' => 'digest',
                    'rationale' => "Automated {$effectivePeriod} digest covering {$changesCount} changes across {$filesTouched} files.",
                    'metadata' => [
                        'period' => $effectivePeriod,
                        'changes_count' => $changesCount,
                        'files_touched' => $filesTouched,
                    ],
                ]);
                $proposalId = $proposal->id;
            } else {
                $actor = $this->resolveDigestWriteActor($vault, $triggeredBy);
                $existing = $vault->files()
                    ->where('path', $notePath)
                    ->where('is_deleted', false)
                    ->first();

                $uploadResult = $this->syncUploadAction->execute(
                    vault: $vault,
                    user: $actor,
                    deviceName: 'Synkk AI Digest',
                    path: $notePath,
                    contents: $fullMarkdown,
                    baseVersion: $existing !== null ? $existing->version : 0,
                );

                $vaultFileVersion = isset($uploadResult['version']) ? (int) $uploadResult['version'] : null;
            }
        }

        $actionUrl = route('vaults.show', [
            'current_team' => $vault->team->slug,
            'vault' => $vault->slug,
            'tab' => $proposalId !== null ? 'ai-inbox' : 'editor',
        ]);

        if ($delivery['team_feed']) {
            $notification = TeamNotification::query()->create([
                'team_id' => $vault->team_id,
                'user_id' => $triggeredBy?->id,
                'type' => 'ai_digest',
                'title' => "Vault Digest ({$vault->name}): {$headline}",
                'message' => Str::limit($summary, 480),
                'action_url' => $actionUrl,
            ]);
            $teamNotificationId = $notification->id;

            $teamMessage = TeamMessage::query()->create([
                'team_id' => $vault->team_id,
                'user_id' => null,
                'author_name' => 'Synkk AI Digest',
                'type' => 'ai_digest',
                'body' => "**{$vault->name} ({$effectivePeriod} digest · {$changesCount} changes across {$filesTouched} files)**\n\n{$summary}",
            ]);
            $teamMessageId = $teamMessage->id;
        }

        if ($delivery['email']) {
            $members = $vault->team->members()->get();
            foreach ($members as $member) {
                if (! filled($member->email)) {
                    continue;
                }

                Mail::to($member->email)->queue((new VaultDigestMail(
                    vault: $vault,
                    period: $effectivePeriod,
                    headline: $headline,
                    summary: $summary,
                    markdownBody: $markdownBody,
                    changesCount: $changesCount,
                    filesTouched: $filesTouched,
                    vaultUrl: $actionUrl,
                ))->afterCommit());

                $emailsQueued++;
            }
        }

        $setting->forceFill(['last_digest_at' => $until])->save();

        return [
            'status' => 'generated',
            'period' => $effectivePeriod,
            'changes_count' => $changesCount,
            'files_touched' => $filesTouched,
            'headline' => $headline,
            'summary' => $summary,
            'markdown' => $fullMarkdown,
            'note_path' => $notePath,
            'proposal_id' => $proposalId,
            'vault_file_version' => $vaultFileVersion,
            'team_notification_id' => $teamNotificationId,
            'team_message_id' => $teamMessageId,
            'emails_queued' => $emailsQueued,
            'model' => $response->model,
        ];
    }

    protected function sanitizeDigestFolder(string $rawFolder): string
    {
        $clean = trim(str_replace('\\', '/', $rawFolder), '/');
        if ($clean === '' || str_contains($clean, "\0")) {
            return 'Digests';
        }

        foreach (explode('/', $clean) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return 'Digests';
            }
        }

        return $clean;
    }

    /**
     * Exclude paths restricted by any hidden ACL rule so shared team digests never leak private notes.
     */
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

    /**
     * @param  Collection<string, Collection<int, VaultChangeLog>>  $groupedByPath
     * @return array<int, array<string, mixed>>
     */
    protected function buildBoundedActivityContext(Vault $vault, Collection $groupedByPath, CarbonInterface $windowStart): array
    {
        $topPaths = $groupedByPath->keys()->take(15)->values()->all();

        /** @var Collection<string, VaultFile> $filesByPath */
        $filesByPath = $vault->files()
            ->whereIn('path', $topPaths)
            ->where('is_deleted', false)
            ->get()
            ->keyBy('path');

        $disk = (string) config('synkk.storage_disk', 'local');
        $context = [];
        $charBudget = 12_000;
        $usedChars = 0;

        foreach ($topPaths as $path) {
            /** @var Collection<int, VaultChangeLog>|null $fileLogs */
            $fileLogs = $groupedByPath->get($path);
            if ($fileLogs === null || $fileLogs->isEmpty()) {
                continue;
            }

            $authors = $fileLogs->map(function (VaultChangeLog $log): string {
                if ($log->user !== null) {
                    return $log->user->name;
                }

                return filled($log->device_name) ? (string) $log->device_name : 'Collaborator';
            })->unique()->values()->all();

            $actions = $fileLogs->pluck('action')->unique()->values()->all();
            /** @var VaultChangeLog $latestLog */
            $latestLog = $fileLogs->first();

            $entry = [
                'path' => $path,
                'events_count' => $fileLogs->count(),
                'actions' => $actions,
                'authors' => $authors,
                'latest_version' => $latestLog->version,
                'latest_at' => $latestLog->created_at?->toIso8601String(),
            ];

            /** @var VaultFile|null $vaultFile */
            $vaultFile = $filesByPath->get($path);
            if ($vaultFile !== null && $vaultFile->isMarkdown() && ! $vaultFile->is_encrypted) {
                $currentContent = $vaultFile->getContents();
                if ($currentContent !== null && $currentContent !== '') {
                    $entry['current_excerpt'] = Str::limit($currentContent, 600);
                }

                $previousVersion = $vaultFile->versions()
                    ->where('created_at', '<', $windowStart)
                    ->latest('version')
                    ->first();

                if ($previousVersion !== null && Storage::disk($disk)->exists($previousVersion->storage_path)) {
                    $prevContent = (string) Storage::disk($disk)->get($previousVersion->storage_path);
                    if ($prevContent !== '') {
                        $entry['previous_excerpt'] = Str::limit($prevContent, 400);
                    }
                }
            }

            $encodedLength = strlen((string) json_encode($entry));
            if ($usedChars + $encodedLength > $charBudget && ! empty($context)) {
                break;
            }

            $context[] = $entry;
            $usedChars += $encodedLength;
        }

        return $context;
    }

    /**
     * @param  array<int, array<string, mixed>>  $activityContext
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildDigestMessages(
        Vault $vault,
        string $period,
        CarbonInterface $windowStart,
        CarbonInterface $until,
        int $changesCount,
        int $filesTouched,
        array $activityContext,
    ): array {
        $systemPrompt = <<<'PROMPT'
You are Synkk AI Digest, writing an accurate, concise, high-signal team activity summary for an Obsidian vault.
Use ONLY facts directly visible in the provided changelog events and excerpts. Never invent contributors, file changes, or decisions.

Return ONLY valid JSON matching this schema:
{
  "headline": "Short action-oriented headline (max 90 chars)",
  "summary": "2-3 sentence executive summary suitable for team feed and email",
  "markdown_body": "Structured Markdown sections (Highlights, Key Decisions & Edits with [[wikilink]] references, Follow-ups/Open Questions)"
}
PROMPT;

        $payload = [
            'vault_name' => $vault->name,
            'period' => $period,
            'window_start' => $windowStart->toIso8601String(),
            'window_end' => $until->toIso8601String(),
            'changes_count' => $changesCount,
            'files_touched' => $filesTouched,
            'files' => $activityContext,
        ];

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)],
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    protected function parseDigestResponse(
        string $raw,
        Vault $vault,
        string $period,
        int $changesCount,
        int $filesTouched,
    ): array {
        $trimmed = trim($raw);
        if (preg_match('/^```(?:json)?\s*\n(.*?)\n```$/s', $trimmed, $matches) === 1) {
            $trimmed = trim($matches[1]);
        }

        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            $firstBrace = strpos($trimmed, '{');
            $lastBrace = strrpos($trimmed, '}');
            if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
                $decoded = json_decode(substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1), true);
            }
        }

        $defaultHeadline = ucfirst($period)." Activity ({$filesTouched} notes, {$changesCount} edits)";
        $defaultSummary = "Recorded {$changesCount} sync events across {$filesTouched} notes in {$vault->name}.";

        if (is_array($decoded)) {
            $headline = is_string($decoded['headline'] ?? null) && trim($decoded['headline']) !== ''
                ? Str::limit(trim($decoded['headline']), 120, '')
                : $defaultHeadline;
            $summary = is_string($decoded['summary'] ?? null) && trim($decoded['summary']) !== ''
                ? trim($decoded['summary'])
                : $defaultSummary;
            $markdownBody = is_string($decoded['markdown_body'] ?? null) && trim($decoded['markdown_body']) !== ''
                ? trim($decoded['markdown_body'])
                : $summary;

            return [$headline, $summary, $markdownBody];
        }

        $fallbackBody = $trimmed !== '' ? $trimmed : $defaultSummary;

        return [$defaultHeadline, Str::limit($fallbackBody, 280), $fallbackBody];
    }

    protected function formatDigestNote(
        Vault $vault,
        string $period,
        CarbonInterface $generatedAt,
        int $changesCount,
        int $filesTouched,
        string $headline,
        string $markdownBody,
    ): string {
        $escapedVault = str_replace('"', '\"', $vault->name);

        return implode("\n", [
            '---',
            'type: synkk-digest',
            "vault: \"{$escapedVault}\"",
            "period: {$period}",
            "generated_at: \"{$generatedAt->toIso8601String()}\"",
            "changes_count: {$changesCount}",
            "files_touched: {$filesTouched}",
            '---',
            '',
            "# {$headline}",
            '',
            $markdownBody,
            '',
        ]);
    }

    /**
     * @throws AiException
     */
    protected function resolveDigestWriteActor(Vault $vault, ?User $triggeredBy): User
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

        throw new AiException('No workspace member available to attribute digest note write.', 'INVALID_INPUT', 422);
    }
}
