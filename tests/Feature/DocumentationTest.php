<?php

use App\Models\Team;
use App\Models\User;

test('the public documentation page renders with full step by step sections and landing branding', function () {
    $response = $this->get(route('public.docs'));

    $response
        ->assertOk()
        ->assertSee('Documentation — Synkk Obsidian Sync')
        ->assertSee('Mastering Synkk.')
        ->assertSee('Step by step.')
        ->assertSee('Quickstart Guide')
        ->assertSee('Plugin Installation &amp; Setup', escape: false)
        ->assertSee('Core Architecture &amp; Storage Engine', escape: false)
        ->assertSee('Roles &amp; Granular Path Permissions', escape: false)
        ->assertSee('Safety Shield &amp; Data Loss Protection', escape: false)
        ->assertSee('REST API Reference')
        ->assertSee('Docker &amp; Self-Hosting Guide', escape: false)
        ->assertSee('Ecosystem Roadmap')
        ->assertSee('/images/synkk-logo.svg', escape: false);
});

test('docs redirect sends traffic to the public documentation page', function () {
    $response = $this->get(route('docs.redirect'));

    $response->assertRedirect(route('public.docs'));
});

test('the landing page documentation links point to the public documentation page with section anchors', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSee('href="'.route('public.docs').'"', escape: false)
        ->assertSee('href="'.route('public.docs').'#architecture"', escape: false)
        ->assertSee('href="'.route('public.docs').'#quickstart"', escape: false)
        ->assertSee('href="'.route('public.docs').'#permissions"', escape: false)
        ->assertSee('href="'.route('public.docs').'#safety-shield"', escape: false)
        ->assertSee('href="'.route('public.docs').'#docker"', escape: false);
});

test('the authenticated in-app documentation page renders with migration and portal sections', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);

    $response = $this->actingAs($user)->get(route('docs', ['current_team' => $team->slug]));

    $response
        ->assertOk()
        ->assertSee('Synkk Documentation')
        ->assertSee('Pre-Flight')
        ->assertSee('Livewire Vault Portals');
});
