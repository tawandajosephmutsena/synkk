<?php

namespace App\Data\QA;

use DateTime;

readonly class QAAuditResult
{
    /**
     * @param  array<string, PhaseResult>  $phaseResults
     * @param  array<string, mixed>  $artifacts
     */
    public function __construct(
        public array $phaseResults,
        public QualityScore $qualityScore,
        public float $duration,
        public DateTime $timestamp,
        public array $artifacts = [],
    ) {
        //
    }

    /**
     * Get all successful phase results.
     *
     * @return array<string, PhaseResult>
     */
    public function getSuccessfulPhases(): array
    {
        return array_filter(
            $this->phaseResults,
            fn (PhaseResult $result) => $result->isSuccessful()
        );
    }

    /**
     * Get all failed phase results.
     *
     * @return array<string, PhaseResult>
     */
    public function getFailedPhases(): array
    {
        return array_filter(
            $this->phaseResults,
            fn (PhaseResult $result) => $result->isFailed()
        );
    }

    /**
     * Check if all phases succeeded.
     */
    public function allPhasesSucceeded(): bool
    {
        return empty($this->getFailedPhases());
    }

    /**
     * Get a specific phase result.
     */
    public function getPhaseResult(string $phase): ?PhaseResult
    {
        return $this->phaseResults[$phase] ?? null;
    }

    /**
     * Convert the audit result to an array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'duration' => $this->duration,
            'qualityScore' => $this->qualityScore->toArray(),
            'phaseResults' => array_map(
                fn (PhaseResult $result) => $result->toArray(),
                $this->phaseResults
            ),
            'artifacts' => $this->artifacts,
            'allPhasesSucceeded' => $this->allPhasesSucceeded(),
        ];
    }
}
