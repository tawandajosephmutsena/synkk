<?php

use App\Exceptions\PairingSessionConsumedException;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Services\QrPairingService;

test('QrPairingService creates pairing sessions with obsidian protocol URL and performs atomic exchange', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->switchTeam($team);

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
        ->and($sessionData['pairing_url'])->toStartWith('obsidian://synkk-pair?')
        ->and($sessionData['pairing_url'])->toContain('server='.urlencode(url('/api/v1')))
        ->and($sessionData['pairing_url'])->toContain('session='.urlencode($sessionData['session']))
        ->and($sessionData['pairing_url'])->toContain('vault='.urlencode($vault->slug))
        ->and($sessionData['pairing_url'])->toContain('v=2')
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
        ->and($exchange['vault_slug'])->toBe($vault->slug)
        ->and($exchange['access_scope'])->toBe('read_write')
        ->and($exchange['broadcasting'])->toHaveKeys(['driver', 'key', 'host', 'port', 'scheme']);

    // Session is now paired
    $statusAfter = $service->checkStatus($sessionData['session']);
    expect($statusAfter['status'])->toBe('paired')
        ->and($statusAfter['device_token_id'])->toBe($exchange['device_id'])
        ->and($statusAfter['claimed_device_name'])->toBe('Pixel 8 Pro');

    // Attempting to exchange again throws PairingSessionConsumedException
    expect(fn () => $service->exchange($sessionData['session'], 'Another Phone', 'ios'))
        ->toThrow(PairingSessionConsumedException::class, 'Pairing session has already been used.');
});

test('API endpoints return HTTP 410 when pairing session is expired or already consumed', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->switchTeam($team);

    $tokenResult = DeviceToken::createToken($user, $team, 'Laptop', 'mac');
    $token = $tokenResult['plain_token'];

    // 1. Create a pairing session
    $sessionRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/pairing/session');

    $sessionRes->assertOk();
    $sessionId = $sessionRes->json('session.session');

    // 2. First exchange succeeds
    $exchange1 = $this->postJson('/api/v1/pairing/exchange', [
        'session' => $sessionId,
        'device_name' => 'iPhone 15',
        'platform' => 'ios',
    ]);
    $exchange1->assertOk()
        ->assertJson(['status' => 'paired']);

    // 3. Second exchange attempt fails with HTTP 410 (already consumed)
    $exchange2 = $this->postJson('/api/v1/pairing/exchange', [
        'session' => $sessionId,
        'device_name' => 'iPhone 15 Clone',
        'platform' => 'ios',
    ]);
    $exchange2->assertStatus(410)
        ->assertJsonPath('error', 'Pairing session expired or already consumed.');

    // 4. Unknown or expired session fails with HTTP 410
    $exchangeExpired = $this->postJson('/api/v1/pairing/exchange', [
        'session' => 'synkk_pair_nonexistent_or_expired',
        'device_name' => 'Random Device',
        'platform' => 'android',
    ]);
    $exchangeExpired->assertStatus(410)
        ->assertJsonPath('error', 'Pairing session expired or already consumed.');
});

test('status reports pending, paired, revoked, and expired accurately', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->switchTeam($team);

    $service = new QrPairingService;
    $sessionData = $service->createPairingSession($user, $team);
    $sessionId = $sessionData['session'];

    // 1. Status is pending
    $resPending = $this->getJson("/api/v1/pairing/status?session={$sessionId}");
    $resPending->assertOk()
        ->assertJson(['status' => 'pending']);

    // 2. Exchange token
    $exchange = $service->exchange($sessionId, 'Tablet', 'android');

    // 3. Status is paired
    $resPaired = $this->getJson("/api/v1/pairing/status?session={$sessionId}");
    $resPaired->assertOk()
        ->assertJson([
            'status' => 'paired',
            'claimed_device_name' => 'Tablet',
        ]);

    // 4. Revoke the device token -> status transitions to revoked
    DeviceToken::whereKey($exchange['device_id'])->delete();
    $resRevoked = $this->getJson("/api/v1/pairing/status?session={$sessionId}");
    $resRevoked->assertOk()
        ->assertJson(['status' => 'revoked']);

    // 5. Expired / missing session -> status reports expired
    $resExpired = $this->getJson('/api/v1/pairing/status?session=synkk_pair_expired_session');
    $resExpired->assertOk()
        ->assertJson(['status' => 'expired']);
});

