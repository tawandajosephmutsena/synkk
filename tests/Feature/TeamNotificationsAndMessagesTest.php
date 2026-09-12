<?php

use App\Models\DeviceToken;
use App\Models\TeamMessage;
use App\Models\TeamNotification;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultChangeLog;
use Livewire\Livewire;

test('dashboard automatically provisions real notifications and messages from system events', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Main Production Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    // Create a DLP secret detection event
    VaultChangeLog::create([
        'vault_id' => $vault->id,
        'user_id' => $user->id,
        'device_name' => 'Studio Mac',
        'path' => 'Credentials/vault.env',
        'action' => 'updated',
        'version' => 1,
        'size' => 1024,
        'has_secrets' => true,
        'detected_secrets' => ['AWS Access Key'],
    ]);

    // Create a paired device using createToken
    DeviceToken::createToken($user, $team, 'Obsidian Desktop (MacBook)', 'mac');

    $component = Livewire::actingAs($user)
        ->test('pages::dashboard.index', ['current_team' => $team->slug]);

    // Assert that real notifications exist in the DB
    $notifications = TeamNotification::where('team_id', $team->id)->get();
    expect($notifications->count())->toBeGreaterThanOrEqual(2);

    $dlpNotif = $notifications->firstWhere('type', 'security');
    expect($dlpNotif)->not->toBeNull()
        ->and($dlpNotif->title)->toContain('DLP Security Alert')
        ->and($dlpNotif->message)->toContain('Credentials/vault.env');

    $deviceNotif = $notifications->firstWhere('type', 'device');
    expect($deviceNotif)->not->toBeNull()
        ->and($deviceNotif->title)->toContain('Device Connected')
        ->and($deviceNotif->message)->toContain('Obsidian mac');

    // Assert that initial system team message exists
    $messages = TeamMessage::where('team_id', $team->id)->get();
    expect($messages)->not->toBeEmpty();
    expect($messages->first()->body)->toContain('Welcome to your team vault');

    // Drawer should render these
    $component->assertSee('DLP Security Alert')
        ->assertSee('Device Connected')
        ->assertSee('Welcome to your team vault');
});

test('team members can post real messages in the team drawer', function () {
    $user = User::factory()->create(['name' => 'Devon Vance']);
    $team = $user->personalTeam();

    Livewire::actingAs($user)
        ->test('pages::dashboard.index', ['current_team' => $team->slug])
        ->set('newMessageText', 'Hey team, I pushed the new obsidian sync settings!')
        ->call('sendTeamMessage')
        ->assertHasNoErrors()
        ->assertSet('newMessageText', '');

    $message = TeamMessage::where('team_id', $team->id)
        ->where('user_id', $user->id)
        ->first();

    expect($message)->not->toBeNull()
        ->and($message->body)->toBe('Hey team, I pushed the new obsidian sync settings!')
        ->and($message->type)->toBe('chat');
});

test('message posting validates that body is not empty', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    Livewire::actingAs($user)
        ->test('pages::dashboard.index', ['current_team' => $team->slug])
        ->set('newMessageText', '')
        ->call('sendTeamMessage')
        ->assertHasErrors(['newMessageText' => 'required']);
});

test('team member can dismiss individual notification', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $notif = TeamNotification::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'type' => 'info',
        'title' => 'Test Notification',
        'message' => 'This is a test notification to be dismissed.',
    ]);

    expect($notif->isRead())->toBeFalse();

    Livewire::actingAs($user)
        ->test('pages::dashboard.index', ['current_team' => $team->slug])
        ->call('dismissNotification', $notif->id);

    expect($notif->fresh()->isRead())->toBeTrue();
});

test('mark all as read clears all unread notifications and messages', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    TeamNotification::create([
        'team_id' => $team->id,
        'type' => 'info',
        'title' => 'Unread Alert 1',
        'message' => 'Alert 1 details',
    ]);

    TeamMessage::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'body' => 'Unread Message 1',
        'type' => 'chat',
    ]);

    expect(TeamNotification::where('team_id', $team->id)->unread()->count())->toBe(1);
    expect(TeamMessage::where('team_id', $team->id)->unread()->count())->toBe(1);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index', ['current_team' => $team->slug])
        ->call('markAllAsRead');

    expect(TeamNotification::where('team_id', $team->id)->unread()->count())->toBe(0);
    expect(TeamMessage::where('team_id', $team->id)->unread()->count())->toBe(0);
});
