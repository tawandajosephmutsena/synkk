<?php

use App\Enums\TeamRole;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    config(['synkk.storage_disk' => 'local']);
    Storage::fake('local');
});

/**
 * Helper to create a team, two users (owner + member), a vault, and a device token
 * for the member. Returns all objects for use in tests.
 *
 * @return array{owner: User, team: Team, member: User, vault: Vault, deviceToken: DeviceToken, plainToken: string}
 */
function createRemovalScenario(): array
{
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->update(['current_team_id' => $team->id]);

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Engineering Vault '.Str::random(6),
        'slug' => 'eng-vault-'.Str::random(6),
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    $tokenResult = DeviceToken::createToken($member, $team, 'Macbook Pro', 'mac');
    /** @var DeviceToken $deviceToken */
    $deviceToken = $tokenResult['device_token'];
    $plainToken = $tokenResult['plain_token'];

    return compact('owner', 'team', 'member', 'vault', 'deviceToken', 'plainToken');
}

// --- P0-03: Device Token Revocation on Member Removal ---

test('removing a team member deletes their device tokens for that team (P0-03)', function () {
    ['owner' => $owner, 'team' => $team, 'member' => $member, 'deviceToken' => $deviceToken] = createRemovalScenario();

    expect(DeviceToken::where('id', $deviceToken->id)->exists())->toBeTrue();

    $this->actingAs($owner);

    Livewire::test('pages::teams.remove-member-modal', [
        'team' => $team,
        'memberId' => $member->id,
        'memberName' => $member->name,
    ])
        ->call('removeMember')
        ->assertDispatched('close-modal');

    // Token must be deleted
    expect(DeviceToken::where('id', $deviceToken->id)->exists())->toBeFalse();
});

test('removed member device token returns 401 on vault API (P0-03)', function () {
    ['owner' => $owner, 'team' => $team, 'member' => $member, 'vault' => $vault, 'plainToken' => $plainToken] = createRemovalScenario();

    // Confirm token works before removal
    $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->getJson(route('api.vaults.manifest', ['vault' => $vault->slug]))
        ->assertOk();

    // Remove member (also deletes tokens)
    $this->actingAs($owner);
    Livewire::test('pages::teams.remove-member-modal', [
        'team' => $team,
        'memberId' => $member->id,
        'memberName' => $member->name,
    ])->call('removeMember');

    // Token must now be rejected
    $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->getJson(route('api.vaults.manifest', ['vault' => $vault->slug]))
        ->assertUnauthorized();
});

test('device tokens for other teams are unaffected when a member is removed from one team (P0-03)', function () {
    ['owner' => $owner, 'team' => $team, 'member' => $member] = createRemovalScenario();

    // Give the same member a token on a completely separate team
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($member, ['role' => TeamRole::Member->value]);
    $otherTokenResult = DeviceToken::createToken($member, $otherTeam, 'Other Device', 'mac');
    /** @var DeviceToken $otherToken */
    $otherToken = $otherTokenResult['device_token'];

    // Remove member from $team
    $this->actingAs($owner);
    Livewire::test('pages::teams.remove-member-modal', [
        'team' => $team,
        'memberId' => $member->id,
        'memberName' => $member->name,
    ])->call('removeMember');

    // Token for $otherTeam must still exist
    expect(DeviceToken::where('id', $otherToken->id)->exists())->toBeTrue();
});

test('middleware rejects a stale token for a removed member as defense-in-depth (P0-03)', function () {
    ['team' => $team, 'member' => $member, 'vault' => $vault, 'deviceToken' => $deviceToken, 'plainToken' => $plainToken] = createRemovalScenario();

    // Manually delete membership without going through removeMember() to simulate
    // a token that was NOT cleaned up on removal (tests the middleware guard).
    $team->memberships()->where('user_id', $member->id)->delete();

    // The token still exists in the DB — only membership is gone.
    expect(DeviceToken::where('id', $deviceToken->id)->exists())->toBeTrue();

    // Middleware must still reject the token due to the live membership check.
    $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->getJson(route('api.vaults.manifest', ['vault' => $vault->slug]))
        ->assertUnauthorized();
});
