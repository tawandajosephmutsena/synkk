<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;

test('automatically creates missing vault on-demand when requested by an authenticated device token', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);

    $tokenResult = DeviceToken::createToken($user, $team, 'Test Mobile');
    $plainToken = $tokenResult['plain_token'];

    // Verify vault brain01 does not exist initially
    expect(Vault::where('team_id', $team->id)->where('slug', 'brain01')->exists())->toBeFalse();

    // Call manifest endpoint for vault brain01
    $response = $this->withToken($plainToken)
        ->getJson('/api/v1/vaults/brain01/manifest');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
            'vault' => [
                'slug' => 'brain01',
            ],
        ]);

    // Verify vault brain01 was automatically created for the team
    $createdVault = Vault::where('team_id', $team->id)->where('slug', 'brain01')->first();
    expect($createdVault)->not()->toBeNull()
        ->and($createdVault->name)->toBe('Brain01')
        ->and($createdVault->created_by)->toBe($user->id);
});
