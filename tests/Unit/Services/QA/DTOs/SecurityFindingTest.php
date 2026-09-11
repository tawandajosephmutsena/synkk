<?php

use App\Services\QA\DTOs\SecurityFinding;

test('can create security finding instance', function () {
    $finding = new SecurityFinding(
        type: 'sql_injection',
        severity: 'Critical',
        description: 'SQL injection vulnerability detected in search endpoint',
        location: '/api/search',
        evidence: 'Query: SELECT * FROM users WHERE name = \'{$input}\'',
        recommendation: 'Use parameterized queries with Eloquent query builder',
        references: ['https://owasp.org/www-community/attacks/SQL_Injection']
    );

    expect($finding->type)->toBe('sql_injection')
        ->and($finding->severity)->toBe('Critical')
        ->and($finding->description)->toBe('SQL injection vulnerability detected in search endpoint')
        ->and($finding->location)->toBe('/api/search')
        ->and($finding->evidence)->toContain('SELECT * FROM users')
        ->and($finding->recommendation)->toContain('parameterized queries')
        ->and($finding->references)->toHaveCount(1);
});

test('can convert security finding to array', function () {
    $finding = new SecurityFinding(
        type: 'xss',
        severity: 'High',
        description: 'XSS vulnerability',
        location: '/vault/create',
        evidence: '<script>alert("XSS")</script>',
        recommendation: 'Escape user input',
        references: []
    );

    $array = $finding->toArray();

    expect($array)->toBeArray()
        ->and($array)->toHaveKeys(['type', 'severity', 'description', 'location', 'evidence', 'recommendation', 'references'])
        ->and($array['type'])->toBe('xss')
        ->and($array['severity'])->toBe('High');
});

test('can check if finding is critical', function () {
    $criticalFinding = new SecurityFinding(
        type: 'sql_injection',
        severity: 'Critical',
        description: 'Test',
        location: '/test',
        evidence: 'test',
        recommendation: 'test'
    );

    $highFinding = new SecurityFinding(
        type: 'pii_leak',
        severity: 'High',
        description: 'Test',
        location: '/test',
        evidence: 'test',
        recommendation: 'test'
    );

    expect($criticalFinding->isCritical())->toBeTrue()
        ->and($highFinding->isCritical())->toBeFalse();
});

test('can check if finding is high severity', function () {
    $highFinding = new SecurityFinding(
        type: 'pii_leak',
        severity: 'High',
        description: 'Test',
        location: '/test',
        evidence: 'test',
        recommendation: 'test'
    );

    $mediumFinding = new SecurityFinding(
        type: 'info_disclosure',
        severity: 'Medium',
        description: 'Test',
        location: '/test',
        evidence: 'test',
        recommendation: 'test'
    );

    expect($highFinding->isHigh())->toBeTrue()
        ->and($mediumFinding->isHigh())->toBeFalse();
});

test('accepts empty references array by default', function () {
    $finding = new SecurityFinding(
        type: 'csrf',
        severity: 'Medium',
        description: 'Missing CSRF token',
        location: '/api/delete',
        evidence: 'No token in request',
        recommendation: 'Enable CSRF middleware'
    );

    expect($finding->references)->toBeArray()
        ->and($finding->references)->toBeEmpty();
});
