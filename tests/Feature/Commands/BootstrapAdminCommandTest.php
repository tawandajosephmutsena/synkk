<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('provisions a new administrator and creates a personal team headlessly', function () {
    $this->artisan('synkk:bootstrap-admin', [
        '--email' => 'admin@synkk.space',
        '--password' => 'SecurePass123!@#',
        '--name' => 'Root Admin',
    ])
        ->expectsOutput('Administrator [admin@synkk.space] provisioned successfully.')
        ->assertSuccessful();

    $user = User::where('email', 'admin@synkk.space')->first();

    expect($user)->not->toBeNull();
    expect($user->is_super_admin)->toBeTrue();
    expect($user->name)->toBe('Root Admin');
    expect(Hash::check('SecurePass123!@#', $user->password))->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->currentTeam)->not->toBeNull();
    expect($user->currentTeam->name)->toBe("Root Admin's Team");
});

it('updates an existing user to super admin with new password', function () {
    $existing = User::factory()->create([
        'email' => 'existing@synkk.space',
        'is_super_admin' => false,
    ]);

    $this->artisan('synkk:bootstrap-admin', [
        '--email' => 'existing@synkk.space',
        '--password' => 'NewSecurePassword456!@#',
        '--name' => 'Promoted Admin',
    ])
        ->expectsOutput('Administrator [existing@synkk.space] updated successfully.')
        ->assertSuccessful();

    $existing->refresh();

    expect($existing->is_super_admin)->toBeTrue();
    expect($existing->name)->toBe('Promoted Admin');
    expect(Hash::check('NewSecurePassword456!@#', $existing->password))->toBeTrue();
});

it('fails validation on weak password or invalid email', function () {
    $this->artisan('synkk:bootstrap-admin', [
        '--email' => 'invalid-email',
        '--password' => '123',
    ])
        ->assertFailed();
});
