<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('team owner can view plan quotas on team edit page', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'free']);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $this->actingAs($user)
        ->get(route('teams.edit', $team))
        ->assertOk()
        ->assertSee('Plan & Subscription')
        ->assertSee('Free CE')
        ->assertSee('Storage')
        ->assertSee('Vaults')
        ->assertSee('Connected Devices');
});

test('team owner can redeem a synk pro license key and upgrade workspace', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'free']);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    Livewire::actingAs($user)
        ->test('pages::teams.edit', ['team' => $team])
        ->set('licenseKeyInput', 'SYNK-PRO-A1B2-C3D4-E5F6')
        ->call('redeemLicense')
        ->assertHasNoErrors();

    $fresh = $team->fresh();
    expect($fresh->plan)->toBe('pro_ltd')
        ->and($fresh->license_status)->toBe('active')
        ->and($fresh->license_key)->toBe('SYNK-PRO-A1B2-C3D4-E5F6');
});

test('team owner can redeem a synk cloud license key and upgrade workspace', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'free']);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    Livewire::actingAs($user)
        ->test('pages::teams.edit', ['team' => $team])
        ->set('licenseKeyInput', 'SYNK-CLOUD-8888-9999-0000')
        ->call('redeemLicense')
        ->assertHasNoErrors();

    $fresh = $team->fresh();
    expect($fresh->plan)->toBe('cloud')
        ->and($fresh->license_status)->toBe('active')
        ->and($fresh->license_key)->toBe('SYNK-CLOUD-8888-9999-0000');
});

test('team member cannot redeem license key on team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'free']);

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    Livewire::actingAs($member)
        ->test('pages::teams.edit', ['team' => $team])
        ->set('licenseKeyInput', 'SYNK-PRO-A1B2-C3D4-E5F6')
        ->call('redeemLicense')
        ->assertForbidden();

    expect($team->fresh()->plan)->toBe('free');
});

test('redeeming duplicate active license key returns error', function () {
    $owner1 = User::factory()->create();
    $owner2 = User::factory()->create();
    $team1 = Team::factory()->create(['plan' => 'free']);
    $team2 = Team::factory()->create(['plan' => 'free']);

    $team1->members()->attach($owner1, ['role' => TeamRole::Owner->value]);
    $team2->members()->attach($owner2, ['role' => TeamRole::Owner->value]);

    Livewire::actingAs($owner1)
        ->test('pages::teams.edit', ['team' => $team1])
        ->set('licenseKeyInput', 'SYNK-PRO-DUPL-KEY1-2222')
        ->call('redeemLicense')
        ->assertHasNoErrors();

    Livewire::actingAs($owner2)
        ->test('pages::teams.edit', ['team' => $team2])
        ->set('licenseKeyInput', 'SYNK-PRO-DUPL-KEY1-2222')
        ->call('redeemLicense')
        ->assertHasErrors(['licenseKeyInput']);

    expect($team2->fresh()->plan)->toBe('free');
});

test('team owner can deactivate license and revert to free tier', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create([
        'plan' => 'pro_ltd',
        'license_key' => 'SYNK-PRO-REVOKE-9999',
        'license_status' => 'active',
    ]);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    Livewire::actingAs($user)
        ->test('pages::teams.edit', ['team' => $team])
        ->call('deactivateLicense')
        ->assertHasNoErrors();

    $fresh = $team->fresh();
    expect($fresh->plan)->toBe('free')
        ->and($fresh->license_status)->toBe('revoked');
});
