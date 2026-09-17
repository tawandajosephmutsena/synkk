<?php

use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Services\VaultRagService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

test('Vault details page provides interactive Vault Copilot tab with chat and re-indexing', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'cloud']);
    $team->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Copilot Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $content = "# System Overview\nSynkk pairs 2D wikilinks with local vector embeddings. Connects to [[Security]].";
    Storage::disk('local')->put("vaults/{$vault->id}/overview.md", $content);
    $file = $vault->files()->create([
        'path' => 'Overview.md',
        'storage_path' => "vaults/{$vault->id}/overview.md",
        'sha256' => hash('sha256', $content),
        'size' => strlen($content),
        'version' => 1,
        'is_deleted' => false,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::vaults.show', ['current_team' => $team->slug, 'vault' => $vault])
        ->set('activeTab', 'copilot')
        ->assertSee('Vault Copilot & Agentic RAG Server')
        ->assertSee('Local RAG Active')
        ->call('reindexVaultEmbeddings')
        ->assertSee('Re-indexed 1 files')
        ->call('askCopilot', 'How does Synkk pair wikilinks with embeddings?')
        ->assertSee('Overview.md')
        ->call('openCitationNote', 'Overview.md')
        ->assertSet('activeTab', 'editor')
        ->assertSet('activeFileId', $file->id);
});

test('Vault Copilot strips unsafe HTML from generated answers', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'cloud']);
    $team->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);
    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Safe Copilot Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $ragService = Mockery::mock(VaultRagService::class);
    $ragService->shouldReceive('query')->once()->andReturn([
        'answer' => 'Safe answer <img src=x onerror="alert(1)"><script>alert(2)</script>',
        'citations' => [],
        'graph_nodes' => [],
        'model' => 'test/model',
        'duration_ms' => 1,
    ]);
    $ragService->shouldReceive('getStatus')->andReturn([
        'indexed' => false,
        'total_files' => 0,
        'total_chunks' => 0,
        'embedding_provider' => 'deterministic',
        'llm_provider' => 'test',
        'last_indexed_at' => null,
    ]);
    app()->instance(VaultRagService::class, $ragService);

    $this->actingAs($user);

    Livewire::test('pages::vaults.show', ['current_team' => $team->slug, 'vault' => $vault])
        ->set('activeTab', 'copilot')
        ->call('askCopilot', 'Give me an answer')
        ->assertSee('Safe answer')
        ->assertDontSeeHtml('<img src=x onerror="alert(1)">')
        ->assertDontSeeHtml('<script>alert(2)</script>');
});
