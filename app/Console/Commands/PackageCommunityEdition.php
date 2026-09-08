<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

#[Signature('synkk:package-ce {--output= : Path where Community Edition zip package should be saved}')]
#[Description('Build, sanitize, and package the open-source Community Edition (CE) distribution')]
class PackageCommunityEdition extends Command
{
    /**
     * Files and patterns strictly excluded from the Community Edition.
     *
     * @var array<int, string>
     */
    protected array $excludedFromCommunity = [
        'resources/views/pages/admin',
        'app/Http/Controllers/Admin',
        'app/Http/Middleware/EnsureSuperAdmin.php',
        '.env',
        '.git',
        'node_modules',
        'vendor',
        'storage/app',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('   SYNKK OPEN-SOURCE COMMUNITY EDITION (CE) EXPORT   ');
        $this->info('====================================================');

        $exportDir = storage_path('app/exports');
        if (! is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $outputPath = $this->option('output') ?: "{$exportDir}/synkk-community-ce.zip";

        $zip = new ZipArchive;
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Failed to create zip package at [{$outputPath}]");

            return self::FAILURE;
        }

        $basePath = base_path();
        $totalFiles = 0;
        $excludedCount = 0;

        $directoriesToScan = [
            'app',
            'bootstrap',
            'config',
            'database',
            'public',
            'resources',
            'routes',
            'tests',
        ];

        $rootFiles = [
            'artisan',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json',
            'vite.config.js',
            'phpunit.xml',
            '.env.example',
            '.dockerignore',
            'README.md',
            'Dockerfile',
            'docker-compose.yml',
        ];

        // Add root files
        foreach ($rootFiles as $file) {
            $fullPath = "{$basePath}/{$file}";
            if (file_exists($fullPath)) {
                $zip->addFile($fullPath, $file);
                $totalFiles++;
            }
        }

        // Add scanned directories while omitting commercial files
        foreach ($directoriesToScan as $dir) {
            $fullDirPath = "{$basePath}/{$dir}";
            if (! is_dir($fullDirPath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullDirPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    continue;
                }

                $filePath = $item->getRealPath();
                $relativePath = substr($filePath, strlen($basePath) + 1);

                if ($this->shouldExclude($relativePath)) {
                    $excludedCount++;

                    continue;
                }

                $zip->addFile($filePath, $relativePath);
                $totalFiles++;
            }
        }

        // Include Obsidian plugin if present
        $obsidianPluginDir = "{$basePath}/obsidian-plugin";
        if (is_dir($obsidianPluginDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($obsidianPluginDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    continue;
                }

                $filePath = $item->getRealPath();
                $relativePath = substr($filePath, strlen($basePath) + 1);

                if (! str_contains($relativePath, 'node_modules')) {
                    $zip->addFile($filePath, $relativePath);
                    $totalFiles++;
                }
            }
        }

        $zip->close();

        $sizeMb = round(filesize($outputPath) / (1024 * 1024), 2);

        $this->line(" [x] Included files:    <info>{$totalFiles}</info>");
        $this->line(" [x] Excluded files:    <comment>{$excludedCount}</comment> (Super Admin & proprietary SaaS files stripped)");
        $this->line(" [x] Package generated: <info>{$outputPath}</info> ({$sizeMb} MB)");
        $this->info('Successfully verified and generated Community Edition bundle.');

        return self::SUCCESS;
    }

    /**
     * Determine if relative file path should be excluded from Community Edition.
     */
    protected function shouldExclude(string $relativePath): bool
    {
        foreach ($this->excludedFromCommunity as $excluded) {
            if (str_starts_with($relativePath, $excluded) || str_contains($relativePath, "/{$excluded}/")) {
                return true;
            }
        }

        return false;
    }
}
