<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Models\Vault;
use App\Models\VaultFileEmbedding;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HybridVaultSearchService
{
    /** @var array<int, string> */
    protected const STOPWORDS = [
        'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and', 'any', 'are', 'as', 'at',
        'be', 'because', 'been', 'before', 'being', 'below', 'between', 'both', 'but', 'by',
        'can', 'cannot', 'could', 'did', 'do', 'does', 'doing', 'down', 'during', 'each', 'few', 'for', 'from', 'further',
        'had', 'has', 'have', 'having', 'he', 'her', 'here', 'hers', 'herself', 'him', 'himself', 'his', 'how',
        'i', 'if', 'in', 'into', 'is', 'it', 'its', 'itself', 'me', 'more', 'most', 'my', 'myself',
        'no', 'nor', 'not', 'of', 'off', 'on', 'once', 'only', 'or', 'other', 'our', 'ours', 'ourselves', 'out', 'over', 'own',
        'same', 'she', 'should', 'so', 'some', 'such', 'than', 'that', 'the', 'their', 'theirs', 'them', 'themselves',
        'then', 'there', 'these', 'they', 'this', 'those', 'through', 'to', 'too', 'under', 'until', 'up', 'very',
        'was', 'we', 'were', 'what', 'when', 'where', 'which', 'while', 'who', 'whom', 'why', 'with', 'would',
        'you', 'your', 'yours', 'yourself', 'yourselves',
        'tell', 'give', 'show', 'explain', 'find', 'summarize', 'notes', 'note', 'vault',
    ];

    public function __construct(
        protected EmbeddingService $embeddingService,
        protected AiQuotaService $quotaService,
        protected AiGateway $aiGateway,
        protected VectorCodec $vectorCodec,
    ) {}

    /**
     * Perform ACL-enforced hybrid search (SQLite FTS5 BM25 + float32 vector cosine + Reciprocal Rank Fusion).
     *
     * @param  array{folder?: string|null, tag?: string|null}  $filters
     * @return array<int, array{
     *     file_id: int,
     *     path: string,
     *     heading: string|null,
     *     start_line: int,
     *     similarity: float,
     *     score: float,
     *     score_pct: int,
     *     content: string,
     *     wikilinks: array<int, string>
     * }>
     */
    public function search(Vault $vault, string $query, int $limit = 5, ?User $user = null, array $filters = []): array
    {
        if ($vault->is_e2ee) {
            return [];
        }

        $cleanQuery = trim($query);
        if ($cleanQuery === '') {
            return [];
        }

        $aiSetting = $vault->aiSetting;
        $folderFilter = isset($filters['folder']) ? trim(str_replace('\\', '/', (string) $filters['folder']), '/') : '';
        $tagFilter = isset($filters['tag']) ? ltrim(strtolower(trim((string) $filters['tag'])), '#') : '';

        // 1. Lightweight column projection (omits full chunk content during scoring)
        $rows = DB::table('vault_file_embeddings as vfe')
            ->join('vault_files as vf', 'vf.id', '=', 'vfe.vault_file_id')
            ->where('vfe.vault_id', $vault->id)
            ->where('vf.is_deleted', false)
            ->select([
                'vfe.id',
                'vfe.vault_file_id',
                'vfe.heading',
                'vfe.embedding_blob',
                'vfe.embedding',
                'vfe.embedding_model',
                'vf.path as file_path',
            ])
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $allowedRows = [];
        $usedModels = [];

        foreach ($rows as $row) {
            $path = (string) $row->file_path;

            if ($user && $vault->permissionForPath($user, $path) === 'hidden') {
                continue;
            }

            if ($aiSetting && $aiSetting->isPathExcluded($path)) {
                continue;
            }

            if ($folderFilter !== '' && stripos($path, $folderFilter.'/') !== 0 && strcasecmp($path, $folderFilter) !== 0) {
                continue;
            }

            $allowedRows[(int) $row->id] = $row;
            if (is_string($row->embedding_model) && $row->embedding_model !== '') {
                $usedModels[$row->embedding_model] = true;
            }
        }

        if (empty($allowedRows)) {
            return [];
        }

        // 2. Compute query vector(s)
        $deterministicQueryVector = $this->embeddingService->generateDeterministic($cleanQuery);
        $remoteQueryVector = null;
        $remoteModelKey = null;

        if ($aiSetting === null || $aiSetting->ai_mode === 'server') {
            $route = $this->quotaService->resolveEmbeddingRoute($vault->team, throwOnQuotaExceeded: false);
            if ($route !== null) {
                $candidateModelKey = "{$route->provider}:{$route->model}";
                if (isset($usedModels[$candidateModelKey])) {
                    try {
                        $embedRes = $this->aiGateway->embed($vault->team, [$cleanQuery], $vault, $user?->id);
                        if (! empty($embedRes->vectors[0])) {
                            $remoteQueryVector = $embedRes->vectors[0];
                            $remoteModelKey = $candidateModelKey;
                        }
                    } catch (\Throwable $e) {
                        Log::debug("Hybrid search remote query embedding fallback for Vault [{$vault->id}]: {$e->getMessage()}");
                    }
                }
            }
        }

        // 3. Lexical FTS5 BM25 ranking
        $lexicalRanks = $this->fetchLexicalRanks($vault->id, $cleanQuery, $allowedRows);

        // 4. Vector cosine similarity & ranking
        $vectorSimilarities = [];
        foreach ($allowedRows as $chunkId => $row) {
            $vector = $this->vectorCodec->unpack(is_string($row->embedding_blob) ? $row->embedding_blob : null);
            if (empty($vector) && is_string($row->embedding) && $row->embedding !== '') {
                $decoded = json_decode($row->embedding, true);
                if (is_array($decoded)) {
                    $vector = array_map(fn (mixed $v): float => (float) $v, $decoded);
                }
            }

            if ($remoteQueryVector !== null
                && $row->embedding_model === $remoteModelKey
                && count($vector) === count($remoteQueryVector)) {
                $sim = $this->vectorCodec->cosineSimilarity($remoteQueryVector, $vector);
            } elseif (count($vector) === count($deterministicQueryVector)) {
                $sim = $this->vectorCodec->cosineSimilarity($deterministicQueryVector, $vector);
            } else {
                $sim = 0.0;
            }

            $vectorSimilarities[$chunkId] = $sim;
        }

        $vectorOrder = $vectorSimilarities;
        arsort($vectorOrder);
        $vectorRanks = [];
        $rank = 1;
        foreach (array_keys($vectorOrder) as $chunkId) {
            $vectorRanks[$chunkId] = $rank++;
        }

        // 5. Reciprocal Rank Fusion (RRF) + blended score
        $maxTheoreticalRrf = 2.0 / 61.0;
        $scored = [];

        foreach (array_keys($allowedRows) as $chunkId) {
            $lexRank = $lexicalRanks[$chunkId] ?? null;
            $vecRank = $vectorRanks[$chunkId] ?? null;
            $vecSim = max(0.0, $vectorSimilarities[$chunkId] ?? 0.0);

            $rrf = ($lexRank !== null ? (1.0 / (60 + $lexRank)) : 0.0)
                + ($vecRank !== null ? (1.0 / (60 + $vecRank)) : 0.0);
            $rrfNorm = min(1.0, $rrf / $maxTheoreticalRrf);

            if ($lexRank !== null) {
                $composite = min(1.0, ($vecSim * 0.45) + ($rrfNorm * 0.45) + 0.15);
            } else {
                $composite = min(1.0, ($vecSim * 0.65) + ($rrfNorm * 0.20));
            }

            $scored[$chunkId] = [
                'chunk_id' => $chunkId,
                'similarity' => round($vectorSimilarities[$chunkId] ?? 0.0, 4),
                'score' => round($composite, 4),
                'score_pct' => (int) round($composite * 100),
            ];
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        // Hydrate a small candidate pool to apply optional tag filtering without scanning the whole table
        $candidateLimit = $tagFilter !== '' ? max($limit * 4, 25) : $limit;
        $topCandidates = array_slice($scored, 0, $candidateLimit);
        $candidateIds = array_column($topCandidates, 'chunk_id');

        if (empty($candidateIds)) {
            return [];
        }

        $records = VaultFileEmbedding::query()
            ->with('file')
            ->whereIn('id', $candidateIds)
            ->get()
            ->keyBy('id');

        $results = [];
        foreach ($topCandidates as $candidate) {
            /** @var VaultFileEmbedding|null $record */
            $record = $records->get($candidate['chunk_id']);
            if (! $record || $record->file->is_deleted) {
                continue;
            }

            if ($tagFilter !== '' && ! $this->contentMatchesTag($record->content, $tagFilter)) {
                continue;
            }

            $results[] = [
                'file_id' => $record->vault_file_id,
                'path' => $record->file->path,
                'heading' => $record->heading,
                'start_line' => $record->start_line,
                'similarity' => $candidate['similarity'],
                'score' => $candidate['score'],
                'score_pct' => $candidate['score_pct'],
                'content' => $record->content,
                'wikilinks' => $record->wikilinks ?? [],
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Build a safe FTS5 query and return chunk_id => 1-based rank among allowed chunks.
     *
     * @param  array<int, object>  $allowedRows
     * @return array<int, int>
     */
    protected function fetchLexicalRanks(int $vaultId, string $query, array $allowedRows): array
    {
        $terms = $this->extractSafeFtsTokens($query);
        if (empty($terms)) {
            return [];
        }

        if (DB::getDriverName() === 'sqlite') {
            $quotedTerms = array_map(fn (string $term): string => '"'.str_replace('"', '', $term).'"', $terms);
            $ftsMatch = implode(' OR ', $quotedTerms);

            try {
                $ftsRows = DB::select(
                    'SELECT chunk_id, bm25(vault_chunks_fts, 0.0, 0.0, 3.0, 2.5, 1.0) AS bm25_score
                     FROM vault_chunks_fts
                     WHERE vault_id = ? AND vault_chunks_fts MATCH ?
                     ORDER BY bm25_score ASC
                     LIMIT 100',
                    [$vaultId, $ftsMatch]
                );

                $ranks = [];
                $rank = 1;
                foreach ($ftsRows as $row) {
                    $chunkId = (int) $row->chunk_id;
                    if (! isset($allowedRows[$chunkId])) {
                        continue;
                    }
                    $ranks[$chunkId] = $rank++;
                }

                if (! empty($ranks)) {
                    return $ranks;
                }
            } catch (\Throwable $e) {
                Log::debug("SQLite FTS5 query fallback triggered: {$e->getMessage()}");
            }
        }

        // Portable fallback for non-SQLite drivers or rows not yet in FTS
        $chunkIds = array_keys($allowedRows);
        $records = VaultFileEmbedding::query()
            ->whereIn('id', $chunkIds)
            ->get(['id', 'vault_file_id', 'heading', 'content']);

        $scores = [];
        foreach ($records as $record) {
            $pathLower = strtolower((string) ($allowedRows[$record->id]->file_path ?? ''));
            $headingLower = strtolower((string) ($record->heading ?? ''));
            $contentLower = strtolower($record->content);

            $hits = 0.0;
            foreach ($terms as $term) {
                if (str_contains($headingLower, $term)) {
                    $hits += 2.5;
                }
                if (str_contains($pathLower, $term)) {
                    $hits += 2.0;
                }
                if (str_contains($contentLower, $term)) {
                    $hits += 1.2;
                }
            }

            if ($hits > 0.0) {
                $scores[$record->id] = $hits;
            }
        }

        arsort($scores);
        $ranks = [];
        $rank = 1;
        foreach (array_keys($scores) as $chunkId) {
            $ranks[$chunkId] = $rank++;
        }

        return $ranks;
    }

    /**
     * Extract safe, punctuation-stripped FTS5 search tokens.
     *
     * @return array<int, string>
     */
    protected function extractSafeFtsTokens(string $query): array
    {
        if (preg_match_all('/[\p{L}\p{N}_]+/u', strtolower($query), $matches) === false) {
            return [];
        }

        $allTokens = $matches[0];

        $substantive = array_values(array_unique(array_filter(
            $allTokens,
            fn (string $token): bool => mb_strlen($token) >= 2 && ! in_array($token, self::STOPWORDS, true),
        )));

        if (! empty($substantive)) {
            return array_slice($substantive, 0, 12);
        }

        return array_slice(
            array_values(array_unique(array_filter($allTokens, fn (string $t): bool => mb_strlen($t) >= 2))),
            0,
            12
        );
    }

    protected function contentMatchesTag(string $content, string $tag): bool
    {
        if (preg_match('/(?:^|\s)#'.preg_quote($tag, '/').'\b/i', $content) === 1) {
            return true;
        }

        return preg_match('/^tags\s*:\s*.*\b'.preg_quote($tag, '/').'\b/im', $content) === 1;
    }
}
