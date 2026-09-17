<?php

use App\Models\User;
use App\Models\Vault;
use App\Models\VaultPortal;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('profile.edit'))->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('deleting an account preserves shared team vault data', function () {
    $user = User::factory()->create();
    $remainingMember = User::factory()->create();
    $team = $user->personalTeam();
    $team->members()->attach($remainingMember, ['role' => 'admin']);
    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Shared Knowledge',
        'slug' => 'shared-knowledge',
        'created_by' => $user->id,
    ]);
    $portal = VaultPortal::create([
        'team_id' => $team->id,
        'vault_id' => $vault->id,
        'created_by' => $user->id,
        'name' => 'Shared Portal',
        'slug' => 'shared-portal',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasNoErrors();

    expect($user->fresh())->toBeNull()
        ->and($vault->fresh())->not->toBeNull()
        ->and($vault->fresh()->created_by)->toBeNull()
        ->and($portal->fresh())->not->toBeNull()
        ->and($portal->fresh()->created_by)->toBeNull()
        ->and($remainingMember->belongsToTeam($team))->toBeTrue();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});
