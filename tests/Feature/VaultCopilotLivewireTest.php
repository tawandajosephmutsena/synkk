<?php

use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
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
        ->call('askCopilot', 'How does Synkk pair wikilinks with embeddings?')
        ->assertSee('Overview.md')
        ->call('reindexVaultEmbeddings')
        ->assertSee('Re-indexed 1 files')
        ->call('openCitationNote', 'Overview.md')
        ->assertSet('activeTab', 'editor')
        ->assertSet('activeFileId', $file->id);
});
