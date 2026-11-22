<?php

use App\Actions\Vaults\SyncUploadAction;
use App\Enums\TeamRole;
use App\Jobs\IndexVaultFileJob;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\TeamAiConfig;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultAiSetting;
use App\Models\VaultFileEmbedding;
use App\Models\VaultPermission;
use App\Services\Ai\HybridVaultSearchService;
use App\Services\Ai\VaultIndexService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('HybridVaultSearchService indexes packed float32 blobs and FTS5, handles punctuation safely, and enforces ACLs & excluded_paths', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/embeddings' => function ($request) {
            $inputs = $request->data()['input'] ?? [];
            $data = [];
            foreach ($inputs as $idx => $text) {
                $vec = str_contains(strtolower((string) $text), 'kubernetes')
                    ? [0.95, 0.05, 0.0]
                    : (str_contains(strtolower((string) $text), 'private') ? [0.1, 0.9, 0.0] : [0.0, 0.1, 0.95]);
                $data[] = ['index' => $idx, 'embedding' => $vec];
            }

            return Http::response([
                'model' => 'text-embedding-3-small',
                'data' => $data,
                'usage' => ['prompt_tokens' => 15],
            ], 200);
        },
    ]);

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'pro_ltd']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    TeamAiConfig::create([
        'team_id' => $team->id,
        'is_enabled' => true,
        'provider_mode' => 'byok',
        'chat_provider' => 'openai',
        'chat_model' => 'gpt-4o-mini',
        'chat_api_key' => 'sk-test-12345678901234567890',
        'embedding_provider' => 'openai',
        'embedding_model' => 'text-embedding-3-small',
    ]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Engineering Hybrid Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    VaultAiSetting::create([
        'vault_id' => $vault->id,
        'ai_mode' => 'server',
        'excluded_paths' => ['Private/**'],
    ]);

    VaultPermission::create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => 'Executive',
        'permission' => 'hidden',
        'is_folder' => true,
    ]);

    $k8sContent = "# Kubernetes Cluster\nDeploying C++ microservices with Helm charts and [[Infra/Runbook]].";
    Storage::disk('local')->put("vaults/{$vault->id}/k8s.md", $k8sContent);
    $vault->files()->create([
        'path' => 'Infra/Kubernetes.md',
        'storage_path' => "vaults/{$vault->id}/k8s.md",
        'sha256' => hash('sha256', $k8sContent),
        'size' => strlen($k8sContent),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $privateContent = "# Private Diary\nPersonal private kubernetes keys and salaries.";
    Storage::disk('local')->put("vaults/{$vault->id}/private.md", $privateContent);
    $vault->files()->create([
        'path' => 'Private/Secrets.md',
        'storage_path' => "vaults/{$vault->id}/private.md",
        'sha256' => hash('sha256', $privateContent),
        'size' => strlen($privateContent),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $execContent = "# Executive Memo\nExecutive kubernetes roadmap for board review.";
    Storage::disk('local')->put("vaults/{$vault->id}/exec.md", $execContent);
    $vault->files()->create([
        'path' => 'Executive/Roadmap.md',
        'storage_path' => "vaults/{$vault->id}/exec.md",
        'sha256' => hash('sha256', $execContent),
        'size' => strlen($execContent),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $indexService = app(VaultIndexService::class);
    $summary = $indexService->indexVault($vault);

    // Private/Secrets.md was skipped by excluded_paths during indexing
    expect($summary['status'])->toBe('indexed')
        ->and(VaultFileEmbedding::where('vault_id', $vault->id)->count())->toBe(2);

    $k8sChunk = VaultFileEmbedding::where('vault_id', $vault->id)->first();
    expect($k8sChunk?->embedding_blob)->not->toBeNull()
        ->and($k8sChunk?->embedding_dimensions)->toBe(3)
        ->and($k8sChunk?->embedding_model)->toBe('openai:text-embedding-3-small')
        ->and(DB::table('vault_chunks_fts')->where('vault_id', $vault->id)->count())->toBe(2);

    // Punctuation-heavy query should NOT trigger an FTS5 syntax error, and member cannot see Executive/Roadmap.md
    $searchService = app(HybridVaultSearchService::class);
    $results = $searchService->search($vault, 'C++ [[Kubernetes]] * OR NOT "unclosed', limit: 5, user: $member);

    expect($results)->toHaveCount(1)
        ->and($results[0]['path'])->toBe('Infra/Kubernetes.md')
        ->and($results[0]['score_pct'])->toBeGreaterThan(50);
});

test('incremental IndexVaultFileJob is dispatched on sync only when team AI and vault server AI are enabled', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'pro_ltd']);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Incremental Sync Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $syncUpload = app(SyncUploadAction::class);

    // 1. Default AI off -> zero jobs dispatched
    $syncUpload->execute($vault, $user, 'Mac', 'Notes/DefaultOff.md', '# Hello');
    Queue::assertNothingPushed();

    // 2. Enable Team AI + Vault Server AI -> upload & delete dispatch IndexVaultFileJob
    TeamAiConfig::create([
        'team_id' => $team->id,
        'is_enabled' => true,
        'provider_mode' => 'ollama',
        'chat_provider' => 'ollama',
        'chat_model' => 'llama3.2',
    ]);

    VaultAiSetting::create([
        'vault_id' => $vault->id,
        'ai_mode' => 'server',
        'excluded_paths' => ['Private/**'],
    ]);

    $vault->refresh();

    // Excluded path still does NOT dispatch
    $syncUpload->execute($vault, $user, 'Mac', 'Private/Ignore.md', '# Ignored');
    Queue::assertNothingPushed();

    // Normal markdown file dispatches incremental indexing job
    $syncUpload->execute($vault, $user, 'Mac', 'Notes/Active.md', '# Active Note');
    Queue::assertPushed(IndexVaultFileJob::class, function (IndexVaultFileJob $job) use ($vault): bool {
        return $job->vaultId === $vault->id && $job->deleted === false;
    });

    // API delete dispatches deletion cleanup job
    $token = DeviceToken::createToken($user, $team, 'Mac', 'mac')['plain_token'];
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/delete", ['path' => 'Notes/Active.md'])
        ->assertOk();

    Queue::assertPushed(IndexVaultFileJob::class, function (IndexVaultFileJob $job) use ($vault): bool {
        return $job->vaultId === $vault->id && $job->deleted === true;
    });
});
