<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultChangeLog;
use App\Models\VaultFile;
use App\Services\VaultAnalyticsService;
use Livewire\Livewire;

test('vault analytics service accurately computes vault-specific content and media stats', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Test Vault',
        'slug' => 'test-vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    // Create 2 Markdown files
    VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'Daily Notes/2026-09-07.md',
        'storage_path' => 'vaults/1/notes/daily.md',
        'sha256' => hash('sha256', 'daily note content'),
        'size' => 1200,
        'is_deleted' => false,
        'version' => 1,
    ]);

    VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'Architecture/System Design.md',
        'storage_path' => 'vaults/1/notes/arch.md',
        'sha256' => hash('sha256', 'arch note content'),
        'size' => 2400,
        'is_deleted' => false,
        'version' => 1,
    ]);

    // Create image files
    VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'Assets/diagram.png',
        'storage_path' => 'vaults/1/assets/diagram.png',
        'sha256' => hash('sha256', 'png content'),
        'size' => 50000,
        'is_deleted' => false,
        'version' => 1,
    ]);

    VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'Assets/photo.jpg',
        'storage_path' => 'vaults/1/assets/photo.jpg',
        'sha256' => hash('sha256', 'jpg content'),
        'size' => 80000,
        'is_deleted' => false,
        'version' => 1,
    ]);

    // Create a deleted file (should be excluded from active stats)
    VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'Old Note.md',
        'storage_path' => 'vaults/1/notes/old.md',
        'sha256' => hash('sha256', 'old content'),
        'size' => 1000,
        'is_deleted' => true,
        'version' => 1,
    ]);

    // Create change logs
    VaultChangeLog::create([
        'vault_id' => $vault->id,
        'user_id' => $user->id,
        'action' => 'created',
        'path' => 'Architecture/System Design.md',
        'file_size' => 2400,
        'version' => 1,
    ]);

    VaultChangeLog::create([
        'vault_id' => $vault->id,
        'user_id' => $user->id,
        'action' => 'updated',
        'path' => 'Daily Notes/2026-09-07.md',
        'file_size' => 1200,
        'version' => 2,
    ]);

    $service = app(VaultAnalyticsService::class);
    $stats = $service->getVaultStats($vault, '30d');

    expect($stats['total_files'])->toBe(4)
        ->and($stats['notes_count'])->toBe(2)
        ->and($stats['images_count'])->toBe(2)
        ->and($stats['estimated_words'])->toBeGreaterThan(0)
        ->and($stats['image_breakdown']['png']['count'])->toBe(1)
        ->and($stats['image_breakdown']['jpg']['count'])->toBe(1)
        ->and($stats['period_changes'])->toBe(2)
        ->and($stats['period_creations'])->toBe(1)
        ->and($stats['period_updates'])->toBe(1);
});

test('vault analytics service accurately generates 14-day velocity timeline', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Timeline Vault',
        'slug' => 'timeline-vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    VaultChangeLog::create([
        'vault_id' => $vault->id,
        'user_id' => $user->id,
        'action' => 'created',
        'path' => 'New Doc.md',
        'version' => 1,
        'created_at' => now(),
    ]);

    $service = app(VaultAnalyticsService::class);
    $velocity = $service->getActivityVelocityTimeline($vault->id, 14);

    expect($velocity)->toBeArray()
        ->and(count($velocity))->toBe(14)
        ->and(end($velocity)['created'])->toBe(1);
});

test('vault analytics service builds collaborator leaderboard and change feed', function () {
    $user1 = User::factory()->create(['name' => 'Alice']);
    $user2 = User::factory()->create(['name' => 'Bob']);
    $team = Team::factory()->create();
    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Collab Vault',
        'slug' => 'collab-vault',
        'default_permission' => 'read_write',
        'created_by' => $user1->id,
    ]);

    VaultChangeLog::create([
        'vault_id' => $vault->id,
        'user_id' => $user1->id,
        'action' => 'created',
        'path' => 'Project Plan.md',
        'device_name' => 'MacBook Pro M3',
        'version' => 1,
    ]);

    VaultChangeLog::create([
        'vault_id' => $vault->id,
        'user_id' => $user1->id,
        'action' => 'updated',
        'path' => 'Project Plan.md',
        'device_name' => 'MacBook Pro M3',
        'version' => 2,
    ]);

    VaultChangeLog::create([
        'vault_id' => $vault->id,
        'user_id' => $user2->id,
        'action' => 'deleted',
        'path' => 'Old Draft.md',
        'device_name' => 'iPhone 15',
        'version' => 1,
    ]);

    $service = app(VaultAnalyticsService::class);
    $leaderboard = $service->getContributorLeaderboard($vault->id, '30d');

    expect($leaderboard)->toHaveCount(2)
        ->and($leaderboard[0]['user_name'])->toBe('Alice')
        ->and($leaderboard[0]['mutations_count'])->toBe(2)
        ->and($leaderboard[1]['user_name'])->toBe('Bob')
        ->and($leaderboard[1]['mutations_count'])->toBe(1);

    // Test filtered feed
    $createdFeed = $service->getRecentChangeFeed($vault->id, 'created');
    expect($createdFeed)->toHaveCount(1)
        ->and($createdFeed->first()->path)->toBe('Project Plan.md');

    $searchedFeed = $service->getRecentChangeFeed($vault->id, null, null, 'Draft');
    expect($searchedFeed)->toHaveCount(1)
        ->and($searchedFeed->first()->path)->toBe('Old Draft.md');
});

test('super admin can switch to vault intelligence analytics tab and filter data', function () {
    $admin = User::factory()->asSuperAdmin()->create();
    $team = Team::factory()->create();
    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Research Vault',
        'slug' => 'research-vault',
        'default_permission' => 'read_write',
        'created_by' => $admin->id,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->call('setTab', 'analytics')
        ->assertSet('activeTab', 'analytics')
        ->assertSee('Vault Intelligence')
        ->assertSee('Scope & Target:')
        ->assertSee('Research Vault')
        ->set('selectedAnalyticsVaultId', $vault->id)
        ->set('analyticsTimeframe', '7d')
        ->set('analyticsActionFilter', 'created')
        ->assertOk();
});

test('workspace members can access analytics tab on vault show page', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Dev Docs',
        'slug' => 'dev-docs',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'Readme.md',
        'storage_path' => 'vaults/1/notes/readme.md',
        'sha256' => hash('sha256', 'readme content'),
        'size' => 1500,
        'is_deleted' => false,
        'version' => 1,
    ]);

    Livewire::actingAs($user)
        ->test('pages::vaults.show', ['vault' => $vault])
        ->set('activeTab', 'analytics')
        ->assertSee('Vault Intelligence & Deep Analytics')
        ->assertSee('Notes & Knowledge')
        ->assertSee('14-Day Vault Mutation Velocity')
        ->assertSee('Vault File Composition')
        ->assertOk();
});
