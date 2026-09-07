<?php

namespace App\Services;

use App\Models\Vault;
use App\Models\VaultChangeLog;
use App\Models\VaultFile;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Number;

class VaultAnalyticsService
{
    /**
     * Supported image extensions.
     *
     * @var array<string>
     */
    protected const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'bmp', 'ico'];

    /**
     * Get aggregate statistics across all active vaults in the system.
     *
     * @return array<string, mixed>
     */
    public function getGlobalStats(?string $timeframe = '30d'): array
    {
        $since = $this->resolveTimeframeCarbon($timeframe);

        $fileMetrics = $this->computeFileMetrics(VaultFile::where('is_deleted', false));
        $totalFiles = $fileMetrics['total_files'];
        $totalStorageBytes = $fileMetrics['total_storage_bytes'];
        $notesCount = $fileMetrics['notes_count'];
        $notesStorageBytes = $fileMetrics['notes_storage_bytes'];
        $imagesCount = $fileMetrics['images_count'];
        $imagesSizeBytes = $fileMetrics['images_size_bytes'];
        $imageBreakdown = $fileMetrics['image_breakdown'];
        $canvasCount = $fileMetrics['canvas_count'];

        $estimatedTotalWords = (int) round($notesStorageBytes / 5.5);
        $readingTimeMinutes = max(1, (int) ceil($estimatedTotalWords / 200));
        $totalCharacters = $notesStorageBytes;

        // Activity metrics
        $activityQuery = VaultChangeLog::query();
        if ($since) {
            $activityQuery->where('created_at', '>=', $since);
        }

        $periodChanges = (clone $activityQuery)->count();
        $periodCreations = (clone $activityQuery)->where('action', 'created')->count();
        $periodUpdates = (clone $activityQuery)->where('action', 'updated')->count();
        $periodDeletions = (clone $activityQuery)->where('action', 'deleted')->count();
        $periodConflicts = (clone $activityQuery)->where('action', 'conflict')->count();

        return [
            'total_vaults' => Vault::count(),
            'total_files' => $totalFiles,
            'total_storage_bytes' => $totalStorageBytes,
            'total_storage_formatted' => $this->formatBytes($totalStorageBytes),
            'formatted_storage' => $this->formatBytes($totalStorageBytes),
            'notes_count' => $notesCount,
            'estimated_words' => $estimatedTotalWords,
            'reading_time_minutes' => $readingTimeMinutes,
            'total_characters' => $totalCharacters,
            'images_count' => $imagesCount,
            'images_size_bytes' => $imagesSizeBytes,
            'images_size_formatted' => $this->formatBytes($imagesSizeBytes),
            'image_breakdown' => $imageBreakdown,
            'canvas_count' => $canvasCount,
            'period_changes' => $periodChanges,
            'period_creations' => $periodCreations,
            'period_updates' => $periodUpdates,
            'period_deletions' => $periodDeletions,
            'period_conflicts' => $periodConflicts,
            'timeframe' => $timeframe,
        ];
    }

    /**
     * Get detailed analytics for a single vault.
     *
     * @return array<string, mixed>
     */
    public function getVaultStats(Vault|int $vault, ?string $timeframe = '30d'): array
    {
        $vault = $vault instanceof Vault ? $vault : Vault::findOrFail($vault);
        $since = $this->resolveTimeframeCarbon($timeframe);

        $fileMetrics = $this->computeFileMetrics($vault->files()->where('is_deleted', false));
        $totalFiles = $fileMetrics['total_files'];
        $totalStorageBytes = $fileMetrics['total_storage_bytes'];
        $notesCount = $fileMetrics['notes_count'];
        $notesStorageBytes = $fileMetrics['notes_storage_bytes'];
        $imagesCount = $fileMetrics['images_count'];
        $imagesStorageBytes = $fileMetrics['images_size_bytes'];
        $imageBreakdown = $fileMetrics['image_breakdown'];
        $canvasCount = $fileMetrics['canvas_count'];

        // Note analytics
        $averageNoteSizeBytes = $notesCount > 0 ? (int) round($notesStorageBytes / $notesCount) : 0;
        $estimatedTotalWords = (int) round($notesStorageBytes / 5.5);
        $averageWordsPerNote = $notesCount > 0 ? (int) round($estimatedTotalWords / $notesCount) : 0;
        $readingTimeMinutes = max(1, (int) ceil($estimatedTotalWords / 200));
        $totalCharacters = $notesStorageBytes;

        // Activity query
        $activityQuery = $vault->changeLogs();
        if ($since) {
            $activityQuery->where('created_at', '>=', $since);
        }

        $periodChanges = (clone $activityQuery)->count();
        $periodCreations = (clone $activityQuery)->where('action', 'created')->count();
        $periodUpdates = (clone $activityQuery)->where('action', 'updated')->count();
        $periodDeletions = (clone $activityQuery)->where('action', 'deleted')->count();
        $periodConflicts = (clone $activityQuery)->where('action', 'conflict')->count();

        return [
            'vault' => [
                'id' => $vault->id,
                'name' => $vault->name,
                'slug' => $vault->slug,
                'description' => $vault->description,
                'team_id' => $vault->team_id,
            ],
            'total_files' => $totalFiles,
            'total_storage_bytes' => $totalStorageBytes,
            'total_storage_formatted' => $this->formatBytes($totalStorageBytes),
            'formatted_storage' => $this->formatBytes($totalStorageBytes),
            'notes_count' => $notesCount,
            'notes_storage_bytes' => $notesStorageBytes,
            'formatted_notes_storage' => $this->formatBytes($notesStorageBytes),
            'average_note_size' => $this->formatBytes($averageNoteSizeBytes),
            'estimated_words' => $estimatedTotalWords,
            'estimated_total_words' => $estimatedTotalWords,
            'average_words_per_note' => $averageWordsPerNote,
            'reading_time_minutes' => $readingTimeMinutes,
            'total_characters' => $totalCharacters,
            'images_count' => $imagesCount,
            'images_size_bytes' => $imagesStorageBytes,
            'images_size_formatted' => $this->formatBytes($imagesStorageBytes),
            'image_breakdown' => $imageBreakdown,
            'canvas_count' => $canvasCount,
            'period_changes' => $periodChanges,
            'period_creations' => $periodCreations,
            'period_updates' => $periodUpdates,
            'period_deletions' => $periodDeletions,
            'period_conflicts' => $periodConflicts,
            'timeframe' => $timeframe,
        ];
    }

    /**
     * Compute internal wiki-links [[wiki-link]] graph connectivity and hub note analysis.
     *
     * @return array<string, mixed>
     */
    public function getWikiLinkGraphStats(Vault|int|null $vault = null): array
    {
        $query = VaultFile::where('is_deleted', false)->where('path', 'like', '%.md');

        if ($vault) {
            $vaultId = $vault instanceof Vault ? $vault->id : $vault;
            $query->where('vault_id', $vaultId);
        }

        // Limit scanned files to 200 for peak real-time performance
        $markdownFiles = $query->limit(200)->get();

        $inboundMap = [];
        $outboundMap = [];
        $allNoteNames = [];
        $totalLinksCount = 0;

        foreach ($markdownFiles as $file) {
            $filename = strtolower(pathinfo($file->path, PATHINFO_FILENAME));
            $allNoteNames[$filename] = $file->path;
            $inboundMap[$filename] = $inboundMap[$filename] ?? 0;
            $outboundMap[$file->path] = 0;
        }

        foreach ($markdownFiles as $file) {
            $content = $file->getContents();
            if (empty($content)) {
                continue;
            }

            // Match [[target]] or [[target|label]] or [[target#heading]]
            preg_match_all('/\[\[([^\]\|#]+)(?:#[^\]\|]+)?(?:\|[^\]]+)?\]\]/', $content, $matches);

            if (! empty($matches[1])) {
                $uniqueTargetsInNote = array_unique(array_map('trim', $matches[1]));
                $outboundCount = count($matches[1]);
                $totalLinksCount += $outboundCount;
                $outboundMap[$file->path] = ($outboundMap[$file->path] ?? 0) + $outboundCount;

                foreach ($uniqueTargetsInNote as $target) {
                    $targetKey = strtolower(pathinfo($target, PATHINFO_FILENAME));
                    $inboundMap[$targetKey] = ($inboundMap[$targetKey] ?? 0) + 1;
                }
            }
        }

        // Top Hub Notes (most inbound links)
        arsort($inboundMap);
        $topHubs = [];
        foreach (array_slice($inboundMap, 0, 8, true) as $name => $count) {
            if ($count > 0) {
                $topHubs[] = [
                    'title' => ucwords(str_replace(['-', '_'], ' ', $name)),
                    'inbound_links' => $count,
                    'path' => $allNoteNames[$name] ?? "{$name}.md",
                ];
            }
        }

        // Outbound champions
        arsort($outboundMap);
        $topOutbound = [];
        foreach (array_slice($outboundMap, 0, 5, true) as $path => $count) {
            if ($count > 0) {
                $topOutbound[] = [
                    'title' => pathinfo($path, PATHINFO_FILENAME),
                    'outbound_links' => $count,
                    'path' => $path,
                ];
            }
        }

        $notesCount = $markdownFiles->count();
        $linkDensity = $notesCount > 0 ? round($totalLinksCount / $notesCount, 1) : 0.0;

        // Orphan notes count (0 inbound and 0 outbound)
        $orphanCount = 0;
        foreach ($markdownFiles as $file) {
            $fn = strtolower(pathinfo($file->path, PATHINFO_FILENAME));
            $in = $inboundMap[$fn] ?? 0;
            $out = $outboundMap[$file->path] ?? 0;
            if ($in === 0 && $out === 0) {
                $orphanCount++;
            }
        }

        $connectedNotesCount = max(0, $notesCount - $orphanCount);

        return [
            'total_links' => $totalLinksCount,
            'density' => $linkDensity,
            'top_hubs' => $topHubs,
            'top_outbound' => $topOutbound,
            'orphan_notes_count' => $orphanCount,
            'unique_targets_count' => count(array_filter($inboundMap, fn ($c) => $c > 0)),
            'connected_notes_count' => $connectedNotesCount,
            'scanned_notes_count' => $notesCount,
        ];
    }

    /**
     * Get active contributor leaderboard with action breakdowns and device telemetry.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getContributorLeaderboard(Vault|int|null $vault = null, ?string $timeframe = '30d'): array
    {
        $since = $this->resolveTimeframeCarbon($timeframe);
        $query = VaultChangeLog::query();

        if ($vault) {
            $vaultId = $vault instanceof Vault ? $vault->id : $vault;
            $query->where('vault_id', $vaultId);
        }

        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        $changeLogs = $query->with(['user', 'vault'])->get();
        $totalChanges = $changeLogs->count();

        if ($totalChanges === 0) {
            return [];
        }

        // Group by user_id
        $grouped = $changeLogs->groupBy('user_id');
        $leaderboard = [];

        foreach ($grouped as $userId => $logs) {
            $firstLog = $logs->first();
            $user = $firstLog?->user;
            $userName = $user ? $user->name : __('Obsidian Sync Device');
            $userEmail = $user ? $user->email : __('Local Token Session');

            $actionCounts = $logs->groupBy('action')->map->count();
            $createdCount = $actionCounts->get('created', 0);
            $updatedCount = $actionCounts->get('updated', 0);
            $deletedCount = $actionCounts->get('deleted', 0);
            $conflictCount = $actionCounts->get('conflict', 0);
            $userTotal = $logs->count();

            $devices = $logs->pluck('device_name')->filter()->unique()->values()->all();
            $deviceName = ! empty($devices) ? $devices[0] : 'Obsidian Client';
            $lastActive = $logs->max('created_at');

            $leaderboard[] = [
                'user_id' => $userId,
                'user_name' => $userName,
                'name' => $userName,
                'email' => $userEmail,
                'initials' => $user ? $user->initials() : 'DEV',
                'mutations_count' => $userTotal,
                'total_changes' => $userTotal,
                'creations' => $createdCount,
                'created_count' => $createdCount,
                'updates' => $updatedCount,
                'updated_count' => $updatedCount,
                'deletions' => $deletedCount,
                'deleted_count' => $deletedCount,
                'conflicts' => $conflictCount,
                'conflict_count' => $conflictCount,
                'devices' => ! empty($devices) ? $devices : ['Desktop Client'],
                'device_name' => $deviceName,
                'last_active_human' => $lastActive instanceof Carbon ? $lastActive->diffForHumans() : 'Recently',
                'last_active' => $lastActive instanceof Carbon ? $lastActive->diffForHumans() : 'Recently',
            ];
        }

        // Sort by total changes descending
        usort($leaderboard, fn ($a, $b) => $b['mutations_count'] <=> $a['mutations_count']);

        return $leaderboard;
    }

    /**
     * Get day-by-day activity velocity timeline for sparklines and activity graphs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActivityVelocityTimeline(Vault|int|null $vault = null, int $days = 14): array
    {
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        $query = VaultChangeLog::where('created_at', '>=', $startDate);

        if ($vault) {
            $vaultId = $vault instanceof Vault ? $vault->id : $vault;
            $query->where('vault_id', $vaultId);
        }

        $logs = $query->get();

        $buckets = [];
        $maxDaily = 1;

        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::today()->subDays($days - 1 - $i);
            $dateKey = $date->format('Y-m-d');
            $label = $date->format('M j');

            $dayLogs = $logs->filter(function (VaultChangeLog $log) use ($dateKey) {
                return Carbon::parse($log->created_at)->format('Y-m-d') === $dateKey;
            });

            $total = $dayLogs->count();
            $created = $dayLogs->where('action', 'created')->count();
            $updated = $dayLogs->where('action', 'updated')->count();
            $deleted = $dayLogs->where('action', 'deleted')->count();
            $conflict = $dayLogs->where('action', 'conflict')->count();

            if ($total > $maxDaily) {
                $maxDaily = $total;
            }

            $buckets[] = [
                'date' => $dateKey,
                'label' => $label,
                'day_name' => $date->format('D'),
                'total' => $total,
                'created' => $created,
                'updated' => $updated,
                'deleted' => $deleted,
                'conflict' => $conflict,
                'height_percent' => max(8, (int) round(($total / max(1, $maxDaily)) * 100)),
            ];
        }

        return $buckets;
    }

    /**
     * Get live audit and mutation change stream.
     *
     * @return Collection<int, VaultChangeLog>
     */
    public function getRecentChangeFeed(
        Vault|int|null $vault = null,
        ?string $action = null,
        ?int $userId = null,
        ?string $search = null,
        int $limit = 50
    ): Collection {
        $query = VaultChangeLog::with(['user', 'vault'])
            ->orderBy('id', 'desc');

        if ($vault) {
            $vaultId = $vault instanceof Vault ? $vault->id : $vault;
            $query->where('vault_id', $vaultId);
        }

        if ($action && in_array($action, ['created', 'updated', 'deleted', 'conflict'], true)) {
            $query->where('action', $action);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if (! empty($search)) {
            $query->where('path', 'like', '%'.$search.'%');
        }

        return $query->limit($limit)->get();
    }

    /**
     * Compute aggregated file and storage statistics from a base VaultFile query.
     *
     * @param  Builder<VaultFile>|HasMany<VaultFile, covariant Vault>  $query
     * @return array{
     *     total_files: int,
     *     total_storage_bytes: int,
     *     notes_count: int,
     *     notes_storage_bytes: int,
     *     images_count: int,
     *     images_size_bytes: int,
     *     image_breakdown: array<string, array{count: int, size_bytes: int, size_formatted: string}>,
     *     canvas_count: int
     * }
     */
    protected function computeFileMetrics(Builder|HasMany $query): array
    {
        $totalFiles = (clone $query)->count();
        $totalStorageBytes = (int) (clone $query)->sum('size');

        $notesQuery = (clone $query)->where('path', 'like', '%.md');
        $notesCount = (clone $notesQuery)->count();
        $notesStorageBytes = (int) (clone $notesQuery)->sum('size');

        $canvasCount = (clone $query)->where('path', 'like', '%.canvas')->count();

        $imagesCount = 0;
        $imagesSizeBytes = 0;
        $imageBreakdown = [];

        foreach (self::IMAGE_EXTENSIONS as $ext) {
            $extQuery = (clone $query)->where('path', 'like', "%.{$ext}");
            $extCount = (clone $extQuery)->count();
            $extSize = (int) (clone $extQuery)->sum('size');

            $imageBreakdown[$ext] = [
                'count' => $extCount,
                'size_bytes' => $extSize,
                'size_formatted' => $this->formatBytes($extSize),
            ];

            $imagesCount += $extCount;
            $imagesSizeBytes += $extSize;
        }

        return [
            'total_files' => $totalFiles,
            'total_storage_bytes' => $totalStorageBytes,
            'notes_count' => $notesCount,
            'notes_storage_bytes' => $notesStorageBytes,
            'images_count' => $imagesCount,
            'images_size_bytes' => $imagesSizeBytes,
            'image_breakdown' => $imageBreakdown,
            'canvas_count' => $canvasCount,
        ];
    }

    /**
     * Resolve carbon instance from timeframe identifier.
     */
    protected function resolveTimeframeCarbon(?string $timeframe): ?Carbon
    {
        return match ($timeframe) {
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            '90d' => Carbon::now()->subDays(90),
            'all' => null,
            default => Carbon::now()->subDays(30),
        };
    }

    /**
     * Format raw byte count into human readable size string.
     */
    public function formatBytes(int $bytes, int $precision = 1): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        return Number::fileSize($bytes, precision: $precision);
    }
}
