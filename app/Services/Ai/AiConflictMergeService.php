<?php

namespace App\Services\Ai;

use App\Exceptions\AiException;
use App\Models\User;
use App\Models\Vault;
use App\Services\PlanService;
use App\Services\ThreeWayDiffService;
use Illuminate\Support\Facades\Storage;

class AiConflictMergeService
{
    public function __construct(
        protected AiGateway $aiGateway,
        protected ThreeWayDiffService $diffService,
        protected PlanService $planService,
    ) {}

    /**
     * Generate a semantically merged conflict resolution preview without modifying vault files.
     *
     * @return array{
     *     canonical_path: string,
     *     conflict_path: string,
     *     merged_content: string,
     *     summary: string,
     *     resolutions: array<int, array{hunk_id: int, strategy: string, rationale: string}>,
     *     conflict_count: int,
     *     clean_count: int,
     *     model: string,
     *     used_ai: bool,
     *     had_unmerged_markers: bool
     * }
     *
     * @throws AiException
     */
    public function suggestMerge(
        Vault $vault,
        User $user,
        string $conflictPath,
        ?string $canonicalPath = null,
        ?string $instructions = null,
        ?string $canonicalContentOverride = null,
        ?string $conflictContentOverride = null,
    ): array {
        if ($vault->is_e2ee) {
            throw new AiException(
                'AI Smart Merge is disabled on Zero-Knowledge E2EE vaults.',
                'E2EE_BLOCKED',
                403,
            );
        }

        $isMember = $vault->team_id === $user->current_team_id
            || $vault->team->members()->where('user_id', $user->id)->exists();

        if (! $isMember) {
            throw new AiException('You do not have access to this vault.', 'FORBIDDEN', 403);
        }

        if (! $this->planService->hasFeature($vault->team, 'ai_smart_merge')) {
            throw new AiException(
                'Your workspace plan does not include AI Smart Merge.',
                'AI_DISABLED',
                403,
            );
        }

        $aiConfig = $vault->team->aiConfig;
        if ($aiConfig === null || ! $aiConfig->is_enabled || $aiConfig->provider_mode === 'off') {
            throw new AiException('Synkk AI Add-On is disabled for this workspace.', 'AI_DISABLED', 403);
        }

        $aiSetting = $vault->aiSetting;
        if ($aiSetting === null || $aiSetting->ai_mode !== 'server') {
            throw new AiException('Server-side AI is not enabled on this vault.', 'AI_DISABLED', 403);
        }

        $cleanConflictPath = $this->normalizeRelativePath($conflictPath);
        $derivedCanonical = $this->deriveCanonicalPath($cleanConflictPath);

        if ($derivedCanonical === $cleanConflictPath) {
            throw new AiException("Path '{$cleanConflictPath}' is not a valid conflict copy.", 'INVALID_PATH', 422);
        }

        if ($canonicalPath !== null && trim($canonicalPath) !== '') {
            $cleanCanonicalPath = $this->normalizeRelativePath($canonicalPath);
            if ($cleanCanonicalPath !== $derivedCanonical) {
                throw new AiException(
                    "Conflict path '{$cleanConflictPath}' does not correspond to canonical path '{$cleanCanonicalPath}'.",
                    'INVALID_PATH',
                    422,
                );
            }
        }

        if ($aiSetting->isPathExcluded($derivedCanonical) || $aiSetting->isPathExcluded($cleanConflictPath)) {
            throw new AiException('Path is excluded from AI access.', 'PATH_EXCLUDED', 403);
        }

        if (
            $vault->permissionForPath($user, $derivedCanonical) !== 'read_write'
            || $vault->permissionForPath($user, $cleanConflictPath) !== 'read_write'
        ) {
            throw new AiException("Write permission is required to merge '{$derivedCanonical}'.", 'FORBIDDEN', 403);
        }

        $conflictFile = $vault->files()
            ->where('path', $cleanConflictPath)
            ->where('is_deleted', false)
            ->first();

        if ($conflictFile === null && $conflictContentOverride === null) {
            throw new AiException("Conflict file '{$cleanConflictPath}' not found.", 'NOT_FOUND', 404);
        }

        $canonicalFile = $vault->files()
            ->where('path', $derivedCanonical)
            ->where('is_deleted', false)
            ->first();

        $theirs = $conflictContentOverride ?? ($conflictFile->getContents() ?? '');
        $ours = $canonicalContentOverride ?? ($canonicalFile !== null ? ($canonicalFile->getContents() ?? '') : '');

        $base = '';
        if ($canonicalFile !== null) {
            $ancestor = $canonicalFile->versions()
                ->where('version', '<', $canonicalFile->version)
                ->latest('version')
                ->first();

            $disk = (string) config('synkk.storage_disk', 'local');
            if ($ancestor !== null && Storage::disk($disk)->exists($ancestor->storage_path)) {
                $base = (string) Storage::disk($disk)->get($ancestor->storage_path);
            }
        }

        $diff = $this->diffService->merge($base, $ours, $theirs);

        if (! $diff['has_conflicts']) {
            return [
                'canonical_path' => $derivedCanonical,
                'conflict_path' => $cleanConflictPath,
                'merged_content' => $diff['merged_content'],
                'summary' => 'No conflicting sections remained; applied deterministic 3-way merge cleanly.',
                'resolutions' => [],
                'conflict_count' => 0,
                'clean_count' => $diff['clean_count'],
                'model' => 'three-way-diff',
                'used_ai' => false,
                'had_unmerged_markers' => false,
            ];
        }

        $hunks = $diff['hunks'];
        $conflictPayloads = $this->extractConflictHunkPayloads($hunks);

        $messages = $this->buildMergeMessages(
            canonicalPath: $derivedCanonical,
            conflictPath: $cleanConflictPath,
            conflictHunks: $conflictPayloads,
            instructions: $instructions,
        );

        $response = $this->aiGateway->chat($vault->team, $messages, [
            'feature' => 'smart_merge',
            'fast' => true,
            'vault' => $vault,
            'user_id' => $user->id,
            'temperature' => 0.1,
            'max_tokens' => 4096,
            'response_format' => ['type' => 'json_object'],
        ]);

        $parsed = $this->decodeJsonPayload($response->content);
        if ($parsed === null || ! is_array($parsed['hunks'] ?? null)) {
            throw new AiException(
                'AI Smart Merge returned an invalid structured response. Please retry or resolve manually.',
                'INVALID_AI_OUTPUT',
                502,
            );
        }

        /** @var array<int, array{strategy: string, resolved_text: string, rationale: string}> $resolvedMap */
        $resolvedMap = [];
        foreach ($parsed['hunks'] as $entry) {
            if (! is_array($entry) || ! isset($entry['hunk_id']) || ! is_string($entry['resolved_text'] ?? null)) {
                continue;
            }

            $hunkId = (int) $entry['hunk_id'];
            $strategy = is_string($entry['strategy'] ?? null) ? trim($entry['strategy']) : 'semantic_merge';
            $rationale = is_string($entry['rationale'] ?? null) ? trim($entry['rationale']) : 'Merged both edits cleanly.';

            $resolvedMap[$hunkId] = [
                'strategy' => $strategy !== '' ? $strategy : 'semantic_merge',
                'resolved_text' => $entry['resolved_text'],
                'rationale' => $rationale !== '' ? $rationale : 'Merged both edits cleanly.',
            ];
        }

        $mergedLines = [];
        $resolutions = [];
        $hadUnmergedMarkers = false;

        foreach ($hunks as $hunk) {
            if (! $hunk['is_conflict']) {
                foreach ($hunk['resolved_lines'] as $line) {
                    $mergedLines[] = $line;
                }

                continue;
            }

            $hunkId = $hunk['id'];
            $aiHunk = $resolvedMap[$hunkId] ?? null;

            if (
                $aiHunk !== null
                && ! str_contains($aiHunk['resolved_text'], '<<<<<<<')
                && ! str_contains($aiHunk['resolved_text'], '>>>>>>>')
            ) {
                $normalizedText = str_replace(["\r\n", "\r"], "\n", $aiHunk['resolved_text']);
                if ($normalizedText !== '') {
                    foreach (explode("\n", $normalizedText) as $line) {
                        $mergedLines[] = $line;
                    }
                }

                $resolutions[] = [
                    'hunk_id' => $hunkId,
                    'strategy' => $aiHunk['strategy'],
                    'rationale' => $aiHunk['rationale'],
                ];

                continue;
            }

            $hadUnmergedMarkers = true;
            $mergedLines[] = '<<<<<<< CURRENT (Ours)';
            foreach ($hunk['our_lines'] as $line) {
                $mergedLines[] = $line;
            }
            $mergedLines[] = '=======';
            foreach ($hunk['their_lines'] as $line) {
                $mergedLines[] = $line;
            }
            $mergedLines[] = '>>>>>>> INCOMING (Theirs)';

            $resolutions[] = [
                'hunk_id' => $hunkId,
                'strategy' => 'manual_required',
                'rationale' => 'Retained conflict markers for manual inspection.',
            ];
        }

        $summary = is_string($parsed['summary'] ?? null) && trim($parsed['summary']) !== ''
            ? trim($parsed['summary'])
            : 'Merged conflicting Markdown sections cleanly while preserving all non-conflicting content.';

        return [
            'canonical_path' => $derivedCanonical,
            'conflict_path' => $cleanConflictPath,
            'merged_content' => implode("\n", $mergedLines),
            'summary' => $summary,
            'resolutions' => $resolutions,
            'conflict_count' => $diff['conflict_count'],
            'clean_count' => $diff['clean_count'],
            'model' => $response->model,
            'used_ai' => true,
            'had_unmerged_markers' => $hadUnmergedMarkers,
        ];
    }

