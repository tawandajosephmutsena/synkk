<?php

use App\Actions\Vaults\SyncUploadAction;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultChangeLog;
use App\Models\VaultFileVersion;
use App\Models\VaultPermission;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

test('user can view markdown editor tab with note content by default', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Engineering Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $uploader = app(SyncUploadAction::class);
    $uploader->execute(
        vault: $vault,
        user: $user,
        deviceName: 'Web',
        path: 'Overview.md',
        contents: "# Project Overview\n\nWelcome to Synkk.\n\n> [!TIP]\n> Remember to keep notes linked.",
        baseVersion: 0,
    );

    $this->actingAs($user);

    $component = Livewire::test('pages::vaults.show', ['vault' => $vault]);

    $component
        ->assertOk()
        ->assertSet('activeTab', 'editor')
        ->assertSee('Markdown Editor')
        ->assertSee('Graph View')
        ->assertSee('Overview')
        ->assertSee('Welcome to Synkk')
        ->assertSee('Remember to keep notes linked');
});

test('can navigate directly to a specific note via path query parameter', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Product Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $uploader = app(SyncUploadAction::class);
    $uploader->execute(
        vault: $vault,
        user: $user,
        deviceName: 'Web',
        path: 'Roadmap.md',
        contents: '# Product Roadmap 2026',
        baseVersion: 0,
    );
    $uploader->execute(
        vault: $vault,
        user: $user,
        deviceName: 'Web',
        path: 'Specs/Auth.md',
        contents: '# Auth Specifications',
        baseVersion: 0,
    );

    $this->actingAs($user);

    $component = Livewire::withQueryParams(['tab' => 'editor', 'path' => 'Specs/Auth.md'])
        ->test('pages::vaults.show', ['vault' => $vault]);

    $component
        ->assertOk()
        ->assertSet('editorTitle', 'Auth')
        ->assertSee('# Auth Specifications');
});

test('tab query alias opens the graph view directly', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Graph Route Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('vaults.show', [
            'current_team' => $team->slug,
            'vault' => $vault->slug,
            'tab' => 'graph',
        ]));

    $response
        ->assertOk()
        ->assertSee('Explore how your notes connect')
        ->assertSee('Filter graph nodes...')
        ->assertSee('x-ref="graphCanvas"', escape: false)
        ->assertSee('role="img"', escape: false)
        ->assertSee('Accessible note index')
        ->assertSee('handleNodeKeydown($event, node)', escape: false);
});

test('a graph note opens in the markdown editor in one action', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Connected Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    app(SyncUploadAction::class)->execute(
        vault: $vault,
        user: $user,
        deviceName: 'Web',
        path: 'Launch.md',
        contents: '# Launch',
        baseVersion: 0,
    );

    $file = $vault->files()->sole();
    $this->actingAs($user);

    Livewire::withQueryParams(['tab' => 'graph'])
        ->test('pages::vaults.show', ['vault' => $vault])
        ->assertSet('activeTab', 'graph')
        ->call('openFileInEditor', $file->id)
        ->assertSet('activeTab', 'editor')
        ->assertSet('activeFileId', $file->id)
        ->assertSet('editorTitle', 'Launch');
});

test('user with read_write permission can save edits and generate changelog', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Work Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $uploader = app(SyncUploadAction::class);
    $uploader->execute(
        vault: $vault,
        user: $user,
        deviceName: 'Web',
        path: 'Notes.md',
        contents: 'Initial line',
        baseVersion: 0,
    );

    $file = $vault->files()->where('path', 'Notes.md')->firstOrFail();
    expect($file->version)->toBe(1);

    $this->actingAs($user);

    Livewire::test('pages::vaults.show', ['vault' => $vault])
        ->call('selectFile', $file->id)
        ->set('editorContent', "Initial line\nAdded second line via Web Editor")
        ->call('saveFile');

    $file->refresh();
    expect($file->version)->toBe(2);
    expect($file->getContents())->toContain('Added second line via Web Editor');

    // Verify snapshot in VaultFileVersion
    expect(VaultFileVersion::where('vault_file_id', $file->id)->count())->toBe(1);

    // Verify change log entry for dashboard activity tracking
    $log = VaultChangeLog::where('vault_id', $vault->id)
        ->where('path', 'Notes.md')
        ->latest('created_at')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->action)->toBe('updated');
    expect($log->version)->toBe(2);
    expect($log->user_id)->toBe($user->id);
});

