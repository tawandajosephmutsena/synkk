<?php

use App\Models\DeviceToken;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('lets the first owner log in, create a vault, pair a device, sync a note, and reconnect after session reset', function () {
    Storage::fake('local');

    $bootstrapVariables = [
        'SYNKK_BOOTSTRAP_EMAIL' => 'owner@example.test',
        'SYNKK_BOOTSTRAP_PASSWORD' => 'SecureBootstrapPassword123!',
        'SYNKK_BOOTSTRAP_NAME' => 'First Owner',
        'SYNKK_BOOTSTRAP_PASSWORD_SOURCE' => null,
    ];
    $previousValues = [];

    foreach ($bootstrapVariables as $name => $value) {
        $previousValues[$name] = getenv($name);
        $value === null ? putenv($name) : putenv("{$name}={$value}");
    }

    try {
        $this->artisan('synkk:bootstrap-admin', ['--if-empty' => true, '--no-interaction' => true])
            ->assertSuccessful();
    } finally {
        foreach ($previousValues as $name => $value) {
            $value === false ? putenv($name) : putenv("{$name}={$value}");
        }
    }

    $owner = User::query()->where('email', 'owner@example.test')->sole();
    $team = $owner->currentTeam;

    $this->post(route('login.store'), [
        'email' => 'owner@example.test',
        'password' => 'SecureBootstrapPassword123!',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('dashboard', ['current_team' => $team->slug]))->assertOk();

    Livewire::actingAs($owner)
        ->test('pages::vaults.index', ['current_team' => $team->slug])
        ->set('vaultName', 'First Vault')
        ->call('createVault')
        ->assertHasNoErrors();

    $vault = Vault::query()->where('team_id', $team->id)->where('slug', 'first-vault')->sole();

    $devicePage = Livewire::actingAs($owner)
        ->test('pages::devices.index', ['current_team' => $team->slug])
        ->set('pairingMethod', 'qr')
        ->call('generateToken')
        ->assertHasNoErrors();

    $sessionId = $devicePage->get('pairingSessionId');

    expect($sessionId)->toStartWith('synkk_pair_');
    expect(DeviceToken::query()->where('team_id', $team->id)->count())->toBe(0);

    $exchange = $this->postJson('/api/v1/pairing/exchange', [
        'session' => $sessionId,
        'device_name' => 'Travel phone',
        'platform' => 'android',
    ]);

    $exchange->assertOk()
        ->assertJsonPath('status', 'paired')
        ->assertJsonPath('vault_slug', $vault->slug);

    $deviceToken = $exchange->json('plain_token');

    expect(DeviceToken::query()->where('team_id', $team->id)->count())->toBe(1);

    $this->post(route('logout'))->assertRedirect(route('home'));

    $this->withToken($deviceToken)
        ->getJson('/api/v1/auth/verify')
        ->assertOk()
        ->assertJsonPath('user.email', 'owner@example.test');

    $this->getJson('/api/v1/vaults')
        ->assertOk()
        ->assertJsonPath('vaults.0.slug', 'first-vault');

    $note = "# Welcome\nSynkk has synced this note.";

    $this->postJson("/api/v1/vaults/{$vault->slug}/upload", [
        'path' => 'Notes/Welcome.md',
        'content_base64' => base64_encode($note),
        'base_version' => 0,
    ])->assertCreated();

    $this->getJson("/api/v1/vaults/{$vault->slug}/manifest")
        ->assertOk()
        ->assertJsonPath('files.0.path', 'Notes/Welcome.md');

    Cache::flush();

    $this->getJson('/api/v1/auth/verify')
        ->assertOk()
        ->assertJsonPath('device.name', 'Travel phone');

    $download = $this->get("/api/v1/vaults/{$vault->slug}/download?path=Notes%2FWelcome.md");

    $download->assertOk();
    expect($download->streamedContent())->toBe($note);
});
