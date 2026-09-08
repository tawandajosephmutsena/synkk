<?php

use App\Enums\TeamRole;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['synkk.storage_disk' => 'local']);
    Storage::fake('local');
});

function createVaultAuthScenario(
    string $userRole = TeamRole::Member->value,
    string $accessScope = 'read_write',
    ?array $allowedVaultIds = null,
    string $defaultPermission = 'read_write',
): array {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => $userRole]);
    $user->update(['current_team_id' => $team->id]);

    $uniqueName = 'Engineering Vault '.Str::random(8);
    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => $uniqueName,
        'slug' => Str::slug($uniqueName),
        'default_permission' => $defaultPermission,
        'created_by' => $owner->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'Test Device '.Str::random(5), 'mac');
    /** @var DeviceToken $deviceToken */
    $deviceToken = $tokenResult['device_token'];
    $deviceToken->update([
        'access_scope' => $accessScope,
        'allowed_vault_ids' => $allowedVaultIds,
    ]);

    return [$vault, $tokenResult['plain_token'], $deviceToken, $user, $team];
}

function createConflictFiles(Vault $vault, User $user, string $canonicalPath = 'Notes/project.md'): array
{
    $conflictPath = 'Notes/project.conflict-20260908-120000.md';

    $canonicalFile = VaultFile::create([
        'vault_id' => $vault->id,
        'path' => $canonicalPath,
        'version' => 1,
        'storage_path' => 'vaults/'.$vault->id.'/canonical.md',
        'sha256' => hash('sha256', '# Canonical Content'),
        'size' => strlen('# Canonical Content'),
        'mime_type' => 'text/markdown',
        'created_by' => $user->id,
        'last_modified_by' => $user->id,
    ]);
    Storage::disk('local')->put($canonicalFile->storage_path, '# Canonical Content');

    $conflictFile = VaultFile::create([
        'vault_id' => $vault->id,
        'path' => $conflictPath,
        'version' => 1,
        'storage_path' => 'vaults/'.$vault->id.'/conflict.md',
        'sha256' => hash('sha256', '# Conflict Content'),
        'size' => strlen('# Conflict Content'),
        'mime_type' => 'text/markdown',
        'created_by' => $user->id,
        'last_modified_by' => $user->id,
    ]);
    Storage::disk('local')->put($conflictFile->storage_path, '# Conflict Content');

    return [$canonicalFile, $conflictFile];
}

test('returns 403 when a read-only device attempts to resolve a conflict', function () {
    [$vault, $plainToken, $deviceToken, $user] = createVaultAuthScenario(accessScope: 'read_only');
    [$canonicalFile, $conflictFile] = createConflictFiles($vault, $user);

    $response = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson(route('api.vaults.conflicts.resolve', ['vault' => $vault->slug]), [
            'canonical_path' => $canonicalFile->path,
            'conflict_path' => $conflictFile->path,
            'resolved_content' => '# Resolved Forbidden Content',
        ]);

    $response->assertForbidden();
    expect($canonicalFile->fresh()->sha256)->toBe($canonicalFile->sha256)
        ->and($conflictFile->fresh()->is_deleted)->toBeFalse();
});

test('returns 403 when a read-only device attempts to dehydrate a file', function () {
    [$vault, $plainToken, $deviceToken, $user] = createVaultAuthScenario(accessScope: 'read_only');
    [$canonicalFile] = createConflictFiles($vault, $user);

    $response = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson(route('api.vaults.files.dehydrate', ['vault' => $vault->slug]), [
            'path' => $canonicalFile->path,
        ]);

    $response->assertForbidden();
    expect($canonicalFile->fresh()->is_ghost)->toBeFalse();
});

test('returns 403 when a non-admin or read-only token attempts to enable or disable E2EE', function () {
    // 1. Read-only token (owner user)
    [$vault, $readOnlyToken] = createVaultAuthScenario(userRole: TeamRole::Owner->value, accessScope: 'read_only');
    $this->withHeader('Authorization', "Bearer {$readOnlyToken}")
        ->postJson(route('api.vaults.e2ee.enable', ['vault' => $vault->slug]), [
            'salt' => bin2hex(random_bytes(16)),
            'test_cipher' => 'valid-cipher',
        ])->assertForbidden();

    // 2. Regular member with read_write scope (cannot administer vault security)
    [$vault2, $memberToken] = createVaultAuthScenario(userRole: TeamRole::Member->value, accessScope: 'read_write');
    $this->withHeader('Authorization', "Bearer {$memberToken}")
        ->postJson(route('api.vaults.e2ee.enable', ['vault' => $vault2->slug]), [
            'salt' => bin2hex(random_bytes(16)),
            'test_cipher' => 'valid-cipher',
        ])->assertForbidden();
});

test('returns 403 when a read-only device attempts to create a QR pairing session', function () {
    [$vault, $plainToken] = createVaultAuthScenario(accessScope: 'read_only');

    $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson('/api/v1/pairing/session')
        ->assertForbidden();
});

test('returns 404 when attempting to diff or resolve a conflict on a hidden path', function () {
    [$vault, $plainToken, $deviceToken, $user] = createVaultAuthScenario();
    [$canonicalFile, $conflictFile] = createConflictFiles($vault, $user, 'Secret/classified.md');

    // Make 'Secret' path hidden for this user
    $vault->permissions()->create([
        'user_id' => $user->id,
        'path' => 'Secret',
        'permission' => 'hidden',
    ]);

    $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson(route('api.vaults.conflicts.diff', ['vault' => $vault->slug]), [
            'conflict_path' => $conflictFile->path,
            'canonical_path' => $canonicalFile->path,
        ])->assertNotFound();

    $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson(route('api.vaults.conflicts.resolve', ['vault' => $vault->slug]), [
            'canonical_path' => $canonicalFile->path,
            'conflict_path' => $conflictFile->path,
            'resolved_content' => '# Forbidden',
        ])->assertNotFound();
});

test('returns 403 when conflict path does not map to canonical path', function () {
    [$vault, $plainToken, $deviceToken, $user] = createVaultAuthScenario();
    [$canonicalFile, $conflictFile] = createConflictFiles($vault, $user);

    // Provide an unrelated canonical path
    $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson(route('api.vaults.conflicts.resolve', ['vault' => $vault->slug]), [
            'canonical_path' => 'Notes/completely-unrelated.md',
            'conflict_path' => $conflictFile->path,
            'resolved_content' => '# Malicious mismatch',
        ])->assertForbidden();
});

test('returns 404 when accessing a vault outside allowed_vault_ids or belonging to another team', function () {
    [$vault, $plainToken, $deviceToken] = createVaultAuthScenario(allowedVaultIds: [99999]);

    $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->getJson(route('api.vaults.manifest', ['vault' => $vault->slug]))
        ->assertNotFound();

    // Cross-team vault
    $otherTeam = Team::factory()->create();
    $otherVault = Vault::create([
        'team_id' => $otherTeam->id,
        'name' => 'Alien Vault',
        'default_permission' => 'read_write',
        'created_by' => User::factory()->create()->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->getJson(route('api.vaults.manifest', ['vault' => $otherVault->slug]))
        ->assertNotFound();
});

test('returns 403 when a non-admin attempts to trigger RAG indexing', function () {
    [$vault, $plainToken] = createVaultAuthScenario(userRole: TeamRole::Member->value, accessScope: 'read_write');

    $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson(route('api.vaults.rag.index', ['vault' => $vault->slug]))
        ->assertForbidden();
});
