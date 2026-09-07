<?php

namespace App\Services;

class ThreeWayDiffService
{
    /**
     * Compute a 3-way diff between base, ours (current), and theirs (incoming/conflict).
     *
     * @return array{
     *     has_conflicts: bool,
     *     conflict_count: int,
     *     clean_count: int,
     *     merged_content: string,
     *     hunks: array<int, array{
     *         id: int,
     *         type: string,
     *         is_conflict: bool,
     *         base_lines: array<int, string>,
     *         our_lines: array<int, string>,
     *         their_lines: array<int, string>,
     *         resolved_lines: array<int, string>,
     *         choice: string
     *     }>
     * }
     */
    public function merge(string $base, string $ours, string $theirs): array
    {
        $baseLines = $this->splitLines($base);
        $ourLines = $this->splitLines($ours);
        $theirLines = $this->splitLines($theirs);

        // If base is empty, fall back to comparing ours and theirs directly
        if (empty($baseLines) && (empty($ourLines) || empty($theirLines))) {
            $effectiveLines = ! empty($ourLines) ? $ourLines : $theirLines;

            return [
                'has_conflicts' => false,
                'conflict_count' => 0,
                'clean_count' => 1,
                'merged_content' => implode("\n", $effectiveLines),
                'hunks' => [
                    [
                        'id' => 0,
                        'type' => 'clean',
                        'is_conflict' => false,
                        'base_lines' => $baseLines,
                        'our_lines' => $ourLines,
                        'their_lines' => $theirLines,
                        'resolved_lines' => $effectiveLines,
                        'choice' => 'clean',
                    ],
                ],
            ];
        }

        $hunks = $this->buildHunks($baseLines, $ourLines, $theirLines);

        $hasConflicts = false;
        $conflictCount = 0;
        $cleanCount = 0;
        $mergedLines = [];

        foreach ($hunks as $hunk) {
            if ($hunk['is_conflict']) {
                $hasConflicts = true;
                $conflictCount++;
                $mergedLines[] = '<<<<<<< CURRENT (Ours)';
                foreach ($hunk['our_lines'] as $l) {
                    $mergedLines[] = $l;
                }
                $mergedLines[] = '=======';
                foreach ($hunk['their_lines'] as $l) {
                    $mergedLines[] = $l;
                }
                $mergedLines[] = '>>>>>>> INCOMING (Theirs)';
            } else {
                $cleanCount++;
                foreach ($hunk['resolved_lines'] as $l) {
                    $mergedLines[] = $l;
                }
            }
        }

        return [
            'has_conflicts' => $hasConflicts,
            'conflict_count' => $conflictCount,
            'clean_count' => $cleanCount,
            'merged_content' => implode("\n", $mergedLines),
            'hunks' => $hunks,
        ];
    }

