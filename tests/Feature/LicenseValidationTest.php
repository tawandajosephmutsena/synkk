<?php

use App\Models\Team;
use App\Services\LicenseValidationService;

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