test('stale editor save preserves the server version in a conflict note', function () {
    $this->travelTo('2026-09-05 10:00:00');

    $user = User::factory()->create(['name' => 'Alice Editor']);
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Concurrent Editing Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $uploader = app(SyncUploadAction::class);
    $uploader->execute(
        vault: $vault,
        user: $user,
        deviceName: 'Web',
        path: 'Notes.md',
        contents: 'Version loaded into the web editor',
        baseVersion: 0,
    );

    $file = $vault->files()->where('path', 'Notes.md')->firstOrFail();

    $this->actingAs($user);

    $component = Livewire::test('pages::vaults.show', ['vault' => $vault])
        ->call('selectFile', $file->id);

    $uploader->execute(
        vault: $vault,
        user: $user,
        deviceName: 'Obsidian Desktop',
        path: 'Notes.md',
        contents: 'Newer server content from Obsidian',
        baseVersion: 1,
    );

    $component
        ->set('editorContent', 'Stale local edits from the web editor')
        ->call('saveFile')
        ->assertDispatched('toast-show', fn (string $event, array $params): bool => $event === 'toast-show'
            && ($params['dataset']['variant'] ?? null) === 'warning');

    $file->refresh();
    $conflictFile = $vault->files()
        ->where('path', '!=', 'Notes.md')
        ->sole();

    expect($file->version)->toBe(2)
        ->and($file->getContents())->toBe('Newer server content from Obsidian')
        ->and($conflictFile->path)->toStartWith('Notes.conflict-alice-editor-')
        ->and($conflictFile->getContents())->toBe('Stale local edits from the web editor');

    $component
        ->assertSet('activeFileId', $conflictFile->id)
        ->assertSet('path', $conflictFile->path)
        ->assertSet('editorContent', 'Stale local edits from the web editor');
});

test('user with read_only permission cannot save edits to note', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Protected Vault',
        'default_permission' => 'read_only',
        'created_by' => $owner->id,
    ]);

    $uploader = app(SyncUploadAction::class);
    $uploader->execute(
        vault: $vault,
        user: $owner,
        deviceName: 'Web',
        path: 'CompanyPolicies.md',
        contents: '# Strict Policies',
        baseVersion: 0,
    );

    $file = $vault->files()->where('path', 'CompanyPolicies.md')->firstOrFail();

    $this->actingAs($member);

    Livewire::test('pages::vaults.show', ['vault' => $vault])
        ->call('selectFile', $file->id)
        ->assertSet('canEditActiveFile', false)
        ->set('editorContent', 'Hacked Content')
        ->call('saveFile');

    $file->refresh();
    expect($file->version)->toBe(1);
    expect($file->getContents())->toBe('# Strict Policies');
});

test('user with read_only permission cannot restore a historical note version', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Read Only History Vault',
        'default_permission' => 'read_only',
        'created_by' => $owner->id,
    ]);

    $uploader = app(SyncUploadAction::class);
    $uploader->execute(
        vault: $vault,
        user: $owner,
        deviceName: 'Web',
        path: 'Handbook.md',
        contents: 'Original handbook',
        baseVersion: 0,
    );
    $uploader->execute(
        vault: $vault,
        user: $owner,
        deviceName: 'Web',
        path: 'Handbook.md',
        contents: 'Updated handbook',
        baseVersion: 1,
    );

    $file = $vault->files()->where('path', 'Handbook.md')->firstOrFail();
    $version = VaultFileVersion::where('vault_file_id', $file->id)->firstOrFail();

    $this->actingAs($member);

    Livewire::test('pages::vaults.show', ['vault' => $vault])
        ->call('restoreVersion', $version->id)
        ->assertForbidden();

    $file->refresh();
    expect($file->getContents())->toBe('Updated handbook');
});

