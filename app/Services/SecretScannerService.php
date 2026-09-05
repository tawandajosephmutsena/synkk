<?php

namespace App\Services;

class SecretScannerService
{
    /**
     * Regex patterns for common API keys, credentials, and sensitive private tokens.
     *
     * @var array<string, string>
     */
    protected array $patterns = [
        'AWS Access Key ID' => '/\b(AKIA[0-9A-Z]{16})\b/',
        'AWS Secret Access Key' => '/\b(?=[0-9a-zA-Z\/+]{40}\b)(?=[0-9a-zA-Z\/+]*[a-z])(?=[0-9a-zA-Z\/+]*[A-Z])(?=[0-9a-zA-Z\/+]*[0-9])[0-9a-zA-Z\/+]{40}\b/',
        'RSA/SSH Private Key' => '/-----BEGIN (RSA|OPENSSH|EC|DSA) PRIVATE KEY-----/',
        'GitHub Personal Access Token' => '/\b(ghp_[a-zA-Z0-9]{36}|github_pat_[a-zA-Z0-9]{22}_[a-zA-Z0-9]{59})\b/',
        'Slack Bot/User Token' => '/\b(xox[baprs]-[0-9a-zA-Z]{10,48})\b/',
        'Generic API Key Header' => '/\b(api[_-]?key|secret[_-]?key|access[_-]?token)\s*[:=]\s*["\']([a-zA-Z0-9_\-]{20,})["\']/i',
    ];

    /**
     * Scan text content for potential secrets.
     *
     * @return array{has_secrets: bool, detected: array<int, string>}
     */
    public function scan(string $contents): array
    {
        $detected = [];

        foreach ($this->patterns as $label => $pattern) {
            if (preg_match($pattern, $contents)) {
                $detected[] = $label;
            }
        }

        return [
            'has_secrets' => ! empty($detected),
            'detected' => array_unique($detected),
        ];
    }
}
