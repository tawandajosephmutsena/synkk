<?php

namespace App\Services\QA\DTOs;

class SecurityFinding
{
    /**
     * Create a new SecurityFinding instance.
     *
     * @param  string  $type  The type of security finding (pii_leak, sql_injection, xss, auth_bypass, etc.)
     * @param  string  $severity  The severity level (Critical, High, Medium, Low)
     * @param  string  $description  Human-readable description of the security finding
     * @param  string  $location  URL, file path, or storage key where the issue was found
     * @param  string  $evidence  The actual data or code snippet demonstrating the issue
     * @param  string  $recommendation  Suggested fix or remediation steps
     * @param  array  $references  Links to relevant documentation (CWE, OWASP, etc.)
     */
    public function __construct(
        public string $type,
        public string $severity,
        public string $description,
        public string $location,
        public string $evidence,
        public string $recommendation,
        public array $references = [],
    ) {}

    /**
     * Convert the SecurityFinding to an array.
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'severity' => $this->severity,
            'description' => $this->description,
            'location' => $this->location,
            'evidence' => $this->evidence,
            'recommendation' => $this->recommendation,
            'references' => $this->references,
        ];
    }

    /**
     * Check if this is a critical severity finding.
     */
    public function isCritical(): bool
    {
        return $this->severity === 'Critical';
    }

    /**
     * Check if this is a high severity finding.
     */
    public function isHigh(): bool
    {
        return $this->severity === 'High';
    }
}
