<?php

namespace App\Services;

use App\Models\Vault;
use App\Models\VaultFile;
use App\Models\VaultFileEmbedding;
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
        $startTime = microtime(true);

        $markdownFiles = VaultFile::where('vault_id', $vault->id)
            ->where('is_deleted', false)
            ->where('path', 'like', '%.md')
            ->get();

        // 1. Remove orphaned embeddings for deleted files
        $activeFileIds = $markdownFiles->pluck('id')->all();
        VaultFileEmbedding::where('vault_id', $vault->id)
            ->whereNotIn('vault_file_id', $activeFileIds)
            ->delete();

        $totalChunksCount = 0;
        $filesIndexed = 0;

        foreach ($markdownFiles as $file) {
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
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'status' => 'indexed',
            'files_indexed' => $filesIndexed,
            'chunks_count' => $totalChunksCount,
            'duration_ms' => $durationMs,
        ];
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
        $cleanQuery = trim($query);
        if ($cleanQuery === '') {
            return [];
        }

        // Auto-index if embeddings are empty
        $existingCount = VaultFileEmbedding::where('vault_id', $vault->id)->count();
        if ($existingCount === 0) {
            $this->indexVault($vault);
        }

        $queryVector = $this->embeddingService->generate($cleanQuery);
        $queryKeywords = array_filter(
            preg_split('/[^a-z0-9_\-\']/i', strtolower($cleanQuery)) ?: [],
            fn ($k) => strlen($k) >= 3
        );

        $embeddings = VaultFileEmbedding::with('file')
            ->where('vault_id', $vault->id)
            ->get();

        $scored = [];

        foreach ($embeddings as $record) {
            if (! $record->file || $record->file->is_deleted) {
                continue;
            }

            $vectorSimilarity = $this->embeddingService->cosineSimilarity($queryVector, $record->embedding ?? []);

            // Sparse Lexical Hit Score
            $chunkLower = strtolower($record->content);
            $headingLower = strtolower($record->heading ?? '');
            $pathLower = strtolower($record->file->path);

            $lexicalScore = 0.0;
            if (! empty($queryKeywords)) {
                $hits = 0;
                foreach ($queryKeywords as $kw) {
                    if (str_contains($headingLower, $kw)) {
                        $hits += 2.0;
                    } elseif (str_contains($pathLower, $kw)) {
                        $hits += 1.5;
                    } elseif (str_contains($chunkLower, $kw)) {
                        $hits += 1.0;
                    }
                }
                $lexicalScore = min(1.0, $hits / (count($queryKeywords) * 1.5));
            }

            // Composite Score: 70% Dense Vector, 30% Lexical
            $composite = max(0.0, ($vectorSimilarity * 0.70) + ($lexicalScore * 0.30));

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
        $startTime = microtime(true);
        $maxCitations = $options['max_citations'] ?? 4;
        $expandGraph = $options['expand_graph'] ?? true;

        // 1. Hybrid Retrieval
        $topChunks = $this->search($vault, $query, $maxCitations);

        // 2. Graph Backlink Traversal (Graph-Augmented RAG)
        $seedPaths = array_values(array_unique(array_column($topChunks, 'path')));
        $graphNodes = $expandGraph ? $this->graphService->expandContext($vault, $seedPaths, depth: 1) : [];

        // 3. Compile Citations
        $citations = [];
        foreach ($topChunks as $chunk) {
            $excerpt = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($chunk['content']))), 0, 180).'...';
            $citations[] = [
                'note' => $chunk['path'],
                'heading' => $chunk['heading'],
                'start_line' => $chunk['start_line'],
                'similarity' => $chunk['similarity'],
                'score_pct' => $chunk['score_pct'],
                'excerpt' => $excerpt,
            ];
        }

        // 4. Synthesize Answer
        $llmAnswer = $this->synthesizeWithLlm($query, $topChunks, $graphNodes);
        $model = 'ollama/local';

        if ($llmAnswer === null) {
            $llmAnswer = $this->synthesizeDeterministic($query, $topChunks, $graphNodes);
            $model = 'synkk/deterministic-reasoning';
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'query' => $query,
            'answer' => $llmAnswer,
            'citations' => $citations,
            'graph_nodes' => array_slice($graphNodes, 0, 5),
            'model' => $model,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * Synthesize answer using local Ollama instance if available.
     *
     * @param  array<int, mixed>  $chunks
     * @param  array<int, mixed>  $graphNodes
     */
    protected function synthesizeWithLlm(string $query, array $chunks, array $graphNodes): ?string
    {
        $ollamaUrl = rtrim((string) config('synkk.rag.ollama_url', 'http://localhost:11434'), '/');
        $model = (string) config('synkk.rag.llm_model', 'llama3.2');

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

        try {
            $response = Http::timeout(4.0)->post("{$ollamaUrl}/api/generate", [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
            ]);

            if ($response->successful()) {
                $text = $response->json('response');
                if (! empty($text)) {
                    return trim($text);
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Ollama RAG synthesis skipped: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Deterministic extractive reasoning synthesizer.
     *
     * @param  array<int, mixed>  $chunks
     * @param  array<int, mixed>  $graphNodes
     */
    protected function synthesizeDeterministic(string $query, array $chunks, array $graphNodes): string
    {
        if (empty($chunks)) {
            return "No matching notes or concepts were found in this vault for \"{$query}\". Try adjusting your search query or re-indexing your vault.";
        }

        $topChunk = $chunks[0];
        $topPath = $topChunk['path'];
        $topHeading = $topChunk['heading'] ? '#'.$topChunk['heading'] : '';

        $lines = [];
        $lines[] = "Based on your vault's knowledge base and **[[{$topPath}{$topHeading}]]** ({$topChunk['score_pct']}% match):";
        $lines[] = '';

        // Extract key informative lines from top chunks
        foreach (array_slice($chunks, 0, 3) as $chunk) {
            $noteRef = "[[{$chunk['path']}]]";
            $cleanContent = trim(preg_replace('/\s+/', ' ', $chunk['content']));
            $sentences = preg_split('/(?<=[.!?])\s+/', $cleanContent);

            $bestSentence = $sentences[0] ?? $cleanContent;
            if (strlen($bestSentence) > 220) {
                $bestSentence = mb_substr($bestSentence, 0, 217).'...';
            }

            $headingLabel = $chunk['heading'] ? " (*{$chunk['heading']}*)" : '';
            $lines[] = "- **{$noteRef}**{$headingLabel}: {$bestSentence}";
        }

        // Backlink connections
        if (! empty($graphNodes)) {
            $lines[] = '';
            $lines[] = '**Graph-Connected Context:**';
            foreach (array_slice($graphNodes, 0, 2) as $node) {
                $lines[] = "- Note **[[{$node['path']}]]** connects back via *{$node['via']}* with relevant context.";
            }
        }

        return implode("\n", $lines);
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
