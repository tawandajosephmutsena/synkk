<?php

namespace App\Console\Commands;

use App\Models\VaultFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneDeletedVaultFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vaults:prune-deleted {--days=30 : Retention period in days for deleted files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune soft-deleted vault file records and their storage payloads older than retention threshold';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 0) {
            $this->error('Days parameter must be a non-negative integer.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $expiredFiles = VaultFile::where('is_deleted', true)
            ->where('updated_at', '<=', $cutoff)
            ->with('versions')
            ->get();

        $prunedCount = 0;

        $disk = config('synkk.storage_disk', 'local');

        foreach ($expiredFiles as $file) {
            foreach ($file->versions as $version) {
                if ($version->storage_path && Storage::disk($disk)->exists($version->storage_path)) {
                    Storage::disk($disk)->delete($version->storage_path);
                }
            }

            if ($file->storage_path && Storage::disk($disk)->exists($file->storage_path)) {
                Storage::disk($disk)->delete($file->storage_path);
            }

            $file->delete();
            $prunedCount++;
        }

        $this->info("Successfully pruned {$prunedCount} deleted vault file(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
