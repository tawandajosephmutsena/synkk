<?php

use App\Models\User;

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
