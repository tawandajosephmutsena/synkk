<?php

namespace App\Services;

use App\Models\Vault;
use App\Models\VaultFile;

class KnowledgeGraphService
{
    /**
     * Build the complete graph topology for a vault.
     *
     * @return array{
     *     nodes: array<int, array{path: string, title: string, inbound: int, outbound: int, is_orphan: bool}>,
     *     edges: array<int, array{source: string, target: string}>,
     *     adjacency: array<string, array<string, array<int, string>>>
     * }
     */
    public function getGraphTopology(Vault $vault): array
    {
        $markdownFiles = VaultFile::where('vault_id', $vault->id)
            ->where('is_deleted', false)
            ->where('path', 'like', '%.md')
            ->get();

        $pathMap = [];
        $basenameMap = [];

        foreach ($markdownFiles as $file) {
            $normalized = $this->normalizePath($file->path);
            $pathMap[$normalized] = $file->path;
            $basename = strtolower(pathinfo($file->path, PATHINFO_FILENAME));
            $basenameMap[$basename][] = $file->path;
        }

        $outbound = [];
        $inbound = [];
        $edges = [];

        foreach ($markdownFiles as $file) {
            $sourcePath = $file->path;
            $outbound[$sourcePath] = $outbound[$sourcePath] ?? [];
            $inbound[$sourcePath] = $inbound[$sourcePath] ?? [];

            $content = $file->getContents();
            if (empty($content)) {
                continue;
            }

            preg_match_all('/\[\[([^\]\|#]+)(?:#[^\]\|]+)?(?:\|[^\]]+)?\]\]/', $content, $matches);
            if (! empty($matches[1])) {
                $rawTargets = array_unique(array_map('trim', $matches[1]));

                foreach ($rawTargets as $rawTarget) {
                    $resolvedTarget = $this->resolveTarget($rawTarget, $sourcePath, $pathMap, $basenameMap);
                    if ($resolvedTarget !== null && $resolvedTarget !== $sourcePath) {
                        $outbound[$sourcePath][] = $resolvedTarget;
                        $inbound[$resolvedTarget] = $inbound[$resolvedTarget] ?? [];
                        $inbound[$resolvedTarget][] = $sourcePath;

                        $edges[] = [
                            'source' => $sourcePath,
                            'target' => $resolvedTarget,
                        ];
                    }
                }
            }
        }

        $nodes = [];
        foreach ($markdownFiles as $file) {
            $p = $file->path;
            $inCount = count($inbound[$p] ?? []);
            $outCount = count(array_unique($outbound[$p] ?? []));

            $nodes[] = [
                'path' => $p,
                'title' => pathinfo($p, PATHINFO_FILENAME),
                'inbound' => $inCount,
                'outbound' => $outCount,
                'is_orphan' => ($inCount === 0 && $outCount === 0),
            ];
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'adjacency' => [
                'outbound' => $outbound,
                'inbound' => $inbound,
            ],
        ];
    }

    /**
     * Expand contextual retrieval by traversing 1-hop and 2-hop connected wikilinks.
     *
     * @param  array<int, string>  $seedPaths
     * @return array<int, array{
     *     path: string,
     *     title: string,
     *     relationship: string,
     *     via: string,
     *     excerpt: string|null
     * }>
     */
    public function expandContext(Vault $vault, array $seedPaths, int $depth = 1): array
    {
        if (empty($seedPaths)) {
            return [];
        }

        $topology = $this->getGraphTopology($vault);
        $outbound = $topology['adjacency']['outbound'];
        $inbound = $topology['adjacency']['inbound'];

        $visited = array_flip($seedPaths);
        $expanded = [];

        foreach ($seedPaths as $seed) {
            // 1. Inbound links (notes that cite the seed note)
            $citingNotes = $inbound[$seed] ?? [];
            foreach ($citingNotes as $neighbor) {
                if (! isset($visited[$neighbor])) {
                    $visited[$neighbor] = true;
                    $expanded[] = $this->buildNeighborContext($vault, $neighbor, 'backlink', $seed);
                }
            }

            // 2. Outbound links (notes cited by the seed note)
            $citedNotes = $outbound[$seed] ?? [];
            foreach ($citedNotes as $neighbor) {
                if (! isset($visited[$neighbor])) {
                    $visited[$neighbor] = true;
                    $expanded[] = $this->buildNeighborContext($vault, $neighbor, 'outbound', $seed);
                }
            }
        }

        // If depth > 1, expand one more hop for top 3 neighbors
        if ($depth > 1) {
            $secondHopCandidates = array_slice($expanded, 0, 3);
            foreach ($secondHopCandidates as $cand) {
                $candPath = $cand['path'];
                $hops = array_merge($inbound[$candPath] ?? [], $outbound[$candPath] ?? []);
                foreach ($hops as $h) {
                    if (! isset($visited[$h])) {
                        $visited[$h] = true;
                        $expanded[] = $this->buildNeighborContext($vault, $h, '2-hop', $candPath);
                    }
                }
            }
        }

        return $expanded;
    }

    /**
     * Build neighbor context object with excerpt.
     *
     * @return array{
     *     path: string,
     *     title: string,
     *     relationship: string,
     *     via: string,
     *     excerpt: string|null
     * }
     */
    protected function buildNeighborContext(Vault $vault, string $path, string $relationship, string $via): array
    {
        $file = VaultFile::where('vault_id', $vault->id)
            ->where('path', $path)
            ->where('is_deleted', false)
            ->first();

        $excerpt = null;
        if ($file) {
            $content = $file->getContents();
            if (! empty($content)) {
                $clean = trim(preg_replace('/[#\*\`\[\]\(\)]/', '', $content));
                $excerpt = mb_substr($clean, 0, 160).'...';
            }
        }

        return [
            'path' => $path,
            'title' => pathinfo($path, PATHINFO_FILENAME),
            'relationship' => $relationship,
            'via' => $via,
            'excerpt' => $excerpt,
        ];
    }

    /**
     * Resolve target wikilink to an actual vault file path.
     *
     * @param  array<string, string>  $pathMap
     * @param  array<string, array<int, string>>  $basenameMap
     */
    protected function resolveTarget(string $rawTarget, string $sourcePath, array $pathMap, array $basenameMap): ?string
    {
        $target = trim($rawTarget);
        if ($target === '') {
            return null;
        }

        $normalized = $this->normalizePath($target);

        // 1. Direct path match
        if (isset($pathMap[$normalized])) {
            return $pathMap[$normalized];
        }

        // 2. Relative from current directory
        $sourceDir = dirname($sourcePath);
        if ($sourceDir !== '.' && $sourceDir !== '') {
            $candidate = $this->normalizePath($sourceDir.'/'.$target);
            if (isset($pathMap[$candidate])) {
                return $pathMap[$candidate];
            }
        }

        // 3. Basename match
        $targetBasename = strtolower(pathinfo($target, PATHINFO_FILENAME));
        if (isset($basenameMap[$targetBasename])) {
            return $basenameMap[$targetBasename][0];
        }

        return null;
    }

    protected function normalizePath(string $path): string
    {
        $clean = str_replace('\\', '/', $path);
        if (! str_ends_with(strtolower($clean), '.md')) {
            $clean .= '.md';
        }

        return strtolower(ltrim($clean, '/'));
    }
}
