<?php

use App\Data\QA\PhaseResult;

describe('PhaseResult DTO', function () {
    test('can be instantiated with all required properties', function () {
        $result = new PhaseResult(
            phase: 'repository',
            status: 'success',
            errorMessage: null,
            data: ['routes' => 10, 'models' => 5],
            duration: 1.5
        );

        expect($result->phase)->toBe('repository');
        expect($result->status)->toBe('success');
        expect($result->errorMessage)->toBeNull();
        expect($result->data)->toBe(['routes' => 10, 'models' => 5]);
        expect($result->duration)->toBe(1.5);
    });

    describe('success factory method', function () {
        test('creates a successful phase result with data', function () {
            $result = PhaseResult::success(
                phase: 'browser',
                data: ['tests_passed' => 10],
                duration: 2.3
            );

            expect($result->phase)->toBe('browser');
            expect($result->status)->toBe('success');
            expect($result->errorMessage)->toBeNull();
            expect($result->data)->toBe(['tests_passed' => 10]);
            expect($result->duration)->toBe(2.3);
        });

        test('creates a successful phase result without data', function () {
            $result = PhaseResult::success(
                phase: 'security',
                duration: 1.0
            );

            expect($result->phase)->toBe('security');
            expect($result->status)->toBe('success');
            expect($result->errorMessage)->toBeNull();
            expect($result->data)->toBe([]);
        });
    });

    describe('failed factory method', function () {
        test('creates a failed phase result with error message', function () {
            $result = PhaseResult::failed(
                phase: 'browser',
                errorMessage: 'Browser driver not found',
                duration: 0.5
            );

            expect($result->phase)->toBe('browser');
            expect($result->status)->toBe('failed');
            expect($result->errorMessage)->toBe('Browser driver not found');
            expect($result->data)->toBeNull();
            expect($result->duration)->toBe(0.5);
        });
    });

    describe('status check methods', function () {
        test('isSuccessful returns true for successful result', function () {
            $result = PhaseResult::success('repository');

            expect($result->isSuccessful())->toBeTrue();
            expect($result->isFailed())->toBeFalse();
        });

        test('isFailed returns true for failed result', function () {
            $result = PhaseResult::failed('browser', 'Error occurred');

            expect($result->isFailed())->toBeTrue();
            expect($result->isSuccessful())->toBeFalse();
        });
    });

    describe('toArray', function () {
        test('converts successful phase result to array', function () {
            $result = PhaseResult::success(
                phase: 'repository',
                data: ['routes' => 10],
                duration: 1.5
            );

            $array = $result->toArray();

            expect($array)->toHaveKeys(['phase', 'status', 'errorMessage', 'data', 'duration']);
            expect($array['phase'])->toBe('repository');
            expect($array['status'])->toBe('success');
            expect($array['errorMessage'])->toBeNull();
            expect($array['data'])->toBe(['routes' => 10]);
            expect($array['duration'])->toBe(1.5);
        });

        test('converts failed phase result to array', function () {
            $result = PhaseResult::failed(
                phase: 'browser',
                errorMessage: 'Test failed',
                duration: 0.5
            );

            $array = $result->toArray();

            expect($array)->toHaveKeys(['phase', 'status', 'errorMessage', 'data', 'duration']);
            expect($array['phase'])->toBe('browser');
            expect($array['status'])->toBe('failed');
            expect($array['errorMessage'])->toBe('Test failed');
            expect($array['data'])->toBeNull();
            expect($array['duration'])->toBe(0.5);
        });
    });
});
