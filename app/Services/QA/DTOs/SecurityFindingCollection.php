<?php

namespace App\Services\QA\DTOs;

class SecurityFindingCollection
{
    /**
     * @var SecurityFinding[]
     */
    public array $findings = [];

    /**
     * Create a new SecurityFindingCollection instance.
     *
     * @param  array<int, SecurityFinding>  $findings  Array of SecurityFinding objects
     */
    public function __construct(array $findings = [])
    {
        $this->findings = $findings;
    }

    /**
     * Add a security finding to the collection.
     */
    public function add(SecurityFinding $finding): void
    {
        $this->findings[] = $finding;
    }

    /**
     * Count findings by severity level.
     *
     * @return array<string, int> Associative array with severity levels as keys and counts as values
     */
    public function countBySeverity(): array
    {
        $counts = [
            'Critical' => 0,
            'High' => 0,
            'Medium' => 0,
            'Low' => 0,
        ];

        foreach ($this->findings as $finding) {
            if (isset($counts[$finding->severity])) {
                $counts[$finding->severity]++;
            }
        }

        return $counts;
    }

    /**
     * Filter findings by severity level.
     *
     * @param  string  $severity  The severity level to filter by (Critical, High, Medium, Low)
     * @return self A new collection containing only findings with the specified severity
     */
    public function filterBySeverity(string $severity): self
    {
        $filtered = array_filter(
            $this->findings,
            fn (SecurityFinding $finding) => $finding->severity === $severity
        );

        return new self(array_values($filtered));
    }

    /**
     * Get all findings as an array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            fn (SecurityFinding $finding) => $finding->toArray(),
            $this->findings
        );
    }

    /**
     * Get the total number of findings.
     */
    public function count(): int
    {
        return count($this->findings);
    }

    /**
     * Check if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->findings);
    }

    /**
     * Get all findings.
     *
     * @return SecurityFinding[]
     */
    public function all(): array
    {
        return $this->findings;
    }
}
