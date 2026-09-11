<?php

namespace App\Services\QA;

use App\Exceptions\ConfigurationException;
use App\Services\QA\DTOs\QAAuditConfig;
use Illuminate\Support\Facades\File;

class ConfigurationLoader
{
    /**
     * The configuration file path.
     */
    private const CONFIG_FILE = 'qa-audit.config.json';

    /**
     * Load and validate the QA audit configuration.
     *
     * @throws ConfigurationException
     */
    public function load(): QAAuditConfig
    {
        $configPath = base_path(self::CONFIG_FILE);

        // If config file doesn't exist, generate template and use defaults
        if (! File::exists($configPath)) {
            $this->generateTemplate($configPath);

            return new QAAuditConfig;
        }

        // Read and parse the configuration file
        $content = File::get($configPath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ConfigurationException::invalidFile(
                self::CONFIG_FILE,
                'Invalid JSON: '.json_last_error_msg()
            );
        }

        // Validate and build configuration
        return $this->validateAndBuild($data);
    }

    /**
     * Generate a template configuration file with sensible defaults.
     */
    public function generateTemplate(string $path): void
    {
        $template = QAAuditConfig::getDefaultStructure();

        $json = json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        File::put($path, $json);
    }

    /**
     * Validate configuration data and build QAAuditConfig object.
     *
     * @throws ConfigurationException
     */
    private function validateAndBuild(array $data): QAAuditConfig
    {
        // Validate baseUrl
        $baseUrl = $data['baseUrl'] ?? 'http://localhost:8000';
        if (! is_string($baseUrl)) {
            throw ConfigurationException::invalidType('baseUrl', 'string', gettype($baseUrl));
        }

        if (! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw ConfigurationException::invalidValue('baseUrl', 'must be a valid URL');
        }

        // Validate browsers
        $browsers = $data['browsers'] ?? ['chromium'];
        if (! is_array($browsers)) {
            throw ConfigurationException::invalidType('browsers', 'array', gettype($browsers));
        }

        $validBrowsers = ['chromium', 'firefox', 'webkit'];
        foreach ($browsers as $browser) {
            if (! in_array($browser, $validBrowsers)) {
                throw ConfigurationException::invalidValue(
                    'browsers',
                    "'{$browser}' is not a valid browser. Valid options: ".implode(', ', $validBrowsers)
                );
            }
        }

        // Validate viewports
        $viewports = $data['viewports'] ?? [320, 768, 1024, 1920];
        if (! is_array($viewports)) {
            throw ConfigurationException::invalidType('viewports', 'array', gettype($viewports));
        }

        foreach ($viewports as $viewport) {
            if (! is_int($viewport) || $viewport <= 0) {
                throw ConfigurationException::invalidValue(
                    'viewports',
                    'all viewport widths must be positive integers'
                );
            }
        }

        // Validate enabledCategories
        $enabledCategories = $data['enabledCategories'] ?? ['happy_path', 'edge_cases', 'security', 'usability', 'performance'];
        if (! is_array($enabledCategories)) {
            throw ConfigurationException::invalidType('enabledCategories', 'array', gettype($enabledCategories));
        }

        $validCategories = ['happy_path', 'edge_cases', 'security', 'usability', 'performance'];
        foreach ($enabledCategories as $category) {
            if (! in_array($category, $validCategories)) {
                throw ConfigurationException::invalidValue(
                    'enabledCategories',
                    "'{$category}' is not a valid category. Valid options: ".implode(', ', $validCategories)
                );
            }
        }

        // Validate timeouts
        $timeouts = $data['timeouts'] ?? ['pageLoad' => 30000, 'action' => 10000];
        if (! is_array($timeouts)) {
            throw ConfigurationException::invalidType('timeouts', 'array', gettype($timeouts));
        }

        if (isset($timeouts['pageLoad']) && (! is_int($timeouts['pageLoad']) || $timeouts['pageLoad'] <= 0)) {
            throw ConfigurationException::invalidValue('timeouts.pageLoad', 'must be a positive integer');
        }

        if (isset($timeouts['action']) && (! is_int($timeouts['action']) || $timeouts['action'] <= 0)) {
            throw ConfigurationException::invalidValue('timeouts.action', 'must be a positive integer');
        }

        // Validate testUsers
        $testUsers = $data['testUsers'] ?? [];
        if (! is_array($testUsers)) {
            throw ConfigurationException::invalidType('testUsers', 'array', gettype($testUsers));
        }

        foreach ($testUsers as $index => $user) {
            if (! is_array($user)) {
                throw ConfigurationException::invalidValue(
                    "testUsers[{$index}]",
                    'must be an object with role, email, and password'
                );
            }

            if (! isset($user['role']) || ! is_string($user['role'])) {
                throw ConfigurationException::missingField("testUsers[{$index}].role");
            }

            if (! isset($user['email']) || ! is_string($user['email'])) {
                throw ConfigurationException::missingField("testUsers[{$index}].email");
            }

            if (! filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                throw ConfigurationException::invalidValue(
                    "testUsers[{$index}].email",
                    'must be a valid email address'
                );
            }

            if (! isset($user['password']) || ! is_string($user['password'])) {
                throw ConfigurationException::missingField("testUsers[{$index}].password");
            }
        }

        // Validate excludedTests
        $excludedTests = $data['excludedTests'] ?? [];
        if (! is_array($excludedTests)) {
            throw ConfigurationException::invalidType('excludedTests', 'array', gettype($excludedTests));
        }

        foreach ($excludedTests as $test) {
            if (! is_string($test)) {
                throw ConfigurationException::invalidValue('excludedTests', 'all entries must be strings');
            }
        }

        // Validate parallel
        $parallel = $data['parallel'] ?? false;
        if (! is_bool($parallel)) {
            throw ConfigurationException::invalidType('parallel', 'boolean', gettype($parallel));
        }

        // Validate fast
        $fast = $data['fast'] ?? false;
        if (! is_bool($fast)) {
            throw ConfigurationException::invalidType('fast', 'boolean', gettype($fast));
        }

        // Validate videoRecording
        $videoRecording = $data['videoRecording'] ?? true;
        if (! is_bool($videoRecording)) {
            throw ConfigurationException::invalidType('videoRecording', 'boolean', gettype($videoRecording));
        }

        // Build and return config object
        return new QAAuditConfig(
            baseUrl: $baseUrl,
            browsers: $browsers,
            viewports: $viewports,
            enabledCategories: $enabledCategories,
            timeouts: $timeouts,
            testUsers: $testUsers,
            excludedTests: $excludedTests,
            parallel: $parallel,
            fast: $fast,
            videoRecording: $videoRecording,
        );
    }
}
