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
