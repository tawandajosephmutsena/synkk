<?php

use Illuminate\Support\Facades\File;
use ZipArchive;

test('synkk:package-ce successfully packages and sanitizes community edition', function () {
    $testZipPath = storage_path('app/exports/test-community-export.zip');

    if (File::exists($testZipPath)) {
        File::delete($testZipPath);
    }

    $this->artisan('synkk:package-ce', ['--output' => $testZipPath])
        ->assertSuccessful()
        ->expectsOutputToContain('SYNKK OPEN-SOURCE COMMUNITY EDITION (CE) EXPORT')
        ->expectsOutputToContain('Successfully verified and generated Community Edition bundle.');

    expect(File::exists($testZipPath))->toBeTrue();

    $zip = new ZipArchive;
    expect($zip->open($testZipPath))->toBeTrue();

    // Verify essential open-source community files are present
    expect($zip->locateName('artisan'))->not->toBeFalse()
        ->and($zip->locateName('composer.json'))->not->toBeFalse()
        ->and($zip->locateName('app/Models/User.php'))->not->toBeFalse()
        ->and($zip->locateName('app/Models/Vault.php'))->not->toBeFalse()
        ->and($zip->locateName('resources/views/welcome.blade.php'))->not->toBeFalse();

    // Verify proprietary Super Admin files are strictly omitted
    expect($zip->locateName('resources/views/pages/admin/dashboard.blade.php'))->toBeFalse()
        ->and($zip->locateName('app/Http/Controllers/Admin/ImpersonationController.php'))->toBeFalse();

    $zip->close();

    // Cleanup
    File::delete($testZipPath);
});
