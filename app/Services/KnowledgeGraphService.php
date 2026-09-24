<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vault;
use App\Models\VaultFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class KnowledgeGraphService
{
    /**
     * Build the interactive graph topology for canvas/SVG visualization in the UI.
     *
     * @param  iterable<VaultFile>|null  $markdownFiles
     * @return array{
     *     nodes: array<int, array{id: int, name: string, path: string, size: int, version: int, updated_at: string, linksCount: int}>,
     *     edges: array<int, array{source: int, target: int}>
     * }
     */
    public function getInteractiveGraph(Vault $vault, ?iterable $markdownFiles = null): array
    {
        $version = $vault->latestVersion();

        if ($markdownFiles === null) {
            $cacheKey = "vault_graph_{$vault->id}_v{$version}";
        } else {
            $identifiers = [];
            foreach ($markdownFiles as $file) {
                $identifiers[] = $file->id.':'.$file->version;
            }
            $cacheKey = "vault_graph_{$vault->id}_v{$version}_".md5(implode(',', $identifiers));
        }

        /** @var array{
         *     nodes: array<int, array{id: int, name: string, path: string, size: int, version: int, updated_at: string, linksCount: int}>,
         *     edges: array<int, array{source: int, target: int}>
         * } */
        return Cache::remember($cacheKey, now()->addHours(6), function () use ($vault, $markdownFiles): array {
            return $this->buildInteractiveGraph($vault, $markdownFiles);
        });
    }

    /**
     * @param  iterable<VaultFile>|null  $markdownFiles
     * @return array{
     *     nodes: array<int, array{id: int, name: string, path: string, size: int, version: int, updated_at: string, linksCount: int}>,
     *     edges: array<int, array{source: int, target: int}>
     * }
     */
    public function buildInteractiveGraph(Vault $vault, ?iterable $markdownFiles = null): array
    {
        $files = $markdownFiles ?? VaultFile::where('vault_id', $vault->id)
            ->where('is_deleted', false)
            ->where('path', 'like', '%.md')
            ->get();

        $nodes = [];
        $edges = [];
        $exactPathMap = [];
        $basenameMap = [];

        foreach ($files as $file) {
            $basename = pathinfo($file->path, PATHINFO_FILENAME);
            $nodes[] = [
                'id' => $file->id,
                'name' => $basename,
                'path' => $file->path,
                'size' => $file->size,
                'version' => $file->version,
                'updated_at' => $file->updated_at?->diffForHumans() ?? '',
                'linksCount' => 0,
            ];
            $nodeIndex = count($nodes) - 1;
            $normalizedPath = $this->normalizeGraphPath($file->path);
            $extensionlessPath = preg_replace('/\.md$/i', '', $normalizedPath) ?? $normalizedPath;
            $normalizedBasename = Str::lower($basename);

            $exactPathMap[$normalizedPath] = $nodeIndex;
            $exactPathMap[$extensionlessPath] = $nodeIndex;
            $basenameMap[$normalizedBasename][] = $nodeIndex;
        }

        $createdEdges = [];
        /** @var list<VaultFile> $fileList */
        $fileList = is_array($files) ? array_values($files) : array_values(iterator_to_array($files));
        $linkCounts = array_fill(0, count($nodes), 0);

        foreach ($fileList as $sourceIndex => $file) {
            $content = $file->getContents() ?? '';
            if (empty($content)) {
                continue;
            }

            preg_match_all('/\[\[(.*?)\]\]/', $content, $wikiMatches);
            $targets = [];
            if (! empty($wikiMatches[1])) {
                foreach ($wikiMatches[1] as $rawTarget) {
                    $targets[] = $rawTarget;
                }
            }

            preg_match_all('/\[[^\]]*\]\(([^)]+\.md(?:#[^)]*)?)\)/i', $content, $mdMatches);
            if (! empty($mdMatches[1])) {
                foreach ($mdMatches[1] as $rawMdTarget) {
                    $targets[] = $rawMdTarget;
                }
            }

            foreach ($targets as $rawTarget) {
                $targetIndex = $this->resolveGraphTarget($rawTarget, $file->path, $exactPathMap, $basenameMap);

                if ($targetIndex === null || $targetIndex === $sourceIndex) {
                    continue;
                }

                $edgeKey = $sourceIndex.'-'.$targetIndex;
                if (! isset($createdEdges[$edgeKey])) {
                    $createdEdges[$edgeKey] = true;
                    $edges[] = [
                        'source' => $sourceIndex,
                        'target' => $targetIndex,
                    ];
                    $linkCounts[$sourceIndex] = ($linkCounts[$sourceIndex] ?? 0) + 1;
                    $linkCounts[$targetIndex] = ($linkCounts[$targetIndex] ?? 0) + 1;
                }
            }
        }

        foreach ($nodes as $index => &$node) {
            $node['linksCount'] = $linkCounts[$index] ?? 0;
        }
        unset($node);

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    public function normalizeGraphPath(string $path): string
    {
        $segments = [];

        foreach (explode('/', str_replace('\\', '/', urldecode(trim($path)))) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return Str::lower(implode('/', $segments));
    }

    /**
     * @param  array<string, int>  $exactPathMap
     * @param  array<string, list<int>>  $basenameMap
     */
    public function resolveGraphTarget(string $rawTarget, string $sourcePath, array $exactPathMap, array $basenameMap): ?int
    {
        $target = trim(explode('|', $rawTarget, 2)[0]);
        $target = trim(explode('#', $target, 2)[0]);
        $target = trim(explode('?', $target, 2)[0]);

        if ($target === '') {
            return null;
        }

        $sourceDirectory = pathinfo(str_replace('\\', '/', $sourcePath), PATHINFO_DIRNAME);
        $targetIsRelative = str_starts_with($target, './') || str_starts_with($target, '../');
        $candidates = [];

        if ($sourceDirectory !== '.' && ($targetIsRelative || ! str_contains($target, '/'))) {
            $candidates[] = $this->normalizeGraphPath($sourceDirectory.'/'.$target);
        }

        $candidates[] = $this->normalizeGraphPath($target);

        foreach (array_unique($candidates) as $candidate) {
            $extensionlessCandidate = preg_replace('/\.md$/i', '', $candidate) ?? $candidate;

            if (isset($exactPathMap[$candidate])) {
                return $exactPathMap[$candidate];
            }

            if (isset($exactPathMap[$extensionlessCandidate])) {
                return $exactPathMap[$extensionlessCandidate];
            }
        }

        if (! str_contains($target, '/')) {
            $basename = Str::lower(pathinfo($target, PATHINFO_FILENAME));
            $matches = $basenameMap[$basename] ?? [];

            if (count($matches) === 1) {
                return $matches[0];
            }
        }

        return null;
    }

    /**
     * Build the complete graph topology for a vault.
     *
     * @return array{
     *     nodes: array<int, array{path: string, title: string, inbound: int, outbound: int, is_orphan: bool}>,
     *     edges: array<int, array{source: string, target: string}>,
     *     adjacency: array<string, array<string, array<int, string>>>
     * }
     */
    public function getGraphTopology(Vault $vault, ?User $user = null): array
    {
        if ($user) {
            return $this->buildGraphTopology($vault, $user);
        }

        $version = $vault->latestVersion();
        $cacheKey = "vault_graph_topology_{$vault->id}_v{$version}";

        /** @var array{
         *     nodes: array<int, array{path: string, title: string, inbound: int, outbound: int, is_orphan: bool}>,
         *     edges: array<int, array{source: string, target: string}>,
         *     adjacency: array<string, array<string, array<int, string>>>
         * } */
        return Cache::remember($cacheKey, now()->addHours(6), function () use ($vault): array {
            return $this->buildGraphTopology($vault);
        });
    }

    /**
     * @return array{
     *     nodes: array<int, array{path: string, title: string, inbound: int, outbound: int, is_orphan: bool}>,
     *     edges: array<int, array{source: string, target: string}>,
     *     adjacency: array<string, array<string, array<int, string>>>
     * }
     */
    public function buildGraphTopology(Vault $vault, ?User $user = null): array
    {
        $markdownFiles = VaultFile::where('vault_id', $vault->id)
            ->where('is_deleted', false)
            ->where('path', 'like', '%.md')
            ->get();

        if ($user) {
            $markdownFiles = $markdownFiles
                ->filter(fn (VaultFile $file): bool => $vault->permissionForPath($user, $file->path) !== 'hidden')
                ->values();
        }

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
    public function expandContext(Vault $vault, array $seedPaths, int $depth = 1, ?User $user = null): array
    {
        if (empty($seedPaths)) {
            return [];
        }

        $topology = $this->getGraphTopology($vault, $user);
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
