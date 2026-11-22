<?php

namespace App\Services\Ai;

use App\Models\Vault;
use App\Models\VaultFile;
use App\Models\VaultFileEmbedding;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VaultIndexService
{
    public function __construct(
        protected EmbeddingService $embeddingService,
        protected AiQuotaService $quotaService,
        protected AiGateway $aiGateway,
        protected VectorCodec $vectorCodec,
    ) {}

    /**
     * Determine whether incremental sync indexing should dispatch automatically for a vault note.
     */
    public function shouldAutoIndex(Vault $vault, string $path): bool
    {
        if ($vault->is_e2ee || ! str_ends_with(strtolower($path), '.md')) {
            return false;
        }

        $team = $vault->team;
        $aiConfig = $team->aiConfig;
        if (! $aiConfig || ! $aiConfig->is_enabled || $aiConfig->provider_mode === 'off') {
            return false;
        }

        $aiSetting = $vault->aiSetting;
        if (! $aiSetting || $aiSetting->ai_mode !== 'server') {
            return false;
        }

        return ! $aiSetting->isPathExcluded($path);
    }

    /**
     * Index or refresh all chunks and FTS5 entries for a single markdown file.
     *
     * @return array{chunks_count: int, embedded: bool}
     */
    public function indexFile(Vault $vault, VaultFile $file, bool $force = false): array
    {
        $isExcluded = $vault->aiSetting?->isPathExcluded($file->path) ?? false;

        if ($vault->is_e2ee || $file->is_deleted || $file->is_encrypted || $isExcluded || ! str_ends_with(strtolower($file->path), '.md')) {
            $this->removeFile($vault, $file->id);

            return ['chunks_count' => 0, 'embedded' => false];
        }

        $content = $file->getContents();
        if ($content === null || trim($content) === '') {
            $this->removeFile($vault, $file->id);

            return ['chunks_count' => 0, 'embedded' => false];
        }

        $chunks = $this->embeddingService->chunkMarkdown($content);
        if (empty($chunks)) {
            $this->removeFile($vault, $file->id);

            return ['chunks_count' => 0, 'embedded' => false];
        }

        $route = $this->resolveActiveEmbeddingRoute($vault);
        $targetModelKey = $this->modelKeyForRoute($route);

        $existingByIndex = VaultFileEmbedding::query()
            ->where('vault_file_id', $file->id)
            ->get()
            ->keyBy('chunk_index');

        $existingFtsChunkIds = $this->existingFtsChunkIds($existingByIndex->pluck('id')->all());

        /** @var array<int, array<string, mixed>> $chunksNeedingEmbed */
        $chunksNeedingEmbed = [];
        $validChunkIndexes = [];

        foreach ($chunks as $chunk) {
            $chunkIndex = $chunk['chunk_index'];
            $validChunkIndexes[] = $chunkIndex;

            /** @var VaultFileEmbedding|null $existing */
            $existing = $existingByIndex->get($chunkIndex);

            $needsEmbed = $force
                || $existing === null
                || $existing->content_hash !== $chunk['content_hash']
                || empty($existing->embedding_blob)
                || $this->modelChanged($existing->embedding_model, $targetModelKey);

            if ($needsEmbed) {
                $chunksNeedingEmbed[$chunkIndex] = $chunk;
            } elseif (! in_array($existing->id, $existingFtsChunkIds, true)) {
                $this->syncFtsRow($vault->id, $existing->id, $file->path, $existing->heading, $existing->content);
            }
        }

        $embeddedAny = false;
        if (! empty($chunksNeedingEmbed)) {
            $vectorsByChunkIndex = $this->generateVectorsForChunks($vault, $chunksNeedingEmbed, $route, $targetModelKey);
            $embeddedAny = true;

            foreach ($chunksNeedingEmbed as $chunkIndex => $chunk) {
                $vectorData = $vectorsByChunkIndex[$chunkIndex];
                $vector = $vectorData['vector'];
                $modelKey = $vectorData['model_key'];
                $blob = $this->vectorCodec->pack($vector);

                $record = VaultFileEmbedding::query()->updateOrCreate(
                    [
                        'vault_file_id' => $file->id,
                        'chunk_index' => $chunkIndex,
                    ],
                    [
                        'vault_id' => $vault->id,
                        'heading' => $chunk['heading'],
                        'start_line' => $chunk['start_line'],
                        'content' => $chunk['content'],
                        'token_count' => $chunk['token_count'],
                        'embedding' => $modelKey === 'deterministic' ? $vector : [],
                        'embedding_blob' => $blob,
                        'embedding_model' => $modelKey,
                        'embedding_dimensions' => count($vector),
                        'content_hash' => $chunk['content_hash'],
                        'wikilinks' => $chunk['wikilinks'],
                    ]
                );

                $this->syncFtsRow($vault->id, $record->id, $file->path, $record->heading, $record->content);
            }
        }

        $staleRecords = VaultFileEmbedding::query()
            ->where('vault_file_id', $file->id)
            ->whereNotIn('chunk_index', $validChunkIndexes)
            ->get(['id']);

        if ($staleRecords->isNotEmpty()) {
            $staleIds = $staleRecords->pluck('id')->all();
            $this->deleteFtsRows($staleIds);
            VaultFileEmbedding::query()->whereIn('id', $staleIds)->delete();
        }

        return [
            'chunks_count' => count($validChunkIndexes),
            'embedded' => $embeddedAny,
        ];
    }

    /**
     * Remove all embeddings and FTS5 entries belonging to a vault file.
     */
    public function removeFile(Vault $vault, int $vaultFileId): void
    {
        $chunkIds = VaultFileEmbedding::query()
            ->where('vault_id', $vault->id)
            ->where('vault_file_id', $vaultFileId)
            ->pluck('id')
            ->all();

        if (! empty($chunkIds)) {
            $this->deleteFtsRows($chunkIds);
            VaultFileEmbedding::query()->whereIn('id', $chunkIds)->delete();
        }
    }

    /**
     * Incrementally index all markdown notes in a vault and maintain progress telemetry.
     *
     * @return array{
     *     status: string,
     *     files_indexed: int,
     *     chunks_count: int,
     *     duration_ms: int,
     *     message?: string
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

        $markdownFiles = VaultFile::query()
            ->where('vault_id', $vault->id)
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

        $activeFileIds = $markdownFiles->pluck('id')->all();
        $orphanIdsQuery = VaultFileEmbedding::query()->where('vault_id', $vault->id);
        if (! empty($activeFileIds)) {
            $orphanIdsQuery->whereNotIn('vault_file_id', $activeFileIds);
        }
        $orphanIds = $orphanIdsQuery->pluck('id')->all();
        if (! empty($orphanIds)) {
            $this->deleteFtsRows($orphanIds);
            VaultFileEmbedding::query()->whereIn('id', $orphanIds)->delete();
        }

        $totalChunksCount = 0;
        $filesIndexed = 0;

        foreach ($markdownFiles as $file) {
            if ($file->is_encrypted) {
                continue;
            }

            $fileResult = $this->indexFile($vault, $file, $force);
            $totalChunksCount += $fileResult['chunks_count'];
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

    protected function resolveActiveEmbeddingRoute(Vault $vault): ?ResolvedAiRoute
    {
        $team = $vault->team;
        $aiSetting = $vault->aiSetting;

        if ($aiSetting && $aiSetting->ai_mode !== 'server') {
            return null;
        }

        $aiConfig = $team->aiConfig;
        if (! $aiConfig || ! $aiConfig->is_enabled) {
            return null;
        }

        return $this->quotaService->resolveEmbeddingRoute($team, throwOnQuotaExceeded: false);
    }

    protected function modelKeyForRoute(?ResolvedAiRoute $route): string
    {
        if ($route !== null) {
            return "{$route->provider}:{$route->model}";
        }

        if (config('synkk.rag.provider', 'deterministic') === 'ollama') {
            return 'ollama:'.(string) config('synkk.rag.embedding_model', 'nomic-embed-text');
        }

        return 'deterministic';
    }

    protected function modelChanged(?string $existingModel, string $targetModelKey): bool
    {
        if ($existingModel === null) {
            return true;
        }

        if ($existingModel === $targetModelKey) {
            return false;
        }

        return $targetModelKey !== 'deterministic';
    }

    /**
     * @param  array<int, array<string, mixed>>  $chunksNeedingEmbed
     * @return array<int, array{vector: array<int, float>, model_key: string}>
     */
    protected function generateVectorsForChunks(
        Vault $vault,
        array $chunksNeedingEmbed,
        ?ResolvedAiRoute $route,
        string $targetModelKey,
    ): array {
        $results = [];

        if ($route !== null) {
            try {
                $chunkIndexes = array_keys($chunksNeedingEmbed);
                $batches = array_chunk($chunkIndexes, 32);

                foreach ($batches as $batchIndexes) {
                    $texts = [];
                    foreach ($batchIndexes as $idx) {
                        $texts[] = (string) $chunksNeedingEmbed[$idx]['content'];
                    }

                    $embedResponse = $this->aiGateway->embed($vault->team, $texts, $vault);

                    if (count($embedResponse->vectors) === count($batchIndexes)) {
                        foreach ($batchIndexes as $offset => $idx) {
                            $results[$idx] = [
                                'vector' => $embedResponse->vectors[$offset],
                                'model_key' => $targetModelKey,
                            ];
                        }
                    }
                }

                if (count($results) === count($chunksNeedingEmbed)) {
                    return $results;
                }
            } catch (\Throwable $e) {
                Log::warning("AI gateway embedding fallback for Vault [{$vault->id}]: {$e->getMessage()}");
                $results = [];
            }
        }

        foreach ($chunksNeedingEmbed as $chunkIndex => $chunk) {
            $vector = $this->embeddingService->generate((string) $chunk['content']);
            $results[$chunkIndex] = [
                'vector' => $vector,
                'model_key' => 'deterministic',
            ];
        }

        return $results;
    }

    /**
     * @param  array<int, int>  $chunkIds
     * @return array<int, int>
     */
    protected function existingFtsChunkIds(array $chunkIds): array
    {
        if (empty($chunkIds) || DB::getDriverName() !== 'sqlite') {
            return $chunkIds;
        }

        return array_map(
            fn (mixed $id): int => (int) $id,
            DB::table('vault_chunks_fts')->whereIn('chunk_id', $chunkIds)->pluck('chunk_id')->all(),
        );
    }

    protected function syncFtsRow(int $vaultId, int $chunkId, string $path, ?string $heading, string $content): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::table('vault_chunks_fts')->where('chunk_id', $chunkId)->delete();
        DB::table('vault_chunks_fts')->insert([
            'chunk_id' => $chunkId,
            'vault_id' => $vaultId,
            'path' => $path,
            'heading' => $heading ?? '',
            'content' => $content,
        ]);
    }

    /**
     * @param  array<int, int>  $chunkIds
     */
    protected function deleteFtsRows(array $chunkIds): void
    {
        if (empty($chunkIds) || DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::table('vault_chunks_fts')->whereIn('chunk_id', $chunkIds)->delete();
    }
}
