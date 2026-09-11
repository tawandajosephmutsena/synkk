<?php

use App\Services\QA\DTOs\SecurityFinding;
use App\Services\QA\DTOs\SecurityFindingCollection;

test('can create empty collection', function () {
    $collection = new SecurityFindingCollection;

    expect($collection->count())->toBe(0)
        ->and($collection->isEmpty())->toBeTrue();
});

test('can create collection with initial findings', function () {
    $findings = [
        new SecurityFinding('sql_injection', 'Critical', 'Test 1', '/test1', 'evidence1', 'fix1'),
        new SecurityFinding('xss', 'High', 'Test 2', '/test2', 'evidence2', 'fix2'),
    ];

    $collection = new SecurityFindingCollection($findings);

    expect($collection->count())->toBe(2)
        ->and($collection->isEmpty())->toBeFalse();
});

test('can add findings to collection', function () {
    $collection = new SecurityFindingCollection;

    $finding1 = new SecurityFinding('sql_injection', 'Critical', 'Test 1', '/test1', 'evidence1', 'fix1');
    $finding2 = new SecurityFinding('xss', 'High', 'Test 2', '/test2', 'evidence2', 'fix2');

    $collection->add($finding1);
    $collection->add($finding2);

    expect($collection->count())->toBe(2);
});

test('can count findings by severity', function () {
    $collection = new SecurityFindingCollection([
        new SecurityFinding('sql_injection', 'Critical', 'Test 1', '/test1', 'evidence1', 'fix1'),
        new SecurityFinding('xss', 'Critical', 'Test 2', '/test2', 'evidence2', 'fix2'),
        new SecurityFinding('pii_leak', 'High', 'Test 3', '/test3', 'evidence3', 'fix3'),
        new SecurityFinding('info_disclosure', 'Medium', 'Test 4', '/test4', 'evidence4', 'fix4'),
        new SecurityFinding('weak_validation', 'Medium', 'Test 5', '/test5', 'evidence5', 'fix5'),
        new SecurityFinding('config_issue', 'Low', 'Test 6', '/test6', 'evidence6', 'fix6'),
    ]);

    $counts = $collection->countBySeverity();

    expect($counts)->toBeArray()
        ->and($counts['Critical'])->toBe(2)
        ->and($counts['High'])->toBe(1)
        ->and($counts['Medium'])->toBe(2)
        ->and($counts['Low'])->toBe(1);
});

test('count by severity returns zero for missing severities', function () {
    $collection = new SecurityFindingCollection([
        new SecurityFinding('sql_injection', 'Critical', 'Test 1', '/test1', 'evidence1', 'fix1'),
    ]);

    $counts = $collection->countBySeverity();

    expect($counts['Critical'])->toBe(1)
        ->and($counts['High'])->toBe(0)
        ->and($counts['Medium'])->toBe(0)
        ->and($counts['Low'])->toBe(0);
});

test('can filter findings by severity', function () {
    $collection = new SecurityFindingCollection([
        new SecurityFinding('sql_injection', 'Critical', 'Test 1', '/test1', 'evidence1', 'fix1'),
        new SecurityFinding('xss', 'Critical', 'Test 2', '/test2', 'evidence2', 'fix2'),
        new SecurityFinding('pii_leak', 'High', 'Test 3', '/test3', 'evidence3', 'fix3'),
        new SecurityFinding('info_disclosure', 'Medium', 'Test 4', '/test4', 'evidence4', 'fix4'),
    ]);

    $criticalFindings = $collection->filterBySeverity('Critical');
    $highFindings = $collection->filterBySeverity('High');
    $lowFindings = $collection->filterBySeverity('Low');

    expect($criticalFindings)->toBeInstanceOf(SecurityFindingCollection::class)
        ->and($criticalFindings->count())->toBe(2)
        ->and($highFindings->count())->toBe(1)
        ->and($lowFindings->count())->toBe(0)
        ->and($lowFindings->isEmpty())->toBeTrue();
});

test('filtered collection maintains finding properties', function () {
    $collection = new SecurityFindingCollection([
        new SecurityFinding('sql_injection', 'Critical', 'SQL Injection Found', '/api/search', 'evidence', 'fix'),
        new SecurityFinding('xss', 'High', 'XSS Found', '/vault/create', 'evidence', 'fix'),
    ]);

    $criticalFindings = $collection->filterBySeverity('Critical');
    $findings = $criticalFindings->all();

    expect($findings[0]->type)->toBe('sql_injection')
        ->and($findings[0]->description)->toBe('SQL Injection Found');
});

test('can convert collection to array', function () {
    $collection = new SecurityFindingCollection([
        new SecurityFinding('sql_injection', 'Critical', 'Test 1', '/test1', 'evidence1', 'fix1'),
        new SecurityFinding('xss', 'High', 'Test 2', '/test2', 'evidence2', 'fix2'),
    ]);

    $array = $collection->toArray();

    expect($array)->toBeArray()
        ->and($array)->toHaveCount(2)
        ->and($array[0])->toHaveKey('type')
        ->and($array[0]['type'])->toBe('sql_injection')
        ->and($array[1]['type'])->toBe('xss');
});

test('can get all findings', function () {
    $findings = [
        new SecurityFinding('sql_injection', 'Critical', 'Test 1', '/test1', 'evidence1', 'fix1'),
        new SecurityFinding('xss', 'High', 'Test 2', '/test2', 'evidence2', 'fix2'),
    ];

    $collection = new SecurityFindingCollection($findings);
    $allFindings = $collection->all();

    expect($allFindings)->toBeArray()
        ->and($allFindings)->toHaveCount(2)
        ->and($allFindings[0])->toBeInstanceOf(SecurityFinding::class);
});
