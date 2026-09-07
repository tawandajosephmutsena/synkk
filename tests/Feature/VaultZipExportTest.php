<?php

use App\Actions\Vaults\SyncUploadAction;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultPermission;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

test('authorized user can export vault as zip archive and download it', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Project Alpha',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $uploader = app(SyncUploadAction::class);
    $uploader->execute(
        vault: $vault,
        user: $user,
        deviceName: 'MacBook Pro',
        path: 'Notes/Getting Started.md',
        contents: "# Welcome to Project Alpha\nThis is synced markdown.",
        baseVersion: 0,
    );
    $uploader->execute(
        vault: $vault,
        user: $user,
        deviceName: 'MacBook Pro',
        path: 'Docs/Architecture.md',
        contents: '# System Architecture',
        baseVersion: 0,
    );

    $this->actingAs($user);

    $component = Livewire::test('pages::vaults.show', ['vault' => $vault]);
    $component->call('exportVaultZip');

    $downloadEffect = data_get($component->effects, 'download');
    expect($downloadEffect)->not->toBeNull();
    expect($downloadEffect['name'])->toContain('project-alpha-export-');
    expect($downloadEffect['contentType'])->toBe('application/zip');

    $tempZip = tempnam(sys_get_temp_dir(), 'test_zip_');
    file_put_contents($tempZip, base64_decode($downloadEffect['content']));

    $zip = new ZipArchive;
    $openResult = $zip->open($tempZip);
    expect($openResult)->toBeTrue();

    expect($zip->numFiles)->toBe(2);
    expect($zip->locateName('Notes/Getting Started.md'))->not->toBeFalse();
    expect($zip->locateName('Docs/Architecture.md'))->not->toBeFalse();

    $content = $zip->getFromName('Notes/Getting Started.md');
    expect($content)->toBe("# Welcome to Project Alpha\nThis is synced markdown.");

    $zip->close();
    @unlink($tempZip);
});

test('exportVaultZip excludes files with hidden permissions for the user', function () {
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
        deviceName: 'MacBook',
        path: 'Public/Handbook.md',
        contents: '# Public Handbook',
        baseVersion: 0,
    );
    $uploader->execute(
        vault: $vault,
        user: $owner,
        deviceName: 'MacBook',
        path: 'Finance/Salaries.md',
        contents: '# Confidential Salaries',
        baseVersion: 0,
    );

    // Member has explicit hidden permission on Finance folder
    VaultPermission::create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => 'Finance',
        'is_folder' => true,
        'permission' => 'hidden',
    ]);

    $this->actingAs($member);

    $component = Livewire::test('pages::vaults.show', ['vault' => $vault]);
    $component->call('exportVaultZip');

    $downloadEffect = data_get($component->effects, 'download');
    expect($downloadEffect)->not->toBeNull();

    $tempZip = tempnam(sys_get_temp_dir(), 'test_zip_');
    file_put_contents($tempZip, base64_decode($downloadEffect['content']));

    $zip = new ZipArchive;
    $zip->open($tempZip);

    expect($zip->numFiles)->toBe(1);
    expect($zip->locateName('Public/Handbook.md'))->not->toBeFalse();
    expect($zip->locateName('Finance/Salaries.md'))->toBeFalse();

    $zip->close();
    @unlink($tempZip);
});

test('exportVaultZip returns no content and warning toast when vault has no files', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Empty Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::vaults.show', ['vault' => $vault]);
    $component->call('exportVaultZip');

    $component->assertNoFileDownloaded();
});

test('unauthorized user cannot export vault zip', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($stranger, ['role' => TeamRole::Owner->value]);
    $stranger->update(['current_team_id' => $otherTeam->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Private Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    $this->actingAs($stranger);

    Livewire::test('pages::vaults.show', ['vault' => $vault])
        ->assertForbidden();
});
