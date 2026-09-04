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
        ->assertSet('editorTitle', 'Brainstorming');

    $newFile = $vault->files()->where('path', 'Ideas/Brainstorming.md')->first();
    expect($newFile)->not->toBeNull();
    expect($newFile->version)->toBe(1);

    // Verify change log entry
    $change = VaultChangeLog::where('vault_id', $vault->id)->where('path', 'Ideas/Brainstorming.md')->first();
    expect($change)->not->toBeNull();
    expect($change->action)->toBe('created');
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
