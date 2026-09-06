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

test('read-only device token cannot upload or modify vault files', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Read Only Test Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'Contractor Tablet', 'ios', 'read_only');
    $plainToken = $tokenResult['plain_token'];

    // Read manifest works
    $manifestRes = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->getJson("/api/v1/vaults/{$vault->slug}/manifest");
    $manifestRes->assertOk();

    // Upload receives 403 Permission Denied
    $uploadRes = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/upload", [
            'path' => 'notes/contractor-notes.md',
            'content' => 'Trying to write on read-only token',
        ]);
    $uploadRes->assertStatus(403)
        ->assertJsonPath('error', 'Permission Denied');
});

test('device token with allowed IP subnets rejects unauthorized client IPs', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Subnet Guard Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    // Token whitelist restricted to 10.0.*
    $tokenResult = DeviceToken::createToken($user, $team, 'Office PC', 'mac', 'full_access', ['10.0.*']);
    $plainToken = $tokenResult['plain_token'];

    // Request from unauthorized IP
    $unauthRes = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
        ->withHeader('Authorization', "Bearer {$plainToken}")
        ->getJson("/api/v1/vaults/{$vault->slug}/manifest");
    $unauthRes->assertStatus(403)
        ->assertJsonPath('error', 'IP Access Restricted');

    // Request from allowed subnet IP
    $authRes = $this->withServerVariables(['REMOTE_ADDR' => '10.0.4.12'])
        ->withHeader('Authorization', "Bearer {$plainToken}")
        ->getJson("/api/v1/vaults/{$vault->slug}/manifest");
    $authRes->assertOk();
});
