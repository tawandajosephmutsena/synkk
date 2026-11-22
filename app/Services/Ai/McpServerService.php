<?php

namespace App\Services\Ai;

use App\Actions\Vaults\SyncUploadAction;
use App\Exceptions\AiException;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultChangeLog;
use App\Models\VaultFile;
use App\Services\KnowledgeGraphService;
use App\Services\PlanService;

class McpServerService
{
    public function __construct(
        protected PlanService $planService,
        protected HybridVaultSearchService $hybridSearch,
        protected KnowledgeGraphService $graphService,
        protected AiProposalService $proposalService,
        protected SyncUploadAction $syncUploadAction,
    ) {}

    /**
     * Dispatch one JSON-RPC 2.0 request for an authenticated device/MCP token.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null Null for JSON-RPC notifications without an id
     */
    public function handleJsonRpc(array $payload, DeviceToken $token): ?array
    {
        $hasId = array_key_exists('id', $payload);
        $id = $payload['id'] ?? null;
        $method = $payload['method'] ?? null;

        if (($payload['jsonrpc'] ?? null) !== '2.0' || ! is_string($method) || $method === '') {
            return $this->errorResponse($id, -32600, 'Invalid Request: expected JSON-RPC 2.0 object with method.');
        }

        $team = $token->team;
        $user = $token->user;

        if (! $team instanceof Team || ! $user instanceof User) {
            return $this->errorResponse($id, -32003, 'Token is not bound to an active user and workspace.');
        }

        if ($team->isSuspended()) {
            return $this->errorResponse($id, -32003, 'Workspace is currently suspended.');
        }

        if (! $this->planService->hasFeature($team, 'ai_mcp_server')) {
            return $this->errorResponse($id, -32003, 'MCP server access is not included on this workspace plan.');
        }

        if (str_starts_with($method, 'notifications/')) {
            return $hasId ? $this->resultResponse($id, (object) []) : null;
        }

        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];