    /**
     * @throws AiException
     */
    protected function normalizeRelativePath(string $rawPath): string
    {
        $clean = trim(str_replace('\\', '/', $rawPath), '/');
        if ($clean === '' || str_contains($clean, "\0")) {
            throw new AiException('Note path cannot be empty.', 'INVALID_PATH', 422);
        }

        foreach (explode('/', $clean) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new AiException("Invalid path segment in '{$rawPath}'.", 'INVALID_PATH', 422);
            }
        }

        return $clean;
    }

    protected function deriveCanonicalPath(string $conflictPath): string
    {
        $derived = (string) preg_replace('/(\.conflict-[^.]+|\.sync-conflict-[^.]+)(\.[^.]+)$/', '$2', $conflictPath);
        if ($derived === $conflictPath) {
            $derived = (string) preg_replace('/(\.conflict-[^.]+|\.sync-conflict-[^.]+)$/', '', $conflictPath);
        }

        return $derived;
    }

    /**
     * @param array<int, array{
     *     id: int,
     *     type: string,
     *     is_conflict: bool,
     *     base_lines: array<int, string>,
     *     our_lines: array<int, string>,
     *     their_lines: array<int, string>,
     *     resolved_lines: array<int, string>,
     *     choice: string
     * }> $hunks
     * @return array<int, array{
     *     hunk_id: int,
     *     context_before: string,
     *     base: string,
     *     ours: string,
     *     theirs: string,
     *     context_after: string
     * }>
     */
    protected function extractConflictHunkPayloads(array $hunks): array
    {
        $payloads = [];
        $count = count($hunks);

        for ($i = 0; $i < $count; $i++) {
            $hunk = $hunks[$i];
            if (! $hunk['is_conflict']) {
                continue;
            }

            $beforeLines = $i > 0 ? array_slice($hunks[$i - 1]['resolved_lines'], -3) : [];
            $afterLines = ($i + 1) < $count ? array_slice($hunks[$i + 1]['resolved_lines'], 0, 3) : [];

            $payloads[] = [
                'hunk_id' => $hunk['id'],
                'context_before' => implode("\n", $beforeLines),
                'base' => implode("\n", $hunk['base_lines']),
                'ours' => implode("\n", $hunk['our_lines']),
                'theirs' => implode("\n", $hunk['their_lines']),
                'context_after' => implode("\n", $afterLines),
            ];
        }

        return $payloads;
    }

    /**
     * @param array<int, array{
     *     hunk_id: int,
     *     context_before: string,
     *     base: string,
     *     ours: string,
     *     theirs: string,
     *     context_after: string
     * }> $conflictHunks
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildMergeMessages(
        string $canonicalPath,
        string $conflictPath,
        array $conflictHunks,
        ?string $instructions,
    ): array {
        $systemPrompt = <<<'PROMPT'
You are Synkk Smart Merge, an expert Markdown and Obsidian conflict resolution engine.
Your job is to resolve conflicting 3-way diff hunks cleanly while preserving valid edits from BOTH collaborators unless they directly contradict, in which case prefer the clearest, most complete union or YAML/task consistency.

Rules:
1. Never emit git conflict markers (`<<<<<<<`, `=======`, `>>>>>>>`) inside `resolved_text`.
2. Preserve Markdown formatting, wikilinks `[[...]]`, tags `#...`, checkboxes `- [ ]` / `- [x]`, and YAML frontmatter syntax.
3. Return ONLY valid JSON matching this exact schema:
{
  "summary": "1-2 sentence overview of how the conflict was reconciled",
  "hunks": [
    {
      "hunk_id": 0,
      "strategy": "semantic_merge",
      "resolved_text": "exact replacement lines without surrounding context",
      "rationale": "short explanation of why these lines were kept/combined"
    }
  ]
}
Allowed values for `strategy`: "semantic_merge", "keep_ours", "keep_theirs", "combine_both".
PROMPT;

        $userPayload = [
            'canonical_path' => $canonicalPath,
            'conflict_path' => $conflictPath,
            'user_instructions' => $instructions !== null && trim($instructions) !== '' ? trim($instructions) : null,
            'conflict_hunks' => $conflictHunks,
        ];

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => (string) json_encode($userPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)],
        ];
    }

    /**
     * Decode a JSON object from an LLM response, tolerating optional code fences.
     *
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
