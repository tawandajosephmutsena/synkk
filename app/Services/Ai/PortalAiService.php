<?php

namespace App\Services\Ai;

use App\Exceptions\AiException;
use App\Models\AiUsageLog;
use App\Models\Vault;
use App\Models\VaultFile;
use App\Models\VaultPortal;
use App\Services\PlanService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PortalAiService
{
    public const MAX_PER_IP_PER_MINUTE = 10;

    public function __construct(
        protected AiGateway $aiGateway,
        protected HybridVaultSearchService $hybridSearch,
        protected PlanService $planService,
    ) {}

    /**
     * Determine whether "Ask This Portal" AI is active and allowed on the given portal.
     */
    public function canUsePortalAi(VaultPortal $portal): bool
    {
        if (! $portal->ai_enabled) {
            return false;
        }

        $vault = $portal->vault;
        $team = $portal->team;

        if ($vault->is_e2ee || $vault->team_id !== $portal->team_id) {
            return false;
        }

        if (! $this->planService->hasFeature($team, 'ai_portal_assistant')) {
            return false;
        }

        $aiConfig = $team->aiConfig;
        if ($aiConfig === null || ! $aiConfig->is_enabled || $aiConfig->provider_mode === 'off') {
            return false;
        }

        $aiSetting = $vault->aiSetting;
        if ($aiSetting === null || $aiSetting->ai_mode !== 'server') {
            return false;
        }

        return true;
    }

    /**
     * Collect Markdown notes within the portal's root_path while filtering out excluded paths and hidden ACL rules.
     *
     * @return Collection<int, VaultFile>
     */
    public function getEligibleMarkdownFiles(VaultPortal $portal): Collection
    {
        $vault = $portal->vault;
        $vault->loadMissing(['aiSetting', 'permissions']);
        $aiSetting = $vault->aiSetting;

        return $portal->getAccessibleFiles()
            ->filter(function (VaultFile $file) use ($vault, $aiSetting): bool {
                if (! $file->isMarkdown() || $file->is_encrypted) {
                    return false;
                }

                $cleanPath = trim(str_replace('\\', '/', $file->path), '/');
                if ($cleanPath === '' || str_contains($cleanPath, '.conflict-') || str_contains($cleanPath, '.sync-conflict-')) {
                    return false;
                }

                if ($aiSetting !== null && $aiSetting->isPathExcluded($cleanPath)) {
                    return false;
                }

                if ($this->isRestrictedByHiddenAcl($vault, $cleanPath)) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * Count remaining daily AI questions allowed on this portal.
     */
    public function remainingDailyQuestions(VaultPortal $portal): int
    {
        $limit = max(1, $portal->ai_daily_question_limit > 0 ? $portal->ai_daily_question_limit : 100);

        $usedToday = AiUsageLog::query()
            ->where('portal_id', $portal->id)
            ->where('feature', 'portal')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        return max(0, $limit - $usedToday);
    }

    /**
     * Answer a visitor's question strictly from published, accessible notes in the portal.
     *
     * @return array{
     *     answer: string,
     *     citations: array<int, array{path: string, title: string}>,
     *     model: string,
     *     remaining_today: int
     * }
     *
     * @throws AiException
     */
    public function ask(VaultPortal $portal, string $question, string $clientIp): array
    {
        $vault = $portal->vault;
        $team = $portal->team;

        if ($vault->team_id !== $portal->team_id) {
            throw new AiException('Portal configuration is invalid.', 'FORBIDDEN', 404);
        }

        if ($vault->is_e2ee) {
            throw new AiException('Portal AI is disabled on Zero-Knowledge E2EE vaults.', 'E2EE_BLOCKED', 403);
        }

        if (! $portal->ai_enabled) {
            throw new AiException('Ask This Portal AI is not enabled for this portal.', 'AI_DISABLED', 403);
        }

        if (! $this->planService->hasFeature($team, 'ai_portal_assistant')) {
            throw new AiException('Your workspace plan does not include Portal AI Assistant.', 'AI_DISABLED', 403);
        }

        $aiConfig = $team->aiConfig;
        if ($aiConfig === null || ! $aiConfig->is_enabled || $aiConfig->provider_mode === 'off') {
            throw new AiException('Synkk AI Add-On is disabled for this workspace.', 'AI_DISABLED', 403);
        }

        $aiSetting = $vault->aiSetting;
        if ($aiSetting === null || $aiSetting->ai_mode !== 'server') {
            throw new AiException('Server-side AI is not enabled on the source vault.', 'AI_DISABLED', 403);
        }

        $cleanQuestion = trim($question);
        if ($cleanQuestion === '' || mb_strlen($cleanQuestion) > 1000) {
            throw new AiException('Please enter a question under 1000 characters.', 'INVALID_INPUT', 422);
        }

        $ipKey = "portal-ai-ip:{$portal->id}:".sha1($clientIp);
        if (RateLimiter::tooManyAttempts($ipKey, self::MAX_PER_IP_PER_MINUTE)) {
            throw new AiException(
                'Too many questions from your connection. Please wait a minute and try again.',
                'RATE_LIMITED',
                429,
            );
        }

        $limit = max(1, $portal->ai_daily_question_limit > 0 ? $portal->ai_daily_question_limit : 100);
        $usedToday = AiUsageLog::query()
            ->where('portal_id', $portal->id)
            ->where('feature', 'portal')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($usedToday >= $limit) {
            throw new AiException('This portal has reached its daily AI question limit.', 'QUOTA_EXCEEDED', 429);
        }

        RateLimiter::hit($ipKey, 60);

        $eligibleFiles = $this->getEligibleMarkdownFiles($portal);
        if ($eligibleFiles->isEmpty()) {
            return [
                'answer' => 'No published notes are currently available in this portal to answer that question.',
                'citations' => [],
                'model' => 'none',
                'remaining_today' => max(0, $limit - $usedToday),
            ];
        }

        $contexts = $this->selectScopedContexts($vault, $eligibleFiles, $cleanQuestion);
        $citations = array_map(fn (array $item): array => [
            'path' => $item['path'],
            'title' => $item['title'],
        ], $contexts);

        $systemPrompt = <<<'PROMPT'
You are an AI documentation assistant embedded inside a published Synkk Portal.
Answer the visitor's question using ONLY the provided portal note excerpts.
If the answer cannot be verified from the excerpts, say clearly that the published portal documentation does not cover it.
Do NOT invent facts, URLs, or reference unpublished notes. Cite relevant note titles clearly.
PROMPT;

        $userPayload = [
            'portal_name' => $portal->name,
            'question' => $cleanQuestion,
            'published_excerpts' => $contexts,
        ];

        $response = $this->aiGateway->chat($team, [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => (string) json_encode($userPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)],
        ], [
            'feature' => 'portal',
            'fast' => true,
            'vault' => $vault,
            'portal_id' => $portal->id,
            'temperature' => 0.2,
            'max_tokens' => 1500,
        ]);

        return [
            'answer' => trim($response->content),
            'citations' => $citations,
            'model' => $response->model,
            'remaining_today' => max(0, $limit - ($usedToday + 1)),
        ];
    }

    /**
     * Render an llmstxt.org compliant plaintext Markdown directory for AI crawlers and agents.
     */
    public function renderLlmsTxt(VaultPortal $portal): string
    {
        $files = $this->getEligibleMarkdownFiles($portal);

        $lines = ["# {$portal->name}", ''];

        if (filled($portal->description)) {
            $lines[] = '> '.str_replace(["\r", "\n"], ' ', trim((string) $portal->description));
            $lines[] = '';
        }

        $lines[] = "Portal URL: {$portal->getUrl()}";
        $lines[] = '';
        $lines[] = '## Notes';
        $lines[] = '';

        if ($files->isEmpty()) {
            $lines[] = '- No public Markdown notes published yet.';

            return implode("\n", $lines)."\n";
        }

        foreach ($files as $file) {
            $content = (string) ($file->getContents() ?? '');
            $title = $this->extractNoteTitle($file, $content);
            $summary = $this->extractNoteSummary($content);
            $encodedSegments = implode('/', array_map('rawurlencode', explode('/', ltrim(str_replace('\\', '/', $file->path), '/'))));
            $noteUrl = url("/p/{$portal->slug}/{$encodedSegments}");

            $lines[] = "- [{$title}]({$noteUrl}): {$summary}";
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Retrieve bounded excerpts strictly constrained to eligible portal files.
     *
     * @param  Collection<int, VaultFile>  $eligibleFiles
     * @return array<int, array{path: string, title: string, excerpt: string}>
     */
    protected function selectScopedContexts(Vault $vault, Collection $eligibleFiles, string $question): array
    {
        /** @var Collection<int, VaultFile> $allowedById */
        $allowedById = $eligibleFiles->keyBy('id');

        $selected = [];
        $seenFileIds = [];
        $totalChars = 0;
        $maxTotalChars = 6500;

        $searchHits = $this->hybridSearch->search($vault, $question, 12, null);
        foreach ($searchHits as $hit) {
            $fileId = (int) $hit['file_id'];
            if (! isset($allowedById[$fileId]) || isset($seenFileIds[$fileId])) {
                continue;
            }

            /** @var VaultFile $file */
            $file = $allowedById[$fileId];
            $content = (string) ($file->getContents() ?? '');
            $excerptSource = trim((string) $hit['content']) !== '' ? (string) $hit['content'] : $content;
            $excerpt = Str::limit($excerptSource, 1200);

            if ($excerpt === '') {
                continue;
            }

            $selected[] = [
                'path' => $file->path,
                'title' => $this->extractNoteTitle($file, $content),
                'excerpt' => $excerpt,
            ];
            $seenFileIds[$fileId] = true;
            $totalChars += mb_strlen($excerpt);

            if (count($selected) >= 5 || $totalChars >= $maxTotalChars) {
                return $selected;
            }
        }

        // Supplement / fallback with lexical scoring over eligible files
        $terms = array_values(array_filter(
            preg_split('/\s+/', strtolower(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $question) ?? '')) ?: [],
            fn (string $t): bool => mb_strlen($t) >= 2,
        ));

        $scored = [];
        foreach ($eligibleFiles as $file) {
            if (isset($seenFileIds[$file->id])) {
                continue;
            }

            $content = (string) ($file->getContents() ?? '');
            if (trim($content) === '') {
                continue;
            }

            $haystackPath = strtolower($file->path);
            $haystackBody = strtolower($content);
            $score = 0;

            foreach ($terms as $term) {
                if (str_contains($haystackPath, $term)) {
                    $score += 5;
                }
                if (str_contains($haystackBody, $term)) {
                    $score += 2;
                }
            }

            $scored[] = [
                'file' => $file,
                'content' => $content,
                'score' => $score,
            ];
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        foreach ($scored as $item) {
            /** @var VaultFile $file */
            $file = $item['file'];
            $content = $item['content'];
            $excerpt = Str::limit($content, 1200);

            $selected[] = [
                'path' => $file->path,
                'title' => $this->extractNoteTitle($file, $content),
                'excerpt' => $excerpt,
            ];
            $totalChars += mb_strlen($excerpt);

            if (count($selected) >= 5 || $totalChars >= $maxTotalChars) {
                break;
            }
        }

        return $selected;
    }

    protected function extractNoteTitle(VaultFile $file, string $content): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        if (preg_match('/^#\s+(.+)$/m', $normalized, $matches) === 1) {
            $heading = trim($matches[1]);
            if ($heading !== '') {
                return Str::limit($heading, 120, '');
            }
        }

        return pathinfo($file->path, PATHINFO_FILENAME);
    }

    protected function extractNoteSummary(string $content): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        if (preg_match('/^---\n(.*?)\n---\n?(.*)$/s', $normalized, $matches) === 1) {
            if (preg_match('/^summary\s*:\s*["\']?(.*?)["\']?\s*$/mi', $matches[1], $sumMatch) === 1) {
                $yamlSummary = trim($sumMatch[1]);
                if ($yamlSummary !== '') {
                    return Str::limit($yamlSummary, 160);
                }
            }
            $body = $matches[2];
        } else {
            $body = $normalized;
        }

        foreach (explode("\n", $body) as $rawLine) {
            $line = trim($rawLine);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '---') || str_starts_with($line, '```')) {
                continue;
            }

            $clean = trim(preg_replace('/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/', '$2$1', $line) ?? $line);
            $clean = trim((string) preg_replace('/^[>*\-+\s]+/', '', $clean));
            if ($clean !== '') {
                return Str::limit($clean, 160);
            }
        }

        return 'Published vault note.';
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
}
