<?php

use App\Enums\TeamRole;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['synkk.storage_disk' => 'local']);
    Storage::fake('local');
});

function createEnvelopeScenario(bool $isE2ee = false): array
{
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Envelope Vault '.Str::random(6),
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
        'is_e2ee' => $isE2ee,
        'e2ee_salt' => $isE2ee ? bin2hex(random_bytes(16)) : null,
        'e2ee_test_cipher' => $isE2ee ? 'test-cipher' : null,
    ]);

    $tokenResult = DeviceToken::createToken($owner, $team, 'MacBook', 'mac');

    return [$vault, $tokenResult['plain_token'], $owner, $team];
}

test('manifest response identifies protocol version and required capabilities', function () {
    [$vault, $token] = createEnvelopeScenario(isE2ee: true);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('X-Synkk-Protocol', '2')
        ->getJson(route('api.vaults.manifest', ['vault' => $vault->slug]));

    $response->assertOk()
        ->assertJson([
            'protocol_version' => 2,
            'minimum_protocol_version' => 2,
            'vault' => [
                'id' => $vault->id,
                'slug' => $vault->slug,
                'is_e2ee' => true,
            ],
        ]);
});

test('rejects plaintext upload for E2EE encrypted vault with 422', function () {
    [$vault, $token] = createEnvelopeScenario(isE2ee: true);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('X-Synkk-Protocol', '2')
        ->postJson(route('api.vaults.upload', ['vault' => $vault->slug]), [
            'path' => 'Private/notes.md',
            'content' => '# Unencrypted Plaintext Leak',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['encrypted']);
});

test('accepts valid encrypted envelope for E2EE vault', function () {
    [$vault, $token] = createEnvelopeScenario(isE2ee: true);

    $iv = bin2hex(random_bytes(12)); // 96-bit AES-GCM IV = 24 hex chars
    $tag = bin2hex(random_bytes(16)); // 128-bit AES-GCM tag = 32 hex chars
    $ciphertext = base64_encode('enc-binary-data');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('X-Synkk-Protocol', '2')
        ->postJson(route('api.vaults.upload', ['vault' => $vault->slug]), [
            'path' => 'Private/secret.md',
            'content_base64' => $ciphertext,
            'encrypted' => true,
            'iv' => $iv,
            'tag' => $tag,
            'format_version' => 2,
            'plaintext_size' => 15,
        ]);

    $response->assertCreated();
    $file = $vault->files()->where('path', 'Private/secret.md')->first();
    expect($file)->not->toBeNull()
        ->and($file->is_encrypted)->toBeTrue()
        ->and($file->encryption_iv)->toBe($iv)
        ->and($file->encryption_tag)->toBe($tag);
});

test('rejects malformed IV and tag lengths with 422', function () {
    [$vault, $token] = createEnvelopeScenario(isE2ee: true);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('X-Synkk-Protocol', '2')
        ->postJson(route('api.vaults.upload', ['vault' => $vault->slug]), [
            'path' => 'Private/secret.md',
            'content_base64' => base64_encode('cipher'),
            'encrypted' => true,
            'iv' => 'short-iv', // invalid length
            'tag' => 'invalid-tag',
            'format_version' => 2,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['iv', 'tag']);
});

test('batch sync rejects unencrypted mutations for E2EE vaults with 422', function () {
    [$vault, $token] = createEnvelopeScenario(isE2ee: true);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('X-Synkk-Protocol', '2')
        ->postJson(route('api.vaults.batch_sync', ['vault' => $vault->slug]), [
            'changes' => [
                [
                    'path' => 'note.md',
                    'action' => 'upload',
                    'content' => 'plaintext leak',
                    'encrypted' => false,
                ],
            ],
        ]);

    $response->assertStatus(422);
});
