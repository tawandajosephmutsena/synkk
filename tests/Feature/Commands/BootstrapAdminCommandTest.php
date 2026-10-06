<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * @param  array<string, string|null>  $variables
 */
function withBootstrapEnvironment(array $variables, Closure $callback): void
{
    $previousValues = [];

    foreach ($variables as $name => $value) {
        $previousValues[$name] = getenv($name);
        $value === null ? putenv($name) : putenv("{$name}={$value}");
    }

    try {
        $callback();
    } finally {
        foreach ($previousValues as $name => $value) {
            $value === false ? putenv($name) : putenv("{$name}={$value}");
        }
    }
}

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

it('bootstraps the first administrator from environment without exposing or changing the password on restart', function () {
    withBootstrapEnvironment([
        'SYNKK_BOOTSTRAP_EMAIL' => 'owner@example.com',
        'SYNKK_BOOTSTRAP_PASSWORD' => 'SecureBootstrapPassword123!',
        'SYNKK_BOOTSTRAP_NAME' => 'Pod Owner',
        'SYNKK_BOOTSTRAP_PASSWORD_SOURCE' => null,
    ], function (): void {
        $this->artisan('synkk:bootstrap-admin', ['--if-empty' => true, '--no-interaction' => true])
            ->expectsOutput('Administrator [owner@example.com] provisioned successfully.')
            ->assertSuccessful();

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();

        expect($user->isSuperAdmin())->toBeTrue();
        expect($user->hasVerifiedEmail())->toBeTrue();
        expect($user->currentTeam?->name)->toBe("Pod Owner's Team");
        expect(Hash::check('SecureBootstrapPassword123!', $user->password))->toBeTrue();

        $this->post(route('login.store'), [
            'email' => 'owner@example.com',
            'password' => 'SecureBootstrapPassword123!',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->get(route('dashboard', ['current_team' => $user->currentTeam->slug]))
            ->assertOk();

        putenv('SYNKK_BOOTSTRAP_PASSWORD=DifferentBootstrapPassword456!');

        $this->artisan('synkk:bootstrap-admin', ['--if-empty' => true, '--no-interaction' => true])
            ->expectsOutput('Administrator bootstrap skipped because users already exist.')
            ->assertSuccessful();

        expect(User::query()->count())->toBe(1);
        expect(Hash::check('SecureBootstrapPassword123!', $user->fresh()->password))->toBeTrue();
    });
});

it('does not promote or change any existing user during guarded bootstrap', function () {
    $user = User::factory()->create([
        'is_super_admin' => false,
        'email' => 'owner@example.com',
    ]);
    $originalPassword = $user->password;

    $this->artisan('synkk:bootstrap-admin', ['--if-empty' => true, '--no-interaction' => true])
        ->expectsOutput('Administrator bootstrap skipped because users already exist.')
        ->assertSuccessful();

    expect($user->fresh()->isSuperAdmin())->toBeFalse();
    expect($user->fresh()->password)->toBe($originalPassword);
});

it('fails closed when first-run bootstrap credentials are missing', function () {
    withBootstrapEnvironment([
        'SYNKK_BOOTSTRAP_EMAIL' => null,
        'SYNKK_BOOTSTRAP_PASSWORD' => null,
        'SYNKK_BOOTSTRAP_NAME' => null,
        'SYNKK_BOOTSTRAP_PASSWORD_SOURCE' => null,
    ], function (): void {
        $this->artisan('synkk:bootstrap-admin', ['--if-empty' => true, '--no-interaction' => true])
            ->expectsOutput('SYNKK_BOOTSTRAP_EMAIL and SYNKK_BOOTSTRAP_PASSWORD are required for first-run bootstrap.')
            ->assertFailed();

        expect(User::query()->count())->toBe(0);
    });
});

it('refuses first-run command-line credentials', function () {
    $this->artisan('synkk:bootstrap-admin', [
        '--if-empty' => true,
        '--no-interaction' => true,
        '--email' => 'owner@example.com',
        '--password' => 'SecureBootstrapPassword123!',
    ])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('accepts only the trusted Umbrel generated password format as a first-run exception', function () {
    withBootstrapEnvironment([
        'SYNKK_BOOTSTRAP_EMAIL' => 'admin@umbrel.local',
        'SYNKK_BOOTSTRAP_PASSWORD' => str_repeat('a4', 32),
        'SYNKK_BOOTSTRAP_NAME' => 'Umbrel Owner',
        'SYNKK_BOOTSTRAP_PASSWORD_SOURCE' => 'umbrel',
    ], function (): void {
        $this->artisan('synkk:bootstrap-admin', ['--if-empty' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $user = User::query()->where('email', 'admin@umbrel.local')->firstOrFail();

        expect($user->hasVerifiedEmail())->toBeTrue();
        expect(Hash::check(str_repeat('a4', 32), $user->password))->toBeTrue();
    });
});

it('rejects a malformed Umbrel generated password without creating an account', function () {
    withBootstrapEnvironment([
        'SYNKK_BOOTSTRAP_EMAIL' => 'admin@umbrel.local',
        'SYNKK_BOOTSTRAP_PASSWORD' => str_repeat('a4', 16),
        'SYNKK_BOOTSTRAP_NAME' => 'Umbrel Owner',
        'SYNKK_BOOTSTRAP_PASSWORD_SOURCE' => 'umbrel',
    ], function (): void {
        $this->artisan('synkk:bootstrap-admin', ['--if-empty' => true, '--no-interaction' => true])
            ->assertFailed();

        expect(User::query()->count())->toBe(0);
    });
});
