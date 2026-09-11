<?php

use App\Services\QA\DTOs\PerformanceMetrics;

test('can create performance metrics instance', function () {
    $metrics = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 2000.0,
        tti: 3000.0,
        pageLoadTime: 4000.0,
        resourceTimings: [
            ['name' => 'script.js', 'duration' => 150],
            ['name' => 'style.css', 'duration' => 75],
        ]
    );

    expect($metrics->fcp)->toBe(1500.0)
        ->and($metrics->lcp)->toBe(2000.0)
        ->and($metrics->tti)->toBe(3000.0)
        ->and($metrics->pageLoadTime)->toBe(4000.0)
        ->and($metrics->resourceTimings)->toHaveCount(2);
});

test('identifies slow page when load time exceeds 5 seconds', function () {
    $slowMetrics = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 2000.0,
        tti: 3000.0,
        pageLoadTime: 6000.0
    );

    expect($slowMetrics->isSlow())->toBeTrue();
});

test('identifies slow page when LCP exceeds 2.5 seconds', function () {
    $slowMetrics = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 3000.0,
        tti: 3000.0,
        pageLoadTime: 4000.0
    );

    expect($slowMetrics->isSlow())->toBeTrue();
});

test('identifies fast page when both thresholds are met', function () {
    $fastMetrics = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 2000.0,
        tti: 3000.0,
        pageLoadTime: 4000.0
    );

    expect($fastMetrics->isSlow())->toBeFalse();
});

test('can convert metrics to array', function () {
    $metrics = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 2000.0,
        tti: 3000.0,
        pageLoadTime: 4000.0
    );

    $array = $metrics->toArray();

    expect($array)->toBeArray()
        ->and($array)->toHaveKeys(['fcp', 'lcp', 'tti', 'pageLoadTime', 'resourceTimings', 'isSlow'])
        ->and($array['fcp'])->toBe(1500.0)
        ->and($array['isSlow'])->toBeFalse();
});

test('can get metrics in seconds', function () {
    $metrics = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 2500.0,
        tti: 3800.0,
        pageLoadTime: 4200.0
    );

    expect($metrics->getFcpInSeconds())->toBe(1.5)
        ->and($metrics->getLcpInSeconds())->toBe(2.5)
        ->and($metrics->getTtiInSeconds())->toBe(3.8)
        ->and($metrics->getPageLoadTimeInSeconds())->toBe(4.2);
});

test('can get performance summary', function () {
    $metrics = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 2000.0,
        tti: 3000.0,
        pageLoadTime: 4000.0
    );

    $summary = $metrics->getSummary();

    expect($summary)->toBeString()
        ->and($summary)->toContain('FCP: 1.50s')
        ->and($summary)->toContain('LCP: 2.00s')
        ->and($summary)->toContain('TTI: 3.00s')
        ->and($summary)->toContain('Load: 4.00s');
});

test('can check if FCP is fast', function () {
    $fastFcp = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 2000.0,
        tti: 3000.0,
        pageLoadTime: 4000.0
    );

    $slowFcp = new PerformanceMetrics(
        fcp: 2000.0,
        lcp: 2000.0,
        tti: 3000.0,
        pageLoadTime: 4000.0
    );

    expect($fastFcp->hasFastFcp())->toBeTrue()
        ->and($slowFcp->hasFastFcp())->toBeFalse();
});

test('can check if LCP is fast', function () {
    $fastLcp = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 2000.0,
        tti: 3000.0,
        pageLoadTime: 4000.0
    );

    $slowLcp = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 3000.0,
        tti: 3000.0,
        pageLoadTime: 4000.0
    );

    expect($fastLcp->hasFastLcp())->toBeTrue()
        ->and($slowLcp->hasFastLcp())->toBeFalse();
});

test('can check if TTI is fast', function () {
    $fastTti = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 2000.0,
        tti: 3500.0,
        pageLoadTime: 4000.0
    );

    $slowTti = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 2000.0,
        tti: 4000.0,
        pageLoadTime: 4000.0
    );

    expect($fastTti->hasFastTti())->toBeTrue()
        ->and($slowTti->hasFastTti())->toBeFalse();
});

test('accepts empty resource timings by default', function () {
    $metrics = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 2000.0,
        tti: 3000.0,
        pageLoadTime: 4000.0
    );

    expect($metrics->resourceTimings)->toBeArray()
        ->and($metrics->resourceTimings)->toBeEmpty();
});

test('boundary case - exactly at slow thresholds', function () {
    $atLcpThreshold = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 2500.0,
        tti: 3000.0,
        pageLoadTime: 4000.0
    );

    $atLoadTimeThreshold = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 2000.0,
        tti: 3000.0,
        pageLoadTime: 5000.0
    );

    expect($atLcpThreshold->isSlow())->toBeFalse()
        ->and($atLoadTimeThreshold->isSlow())->toBeFalse();
});

test('boundary case - just over slow thresholds', function () {
    $overLcpThreshold = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 2501.0,
        tti: 3000.0,
        pageLoadTime: 4000.0
    );

    $overLoadTimeThreshold = new PerformanceMetrics(
        fcp: 1500.0,
        lcp: 2000.0,
        tti: 3000.0,
        pageLoadTime: 5001.0
    );

    expect($overLcpThreshold->isSlow())->toBeTrue()
        ->and($overLoadTimeThreshold->isSlow())->toBeTrue();
});
