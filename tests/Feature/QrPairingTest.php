<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Services\QrPairingService;

test('QrPairingService creates pairing sessions and performs instant 2s mobile exchange', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Mobile Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $service = new QrPairingService;
    $sessionData = $service->createPairingSession($user, $team, $vault->slug);

    expect($sessionData['session'])->toBeString()
        ->and($sessionData['qr_svg'])->toContain('<svg')
        ->and($sessionData['payload']['v'])->toBe(2)
        ->and($sessionData['payload']['type'])->toBe('synkk-pairing-session');

    // Status is initially pending
    $status = $service->checkStatus($sessionData['session']);
    expect($status['status'])->toBe('pending');

    // Mobile device exchanges session for token
    $exchange = $service->exchange(
        sessionId: $sessionData['session'],
        deviceName: 'Pixel 8 Pro',
        platform: 'android'
    );

    expect($exchange['status'])->toBe('paired')
        ->and($exchange['plain_token'])->toStartWith('synkk_')
        ->and($exchange['team_slug'])->toBe($team->slug)
        ->and($exchange['vault_slug'])->toBe($vault->slug);

    // Session is now paired
    $statusAfter = $service->checkStatus($sessionData['session']);
    expect($statusAfter['status'])->toBe('paired')
        ->and($statusAfter['device_token_id'])->toBe($exchange['device_id']);

    // Attempting to exchange again throws exception
    expect(fn () => $service->exchange($sessionData['session'], 'Another Phone', 'ios'))
        ->toThrow(RuntimeException::class, 'Pairing session has already been used.');
});

test('API endpoints for QR pairing allow authenticated session creation and public mobile exchange', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $tokenResult = DeviceToken::createToken($user, $team, 'Laptop', 'mac');
    $token = $tokenResult['plain_token'];

    // 1. Authenticated client requests a pairing session
    $sessionRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/pairing/session');

    $sessionRes->assertOk()
        ->assertJsonStructure([
            'status',
            'session' => [
                'session',
                'expires_at',
                'qr_svg',
                'payload',
            ],
        ]);

    $sessionId = $sessionRes->json('session.session');

    // 2. Browser checks status (public endpoint)
    $statusRes = $this->getJson("/api/v1/pairing/status?session={$sessionId}");
    $statusRes->assertOk()
        ->assertJson(['status' => 'pending']);

    // 3. Mobile device scans and exchanges session without existing bearer token
    $exchangeRes = $this->postJson('/api/v1/pairing/exchange', [
        'session' => $sessionId,
        'device_name' => 'iPad Air',
        'platform' => 'ios',
    ]);

    $exchangeRes->assertOk()
        ->assertJsonStructure([
            'status',
            'device_id',
            'plain_token',
            'server_url',
            'team_slug',
        ]);

    $newPlainToken = $exchangeRes->json('plain_token');

    // 4. Verify newly created device token works for authenticated endpoints
    $verifyRes = $this->withHeader('Authorization', "Bearer {$newPlainToken}")
        ->getJson('/api/v1/auth/verify');

    $verifyRes->assertOk()
        ->assertJsonPath('device.name', 'iPad Air')
        ->assertJsonPath('device.platform', 'ios');
});
