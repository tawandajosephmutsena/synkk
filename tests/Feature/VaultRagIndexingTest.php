<?php

use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultFileEmbedding;
use App\Services\EmbeddingService;
use App\Services\VaultRagService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('EmbeddingService chunks markdown accurately by headings and extracts wikilinks', function () {
    $service = app(EmbeddingService::class);

    $markdown = <<<'MD'
# Architecture Overview
This is the root architecture document connecting to [[Security/Path-ACLs]] and [[Transport/E2EE]].

## Secret Scanning
Synkk scans every commit for high-entropy tokens and AWS credentials.
Reference note [[DLP-Shield]].

## Vector Embeddings
Local RAG operates on 128-dimensional hyperspheres with zero cloud leakage.
MD;

    $chunks = $service->chunkMarkdown($markdown);

    expect($chunks)->toHaveCount(3)
        ->and($chunks[0]['heading'])->toBe('Architecture Overview')
        ->and($chunks[0]['wikilinks'])->toContain('Security/Path-ACLs')
        ->and($chunks[0]['wikilinks'])->toContain('Transport/E2EE')
        ->and($chunks[1]['heading'])->toBe('Secret Scanning')
        ->and($chunks[1]['wikilinks'])->toContain('DLP-Shield')
        ->and($chunks[2]['heading'])->toBe('Vector Embeddings')
        ->and($chunks[2]['token_count'])->toBeGreaterThan(5);
});

test('EmbeddingService generates deterministic normalized unit vectors with high semantic similarity for related texts', function () {
    $service = app(EmbeddingService::class);

    $vec1 = $service->generateDeterministic('Security protocol and token authentication for mobile clients');
    $vec2 = $service->generateDeterministic('Security protocols, authentication tokens, and mobile sync clients');
    $vec3 = $service->generateDeterministic('Baking sourdough bread with wild yeast starter in a cast iron pot');

    expect(count($vec1))->toBe(128);

    // Verify unit length
    $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $vec1)));
    expect(abs($norm - 1.0))->toBeLessThan(0.01);

    // Semantically close texts should have high cosine similarity
    $simClose = $service->cosineSimilarity($vec1, $vec2);
    $simDistant = $service->cosineSimilarity($vec1, $vec3);

    expect($simClose)->toBeGreaterThan(0.70)
        ->and($simDistant)->toBeLessThan($simClose);
});

test('VaultRagService incrementally indexes notes and cleans up stale embeddings', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'cloud']);
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Engineering Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    // Create a note file on disk
    $content1 = "# Storage Engine\nFast local storage with SQLite and WAL mode.\n\n## Replication\nSyncing to [[Backup]] daily.";
    Storage::disk('local')->put("vaults/{$vault->id}/storage.md", $content1);

    $file = $vault->files()->create([
        'path' => 'Storage.md',
        'storage_path' => "vaults/{$vault->id}/storage.md",
        'sha256' => hash('sha256', $content1),
        'size' => strlen($content1),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $ragService = app(VaultRagService::class);

    // 1. Initial indexing
    $res = $ragService->indexVault($vault);
    expect($res['status'])->toBe('indexed')
        ->and($res['files_indexed'])->toBe(1)
        ->and($res['chunks_count'])->toBe(2);

    expect(VaultFileEmbedding::where('vault_id', $vault->id)->count())->toBe(2);

    // 2. Incremental re-index without force skips embedding generation
    $res2 = $ragService->indexVault($vault, force: false);
    expect($res2['files_indexed'])->toBe(1);

    // 3. Mark file deleted -> index cleans it up
    $file->update(['is_deleted' => true]);
    $res3 = $ragService->indexVault($vault);
    expect(VaultFileEmbedding::where('vault_id', $vault->id)->count())->toBe(0);
});
