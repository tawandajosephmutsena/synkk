<?php

use App\Models\Team;
use App\Models\User;
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
        ->assertSee('Multi-Tenant SaaS Control');
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
