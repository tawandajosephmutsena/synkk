<?php

namespace App\Services;

class SecretScannerService
{
    /**
     * Regex patterns for common API keys, credentials, and sensitive private tokens.
     * Ordered so multi-line blocks and prefix-specific tokens match before generic patterns.
     *
     * @var array<string, string>
     */
    protected array $patterns = [
        'PEM Private Key Block' => '/-----BEGIN (?:RSA|OPENSSH|EC|DSA|PRIVATE) PRIVATE KEY-----[\s\S]*?-----END (?:RSA|OPENSSH|EC|DSA|PRIVATE) PRIVATE KEY-----/',
        'RSA/SSH Private Key' => '/-----BEGIN (?:RSA|OPENSSH|EC|DSA|PRIVATE) PRIVATE KEY-----/',
        'Anthropic API Key' => '/\b(sk-ant-[a-zA-Z0-9_\-]{20,})\b/',
        'OpenAI API Key' => '/\b(sk-(?:proj-|svcacct-|live-|test-)?[a-zA-Z0-9_\-]{20,})\b/',
        'Synkk Device Token' => '/\b(synkk_[a-zA-Z0-9]{32,})\b/',
        'Stripe Secret Key' => '/\b((?:sk|rk)_(?:live|test)_[a-zA-Z0-9]{16,})\b/',
        'AWS Access Key ID' => '/\b(AKIA[0-9A-Z]{16})\b/',
        'GitHub Personal Access Token' => '/\b(ghp_[a-zA-Z0-9]{36}|github_pat_[a-zA-Z0-9]{22}_[a-zA-Z0-9]{59})\b/',
        'Slack Bot/User Token' => '/\b(xox[baprs]-[0-9a-zA-Z\-]{10,64})\b/',
        'Generic API Key Header' => '/\b(api[_-]?key|secret[_-]?key|access[_-]?token)(\s*[:=]\s*["\'])([a-zA-Z0-9_\-]{20,})(["\'])/i',
        'AWS Secret Access Key' => '/\b(?=[0-9a-zA-Z\/+]{40}\b)(?=[0-9a-zA-Z\/+]*[a-z])(?=[0-9a-zA-Z\/+]*[A-Z])(?=[0-9a-zA-Z\/+]*[0-9])[0-9a-zA-Z\/+]{40}\b/',
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
            'detected' => array_values(array_unique($detected)),
        ];
    }

    /**
     * Redact detected secrets from text before sending payloads to external AI providers.
     *
     * @return array{redacted: string, count: int, detected: array<int, string>}
     */
    public function redact(string $contents): array
    {
        if ($contents === '') {
            return [
                'redacted' => '',
                'count' => 0,
                'detected' => [],
            ];
        }

        $redacted = $contents;
        $totalCount = 0;
        $detected = [];

        foreach ($this->patterns as $label => $pattern) {
            $tokenLabel = strtoupper((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $label));
            $tokenLabel = trim($tokenLabel, '_');
            $replacementTag = "[REDACTED:{$tokenLabel}]";

            $matchesCount = 0;

            $updated = preg_replace_callback(
                $pattern,
                function (array $matches) use ($label, $replacementTag, &$matchesCount): string {
                    $matchesCount++;

                    if ($label === 'Generic API Key Header' && isset($matches[1], $matches[2], $matches[4])) {
                        return $matches[1].$matches[2].$replacementTag.$matches[4];
                    }

                    return $replacementTag;
                },
                $redacted
            );

            if (is_string($updated) && $matchesCount > 0) {
                $redacted = $updated;
                $totalCount += $matchesCount;
                $detected[] = $label;
            }
        }

        return [
            'redacted' => $redacted,
            'count' => $totalCount,
            'detected' => array_values(array_unique($detected)),
        ];
    }
}
