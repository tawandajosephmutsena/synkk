<?php

use App\Console\Commands\PackageCommunityEdition;
use App\Jobs\IndexVaultRagJob;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultFile;
use App\Models\VaultFileEmbedding;
use App\Services\EmbeddingService;
use App\Services\VaultRagService;
use Illuminate\Contracts\Queue\ShouldBeUnique;

test('IndexVaultRagJob enforces unique execution per vault with aligned retry timing', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->switchTeam($team);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'AI Knowledge Base',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $job = new IndexVaultRagJob($vault);

    // Job implements ShouldBeUnique
    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe((string) $vault->id)
        ->and($job->timeout)->toBe(600)
        ->and($job->uniqueFor)->toBeGreaterThanOrEqual(600);

    // Queue retry_after exceeds job timeout
    $retryAfter = config('queue.connections.database.retry_after');
    expect($retryAfter)->toBeGreaterThan($job->timeout);
});

test('VaultRagService search does not perform synchronous full vault indexing when embeddings are empty', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->switchTeam($team);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Unindexed Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    // Create 5 markdown files without embeddings
    for ($i = 1; $i <= 5; $i++) {
        VaultFile::create([
            'vault_id' => $vault->id,
            'path' => "Note-{$i}.md",
            'storage_path' => "vaults/{$vault->id}/Note-{$i}.md",
            'file_size' => 100,
            'sha256' => hash('sha256', "Note {$i} content"),
            'version' => 1,
            'is_deleted' => false,
        ]);
    }

    $mockEmbedding = mock(EmbeddingService::class);
    $mockEmbedding->shouldReceive('generate')->andReturn(array_fill(0, 384, 0.1));

    $ragService = app(VaultRagService::class);

    // Search query should NOT synchronously index all 5 files into the database
    $results = $ragService->search($vault, 'search query');

    expect($results)->toBeArray()
        ->and(VaultFileEmbedding::where('vault_id', $vault->id)->count())->toBe(0);
});

test('VaultRagService explicitly disables server-side RAG indexing and search for E2EE vaults', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->switchTeam($team);

    $e2eeVault = Vault::create([
        'team_id' => $team->id,
        'name' => 'E2EE Private Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
        'is_e2ee' => true,
        'e2ee_salt' => bin2hex(random_bytes(16)),
        'e2ee_test_cipher' => 'test-cipher',
    ]);

    $ragService = app(VaultRagService::class);

    // 1. Indexing skips E2EE vault
    $indexRes = $ragService->indexVault($e2eeVault);
    expect($indexRes['status'])->toBe('skipped')
        ->and($indexRes['message'])->toContain('Zero-Knowledge E2EE');

    // 2. Search returns empty array
    $searchRes = $ragService->search($e2eeVault, 'any query');
    expect($searchRes)->toBeEmpty();

    // 3. Query returns guarded message
    $queryRes = $ragService->query($e2eeVault, 'what is in this vault?');
    expect($queryRes['model'])->toBe('synkk/e2ee-guarded')
        ->and($queryRes['answer'])->toContain('disabled on Zero-Knowledge E2EE vaults');
});

test('community edition packaging command includes composer.lock and package-lock.json', function () {
    $reflection = new ReflectionClass(PackageCommunityEdition::class);
    $method = $reflection->getMethod('handle');

    // Read command source to verify critical lockfile packaging
    $source = file_get_contents($reflection->getFileName() ?: '');

    expect($source)->toContain("'composer.lock'")
        ->and($source)->toContain("'package-lock.json'")
        ->and($source)->toContain("'.dockerignore'");
});
