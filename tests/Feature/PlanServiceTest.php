<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultFile;
use App\Services\PlanService;
use Livewire\Livewire;

test('default free plan has expected limits and features', function () {
    $planService = app(PlanService::class);
    $team = Team::factory()->create(['plan' => 'free']);

    expect($planService->getDeviceLimit($team))->toBe(3)
        ->and($planService->getVaultLimit($team))->toBe(1)
        ->and($planService->getMemberLimit($team))->toBe(3)
        ->and($planService->getStorageLimitMb($team))->toBe(1000)
        ->and($planService->hasFeature($team, 'basic_sync'))->toBeTrue()
        ->and($planService->hasFeature($team, 'dlp_scan'))->toBeFalse()
        ->and($planService->hasFeature($team, 'crdt_multiplayer'))->toBeFalse();
});

test('pro ltd and cloud plans unlock advanced features and higher limits', function () {
    $planService = app(PlanService::class);
    $proTeam = Team::factory()->create(['plan' => 'pro_ltd']);
    $cloudTeam = Team::factory()->create(['plan' => 'cloud']);

    expect($planService->getDeviceLimit($proTeam))->toBe(25)
        ->and($planService->getVaultLimit($proTeam))->toBe(15)
        ->and($planService->hasFeature($proTeam, 'dlp_scan'))->toBeTrue()
        ->and($planService->hasFeature($proTeam, 'path_acls'))->toBeTrue()
        ->and($planService->getDeviceLimit($cloudTeam))->toBe(100)
        ->and($planService->getVaultLimit($cloudTeam))->toBe(50)
        ->and($planService->hasFeature($cloudTeam, 'crdt_multiplayer'))->toBeTrue();
});

test('custom team limits override plan defaults', function () {
    $planService = app(PlanService::class);
    $team = Team::factory()->create([
        'plan' => 'free',
        'max_devices' => 10,
        'max_vaults' => 5,
        'storage_limit_mb' => 20000,
    ]);

    expect($planService->getDeviceLimit($team))->toBe(10)
        ->and($planService->getVaultLimit($team))->toBe(5)
        ->and($planService->getStorageLimitMb($team))->toBe(20000);
});

test('suspended team is denied all actions regardless of plan', function () {
    $planService = app(PlanService::class);
    $team = Team::factory()->create([
        'plan' => 'cloud',
        'status' => 'suspended',
    ]);

    expect($planService->canAddDevice($team))->toBeFalse()
        ->and($planService->canCreateVault($team))->toBeFalse()
        ->and($planService->canInviteMember($team))->toBeFalse()
        ->and($planService->canUploadStorage($team))->toBeFalse()
        ->and($planService->hasFeature($team, 'crdt_multiplayer'))->toBeFalse();
});

test('dashboard prevents creating more vaults than allowed by the plan', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();
    $team->update(['plan' => 'free', 'max_vaults' => 1]);
    $user->refresh();

    // Create 1 vault (the allowed limit)
    Vault::create([
        'team_id' => $team->id,
        'name' => 'First Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index', ['current_team' => $team->slug])
        ->set('vaultName', 'Second Vault')
        ->set('vaultDefaultPermission', 'read_write')
        ->call('createVault');

    // Should still only have 1 vault
    expect($team->vaults()->count())->toBe(1);
});

test('devices page prevents adding devices beyond plan limit', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();
    $team->update(['plan' => 'free', 'max_devices' => 1]);
    $user->refresh();

    // Create 1 device token (the allowed limit)
    DeviceToken::createToken($user, $team, 'Device 1', 'mac');

    Livewire::actingAs($user)
        ->test('pages::devices.index', ['current_team' => $team->slug])
        ->set('deviceName', 'Device 2')
        ->set('devicePlatform', 'mac')
        ->set('accessScope', 'full_access')
        ->call('generateToken');

    // Should still only have 1 device token
    expect($team->deviceTokens()->count())->toBe(1);
});

