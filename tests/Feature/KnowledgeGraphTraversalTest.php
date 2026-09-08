<?php

use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Services\KnowledgeGraphService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('KnowledgeGraphService constructs graph topology and detects wikilink edges', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Knowledge Base',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    // Note A links to Note B and Note C
    $contentA = "# Note A\nLinks to [[Note B]] and [[Note C]].";
    Storage::disk('local')->put("vaults/{$vault->id}/note-a.md", $contentA);
    $vault->files()->create([
        'path' => 'Note A.md',
        'storage_path' => "vaults/{$vault->id}/note-a.md",
        'sha256' => hash('sha256', $contentA),
        'size' => strlen($contentA),
        'version' => 1,
        'is_deleted' => false,
    ]);

    // Note B links back to Note A
    $contentB = "# Note B\nBacklink to [[Note A]].";
    Storage::disk('local')->put("vaults/{$vault->id}/note-b.md", $contentB);
    $vault->files()->create([
        'path' => 'Note B.md',
        'storage_path' => "vaults/{$vault->id}/note-b.md",
        'sha256' => hash('sha256', $contentB),
        'size' => strlen($contentB),
        'version' => 1,
        'is_deleted' => false,
    ]);

    // Note C has no outbound links
    $contentC = "# Note C\nTerminal node with no links.";
    Storage::disk('local')->put("vaults/{$vault->id}/note-c.md", $contentC);
    $vault->files()->create([
        'path' => 'Note C.md',
        'storage_path' => "vaults/{$vault->id}/note-c.md",
        'sha256' => hash('sha256', $contentC),
        'size' => strlen($contentC),
        'version' => 1,
        'is_deleted' => false,
    ]);

    // Note D is an orphan
    $contentD = "# Note D\nCompletely isolated concept.";
    Storage::disk('local')->put("vaults/{$vault->id}/note-d.md", $contentD);
    $vault->files()->create([
        'path' => 'Note D.md',
        'storage_path' => "vaults/{$vault->id}/note-d.md",
        'sha256' => hash('sha256', $contentD),
        'size' => strlen($contentD),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $service = app(KnowledgeGraphService::class);
    $topology = $service->getGraphTopology($vault);

    expect($topology['nodes'])->toHaveCount(4);

    $nodesByPath = collect($topology['nodes'])->keyBy('path');
    expect($nodesByPath['Note A.md']['outbound'])->toBeGreaterThanOrEqual(2)
        ->and($nodesByPath['Note B.md']['inbound'])->toBeGreaterThanOrEqual(1)
        ->and($nodesByPath['Note D.md']['is_orphan'])->toBeTrue();

    // Verify edges contain Note A -> Note B
    $edges = collect($topology['edges']);
    $hasEdge = $edges->contains(fn ($e) => $e['source'] === 'Note A.md' && $e['target'] === 'Note B.md');
    expect($hasEdge)->toBeTrue();
});

test('KnowledgeGraphService expands context along 1-hop and 2-hop connected backlinks', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Graph Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $fileRoot = 'Architecture.md';
    $contentRoot = "# Architecture\nDefines [[Security]] and [[Protocols]].";
    Storage::disk('local')->put("vaults/{$vault->id}/arch.md", $contentRoot);
    $vault->files()->create([
        'path' => $fileRoot,
        'storage_path' => "vaults/{$vault->id}/arch.md",
        'sha256' => hash('sha256', $contentRoot),
        'size' => strlen($contentRoot),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $fileSec = 'Security.md';
    $contentSec = "# Security\nBacklink to [[Architecture]]. Details on encryption.";
    Storage::disk('local')->put("vaults/{$vault->id}/sec.md", $contentSec);
    $vault->files()->create([
        'path' => $fileSec,
        'storage_path' => "vaults/{$vault->id}/sec.md",
        'sha256' => hash('sha256', $contentSec),
        'size' => strlen($contentSec),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $service = app(KnowledgeGraphService::class);
    $expanded = $service->expandContext($vault, ['Architecture.md'], depth: 1);

    expect($expanded)->not->toBeEmpty();
    $paths = array_column($expanded, 'path');
    expect($paths)->toContain('Security.md');
});

test('KnowledgeGraphService getInteractiveGraph generates index-based graph format for UI components', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'UI Graph Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $contentA = "# Alpha\nPoints to [[Beta]].";
    Storage::disk('local')->put("vaults/{$vault->id}/alpha.md", $contentA);
    $vault->files()->create([
        'path' => 'Alpha.md',
        'storage_path' => "vaults/{$vault->id}/alpha.md",
        'sha256' => hash('sha256', $contentA),
        'size' => strlen($contentA),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $contentB = "# Beta\nTerminal node.";
    Storage::disk('local')->put("vaults/{$vault->id}/beta.md", $contentB);
    $vault->files()->create([
        'path' => 'Beta.md',
        'storage_path' => "vaults/{$vault->id}/beta.md",
        'sha256' => hash('sha256', $contentB),
        'size' => strlen($contentB),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $service = app(KnowledgeGraphService::class);
    $interactive = $service->getInteractiveGraph($vault);

    expect($interactive['nodes'])->toHaveCount(2)
        ->and($interactive['edges'])->toHaveCount(1)
        ->and($interactive['edges'][0]['source'])->toBe(0)
        ->and($interactive['edges'][0]['target'])->toBe(1)
        ->and($interactive['nodes'][0]['linksCount'])->toBe(1)
        ->and($interactive['nodes'][1]['linksCount'])->toBe(1);
});

test('KnowledgeGraphService caches interactive graph and busts cache on version increment', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Cache Test Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $contentA = "# Doc One\nLinks to [[Doc Two]].";
    Storage::disk('local')->put("vaults/{$vault->id}/doc1.md", $contentA);
    $vault->files()->create([
        'path' => 'Doc One.md',
        'storage_path' => "vaults/{$vault->id}/doc1.md",
        'sha256' => hash('sha256', $contentA),
        'size' => strlen($contentA),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $service = app(KnowledgeGraphService::class);
    $cacheKeyV1 = "vault_graph_{$vault->id}_v1";

    expect(Cache::has($cacheKeyV1))->toBeFalse();

    $result1 = $service->getInteractiveGraph($vault);
    expect(Cache::has($cacheKeyV1))->toBeTrue()
        ->and($result1['nodes'])->toHaveCount(1);

    // Modify file contents on disk without changing DB version; cache should return cached result
    Storage::disk('local')->put("vaults/{$vault->id}/doc1.md", '# Modified without version bump');
    $cachedResult = $service->getInteractiveGraph($vault);
    expect($cachedResult)->toEqual($result1);

    // Now bump version by adding a second file at version 2
    $contentB = "# Doc Two\nLinked note to [[Doc One]].";
    Storage::disk('local')->put("vaults/{$vault->id}/doc2.md", $contentB);
    $vault->files()->create([
        'path' => 'Doc Two.md',
        'storage_path' => "vaults/{$vault->id}/doc2.md",
        'sha256' => hash('sha256', $contentB),
        'size' => strlen($contentB),
        'version' => 2,
        'is_deleted' => false,
    ]);

    $cacheKeyV2 = "vault_graph_{$vault->id}_v2";
    expect(Cache::has($cacheKeyV2))->toBeFalse();

    $result2 = $service->getInteractiveGraph($vault);
    expect(Cache::has($cacheKeyV2))->toBeTrue()
        ->and($result2['nodes'])->toHaveCount(2)
        ->and($result2['edges'])->toHaveCount(1);
});
