<?php

use App\Models\DeviceToken;
use App\Models\User;
use Livewire\Livewire;

test('a generated token is shown once and only its hash is stored', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::devices.index')
        ->set('deviceName', 'Studio Mac')
        ->set('devicePlatform', 'mac')
        ->call('generateToken');

    $plainToken = $component->get('generatedPlainToken');
    $deviceToken = DeviceToken::query()->sole();

    expect($plainToken)
        ->toStartWith('synkk_')
        ->and($deviceToken->token_hash)->toBe(hash('sha256', $plainToken))
        ->and($deviceToken->token_hash)->not->toContain($plainToken)
        ->and($deviceToken->token_preview)->toStartWith('synkk_');

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
        ->call('generateToken')
        ->assertSet('generatedPlainToken', fn (?string $token): bool => str_starts_with((string) $token, 'synkk_'))
        ->call('clearGeneratedToken')
        ->assertSet('generatedPlainToken', null)
        ->assertDispatched('modal-close', name: 'show-token-modal');
});