    /**
     * Reconstruct merged content from user-resolved hunks.
     *
     * @param  array<int, array{type: string, choice?: string, our_lines: array<int, string>, their_lines: array<int, string>, resolved_lines?: array<int, string>}>  $hunks
     */
    public function assemble(array $hunks): string
    {
        $lines = [];

        foreach ($hunks as $hunk) {
            $choice = $hunk['choice'] ?? 'ours';

            if (($hunk['type'] ?? '') === 'clean') {
                foreach ($hunk['resolved_lines'] ?? $hunk['our_lines'] as $line) {
                    $lines[] = $line;
                }
            } elseif ($choice === 'theirs') {
                foreach ($hunk['their_lines'] as $line) {
                    $lines[] = $line;
                }
            } elseif ($choice === 'both_ours_first') {
                foreach ($hunk['our_lines'] as $line) {
                    $lines[] = $line;
                }
                foreach ($hunk['their_lines'] as $line) {
                    $lines[] = $line;
                }
            } elseif ($choice === 'both_theirs_first') {
                foreach ($hunk['their_lines'] as $line) {
                    $lines[] = $line;
                }
                foreach ($hunk['our_lines'] as $line) {
                    $lines[] = $line;
                }
            } elseif (isset($hunk['resolved_lines'])) {
                foreach ($hunk['resolved_lines'] as $line) {
                    $lines[] = $line;
                }
            } else {
                foreach ($hunk['our_lines'] as $line) {
                    $lines[] = $line;
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Build 3-way hunks from base, ours, and theirs line lists.
     *
     * @param  array<int, string>  $base
     * @param  array<int, string>  $ours
     * @param  array<int, string>  $theirs
     * @return array<int, array{
     *     id: int,
     *     type: string,
     *     is_conflict: bool,
     *     base_lines: array<int, string>,
     *     our_lines: array<int, string>,
     *     their_lines: array<int, string>,
     *     resolved_lines: array<int, string>,
     *     choice: string
     * }>
     */
    protected function buildHunks(array $base, array $ours, array $theirs): array
    {
        $diffOur = $this->diff($base, $ours);
        $diffTheir = $this->diff($base, $theirs);

        $hunks = [];
        $hunkId = 0;

        $baseLen = count($base);
        $bIdx = 0;

        while ($bIdx < $baseLen) {
            $baseLine = $base[$bIdx];
            $hasOur = isset($diffOur[$bIdx]);
            $hasTheir = isset($diffTheir[$bIdx]);

            // Case 1: Both unchanged at this line
            if (! $hasOur && ! $hasTheir) {
                $cleanLines = [$baseLine];
                $bIdx++;
                while ($bIdx < $baseLen && ! isset($diffOur[$bIdx]) && ! isset($diffTheir[$bIdx])) {
                    $cleanLines[] = $base[$bIdx];
                    $bIdx++;
                }

                $hunks[] = [
                    'id' => $hunkId++,
                    'type' => 'clean',
                    'is_conflict' => false,
                    'base_lines' => $cleanLines,
                    'our_lines' => $cleanLines,
                    'their_lines' => $cleanLines,
                    'resolved_lines' => $cleanLines,
                    'choice' => 'clean',
                ];

                continue;
            }

            // Case 2: Only Ours modified this line
            if ($hasOur && ! $hasTheir) {
                $baseBlock = [];
                $ourBlock = [];

                while ($bIdx < $baseLen && isset($diffOur[$bIdx]) && ! isset($diffTheir[$bIdx])) {
                    $baseBlock[] = $base[$bIdx];
                    foreach ($diffOur[$bIdx]['lines'] as $l) {
                        $ourBlock[] = $l;
                    }
                    $bIdx++;
                }

                $hunks[] = [
                    'id' => $hunkId++,
                    'type' => 'ours',
                    'is_conflict' => false,
                    'base_lines' => $baseBlock,
                    'our_lines' => $ourBlock,
                    'their_lines' => $baseBlock,
                    'resolved_lines' => $ourBlock,
                    'choice' => 'ours',
                ];

                continue;
            }

            // Case 3: Only Theirs modified this line
            if (! $hasOur && $hasTheir) {
                $baseBlock = [];
                $theirBlock = [];

                while ($bIdx < $baseLen && ! isset($diffOur[$bIdx]) && isset($diffTheir[$bIdx])) {
                    $baseBlock[] = $base[$bIdx];
                    foreach ($diffTheir[$bIdx]['lines'] as $l) {
                        $theirBlock[] = $l;
                    }
                    $bIdx++;
                }

                $hunks[] = [
                    'id' => $hunkId++,
                    'type' => 'theirs',
                    'is_conflict' => false,
                    'base_lines' => $baseBlock,
                    'our_lines' => $baseBlock,
                    'their_lines' => $theirBlock,
                    'resolved_lines' => $theirBlock,
                    'choice' => 'theirs',
                ];

                continue;
            }

            // Case 4: Both modified at this line -> Potential conflict
            $baseBlock = [];
            $ourBlock = [];
            $theirBlock = [];

            while ($bIdx < $baseLen && (isset($diffOur[$bIdx]) || isset($diffTheir[$bIdx]))) {
                $baseBlock[] = $base[$bIdx];
                $curOur = $diffOur[$bIdx] ?? ['lines' => [$base[$bIdx]]];
                $curTheir = $diffTheir[$bIdx] ?? ['lines' => [$base[$bIdx]]];

                foreach ($curOur['lines'] as $l) {
                    $ourBlock[] = $l;
                }
                foreach ($curTheir['lines'] as $l) {
                    $theirBlock[] = $l;
                }

                $bIdx++;
            }

            if ($ourBlock === $theirBlock) {
                $hunks[] = [
                    'id' => $hunkId++,
                    'type' => 'clean',
                    'is_conflict' => false,
                    'base_lines' => $baseBlock,
                    'our_lines' => $ourBlock,
                    'their_lines' => $theirBlock,
                    'resolved_lines' => $ourBlock,
                    'choice' => 'clean',
                ];
            } else {
                $hunks[] = [
                    'id' => $hunkId++,
                    'type' => 'conflict',
                    'is_conflict' => true,
                    'base_lines' => $baseBlock,
                    'our_lines' => $ourBlock,
                    'their_lines' => $theirBlock,
                    'resolved_lines' => $ourBlock,
                    'choice' => 'ours',
                ];
            }
        }

        // Check trailing additions
        $trailingOur = $diffOur['trailing'] ?? [];
        $trailingTheir = $diffTheir['trailing'] ?? [];

        if (! empty($trailingOur) || ! empty($trailingTheir)) {
            if ($trailingOur === $trailingTheir) {
                $hunks[] = [
                    'id' => $hunkId++,
                    'type' => 'clean',
                    'is_conflict' => false,
                    'base_lines' => [],
                    'our_lines' => $trailingOur,
                    'their_lines' => $trailingTheir,
                    'resolved_lines' => $trailingOur,
                    'choice' => 'clean',
                ];
            } elseif (empty($trailingOur)) {
                $hunks[] = [
                    'id' => $hunkId++,
                    'type' => 'theirs',
                    'is_conflict' => false,
                    'base_lines' => [],
                    'our_lines' => [],
                    'their_lines' => $trailingTheir,
                    'resolved_lines' => $trailingTheir,
                    'choice' => 'theirs',
                ];
            } elseif (empty($trailingTheir)) {
                $hunks[] = [
                    'id' => $hunkId++,
                    'type' => 'ours',
                    'is_conflict' => false,
                    'base_lines' => [],
                    'our_lines' => $trailingOur,
                    'their_lines' => [],
                    'resolved_lines' => $trailingOur,
                    'choice' => 'ours',
                ];
            } else {
                $hunks[] = [
                    'id' => $hunkId++,
                    'type' => 'conflict',
                    'is_conflict' => true,
                    'base_lines' => [],
                    'our_lines' => $trailingOur,
                    'their_lines' => $trailingTheir,
                    'resolved_lines' => $trailingOur,
                    'choice' => 'ours',
                ];
            }
        }

        return $hunks;
    }

    /**
     * Compare a sequence of lines against base lines.
     *
     * @param  array<int, string>  $base
     * @param  array<int, string>  $modified
     * @return array<int|string, array{lines: array<int, string>}>
     */
    protected function diff(array $base, array $modified): array
    {
        $baseCount = count($base);
        $modCount = count($modified);

        if ($baseCount === 0) {
            return ['trailing' => $modified];
        }

        $lcs = $this->longestCommonSubsequence($base, $modified);

        $result = [];
        $b = 0;
        $m = 0;
        $lcsIdx = 0;
        $lcsCount = count($lcs);

        while ($lcsIdx < $lcsCount) {
            $common = $lcs[$lcsIdx];
            $targetB = $common['base'];
            $targetM = $common['mod'];

            // Any mod lines before the common anchor
            $inserted = [];
            while ($m < $targetM) {
                $inserted[] = $modified[$m];
                $m++;
            }

            // Any base lines replaced before the common anchor
            while ($b < $targetB) {
                $result[$b] = ['lines' => $inserted];
                $inserted = []; // consumed on the first replaced line
                $b++;
            }

            if (! empty($inserted)) {
                // If there were insertions before a common line without base replacements
                if (isset($result[$targetB])) {
                    $result[$targetB]['lines'] = array_merge($inserted, $result[$targetB]['lines']);
                } else {
                    $result[$targetB] = ['lines' => array_merge($inserted, [$base[$targetB]])];
                }
            }

            $b = $targetB + 1;
            $m = $targetM + 1;
            $lcsIdx++;
        }

        // Trailing lines after the last common anchor
        $trailingInserted = [];
        while ($m < $modCount) {
            $trailingInserted[] = $modified[$m];
            $m++;
        }

        while ($b < $baseCount) {
            $result[$b] = ['lines' => $trailingInserted];
            $trailingInserted = [];
            $b++;
        }

        if (! empty($trailingInserted)) {
            $result['trailing'] = $trailingInserted;
        }

        return $result;
    }

    /**
     * Compute Longest Common Subsequence indices between two line arrays.
     *
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     * @return array<int, array{base: int, mod: int}>
     */
    protected function longestCommonSubsequence(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);

        $matrix = [];
        for ($i = 0; $i <= $n; $i++) {
            $matrix[$i] = array_fill(0, $m + 1, 0);
        }

        for ($i = 1; $i <= $n; $i++) {
            for ($j = 1; $j <= $m; $j++) {
                if ($a[$i - 1] === $b[$j - 1]) {
                    $matrix[$i][$j] = $matrix[$i - 1][$j - 1] + 1;
                } else {
                    $matrix[$i][$j] = max($matrix[$i - 1][$j], $matrix[$i][$j - 1]);
                }
            }
        }

        // Backtrack
        $result = [];
        $i = $n;
        $j = $m;

        while ($i > 0 && $j > 0) {
            if ($a[$i - 1] === $b[$j - 1]) {
                $result[] = ['base' => $i - 1, 'mod' => $j - 1];
                $i--;
                $j--;
            } elseif ($matrix[$i - 1][$j] >= $matrix[$i][$j - 1]) {
                $i--;
            } else {
                $j--;
            }
        }

        return array_reverse($result);
    }

    /**
     * Split a string into an array of lines, normalizing line endings.
     *
     * @return array<int, string>
     */
    protected function splitLines(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $text);

        return explode("\n", $normalized);
    }
}
