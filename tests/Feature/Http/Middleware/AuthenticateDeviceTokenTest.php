<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;

it('does not write another heartbeat within one minute for the same device metadata', function () {
    $this->travelTo('2026-09-04 10:00:00');

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $tokenResult = DeviceToken::createToken($user, $team, 'Studio Mac', 'mac');
    $deviceToken = $tokenResult['device_token'];
    $deviceToken->update([
        'last_used_at' => now(),
        'last_ip' => '203.0.113.10',
    ]);

    $this->travelTo('2026-09-04 10:00:30');

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeaders(['X-Client-Platform' => 'mac'])
        ->withToken($tokenResult['plain_token'])
        ->getJson('/api/v1/auth/verify');

    $response->assertOk()
        ->assertJsonPath('device.last_used_at', '2026-09-04T10:00:00+00:00');

    $deviceToken->refresh();

    expect($deviceToken->last_used_at?->toDateTimeString())->toBe('2026-09-04 10:00:00')
        ->and($deviceToken->updated_at?->toDateTimeString())->toBe('2026-09-04 10:00:00');
});

it('refreshes the heartbeat once one minute has elapsed', function () {
    $this->travelTo('2026-09-04 10:00:00');

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $tokenResult = DeviceToken::createToken($user, $team, 'Studio Mac', 'mac');
    $deviceToken = $tokenResult['device_token'];
    $deviceToken->update([
        'last_used_at' => now(),
        'last_ip' => '203.0.113.10',
    ]);

    $this->travelTo('2026-09-04 10:01:00');

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeaders(['X-Client-Platform' => 'mac'])
        ->withToken($tokenResult['plain_token'])
        ->getJson('/api/v1/auth/verify');

    $response->assertOk()
        ->assertJsonPath('device.last_used_at', '2026-09-04T10:01:00+00:00');

    $deviceToken->refresh();

    expect($deviceToken->last_used_at?->toDateTimeString())->toBe('2026-09-04 10:01:00');
});

it('updates the heartbeat immediately when the request IP changes', function () {
    $this->travelTo('2026-09-04 10:00:00');

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $tokenResult = DeviceToken::createToken($user, $team, 'Studio Mac', 'mac');
    $deviceToken = $tokenResult['device_token'];
    $deviceToken->update([
        'last_used_at' => now(),
        'last_ip' => '203.0.113.10',
    ]);

    $this->travelTo('2026-09-04 10:00:15');

    $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.25'])
        ->withToken($tokenResult['plain_token'])
        ->getJson('/api/v1/auth/verify');

    $response->assertOk()
        ->assertJsonPath('device.platform', 'mac')
        ->assertJsonPath('device.last_used_at', '2026-09-04T10:00:15+00:00');

    $deviceToken->refresh();

    expect($deviceToken->last_ip)->toBe('198.51.100.25')
        ->and($deviceToken->client_platform)->toBe('mac')
        ->and($deviceToken->last_used_at?->toDateTimeString())->toBe('2026-09-04 10:00:15');
});

it('updates the heartbeat immediately when the client platform changes', function () {
    $this->travelTo('2026-09-04 10:00:00');

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $tokenResult = DeviceToken::createToken($user, $team, 'Travel Laptop', 'mac');
    $deviceToken = $tokenResult['device_token'];
    $deviceToken->update([
        'last_used_at' => now(),
        'last_ip' => '203.0.113.10',
    ]);

    $this->travelTo('2026-09-04 10:00:15');

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeaders(['X-Client-Platform' => 'linux'])
        ->withToken($tokenResult['plain_token'])
        ->getJson('/api/v1/auth/verify');

    $response->assertOk()
        ->assertJsonPath('device.platform', 'linux')
        ->assertJsonPath('device.last_used_at', '2026-09-04T10:00:15+00:00');

    $deviceToken->refresh();

    expect($deviceToken->last_ip)->toBe('203.0.113.10')
        ->and($deviceToken->client_platform)->toBe('linux')
        ->and($deviceToken->last_used_at?->toDateTimeString())->toBe('2026-09-04 10:00:15');
});
