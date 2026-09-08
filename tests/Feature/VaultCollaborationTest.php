<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultCollaborationDocument;
use App\Models\VaultCollaborationUpdate;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Alice']);
    $this->member = User::factory()->create(['name' => 'Bob']);
    $this->team = Team::factory()->create();
    $this->team->members()->attach([
        $this->owner->id => ['role' => 'owner'],
        $this->member->id => ['role' => 'member'],
    ]);
    $this->owner->update(['current_team_id' => $this->team->id]);
    $this->member->update(['current_team_id' => $this->team->id]);

    $this->vault = Vault::create([
        'team_id' => $this->team->id,
        'name' => 'Collab Vault',
        'default_permission' => 'read_write',
        'created_by' => $this->owner->id,
        'is_e2ee' => false,
    ]);

    $this->ownerTokenResult = DeviceToken::createToken($this->owner, $this->team, 'Alice MacBook', 'mac');
    $this->ownerToken = $this->ownerTokenResult['plain_token'];

    $this->memberTokenResult = DeviceToken::createToken($this->member, $this->team, 'Bob ThinkPad', 'linux');
    $this->memberToken = $this->memberTokenResult['plain_token'];
});

test('join creates or resolves stable collaboration document and returns initial state', function () {
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/join", [
        'path' => 'SharedDoc.md',
        'peer_id' => 'peer-alice-1',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'joined')
        ->assertJsonStructure(['document_id', 'room_id', 'latest_sequence', 'peers']);

    $documentId = $response->json('document_id');
    expect($documentId)->toBeInt();

    // Joining again for same path returns identical document_id
    $secondJoin = $this->withHeaders([
        'Authorization' => "Bearer {$this->memberToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/join", [
        'path' => 'SharedDoc.md',
        'peer_id' => 'peer-bob-1',
    ]);

    $secondJoin->assertOk()
        ->assertJsonPath('document_id', $documentId);
});

test('appends updates with strictly ordered sequences', function () {
    $joinRes = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/join", [
        'path' => 'ProjectSpec.md',
        'peer_id' => 'peer-alice-1',
    ])->assertOk();

    $documentId = $joinRes->json('document_id');

    // Append update 1
    $update1Res = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/append", [
        'document_id' => $documentId,
        'client_update_id' => (string) Str::uuid(),
        'payload' => base64_encode('YJS_UPDATE_CHUNK_1'),
    ]);

    $update1Res->assertOk()
        ->assertJsonPath('status', 'committed')
        ->assertJsonPath('update.sequence', 1);

    // Append update 2
    $update2Res = $this->withHeaders([
        'Authorization' => "Bearer {$this->memberToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/append", [
        'document_id' => $documentId,
        'client_update_id' => (string) Str::uuid(),
        'payload' => base64_encode('YJS_UPDATE_CHUNK_2'),
    ]);

    $update2Res->assertOk()
        ->assertJsonPath('status', 'committed')
        ->assertJsonPath('update.sequence', 2);

    $document = VaultCollaborationDocument::findOrFail($documentId);
    expect($document->latest_sequence)->toBe(2);
});

test('enforces client_update_id idempotency and stable duplicate responses', function () {
    $joinRes = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/join", [
        'path' => 'IdempotentDoc.md',
        'peer_id' => 'peer-alice-1',
    ])->assertOk();

    $documentId = $joinRes->json('document_id');
    $clientUpdateId = (string) Str::uuid();
    $payload = base64_encode('IDEMPOTENT_UPDATE_DATA');

    // First attempt
    $res1 = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/append", [
        'document_id' => $documentId,
        'client_update_id' => $clientUpdateId,
        'payload' => $payload,
    ])->assertOk();

    $seq1 = $res1->json('update.sequence');
    expect($seq1)->toBe(1);

    // Duplicate attempt with exact same client_update_id
    $res2 = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/append", [
        'document_id' => $documentId,
        'client_update_id' => $clientUpdateId,
        'payload' => $payload,
    ])->assertOk();

    $seq2 = $res2->json('update.sequence');
    expect($seq2)->toBe(1);

    // Ensure database only has 1 record
    expect(VaultCollaborationUpdate::where('vault_collaboration_document_id', $documentId)->count())->toBe(1);
});

