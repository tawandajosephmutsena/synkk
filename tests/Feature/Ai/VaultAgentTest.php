<?php

use App\Actions\Vaults\SyncUploadAction;
use App\Broadcasting\VaultAiChannel;
use App\Enums\TeamRole;
use App\Events\AiStreamChunk;
use App\Events\AiStreamCompleted;
use App\Models\AiProposal;
use App\Models\AiThread;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\TeamAiConfig;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultAiSetting;
use App\Models\VaultPermission;
use App\Services\Ai\VaultAgentService;
use App\Services\Ai\VaultIndexService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('VaultAgentService runs multi-round tool calls, respects ACLs & exclusions, and stages proposals', function () {
    Http::preventStrayRequests();
    $callCount = 0;

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => function (HttpRequest $request) use (&$callCount) {
            $callCount++;
            $messages = $request->data()['messages'] ?? [];

            if ($callCount === 1) {
                $sse = "data: {\"model\":\"gpt-4o-mini\",\"choices\":[{\"delta\":{\"tool_calls\":[{\"index\":0,\"id\":\"call_search\",\"function\":{\"name\":\"search_notes\",\"arguments\":\"{\\\"query\\\":\\\"sync protocol\\\"}\"}}]}}]}\n\n".
                    "data: {\"model\":\"gpt-4o-mini\",\"choices\":[{\"delta\":{},\"finish_reason\":\"tool_calls\"}]}\n\n".
                    "data: [DONE]\n\n";

                return Http::response($sse, 200, ['Content-Type' => 'text/event-stream']);
            }

            if ($callCount === 2) {
                $sse = "data: {\"model\":\"gpt-4o-mini\",\"choices\":[{\"delta\":{\"tool_calls\":[{\"index\":0,\"id\":\"call_propose\",\"function\":{\"name\":\"propose_note\",\"arguments\":\"{\\\"path\\\":\\\"Briefs/SyncSummary.md\\\",\\\"content\\\":\\\"# Sync Summary\\\\nUses SHA-256 checks.\\\",\\\"rationale\\\":\\\"Summarized sync protocol\\\",\\\"action\\\":\\\"create\\\"}\"}}]}}]}\n\n".
                    "data: {\"model\":\"gpt-4o-mini\",\"choices\":[{\"delta\":{},\"finish_reason\":\"tool_calls\"}]}\n\n".
                    "data: [DONE]\n\n";

                return Http::response($sse, 200, ['Content-Type' => 'text/event-stream']);
            }

            // Ensure hidden/excluded notes never appear in tool responses sent to LLM
            $serialized = json_encode($messages) ?: '';
            expect($serialized)->not->toContain('TopSecretBoardPlan')
                ->and($serialized)->not->toContain('PrivateDiarySecret');

            $sse = "data: {\"model\":\"gpt-4o-mini\",\"choices\":[{\"delta\":{\"content\":\"I staged a summary in [[Briefs/SyncSummary.md]] based on [[Architecture/Sync.md]].\"}}]}\n\n".
                "data: {\"model\":\"gpt-4o-mini\",\"choices\":[{\"delta\":{},\"finish_reason\":\"stop\"}],\"usage\":{\"prompt_tokens\":50,\"completion_tokens\":20}}\n\n".
                "data: [DONE]\n\n";

            return Http::response($sse, 200, ['Content-Type' => 'text/event-stream']);
        },
    ]);

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'pro_ltd']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    TeamAiConfig::create([
        'team_id' => $team->id,
        'is_enabled' => true,
        'provider_mode' => 'byok',
        'chat_provider' => 'openai',
        'chat_model' => 'gpt-4o-mini',
        'chat_api_key' => 'sk-test-12345678901234567890',
        'embedding_provider' => 'none',
    ]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Agent Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    VaultAiSetting::create([
        'vault_id' => $vault->id,
        'ai_mode' => 'server',
        'excluded_paths' => ['Private/**'],
    ]);

    VaultPermission::create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => 'Executive',
        'permission' => 'hidden',
        'is_folder' => true,
    ]);

    $sync = app(SyncUploadAction::class);
    $sync->execute($vault, $owner, 'Mac', 'Architecture/Sync.md', "# Sync Protocol\nSynkk verifies SHA-256 hashes on every upload.");
    $sync->execute($vault, $owner, 'Mac', 'Executive/Secret.md', '# TopSecretBoardPlan');
    $sync->execute($vault, $owner, 'Mac', 'Private/Diary.md', '# PrivateDiarySecret');

    app(VaultIndexService::class)->indexVault($vault);

    $thread = AiThread::create([
        'team_id' => $team->id,
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'title' => 'New Conversation',
    ]);

    $agent = app(VaultAgentService::class);
    $assistantMsg = $agent->runTurn($thread, $member, 'Search sync protocol and draft a summary note');

    expect($assistantMsg->content)->toContain('Briefs/SyncSummary.md')
        ->and($assistantMsg->proposal_ids)->toHaveCount(1)
        ->and($assistantMsg->citations)->not->toBeEmpty()
        ->and($thread->fresh()->title)->toContain('Search sync protocol');

    $proposal = AiProposal::findOrFail($assistantMsg->proposal_ids[0]);
    expect($proposal->target_path)->toBe('Briefs/SyncSummary.md')
        ->and($proposal->status)->toBe('pending');
});