        return match ($method) {
            'initialize' => $this->resultResponse($id, [
                'protocolVersion' => '2025-03-26',
                'capabilities' => [
                    'tools' => (object) [],
                    'resources' => (object) [],
                ],
                'serverInfo' => [
                    'name' => 'synkk-mcp',
                    'version' => '1.0.0',
                ],
            ]),
            'ping' => $this->resultResponse($id, (object) []),
            'resources/list' => $this->handleResourcesList($id, $token, $team, $user),
            'resources/read' => $this->handleResourcesRead($id, $params, $token, $team, $user),
            'tools/list' => $this->resultResponse($id, ['tools' => $this->toolDefinitions()]),
            'tools/call' => $this->handleToolCall($id, $params, $token, $team, $user),
            default => $this->errorResponse($id, -32601, "Method '{$method}' not found."),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function handleResourcesList(mixed $id, DeviceToken $token, Team $team, User $user): array
    {
        $vaults = $this->accessibleAiVaults($token, $team);
        $resources = [];

        foreach ($vaults as $vault) {
            $files = $vault->files()
                ->where('is_deleted', false)
                ->where('path', 'like', '%.md')
                ->orderBy('path')
                ->limit(100)
                ->get();

            foreach ($files as $file) {
                if ($vault->permissionForPath($user, $file->path) === 'hidden') {
                    continue;
                }
                if ($vault->aiSetting?->isPathExcluded($file->path)) {
                    continue;
                }

                $resources[] = [
                    'uri' => "synkk://vault/{$vault->slug}/note/{$file->path}",
                    'name' => "{$vault->name}: {$file->path}",
                    'mimeType' => 'text/markdown',
                ];

                if (count($resources) >= 100) {
                    break 2;
                }
            }
        }

        return $this->resultResponse($id, ['resources' => $resources]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function handleResourcesRead(mixed $id, array $params, DeviceToken $token, Team $team, User $user): array
    {
        $uri = (string) ($params['uri'] ?? '');
        if (! preg_match('/^synkk:\/\/vault\/([^\/]+)\/note\/(.+)$/', $uri, $matches)) {
            return $this->errorResponse($id, -32602, 'Invalid resource URI. Expected synkk://vault/{slug}/note/{path}.');
        }

        $vaultSlug = $matches[1];
        $path = trim(str_replace('\\', '/', rawurldecode($matches[2])), '/');

        if ($path === '' || str_contains($path, '..')) {
            return $this->errorResponse($id, -32602, 'Invalid note path.');
        }

        $vaultOrError = $this->resolveAuthorizedVault($id, $vaultSlug, $token, $team);
        if (! $vaultOrError instanceof Vault) {
            return $vaultOrError;
        }
        $vault = $vaultOrError;

        if ($vault->permissionForPath($user, $path) === 'hidden' || ($vault->aiSetting?->isPathExcluded($path) ?? false)) {
            return $this->errorResponse($id, -32003, "Access denied or path excluded for '{$path}'.");
        }

        $file = $vault->files()->where('path', $path)->where('is_deleted', false)->first();
        if (! $file) {
            return $this->errorResponse($id, -32602, "Note '{$path}' not found.");
        }

        return $this->resultResponse($id, [
            'contents' => [[
                'uri' => $uri,
                'mimeType' => 'text/markdown',
                'text' => (string) ($file->getContents() ?? ''),
            ]],
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function handleToolCall(mixed $id, array $params, DeviceToken $token, Team $team, User $user): array
    {
        $toolName = (string) ($params['name'] ?? '');
        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        if ($toolName === '') {
            return $this->errorResponse($id, -32602, 'Tool name is required.');
        }

        if ($toolName === 'synkk_list_vaults') {
            $vaults = [];
            foreach ($this->accessibleAiVaults($token, $team) as $vault) {
                $vaults[] = [
                    'slug' => $vault->slug,
                    'name' => $vault->name,
                    'allow_direct_mcp_writes' => $vault->aiSetting !== null ? $vault->aiSetting->allow_direct_mcp_writes : false,
                    'latest_version' => $vault->latestVersion(),
                ];
            }

            return $this->toolSuccess($id, ['vaults' => $vaults]);
        }

        $vaultSlug = trim((string) ($args['vault'] ?? ''));
        if ($vaultSlug === '') {
            return $this->errorResponse($id, -32602, "Parameter 'vault' (vault slug) is required.");
        }

        $vaultOrError = $this->resolveAuthorizedVault($id, $vaultSlug, $token, $team);
        if (! $vaultOrError instanceof Vault) {
            return $vaultOrError;
        }
        $vault = $vaultOrError;

        return match ($toolName) {
            'synkk_search_notes' => $this->toolSearchNotes($id, $vault, $user, $args),
            'synkk_read_note' => $this->toolReadNote($id, $vault, $user, $args),
            'synkk_traverse_graph' => $this->toolTraverseGraph($id, $vault, $user, $args),
            'synkk_recent_activity' => $this->toolRecentActivity($id, $vault, $user, $args),
            'synkk_propose_note' => $this->toolProposeNote($id, $vault, $token, $user, $args),
            'synkk_write_note' => $this->toolWriteNote($id, $vault, $token, $user, $args),
            default => $this->errorResponse($id, -32601, "Unknown MCP tool '{$toolName}'."),
        };
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolSearchNotes(mixed $id, Vault $vault, User $user, array $args): array
    {
        $query = trim((string) ($args['query'] ?? ''));
        if ($query === '') {
            return $this->errorResponse($id, -32602, "Parameter 'query' is required.");
        }

        $limit = max(1, min(15, (int) ($args['limit'] ?? 5)));
        $results = $this->hybridSearch->search($vault, $query, $limit, $user, [
            'folder' => isset($args['folder']) ? (string) $args['folder'] : null,
            'tag' => isset($args['tag']) ? (string) $args['tag'] : null,
        ]);

        return $this->toolSuccess($id, ['results' => $results]);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolReadNote(mixed $id, Vault $vault, User $user, array $args): array
    {
        $rawPath = trim(str_replace('\\', '/', (string) ($args['path'] ?? '')), '/');
        if ($rawPath === '' || str_contains($rawPath, '..')) {
            return $this->errorResponse($id, -32602, 'Invalid note path.');
        }

        $file = $vault->files()
            ->where('is_deleted', false)
            ->where(function ($q) use ($rawPath): void {
                $q->where('path', $rawPath)
                    ->orWhere('path', $rawPath.'.md');
            })
            ->first();

        if (! $file) {
            return $this->errorResponse($id, -32602, "Note '{$rawPath}' not found.");
        }

        if ($vault->permissionForPath($user, $file->path) === 'hidden'
            || ($vault->aiSetting?->isPathExcluded($file->path) ?? false)) {
            return $this->errorResponse($id, -32003, "Access denied or path excluded for '{$file->path}'.");
        }

        return $this->toolSuccess($id, [
            'path' => $file->path,
            'version' => $file->version,
            'content' => (string) ($file->getContents() ?? ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolTraverseGraph(mixed $id, Vault $vault, User $user, array $args): array
    {
        $rawPath = trim(str_replace('\\', '/', (string) ($args['path'] ?? '')), '/');
        if ($rawPath === '' || str_contains($rawPath, '..')) {
            return $this->errorResponse($id, -32602, 'Invalid note path.');
        }

        if ($vault->permissionForPath($user, $rawPath) === 'hidden'
            || ($vault->aiSetting?->isPathExcluded($rawPath) ?? false)) {
            return $this->errorResponse($id, -32003, "Access denied or path excluded for '{$rawPath}'.");
        }

        $depth = max(1, min(2, (int) ($args['depth'] ?? 1)));
        $nodes = $this->graphService->expandContext($vault, [$rawPath], $depth, $user);

        $filtered = array_values(array_filter(
            $nodes,
            fn (array $node): bool => ! ($vault->aiSetting?->isPathExcluded($node['path']) ?? false),
        ));

        return $this->toolSuccess($id, ['nodes' => $filtered]);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolRecentActivity(mixed $id, Vault $vault, User $user, array $args): array
    {
        $days = max(1, min(30, (int) ($args['days'] ?? 7)));
        $limit = max(1, min(25, (int) ($args['limit'] ?? 15)));

        $logs = VaultChangeLog::query()
            ->with('user')
            ->where('vault_id', $vault->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->latest('id')
            ->limit($limit * 3)
            ->get();

        $events = [];
        foreach ($logs as $log) {
            if ($vault->permissionForPath($user, $log->path) === 'hidden'
                || ($vault->aiSetting?->isPathExcluded($log->path) ?? false)) {
                continue;
            }

            $events[] = [
                'path' => $log->path,
                'action' => $log->action,
                'version' => $log->version,
                'author' => $log->user !== null ? $log->user->name : 'System',
                'device' => $log->device_name,
                'at' => $log->created_at?->toIso8601String(),
            ];

            if (count($events) >= $limit) {
                break;
            }
        }

        return $this->toolSuccess($id, ['events' => $events]);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolProposeNote(mixed $id, Vault $vault, DeviceToken $token, User $user, array $args): array
    {
        if (! in_array($token->access_scope, ['mcp_propose', 'mcp_write', 'full_access', 'read_write'], true)) {
            return $this->errorResponse($id, -32003, 'Token scope does not permit proposing notes. Use an mcp_propose or mcp_write token.');
        }

        try {
            $proposal = $this->proposalService->stageProposal($vault, $user, [
                'target_path' => (string) ($args['path'] ?? ''),
                'proposed_content' => (string) ($args['content'] ?? ''),
                'rationale' => (string) ($args['rationale'] ?? "Proposed via MCP token '{$token->name}'"),
                'action' => (string) ($args['action'] ?? 'update'),
                'source' => 'mcp',
                'metadata' => ['mcp_token_name' => $token->name, 'mcp_token_id' => $token->id],
            ]);

            return $this->toolSuccess($id, [
                'status' => 'staged',
                'proposal_id' => $proposal->id,
                'target_path' => $proposal->target_path,
                'action' => $proposal->action,
            ]);
        } catch (AiException $e) {
            $code = $e->errorCode === 'INVALID_PATH' ? -32602 : -32003;

            return $this->errorResponse($id, $code, $e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolWriteNote(mixed $id, Vault $vault, DeviceToken $token, User $user, array $args): array
    {
        if (! in_array($token->access_scope, ['mcp_write', 'full_access'], true)) {
            return $this->errorResponse($id, -32003, 'Token scope does not allow direct writes. Use synkk_propose_note instead.');
        }

        if ($vault->aiSetting === null || ! $vault->aiSetting->allow_direct_mcp_writes) {
            return $this->errorResponse($id, -32003, 'Direct MCP writes are disabled on this vault. Use synkk_propose_note to stage a diff proposal.');
        }

        try {
            $targetPath = $this->proposalService->normalizePath((string) ($args['path'] ?? ''));
        } catch (AiException $e) {
            return $this->errorResponse($id, -32602, $e->getMessage());
        }

        if ($vault->aiSetting->isPathExcluded($targetPath)) {
            return $this->errorResponse($id, -32003, "Path '{$targetPath}' is excluded from AI access.");
        }

        if ($vault->permissionForPath($user, $targetPath) !== 'read_write') {
            return $this->errorResponse($id, -32003, "Write permission denied for '{$targetPath}'.");
        }

        /** @var VaultFile|null $existing */
        $existing = $vault->files()->where('path', $targetPath)->where('is_deleted', false)->first();

        $result = $this->syncUploadAction->execute(
            vault: $vault,
            user: $user,
            deviceName: "MCP ({$token->name})",
            path: $targetPath,
            contents: (string) ($args['content'] ?? ''),
            baseVersion: $existing !== null ? $existing->version : 0,
        );

        return $this->toolSuccess($id, $result);
    }

    /**
     * Resolve and authorize a vault for MCP access.
     *
     * @return Vault|array<string, mixed>
     */
    protected function resolveAuthorizedVault(mixed $id, string $slug, DeviceToken $token, Team $team): Vault|array
    {
        $vault = Vault::query()
            ->with('aiSetting')
            ->where('team_id', $team->id)
            ->where('slug', $slug)
            ->first();

        if (! $vault || ! $token->canAccessVault($vault->id)) {
            return $this->errorResponse($id, -32003, "Vault '{$slug}' not found or not authorized for this MCP token.");
        }

        if ($vault->is_e2ee) {
            return $this->errorResponse($id, -32003, "Vault '{$slug}' has Zero-Knowledge E2EE enabled. Server MCP access is blocked.");
        }

        if ($vault->aiSetting === null || $vault->aiSetting->ai_mode !== 'server') {
            return $this->errorResponse($id, -32003, "Server AI / MCP access is not enabled for vault '{$slug}'.");
        }

        return $vault;
    }

    /**
     * @return array<int, Vault>
     */
    protected function accessibleAiVaults(DeviceToken $token, Team $team): array
    {
        return $team->vaults()
            ->with('aiSetting')
            ->where('is_e2ee', false)
            ->orderBy('name')
            ->get()
            ->filter(fn (Vault $v): bool => $token->canAccessVault($v->id) && $v->aiSetting !== null && $v->aiSetting->ai_mode === 'server')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function toolDefinitions(): array
    {
        return [
            [
                'name' => 'synkk_list_vaults',
                'description' => 'List Synkk vaults accessible to this MCP token with server AI enabled.',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'synkk_search_notes',
                'description' => 'Hybrid FTS5 + semantic vector search across a vault.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'vault' => ['type' => 'string', 'description' => 'Vault slug'],
                        'query' => ['type' => 'string', 'description' => 'Search query'],
                        'folder' => ['type' => 'string', 'description' => 'Optional folder prefix'],
                        'tag' => ['type' => 'string', 'description' => 'Optional tag without #'],
                        'limit' => ['type' => 'integer', 'description' => 'Max results (1-15)'],
                    ],
                    'required' => ['vault', 'query'],
                ],
            ],
            [
                'name' => 'synkk_read_note',
                'description' => 'Read the Markdown content of a specific vault note.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'vault' => ['type' => 'string', 'description' => 'Vault slug'],
                        'path' => ['type' => 'string', 'description' => 'Vault-relative note path'],
                    ],
                    'required' => ['vault', 'path'],
                ],
            ],
            [
                'name' => 'synkk_traverse_graph',
                'description' => 'Traverse [[wikilink]] backlinks and outlinks starting from a note.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'vault' => ['type' => 'string', 'description' => 'Vault slug'],
                        'path' => ['type' => 'string', 'description' => 'Seed note path'],
                        'depth' => ['type' => 'integer', 'description' => 'Depth 1 or 2'],
                    ],
                    'required' => ['vault', 'path'],
                ],
            ],
            [
                'name' => 'synkk_recent_activity',
                'description' => 'Fetch recent synced file changes in a vault.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'vault' => ['type' => 'string', 'description' => 'Vault slug'],
                        'days' => ['type' => 'integer', 'description' => 'Lookback days (1-30)'],
                        'limit' => ['type' => 'integer', 'description' => 'Max events (1-25)'],
                    ],
                    'required' => ['vault'],
                ],
            ],
            [
                'name' => 'synkk_propose_note',
                'description' => 'Stage a new or updated Markdown note in the Vault Diff Approval Inbox.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'vault' => ['type' => 'string', 'description' => 'Vault slug'],
                        'path' => ['type' => 'string', 'description' => 'Target Markdown path'],
                        'content' => ['type' => 'string', 'description' => 'Full proposed Markdown content (or section when action=append)'],
                        'rationale' => ['type' => 'string', 'description' => 'Reason for proposed edit'],
                        'action' => ['type' => 'string', 'enum' => ['create', 'update', 'append']],
                    ],
                    'required' => ['vault', 'path', 'content'],
                ],
            ],
            [
                'name' => 'synkk_write_note',
                'description' => 'Directly write a versioned note (requires mcp_write scope AND vault allow_direct_mcp_writes=true).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'vault' => ['type' => 'string', 'description' => 'Vault slug'],
                        'path' => ['type' => 'string', 'description' => 'Target Markdown path'],
                        'content' => ['type' => 'string', 'description' => 'Markdown content to write'],
                    ],
                    'required' => ['vault', 'path', 'content'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function toolSuccess(mixed $id, array $data): array
    {
        return $this->resultResponse($id, [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
            ]],
            'isError' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resultResponse(mixed $id, mixed $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function errorResponse(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
