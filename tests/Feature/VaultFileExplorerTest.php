<?php

use App\Models\User;
use App\Models\Vault;
use App\Models\VaultChangeLog;
use App\Models\VaultFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(config('synkk.storage_disk', 'local'));
});

test('file explorer renders categories, statistics, and filters accurately', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Knowledge Base',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    // Create a markdown file
    $disk = config('synkk.storage_disk', 'local');
    Storage::disk($disk)->put('vaults/'.$vault->id.'/Notes/Brainstorming.md', "# Brainstorming\n\n- Project Alpha\n- Project Beta\n");
    VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'Notes/Brainstorming.md',
        'storage_path' => 'vaults/'.$vault->id.'/Notes/Brainstorming.md',
        'sha256' => hash('sha256', "# Brainstorming\n\n- Project Alpha\n- Project Beta\n"),
        'size' => 45,
        'version' => 1,
        'is_deleted' => false,
        'last_modified_by' => $user->id,
    ]);

    // Create a canvas file
    Storage::disk($disk)->put('vaults/'.$vault->id.'/Diagrams/Arch.canvas', '{"nodes":[],"edges":[]}');
    VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'Diagrams/Arch.canvas',
        'storage_path' => 'vaults/'.$vault->id.'/Diagrams/Arch.canvas',
        'sha256' => hash('sha256', '{"nodes":[],"edges":[]}'),
        'size' => 25,
        'version' => 1,
        'is_deleted' => false,
        'last_modified_by' => $user->id,
    ]);

    // Create an attachment
    Storage::disk($disk)->put('vaults/'.$vault->id.'/Images/logo.png', 'fake_png_data');
    VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'Images/logo.png',
        'storage_path' => 'vaults/'.$vault->id.'/Images/logo.png',
        'sha256' => hash('sha256', 'fake_png_data'),
        'size' => 13,
        'version' => 1,
        'is_deleted' => false,
        'last_modified_by' => $user->id,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::vaults.show', ['vault' => $vault])
        ->set('activeTab', 'files');

    $component->assertSee('Vault File Browser')
        ->assertSee('Brainstorming.md')
        ->assertSee('Arch.canvas')
        ->assertSee('logo.png');

    // Filter by markdown
    $component->set('fileCategory', 'markdown')
        ->assertSee('Brainstorming.md')
        ->assertDontSee('Arch.canvas')
        ->assertDontSee('logo.png');

    // Filter by canvas
    $component->set('fileCategory', 'canvas')
        ->assertSee('Arch.canvas')
        ->assertDontSee('Brainstorming.md')
        ->assertDontSee('logo.png');

    // Filter by attachments
    $component->set('fileCategory', 'attachments')
        ->assertSee('logo.png')
        ->assertDontSee('Brainstorming.md')
        ->assertDontSee('Arch.canvas');
});

test('user can duplicate a note with copy suffix', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Duplication Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $disk = config('synkk.storage_disk', 'local');
    $content = "# Master Strategy\n\nConfidential plan.";
    Storage::disk($disk)->put('vaults/'.$vault->id.'/Docs/Strategy.md', $content);

    $file = VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'Docs/Strategy.md',
        'storage_path' => 'vaults/'.$vault->id.'/Docs/Strategy.md',
        'sha256' => hash('sha256', $content),
        'size' => strlen($content),
        'version' => 1,
        'is_deleted' => false,
        'last_modified_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::vaults.show', ['vault' => $vault])
        ->set('activeTab', 'files')
        ->call('duplicateFile', $file->id);

    $duplicate = VaultFile::where('vault_id', $vault->id)
        ->where('path', 'Docs/Strategy (Copy).md')
        ->first();

    expect($duplicate)->not->toBeNull()
        ->and($duplicate->is_deleted)->toBeFalse();

    expect(Storage::disk($disk)->get($duplicate->storage_path))->toBe($content);
});

test('user can rename a note path and old path is marked deleted', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Rename Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $disk = config('synkk.storage_disk', 'local');
    $content = "# Draft Article\n\nWork in progress.";
    Storage::disk($disk)->put('vaults/'.$vault->id.'/Drafts/OldName.md', $content);

    $file = VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'Drafts/OldName.md',
        'storage_path' => 'vaults/'.$vault->id.'/Drafts/OldName.md',
        'sha256' => hash('sha256', $content),
        'size' => strlen($content),
        'version' => 1,
        'is_deleted' => false,
        'last_modified_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::vaults.show', ['vault' => $vault])
        ->set('activeTab', 'files')
        ->call('openRenameModal', $file->id)
        ->assertSet('renamingFileId', $file->id)
        ->assertSet('renamingNewPath', 'Drafts/OldName.md')
        ->set('renamingNewPath', 'Published/NewArticle.md')
        ->call('executeRename');

    expect($file->fresh()->is_deleted)->toBeTrue();

    $renamed = VaultFile::where('vault_id', $vault->id)
        ->where('path', 'Published/NewArticle.md')
        ->where('is_deleted', false)
        ->first();

    expect($renamed)->not->toBeNull();

    $log = VaultChangeLog::where('vault_id', $vault->id)
        ->where('path', 'Drafts/OldName.md')
        ->where('action', 'deleted')
        ->first();

    expect($log)->not->toBeNull();
});

test('user can soft delete a note with changelog logging', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Delete Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $file = VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'Scratch/Temp.md',
        'storage_path' => 'vaults/'.$vault->id.'/Scratch/Temp.md',
        'sha256' => hash('sha256', 'temp'),
        'size' => 4,
        'version' => 1,
        'is_deleted' => false,
        'last_modified_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::vaults.show', ['vault' => $vault])
        ->set('activeTab', 'files')
        ->call('confirmDeleteFile', $file->id)
        ->assertSet('deletingFileId', $file->id)
        ->call('executeDeleteFile');

    expect($file->fresh()->is_deleted)->toBeTrue();

    $log = VaultChangeLog::where('vault_id', $vault->id)
        ->where('path', 'Scratch/Temp.md')
        ->where('action', 'deleted')
        ->first();

    expect($log)->not->toBeNull();
});

test('user can inspect file metadata including word count, lines, and links', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Inspection Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $content = "# Project Brief\n\nThis note references [[Architecture]] and [[Database]]. It also has tags #planning #roadmap.\n";
    $disk = config('synkk.storage_disk', 'local');
    Storage::disk($disk)->put('vaults/'.$vault->id.'/Project.md', $content);

    $file = VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'Project.md',
        'storage_path' => 'vaults/'.$vault->id.'/Project.md',
        'sha256' => hash('sha256', $content),
        'size' => strlen($content),
        'version' => 2,
        'is_deleted' => false,
        'last_modified_by' => $user->id,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::vaults.show', ['vault' => $vault])
        ->set('activeTab', 'files')
        ->call('inspectFile', $file->id);

    $component->assertSet('inspectedFileId', $file->id);
    $meta = $component->get('inspectedFileMetadata');

    expect($meta['words'])->toBeGreaterThan(5)
        ->and($meta['lines'])->toBeGreaterThanOrEqual(2)
        ->and($meta['wikilinks'])->toContain('Architecture')
        ->and($meta['wikilinks'])->toContain('Database')
        ->and($meta['tags'])->toContain('planning')
        ->and($meta['tags'])->toContain('roadmap');
});
