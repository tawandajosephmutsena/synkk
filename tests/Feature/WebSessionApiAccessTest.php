<?php

use App\Enums\TeamRole;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Services\PlanService;
use Livewire\Livewire;

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

test('browser access does not consume a device slot or show as a connected device', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();
    $team->update(['max_devices' => 1]);

    $this->actingAs($user, 'web')
        ->getJson('/api/v1/auth/verify')
        ->assertOk();

    $planService = app(PlanService::class);
    expect($team->deviceTokens()->count())->toBe(1)
        ->and($planService->getUsageSummary($team)['devices']['used'])->toBe(0)
        ->and($planService->canAddDevice($team))->toBeTrue();

    Livewire::actingAs($user)
        ->test('pages::devices.index', ['current_team' => $team->slug])
        ->assertDontSee('Web Browser Session')
        ->set('pairingMethod', 'token')
        ->set('deviceName', 'My laptop')
        ->set('devicePlatform', 'mac')
        ->call('generateToken')
        ->assertHasNoErrors()
        ->assertSee('My laptop');

    expect($team->deviceTokens()->userDevices()->count())->toBe(1)
        ->and($planService->getUsageSummary($team)['devices']['used'])->toBe(1)
        ->and($planService->canAddDevice($team))->toBeFalse();
});

test('web session tokens are distinct for each team and reused within a team', function () {
    $user = User::factory()->create();
    $firstTeam = $user->personalTeam();
    $secondTeam = Team::factory()->create();
    $secondTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $this->actingAs($user, 'web')
        ->getJson('/api/v1/auth/verify')
        ->assertOk()
        ->assertJsonPath('team.id', $firstTeam->id);

    $user->switchTeam($secondTeam);

    $this->actingAs($user, 'web')
        ->getJson('/api/v1/auth/verify')
        ->assertOk()
        ->assertJsonPath('team.id', $secondTeam->id);

    $this->getJson('/api/v1/auth/verify')->assertOk();

    $webSessions = DeviceToken::query()
        ->where('user_id', $user->id)
        ->where('token_preview', DeviceToken::WEB_SESSION_TOKEN_PREVIEW)
        ->get();

    expect($webSessions)->toHaveCount(2)
        ->and($webSessions->pluck('team_id')->all())->toContain($firstTeam->id, $secondTeam->id)
        ->and($webSessions->pluck('token_hash')->unique())->toHaveCount(2);
});
