<?php

use App\Data\QA\PhaseResult;
use App\Data\QA\QAAuditResult;
use App\Data\QA\QualityScore;

describe('QAAuditResult DTO', function () {
    test('can be instantiated with all required properties', function () {
        $timestamp = new DateTime;
        $qualityScore = new QualityScore(85, 90.0, 80.0, 85.0, 95.0);
        $phaseResults = [
            'repository' => PhaseResult::success('repository', ['routes' => 10]),
            'browser' => PhaseResult::success('browser', ['tests_passed' => 15]),
        ];

        $result = new QAAuditResult(
            phaseResults: $phaseResults,
            qualityScore: $qualityScore,
            duration: 10.5,
            timestamp: $timestamp,
            artifacts: ['screenshots' => '/path/to/screenshots']
        );

        expect($result->phaseResults)->toBe($phaseResults);
        expect($result->qualityScore)->toBe($qualityScore);
        expect($result->duration)->toBe(10.5);
        expect($result->timestamp)->toBe($timestamp);
        expect($result->artifacts)->toBe(['screenshots' => '/path/to/screenshots']);
    });

    describe('getSuccessfulPhases', function () {
        test('returns only successful phase results', function () {
            $phaseResults = [
                'repository' => PhaseResult::success('repository'),
                'browser' => PhaseResult::failed('browser', 'Error'),
                'security' => PhaseResult::success('security'),
            ];

            $result = new QAAuditResult(
                phaseResults: $phaseResults,
                qualityScore: new QualityScore(85, 90.0, 80.0, 85.0, 95.0),
                duration: 10.5,
                timestamp: new DateTime
            );

            $successful = $result->getSuccessfulPhases();

            expect($successful)->toHaveCount(2);
            expect($successful)->toHaveKeys(['repository', 'security']);
        });
    });

    describe('getFailedPhases', function () {
        test('returns only failed phase results', function () {
            $phaseResults = [
                'repository' => PhaseResult::success('repository'),
                'browser' => PhaseResult::failed('browser', 'Error 1'),
                'security' => PhaseResult::failed('security', 'Error 2'),
            ];

            $result = new QAAuditResult(
                phaseResults: $phaseResults,
                qualityScore: new QualityScore(85, 90.0, 80.0, 85.0, 95.0),
                duration: 10.5,
                timestamp: new DateTime
            );

            $failed = $result->getFailedPhases();

            expect($failed)->toHaveCount(2);
            expect($failed)->toHaveKeys(['browser', 'security']);
        });
    });

    describe('allPhasesSucceeded', function () {
        test('returns true when all phases succeeded', function () {
            $phaseResults = [
                'repository' => PhaseResult::success('repository'),
                'browser' => PhaseResult::success('browser'),
                'security' => PhaseResult::success('security'),
            ];

            $result = new QAAuditResult(
                phaseResults: $phaseResults,
                qualityScore: new QualityScore(85, 90.0, 80.0, 85.0, 95.0),
                duration: 10.5,
                timestamp: new DateTime
            );

            expect($result->allPhasesSucceeded())->toBeTrue();
        });

        test('returns false when at least one phase failed', function () {
            $phaseResults = [
                'repository' => PhaseResult::success('repository'),
                'browser' => PhaseResult::failed('browser', 'Error'),
                'security' => PhaseResult::success('security'),
            ];

            $result = new QAAuditResult(
                phaseResults: $phaseResults,
                qualityScore: new QualityScore(85, 90.0, 80.0, 85.0, 95.0),
                duration: 10.5,
                timestamp: new DateTime
            );

            expect($result->allPhasesSucceeded())->toBeFalse();
        });
    });

    describe('getPhaseResult', function () {
        test('returns phase result by name when it exists', function () {
            $repositoryResult = PhaseResult::success('repository');
            $phaseResults = [
                'repository' => $repositoryResult,
                'browser' => PhaseResult::success('browser'),
            ];

            $result = new QAAuditResult(
                phaseResults: $phaseResults,
                qualityScore: new QualityScore(85, 90.0, 80.0, 85.0, 95.0),
                duration: 10.5,
                timestamp: new DateTime
            );

            expect($result->getPhaseResult('repository'))->toBe($repositoryResult);
        });

        test('returns null when phase does not exist', function () {
            $phaseResults = [
                'repository' => PhaseResult::success('repository'),
            ];

            $result = new QAAuditResult(
                phaseResults: $phaseResults,
                qualityScore: new QualityScore(85, 90.0, 80.0, 85.0, 95.0),
                duration: 10.5,
                timestamp: new DateTime
            );

            expect($result->getPhaseResult('nonexistent'))->toBeNull();
        });
    });

    describe('toArray', function () {
        test('converts audit result to array with all properties', function () {
            $timestamp = new DateTime('2024-01-15 10:30:00');
            $qualityScore = new QualityScore(85, 90.0, 80.0, 85.0, 95.0);
            $phaseResults = [
                'repository' => PhaseResult::success('repository', ['routes' => 10]),
                'browser' => PhaseResult::failed('browser', 'Error message'),
            ];

            $result = new QAAuditResult(
                phaseResults: $phaseResults,
                qualityScore: $qualityScore,
                duration: 10.5,
                timestamp: $timestamp,
                artifacts: ['screenshots' => '/path/to/screenshots']
            );

            $array = $result->toArray();

            expect($array)->toHaveKeys([
                'timestamp',
                'duration',
                'qualityScore',
                'phaseResults',
                'artifacts',
                'allPhasesSucceeded',
            ]);
            expect($array['timestamp'])->toBe('2024-01-15 10:30:00');
            expect($array['duration'])->toBe(10.5);
            expect($array['qualityScore'])->toBeArray();
            expect($array['qualityScore']['total'])->toBe(85);
            expect($array['phaseResults'])->toHaveCount(2);
            expect($array['phaseResults']['repository']['status'])->toBe('success');
            expect($array['phaseResults']['browser']['status'])->toBe('failed');
            expect($array['artifacts'])->toBe(['screenshots' => '/path/to/screenshots']);
            expect($array['allPhasesSucceeded'])->toBeFalse();
        });
    });
});
