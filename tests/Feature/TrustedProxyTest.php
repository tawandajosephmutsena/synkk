<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;

test('untrusted proxy cannot spoof X-Forwarded-For to bypass IP allowlist when TRUSTED_PROXIES is empty', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->switchTeam($team);

    // Create a device token restricted to corporate office subnet 198.51.100.0/24
    $tokenResult = DeviceToken::createToken(
        $user,
        $team,
        'Secure Laptop',
        'mac',
        'read_write',
        ['198.51.100.*']
    );
    $token = $tokenResult['plain_token'];

    // Attacker at 203.0.113.50 sends a forged X-Forwarded-For: 198.51.100.55
    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
        ->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Forwarded-For' => '198.51.100.55',
        ])
        ->getJson('/api/v1/auth/verify');

    // Because direct exposure does not trust wildcard proxies, client IP is 203.0.113.50, rejecting spoofing with 403
    $response->assertStatus(403)
        ->assertJson([
            'error' => 'IP Access Restricted',
            'message' => 'Access from IP 203.0.113.50 is not authorized for this device.',
        ]);
});

test('trusted proxy correctly propagates genuine client IP from X-Forwarded-For', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->switchTeam($team);

    // Create a device token restricted to corporate office subnet 198.51.100.0/24
    $tokenResult = DeviceToken::createToken(
        $user,
        $team,
        'Office Desktop',
        'mac',
        'read_write',
        ['198.51.100.*']
    );
    $token = $tokenResult['plain_token'];

    // Legitimate request from 198.51.100.55
    $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.55'])
        ->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/verify');

    $response->assertOk()
        ->assertJsonPath('device.name', 'Office Desktop');
});

test('configured reverse proxy in trustedproxy.proxies forwards client IP and untrusted proxy is ignored', function () {
    config()->set('trustedproxy.proxies', '10.0.0.1');

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->switchTeam($team);

    $tokenResult = DeviceToken::createToken(
        $user,
        $team,
        'Office Desktop',
        'mac',
        'read_write',
        ['198.51.100.*']
    );
    $token = $tokenResult['plain_token'];

    // Legitimate request behind trusted reverse proxy 10.0.0.1
    $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Forwarded-For' => '198.51.100.55',
        ])
        ->getJson('/api/v1/auth/verify');

    $response->assertOk()
        ->assertJsonPath('device.name', 'Office Desktop');

    // Untrusted reverse proxy 10.0.0.99 attempting to forward the same client IP
    $untrustedResponse = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
        ->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Forwarded-For' => '198.51.100.55',
        ])
        ->getJson('/api/v1/auth/verify');

    $untrustedResponse->assertStatus(403)
        ->assertJson([
            'error' => 'IP Access Restricted',
            'message' => 'Access from IP 10.0.0.99 is not authorized for this device.',
        ]);
});