test('refuses publication from read-only scoped device token', function () {
    $readOnlyTokenResult = DeviceToken::createToken(
        user: $this->member,
        team: $this->team,
        name: 'Read Only Device',
        platform: 'web',
        accessScope: 'read_only'
    );
    $readOnlyToken = $readOnlyTokenResult['plain_token'];

    $joinRes = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/join", [
        'path' => 'ReadOnlyTest.md',
        'peer_id' => 'peer-alice-1',
    ])->assertOk();

    $documentId = $joinRes->json('document_id');

    // Read-only token can catch-up/read
    $this->withHeaders([
        'Authorization' => "Bearer {$readOnlyToken}",
        'X-Synkk-Protocol' => '2',
    ])->getJson("/api/v1/vaults/{$this->vault->slug}/collab/catch-up?document_id={$documentId}")
        ->assertOk();

    // Read-only token CANNOT append update
    $this->withHeaders([
        'Authorization' => "Bearer {$readOnlyToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/append", [
        'document_id' => $documentId,
        'client_update_id' => (string) Str::uuid(),
        'payload' => base64_encode('UNAUTHORIZED_MUTATION'),
    ])->assertForbidden();
});

test('refuses unencrypted plaintext updates on E2EE vaults', function () {
    $e2eeVault = Vault::create([
        'team_id' => $this->team->id,
        'name' => 'Secret E2EE Vault',
        'default_permission' => 'read_write',
        'created_by' => $this->owner->id,
        'is_e2ee' => true,
    ]);

    $joinRes = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$e2eeVault->slug}/collab/join", [
        'path' => 'Classified.md',
        'peer_id' => 'peer-alice-1',
    ])->assertOk();

    $documentId = $joinRes->json('document_id');

    // Plaintext append without IV/tag must fail with 422
    $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$e2eeVault->slug}/collab/append", [
        'document_id' => $documentId,
        'client_update_id' => (string) Str::uuid(),
        'payload' => base64_encode('PLAINTEXT_LEAK'),
        'encrypted' => false,
    ])->assertStatus(422);

    // Properly encrypted append succeeds
    $iv = str_repeat('e1', 12);
    $tag = str_repeat('f2', 16);
    $ciphertext = base64_encode('ENCRYPTED_YJS_UPDATE');

    $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$e2eeVault->slug}/collab/append", [
        'document_id' => $documentId,
        'client_update_id' => (string) Str::uuid(),
        'payload' => $ciphertext,
        'encrypted' => true,
        'iv' => $iv,
        'tag' => $tag,
        'original_size' => 32,
    ])->assertOk()
        ->assertJsonPath('status', 'committed')
        ->assertJsonPath('update.is_encrypted', true)
        ->assertJsonPath('update.encryption_iv', $iv)
        ->assertJsonPath('update.encryption_tag', $tag);
});

test('retrieves catch-up updates strictly after a given sequence', function () {
    $joinRes = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/join", [
        'path' => 'CatchUpDoc.md',
        'peer_id' => 'peer-alice-1',
    ])->assertOk();

    $documentId = $joinRes->json('document_id');

    // Append 3 updates
    for ($i = 1; $i <= 3; $i++) {
        $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
            'X-Synkk-Protocol' => '2',
        ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/append", [
            'document_id' => $documentId,
            'client_update_id' => "client-update-{$i}",
            'payload' => base64_encode("CHUNK_{$i}"),
        ])->assertOk();
    }

    // Catch up with after_sequence = 1 should return chunks 2 and 3
    $catchUpRes = $this->withHeaders([
        'Authorization' => "Bearer {$this->memberToken}",
        'X-Synkk-Protocol' => '2',
    ])->getJson("/api/v1/vaults/{$this->vault->slug}/collab/catch-up?document_id={$documentId}&after_sequence=1")
        ->assertOk();

    $catchUpRes->assertJsonCount(2, 'updates')
        ->assertJsonPath('updates.0.sequence', 2)
        ->assertJsonPath('updates.1.sequence', 3);
});

