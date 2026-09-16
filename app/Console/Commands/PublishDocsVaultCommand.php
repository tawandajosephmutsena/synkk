<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\Vault;
use App\Models\VaultFile;
use App\Models\VaultPortal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PublishDocsVaultCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'synkk:publish-docs-vault {--team= : The team ID to associate with the published vault}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed and publish the official Synkk Documentation Obsidian Vault and Livewire Portal';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Seeding and publishing Synkk Documentation Vault...');

        $teamId = $this->option('team');
        $team = $teamId ? Team::find($teamId) : Team::first();

        if (! $team) {
            $this->error('No team found in database. Please run migrations and create a team first.');

            return self::FAILURE;
        }

        $userId = $team->user_id ?? $team->owner_id ?? 1;

        // 1. Find or create the Vault
        $vault = Vault::firstOrCreate(
            ['slug' => 'synkk-docs'],
            [
                'team_id' => $team->id,
                'created_by' => $userId,
                'name' => 'Synkk Documentation',
                'description' => 'Official Synkk systems architecture, guides, and documentation vault.',
                'default_permission' => 'read_only',
                'is_e2ee' => false,
            ]
        );

        $this->info("✓ Vault established: {$vault->name} (id: {$vault->id}, slug: {$vault->slug})");

        // 2. Scan and populate markdown notes
        $sourceDir = database_path('seeders/data/synkk-docs-vault');
        if (! File::isDirectory($sourceDir)) {
            $this->error("Source documentation directory not found at: {$sourceDir}");

            return self::FAILURE;
        }

        $disk = config('synkk.storage_disk', 'local');
        $files = File::allFiles($sourceDir);
        $count = 0;
        $primaryFile = null;

        foreach ($files as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());

            // Skip hidden or config files
            if (str_starts_with($relativePath, '.') || str_contains($relativePath, '/.')) {
                continue;
            }

            $content = File::get($file->getRealPath());
            $size = strlen($content);
            $sha256 = hash('sha256', $content);
            $cleanPath = trim($relativePath, '/');
            $storagePath = "vaults/{$vault->id}/{$cleanPath}";

            Storage::disk($disk)->put($storagePath, $content);

            $vaultFile = VaultFile::updateOrCreate(
                [
                    'vault_id' => $vault->id,
                    'path' => $cleanPath,
                ],
                [
                    'storage_path' => $storagePath,
                    'sha256' => $sha256,
                    'size' => $size,
                    'version' => 1,
                    'is_deleted' => false,
                    'last_modified_by' => $userId,
                    'is_encrypted' => false,
                    'is_ghost' => false,
                    'original_size' => $size,
                    'mime_type' => 'text/markdown',
                ]
            );

            if ($cleanPath === '00-Overview/Welcome to Synkk.md') {
                $primaryFile = $vaultFile;
            }

            $count++;
        }

        $this->info("✓ Synchronized {$count} documentation notes to storage disk [{$disk}].");

        // 3. Create or update the Interactive Livewire Vault Portal
        $portal = VaultPortal::updateOrCreate(
            ['slug' => 'synkk-docs'],
            [
                'team_id' => $team->id,
                'vault_id' => $vault->id,
                'created_by' => $userId,
                'name' => 'Synkk Documentation & Systems Architecture',
                'description' => 'The official interactive documentation, architecture, and developer reference for Synkk, powered by Livewire.',
                'layout' => 'docs',
                'theme' => 'enterprise-docs',
                'font_family' => 'inter',
                'accent_color' => '#10b981',
                'is_public' => true,
                'root_path' => '',
                'primary_file_id' => $primaryFile?->id,
                'settings' => [
                    'show_breadcrumbs' => true,
                    'show_table_of_contents' => true,
                    'show_backlinks' => true,
                    'enable_client_search' => true,
                ],
            ]
        );

        $portalUrl = url("/p/{$portal->slug}");
        $this->info('🎉 Portal successfully published!');
        $this->info("🌐 Live URL: {$portalUrl}");

        return self::SUCCESS;
    }
}
