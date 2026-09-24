<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultPermission;
use App\Services\VaultRagService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('VaultRagService hybrid search finds top matching note chunks and extracts citations', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'cloud']);
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Specs Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $contentDlp = <<<'MD'
# DLP Secret Shield
Synkk enforces automatic client and server secret detection.
High entropy tokens like AWS keys, private RSA keys, and Stripe secrets are blocked.
MD;
    Storage::disk('local')->put("vaults/{$vault->id}/dlp.md", $contentDlp);
    $vault->files()->create([
        'path' => 'Security/DLP.md',
        'storage_path' => "vaults/{$vault->id}/dlp.md",
        'sha256' => hash('sha256', $contentDlp),
        'size' => strlen($contentDlp),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $contentRecipe = <<<'MD'
# Sourdough Recipe
Ingredients: 500g flour, 350g water, 100g active sourdough starter, 10g salt.
Bake at 230 degrees Celsius.
MD;
    Storage::disk('local')->put("vaults/{$vault->id}/recipe.md", $contentRecipe);
    $vault->files()->create([
        'path' => 'Recipes/Bread.md',
        'storage_path' => "vaults/{$vault->id}/recipe.md",
        'sha256' => hash('sha256', $contentRecipe),
        'size' => strlen($contentRecipe),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $ragService = app(VaultRagService::class);
    $ragService->indexVault($vault);
    $results = $ragService->search($vault, 'secret detection and high entropy AWS tokens', limit: 2);

    expect($results)->not->toBeEmpty()
        ->and($results[0]['path'])->toBe('Security/DLP.md')
        ->and($results[0]['similarity'])->toBeGreaterThan(0.50)
        ->and($results[0]['score_pct'])->toBeGreaterThan(50);
});

test('VaultRagService query synthesizes verified answers with citations and graph nodes', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'cloud']);
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'AI Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $contentA = <<<'MD'
# Sync Protocol
Synkk verifies SHA-256 hashes on every upload.
Conflicts generate automatic fork copies and link to [[Security/ACLs]].
MD;
    Storage::disk('local')->put("vaults/{$vault->id}/sync.md", $contentA);
    $vault->files()->create([
        'path' => 'Protocol/Sync.md',
        'storage_path' => "vaults/{$vault->id}/sync.md",
        'sha256' => hash('sha256', $contentA),
        'size' => strlen($contentA),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $contentB = <<<'MD'
# Path ACLs
Defines folder-level read and write access rules per member.
Links back to [[Protocol/Sync]].
MD;
    Storage::disk('local')->put("vaults/{$vault->id}/acls.md", $contentB);
    $vault->files()->create([
        'path' => 'Security/ACLs.md',
        'storage_path' => "vaults/{$vault->id}/acls.md",
        'sha256' => hash('sha256', $contentB),
        'size' => strlen($contentB),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $ragService = app(VaultRagService::class);
    $ragService->indexVault($vault);
    $response = $ragService->query($vault, 'How does the sync protocol handle hashes and conflicts?', [
        'expand_graph' => true,
        'max_citations' => 3,
    ]);

    expect($response['answer'])->toContain('Protocol/Sync.md')
        ->and($response['answer'])->toContain('Summary & Findings')
        ->and($response['answer'])->toContain('Relevant Notes & References')
        ->and($response['citations'])->not->toBeEmpty()
        ->and($response['citations'][0]['note'])->toBe('Protocol/Sync.md')
        ->and($response['model'])->toContain('deterministic-reasoning');
});

test('VaultRagService excludes hidden paths from search and graph context', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'cloud']);
    $team->members()->attach($owner, ['role' => 'owner']);
    $team->members()->attach($member, ['role' => 'member']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Permissioned RAG Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    $publicContent = "# Public Index\nThe public project index links to [[Secret/Plan]].";
    Storage::disk('local')->put("vaults/{$vault->id}/index.md", $publicContent);
    $vault->files()->create([
        'path' => 'Public/Index.md',
        'storage_path' => "vaults/{$vault->id}/index.md",
        'sha256' => hash('sha256', $publicContent),
        'size' => strlen($publicContent),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $secretContent = '# Secret Plan\nConfidential launch budget and acquisition strategy.';
    Storage::disk('local')->put("vaults/{$vault->id}/plan.md", $secretContent);
    $vault->files()->create([
        'path' => 'Secret/Plan.md',
        'storage_path' => "vaults/{$vault->id}/plan.md",
        'sha256' => hash('sha256', $secretContent),
        'size' => strlen($secretContent),
        'version' => 1,
        'is_deleted' => false,
    ]);

    VaultPermission::create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => 'Secret',
        'permission' => 'hidden',
        'is_folder' => true,
    ]);

    $ragService = app(VaultRagService::class);
    $ragService->indexVault($vault);

    $results = $ragService->search($vault, 'confidential launch budget', limit: 5, user: $member);
    $response = $ragService->query($vault, 'What does the public project index contain?', user: $member);
    $plainToken = DeviceToken::createToken($member, $team, 'Restricted RAG QA')['plain_token'];

    $apiResults = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson(route('api.vaults.rag.search', ['vault' => $vault->slug]), [
            'query' => 'confidential launch budget',
            'limit' => 5,
        ])
        ->assertOk()
        ->json('results');

    expect(collect($results)->pluck('path'))->not->toContain('Secret/Plan.md')
        ->and(collect($response['citations'])->pluck('note'))->not->toContain('Secret/Plan.md')
        ->and(collect($response['graph_nodes'])->pluck('path'))->not->toContain('Secret/Plan.md')
        ->and(collect($apiResults)->pluck('path'))->not->toContain('Secret/Plan.md');
});

test('extractBestExcerpt cleans markdown artifacts and prevents single digits like 5. from becoming excerpts', function () {
    $ragService = app(VaultRagService::class);

    $rawContent = <<<'MD'
### 5.1 Exact Phase 2 SMS concepts
5. Concept 1 — “She didn’t choose this” (the girl forced out of school) - Exact wording: Every day, a 14-year-old girl leaves school pregnant, unable to return.
MD;

    $excerpt = $ragService->extractBestExcerpt($rawContent, ['sms', 'concept']);

    expect($excerpt)->not->toBe('5.')
        ->and($excerpt)->not->toStartWith('###')
        ->and($excerpt)->toContain('Concept 1')
        ->and($excerpt)->toContain('Every day');
});

test('VaultRagService query marks low confidence searches with a helpful guidance alert', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'cloud']);
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Empty Specs',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $content = "# Project Notes\nGeneral meetings schedule for Tuesday mornings.";
    Storage::disk('local')->put("vaults/{$vault->id}/notes.md", $content);
    $vault->files()->create([
        'path' => 'Notes.md',
        'storage_path' => "vaults/{$vault->id}/notes.md",
        'sha256' => hash('sha256', $content),
        'size' => strlen($content),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $ragService = app(VaultRagService::class);
    $ragService->indexVault($vault);

    $response = $ragService->query($vault, 'quantum cryptography entanglement keys');

    expect($response['answer'])->toContain('Low confidence match')
        ->and($response['answer'])->toContain('quantum cryptography entanglement keys');
});
