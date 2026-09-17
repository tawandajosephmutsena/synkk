<?php

namespace App\Services\QA\DTOs;

class PerformanceMetrics
{
    /**
     * Create a new PerformanceMetrics instance.
     *
     * @param  float  $fcp  First Contentful Paint in milliseconds
     * @param  float  $lcp  Largest Contentful Paint in milliseconds
     * @param  float  $tti  Time to Interactive in milliseconds
     * @param  float  $pageLoadTime  Total page load time in milliseconds
     * @param  array<array-key, mixed>  $resourceTimings  Array of resource timing entries for network requests
     */
    public function __construct(
        public float $fcp,
        public float $lcp,
        public float $tti,
        public float $pageLoadTime,
        public array $resourceTimings = [],
    ) {}

    /**
     * Determine if the page is slow based on performance thresholds.
     *
     * A page is considered slow if:
     * - Page load time exceeds 5000ms (5 seconds), OR
     * - Largest Contentful Paint exceeds 2500ms (2.5 seconds)
     */
    public function isSlow(): bool
    {
        return $this->pageLoadTime > 5000 || $this->lcp > 2500;
    }

    /**
     * Convert the PerformanceMetrics to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fcp' => $this->fcp,
            'lcp' => $this->lcp,
            'tti' => $this->tti,
            'pageLoadTime' => $this->pageLoadTime,
            'resourceTimings' => $this->resourceTimings,
            'isSlow' => $this->isSlow(),
        ];
    }

    /**
     * Get First Contentful Paint in seconds.
     */
    public function getFcpInSeconds(): float
    {
        return round($this->fcp / 1000, 2);
    }

    /**
     * Get Largest Contentful Paint in seconds.
     */
    public function getLcpInSeconds(): float
    {
        return round($this->lcp / 1000, 2);
    }

    /**
     * Get Time to Interactive in seconds.
     */
    public function getTtiInSeconds(): float
    {
        return round($this->tti / 1000, 2);
    }

    /**
     * Get page load time in seconds.
     */
    public function getPageLoadTimeInSeconds(): float
    {
        return round($this->pageLoadTime / 1000, 2);
    }

    /**
     * Get a performance summary string.
     */
    public function getSummary(): string
    {
        return sprintf(
            'FCP: %.2fs, LCP: %.2fs, TTI: %.2fs, Load: %.2fs',
            $this->getFcpInSeconds(),
            $this->getLcpInSeconds(),
            $this->getTtiInSeconds(),
            $this->getPageLoadTimeInSeconds()
        );
    }

    /**
     * Check if FCP meets the good threshold (< 1.8 seconds).
     */
    public function hasFastFcp(): bool
    {
        return $this->fcp < 1800;
    }

    /**
     * Check if LCP meets the good threshold (< 2.5 seconds).
     */
    public function hasFastLcp(): bool
    {
        return $this->lcp < 2500;
    }

    /**
     * Check if TTI meets the good threshold (< 3.8 seconds).
     */
    public function hasFastTti(): bool
    {
        return $this->tti < 3800;
    }
}
