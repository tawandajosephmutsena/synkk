<?php

use App\Actions\Collaboration\AppendCollaborationUpdateAction;
use App\Broadcasting\VaultCollaborationChannel;
use App\Events\VaultCollaborationUpdateCommitted;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultCollaborationDocument;
use App\ValueObjects\VaultContentEnvelope;
use Illuminate\Support\Facades\Event;
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
        'name' => 'Broadcast Vault',
        'default_permission' => 'read_write',
        'created_by' => $this->owner->id,
    ]);

    $this->document = VaultCollaborationDocument::create([
        'vault_id' => $this->vault->id,
        'path' => 'Notes/BroadcastPlan.md',
        'latest_sequence' => 0,
    ]);

    $this->channelHandler = app(VaultCollaborationChannel::class);
});

test('authorizes web owner and permitted member on private document channel', function () {
    $ownerResult = $this->channelHandler->join($this->owner, $this->document->id);
    expect($ownerResult)->not->toBeFalse();

    $memberResult = $this->channelHandler->join($this->member, $this->document->id);
    expect($memberResult)->not->toBeFalse();
});

test('refuses channel authorization for user with hidden path permission', function () {
    // Configure hidden path permission for member on 'Notes/'
    $this->vault->permissions()->create([
        'user_id' => $this->member->id,
        'path' => 'Notes',
        'permission' => 'hidden',
    ]);

    $result = $this->channelHandler->join($this->member, $this->document->id);
    expect($result)->toBeFalse();
});

test('authorizes permitted device token and read-only scoped device token', function () {
    $tokenResult = DeviceToken::createToken($this->member, $this->team, 'Bob Laptop', 'linux');
    $deviceToken = $tokenResult['device_token'];

    // Bind token to request attributes
    request()->attributes->set('device_token', $deviceToken);

    $result = $this->channelHandler->join($this->member, $this->document->id);
    expect($result)->not->toBeFalse();

    // Read-only device should also be authorized to receive updates
    $deviceToken->update(['access_scope' => 'read_only']);
    $readOnlyResult = $this->channelHandler->join($this->member, $this->document->id);
    expect($readOnlyResult)->not->toBeFalse();
});

test('refuses channel authorization for device token excluded from vault or wiped', function () {
    $otherVault = Vault::create([
        'team_id' => $this->team->id,
        'name' => 'Allowed Vault Only',
        'default_permission' => 'read_write',
        'created_by' => $this->owner->id,
    ]);

    $tokenResult = DeviceToken::createToken($this->member, $this->team, 'Restricted Device', 'mobile');
    $deviceToken = $tokenResult['device_token'];
    $deviceToken->update(['allowed_vault_ids' => [$otherVault->id]]);

    request()->attributes->set('device_token', $deviceToken);

    // Excluded from this->vault
    $excludedResult = $this->channelHandler->join($this->member, $this->document->id);
    expect($excludedResult)->toBeFalse();

    // Wiped token
    $deviceToken->update([
        'allowed_vault_ids' => null,
        'is_wiped' => true,
    ]);

    $wipedResult = $this->channelHandler->join($this->member, $this->document->id);
    expect($wipedResult)->toBeFalse();
});

test('refuses channel authorization for cross-team users and devices', function () {
    $otherTeam = Team::factory()->create();
    $otherUser = User::factory()->create();
    $otherTeam->members()->attach($otherUser, ['role' => 'owner']);
    $otherUser->update(['current_team_id' => $otherTeam->id]);

    $crossResult = $this->channelHandler->join($otherUser, $this->document->id);
    expect($crossResult)->toBeFalse();
});

test('dispatches VaultCollaborationUpdateCommitted exactly once after transaction commit', function () {
    Event::fake([VaultCollaborationUpdateCommitted::class]);

    $action = app(AppendCollaborationUpdateAction::class);
    $envelope = new VaultContentEnvelope(
        payload: base64_encode('LIVE_UPDATE_PAYLOAD'),
        payloadSha256: hash('sha256', base64_encode('LIVE_UPDATE_PAYLOAD')),
        plaintextSize: 19,
        encrypted: false,
    );

    $clientUpdateId = (string) Str::uuid();

    // 1. First append commits and fires event
    $action->execute(
        document: $this->document,
        clientUpdateId: $clientUpdateId,
        envelope: $envelope,
        user: $this->owner
    );

    Event::assertDispatched(VaultCollaborationUpdateCommitted::class, function ($event) use ($clientUpdateId) {
        return $event->documentId === $this->document->id
            && $event->sequence === 1
            && $event->clientUpdateId === $clientUpdateId
            && $event->isEncrypted === false
            && ! isset($event->user)
            && ! isset($event->deviceToken);
    });

    // 2. Duplicate append must NOT dispatch another event
    $action->execute(
        document: $this->document,
        clientUpdateId: $clientUpdateId,
        envelope: $envelope,
        user: $this->owner
    );

    Event::assertDispatchedTimes(VaultCollaborationUpdateCommitted::class, 1);
});

test('device token authenticates private channel over /api/v1/broadcasting/auth', function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('broadcasting.connections.reverb.secret', 'test-secret');
    config()->set('broadcasting.connections.reverb.app_id', '12345');
    require base_path('routes/channels.php');

    $tokenResult = DeviceToken::createToken($this->member, $this->team, 'Bob Mobile', 'ios');
    $token = $tokenResult['plain_token'];

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/v1/broadcasting/auth', [
            'channel_name' => "private-vault-collaboration.{$this->document->id}",
            'socket_id' => '1234.5678',
        ]);

    $response->assertOk()
        ->assertJsonStructure(['auth']);
});

test('refuses private channel over /api/v1/broadcasting/auth for invalid or wiped token', function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('broadcasting.connections.reverb.secret', 'test-secret');
    config()->set('broadcasting.connections.reverb.app_id', '12345');
    require base_path('routes/channels.php');

    // Invalid bearer token
    $invalidResponse = $this->withHeader('Authorization', 'Bearer invalid-token')
        ->post('/api/v1/broadcasting/auth', [
            'channel_name' => "private-vault-collaboration.{$this->document->id}",
            'socket_id' => '1234.5678',
        ]);
    $invalidResponse->assertStatus(401);

    // Wiped device token
    $tokenResult = DeviceToken::createToken($this->member, $this->team, 'Wiped Mobile', 'ios');
    $tokenResult['device_token']->update(['is_wiped' => true]);

    $wipedResponse = $this->withHeader('Authorization', "Bearer {$tokenResult['plain_token']}")
        ->post('/api/v1/broadcasting/auth', [
            'channel_name' => "private-vault-collaboration.{$this->document->id}",
            'socket_id' => '1234.5678',
        ]);
    $wipedResponse->assertStatus(410);
});

test('refuses private channel over /api/v1/broadcasting/auth when permission is hidden', function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('broadcasting.connections.reverb.secret', 'test-secret');
    config()->set('broadcasting.connections.reverb.app_id', '12345');
    require base_path('routes/channels.php');

    $this->vault->permissions()->create([
        'user_id' => $this->member->id,
        'path' => 'Notes',
        'permission' => 'hidden',
    ]);

    $tokenResult = DeviceToken::createToken($this->member, $this->team, 'Bob Mobile 2', 'ios');

    $response = $this->withHeader('Authorization', "Bearer {$tokenResult['plain_token']}")
        ->post('/api/v1/broadcasting/auth', [
            'channel_name' => "private-vault-collaboration.{$this->document->id}",
            'socket_id' => '1234.5678',
        ]);

    $response->assertStatus(403);
});
