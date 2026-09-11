<?php

namespace App\Services\QA\DTOs;

class QAAuditConfig
{
    public function __construct(
        public string $baseUrl = 'http://localhost:8000',
        public array $browsers = ['chromium'],
        public array $viewports = [320, 768, 1024, 1920],
        public array $enabledCategories = ['happy_path', 'edge_cases', 'security', 'usability', 'performance'],
        public array $timeouts = ['pageLoad' => 30000, 'action' => 10000],
        public array $testUsers = [],
        public array $excludedTests = [],
        public bool $parallel = false,
        public bool $fast = false,
        public bool $videoRecording = true,
    ) {}

    /**
     * Convert the configuration to an array.
     */
    public function toArray(): array
    {
        return [
            'baseUrl' => $this->baseUrl,
            'browsers' => $this->browsers,
            'viewports' => $this->viewports,
            'enabledCategories' => $this->enabledCategories,
            'timeouts' => $this->timeouts,
            'testUsers' => $this->testUsers,
            'excludedTests' => $this->excludedTests,
            'parallel' => $this->parallel,
            'fast' => $this->fast,
            'videoRecording' => $this->videoRecording,
        ];
    }

    /**
     * Get the default configuration structure for template generation.
     */
    public static function getDefaultStructure(): array
    {
        return [
            'baseUrl' => 'http://localhost:8000',
            'browsers' => ['chromium'],
            'viewports' => [320, 768, 1024, 1920],
            'enabledCategories' => [
                'happy_path',
                'edge_cases',
                'security',
                'usability',
                'performance',
            ],
            'timeouts' => [
                'pageLoad' => 30000,
                'action' => 10000,
            ],
            'testUsers' => [
                [
                    'role' => 'super_admin',
                    'email' => 'admin@test.local',
                    'password' => 'SecureAdminPass123!',
                ],
                [
                    'role' => 'standard_user',
                    'email' => 'user@test.local',
                    'password' => 'SecureUserPass123!',
                ],
            ],
            'excludedTests' => [],
            'parallel' => false,
            'fast' => false,
            'videoRecording' => true,
        ];
    }
}