test('Vault AI API runs streaming job over Reverb channel and isolates private threads', function () {
    Event::fake([AiStreamChunk::class, AiStreamCompleted::class]);
    Http::preventStrayRequests();
    Http::fake([
        'http://localhost:11434/api/chat' => Http::response(
            json_encode(['model' => 'llama3.2', 'message' => ['role' => 'assistant', 'content' => 'Local streamed response across more than sixty characters so at least one chunk event broadcasts immediately!'], 'done' => true, 'prompt_eval_count' => 10, 'eval_count' => 15])."\n",
            200
        ),
    ]);

    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'pro_ltd']);
    $team->members()->attach($userA, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($userB, ['role' => TeamRole::Member->value]);

    TeamAiConfig::create([
        'team_id' => $team->id,
        'is_enabled' => true,
        'provider_mode' => 'ollama',
        'chat_provider' => 'ollama',
        'chat_model' => 'llama3.2',
    ]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Stream API Vault',
        'default_permission' => 'read_write',
        'created_by' => $userA->id,
    ]);

    VaultAiSetting::create([
        'vault_id' => $vault->id,
        'ai_mode' => 'server',
    ]);

    $tokenA = DeviceToken::createToken($userA, $team, 'Mac A', 'mac')['plain_token'];
    $tokenB = DeviceToken::createToken($userB, $team, 'Mac B', 'mac')['plain_token'];

    $threadRes = $this->withHeader('Authorization', "Bearer {$tokenA}")
        ->postJson("/api/v1/vaults/{$vault->slug}/ai/threads", ['title' => 'Private Investigation'])
        ->assertCreated();

    $threadId = $threadRes->json('thread.id');

    // User B cannot view User A's private thread
    $this->withHeader('Authorization', "Bearer {$tokenB}")
        ->getJson("/api/v1/vaults/{$vault->slug}/ai/threads/{$threadId}/messages")
        ->assertNotFound();

    // Send message -> sync queue executes RunVaultAgentTurnJob & broadcasts stream events
    $sendRes = $this->withHeader('Authorization', "Bearer {$tokenA}")
        ->postJson("/api/v1/vaults/{$vault->slug}/ai/threads/{$threadId}/messages", [
            'prompt' => 'Explain the vault state',
        ])
        ->assertStatus(202);

    $runId = $sendRes->json('run_id');

    $this->withHeader('Authorization', "Bearer {$tokenA}")
        ->getJson("/api/v1/vaults/{$vault->slug}/ai/runs/{$runId}")
        ->assertOk()
        ->assertJsonPath('run.status', 'completed');

    Event::assertDispatched(AiStreamChunk::class);
    Event::assertDispatched(AiStreamCompleted::class, function (AiStreamCompleted $event) use ($vault, $userA): bool {
        return $event->vaultId === $vault->id && $event->userId === $userA->id && $event->status === 'completed';
    });

    // Verify private channel authorization allows User A and rejects User B on User A's channel
    $channel = app(VaultAiChannel::class);
    expect($channel->join($userA, $vault->id, $userA->id))->toBeTrue()
        ->and($channel->join($userB, $vault->id, $userA->id))->toBeFalse();
});

