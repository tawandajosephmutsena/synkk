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
        ->and($zip->locateName('.env.example'))->not->toBeFalse()
        ->and($zip->locateName('composer.json'))->not->toBeFalse()
        ->and($zip->locateName('app/Models/User.php'))->not->toBeFalse()
        ->and($zip->locateName('app/Models/Vault.php'))->not->toBeFalse()
        ->and($zip->locateName('resources/views/welcome.blade.php'))->not->toBeFalse();

    // Verify proprietary Super Admin files are strictly omitted
    expect($zip->locateName('resources/views/pages/admin/dashboard.blade.php'))->toBeFalse()
        ->and($zip->locateName('app/Http/Controllers/Admin/ImpersonationController.php'))->toBeFalse();

    $communityRoutes = $zip->getFromName('routes/web.php');
    expect($communityRoutes)->toBeString()
        ->and($communityRoutes)->toContain('class_exists(EnsureSuperAdmin::class)')
        ->and($communityRoutes)->toContain('class_exists(ImpersonationController::class)');

    $forbiddenPatterns = [
        '#(^|/)\.env(?:$|\.(?!example$))#i',
        '#(^|/)\.git(?:/|$)#i',
        '#(^|/)cookies(?:\.[^/]+)?\.txt$#i',
        '#(^|/)database/.*\.(?:sqlite|sqlite3|db)(?:[-.].*)?$#i',
        '#(^|/)database/.*\.backup(?:[-.].*)?$#i',
        '#(^|/)bootstrap/cache/#i',
        '#(^|/)storage/#i',
        '#(^|/)node_modules/#i',
        '#(^|/)vendor/#i',
        '#(^|/)tests/#i',
        '#(^|/)phpunit\.xml$#i',
    ];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entry = $zip->getNameIndex($index);
        expect($entry)->toBeString();

        foreach ($forbiddenPatterns as $pattern) {
            expect(preg_match($pattern, $entry))->toBe(0, "Forbidden archive entry: {$entry}");
        }
    }

    $zip->close();

    // Cleanup
    File::delete($testZipPath);
});
