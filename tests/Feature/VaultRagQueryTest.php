<?php

use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
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
        ->and($response['citations'])->not->toBeEmpty()
        ->and($response['citations'][0]['note'])->toBe('Protocol/Sync.md')
        ->and($response['model'])->toContain('deterministic-reasoning');
});
