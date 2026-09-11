<?php

use App\Data\QA\QualityScore;

describe('QualityScore DTO', function () {
    test('can be instantiated with all required properties', function () {
        $score = new QualityScore(
            total: 85,
            passRate: 90.5,
            securityScore: 80.0,
            performanceScore: 85.0,
            usabilityScore: 95.0
        );

        expect($score->total)->toBe(85);
        expect($score->passRate)->toBe(90.5);
        expect($score->securityScore)->toBe(80.0);
        expect($score->performanceScore)->toBe(85.0);
        expect($score->usabilityScore)->toBe(95.0);
    });

    describe('getReadinessAssessment', function () {
        test('returns Ready when score is 90 or above', function () {
            $score = new QualityScore(
                total: 90,
                passRate: 95.0,
                securityScore: 90.0,
                performanceScore: 85.0,
                usabilityScore: 90.0
            );

            expect($score->getReadinessAssessment())->toBe('Ready');
        });

        test('returns Ready when score is 100', function () {
            $score = new QualityScore(
                total: 100,
                passRate: 100.0,
                securityScore: 100.0,
                performanceScore: 100.0,
                usabilityScore: 100.0
            );

            expect($score->getReadinessAssessment())->toBe('Ready');
        });

        test('returns Conditional when score is between 70 and 89', function () {
            $score = new QualityScore(
                total: 70,
                passRate: 80.0,
                securityScore: 70.0,
                performanceScore: 65.0,
                usabilityScore: 75.0
            );

            expect($score->getReadinessAssessment())->toBe('Conditional');
        });

        test('returns Conditional when score is 89', function () {
            $score = new QualityScore(
                total: 89,
                passRate: 90.0,
                securityScore: 88.0,
                performanceScore: 87.0,
                usabilityScore: 90.0
            );

            expect($score->getReadinessAssessment())->toBe('Conditional');
        });

        test('returns Not Ready when score is below 70', function () {
            $score = new QualityScore(
                total: 69,
                passRate: 70.0,
                securityScore: 65.0,
                performanceScore: 60.0,
                usabilityScore: 80.0
            );

            expect($score->getReadinessAssessment())->toBe('Not Ready');
        });

        test('returns Not Ready when score is 0', function () {
            $score = new QualityScore(
                total: 0,
                passRate: 0.0,
                securityScore: 0.0,
                performanceScore: 0.0,
                usabilityScore: 0.0
            );

            expect($score->getReadinessAssessment())->toBe('Not Ready');
        });
    });

    describe('getColorCode', function () {
        test('returns green when score is 90 or above', function () {
            $score = new QualityScore(
                total: 90,
                passRate: 95.0,
                securityScore: 90.0,
                performanceScore: 85.0,
                usabilityScore: 90.0
            );

            expect($score->getColorCode())->toBe('green');
        });

        test('returns green when score is 100', function () {
            $score = new QualityScore(
                total: 100,
                passRate: 100.0,
                securityScore: 100.0,
                performanceScore: 100.0,
                usabilityScore: 100.0
            );

            expect($score->getColorCode())->toBe('green');
        });

        test('returns yellow when score is between 70 and 89', function () {
            $score = new QualityScore(
                total: 70,
                passRate: 80.0,
                securityScore: 70.0,
                performanceScore: 65.0,
                usabilityScore: 75.0
            );

            expect($score->getColorCode())->toBe('yellow');
        });

        test('returns yellow when score is 89', function () {
            $score = new QualityScore(
                total: 89,
                passRate: 90.0,
                securityScore: 88.0,
                performanceScore: 87.0,
                usabilityScore: 90.0
            );

            expect($score->getColorCode())->toBe('yellow');
        });

        test('returns red when score is below 70', function () {
            $score = new QualityScore(
                total: 69,
                passRate: 70.0,
                securityScore: 65.0,
                performanceScore: 60.0,
                usabilityScore: 80.0
            );

            expect($score->getColorCode())->toBe('red');
        });

        test('returns red when score is 0', function () {
            $score = new QualityScore(
                total: 0,
                passRate: 0.0,
                securityScore: 0.0,
                performanceScore: 0.0,
                usabilityScore: 0.0
            );

            expect($score->getColorCode())->toBe('red');
        });
    });

    describe('toArray', function () {
        test('converts quality score to array with all properties', function () {
            $score = new QualityScore(
                total: 85,
                passRate: 90.5,
                securityScore: 80.0,
                performanceScore: 85.0,
                usabilityScore: 95.0
            );

            $array = $score->toArray();

            expect($array)->toHaveKeys([
                'total',
                'passRate',
                'securityScore',
                'performanceScore',
                'usabilityScore',
                'readinessAssessment',
                'colorCode',
            ]);
            expect($array['total'])->toBe(85);
            expect($array['passRate'])->toBe(90.5);
            expect($array['securityScore'])->toBe(80.0);
            expect($array['performanceScore'])->toBe(85.0);
            expect($array['usabilityScore'])->toBe(95.0);
            expect($array['readinessAssessment'])->toBe('Conditional');
            expect($array['colorCode'])->toBe('yellow');
        });
    });
});