test('refuses access when document ID does not belong to the requested vault', function () {
    $otherVault = Vault::create([
        'team_id' => $this->team->id,
        'name' => 'Other Vault',
        'default_permission' => 'read_write',
        'created_by' => $this->owner->id,
    ]);

    // Create document in vault 1
    $joinRes = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/join", [
        'path' => 'PrivateDoc.md',
        'peer_id' => 'peer-alice-1',
    ])->assertOk();

    $docInVault1 = $joinRes->json('document_id');

    // Attempt to access docInVault1 via otherVault's endpoint must return 404
    $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->getJson("/api/v1/vaults/{$otherVault->slug}/collab/catch-up?document_id={$docInVault1}")
        ->assertNotFound();

    $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$otherVault->slug}/collab/append", [
        'document_id' => $docInVault1,
        'client_update_id' => (string) Str::uuid(),
        'payload' => base64_encode('CROSS_VAULT_EXPLOIT'),
    ])->assertNotFound();
});

test('supports publishing and retrieving durable checkpoints', function () {
    $joinRes = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/join", [
        'path' => 'CheckpointDoc.md',
        'peer_id' => 'peer-alice-1',
    ])->assertOk();

    $documentId = $joinRes->json('document_id');

    // Append 2 updates
    $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/append", [
        'document_id' => $documentId,
        'client_update_id' => 'update-1',
        'payload' => base64_encode('DELTA_1'),
    ])->assertOk();

    $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/append", [
        'document_id' => $documentId,
        'client_update_id' => 'update-2',
        'payload' => base64_encode('DELTA_2'),
    ])->assertOk();

    // Publish checkpoint consolidating up to sequence 2
    $checkpointRes = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/checkpoint", [
        'document_id' => $documentId,
        'client_update_id' => 'checkpoint-1',
        'acknowledged_base_sequence' => 2,
        'payload' => base64_encode('FULL_COMPILED_STATE_AT_SEQ_2'),
    ]);

    $checkpointRes->assertOk()
        ->assertJsonPath('status', 'checkpoint_committed')
        ->assertJsonPath('update.is_checkpoint', true)
        ->assertJsonPath('update.acknowledged_base_sequence', 2);

    // Superseded updates 1 and 2 should have been pruned on checkpoint publish
    expect(VaultCollaborationUpdate::where('vault_collaboration_document_id', $documentId)->count())->toBe(1);
    expect(VaultCollaborationUpdate::where('vault_collaboration_document_id', $documentId)->first()->is_checkpoint)->toBeTrue();

    // Append update 3 (sequence 4)
    $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->postJson("/api/v1/vaults/{$this->vault->slug}/collab/append", [
        'document_id' => $documentId,
        'client_update_id' => 'update-3',
        'payload' => base64_encode('DELTA_3'),
    ])->assertOk();

    // Catch up from sequence 0 returns checkpoint and update 3
    $catchUpRes = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->getJson("/api/v1/vaults/{$this->vault->slug}/collab/catch-up?document_id={$documentId}&after_sequence=0");

    $catchUpRes->assertOk()
        ->assertJsonCount(2, 'updates')
        ->assertJsonPath('updates.0.is_checkpoint', true)
        ->assertJsonPath('updates.1.client_update_id', 'update-3');

    // Catch up from checkpoint sequence returns only update 3
    $checkpointSeq = $checkpointRes->json('update.sequence');
    $catchUpFromCheckpoint = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
        'X-Synkk-Protocol' => '2',
    ])->getJson("/api/v1/vaults/{$this->vault->slug}/collab/catch-up?document_id={$documentId}&after_sequence={$checkpointSeq}");

    $catchUpFromCheckpoint->assertOk()
        ->assertJsonCount(1, 'updates')
        ->assertJsonPath('updates.0.client_update_id', 'update-3');
});

