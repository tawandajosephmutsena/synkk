<?php

use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultFile;
use Illuminate\Support\Facades\Storage;

it('prunes soft-deleted vault files older than retention threshold', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Demo Vault',
        'slug' => 'demo-vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $oldStoragePath = "vaults/{$vault->id}/old_file.md";
    $recentStoragePath = "vaults/{$vault->id}/recent_file.md";
    $activeStoragePath = "vaults/{$vault->id}/active_file.md";

    Storage::disk('local')->put($oldStoragePath, 'Old deleted content');
    Storage::disk('local')->put($recentStoragePath, 'Recently deleted content');
    Storage::disk('local')->put($activeStoragePath, 'Active content');

    // 1. Old deleted file (35 days old) with historical version
    $oldFile = VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'old.md',
        'storage_path' => $oldStoragePath,
        'sha256' => hash('sha256', 'Old deleted content'),
        'size' => 19,
        'version' => 2,
        'is_deleted' => true,
        'last_modified_by' => $user->id,
    ]);
    $oldFile->timestamps = false;
    $oldFile->updated_at = now()->subDays(35);
    $oldFile->save();

    $oldVersionStoragePath = "vaults/{$vault->id}/versions/old_v1.md";
    Storage::disk('local')->put($oldVersionStoragePath, 'Old v1 historical content');
    $oldFile->versions()->create([
        'vault_id' => $vault->id,
        'version' => 1,
        'storage_path' => $oldVersionStoragePath,
        'sha256' => hash('sha256', 'Old v1 historical content'),
        'size' => 25,
        'created_by' => $user->id,
    ]);

    // 2. Recently deleted file (10 days old)
    $recentFile = VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'recent.md',
        'storage_path' => $recentStoragePath,
        'sha256' => hash('sha256', 'Recently deleted content'),
        'size' => 24,
        'version' => 1,
        'is_deleted' => true,
        'last_modified_by' => $user->id,
    ]);
    $recentFile->timestamps = false;
    $recentFile->updated_at = now()->subDays(10);
    $recentFile->save();

    // 3. Active file
    $activeFile = VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'active.md',
        'storage_path' => $activeStoragePath,
        'sha256' => hash('sha256', 'Active content'),
        'size' => 14,
        'version' => 1,
        'is_deleted' => false,
        'last_modified_by' => $user->id,
    ]);
    $activeFile->timestamps = false;
    $activeFile->updated_at = now()->subDays(35);
    $activeFile->save();

    $this->artisan('vaults:prune-deleted', ['--days' => 30])
        ->expectsOutputToContain('Successfully pruned 1 deleted vault file(s)')
        ->assertSuccessful();

    expect(VaultFile::find($oldFile->id))->toBeNull()
        ->and(Storage::disk('local')->exists($oldStoragePath))->toBeFalse()
        ->and(Storage::disk('local')->exists($oldVersionStoragePath))->toBeFalse()
        ->and(VaultFile::find($recentFile->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists($recentStoragePath))->toBeTrue()
        ->and(VaultFile::find($activeFile->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists($activeStoragePath))->toBeTrue();
});
