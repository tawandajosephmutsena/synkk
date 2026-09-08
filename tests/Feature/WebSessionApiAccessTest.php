<?php

use App\Models\User;
use App\Models\Vault;

test('web user session can access API auth verification without a Bearer token', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->actingAs($user, 'web')
        ->getJson('/api/v1/auth/verify');

    $response->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('team.id', $team->id)
        ->assertJsonPath('device.name', 'Web Browser Session');
});

test('web user session can access CRDT collaboration join without a Bearer token', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Collab Web Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user, 'web')
        ->postJson("/api/v1/vaults/{$vault->slug}/collab/join", [
            'path' => 'WebNote.md',
            'peer_id' => 'browser_client_1',
        ]);

    $response->assertOk()
        ->assertJsonPath('status', 'joined')
        ->assertJsonStructure(['room_id', 'latest_sequence', 'peers']);
});
