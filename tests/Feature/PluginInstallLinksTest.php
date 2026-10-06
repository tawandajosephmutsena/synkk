<?php

use App\Models\User;

test('public install guidance points to the current plugin release', function () {
    $this->get('/documentation')
        ->assertOk()
        ->assertSee('https://github.com/tawandajosephmutsena/synk-obsidian-plugin/releases/latest', false)
        ->assertDontSee('/releases/tag/1.0.0', false);
});

test('signed-in device page links directly to the latest plugin release', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $this->actingAs($user)
        ->get("/{$team->slug}/devices")
        ->assertOk()
        ->assertSee('https://github.com/tawandajosephmutsena/synk-obsidian-plugin/releases/latest', false);
});