test('member with write permission can restore a historical note version', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Writable History Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    $uploader = app(SyncUploadAction::class);
    $uploader->execute(
        vault: $vault,
        user: $owner,
        deviceName: 'Web',
        path: 'Handbook.md',
        contents: 'Original handbook',
        baseVersion: 0,
    );
    $uploader->execute(
        vault: $vault,
        user: $owner,
        deviceName: 'Web',
        path: 'Handbook.md',
        contents: 'Updated handbook',
        baseVersion: 1,
    );

    $file = $vault->files()->where('path', 'Handbook.md')->firstOrFail();
    $version = VaultFileVersion::where('vault_file_id', $file->id)->firstOrFail();

    $this->actingAs($member);

    Livewire::test('pages::vaults.show', ['vault' => $vault])
        ->call('restoreVersion', $version->id)
        ->assertDispatched('toast-show');

    $file->refresh();

    expect($file->version)->toBe(3)
        ->and($file->getContents())->toBe('Original handbook');
});

test('user can create new note which opens directly in editor', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Creation Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::vaults.show', ['vault' => $vault])
        ->set('newNotePath', 'Ideas/Brainstorming.md')
        ->call('createNewNote')
        ->assertSet('activeTab', 'editor')
        ->assertSet('editorTitle', 'Brainstorming')
        ->assertDispatched('modal-close', name: 'new-note-modal');

    $newFile = $vault->files()->where('path', 'Ideas/Brainstorming.md')->first();
    expect($newFile)->not->toBeNull();
    expect($newFile->version)->toBe(1);

    // Verify change log entry
    $change = VaultChangeLog::where('vault_id', $vault->id)->where('path', 'Ideas/Brainstorming.md')->first();
    expect($change)->not->toBeNull();
    expect($change->action)->toBe('created');
});

test('creating a note at an equivalent existing path is rejected without overwriting it', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Duplicate Note Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    app(SyncUploadAction::class)->execute(
        vault: $vault,
        user: $user,
        deviceName: 'Obsidian Desktop',
        path: 'Ideas/Existing.md',
        contents: '# Existing note that must be preserved',
        baseVersion: 0,
    );

    $file = $vault->files()->where('path', 'Ideas/Existing.md')->firstOrFail();

    $this->actingAs($user);

    Livewire::test('pages::vaults.show', ['vault' => $vault])
        ->set('newNotePath', 'ideas\\existing.md')
        ->call('createNewNote')
        ->assertHasErrors(['newNotePath'])
        ->assertNotDispatched('modal-close', name: 'new-note-modal');

    $file->refresh();

    expect($vault->files()->count())->toBe(1)
        ->and($vault->changeLogs()->count())->toBe(1)
        ->and($file->version)->toBe(1)
        ->and($file->getContents())->toBe('# Existing note that must be preserved');
});

test('viewing file history opens the Flux modal for an accessible file', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'History Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    app(SyncUploadAction::class)->execute(
        vault: $vault,
        user: $user,
        deviceName: 'Web',
        path: 'History.md',
        contents: '# History',
        baseVersion: 0,
    );

    $file = $vault->files()->where('path', 'History.md')->firstOrFail();

    $this->actingAs($user);

    Livewire::test('pages::vaults.show', ['vault' => $vault])
        ->call('showFileHistory', $file->id)
        ->assertSet('selectedFileId', $file->id)
        ->assertSet('selectedFile', fn ($selectedFile): bool => $selectedFile?->is($file) ?? false)
        ->assertDispatched('modal-show', name: 'file-history');
});