test('Vault AI HTTP API supports thread listing, context_paths security gating, proposal diff inbox, and broadcasting auth', function () {
    Http::preventStrayRequests();
    $capturedMessages = [];
    Http::fake([
        'http://localhost:11434/api/chat' => function (HttpRequest $request) use (&$capturedMessages) {
            $capturedMessages = $request->data()['messages'] ?? [];

            return Http::response(
                json_encode([
                    'model' => 'llama3.2',
                    'message' => ['role' => 'assistant', 'content' => 'Reviewed [[Notes/Active.md]] safely.'],
                    'done' => true,
                    'prompt_eval_count' => 12,
                    'eval_count' => 14,
                ])."\n",
                200
            );
        },
    ]);

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'pro_ltd']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    TeamAiConfig::create([
        'team_id' => $team->id,
        'is_enabled' => true,
        'provider_mode' => 'ollama',
        'chat_provider' => 'ollama',
        'chat_model' => 'llama3.2',
    ]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'HTTP Contract Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    VaultAiSetting::create([
        'vault_id' => $vault->id,
        'ai_mode' => 'server',
        'excluded_paths' => ['Private/**'],
    ]);

    VaultPermission::create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => 'Executive',
        'permission' => 'hidden',
        'is_folder' => true,
    ]);

    $sync = app(SyncUploadAction::class);
    $sync->execute($vault, $owner, 'Mac', 'Notes/Active.md', "# Active Note\nAccessible team details.");
    $sync->execute($vault, $owner, 'Mac', 'Executive/Hidden.md', '# ExecutiveHiddenSecret');
    $sync->execute($vault, $owner, 'Mac', 'Private/Excluded.md', '# ExcludedPrivateSecret');

    $ownerPrivate = AiThread::create([
        'team_id' => $team->id,
        'vault_id' => $vault->id,
        'user_id' => $owner->id,
        'title' => 'Owner Only Thread',
        'is_shared_with_team' => false,
        'last_message_at' => now(),
    ]);
    $sharedThread = AiThread::create([
        'team_id' => $team->id,
        'vault_id' => $vault->id,
        'user_id' => $owner->id,
        'title' => 'Shared Team Thread',
        'is_shared_with_team' => true,
        'last_message_at' => now(),
    ]);

    $ownerToken = DeviceToken::createToken($owner, $team, 'Owner Mac', 'mac')['plain_token'];
    $memberToken = DeviceToken::createToken($member, $team, 'Member Mac', 'mac')['plain_token'];

    // Member sees shared thread, not owner's private thread
    $listRes = $this->withHeader('Authorization', "Bearer {$memberToken}")
        ->getJson("/api/v1/vaults/{$vault->slug}/ai/threads")
        ->assertOk();

    $threadIds = collect($listRes->json('threads'))->pluck('id')->all();
    expect($threadIds)->toContain($sharedThread->id)
        ->and($threadIds)->not->toContain($ownerPrivate->id);

    // Create member's own thread & send message with context_paths (including unauthorized paths)
    $created = $this->withHeader('Authorization', "Bearer {$memberToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/ai/threads", ['title' => 'Active Note Query'])
        ->assertCreated();

    $memberThreadId = (int) $created->json('thread.id');

    $sendRes = $this->withHeader('Authorization', "Bearer {$memberToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/ai/threads/{$memberThreadId}/messages", [
            'prompt' => 'Summarize my active note',
            'context_paths' => ['Notes/Active.md', 'Executive/Hidden.md', 'Private/Excluded.md'],
        ])
        ->assertStatus(202);

    $serializedPrompt = json_encode($capturedMessages) ?: '';
    expect($serializedPrompt)->toContain('Accessible team details.')
        ->and($serializedPrompt)->not->toContain('ExecutiveHiddenSecret')
        ->and($serializedPrompt)->not->toContain('ExcludedPrivateSecret');

    $runRes = $this->withHeader('Authorization', "Bearer {$memberToken}")
        ->getJson("/api/v1/vaults/{$vault->slug}/ai/runs/".$sendRes->json('run_id'))
        ->assertOk()
        ->assertJsonPath('run.status', 'completed')
        ->assertJsonPath('run.assistant_message.citations.0.note', 'Notes/Active.md');

    expect($runRes->json('run.assistant_message.content'))->toContain('Notes/Active.md');

    // Stage stale update, move, and hidden proposals to verify exact proposals?with_diff=1 contract
    $initialStale = $sync->execute($vault, $owner, 'Mac', 'Notes/Stale.md', "# Stale\nLine 1\nLine 2\nLine 3", baseVersion: 0);
    $staleBaseVersion = (int) $initialStale['version'];

    $staleProposal = AiProposal::create([
        'team_id' => $team->id,
        'vault_id' => $vault->id,
        'created_by_user_id' => $owner->id,
        'source' => 'gardener_links',
        'action' => 'update',
        'target_path' => 'Notes/Stale.md',
        'base_version' => $staleBaseVersion,
        'proposed_content' => "# Stale\nLine 1\nLine 2\nLine 3 (AI Link)",
        'rationale' => 'Add related link',
        'status' => 'pending',
    ]);
    // Advance canonical note to make proposal stale
    $sync->execute($vault, $owner, 'Laptop', 'Notes/Stale.md', "# Stale\nLine 1 (Human Edit)\nLine 2\nLine 3", baseVersion: $staleBaseVersion);

    $hiddenProposal = AiProposal::create([
        'team_id' => $team->id,
        'vault_id' => $vault->id,
        'created_by_user_id' => $owner->id,
        'source' => 'copilot',
        'action' => 'update',
        'target_path' => 'Executive/Hidden.md',
        'proposed_content' => '# Secret update',
        'status' => 'pending',
    ]);

    $rejectTarget = AiProposal::create([
        'team_id' => $team->id,
        'vault_id' => $vault->id,
        'created_by_user_id' => $owner->id,
        'source' => 'copilot',
        'action' => 'create',
        'target_path' => 'Notes/DraftReject.md',
        'proposed_content' => '# Reject me',
        'status' => 'pending',
    ]);

    $proposalsRes = $this->withHeader('Authorization', "Bearer {$memberToken}")
        ->getJson("/api/v1/vaults/{$vault->slug}/ai/proposals?status=pending&with_diff=1")
        ->assertOk();

    $proposalIds = collect($proposalsRes->json('proposals'))->pluck('id')->all();
    expect($proposalIds)->toContain($staleProposal->id)
        ->and($proposalIds)->toContain($rejectTarget->id)
        ->and($proposalIds)->not->toContain($hiddenProposal->id);

    $staleRow = collect($proposalsRes->json('proposals'))->firstWhere('id', $staleProposal->id);
    expect($staleRow)
        ->toHaveKey('target_path', 'Notes/Stale.md')
        ->toHaveKey('source', 'gardener_links')
        ->toHaveKey('preview.is_stale', true)
        ->and($staleRow['preview']['diff']['hunks'])->toBeArray()->not->toBeEmpty()
        ->and($staleRow['preview']['diff']['merged_content'])->toContain('Human Edit');

    // Approve with explicit override_content and reject second proposal
    $this->withHeader('Authorization', "Bearer {$memberToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/ai/proposals/{$staleProposal->id}/approve", [
            'override_content' => "# Stale\nApproved custom override content.",
        ])
        ->assertOk()
        ->assertJsonPath('status', 'approved');

    expect($vault->files()->where('path', 'Notes/Stale.md')->firstOrFail()->getContents())
        ->toBe("# Stale\nApproved custom override content.");

    $this->withHeader('Authorization', "Bearer {$memberToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/ai/proposals/{$rejectTarget->id}/reject")
        ->assertOk()
        ->assertJsonPath('status', 'rejected');

    // Broadcasting auth with Reverb config & device token
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-reverb-key',
        'broadcasting.connections.reverb.secret' => 'test-reverb-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app-id',
    ]);
    require base_path('routes/channels.php');

    $this->withHeader('Authorization', "Bearer {$memberToken}")
        ->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-vault-ai.{$vault->id}.{$member->id}",
        ])
        ->assertOk()
        ->assertJsonStructure(['auth']);

    $this->withHeader('Authorization', "Bearer {$memberToken}")
        ->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-vault-ai.{$vault->id}.{$owner->id}",
        ])
        ->assertForbidden();
});
