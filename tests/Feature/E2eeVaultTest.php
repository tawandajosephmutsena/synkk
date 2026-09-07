<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Services\E2eeVaultService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('E2eeVaultService manages vault encryption state and key salt', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Secret Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $service = new E2eeVaultService;
    $salt = bin2hex(random_bytes(16));
    $testCipher = json_encode(['ciphertextBase64' => 'xyz', 'ivHex' => 'abc', 'tagHex' => '123']);

    $service->enableE2ee($vault, $salt, $testCipher);

    $vault->refresh();
    expect($vault->is_e2ee)->toBeTrue()
        ->and($vault->e2ee_salt)->toBe($salt)
        ->and($vault->e2ee_test_cipher)->toBe($testCipher);

    $status = $service->getStatus($vault);
    expect($status['is_e2ee'])->toBeTrue()
        ->and($status['salt'])->toBe($salt)
        ->and($status['has_test_cipher'])->toBeTrue();

    $service->disableE2ee($vault);
    $vault->refresh();
    expect($vault->is_e2ee)->toBeFalse()
        ->and($vault->e2ee_salt)->toBeNull();
});

test('API supports enabling zero-knowledge E2EE and syncing encrypted payloads', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Cryptographic Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'iPhone 15 Pro', 'ios');
    $token = $tokenResult['plain_token'];

    $salt = bin2hex(random_bytes(16));
    $testCipher = json_encode(['test' => true]);

    // 1. Enable E2EE via API
    $enableRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/e2ee/enable", [
            'salt' => $salt,
            'test_cipher' => $testCipher,
        ]);

    $enableRes->assertOk()
        ->assertJson([
            'status' => 'enabled',
            'is_e2ee' => true,
            'salt' => $salt,
        ]);

    // 2. Check E2EE status
    $statusRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/vaults/{$vault->slug}/e2ee/status");

    $statusRes->assertOk()
        ->assertJson([
            'is_e2ee' => true,
            'salt' => $salt,
            'has_test_cipher' => true,
        ]);

    // 3. Upload encrypted note
    $iv = bin2hex(random_bytes(12));
    $tag = bin2hex(random_bytes(16));
    $encryptedCiphertextBase64 = base64_encode('opaque-encrypted-binary-data');

    $uploadRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/upload", [
            'path' => 'Secret/Financials.md',
            'content_base64' => $encryptedCiphertextBase64,
            'is_encrypted' => true,
            'encryption_iv' => $iv,
            'encryption_tag' => $tag,
            'base_version' => 0,
        ]);

    $uploadRes->assertCreated();

    // 4. Manifest includes E2EE vault metadata and encrypted file fields
    $manifestRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/vaults/{$vault->slug}/manifest");

    $manifestRes->assertOk()
        ->assertJsonPath('vault.is_e2ee', true)
        ->assertJsonPath('vault.e2ee_salt', $salt);

    $manifestFiles = $manifestRes->json('files');
    expect($manifestFiles)->toHaveCount(1)
        ->and($manifestFiles[0]['is_encrypted'])->toBeTrue()
        ->and($manifestFiles[0]['encryption_iv'])->toBe($iv)
        ->and($manifestFiles[0]['encryption_tag'])->toBe($tag);

    // 5. Download encrypted note delivers encryption headers
    $downloadRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/vaults/{$vault->slug}/download?path=Secret/Financials.md");

    $downloadRes->assertOk()
        ->assertHeader('X-Synkk-Encrypted', '1')
        ->assertHeader('X-Synkk-IV', $iv)
        ->assertHeader('X-Synkk-Tag', $tag);
});
