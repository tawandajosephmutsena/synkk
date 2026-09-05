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
