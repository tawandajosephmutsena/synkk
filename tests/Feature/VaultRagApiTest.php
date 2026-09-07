<?php

use App\Jobs\IndexVaultRagJob;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('RAG API endpoints respond successfully to authorized device tokens', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'cloud']);
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'API RAG Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $content = "# Core Specs\nSynkk provides 2-second QR pairing and AES-256-GCM zero-knowledge encryption.";
    Storage::disk('local')->put("vaults/{$vault->id}/core.md", $content);
    $vault->files()->create([
        'path' => 'Core.md',
        'storage_path' => "vaults/{$vault->id}/core.md",
        'sha256' => hash('sha256', $content),
        'size' => strlen($content),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'MacBook Pro', 'desktop');
    $plainToken = $tokenResult['plain_token'];

    // 1. Index Endpoint
    $indexRes = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/rag/index", [
            'force' => true,
        ]);

    $indexRes->assertOk()
        ->assertJson([
            'status' => 'indexed',
            'files_indexed' => 1,
            'chunks_count' => 1,
        ]);

    // 2. Status Endpoint
    $statusRes = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->getJson("/api/v1/vaults/{$vault->slug}/rag/status");

    $statusRes->assertOk()
        ->assertJson([
            'status' => 'ok',
            'indexed' => true,
            'total_files' => 1,
            'total_chunks' => 1,
        ]);

    // 3. Search Endpoint
    $searchRes = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/rag/search", [
            'query' => 'zero-knowledge encryption',
            'limit' => 3,
        ]);

    $searchRes->assertOk()
        ->assertJsonStructure([
            'status',
            'query',
            'count',
            'results' => [
                '*' => ['file_id', 'path', 'heading', 'similarity', 'score_pct', 'content'],
            ],
        ]);

    // 4. Query Endpoint
    $queryRes = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/rag/query", [
            'query' => 'Explain encryption in this vault',
            'expand_graph' => true,
            'max_citations' => 2,
        ]);

    $queryRes->assertOk()
        ->assertJsonStructure([
            'status',
            'query',
            'answer',
            'citations',
            'graph_nodes',
            'model',
            'duration_ms',
        ]);
});

test('RAG API returns 403 when device token is not authorized for vault', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'cloud']);
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Secret Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    // Device token restricted to vault id 99999 (not this vault)
    $tokenResult = DeviceToken::createToken($user, $team, 'Guest Device', 'mobile');
    $tokenResult['device_token']->update(['allowed_vault_ids' => [99999]]);
    $plainToken = $tokenResult['plain_token'];

    $res = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/rag/query", [
            'query' => 'Leaked info?',
        ]);

    $res->assertForbidden();
});

test('RAG progress endpoint reports live indexing status and percentage', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'cloud']);
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Progress Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'MacBook Pro', 'desktop');
    $plainToken = $tokenResult['plain_token'];

    $res = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->getJson("/api/v1/vaults/{$vault->slug}/rag/progress");

    $res->assertOk()
        ->assertJsonStructure([
            'status',
            'vault',
            'percentage',
            'total_files',
            'indexed_files',
            'chunks_count',
        ]);
});

test('RAG index dispatches IndexVaultRagJob to queue when asynchronous', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'cloud']);
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Async Queue Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'MacBook Pro', 'desktop');
    $plainToken = $tokenResult['plain_token'];

    $res = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/rag/index", [
            'force' => true,
        ]);

    $res->assertStatus(202)
        ->assertJson([
            'status' => 'queued',
            'vault' => $vault->slug,
        ])
        ->assertJsonStructure(['progress_url']);

    Queue::assertPushed(IndexVaultRagJob::class, function ($job) use ($vault) {
        return $job->vault->id === $vault->id && $job->force === true;
    });
});
