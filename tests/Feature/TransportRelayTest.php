<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;

test('Transport relay status provides real-time sync metrics for mobile clients', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Transport Relay Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'iPhone 16 Pro', 'ios');
    $token = $tokenResult['plain_token'];

    $res = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/vaults/{$vault->slug}/transport/status");

    $res->assertOk()
        ->assertJsonStructure([
            'status',
            'vault',
            'latest_version',
            'is_e2ee',
            'active_collaborators',
            'total_files',
            'server_time',
        ])
        ->assertJson([
            'status' => 'healthy',
            'vault' => 'Transport Relay Vault',
            'is_e2ee' => false,
            'latest_version' => 0,
        ]);
});
