<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultChangeLog;
use App\Services\PlanService;
use Livewire\Livewire;

test('guests are redirected to login when attempting to access super admin dashboard', function () {
    $this->get('/admin')
        ->assertRedirect(route('login'));
});

test('regular users receive 403 forbidden when accessing super admin dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('super admins can successfully view the super admin dashboard', function () {
    $admin = User::factory()->asSuperAdmin()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Synkk Platform Super Admin')
        ->assertSee('Multi-Tenant SaaS Control')
        ->assertSee('$79 LTD')
        ->assertSee('$12/mo');
});

test('super admin livewire component can update a tenant plan', function () {
    $admin = User::factory()->asSuperAdmin()->create();
    $team = Team::factory()->create(['plan' => 'free']);

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->call('updateTenantPlan', $team->id, 'pro_ltd');

    expect($team->fresh()->plan)->toBe('pro_ltd');
});

test('super admin livewire component can toggle tenant active or suspended status', function () {
    $admin = User::factory()->asSuperAdmin()->create();
    $team = Team::factory()->create(['status' => 'active']);

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->call('toggleTenantStatus', $team->id);

    expect($team->fresh()->status)->toBe('suspended');

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->call('toggleTenantStatus', $team->id);

    expect($team->fresh()->status)->toBe('active');
});

test('super admin livewire component can set custom quota overrides on a tenant', function () {
    $admin = User::factory()->asSuperAdmin()->create();
    $team = Team::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->call('openQuotaModal', $team->id)
        ->set('editStorageLimitMb', 25000)
        ->set('editMaxDevices', 50)
        ->set('editMaxVaults', 20)
        ->set('editMaxMembers', 15)
        ->call('saveQuotas');

    $fresh = $team->fresh();
    expect($fresh->storage_limit_mb)->toBe(25000)
        ->and($fresh->max_devices)->toBe(50)
        ->and($fresh->max_vaults)->toBe(20)
        ->and($fresh->max_members)->toBe(15);
});

test('super admin livewire component can generate and activate a commercial license key', function () {
    $admin = User::factory()->asSuperAdmin()->create();
    $team = Team::factory()->create();

    $component = Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->set('newLicenseTier', 'pro_ltd')
        ->call('generateLicenseKey');

    $generatedKey = $component->get('generatedKey');
    expect($generatedKey)->toStartWith('SYNK-PRO-');

    $component->call('activateLicenseOnTeam', $team->id);

    $fresh = $team->fresh();
    expect($fresh->license_key)->toBe($generatedKey)
        ->and($fresh->license_status)->toBe('active')
        ->and($fresh->plan)->toBe('pro_ltd');
});

test('super admin can impersonate a tenant user and return back safely', function () {
    $admin = User::factory()->asSuperAdmin()->create(['name' => 'Admin Operator']);
    $user = User::factory()->create(['name' => 'Tenant Client']);

    // Start impersonation
    $response = $this->actingAs($admin)
        ->post(route('admin.impersonate', $user));

    $response->assertSessionHas('impersonator_id', $admin->id);
    expect(auth()->id())->toBe($user->id);

    // Stop impersonation
    $exitResponse = $this->post(route('admin.stop-impersonation'));
    $exitResponse->assertRedirect(route('admin.dashboard'));
    expect(auth()->id())->toBe($admin->id);
    expect(session()->has('impersonator_id'))->toBeFalse();
});

test('super admin cannot impersonate themselves', function () {
    $admin = User::factory()->asSuperAdmin()->create();

    $this->actingAs($admin)
        ->post(route('admin.impersonate', $admin))
        ->assertSessionHas('error');

    expect(session()->has('impersonator_id'))->toBeFalse();
});

test('super admin livewire component can generate a batch of commercial license keys', function () {
    $admin = User::factory()->asSuperAdmin()->create();

    $component = Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->set('newLicenseTier', 'pro_ltd')
        ->set('batchLicenseCount', 5)
        ->call('generateBatchLicenses');

    $batch = $component->get('batchGeneratedKeys');
    expect($batch)->toHaveCount(5);
    foreach ($batch as $key) {
        expect($key)->toStartWith('SYNK-PRO-');
    }
});

test('super admin can inspect tenant and execute emergency device revocation', function () {
    $admin = User::factory()->asSuperAdmin()->create();
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $dev1 = DeviceToken::createToken($user, $team, 'MacBook', 'mac');
    $dev2 = DeviceToken::createToken($user, $team, 'iPhone', 'ios');

    expect($team->deviceTokens()->where('is_wiped', false)->count())->toBe(2);

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->call('inspectTenant', $team->id)
        ->call('revokeAllTenantDevices', $team->id);

    expect($team->deviceTokens()->where('is_wiped', false)->count())->toBe(0);
});

test('super admin can view sync telemetry and dismiss dlp security alerts', function () {
    $admin = User::factory()->asSuperAdmin()->create();
    $user = User::factory()->create();
    $team = $user->personalTeam();
    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Audit Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $log = VaultChangeLog::create([
        'vault_id' => $vault->id,
        'user_id' => $user->id,
        'device_name' => 'MacBook Pro',
        'path' => 'secrets.env',
        'action' => 'updated',
        'version' => 1,
        'size' => 1024,
        'has_secrets' => true,
        'detected_secrets' => ['AWS Access Key'],
    ]);

    $component = Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->set('activeTab', 'telemetry')
        ->set('telemetrySecretsOnly', true);

    expect($component->get('stats')['dlp_alerts'])->toBe(1);

    $component->call('dismissDlpAlert', $log->id);

    expect($log->fresh()->has_secrets)->toBeFalse();
});

test('super admin can trigger cache clear and snapshot pruning operations', function () {
    $admin = User::factory()->asSuperAdmin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->set('activeTab', 'system')
        ->call('clearApplicationCache')
        ->call('pruneOldSnapshots')
        ->assertOk();
});

test('super admin teams receive unlimited quotas and bypass all plan limits in PlanService', function () {
    $admin = User::factory()->asSuperAdmin()->create();
    $team = $admin->personalTeam();
    $planService = app(PlanService::class);

    expect($planService->isSuperAdminTeam($team))->toBeTrue()
        ->and($planService->canAddDevice($team))->toBeTrue()
        ->and($planService->canCreateVault($team))->toBeTrue()
        ->and($planService->canInviteMember($team))->toBeTrue()
        ->and($planService->canUploadStorage($team, 999999999))->toBeTrue()
        ->and($planService->hasFeature($team, 'cloud_relay'))->toBeTrue()
        ->and($planService->getPlanConfig($team)['name'])->toBe('Super Admin Unlimited');

    $usage = $planService->getUsageSummary($team);
    expect($usage['is_superadmin'])->toBeTrue();
});

test('super admin can upgrade all user workspaces at once from user management', function () {
    $admin = User::factory()->asSuperAdmin()->create();
    $user = User::factory()->create();
    $team1 = $user->personalTeam();
    $team1->update(['plan' => 'free']);

    expect($team1->fresh()->plan)->toBe('free');

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->set('activeTab', 'users')
        ->call('upgradeUserWorkspaces', $user->id, 'pro_ltd');

    expect($team1->fresh()->plan)->toBe('pro_ltd');
});
