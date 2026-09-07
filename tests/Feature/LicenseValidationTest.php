<?php

use App\Models\Team;
use App\Services\LicenseValidationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('can activate test lifetime license on team', function () {
    $team = Team::factory()->create();

    $service = app(LicenseValidationService::class);
    $result = $service->activateLicenseKey($team, 'SYNKK-LIFETIME-TEST-KEY-12345');

    expect($result['success'])->toBeTrue();
    $team->refresh();
    expect($team->license_status)->toBe('active');
    expect($team->license_key)->toBe('SYNKK-LIFETIME-TEST-KEY-12345');
    expect($team->hasActiveLicense())->toBeTrue();
});

test('license verification does not expose connection details', function () {
    $team = Team::factory()->create();

    Http::fake(function (): never {
        throw new ConnectionException('Connection refused by internal license host.');
    });

    $result = app(LicenseValidationService::class)->activateLicenseKey($team, 'production-license-key');

    expect($result)
        ->toMatchArray([
            'success' => false,
            'message' => 'License verification is temporarily unavailable. Please try again later.',
        ])
        ->not->toHaveKey('license');
});

test('can activate internal synkk pro commercial license key and upgrade plan', function () {
    $team = Team::factory()->create(['plan' => 'free']);
    $service = app(LicenseValidationService::class);

    $result = $service->activateLicenseKey($team, 'SYNK-PRO-7A9B-4C2E-8F1D');

    expect($result['success'])->toBeTrue()
        ->and($result['plan'])->toBe('pro_ltd')
        ->and($team->fresh()->plan)->toBe('pro_ltd')
        ->and($team->fresh()->license_status)->toBe('active')
        ->and($team->fresh()->license_key)->toBe('SYNK-PRO-7A9B-4C2E-8F1D');
});

test('can activate internal synkk cloud commercial license key and upgrade plan', function () {
    $team = Team::factory()->create(['plan' => 'free']);
    $service = app(LicenseValidationService::class);

    $result = $service->activateLicenseKey($team, 'SYNK-CLOUD-1122-3344-5566');

    expect($result['success'])->toBeTrue()
        ->and($result['plan'])->toBe('cloud')
        ->and($team->fresh()->plan)->toBe('cloud')
        ->and($team->fresh()->license_status)->toBe('active');
});

test('prevents redeeming the same active license key across multiple teams', function () {
    $team1 = Team::factory()->create(['plan' => 'free']);
    $team2 = Team::factory()->create(['plan' => 'free']);
    $service = app(LicenseValidationService::class);

    $res1 = $service->activateLicenseKey($team1, 'SYNK-PRO-DUPL-ICAT-EKEY');
    expect($res1['success'])->toBeTrue();

    $res2 = $service->activateLicenseKey($team2, 'SYNK-PRO-DUPL-ICAT-EKEY');
    expect($res2['success'])->toBeFalse()
        ->and($res2['message'])->toContain('already been redeemed by another workspace')
        ->and($team2->fresh()->plan)->toBe('free');
});

test('can deactivate license and revert team to community free tier', function () {
    $team = Team::factory()->create([
        'plan' => 'pro_ltd',
        'license_key' => 'SYNK-PRO-REVOKE-TEST',
        'license_status' => 'active',
    ]);
    $service = app(LicenseValidationService::class);

    $result = $service->deactivateLicenseKey($team);

    expect($result['success'])->toBeTrue()
        ->and($team->fresh()->plan)->toBe('free')
        ->and($team->fresh()->license_status)->toBe('revoked');
});
