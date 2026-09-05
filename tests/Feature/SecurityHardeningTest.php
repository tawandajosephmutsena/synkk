<?php

use App\Enums\TeamRole;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

test('vault policy prevents non-team members from viewing vault', function () {
    $owner = User::factory()->create();
    $teamA = Team::factory()->create();
    $teamA->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $vault = $teamA->vaults()->create([
        'name' => 'Secret Vault',
        'created_by' => $owner->id,
    ]);

    $outsider = User::factory()->create();

    expect($outsider->can('view', $vault))->toBeFalse()
        ->and($outsider->can('update', $vault))->toBeFalse()
        ->and($outsider->can('delete', $vault))->toBeFalse()
        ->and($outsider->can('managePermissions', $vault))->toBeFalse();
});

test('regular team members cannot delete vault or manage permissions', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $vault = $team->vaults()->create([
        'name' => 'Team Brain',
        'created_by' => $owner->id,
    ]);

    expect($member->can('view', $vault))->toBeTrue()
        ->and($member->can('delete', $vault))->toBeFalse()
        ->and($member->can('managePermissions', $vault))->toBeFalse()
        ->and($member->can('update', $vault))->toBeFalse();

    expect($owner->can('delete', $vault))->toBeTrue()
        ->and($owner->can('managePermissions', $vault))->toBeTrue()
        ->and($owner->can('update', $vault))->toBeTrue();
});

test('device token allowed vault scoping is enforced on API endpoints', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $allowedVault = $team->vaults()->create(['name' => 'Allowed Vault', 'created_by' => $user->id]);
    $restrictedVault = $team->vaults()->create(['name' => 'Restricted Vault', 'created_by' => $user->id]);

    $tokenResult = DeviceToken::createToken($user, $team, 'Scoped Phone', 'ios');
    $tokenResult['device_token']->update([
        'allowed_vault_ids' => [$allowedVault->id],
    ]);

    // Scoped index
    $indexResponse = $this->withHeaders(['Authorization' => "Bearer {$tokenResult['plain_token']}"])
        ->getJson(route('api.vaults.index'));

    $indexResponse->assertOk();
    $vaultIds = collect($indexResponse->json('vaults'))->pluck('id')->all();
    expect($vaultIds)->toContain($allowedVault->id)
        ->and($vaultIds)->not->toContain($restrictedVault->id);

    // Forbidden manifest on restricted vault
    $this->withHeaders(['Authorization' => "Bearer {$tokenResult['plain_token']}"])
        ->getJson(route('api.vaults.manifest', ['vault' => $restrictedVault->slug]))
        ->assertNotFound();

    // Allowed manifest on allowed vault
    $this->withHeaders(['Authorization' => "Bearer {$tokenResult['plain_token']}"])
        ->getJson(route('api.vaults.manifest', ['vault' => $allowedVault->slug]))
        ->assertOk();
});

test('read-only device tokens cannot upload or delete files', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $vault = $team->vaults()->create(['name' => 'Docs Vault', 'created_by' => $user->id]);

    $tokenResult = DeviceToken::createToken($user, $team, 'Read Only Device', 'mac');
    $tokenResult['device_token']->update([
        'access_scope' => 'read_only',
    ]);

    $uploadResponse = $this->withHeaders(['Authorization' => "Bearer {$tokenResult['plain_token']}"])
        ->postJson(route('api.vaults.upload', ['vault' => $vault->slug]), [
            'path' => 'notes/test.md',
            'content' => base64_encode('# Hello World'),
            'sha256' => hash('sha256', '# Hello World'),
        ]);

    $uploadResponse->assertStatus(403);

    $deleteResponse = $this->withHeaders(['Authorization' => "Bearer {$tokenResult['plain_token']}"])
        ->postJson(route('api.vaults.delete', ['vault' => $vault->slug]), [
            'path' => 'notes/test.md',
        ]);

    $deleteResponse->assertStatus(403);

    $batchResponse = $this->withHeaders(['Authorization' => "Bearer {$tokenResult['plain_token']}"])
        ->postJson(route('api.vaults.batch_sync', ['vault' => $vault->slug]), [
            'items' => [
                [
                    'path' => 'notes/test.md',
                    'action' => 'upload',
                    'content' => base64_encode('# Test'),
                    'sha256' => hash('sha256', '# Test'),
                ],
            ],
        ]);

    $batchResponse->assertStatus(403);
});

test('api rate limiter is registered and enforces request bounds', function () {
    $limiter = RateLimiter::limiter('api');
    expect($limiter)->not->toBeNull();

    $request = Request::create('/api/v1/vaults', 'GET');
    $request->headers->set('Authorization', 'Bearer synkk_test_token');

    $limit = $limiter($request);
    expect($limit->maxAttempts)->toBe(300)
        ->and($limit->decaySeconds)->toBe(60)
        ->and($limit->key)->toBe('synkk_test_token');
});