test('file history returns 404 for a file from another vault', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Current Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);
    $otherVault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Other Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    app(SyncUploadAction::class)->execute(
        vault: $otherVault,
        user: $user,
        deviceName: 'Web',
        path: 'Other.md',
        contents: '# Other vault history',
        baseVersion: 0,
    );

    $otherFile = $otherVault->files()->where('path', 'Other.md')->firstOrFail();

    $this->actingAs($user);

    Livewire::test('pages::vaults.show', ['vault' => $vault])
        ->call('showFileHistory', $otherFile->id)
        ->assertNotFound()
        ->assertSet('selectedFileId', null)
        ->assertSet('selectedFile', null)
        ->assertNotDispatched('modal-show');
});

test('file history returns 404 for a hidden file', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Hidden History Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    app(SyncUploadAction::class)->execute(
        vault: $vault,
        user: $owner,
        deviceName: 'Web',
        path: 'Secret/Payroll.md',
        contents: '# Confidential payroll',
        baseVersion: 0,
    );

    $hiddenFile = $vault->files()->where('path', 'Secret/Payroll.md')->firstOrFail();

    VaultPermission::create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => 'Secret',
        'permission' => 'hidden',
        'is_folder' => true,
    ]);

    $this->actingAs($member);

    Livewire::test('pages::vaults.show', ['vault' => $vault])
        ->call('showFileHistory', $hiddenFile->id)
        ->assertNotFound()
        ->assertSet('selectedFileId', null)
        ->assertSet('selectedFile', null)
        ->assertNotDispatched('modal-show');
});

test('vault graph view accurately resolves nodes and wikilink edges', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Graph Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $uploader = app(SyncUploadAction::class);
    $uploader->execute(
        vault: $vault,
        user: $user,
        deviceName: 'Web',
        path: 'Alpha.md',
        contents: 'Referencing [[Beta]] and [[Gamma]] here.',
        baseVersion: 0,
    );
    $uploader->execute(
        vault: $vault,
        user: $user,
        deviceName: 'Web',
        path: 'Beta.md',
        contents: 'Referencing [[Alpha]] back.',
        baseVersion: 0,
    );
    $uploader->execute(
        vault: $vault,
        user: $user,
        deviceName: 'Web',
        path: 'Gamma.md',
        contents: 'Stand-alone leaf note.',
        baseVersion: 0,
    );

    $this->actingAs($user);

    $component = Livewire::test('pages::vaults.show', ['vault' => $vault])
        ->set('activeTab', 'graph');

    $graphData = $component->get('graphData');

    expect($graphData['nodes'])->toHaveCount(3);
    // Alpha connects to Beta and Gamma; Beta connects to Alpha (bidirectional edge deduped)
    expect(count($graphData['edges']))->toBeGreaterThanOrEqual(2);
});

test('hidden notes are excluded from editor note list and graph nodes', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Restricted Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    $uploader = app(SyncUploadAction::class);
    $uploader->execute(
        vault: $vault,
        user: $owner,
        deviceName: 'Web',
        path: 'PublicNote.md',
        contents: 'Visible note',
        baseVersion: 0,
    );
    $uploader->execute(
        vault: $vault,
        user: $owner,
        deviceName: 'Web',
        path: 'Secret/Payroll.md',
        contents: 'Confidential salaries',
        baseVersion: 0,
    );

    // Create hidden permission rule for member on Secret/ folder
    VaultPermission::create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => 'Secret',
        'permission' => 'hidden',
        'is_folder' => true,
    ]);

    $this->actingAs($member);

    $component = Livewire::test('pages::vaults.show', ['vault' => $vault]);

    // Member should only see PublicNote.md
    $accessibleFiles = $component->get('accessibleFiles');
    expect($accessibleFiles->pluck('path')->all())->toEqual(['PublicNote.md']);

    $graphData = $component->get('graphData');
    expect(collect($graphData['nodes'])->pluck('path')->all())->toEqual(['PublicNote.md']);
});
