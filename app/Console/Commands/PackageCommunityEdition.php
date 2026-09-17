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
    /** @var array<int, string> */
    protected array $sourceDirectories = [
        'app',
        'config',
        'database/factories',
        'database/migrations',
        'database/seeders',
        'public',
        'resources',
        'routes',
    ];

    /** @var array<int, string> */
    protected array $excludedPaths = [
        'resources/views/pages/admin',
        'app/Http/Controllers/Admin',
        'app/Http/Middleware/EnsureSuperAdmin.php',
        'public/hot',
        'public/storage',
    ];

    /** @var array<int, string> */
    protected array $forbiddenArchivePatterns = [
        '#(^|/)\.env(?:$|\.(?!example$))#i',
        '#(^|/)\.git(?:/|$)#i',
        '#(^|/)\.DS_Store$#i',
        '#(^|/)cookies(?:\.[^/]+)?\.txt$#i',
        '#(^|/)database/.*\.(?:sqlite|sqlite3|db)(?:[-.].*)?$#i',
        '#(^|/)database/.*\.backup(?:[-.].*)?$#i',
        '#(^|/)bootstrap/cache/#i',
        '#(^|/)storage/#i',
        '#(^|/)node_modules/#i',
        '#(^|/)vendor/#i',
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

        $rootFiles = [
            'artisan',
            'bootstrap/app.php',
            'bootstrap/providers.php',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json',
            'vite.config.js',
            '.env.example',
            '.dockerignore',
            '.gitignore',
            'LICENSE',
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
        foreach ($this->sourceDirectories as $dir) {
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

                if ($this->shouldExclude($relativePath) || $item->isLink()) {
                    $excludedCount++;

                    continue;
                }

                $zip->addFile($filePath, $relativePath);
                $totalFiles++;
            }
        }

        $totalFiles += $this->addPluginSources($zip, $basePath);

        $zip->close();

        if (! $this->archiveIsSafe($outputPath)) {
            @unlink($outputPath);
            $this->error('Package safety verification failed. The archive was deleted.');

            return self::FAILURE;
        }

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
        foreach ($this->excludedPaths as $excluded) {
            if (str_starts_with($relativePath, $excluded) || str_contains($relativePath, "/{$excluded}/")) {
                return true;
            }
        }

        foreach ($this->forbiddenArchivePatterns as $pattern) {
            if (preg_match($pattern, $relativePath) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function addPluginSources(ZipArchive $zip, string $basePath): int
    {
        $pluginRoot = "{$basePath}/obsidian-plugin";
        $pluginPaths = [
            'src',
            'esbuild.config.mjs',
            'main.js',
            'manifest.json',
            'package.json',
            'package-lock.json',
            'styles.css',
            'tsconfig.json',
            'versions.json',
        ];
        $included = 0;

        foreach ($pluginPaths as $pluginPath) {
            $absolutePath = "{$pluginRoot}/{$pluginPath}";
            if (is_file($absolutePath)) {
                $zip->addFile($absolutePath, "obsidian-plugin/{$pluginPath}");
                $included++;

                continue;
            }

            if (! is_dir($absolutePath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absolutePath, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $item) {
                if (! $item->isFile() || $item->isLink()) {
                    continue;
                }

                $relativePath = substr($item->getRealPath(), strlen($basePath) + 1);
                if ($this->shouldExclude($relativePath)) {
                    continue;
                }

                $zip->addFile($item->getRealPath(), $relativePath);
                $included++;
            }
        }

        return $included;
    }

    protected function archiveIsSafe(string $outputPath): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($outputPath) !== true) {
            return false;
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->getNameIndex($index);
            if ($entry === false || $this->shouldExclude($entry)) {
                $zip->close();

                return false;
            }
        }

        $zip->close();

        return true;
    }
}
