<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Services\CrdtCollabService;

test('CrdtCollabService handles joining, presence, and deterministic delta application', function () {
    $user1 = User::factory()->create(['name' => 'Alice']);
    $user2 = User::factory()->create(['name' => 'Bob']);
    $team = Team::factory()->create();
    $team->members()->attach([$user1->id => ['role' => 'owner'], $user2->id => ['role' => 'member']]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Team Vault',
        'default_permission' => 'read_write',
        'created_by' => $user1->id,
    ]);

    $service = new CrdtCollabService;
    $notePath = 'Notes/Meeting.md';

    // 1. Peer 1 joins
    $join1 = $service->join($vault, $user1, $notePath, 'peer_alice');
    expect($join1['status'])->toBe('joined')
        ->and(count($join1['peers']))->toBe(1)
        ->and($join1['peers'][0]['name'])->toBe('Alice');

    // 2. Peer 2 joins
    $join2 = $service->join($vault, $user2, $notePath, 'peer_bob');
    expect($join2['status'])->toBe('joined')
        ->and(count($join2['peers']))->toBe(2);

    // 3. Peer 1 sends deltas
    $sync1 = $service->sync(
        vault: $vault,
        user: $user1,
        path: $notePath,
        peerId: 'peer_alice',
        localDeltas: [
            ['type' => 'insert', 'pos' => 0, 'text' => 'Hello World'],
        ],
        cursor: ['line' => 1, 'col' => 11],
        sinceClock: 0
    );

    expect($sync1['clock'])->toBe(1);

    // 4. Peer 2 syncs and receives Peer 1's deltas
    $sync2 = $service->sync(
        vault: $vault,
        user: $user2,
        path: $notePath,
        peerId: 'peer_bob',
        localDeltas: [],
        cursor: ['line' => 1, 'col' => 0],
        sinceClock: 0
    );

    expect($sync2['incoming_deltas'])->toHaveCount(1)
        ->and($sync2['incoming_deltas'][0]['text'])->toBe('Hello World')
        ->and($sync2['incoming_deltas'][0]['peer_id'])->toBe('peer_alice');

    // 5. Apply deltas deterministically
    $baseContent = '';
    $finalContent = $service->applyDeltasToContent($baseContent, [
        ['type' => 'insert', 'pos' => 0, 'text' => 'Hello '],
        ['type' => 'insert', 'pos' => 6, 'text' => 'World!'],
    ]);
    expect($finalContent)->toBe('Hello World!');

    // 6. Peer 1 leaves
    $service->leave($vault, $notePath, 'peer_alice');
    $presence = $service->getPresence($vault, $notePath);
    expect($presence)->toHaveCount(1)
        ->and($presence[0]['peer_id'])->toBe('peer_bob');
});

test('API endpoints support CRDT multiplayer join, sync, leave, and presence', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Collab Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'MacBook Pro', 'mac');
    $token = $tokenResult['plain_token'];

    // 1. Join room
    $joinRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/collab/join", [
            'path' => 'CollabNote.md',
            'peer_id' => 'obsidian_client_1',
        ]);

    $joinRes->assertOk()
        ->assertJsonPath('status', 'joined')
        ->assertJsonStructure(['room_id', 'clock', 'peers', 'deltas']);

    // 2. Sync deltas & presence
    $syncRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/collab/sync", [
            'path' => 'CollabNote.md',
            'peer_id' => 'obsidian_client_1',
            'deltas' => [
                ['type' => 'insert', 'pos' => 0, 'text' => 'Live Collab Edit'],
            ],
            'cursor' => ['line' => 2, 'col' => 5],
            'since_clock' => 0,
        ]);

    $syncRes->assertOk()
        ->assertJsonPath('status', 'synced')
        ->assertJsonPath('clock', 1);

    // 3. Check presence
    $presenceRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/vaults/{$vault->slug}/collab/presence?path=CollabNote.md");

    $presenceRes->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonCount(1, 'peers')
        ->assertJsonPath('peers.0.peer_id', 'obsidian_client_1');

    // 4. Leave room
    $leaveRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/collab/leave", [
            'path' => 'CollabNote.md',
            'peer_id' => 'obsidian_client_1',
        ]);

    $leaveRes->assertOk()
        ->assertJsonPath('status', 'left');
});
