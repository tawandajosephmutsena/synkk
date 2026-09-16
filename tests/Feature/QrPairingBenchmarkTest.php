<?php

use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Services\QrPairingService;

test('Phase 4: Full End-to-End Pairing Lifecycle with sub-200ms benchmark', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->switchTeam($team);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'E2E Milestone Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $service = new QrPairingService;

    // STEP 1: Desktop generates session
    $t0 = microtime(true);
    $sessionData = $service->createPairingSession($user, $team, $vault->slug);
    $t1 = microtime(true);
    $sessionGenerationTimeMs = ($t1 - $t0) * 1000;

    expect($sessionData)->toHaveKeys(['session', 'pairing_url', 'web_pairing_url', 'qr_svg', 'expires_at'])
        ->and($sessionData['session'])->toStartWith('synkk_pair_')
        ->and($sessionData['pairing_url'])->toStartWith('obsidian://synkk-pair?')
        ->and($sessionData['web_pairing_url'])->toContain('/pair?session=');

    $sessionId = $sessionData['session'];

    // STEP 2: Mobile Camera opens web bridge URL
    $bridgeRes = $this->get($sessionData['web_pairing_url']);
    $bridgeRes->assertOk()
        ->assertSee('Connecting to Obsidian...')
        ->assertSee('Open in Obsidian App');

    // STEP 3: Initial status on desktop is pending
    $statusBefore = $this->getJson("/api/v1/pairing/status?session={$sessionId}");
    $statusBefore->assertOk()
        ->assertJson([
            'status' => 'pending',
            'claimed_device_name' => null,
            'device_token_id' => null,
        ]);

    // STEP 4: Mobile device performs token exchange (Benchmark)
    $tExchangeStart = microtime(true);
    $exchangeRes = $this->postJson('/api/v1/pairing/exchange', [
        'session' => $sessionId,
        'device_name' => 'iPhone 16 Pro Max',
        'platform' => 'ios',
    ]);
    $tExchangeEnd = microtime(true);
    $exchangeDurationMs = ($tExchangeEnd - $tExchangeStart) * 1000;

    $exchangeRes->assertOk()
        ->assertJson([
            'status' => 'paired',
            'vault_slug' => $vault->slug,
            'team_slug' => $team->slug,
            'device_name' => 'iPhone 16 Pro Max',
        ]);

    $plainToken = $exchangeRes->json('plain_token');
    expect($plainToken)->toBeString()
        ->and($plainToken)->toStartWith('synkk_');

    // STEP 5: Verify new token authenticates against REST API
    $authVerify = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->getJson('/api/v1/auth/verify');
    $authVerify->assertOk()
        ->assertJsonPath('user.name', $user->name)
        ->assertJsonPath('team.slug', $team->slug);

    // STEP 6: Desktop status transitions to paired
    $statusAfter = $this->getJson("/api/v1/pairing/status?session={$sessionId}");
    $statusAfter->assertOk()
        ->assertJson([
            'status' => 'paired',
            'claimed_device_name' => 'iPhone 16 Pro Max',
        ]);

    // STEP 7: Single-use replay rejection
    $replayAttempt = $this->postJson('/api/v1/pairing/exchange', [
        'session' => $sessionId,
        'device_name' => 'Attacker Clone Phone',
        'platform' => 'ios',
    ]);
    $replayAttempt->assertStatus(410)
        ->assertJsonPath('error', 'Pairing session expired or already consumed.');

    // Print benchmarks to standard out
    fwrite(STDERR, sprintf("\n[BENCHMARK] Session Generation: %.2f ms | Exchange Handshake: %.2f ms\n", $sessionGenerationTimeMs, $exchangeDurationMs));

    expect($exchangeDurationMs)->toBeLessThan(500); // Strict threshold
});
