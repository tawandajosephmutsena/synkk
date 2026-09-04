<?php

use App\Actions\Vaults\RestoreFileVersionAction;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultFileVersion;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('archives previous version snapshot when updating note content', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Version Test Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'MacBook Pro', 'mac');
    $token = $tokenResult['plain_token'];

    // 1. Initial upload (v1)
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/upload", [
            'path' => 'ProjectPlan.md',
            'content' => 'Initial Version 1 Content',
            'base_version' => 0,
        ])->assertCreated();

    $file = $vault->files()->where('path', 'ProjectPlan.md')->firstOrFail();
    expect($file->version)->toBe(1);
    expect($file->versions)->toHaveCount(0);

    // 2. Update note (v2)
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/upload", [
            'path' => 'ProjectPlan.md',
            'content' => 'Updated Version 2 Content',
            'base_version' => 1,
        ])->assertOk();

    $file->refresh();
    expect($file->version)->toBe(2);
    expect($file->versions)->toHaveCount(1);

    $versionRecord = $file->versions->first();
    expect($versionRecord->version)->toBe(1);
    expect(Storage::disk('local')->get($versionRecord->storage_path))->toBe('Initial Version 1 Content');
});

test('can restore historical version snapshot as active note', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Restore Test Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'MacBook Pro', 'mac');
    $token = $tokenResult['plain_token'];

    // Version 1
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/upload", [
            'path' => 'MeetingNotes.md',
            'content' => 'Original Agenda',
            'base_version' => 0,
        ]);

    // Version 2
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/upload", [
            'path' => 'MeetingNotes.md',
            'content' => 'Accidentally Overwritten Agenda',
            'base_version' => 1,
        ]);

    $file = $vault->files()->where('path', 'MeetingNotes.md')->firstOrFail();
    expect($file->getContents())->toBe('Accidentally Overwritten Agenda');

    $versionRecord = VaultFileVersion::where('vault_file_id', $file->id)->firstOrFail();
    expect($versionRecord->version)->toBe(1);

    // Perform version restore action
    $restoreAction = app(RestoreFileVersionAction::class);
    $restoredFile = $restoreAction->execute($versionRecord, $user);

    expect($restoredFile->getContents())->toBe('Original Agenda');
    expect($restoredFile->version)->toBe(3);
});
