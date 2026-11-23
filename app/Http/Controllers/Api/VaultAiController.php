<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AiException;
use App\Http\Controllers\Controller;
use App\Jobs\RunVaultAgentTurnJob;
use App\Models\AiProposal;
use App\Models\AiRun;
use App\Models\AiThread;
use App\Models\DeviceToken;
use App\Models\Vault;
use App\Services\Ai\AiProposalService;
use App\Services\Ai\VaultAgentService;
use App\Services\Ai\VaultDigestService;
use App\Services\Ai\VaultGardenerService;
use App\Services\DeviceVaultAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VaultAiController extends Controller
{
    public function __construct(
        protected DeviceVaultAccess $vaultAccess,
        protected VaultAgentService $agentService,
        protected AiProposalService $proposalService,
        protected VaultDigestService $digestService,
        protected VaultGardenerService $gardenerService,
    ) {}

    public function threads(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($deviceToken->isMcpToken()) {
            return response()->json(['error' => 'MCP tokens must use /api/v1/mcp.'], 403);
        }

        $this->vaultAccess->authorizeVaultAccess($deviceToken, $vault);
        $user = $deviceToken->user;

        $threads = AiThread::query()
            ->where('vault_id', $vault->id)
            ->where(function ($q) use ($user): void {
                $q->where('user_id', $user->id)
                    ->orWhere('is_shared_with_team', true);
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'status' => 'ok',
            'threads' => $threads,
        ]);
    }

    public function createThread(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($deviceToken->isMcpToken()) {
            return response()->json(['error' => 'MCP tokens must use /api/v1/mcp.'], 403);
        }

        $this->vaultAccess->authorizeVaultAccess($deviceToken, $vault);
        $user = $deviceToken->user;

        try {
            $this->agentService->assertVaultServerAiReady($vault, $user);
        } catch (AiException $e) {
            return response()->json(['error' => $e->errorCode, 'message' => $e->getMessage()], $e->statusCode);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'is_shared_with_team' => ['nullable', 'boolean'],
        ]);

        $thread = AiThread::query()->create([
            'team_id' => $vault->team_id,
            'vault_id' => $vault->id,
            'user_id' => $user->id,
            'title' => trim((string) ($validated['title'] ?? '')) ?: 'New Conversation',
            'is_shared_with_team' => (bool) ($validated['is_shared_with_team'] ?? false),
            'last_message_at' => now(),
        ]);

        return response()->json([
            'status' => 'created',
            'thread' => $thread,
        ], 201);
    }

    public function messages(Request $request, Vault $vault, AiThread $thread): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($deviceToken->isMcpToken()) {
            return response()->json(['error' => 'MCP tokens must use /api/v1/mcp.'], 403);
        }

        $this->vaultAccess->authorizeVaultAccess($deviceToken, $vault);
        $user = $deviceToken->user;

        if ($thread->vault_id !== $vault->id || ($thread->user_id !== $user->id && ! $thread->is_shared_with_team)) {
            return response()->json(['error' => 'Thread not found.'], 404);
        }

        $messages = $thread->messages()->orderBy('id')->get();

        return response()->json([
            'status' => 'ok',
            'thread' => $thread,
            'messages' => $messages,
        ]);
    }

    public function sendMessage(Request $request, Vault $vault, AiThread $thread): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($deviceToken->isMcpToken()) {
            return response()->json(['error' => 'MCP tokens must use /api/v1/mcp.'], 403);
        }

        $this->vaultAccess->authorizeVaultAccess($deviceToken, $vault);
        $user = $deviceToken->user;

        if ($thread->vault_id !== $vault->id || ($thread->user_id !== $user->id && ! $thread->is_shared_with_team)) {
            return response()->json(['error' => 'Thread not found.'], 404);
        }

        try {
            $this->agentService->assertVaultServerAiReady($vault, $user);
        } catch (AiException $e) {
            return response()->json(['error' => $e->errorCode, 'message' => $e->getMessage()], $e->statusCode);
        }

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:8000'],
            'context_paths' => ['nullable', 'array', 'max:5'],
            'context_paths.*' => ['string', 'max:500', 'not_regex:/(^|\/)\.\.($|\/)/'],
        ]);

        $run = AiRun::query()->create([
            'thread_id' => $thread->id,
            'vault_id' => $vault->id,
            'user_id' => $user->id,
            'status' => 'queued',
            'partial_content' => '',
        ]);

        /** @var array<int, string> $contextPaths */
        $contextPaths = $validated['context_paths'] ?? [];

        RunVaultAgentTurnJob::dispatch($run->id, $thread->id, $user->id, $validated['prompt'], $contextPaths);

        $freshRun = $run->fresh(['assistantMessage']);

        return response()->json([
            'status' => 'accepted',
            'run_id' => $run->id,
            'channel' => "private-vault-ai.{$vault->id}.{$user->id}",
            'run' => $freshRun,
        ], 202);
    }

    public function runStatus(Request $request, Vault $vault, AiRun $run): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($deviceToken->isMcpToken()) {
            return response()->json(['error' => 'MCP tokens must use /api/v1/mcp.'], 403);
        }

        $this->vaultAccess->authorizeVaultAccess($deviceToken, $vault);
        $user = $deviceToken->user;

        if ($run->vault_id !== $vault->id || $run->user_id !== $user->id) {
            return response()->json(['error' => 'Run not found.'], 404);
        }

        $run->load('assistantMessage');

        return response()->json([
            'status' => 'ok',
            'run' => $run,
        ]);
    }

    public function proposals(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($deviceToken->isMcpToken()) {
            return response()->json(['error' => 'MCP tokens must use /api/v1/mcp.'], 403);
        }

        $this->vaultAccess->authorizeVaultAccess($deviceToken, $vault);
        $user = $deviceToken->user;

        $status = (string) $request->query('status', 'pending');
        $withDiff = $request->boolean('with_diff', false);

        $query = AiProposal::query()
            ->where('vault_id', $vault->id)
            ->orderByDesc('id');

        if (in_array($status, ['pending', 'approved', 'rejected', 'superseded'], true)) {
            $query->where('status', $status);
        }

        $proposals = $query->limit(50)->get()->filter(
            fn (AiProposal $proposal): bool => $vault->permissionForPath($user, $proposal->target_path) !== 'hidden',
        )->values()->map(function (AiProposal $proposal) use ($withDiff): array {
            $row = $proposal->toArray();
            if ($withDiff) {
                $row['preview'] = $this->proposalService->buildDiffPreview($proposal);
            }

            return $row;
        });

        return response()->json([
            'status' => 'ok',
            'proposals' => $proposals,
        ]);
    }

    public function approveProposal(Request $request, Vault $vault, AiProposal $proposal): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($deviceToken->isMcpToken()) {
            return response()->json(['error' => 'MCP tokens must use /api/v1/mcp.'], 403);
        }

        $this->vaultAccess->authorizeVaultAccess($deviceToken, $vault);

        if ($proposal->vault_id !== $vault->id) {
            return response()->json(['error' => 'Proposal not found.'], 404);
        }

        $validated = $request->validate([
            'override_content' => ['nullable', 'string'],
        ]);

        try {
            $result = $this->proposalService->approve(
                $proposal,
                $deviceToken->user,
                $validated['override_content'] ?? null,
            );

            return response()->json([
                'status' => 'approved',
                ...$result,
            ]);
        } catch (AiException $e) {
            return response()->json(['error' => $e->errorCode, 'message' => $e->getMessage()], $e->statusCode);
        }
    }

    public function rejectProposal(Request $request, Vault $vault, AiProposal $proposal): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($deviceToken->isMcpToken()) {
            return response()->json(['error' => 'MCP tokens must use /api/v1/mcp.'], 403);
        }

        $this->vaultAccess->authorizeVaultAccess($deviceToken, $vault);
        $user = $deviceToken->user;

        if ($proposal->vault_id !== $vault->id) {
            return response()->json(['error' => 'Proposal not found.'], 404);
        }

        if ($vault->permissionForPath($user, $proposal->target_path) !== 'read_write') {
            return response()->json(['error' => 'FORBIDDEN', 'message' => 'Write permission required to reject this proposal.'], 403);
        }

        try {
            $rejected = $this->proposalService->reject($proposal, $user);

            return response()->json([
                'status' => 'rejected',
                'proposal' => $rejected,
            ]);
        } catch (AiException $e) {
            return response()->json(['error' => $e->errorCode, 'message' => $e->getMessage()], $e->statusCode);
        }
    }

    public function generateDigest(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($deviceToken->isMcpToken()) {
            return response()->json(['error' => 'MCP tokens must use /api/v1/mcp.'], 403);
        }

        $this->vaultAccess->authorizeVaultAccess($deviceToken, $vault);

        $validated = $request->validate([
            'period' => ['nullable', 'in:daily,weekly'],
        ]);

        try {
            $result = $this->digestService->generateDigest(
                vault: $vault,
                triggeredBy: $deviceToken->user,
                period: $validated['period'] ?? null,
                force: true,
            );

            return response()->json($result);
        } catch (AiException $e) {
            return response()->json(['error' => $e->errorCode, 'message' => $e->getMessage()], $e->statusCode);
        }
    }

    public function runGardener(Request $request, Vault $vault): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');
        if ($deviceToken->isMcpToken()) {
            return response()->json(['error' => 'MCP tokens must use /api/v1/mcp.'], 403);
        }

        $this->vaultAccess->authorizeVaultAccess($deviceToken, $vault);

        $validated = $request->validate([
            'path' => ['nullable', 'string', 'not_regex:/(^|\/)\.\.($|\/)/'],
            'max_files' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        try {
            if (isset($validated['path']) && trim((string) $validated['path']) !== '') {
                $targetPath = $this->proposalService->normalizePath((string) $validated['path']);
                $file = $vault->files()
                    ->where('path', $targetPath)
                    ->where('is_deleted', false)
                    ->first();

                if ($file === null) {
                    return response()->json(['error' => 'NOT_FOUND', 'message' => "Note '{$targetPath}' not found."], 404);
                }

                $result = $this->gardenerService->runForFile($vault, $file, $deviceToken->user, force: true);

                return response()->json([
                    ...$result,
                    'status' => 'ok',
                ]);
            }

            $result = $this->gardenerService->runForVault(
                vault: $vault,
                triggeredBy: $deviceToken->user,
                maxFiles: (int) ($validated['max_files'] ?? 10),
                force: true,
            );

            return response()->json([
                ...$result,
                'status' => 'ok',
            ]);
        } catch (AiException $e) {
            return response()->json(['error' => $e->errorCode, 'message' => $e->getMessage()], $e->statusCode);
        }
    }
}
