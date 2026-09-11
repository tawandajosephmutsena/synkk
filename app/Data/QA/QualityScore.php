<?php

namespace App\Data\QA;

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

    public function getReadinessAssessment(): string
    {
        return match (true) {
            $this->total >= 90 => 'Ready',
            $this->total >= 70 => 'Conditional',
            default => 'Not Ready',
        };
    }

    public function getColorCode(): string
    {
        return match (true) {
            $this->total >= 90 => 'green',
            $this->total >= 70 => 'yellow',
            default => 'red',
        };
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'passRate' => $this->passRate,
            'securityScore' => $this->securityScore,
            'performanceScore' => $this->performanceScore,
            'usabilityScore' => $this->usabilityScore,
            'readinessAssessment' => $this->getReadinessAssessment(),
            'colorCode' => $this->getColorCode(),
        ];
    }
}
