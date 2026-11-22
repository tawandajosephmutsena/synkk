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
