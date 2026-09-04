<?php

use Illuminate\Support\Facades\File;

test('artisan command synkk:export-bundle generates distribution zip file', function () {
    $outputPath = storage_path('app/exports/test-synkk-bundle.zip');
    if (File::exists($outputPath)) {
        File::delete($outputPath);
    }

    $this->artisan('synkk:export-bundle', ['--output' => $outputPath])
        ->assertSuccessful();

    expect(File::exists($outputPath))->toBeTrue();
    expect(filesize($outputPath))->toBeGreaterThan(100);

    File::delete($outputPath);
});
