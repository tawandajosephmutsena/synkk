<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    public const DEFAULT_DIMENSIONS = 128;

    public function __construct(
        protected string $defaultProvider = 'deterministic',
        protected string $ollamaUrl = 'http://localhost:11434',
        protected string $embeddingModel = 'nomic-embed-text'
    ) {
        $this->defaultProvider = config('synkk.rag.provider', 'deterministic');
        $this->ollamaUrl = rtrim((string) config('synkk.rag.ollama_url', 'http://localhost:11434'), '/');
        $this->embeddingModel = (string) config('synkk.rag.embedding_model', 'nomic-embed-text');
    }

    /**
     * Chunk markdown text into structured semantic blocks.
     *
     * @return array<int, array{
     *     chunk_index: int,
     *     heading: string|null,
     *     start_line: int,
     *     content: string,
     *     token_count: int,
     *     content_hash: string,
     *     wikilinks: array<int, string>
     * }>
     */
    public function chunkMarkdown(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content);
        if ($lines === false || empty($lines)) {
            return [];
        }

        $chunks = [];
        $currentHeading = null;
        $currentLines = [];
        $currentStartLine = 1;
        $chunkIndex = 0;

        foreach ($lines as $idx => $line) {
            $lineNumber = $idx + 1;

            if (preg_match('/^(#{1,6})\s+(.+)$/', trim($line), $matches)) {
                // If we already have accumulated content, flush existing chunk
                if (! empty($currentLines)) {
                    $chunkText = trim(implode("\n", $currentLines));
                    if (! empty($chunkText)) {
                        $chunks[] = $this->buildChunkDescriptor($chunkIndex++, $currentHeading, $currentStartLine, $chunkText);
                    }
                    $currentLines = [];
                }

                $currentHeading = trim($matches[2]);
                $currentStartLine = $lineNumber;
                $currentLines[] = $line;
            } else {
                $currentLines[] = $line;

                // Split very large sections (> 350 words) at empty lines for granular retrieval
                $currentWordCount = str_word_count(implode(' ', $currentLines));
                if ($currentWordCount >= 350 && trim($line) === '') {
                    $chunkText = trim(implode("\n", $currentLines));
                    if (! empty($chunkText)) {
                        $chunks[] = $this->buildChunkDescriptor($chunkIndex++, $currentHeading, $currentStartLine, $chunkText);
                    }
                    $currentLines = [];
                    $currentStartLine = $lineNumber + 1;
                }
            }
        }

        if (! empty($currentLines)) {
            $chunkText = trim(implode("\n", $currentLines));
            if (! empty($chunkText)) {
                $chunks[] = $this->buildChunkDescriptor($chunkIndex++, $currentHeading, $currentStartLine, $chunkText);
            }
        }

        return $chunks;
    }

    /**
     * Build a structured descriptor for an extracted chunk.
     *
     * @return array{
     *     chunk_index: int,
     *     heading: string|null,
     *     start_line: int,
     *     content: string,
     *     token_count: int,
     *     content_hash: string,
     *     wikilinks: array<int, string>
     * }
     */
    protected function buildChunkDescriptor(int $index, ?string $heading, int $startLine, string $text): array
    {
        preg_match_all('/\[\[([^\]\|#]+)(?:#[^\]\|]+)?(?:\|[^\]]+)?\]\]/', $text, $matches);
        $wikilinks = ! empty($matches[1]) ? array_values(array_unique(array_map('trim', $matches[1]))) : [];

        $words = preg_split('/\s+/', trim($text));
        $tokenCount = $words !== false ? count($words) : 0;

        return [
            'chunk_index' => $index,
            'heading' => $heading,
            'start_line' => $startLine,
            'content' => $text,
            'token_count' => $tokenCount,
            'content_hash' => hash('sha256', $text),
            'wikilinks' => $wikilinks,
        ];
    }

    /**
     * Generate an embedding vector for given text.
     *
     * @return array<int, float>
     */
    public function generate(string $text, ?string $provider = null): array
    {
        $provider = $provider ?? $this->defaultProvider;

        if ($provider === 'ollama') {
            $vector = $this->generateOllama($text);
            if (! empty($vector)) {
                return $vector;
            }
        }

        return $this->generateDeterministic($text);
    }

    /**
     * Deterministic, zero-dependency, unit-normalized semantic vector generation.
     * Uses sublinear TF-IDF character/word n-gram hashing into a 128-dimensional hypersphere.
     *
     * @return array<int, float>
     */
    public function generateDeterministic(string $text, int $dimensions = self::DEFAULT_DIMENSIONS): array
    {
        $vector = array_fill(0, $dimensions, 0.0);
        $normalizedText = strtolower(trim($text));

        if ($normalizedText === '') {
            return $vector;
        }

        // 1. Extract words
        $words = preg_split('/[^a-z0-9_\-\']/i', $normalizedText);
        $terms = [];

        if ($words !== false) {
            foreach ($words as $word) {
                $w = trim($word);
                if (strlen($w) >= 2) {
                    $terms[] = $w;
                    // Character trigrams for morphological similarity
                    $len = strlen($w);
                    if ($len >= 4) {
                        for ($i = 0; $i <= $len - 3; $i++) {
                            $terms[] = substr($w, $i, 3);
                        }
                    }
                }
            }
        }

        // 2. Count term frequencies
        $tf = [];
        foreach ($terms as $term) {
            $tf[$term] = ($tf[$term] ?? 0) + 1;
        }

        // 3. Hash into dimensions with sublinear scaling (1 + log(tf))
        foreach ($tf as $term => $count) {
            $weight = 1.0 + log((float) $count);
            $hash = hexdec(substr(hash('fnv1a32', $term), 0, 8));
            $bucket = $hash % $dimensions;
            $sign = (($hash >> 16) & 1) === 1 ? 1.0 : -1.0;

            $vector[$bucket] += $sign * $weight;
        }

        // 4. L2 Normalization to unit length
        $sumSq = 0.0;
        foreach ($vector as $val) {
            $sumSq += $val * $val;
        }

        $norm = sqrt($sumSq);
        if ($norm > 0.000001) {
            foreach ($vector as $idx => $val) {
                $vector[$idx] = round($val / $norm, 6);
            }
        }

        return $vector;
    }

    /**
     * Generate embedding using local Ollama instance.
     *
     * @return array<int, float>|null
     */
    protected function generateOllama(string $text): ?array
    {
        try {
            $response = Http::timeout(2.0)->post("{$this->ollamaUrl}/api/embeddings", [
                'model' => $this->embeddingModel,
                'prompt' => $text,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['embedding']) && is_array($data['embedding'])) {
                    return array_map(fn ($val) => (float) $val, $data['embedding']);
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Ollama embedding fallback triggered: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Compute cosine similarity between two float vectors.
     *
     * @param  array<int, float>  $vecA
     * @param  array<int, float>  $vecB
     */
    public function cosineSimilarity(array $vecA, array $vecB): float
    {
        $count = count($vecA);
        if ($count === 0 || $count !== count($vecB)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $a = (float) $vecA[$i];
            $b = (float) $vecB[$i];

            $dotProduct += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA <= 0.000001 || $normB <= 0.000001) {
            return 0.0;
        }

        $similarity = $dotProduct / (sqrt($normA) * sqrt($normB));

        return max(-1.0, min(1.0, round($similarity, 4)));
    }
}
