<?php

use App\Actions\Vaults\ResolveConflictAction;
use App\Actions\Vaults\SyncUploadAction;
use App\Enums\TeamRole;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('device token cannot access another teams vault secondary endpoints (multi-tenant boundary)', function () {
    // Team 1 with User 1 and Device 1
    $user1 = User::factory()->create();
    $team1 = Team::factory()->create();
    $team1->members()->attach($user1, ['role' => TeamRole::Owner->value]);
    $tokenResult1 = DeviceToken::createToken($user1, $team1, 'MacBook 1');
    $token1 = $tokenResult1['plain_token'];

    // Team 2 with Vault 2
    $user2 = User::factory()->create();
    $team2 = Team::factory()->create();
    $team2->members()->attach($user2, ['role' => TeamRole::Owner->value]);
    $vault2 = Vault::create([
        'team_id' => $team2->id,
        'name' => 'Confidential Vault Team 2',
        'default_permission' => 'read_write',
        'created_by' => $user2->id,
    ]);

    // Token 1 attempts to access Team 2's vault conflicts
    $response = $this->withHeader('Authorization', "Bearer {$token1}")
        ->getJson(route('api.vaults.conflicts.index', ['vault' => $vault2->slug]));
    $response->assertStatus(404);

    // Token 1 attempts to access Team 2's collab join
    $response = $this->withHeader('Authorization', "Bearer {$token1}")
        ->postJson(route('api.vaults.collab.join', ['vault' => $vault2->slug]), [
            'path' => 'Secret.md',
            'peer_id' => 'peer_hacker',
        ]);
    $response->assertStatus(404);

    // Token 1 attempts to access Team 2's RAG query
    $response = $this->withHeader('Authorization', "Bearer {$token1}")
        ->postJson(route('api.vaults.rag.query', ['vault' => $vault2->slug]), [
            'query' => 'What is the secret financial plan?',
        ]);
    $response->assertStatus(404);

    // Token 1 attempts to access Team 2's transport status
    $response = $this->withHeader('Authorization', "Bearer {$token1}")
        ->getJson(route('api.vaults.transport.status', ['vault' => $vault2->slug]));
    $response->assertStatus(404);
});

test('conflict resolution increments conflict file version and propagates tombstone in incremental sync', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Engineering',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'MacBook');
    $token = $tokenResult['plain_token'];

    $uploadAction = app(SyncUploadAction::class);

    // 1. Initial note at version 1
    $res1 = $uploadAction->execute(
        vault: $vault,
        user: $user,
        deviceName: 'Desktop',
        path: 'Architecture.md',
        contents: "# Architecture\nBase line.",
        baseVersion: 0,
    );
    expect($res1['version'])->toBe(1);

    // 2. Conflict note created at version 2
    $conflictPath = 'Architecture.conflict-alice-20260908.md';
    $resConflict = $uploadAction->execute(
        vault: $vault,
        user: $user,
        deviceName: 'Mobile',
        path: $conflictPath,
        contents: "# Architecture\nConflicting edits.",
        baseVersion: 0,
    );
    expect($resConflict['version'])->toBe(2);

    $conflictFile = $vault->files()->where('path', $conflictPath)->first();
    expect($conflictFile->version)->toBe(2);

    // 3. Resolve conflict (creates v3 for canonical, soft-deletes conflict file)
    $resolveAction = app(ResolveConflictAction::class);
    $resolvedResult = $resolveAction->execute(
        vault: $vault,
        user: $user,
        canonicalPath: 'Architecture.md',
        conflictPath: $conflictPath,
        resolvedContent: "# Architecture\nReconciled content.",
        deviceName: 'Visual Conflict Sandbox',
    );

    $canonicalFile = $vault->files()->where('path', 'Architecture.md')->first();
    $conflictFile->refresh();

    expect($conflictFile->is_deleted)->toBeTrue()
        ->and($conflictFile->version)->toBe($canonicalFile->version)
        ->and($conflictFile->version)->toBeGreaterThan(2);

    // 4. Client at version 2 asks for changes since_version=2
    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('api.vaults.manifest', ['vault' => $vault->slug, 'since_version' => 2]));

    $response->assertOk();
    $data = $response->json();

    // The tombstone for the resolved conflict MUST be returned so other devices delete their local copy!
    $deletedPaths = collect($data['deleted'])->pluck('path')->all();
    expect($deletedPaths)->toContain($conflictPath);
});
