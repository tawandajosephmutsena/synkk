<?php

namespace App\Services;

use App\Models\Vault;
use App\Models\VaultFile;
use App\Models\VaultFileEmbedding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VaultRagService
{
    public function __construct(
        protected EmbeddingService $embeddingService,
        protected KnowledgeGraphService $graphService
    ) {}

    /**
     * Incrementally index all markdown notes in a vault.
     *
     * @return array{
     *     status: string,
     *     files_indexed: int,
     *     chunks_count: int,
     *     duration_ms: int
     * }
     */
    public function indexVault(Vault $vault, bool $force = false): array
    {
        if ($vault->is_e2ee) {
            Cache::put("vault_rag_progress_{$vault->id}", [
                'status' => 'skipped',
                'percentage' => 100,
                'total_files' => 0,
                'indexed_files' => 0,
                'current_file' => null,
                'chunks_count' => 0,
                'duration_ms' => 0,
                'message' => 'Vault has Zero-Knowledge E2EE enabled. Server-side RAG indexing is disabled.',
                'updated_at' => now()->toIso8601String(),
            ], now()->addHours(2));

            return [
                'status' => 'skipped',
                'files_indexed' => 0,
                'chunks_count' => 0,
                'duration_ms' => 0,
                'message' => 'Vault has Zero-Knowledge E2EE enabled. Server-side RAG indexing is disabled.',
            ];
        }

        $startTime = microtime(true);

        $markdownFiles = VaultFile::where('vault_id', $vault->id)
            ->where('is_deleted', false)
            ->where('path', 'like', '%.md')
            ->get();

        $totalFiles = $markdownFiles->count();

        Cache::put("vault_rag_progress_{$vault->id}", [
            'status' => 'indexing',
            'percentage' => $totalFiles > 0 ? 0 : 100,
            'total_files' => $totalFiles,
            'indexed_files' => 0,
            'current_file' => null,
            'chunks_count' => 0,
            'duration_ms' => 0,
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(2));

        // 1. Remove orphaned embeddings for deleted files
        $activeFileIds = $markdownFiles->pluck('id')->all();
        VaultFileEmbedding::where('vault_id', $vault->id)
            ->whereNotIn('vault_file_id', $activeFileIds)
            ->delete();

        $totalChunksCount = 0;
        $filesIndexed = 0;

        foreach ($markdownFiles as $file) {
            if ($file->is_encrypted) {
                continue;
            }

            $content = $file->getContents();
            if ($content === null || trim($content) === '') {
                VaultFileEmbedding::where('vault_file_id', $file->id)->delete();

                continue;
            }

            $chunks = $this->embeddingService->chunkMarkdown($content);
            $validChunkIndexes = [];

            foreach ($chunks as $chunk) {
                $validChunkIndexes[] = $chunk['chunk_index'];
                $totalChunksCount++;

                $existing = VaultFileEmbedding::where('vault_file_id', $file->id)
                    ->where('chunk_index', $chunk['chunk_index'])
                    ->first();

                if (! $force && $existing && $existing->content_hash === $chunk['content_hash']) {
                    continue;
                }

                $vector = $this->embeddingService->generate($chunk['content']);

                VaultFileEmbedding::updateOrCreate(
                    [
                        'vault_file_id' => $file->id,
                        'chunk_index' => $chunk['chunk_index'],
                    ],
                    [
                        'vault_id' => $vault->id,
                        'heading' => $chunk['heading'],
                        'start_line' => $chunk['start_line'],
                        'content' => $chunk['content'],
                        'token_count' => $chunk['token_count'],
                        'embedding' => $vector,
                        'content_hash' => $chunk['content_hash'],
                        'wikilinks' => $chunk['wikilinks'],
                    ]
                );
            }

            // Remove chunks that no longer exist in the file
            VaultFileEmbedding::where('vault_file_id', $file->id)
                ->whereNotIn('chunk_index', $validChunkIndexes)
                ->delete();

            $filesIndexed++;

            $currentPct = $totalFiles > 0 ? (int) round(($filesIndexed / $totalFiles) * 100) : 100;
            Cache::put("vault_rag_progress_{$vault->id}", [
                'status' => 'indexing',
                'percentage' => $currentPct,
                'total_files' => $totalFiles,
                'indexed_files' => $filesIndexed,
                'current_file' => $file->path,
                'chunks_count' => $totalChunksCount,
                'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
                'updated_at' => now()->toIso8601String(),
            ], now()->addHours(2));
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        Cache::put("vault_rag_progress_{$vault->id}", [
            'status' => 'completed',
            'percentage' => 100,
            'total_files' => $totalFiles,
            'indexed_files' => $filesIndexed,
            'current_file' => null,
            'chunks_count' => $totalChunksCount,
            'duration_ms' => $durationMs,
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(2));

        return [
            'status' => 'indexed',
            'files_indexed' => $filesIndexed,
            'chunks_count' => $totalChunksCount,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * Get real-time indexing progress and percentage.
     *
     * @return array<string, mixed>
     */
    public function getProgress(Vault $vault): array
    {
        $default = [
            'status' => 'idle',
            'percentage' => 0,
            'total_files' => 0,
            'indexed_files' => 0,
            'current_file' => null,
            'chunks_count' => 0,
            'duration_ms' => 0,
            'updated_at' => now()->toIso8601String(),
        ];

        return Cache::get("vault_rag_progress_{$vault->id}", $default);
    }

    /**
     * Perform hybrid semantic search across vault chunk embeddings.
     *
     * @return array<int, array{
     *     file_id: int,
     *     path: string,
     *     heading: string|null,
     *     start_line: int,
     *     similarity: float,
     *     score_pct: int,
     *     content: string,
     *     wikilinks: array<int, string>
     * }>
     */
    public const STOPWORDS = [
        'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and', 'any', 'are', 'aren\'t', 'as', 'at',
        'be', 'because', 'been', 'before', 'being', 'below', 'between', 'both', 'but', 'by',
        'can', 'can\'t', 'cannot', 'could', 'couldn\'t',
        'did', 'didn\'t', 'do', 'does', 'doesn\'t', 'doing', 'don\'t', 'down', 'during',
        'each',
        'few', 'for', 'from', 'further',
        'had', 'hadn\'t', 'has', 'hasn\'t', 'have', 'haven\'t', 'having', 'he', 'he\'d', 'he\'ll', 'he\'s', 'her', 'here', 'here\'s', 'hers', 'herself', 'him', 'himself', 'his', 'how', 'how\'s',
        'i', 'i\'d', 'i\'ll', 'i\'m', 'i\'ve', 'if', 'in', 'into', 'is', 'isn\'t', 'it', 'it\'s', 'its', 'itself',
        'let\'s',
        'me', 'more', 'most', 'mustn\'t', 'my', 'myself',
        'no', 'nor', 'not',
        'of', 'off', 'on', 'once', 'only', 'or', 'other', 'ought', 'our', 'ours', 'ourselves', 'out', 'over', 'own',
        'same', 'shan\'t', 'she', 'she\'d', 'she\'ll', 'she\'s', 'should', 'shouldn\'t', 'so', 'some', 'such',
        'than', 'that', 'that\'s', 'the', 'their', 'theirs', 'them', 'themselves', 'then', 'there', 'there\'s', 'these', 'they', 'they\'d', 'they\'ll', 'they\'re', 'they\'ve', 'this', 'those', 'through', 'to', 'too',
        'under', 'until', 'up',
        'very',
        'was', 'wasn\'t', 'we', 'we\'d', 'we\'ll', 'we\'re', 'we\'ve', 'were', 'weren\'t', 'what', 'what\'s', 'when', 'when\'s', 'where', 'where\'s', 'which', 'while', 'who', 'who\'s', 'whom', 'why', 'why\'s', 'with', 'won\'t', 'would', 'wouldn\'t',
        'you', 'you\'d', 'you\'ll', 'you\'re', 'you\'ve', 'your', 'yours', 'yourself', 'yourselves',
        'tell', 'give', 'show', 'explain', 'find', 'summarize', 'notes', 'note', 'vault',
    ];

    /**
     * Extract meaningful search keywords from user query, filtering out common stopwords.
     *
     * @return array<int, string>
     */
    public function extractQueryKeywords(string $query): array
    {
        $tokens = preg_split('/[^a-z0-9_\-\']/i', strtolower($query)) ?: [];
        $substantive = array_values(array_filter($tokens, function ($token) {
            return strlen($token) >= 3 && ! in_array($token, self::STOPWORDS, true);
        }));

        if (! empty($substantive)) {
            return $substantive;
        }

        // Fallback to all tokens with length >= 3 if all were filtered
        return array_values(array_filter($tokens, fn ($t) => strlen($t) >= 3));
    }

    /**
     * Extract a clean, highly relevant excerpt from chunk content based on query keywords.
     * Strips leading markdown headings, bullet markers, and false sentence breaks like '5.'.
     *
     * @param  array<int, string>  $keywords
     */
    public function extractBestExcerpt(string $content, array $keywords = [], ?string $heading = null): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $cleanedLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            // Strip leading heading markdown
            if (preg_match('/^#{1,6}\s+(.*)$/', $trimmed, $m)) {
                $headingText = trim($m[1]);
                if ($heading && strcasecmp($headingText, $heading) === 0) {
                    continue;
                }
                $trimmed = $headingText;
            }

            // Strip list markers and checkboxes (e.g. "5. ", "- [ ] ", "* ")
            $trimmed = preg_replace('/^(\*|-|\+|\d+\.)\s+(\[[ xX]\]\s+)?/', '', $trimmed);
            // Strip blockquotes
            $trimmed = preg_replace('/^>\s*/', '', $trimmed);

            if ($trimmed !== '') {
                $cleanedLines[] = $trimmed;
            }
        }

        $cleanText = implode(' ', $cleanedLines);
        $cleanText = trim(preg_replace('/\s+/', ' ', $cleanText));

        if ($cleanText === '') {
            return $heading ? "Section: {$heading}" : 'Note content';
        }

        // Split into candidate sentences avoiding splitting on numbers like "5."
        $candidates = preg_split('/(?<!\b\d)(?<=[.!?])\s+(?=[A-Z0-9"\'`])/', $cleanText) ?: [];

        $validCandidates = array_values(array_filter($candidates, function ($c) {
            $words = str_word_count($c);

            return strlen(trim($c)) >= 15 && $words >= 3;
        }));

        if (empty($validCandidates)) {
            $validCandidates = [$cleanText];
        }

        $bestCandidate = $validCandidates[0];
        $bestScore = -1;

        foreach ($validCandidates as $candidate) {
            $candLower = strtolower($candidate);
            $score = 0;

            foreach ($keywords as $kw) {
                if (str_contains($candLower, $kw)) {
                    $score += 3;
                }
            }

            $len = strlen($candidate);
            if ($len >= 40 && $len <= 220) {
                $score += 1;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCandidate = $candidate;
            }
        }

        $bestCandidate = trim($bestCandidate, " \t\n\r\0\x0B-*>#");

        if (mb_strlen($bestCandidate) > 230) {
            $bestCandidate = mb_substr($bestCandidate, 0, 227).'...';
        }

        return $bestCandidate;
    }

    /**
     * Perform hybrid semantic search across vault chunk embeddings.
     *
     * @return array<int, array{
     *     file_id: int,
     *     path: string,
     *     heading: string|null,
     *     start_line: int,
     *     similarity: float,
     *     score_pct: int,
     *     content: string,
     *     wikilinks: array<int, string>
     * }>
     */
    public function search(Vault $vault, string $query, int $limit = 5): array
    {
        if ($vault->is_e2ee) {
            return [];
        }

        $cleanQuery = trim($query);
        if ($cleanQuery === '') {
            return [];
        }

        $queryVector = $this->embeddingService->generate($cleanQuery);
        $queryKeywords = $this->extractQueryKeywords($cleanQuery);

        $embeddings = VaultFileEmbedding::with('file')
            ->where('vault_id', $vault->id)
            ->whereHas('file', fn ($q) => $q->where('is_deleted', false))
            ->get();

        $scored = [];

        foreach ($embeddings as $record) {
            if ($record->file->is_deleted) {
                continue;
            }

            $vectorSimilarity = $this->embeddingService->cosineSimilarity($queryVector, $record->embedding ?? []);

            // Sparse Lexical Hit Score
            $chunkLower = strtolower($record->content);
            $headingLower = strtolower($record->heading ?? '');
            $pathLower = strtolower($record->file->path);

            $rawHits = 0.0;
            $matchedKeywords = 0;
            if (! empty($queryKeywords)) {
                foreach ($queryKeywords as $kw) {
                    $matched = false;
                    if (str_contains($headingLower, $kw)) {
                        $rawHits += 2.5;
                        $matched = true;
                    }
                    if (str_contains($pathLower, $kw)) {
                        $rawHits += 2.0;
                        $matched = true;
                    }
                    if (str_contains($chunkLower, $kw)) {
                        $rawHits += 1.2;
                        $matched = true;
                    }
                    if ($matched) {
                        $matchedKeywords++;
                    }
                }
            }

            $coverage = ! empty($queryKeywords) ? ($matchedKeywords / count($queryKeywords)) : 0.0;
            $lexicalScore = ! empty($queryKeywords) ? min(1.0, $rawHits / (count($queryKeywords) * 2.0)) : 0.0;

            if (! empty($queryKeywords)) {
                if ($matchedKeywords > 0) {
                    $composite = ($vectorSimilarity * 0.40) + ($lexicalScore * 0.35) + ($coverage * 0.25);
                    if (str_contains($chunkLower, strtolower($cleanQuery))) {
                        $composite = min(1.0, $composite + 0.15);
                    }
                } else {
                    // Suppress dense vector baseline noise for documents with 0 keyword matches
                    $composite = $vectorSimilarity * 0.40;
                }
            } else {
                $composite = $vectorSimilarity;
            }

            $composite = max(0.0, min(1.0, $composite));

            $scored[] = [
                'file_id' => $record->vault_file_id,
                'path' => $record->file->path,
                'heading' => $record->heading,
                'start_line' => $record->start_line,
                'similarity' => round($vectorSimilarity, 4),
                'score' => $composite,
                'score_pct' => (int) round($composite * 100),
                'content' => $record->content,
                'wikilinks' => $record->wikilinks ?? [],
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * Execute an agentic RAG query with graph-augmented retrieval and answer synthesis.
     *
     * @param array{
     *     provider?: string,
     *     expand_graph?: bool,
     *     max_citations?: int
     * } $options
     * @return array{
     *     query: string,
     *     answer: string,
     *     citations: array<int, array{
     *         note: string,
     *         heading: string|null,
     *         start_line: int,
     *         similarity: float,
     *         score_pct: int,
     *         excerpt: string
     *     }>,
     *     graph_nodes: array<int, array{
     *         path: string,
     *         title: string,
     *         relationship: string,
     *         via: string,
     *         excerpt: string|null
     *     }>,
     *     model: string,
     *     duration_ms: int
     * }
     */
    public function query(Vault $vault, string $query, array $options = []): array
    {
        if ($vault->is_e2ee) {
            return [
                'query' => $query,
                'answer' => 'Vault Copilot RAG is disabled on Zero-Knowledge E2EE vaults to protect privacy. Server storage contains ciphertext and cannot be decrypted without your client passphrase.',
                'citations' => [],
                'graph_nodes' => [],
                'model' => 'synkk/e2ee-guarded',
                'duration_ms' => 0,
            ];
        }

        $startTime = microtime(true);
        $maxCitations = $options['max_citations'] ?? 4;
        $expandGraph = $options['expand_graph'] ?? true;
        $keywords = $this->extractQueryKeywords($query);

        // 1. Hybrid Retrieval
        $topChunks = $this->search($vault, $query, $maxCitations);

        // 2. Graph Backlink Traversal (Graph-Augmented RAG)
        $seedPaths = array_values(array_unique(array_column($topChunks, 'path')));
        $graphNodes = $expandGraph ? $this->graphService->expandContext($vault, $seedPaths, depth: 1) : [];

        // 3. Compile Citations with clean excerpts
        $citations = [];
        foreach ($topChunks as $chunk) {
            $excerpt = $this->extractBestExcerpt($chunk['content'], $keywords, $chunk['heading']);
            $citations[] = [
                'note' => $chunk['path'],
                'heading' => $chunk['heading'],
                'start_line' => $chunk['start_line'],
                'similarity' => $chunk['similarity'],
                'score_pct' => $chunk['score_pct'],
                'excerpt' => $excerpt,
            ];
        }

        // 4. Synthesize Answer (Local LLM / Cloud LLM / Refined Deterministic Reasoning)
        $llmResult = $this->synthesizeWithLlm($query, $topChunks, $graphNodes);

        if ($llmResult !== null) {
            $answer = $llmResult['answer'];
            $model = $llmResult['model'];
        } else {
            $answer = $this->synthesizeDeterministic($query, $topChunks, $graphNodes);
            $model = 'synkk/deterministic-reasoning';
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'query' => $query,
            'answer' => $answer,
            'citations' => $citations,
            'graph_nodes' => array_slice($graphNodes, 0, 5),
            'model' => $model,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * Synthesize answer using local Ollama instance or OpenAI if available.
     *
     * @param  array<int, mixed>  $chunks
     * @param  array<int, mixed>  $graphNodes
     * @return array{answer: string, model: string}|null
     */
    protected function synthesizeWithLlm(string $query, array $chunks, array $graphNodes): ?array
    {
        if (empty($chunks)) {
            return null;
        }

        $contextParts = [];
        foreach ($chunks as $c) {
            $headingStr = $c['heading'] ? " > {$c['heading']}" : '';
            $contextParts[] = "--- Note: [[{$c['path']}]{$headingStr} ---\n".$c['content'];
        }

        if (! empty($graphNodes)) {
            $contextParts[] = '--- Connected Graph Context (Backlinks) ---';
            foreach (array_slice($graphNodes, 0, 3) as $g) {
                $contextParts[] = "Connected Note [[{$g['path']}]] (via {$g['via']}): {$g['excerpt']}";
            }
        }

        $prompt = "You are Synkk Vault Copilot, an AI assistant answering questions about the user's private Obsidian vault.\n".
            "Context retrieved from vault:\n".implode("\n\n", $contextParts)."\n\n".
            "Question: {$query}\n\n".
            'Instructions: Provide a concise, accurate answer based strictly on the provided vault context. Cite relevant notes using [[NoteName]] syntax.';

        // 1. Try Local Ollama first
        $ollamaUrl = rtrim((string) config('synkk.rag.ollama_url', 'http://localhost:11434'), '/');
        $model = (string) config('synkk.rag.llm_model', 'llama3.2');

        try {
            $response = Http::timeout(3.0)->post("{$ollamaUrl}/api/generate", [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
            ]);

            if ($response->successful()) {
                $text = $response->json('response');
                if (! empty($text)) {
                    return [
                        'answer' => trim($text),
                        'model' => "ollama/{$model}",
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Ollama RAG synthesis skipped: '.$e->getMessage());
        }

        // 2. Try OpenAI if configured
        $openaiKey = config('synkk.rag.openai_api_key');
        if (! empty($openaiKey)) {
            $openaiModel = (string) config('synkk.rag.openai_model', 'gpt-4o-mini');
            try {
                $response = Http::withToken($openaiKey)
                    ->timeout(8.0)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $openaiModel,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => "You are Synkk Vault Copilot, an AI assistant answering questions about the user's private Obsidian vault. Synthesize answers strictly based on the provided vault context. Cite relevant notes using [[NoteName]] syntax.",
                            ],
                            [
                                'role' => 'user',
                                'content' => "Context retrieved from vault:\n".implode("\n\n", $contextParts)."\n\nQuestion: {$query}",
                            ],
                        ],
                        'temperature' => 0.2,
                    ]);

                if ($response->successful()) {
                    $text = $response->json('choices.0.message.content');
                    if (! empty($text)) {
                        return [
                            'answer' => trim($text),
                            'model' => "openai/{$openaiModel}",
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('OpenAI RAG synthesis skipped: '.$e->getMessage());
            }
        }

        return null;
    }

    /**
     * Deterministic extractive reasoning synthesizer.
     * Produces clean, structured markdown with direct findings, note references, and graph context.
     *
     * @param  array<int, mixed>  $chunks
     * @param  array<int, mixed>  $graphNodes
     */
    protected function synthesizeDeterministic(string $query, array $chunks, array $graphNodes): string
    {
        if (empty($chunks)) {
            return "### No Direct Matches Found\n\nNo matching notes or concepts were found in this vault for \"**{$query}**\". Try adjusting your search keywords or re-indexing your vault.";
        }

        $keywords = $this->extractQueryKeywords($query);
        $topChunk = $chunks[0];
        $isHighConfidence = $topChunk['score_pct'] >= 45;

        $lines = [];

        if (! $isHighConfidence) {
            $lines[] = "> ⚠️ **Low confidence match:** No direct high-confidence answers were found for \"**{$query}**\" in your vault. Below are the closest matching notes and concepts:";
            $lines[] = '';
        } else {
            $lines[] = '### Summary & Findings';
            $lines[] = '';

            $topExcerpt = $this->extractBestExcerpt($topChunk['content'], $keywords, $topChunk['heading']);
            $topHeadingStr = $topChunk['heading'] ? " (*{$topChunk['heading']}*)" : '';

            $lines[] = "Based on your vault's notes, the most relevant information is found in **[[{$topChunk['path']}]]**{$topHeadingStr} ({$topChunk['score_pct']}% match):";
            $lines[] = '';
            $lines[] = "> \"{$topExcerpt}\"";
            $lines[] = '';
        }

        // Relevant Notes & References
        $lines[] = '### Relevant Notes & References';
        $lines[] = '';

        $displayedCount = 0;
        foreach ($chunks as $chunk) {
            if ($displayedCount >= 3) {
                break;
            }

            $excerpt = $this->extractBestExcerpt($chunk['content'], $keywords, $chunk['heading']);
            $headingLabel = $chunk['heading'] ? " &rsaquo; *{$chunk['heading']}*" : '';
            $noteRef = "[[{$chunk['path']}]]";

            $lines[] = "- **{$noteRef}**{$headingLabel} — `{$chunk['score_pct']}% match`";
            $lines[] = "  {$excerpt}";
            $lines[] = '';

            $displayedCount++;
        }

        // Backlink connections
        if (! empty($graphNodes)) {
            $lines[] = '### Connected Concepts (Knowledge Graph)';
            $lines[] = '';
            foreach (array_slice($graphNodes, 0, 3) as $node) {
                $lines[] = "- Note **[[{$node['path']}]]** connects via *{$node['via']}* with related context.";
            }
            $lines[] = '';
        }

        if (! $isHighConfidence) {
            $lines[] = '---';
            $lines[] = '💡 **Tip:** Try refining your query with specific note titles, tags, or domain terms, or re-index your vault embeddings from the top-right menu.';
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Get RAG index and health status for a vault.
     *
     * @return array{
     *     indexed: bool,
     *     total_files: int,
     *     total_chunks: int,
     *     embedding_provider: string,
     *     llm_provider: string,
     *     last_indexed_at: string|null
     * }
     */
    public function getStatus(Vault $vault): array
    {
        $totalFiles = VaultFile::where('vault_id', $vault->id)
            ->where('is_deleted', false)
            ->where('path', 'like', '%.md')
            ->count();

        $embeddingsQuery = VaultFileEmbedding::where('vault_id', $vault->id);
        $totalChunks = $embeddingsQuery->count();
        $latestRecord = $embeddingsQuery->latest('updated_at')->first();

        return [
            'indexed' => ($totalChunks > 0),
            'total_files' => $totalFiles,
            'total_chunks' => $totalChunks,
            'embedding_provider' => config('synkk.rag.provider', 'deterministic'),
            'llm_provider' => config('synkk.rag.llm_provider', 'local-first'),
            'last_indexed_at' => $latestRecord?->updated_at?->toIso8601String(),
        ];
    }
}
