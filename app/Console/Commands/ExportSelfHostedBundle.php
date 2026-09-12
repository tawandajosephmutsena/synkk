<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;

class ExportSelfHostedBundle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'synkk:export-bundle {--output= : Path where zip package should be saved}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Package and export standalone Synkk self-hosted distribution zip bundle';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Synkk Self-Hosted Distribution Packaging...');

        $exportDir = storage_path('app/exports');
        if (! is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $outputPath = $this->option('output') ?: "{$exportDir}/synkk-self-hosted-v1.0.0.zip";

        $zip = new ZipArchive;
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Failed to create zip file at [{$outputPath}]");

            return self::FAILURE;
        }

        $basePath = base_path();

        // Essential files & directories to include in self-hosted bundle
        $filesToInclude = [
            'Dockerfile',
            'docker-compose.yml',
            '.env.example',
            'artisan',
            'composer.json',
            'package.json',
            'vite.config.js',
            'AGENTS.md',
            'README.md',
        ];

        $directoriesToInclude = [
            'app',
            'bootstrap',
            'config',
            'database/factories',
            'database/migrations',
            'database/seeders',
            'docker',
            'public',
            'resources',
            'routes',
        ];

        foreach ($filesToInclude as $file) {
            $fullPath = "{$basePath}/{$file}";
            if (file_exists($fullPath)) {
                $zip->addFile($fullPath, "synkk-self-hosted/{$file}");
            }
        }

        foreach ($directoriesToInclude as $dir) {
            $fullDirPath = "{$basePath}/{$dir}";
            if (is_dir($fullDirPath)) {
                $this->addDirectoryToZip($zip, $fullDirPath, "synkk-self-hosted/{$dir}");
            }
        }

        // Add self-hosted setup guide README
        $guideContent = <<<'TEXT'
# Synkk Self-Hosted Package (v1.0.0)

Thank you for purchasing Synkk Self-Hosted!

## 🚀 1-Command Quickstart

To launch your self-hosted Synkk server instantly with zero pre-installed PHP dependencies:

1. Open your terminal in this directory.
2. Run:
   ```bash
   docker compose up -d
   ```
3. Open your browser and navigate to:
   ```text
   http://localhost:8000
   ```
4. Register your admin user, log in, create your team and vaults, and generate device sync tokens for Obsidian!

## 🛠️ Configuration & License

- Edit `.env` or set environment variables in `docker-compose.yml`.
- Enter your LemonSqueezy license key under Team Settings to activate your Lifetime License.

Support & Docs: https://synkk.space
TEXT;

        $zip->addFromString('synkk-self-hosted/SETUP_GUIDE.md', $guideContent);

        $zip->close();

        $sizeMb = round(filesize($outputPath) / (1024 * 1024), 2);
        $this->info('✅ Successfully generated self-hosted bundle!');
        $this->line("Path: {$outputPath} ({$sizeMb} MB)");
        $this->line('Command to launch: docker compose up -d');

        return self::SUCCESS;
    }

    private function addDirectoryToZip(ZipArchive $zip, string $dirPath, string $zipPath): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen(base_path()) + 1);

            // Skip node_modules, storage logs, vendor, git, and ds_store
            if (str_contains($relativePath, 'node_modules') ||
                str_contains($relativePath, 'vendor') ||
                str_contains($relativePath, '.git') ||
                str_contains($relativePath, '.DS_Store') ||
                str_contains($relativePath, 'storage/logs')
            ) {
                continue;
            }

            $localZipPath = "synkk-self-hosted/{$relativePath}";

            if ($file->isDir()) {
                $zip->addEmptyDir($localZipPath);
            } else {
                $zip->addFile($filePath, $localZipPath);
            }
        }
    }
}