test('prune collaboration journal command deletes obsolete updates and retains active checkpoints', function () {
    $document = VaultCollaborationDocument::create([
        'vault_id' => $this->vault->id,
        'path' => 'PruneTest.md',
        'latest_sequence' => 0,
    ]);

    // Create 3 old updates
    $u1 = $document->updates()->create([
        'sequence' => 1,
        'client_update_id' => 'old-1',
        'payload' => 'd1',
        'payload_sha256' => hash('sha256', 'd1'),
        'is_checkpoint' => false,
        'created_at' => now()->subDays(40),
    ]);
    $u2 = $document->updates()->create([
        'sequence' => 2,
        'client_update_id' => 'old-2',
        'payload' => 'd2',
        'payload_sha256' => hash('sha256', 'd2'),
        'is_checkpoint' => false,
        'created_at' => now()->subDays(35),
    ]);
    $checkpoint = $document->updates()->create([
        'sequence' => 3,
        'client_update_id' => 'old-checkpoint',
        'payload' => 'checkpoint-snap',
        'payload_sha256' => hash('sha256', 'checkpoint-snap'),
        'is_checkpoint' => true,
        'acknowledged_base_sequence' => 2,
        'created_at' => now()->subDays(31),
    ]);
    $latest = $document->updates()->create([
        'sequence' => 4,
        'client_update_id' => 'fresh-update',
        'payload' => 'fresh',
        'payload_sha256' => hash('sha256', 'fresh'),
        'is_checkpoint' => false,
        'created_at' => now()->subHours(1),
    ]);
    $document->update(['latest_sequence' => 4]);

    $this->artisan('vaults:prune-collaboration', ['--days' => 30])
        ->assertSuccessful();

    // Checkpoint 3 and fresh update 4 must still exist; superseded old updates 1 and 2 deleted
    expect(VaultCollaborationUpdate::where('id', $u1->id)->exists())->toBeFalse();
    expect(VaultCollaborationUpdate::where('id', $u2->id)->exists())->toBeFalse();
    expect(VaultCollaborationUpdate::where('id', $checkpoint->id)->exists())->toBeTrue();
    expect(VaultCollaborationUpdate::where('id', $latest->id)->exists())->toBeTrue();
});

test('prune-collaboration strictly preserves updates when no checkpoint exists to prevent CRDT corruption', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Collab Vault Uncheckpointed',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $document = VaultCollaborationDocument::create([
        'vault_id' => $vault->id,
        'path' => 'Notes/Uncheckpointed.md',
        'latest_sequence' => 3,
        'is_active' => true,
    ]);

    $u1 = $document->updates()->create([
        'sequence' => 1,
        'client_update_id' => 'up-1',
        'payload' => 'delta-1',
        'payload_sha256' => hash('sha256', 'delta-1'),
        'is_checkpoint' => false,
        'created_at' => now()->subDays(45),
    ]);
    $u2 = $document->updates()->create([
        'sequence' => 2,
        'client_update_id' => 'up-2',
        'payload' => 'delta-2',
        'payload_sha256' => hash('sha256', 'delta-2'),
        'is_checkpoint' => false,
        'created_at' => now()->subDays(40),
    ]);
    $u3 = $document->updates()->create([
        'sequence' => 3,
        'client_update_id' => 'up-3',
        'payload' => 'delta-3',
        'payload_sha256' => hash('sha256', 'delta-3'),
        'is_checkpoint' => false,
        'created_at' => now()->subDays(35),
    ]);

    $this->artisan('vaults:prune-collaboration', ['--days' => 30])
        ->assertSuccessful();

    // All updates must remain intact because deleting them without a checkpoint destroys CRDT replayability
    expect(VaultCollaborationUpdate::where('id', $u1->id)->exists())->toBeTrue();
    expect(VaultCollaborationUpdate::where('id', $u2->id)->exists())->toBeTrue();
    expect(VaultCollaborationUpdate::where('id', $u3->id)->exists())->toBeTrue();
});
