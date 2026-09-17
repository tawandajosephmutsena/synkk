<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;

test('read requests do not create missing vaults', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);

    $tokenResult = DeviceToken::createToken($user, $team, 'Test Mobile');
    $plainToken = $tokenResult['plain_token'];

    expect(Vault::where('team_id', $team->id)->where('slug', 'brain01')->exists())->toBeFalse();

    $this->withToken($plainToken)
        ->getJson('/api/v1/vaults/brain01/manifest')
        ->assertNotFound();

    expect(Vault::where('team_id', $team->id)->where('slug', 'brain01')->exists())->toBeFalse();
});

test('vault route binding is scoped to the device token team', function () {
    $user = User::factory()->create();
    $firstTeam = Team::factory()->create();
    $secondTeam = Team::factory()->create();
    $firstTeam->members()->attach($user, ['role' => 'owner']);
    $secondTeam->members()->attach($user, ['role' => 'owner']);

    $firstVault = Vault::create([
        'team_id' => $firstTeam->id,
        'name' => 'Shared Name',
        'slug' => 'shared-name',
        'created_by' => $user->id,
    ]);
    Vault::create([
        'team_id' => $secondTeam->id,
        'name' => 'Shared Name',
        'slug' => 'shared-name',
        'created_by' => $user->id,
    ]);

    $plainToken = DeviceToken::createToken($user, $firstTeam, 'First Team Device')['plain_token'];

    $this->withToken($plainToken)
        ->getJson('/api/v1/vaults/shared-name/manifest')
        ->assertOk()
        ->assertJsonPath('vault.id', $firstVault->id);
});

test('listing an empty team does not create a demo vault', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $plainToken = DeviceToken::createToken($user, $team, 'Empty Team Device')['plain_token'];

    $this->withToken($plainToken)
        ->getJson('/api/v1/vaults')
        ->assertOk()
        ->assertJsonPath('vaults', []);

    expect(Vault::where('team_id', $team->id)->exists())->toBeFalse();
});
