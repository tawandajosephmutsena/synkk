<?php

namespace App\Console\Commands;

use App\Models\VaultCollaborationDocument;
use Illuminate\Console\Command;

class PruneCollaborationJournalCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vaults:prune-collaboration {--days=30 : Retention period in days for obsolete collaboration updates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune superseded collaboration journal updates older than retention threshold';

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
        $prunedCount = 0;

        // Iterate through all collaboration documents
        VaultCollaborationDocument::chunk(100, function ($documents) use ($cutoff, &$prunedCount) {
            /** @var VaultCollaborationDocument $document */
            foreach ($documents as $document) {
                // Find latest checkpoint sequence if available
                $latestCheckpoint = $document->updates()
                    ->where('is_checkpoint', true)
                    ->orderByDesc('sequence')
                    ->first();

                if ($latestCheckpoint) {
                    // Prune superseded non-checkpoint updates up to latest checkpoint sequence
                    $pruned = $document->updates()
                        ->where('sequence', '<', $latestCheckpoint->sequence)
                        ->where('is_checkpoint', false)
                        ->delete();
                    $prunedCount += $pruned;

                    // Prune older checkpoints older than cutoff
                    $prunedCheckpoints = $document->updates()
                        ->where('is_checkpoint', true)
                        ->where('sequence', '<', $latestCheckpoint->sequence)
                        ->where('created_at', '<=', $cutoff)
                        ->delete();
                    $prunedCount += $prunedCheckpoints;
                }
            }
        });

        $this->info("Successfully pruned {$prunedCount} obsolete collaboration update(s).");

        return self::SUCCESS;
    }
}
