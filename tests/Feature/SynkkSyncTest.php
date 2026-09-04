<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultChangeLog;
use App\Models\VaultPermission;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('can authenticate with device token and verify profile', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $tokenResult = DeviceToken::createToken($user, $team, 'iPhone 15', 'ios');
    $token = $tokenResult['plain_token'];

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/verify');

    $response->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('user.name', $user->name)
        ->assertJsonPath('team.name', $team->name)
        ->assertJsonPath('device.platform', 'ios');
});

test('can list accessible vaults', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Ottomate Knowledge',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'MacBook Pro', 'mac');

    $response = $this->withHeader('Authorization', "Bearer {$tokenResult['plain_token']}")
        ->getJson('/api/v1/vaults');

    $response->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonCount(1, 'vaults')
        ->assertJsonPath('vaults.0.name', 'Ottomate Knowledge');
});

test('can upload notes and download them via sync API', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Demo Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'MacBook Pro', 'mac');
    $token = $tokenResult['plain_token'];

    // 1. Upload a markdown note
    $noteContent = "# Hello Synkk\nThis is a synchronized test note.";
    $uploadRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/upload", [
            'path' => '01 - Projects/Synkk.md',
            'content_base64' => base64_encode($noteContent),
            'base_version' => 0,
        ]);

    $uploadRes->assertCreated()
        ->assertJsonPath('status', 'created')
        ->assertJsonPath('version', 1);

    // 2. Request manifest
    $manifestRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/vaults/{$vault->slug}/manifest");

    $manifestRes->assertOk()
        ->assertJsonCount(1, 'files')
        ->assertJsonPath('files.0.path', '01 - Projects/Synkk.md')
        ->assertJsonPath('files.0.version', 1);

    // 3. Download the note
    $downloadRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->get("/api/v1/vaults/{$vault->slug}/download?path=01+-+Projects/Synkk.md");

    $downloadRes->assertOk();
    expect($downloadRes->streamedContent())->toBe($noteContent);
});

test('enforces folder and file level permissions with hidden and read_only rules', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => 'owner']);
    $team->members()->attach($member, ['role' => 'member']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Company Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    $ownerToken = DeviceToken::createToken($owner, $team, 'Owner Mac', 'mac')['plain_token'];
    $memberToken = DeviceToken::createToken($member, $team, 'Member Phone', 'ios')['plain_token'];

    // Owner uploads three files:
    // 1. General note
    $this->withHeader('Authorization', "Bearer {$ownerToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/upload", [
            'path' => 'General.md',
            'content_base64' => base64_encode('General content'),
            'base_version' => 0,
        ]);

    // 2. Secret finance folder
    $this->withHeader('Authorization', "Bearer {$ownerToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/upload", [
            'path' => '02 - Finance/Salaries.md',
            'content_base64' => base64_encode('Confidential salary sheet'),
            'base_version' => 0,
        ]);

    // 3. Read-only policies folder
    $this->withHeader('Authorization', "Bearer {$ownerToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/upload", [
            'path' => 'Policies/CompanyPolicy.md',
            'content_base64' => base64_encode('Company rules'),
            'base_version' => 0,
        ]);

    // Create permission rules:
    // Rule A: '02 - Finance' is HIDDEN for $member
    VaultPermission::create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => '02 - Finance',
        'permission' => 'hidden',
        'is_folder' => true,
    ]);

    // Rule B: 'Policies' is READ_ONLY for $member
    VaultPermission::create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => 'Policies',
        'permission' => 'read_only',
        'is_folder' => true,
    ]);

    // Check member's manifest:
    $memberManifest = $this->withHeader('Authorization', "Bearer {$memberToken}")
        ->getJson("/api/v1/vaults/{$vault->slug}/manifest");

    $memberManifest->assertOk();
    $paths = collect($memberManifest->json('files'))->pluck('path')->toArray();

    // Member sees 'General.md' and 'Policies/CompanyPolicy.md'
    expect($paths)->toContain('General.md');
    expect($paths)->toContain('Policies/CompanyPolicy.md');

    // BUT '02 - Finance/Salaries.md' MUST BE HIDDEN completely!
    expect($paths)->not->toContain('02 - Finance/Salaries.md');

    // Member tries to modify read-only file -> Must return 403 Forbidden!
    $forbiddenRes = $this->withHeader('Authorization', "Bearer {$memberToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/upload", [
            'path' => 'Policies/CompanyPolicy.md',
            'content_base64' => base64_encode('Hacked policy'),
            'base_version' => 1,
        ]);

    $forbiddenRes->assertStatus(403);

    // Member tries to access hidden file -> Must return 404/denied
    $hiddenRes = $this->withHeader('Authorization', "Bearer {$memberToken}")
        ->get("/api/v1/vaults/{$vault->slug}/download?path=02+-+Finance/Salaries.md");

    $hiddenRes->assertStatus(404);
});