test('initiator token scope and vault restrictions are strictly inherited by child token', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->switchTeam($team);

    $vaultA = Vault::create([
        'team_id' => $team->id,
        'name' => 'Allowed Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $vaultB = Vault::create([
        'team_id' => $team->id,
        'name' => 'Forbidden Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    // 1. Read-only token cannot initiate pairing session
    $roResult = DeviceToken::createToken($user, $team, 'Read Only Device', 'mac', 'read_only');
    $roToken = $roResult['plain_token'];

    $roAttempt = $this->withHeader('Authorization', "Bearer {$roToken}")
        ->postJson('/api/v1/pairing/session');
    $roAttempt->assertStatus(403);

    // 2. Restricted token cannot pair for inaccessible vault
    $restrictedResult = DeviceToken::createToken(
        $user,
        $team,
        'Restricted Device',
        'mac',
        'read_write',
    );
    $restrictedResult['device_token']->update([
        'allowed_vault_ids' => [$vaultA->id],
    ]);
    $restrictedToken = $restrictedResult['plain_token'];

    // Inaccessible vault B -> rejected
    $badVaultAttempt = $this->withHeader('Authorization', "Bearer {$restrictedToken}")
        ->postJson('/api/v1/pairing/session', [
            'vault' => $vaultB->slug,
        ]);
    $badVaultAttempt->assertStatus(403);

    // Accessible vault A -> succeeds
    $goodVaultAttempt = $this->withHeader('Authorization', "Bearer {$restrictedToken}")
        ->postJson('/api/v1/pairing/session', [
            'vault' => $vaultA->slug,
            'access_scope' => 'full_access', // Attempts escalation
        ]);
    $goodVaultAttempt->assertOk();

    $childSessionId = $goodVaultAttempt->json('session.session');

    // 3. Exchange child token and verify scope capped to read_write and limited to vault A
    $exchangeRes = $this->postJson('/api/v1/pairing/exchange', [
        'session' => $childSessionId,
        'device_name' => 'Child Mobile',
        'platform' => 'ios',
    ]);
    $exchangeRes->assertOk();

    $childDeviceId = $exchangeRes->json('device_id');
    $childDevice = DeviceToken::find($childDeviceId);

    expect($childDevice->access_scope)->toBe('read_write') // Escalation prevented!
        ->and($childDevice->allowed_vault_ids)->toBe([$vaultA->id]) // Vault constraint inherited!
        ->and($childDevice->canAccessVault($vaultA->id))->toBeTrue()
        ->and($childDevice->canAccessVault($vaultB->id))->toBeFalse();
});

test('public mobile pairing bridge renders auto-redirect and obsidian protocol links', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->switchTeam($team);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Bridge Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $service = new QrPairingService;
    $sessionData = $service->createPairingSession($user, $team, $vault->slug);
    $sessionId = $sessionData['session'];

    expect($sessionData)->toHaveKey('web_pairing_url')
        ->and($sessionData['web_pairing_url'])->toContain('/pair?session=');

    // 1. Pending session renders bridge page with Obsidian link
    $resPending = $this->get("/pair?session={$sessionId}&vault={$vault->slug}");
    $resPending->assertOk()
        ->assertSee('Connecting to Obsidian...')
        ->assertSee('obsidian://synkk-pair', false)
        ->assertSee('Open in Obsidian App');

    // 2. Claim session via exchange
    $service->exchange($sessionId, 'Mobile Tester', 'ios');

    // 3. Paired session renders paired state
    $resPaired = $this->get("/pair?session={$sessionId}");
    $resPaired->assertOk()
        ->assertSee('Device Already Paired!')
        ->assertSee('Mobile Tester');

    // 4. Expired or non-existent session renders expired state
    $resExpired = $this->get('/pair?session=synkk_pair_non_existent');
    $resExpired->assertOk()
        ->assertSee('Session Expired or Invalid');
});