test('sync upload endpoint rejects upload if storage quota is exceeded', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();
    $team->update([
        'plan' => 'free',
        'storage_limit_mb' => 1, // 1 MB limit
    ]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Quota Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);
    $tokenResult = DeviceToken::createToken($user, $team, 'Test Device', 'mac');

    // Create a dummy file taking up 1.5 MB in database to exceed quota
    VaultFile::create([
        'vault_id' => $vault->id,
        'path' => 'large.dat',
        'storage_path' => 'vaults/test/large.dat',
        'size' => (int) (1.5 * 1024 * 1024),
        'sha256' => hash('sha256', 'dummy'),
        'version' => 1,
        'is_deleted' => false,
        'last_modified_by' => $user->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$tokenResult['plain_token'],
    ])->postJson("/api/v1/vaults/{$vault->slug}/upload", [
        'path' => 'notes/new.md',
        'content' => 'Sample note content',
    ]);

    $response->assertStatus(402)
        ->assertJson([
            'error' => 'Quota Exceeded',
            'code' => 'STORAGE_QUOTA_EXCEEDED',
        ]);
});

test('sync upload endpoint rejects requests for suspended teams', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();
    $team->update([
        'status' => 'suspended',
    ]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Suspended Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);
    $tokenResult = DeviceToken::createToken($user, $team, 'Test Device', 'mac');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$tokenResult['plain_token'],
    ])->postJson("/api/v1/vaults/{$vault->slug}/upload", [
        'path' => 'notes/new.md',
        'content' => 'Sample note content',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'Team Suspended',
        ]);
});

test('free plan prevents configuring custom path permission rules', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();
    $team->update(['plan' => 'free']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Free Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::vaults.show', ['vault' => $vault])
        ->set('rulePath', 'confidential/')
        ->set('rulePermission', 'hidden')
        ->call('addPathPermission');

    expect($vault->permissions()->count())->toBe(0);

    // Switch team to pro_ltd and verify it now succeeds
    $team->update(['plan' => 'pro_ltd']);
    $user->refresh();

    Livewire::actingAs($user)
        ->test('pages::vaults.show', ['vault' => $vault])
        ->set('rulePath', 'confidential/')
        ->set('rulePermission', 'hidden')
        ->call('addPathPermission')
        ->assertHasNoErrors();

    expect($vault->permissions()->count())->toBe(1);
});

test('free plan prevents configuring ip subnet whitelist and read only device tokens', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();
    $team->update(['plan' => 'free']);

    // Attempt IP whitelisting on free plan
    Livewire::actingAs($user)
        ->test('pages::devices.index', ['current_team' => $team->slug])
        ->set('deviceName', 'Guarded Mac')
        ->set('devicePlatform', 'mac')
        ->set('allowedIpSubnets', '192.168.1.*')
        ->call('generateToken')
        ->assertHasErrors(['allowedIpSubnets']);

    // Attempt Read-Only token on free plan
    Livewire::actingAs($user)
        ->test('pages::devices.index', ['current_team' => $team->slug])
        ->set('deviceName', 'Contractor Mac')
        ->set('devicePlatform', 'mac')
        ->set('accessScope', 'read_only')
        ->call('generateToken')
        ->assertHasErrors(['accessScope']);

    // Upgrade to pro_ltd and verify both now succeed
    $team->update(['plan' => 'pro_ltd']);
    $user->refresh();

    Livewire::actingAs($user)
        ->test('pages::devices.index', ['current_team' => $team->slug])
        ->set('deviceName', 'Pro Guarded Mac')
        ->set('devicePlatform', 'mac')
        ->set('accessScope', 'read_only')
        ->set('allowedIpSubnets', '10.0.0.*')
        ->call('generateToken')
        ->assertHasNoErrors();

    expect($team->deviceTokens()->count())->toBe(1);
});

test('free plan prevents triggering remote wipe on devices', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();
    $team->update(['plan' => 'free']);

    $tokenResult = DeviceToken::createToken($user, $team, 'Test Device', 'mac');
    $token = $tokenResult['device_token'];

    Livewire::actingAs($user)
        ->test('pages::devices.index', ['current_team' => $team->slug])
        ->call('triggerRemoteWipe', $token->id);

    expect($token->fresh()->is_wiped)->toBeFalse();

    // Upgrade to pro_ltd and verify remote wipe triggers
    $team->update(['plan' => 'pro_ltd']);
    $user->refresh();

    Livewire::actingAs($user)
        ->test('pages::devices.index', ['current_team' => $team->slug])
        ->call('triggerRemoteWipe', $token->id);

    expect($token->fresh()->is_wiped)->toBeTrue();
});