test('handles concurrent conflicts without data loss by creating conflict branch', function () {
    $user1 = User::factory()->create(['name' => 'Alice']);
    $user2 = User::factory()->create(['name' => 'Bob']);
    $team = Team::factory()->create();

    $team->members()->attach($user1, ['role' => 'owner']);
    $team->members()->attach($user2, ['role' => 'member']);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Collaboration Vault',
        'default_permission' => 'read_write',
        'created_by' => $user1->id,
    ]);

    $token1 = DeviceToken::createToken($user1, $team, 'Alice Mac', 'mac')['plain_token'];
    $token2 = DeviceToken::createToken($user2, $team, 'Bob Windows', 'windows')['plain_token'];

    // 1. Alice creates note v1
    $this->withHeader('Authorization', "Bearer {$token1}")
        ->postJson("/api/v1/vaults/{$vault->slug}/upload", [
            'path' => 'TeamMeeting.md',
            'content_base64' => base64_encode('Initial notes'),
            'base_version' => 0,
        ]);

    // 2. Alice updates note to v2
    $this->withHeader('Authorization', "Bearer {$token1}")
        ->postJson("/api/v1/vaults/{$vault->slug}/upload", [
            'path' => 'TeamMeeting.md',
            'content_base64' => base64_encode('Alice edited this note'),
            'base_version' => 1,
        ]);

    // 3. Bob was offline and tries to push his edits based on old v1
    $bobRes = $this->withHeader('Authorization', "Bearer {$token2}")
        ->postJson("/api/v1/vaults/{$vault->slug}/upload", [
            'path' => 'TeamMeeting.md',
            'content_base64' => base64_encode('Bob edited this note concurrently'),
            'base_version' => 1, // Bob thinks it is v1, but it is already v2
        ]);

    $bobRes->assertOk()
        ->assertJsonPath('status', 'conflict')
        ->assertJsonPath('is_conflict', true);

    $conflictPath = $bobRes->json('path');
    expect($conflictPath)->toContain('TeamMeeting.conflict-bob-');

    // Both files must now exist in the vault!
    $manifest = $this->withHeader('Authorization', "Bearer {$token1}")
        ->getJson("/api/v1/vaults/{$vault->slug}/manifest");

    $manifestPaths = collect($manifest->json('files'))->pluck('path')->toArray();
    expect($manifestPaths)->toContain('TeamMeeting.md');
    expect($manifestPaths)->toContain($conflictPath);
});

test('detects sensitive API keys and records DLP secret flags in sync change logs', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Security Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $token = DeviceToken::createToken($user, $team, 'MacBook', 'mac')['plain_token'];

    $secretContent = "# Sensitive Config\nAWS_KEY=AKIAIOSFODNN7EXAMPLE\ngithub_pat=ghp_1234567890abcdef1234567890abcdef1234";

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/upload", [
            'path' => 'Secrets/env.md',
            'content_base64' => base64_encode($secretContent),
            'base_version' => 0,
        ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'created')
        ->assertJsonPath('has_secrets', true);

    $log = VaultChangeLog::where('vault_id', $vault->id)
        ->where('path', 'Secrets/env.md')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->has_secrets)->toBeTrue();
    expect($log->detected_secrets)->toContain('AWS Access Key ID');
});
