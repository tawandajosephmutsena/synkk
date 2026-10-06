<?php

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\QrPairingService;
use Livewire\Livewire;

test('a generated token is shown once and only its hash is stored', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::devices.index')
        ->set('deviceName', 'Studio Mac')
        ->set('devicePlatform', 'mac')
        ->set('pairingMethod', 'token')
        ->call('generateToken');

    $plainToken = $component->get('generatedPlainToken');
    $deviceToken = DeviceToken::query()->sole();

    expect($plainToken)
        ->toStartWith('synkk_')
        ->and($deviceToken->token_hash)->toBe(hash('sha256', $plainToken))
        ->and($deviceToken->token_hash)->not->toContain($plainToken)
        ->and($deviceToken->token_preview)->toStartWith('synkk_');

    expect($component->get('pairingSessionId'))->toBeNull();

    $component
        ->assertSee($plainToken)
        ->assertSee('If automatic copy is blocked, select the token and press')
        ->assertDispatched('modal-close', name: 'create-device-token')
        ->assertDispatched('modal-show', name: 'show-token-modal');
});

test('the plaintext token can be cleared after it is saved', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::devices.index')
        ->set('deviceName', 'Travel phone')
        ->set('devicePlatform', 'ios')
        ->set('pairingMethod', 'token')
        ->call('generateToken')
        ->assertSet('generatedPlainToken', fn (?string $token): bool => str_starts_with((string) $token, 'synkk_'))
        ->call('clearGeneratedToken')
        ->assertSet('generatedPlainToken', null)
        ->assertDispatched('modal-close', name: 'show-token-modal');
});

test('QR setup creates one token only when the pairing session is redeemed', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();
    $team->update(['plan' => 'pro_ltd', 'max_devices' => 1]);
    $vault = $team->vaults()->create([
        'name' => 'Travel Notes',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);
    $user->refresh();

    $component = Livewire::actingAs($user)
        ->test('pages::devices.index', ['current_team' => $team->slug])
        ->set('pairingMethod', 'qr')
        ->set('allowedIpSubnets', '203.0.113.*')
        ->call('generateToken')
        ->assertHasNoErrors();

    $sessionId = $component->get('pairingSessionId');

    expect($component->get('generatedPlainToken'))->toBeNull();
    expect($sessionId)->toStartWith('synkk_pair_');
    expect($team->deviceTokens()->count())->toBe(0);

    $component->assertSee('Scan this QR code now')
        ->assertDontSee('Device Sync Token');

    $response = $this->postJson('/api/v1/pairing/exchange', [
        'session' => $sessionId,
        'device_name' => 'Travel phone',
        'platform' => 'android',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'paired')
        ->assertJsonPath('vault_slug', $vault->slug);

    expect($team->deviceTokens()->count())->toBe(1);
    expect($team->deviceTokens()->sole()->allowed_ip_subnets)->toBe(['203.0.113.*']);
});

test('QR setup requires a vault but manual token setup remains available', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::devices.index')
        ->assertSee('QR pairing needs a vault to sync.')
        ->set('pairingMethod', 'qr')
        ->call('generateToken')
        ->assertHasErrors(['pairingMethod'])
        ->assertSee('Create a vault before pairing a device with a QR code.')
        ->assertSet('pairingSessionId', null)
        ->assertNotDispatched('modal-show');

    expect(DeviceToken::query()->count())->toBe(0);

    $component
        ->set('pairingMethod', 'token')
        ->set('deviceName', 'Manual laptop')
        ->call('generateToken')
        ->assertHasNoErrors()
        ->assertSet('generatedPlainToken', fn (?string $token): bool => str_starts_with((string) $token, 'synkk_'));

    expect(DeviceToken::query()->count())->toBe(1);
});

test('QR setup requires an explicit vault choice when the team has multiple vaults', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();
    $team->update(['plan' => 'pro_ltd']);
    $team->vaults()->create([
        'name' => 'Alpha Notes',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);
    $chosenVault = $team->vaults()->create([
        'name' => 'Beta Notes',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);
    $user->refresh();

    $component = Livewire::actingAs($user)
        ->test('pages::devices.index')
        ->assertSee('Vault to Sync')
        ->set('pairingMethod', 'qr')
        ->call('generateToken')
        ->assertHasErrors(['pairingVaultId'])
        ->assertSee('Select a vault to pair with this device.')
        ->assertSet('pairingSessionId', null)
        ->set('pairingVaultId', (string) $chosenVault->id)
        ->call('generateToken')
        ->assertHasNoErrors();

    $this->postJson('/api/v1/pairing/exchange', [
        'session' => $component->get('pairingSessionId'),
        'device_name' => 'Studio laptop',
        'platform' => 'mac',
    ])->assertOk()
        ->assertJsonPath('vault_slug', $chosenVault->slug);

    expect($team->deviceTokens()->sole()->allowed_vault_ids)->toBe([$chosenVault->id]);
});

test('device setup rejects an invalid method without creating a token', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::devices.index')
        ->set('deviceName', 'Unknown device')
        ->set('pairingMethod', 'unsupported')
        ->call('generateToken')
        ->assertHasErrors(['pairingMethod' => 'in']);

    expect(DeviceToken::query()->count())->toBe(0);
});

test('QR setup shows a usable error when code generation fails', function () {
    $user = User::factory()->create();
    $user->personalTeam()->vaults()->create([
        'name' => 'Notes',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);
    $pairingService = Mockery::mock(QrPairingService::class);
    $pairingService->shouldReceive('createPairingSession')->once()->andThrow(new RuntimeException('QR renderer failed'));
    app()->instance(QrPairingService::class, $pairingService);

    Livewire::actingAs($user)
        ->test('pages::devices.index')
        ->set('pairingMethod', 'qr')
        ->call('generateToken')
        ->assertHasErrors(['pairingMethod'])
        ->assertNotDispatched('modal-show');

    expect(DeviceToken::query()->count())->toBe(0);
});
