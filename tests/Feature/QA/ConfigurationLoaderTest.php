<?php

use App\Exceptions\ConfigurationException;
use App\Services\QA\ConfigurationLoader;
use App\Services\QA\DTOs\QAAuditConfig;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->configPath = base_path('qa-audit.config.json');
    $this->loader = new ConfigurationLoader;

    // Clean up any existing config file
    if (File::exists($this->configPath)) {
        File::delete($this->configPath);
    }
});

afterEach(function () {
    // Clean up config file after each test
    if (File::exists($this->configPath)) {
        File::delete($this->configPath);
    }
});

test('generates template configuration file when missing', function () {
    expect(File::exists($this->configPath))->toBeFalse();

    $config = $this->loader->load();

    expect(File::exists($this->configPath))->toBeTrue();
    expect($config)->toBeInstanceOf(QAAuditConfig::class);
    expect($config->baseUrl)->toBe('http://localhost:8000');
    expect($config->browsers)->toBe(['chromium']);
    expect($config->viewports)->toBe([320, 768, 1024, 1920]);
});

test('loads valid configuration file', function () {
    $validConfig = [
        'baseUrl' => 'http://example.com:3000',
        'browsers' => ['chromium', 'firefox'],
        'viewports' => [768, 1024],
        'enabledCategories' => ['happy_path', 'security'],
        'timeouts' => [
            'pageLoad' => 60000,
            'action' => 20000,
        ],
        'testUsers' => [
            [
                'role' => 'admin',
                'email' => 'admin@test.com',
                'password' => 'secret123',
            ],
        ],
        'excludedTests' => ['SlowTest'],
        'parallel' => true,
        'fast' => true,
        'videoRecording' => false,
    ];

    File::put($this->configPath, json_encode($validConfig));

    $config = $this->loader->load();

    expect($config->baseUrl)->toBe('http://example.com:3000');
    expect($config->browsers)->toBe(['chromium', 'firefox']);
    expect($config->viewports)->toBe([768, 1024]);
    expect($config->enabledCategories)->toBe(['happy_path', 'security']);
    expect($config->timeouts)->toBe(['pageLoad' => 60000, 'action' => 20000]);
    expect($config->testUsers)->toHaveCount(1);
    expect($config->excludedTests)->toBe(['SlowTest']);
    expect($config->parallel)->toBeTrue();
    expect($config->fast)->toBeTrue();
    expect($config->videoRecording)->toBeFalse();
});

test('throws exception for invalid JSON', function () {
    File::put($this->configPath, '{invalid json}');

    $this->loader->load();
})->throws(ConfigurationException::class, 'Invalid JSON');

test('throws exception for invalid baseUrl type', function () {
    File::put($this->configPath, json_encode(['baseUrl' => 12345]));

    $this->loader->load();
})->throws(ConfigurationException::class, 'must be of type string');

test('throws exception for invalid baseUrl format', function () {
    File::put($this->configPath, json_encode(['baseUrl' => 'not-a-url']));

    $this->loader->load();
})->throws(ConfigurationException::class, 'must be a valid URL');

test('throws exception for invalid browser value', function () {
    File::put($this->configPath, json_encode([
        'baseUrl' => 'http://localhost:8000',
        'browsers' => ['chromium', 'safari'],
    ]));

    $this->loader->load();
})->throws(ConfigurationException::class, 'not a valid browser');

test('throws exception for invalid viewport values', function () {
    File::put($this->configPath, json_encode([
        'baseUrl' => 'http://localhost:8000',
        'viewports' => [320, -100, 1024],
    ]));

    $this->loader->load();
})->throws(ConfigurationException::class, 'must be positive integers');

test('throws exception for invalid category', function () {
    File::put($this->configPath, json_encode([
        'baseUrl' => 'http://localhost:8000',
        'enabledCategories' => ['happy_path', 'invalid_category'],
    ]));

    $this->loader->load();
})->throws(ConfigurationException::class, 'not a valid category');

test('throws exception for invalid timeout values', function () {
    File::put($this->configPath, json_encode([
        'baseUrl' => 'http://localhost:8000',
        'timeouts' => [
            'pageLoad' => -5000,
            'action' => 10000,
        ],
    ]));

    $this->loader->load();
})->throws(ConfigurationException::class, 'must be a positive integer');

test('throws exception for missing test user role', function () {
    File::put($this->configPath, json_encode([
        'baseUrl' => 'http://localhost:8000',
        'testUsers' => [
            [
                'email' => 'test@example.com',
                'password' => 'secret',
            ],
        ],
    ]));

    $this->loader->load();
})->throws(ConfigurationException::class, 'testUsers[0].role');

test('throws exception for invalid test user email', function () {
    File::put($this->configPath, json_encode([
        'baseUrl' => 'http://localhost:8000',
        'testUsers' => [
            [
                'role' => 'admin',
                'email' => 'not-an-email',
                'password' => 'secret',
            ],
        ],
    ]));

    $this->loader->load();
})->throws(ConfigurationException::class, 'must be a valid email address');

test('throws exception for missing test user password', function () {
    File::put($this->configPath, json_encode([
        'baseUrl' => 'http://localhost:8000',
        'testUsers' => [
            [
                'role' => 'admin',
                'email' => 'test@example.com',
            ],
        ],
    ]));

    $this->loader->load();
})->throws(ConfigurationException::class, 'testUsers[0].password');

test('throws exception for invalid parallel type', function () {
    File::put($this->configPath, json_encode([
        'baseUrl' => 'http://localhost:8000',
        'parallel' => 'yes',
    ]));

    $this->loader->load();
})->throws(ConfigurationException::class, 'must be of type boolean');

test('uses default values for missing optional fields', function () {
    File::put($this->configPath, json_encode([
        'baseUrl' => 'http://localhost:8000',
    ]));

    $config = $this->loader->load();

    expect($config->browsers)->toBe(['chromium']);
    expect($config->parallel)->toBeFalse();
    expect($config->fast)->toBeFalse();
    expect($config->videoRecording)->toBeTrue();
});

test('generated template contains all required fields', function () {
    $this->loader->load();

    expect(File::exists($this->configPath))->toBeTrue();

    $content = File::get($this->configPath);
    $data = json_decode($content, true);

    expect($data)->toHaveKeys([
        'baseUrl',
        'browsers',
        'viewports',
        'enabledCategories',
        'timeouts',
        'testUsers',
        'excludedTests',
        'parallel',
        'fast',
        'videoRecording',
    ]);

    expect($data['testUsers'])->toBeArray();
    expect($data['testUsers'])->toHaveCount(2);
    expect($data['testUsers'][0])->toHaveKeys(['role', 'email', 'password']);
});

test('config DTO toArray method returns correct structure', function () {
    $config = new QAAuditConfig(
        baseUrl: 'http://test.com',
        browsers: ['firefox'],
        viewports: [1024],
        enabledCategories: ['security'],
        timeouts: ['pageLoad' => 30000, 'action' => 10000],
        testUsers: [],
        excludedTests: [],
        parallel: true,
        fast: false,
        videoRecording: true
    );

    $array = $config->toArray();

    expect($array)->toBe([
        'baseUrl' => 'http://test.com',
        'browsers' => ['firefox'],
        'viewports' => [1024],
        'enabledCategories' => ['security'],
        'timeouts' => ['pageLoad' => 30000, 'action' => 10000],
        'testUsers' => [],
        'excludedTests' => [],
        'parallel' => true,
        'fast' => false,
        'videoRecording' => true,
    ]);
});
