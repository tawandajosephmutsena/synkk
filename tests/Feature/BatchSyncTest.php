<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('can perform batch upload and deletion in a single request', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Batch Test Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'MacBook Pro', 'mac');
    $token = $tokenResult['plain_token'];

    // Batch upload 2 files
    $batchRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/batch-sync", [
            'items' => [
                [
                    'action' => 'upload',
                    'path' => 'NoteA.md',
                    'content_base64' => base64_encode('# Note A'),
                    'base_version' => 0,
                ],
                [
                    'action' => 'upload',
                    'path' => 'NoteB.md',
                    'content_base64' => base64_encode('# Note B'),
                    'base_version' => 0,
                ],
            ],
        ]);

    $batchRes->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('summary.pushed', 2)
        ->assertJsonPath('summary.errors', 0);

    $this->assertDatabaseHas('vault_files', ['vault_id' => $vault->id, 'path' => 'NoteA.md']);
    $this->assertDatabaseHas('vault_files', ['vault_id' => $vault->id, 'path' => 'NoteB.md']);

    // Now batch delete NoteA and upload NoteC
    $batchDeleteRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/batch-sync", [
            'items' => [
                [
                    'action' => 'delete',
                    'path' => 'NoteA.md',
                ],
                [
                    'action' => 'upload',
                    'path' => 'NoteC.md',
                    'content_base64' => base64_encode('# Note C'),
                    'base_version' => 0,
                ],
            ],
        ]);

    $batchDeleteRes->assertOk()
        ->assertJsonPath('summary.deleted', 1)
        ->assertJsonPath('summary.pushed', 1);

    $this->assertDatabaseHas('vault_files', ['vault_id' => $vault->id, 'path' => 'NoteA.md', 'is_deleted' => true]);
    $this->assertDatabaseHas('vault_files', ['vault_id' => $vault->id, 'path' => 'NoteC.md', 'is_deleted' => false]);
});

test('allows filenames with double dots but blocks directory traversal', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Double Dot Test Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'MacBook Pro', 'mac');
    $token = $tokenResult['plain_token'];

    // Valid filename with double dots (e.g. Zimbabwe..md)
    $resValid = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/batch-sync", [
            'items' => [
                [
                    'action' => 'upload',
                    'path' => 'docs/remittance..md',
                    'content_base64' => base64_encode('# Valid double dot'),
                    'base_version' => 0,
                ],
            ],
        ]);
    $resValid->assertOk();

    // Invalid path traversal (e.g. ../../secret.txt)
    $resTraversal = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/batch-sync", [
            'items' => [
                [
                    'action' => 'upload',
                    'path' => '../secret.txt',
                    'content_base64' => base64_encode('hacked'),
                    'base_version' => 0,
                ],
            ],
        ]);
    $resTraversal->assertStatus(422);
});
