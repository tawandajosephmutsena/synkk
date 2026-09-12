<?php

use App\Models\User;
use App\Models\Vault;
use App\Models\VaultChangeLog;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSee('Dashboard - Synkk');
});

test('authenticated users can read the expanded documentation guidance', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('docs', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertSee('Choose what each device carries')
        ->assertSee('images/synkk-logo.svg', escape: false);

    Livewire::test('pages::docs.index')
        ->assertSee('Choose what each device carries')
        ->call('setSection', 'mobile')
        ->assertSee('Background execution is not guaranteed')
        ->call('setSection', 'conflict')
        ->assertSee('How to review a conflict')
        ->call('setSection', 'docker')
        ->assertSee('Before calling the deployment production-ready');
});

test('dashboard copy describes the foundation sync capabilities accurately', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertSee('Sync your vault notes across trusted devices.')
        ->assertSee('Recent Obsidian note revisions and sync operations')
        ->assertDontSee('real-time', escape: false)
        ->assertDontSee('zero-knowledge', escape: false)
        ->assertDontSee('encrypted disk', escape: false);
});

test('authenticated pages are not forced into dark mode', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('<html lang="en" class="dark">', escape: false);
});

test('dashboard links vaults and recent note activities directly to markdown editor', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Marketing Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    VaultChangeLog::create([
        'vault_id' => $vault->id,
        'user_id' => $user->id,
        'device_name' => 'MacBook Pro',
        'path' => 'Campaigns/Launch2026.md',
        'action' => 'updated',
        'version' => 1,
        'size' => 1024,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]));

    $response
        ->assertOk()
        ->assertSee(route('vaults.show', ['vault' => $vault->slug, 'tab' => 'editor']))
        ->assertSee(route('vaults.show', ['vault' => $vault->slug, 'tab' => 'editor', 'path' => 'Campaigns/Launch2026.md']))
        ->assertSee('Campaigns/Launch2026.md');
});

test('sidebar user card renders team and settings dropdown and removes bottom user menu', function () {
    $user = User::factory()->create(['name' => 'Alice Walker']);
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]));

    $response
        ->assertOk()
        ->assertSee('Alice Walker')
        ->assertSee(route('profile.edit'))
        ->assertSee(route('security.edit'))
        ->assertSee(route('teams.index'))
        ->assertSee(route('appearance.edit'))
        ->assertDontSee('data-test="sidebar-menu-button"', escape: false);
});

test('authenticated user can redeem license key directly from dashboard', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();
    $team->update(['plan' => 'free']);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index', ['current_team' => $team->slug])
        ->set('upgradeLicenseKey', 'SYNK-PRO-DASH-TEST-1234')
        ->call('redeemLicenseKeyInDashboard')
        ->assertHasNoErrors();

    expect($team->fresh()->plan)->toBe('pro_ltd')
        ->and($team->fresh()->license_status)->toBe('active')
        ->and($team->fresh()->license_key)->toBe('SYNK-PRO-DASH-TEST-1234');
});

test('dashboard provides interactive audit threats modal when DLP security alerts are detected', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Security Audit Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    VaultChangeLog::create([
        'vault_id' => $vault->id,
        'user_id' => $user->id,
        'device_name' => 'MacBook Pro M3',
        'path' => 'Secrets/API_Keys.md',
        'action' => 'updated',
        'version' => 1,
        'size' => 512,
        'has_secrets' => true,
        'detected_secrets' => ['AWS Access Key', 'OpenAI API Key'],
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index', ['current_team' => $team->slug])
        ->assertSee('Audit Threats')
        ->assertSee('DLP Security Threats Audit')
        ->assertSee('Secrets/API_Keys.md')
        ->assertSee('AWS Access Key')
        ->assertSee('OpenAI API Key')
        ->assertSee('MacBook Pro M3');
});
