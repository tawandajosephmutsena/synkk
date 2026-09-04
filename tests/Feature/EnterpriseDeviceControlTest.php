<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Services\SecretScannerService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('remotely wiped device token receives HTTP 410 Gone response', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Wipe Test Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'Stolen Device', 'android');
    /** @var DeviceToken $deviceToken */
    $deviceToken = $tokenResult['device_token'];
    $plainToken = $tokenResult['plain_token'];

    // Trigger remote wipe
    $deviceToken->triggerRemoteWipe();

    $response = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->getJson("/api/v1/vaults/{$vault->slug}/manifest");

    $response->assertStatus(410)
        ->assertJsonPath('error', 'Device Wiped')
        ->assertJsonPath('action', 'remote_wipe');
});

test('secret scanner detects leaked AWS keys and private keys', function () {
    $scanner = app(SecretScannerService::class);

    $cleanContent = '# Regular Note Title';
    $cleanResult = $scanner->scan($cleanContent);
    expect($cleanResult['has_secrets'])->toBeFalse();

    $leakedContent = "AWS Key: AKIAIOSFODNN7EXAMPLE\n-----BEGIN RSA PRIVATE KEY-----";
    $leakedResult = $scanner->scan($leakedContent);
    expect($leakedResult['has_secrets'])->toBeTrue();
    expect($leakedResult['detected'])->toContain('AWS Access Key ID');
    expect($leakedResult['detected'])->toContain('RSA/SSH Private Key');
});
