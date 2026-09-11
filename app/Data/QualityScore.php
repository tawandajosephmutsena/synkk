<?php

namespace App\Data;

readonly class QualityScore
{
    public function __construct(
        public int $total,
        public float $passRate,
        public float $securityScore,
        public float $performanceScore,
        public float $usabilityScore,
    ) {
        //
    }

    /**
     * Get production readiness assessment based on total score.
     *
     * Returns:
     * - 'Ready' when score >= 90
     * - 'Conditional' when score is 70-89
     * - 'Not Ready' when score < 70
     */
    public function getReadinessAssessment(): string
    {
        return match (true) {
            $this->total >= 90 => 'Ready',
            $this->total >= 70 => 'Conditional',
            default => 'Not Ready',
        };
    }

    /**
     * Get color code representation for the quality score.
     *
     * Returns:
     * - 'green' when score >= 90
     * - 'yellow' when score is 70-89
     * - 'red' when score < 70
     */
    public function getColorCode(): string
    {
        return match (true) {
            $this->total >= 90 => 'green',
            $this->total >= 70 => 'yellow',
            default => 'red',
        };
    }
}
