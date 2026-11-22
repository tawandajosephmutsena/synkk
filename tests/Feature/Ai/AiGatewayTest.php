<?php

use App\Enums\TeamRole;
use App\Exceptions\AiException;
use App\Models\AiUsageLog;
use App\Models\Team;
use App\Models\TeamAiConfig;
use App\Models\User;
use App\Models\Vault;
use App\Services\Ai\AiGateway;
use App\Services\Ai\ResolvedAiRoute;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

test('AiGateway routes OpenAI-compatible chat, streaming, embeddings, and DLP redaction', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => function (HttpRequest $request) {
            $data = $request->data();
            if (($data['stream'] ?? false) === true) {
                $sse = "data: {\"model\":\"gpt-4o-mini\",\"choices\":[{\"delta\":{\"content\":\"Hello \"}}]}\n\n".
                    "data: {\"model\":\"gpt-4o-mini\",\"choices\":[{\"delta\":{\"content\":\"[[Note]]\"},\"finish_reason\":\"stop\"}],\"usage\":{\"prompt_tokens\":12,\"completion_tokens\":4}}\n\n".
                    "data: [DONE]\n\n";

                return Http::response($sse, 200, ['Content-Type' => 'text/event-stream']);
            }

            return Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [[
                    'message' => [
                        'content' => 'Answer citing [[Note]].',
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => 'search_notes', 'arguments' => '{"query":"sync"}'],
                        ]],
                    ],
                    'finish_reason' => 'tool_calls',
                ]],
                'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 10],
            ], 200);
        },
        'https://api.openai.com/v1/embeddings' => Http::response([
            'model' => 'text-embedding-3-small',
            'data' => [
                ['index' => 0, 'embedding' => [0.1, 0.2, 0.3]],
            ],
            'usage' => ['prompt_tokens' => 5],
        ], 200),
    ]);

    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'free']);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    TeamAiConfig::create([
        'team_id' => $team->id,
        'is_enabled' => true,
        'provider_mode' => 'byok',
        'chat_provider' => 'openai',
        'chat_model' => 'gpt-4o-mini',
        'chat_api_key' => 'sk-test-12345678901234567890',
        'embedding_provider' => 'openai',
        'embedding_model' => 'text-embedding-3-small',
        'dlp_redaction_enabled' => true,
    ]);

    $gateway = app(AiGateway::class);

    $chatRes = $gateway->chat($team, [
        ['role' => 'user', 'content' => 'Summarize note with key AKIAIOSFODNN7EXAMPLE'],
    ], ['user_id' => $user->id]);

    expect($chatRes->content)->toBe('Answer citing [[Note]].')
        ->and($chatRes->toolCalls)->toHaveCount(1)
        ->and($chatRes->toolCalls[0]['name'])->toBe('search_notes')
        ->and($chatRes->toolCalls[0]['arguments'])->toBe(['query' => 'sync']);

    Http::assertSent(function (HttpRequest $request): bool {
        if ($request->url() !== 'https://api.openai.com/v1/chat/completions') {
            return false;
        }
        $content = (string) ($request->data()['messages'][0]['content'] ?? '');

        return str_contains($content, '[REDACTED:AWS_ACCESS_KEY_ID]')
            && ! str_contains($content, 'AKIAIOSFODNN7EXAMPLE');
    });

    $streamedChunks = [];
    $streamRes = $gateway->streamChat(
        $team,
        [['role' => 'user', 'content' => 'Stream test']],
        function (string $delta) use (&$streamedChunks): void {
            $streamedChunks[] = $delta;
        }
    );

    expect($streamedChunks)->toBe(['Hello ', '[[Note]]'])
        ->and($streamRes->content)->toBe('Hello [[Note]]')
        ->and($streamRes->inputTokens)->toBe(12)
        ->and($streamRes->outputTokens)->toBe(4);

    $embedRes = $gateway->embed($team, ['Hello vector world']);
    expect($embedRes->vectors)->toBe([[0.1, 0.2, 0.3]])
        ->and($embedRes->dimensions)->toBe(3);

    $log = AiUsageLog::query()->where('team_id', $team->id)->where('feature', 'copilot')->first();
    expect($log)->not->toBeNull()
        ->and($log->redacted_secrets_count)->toBe(1);
});

test('AiGateway supports Anthropic chat, streaming, tool calls, and rejects Anthropic embeddings', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.anthropic.com/v1/messages' => function (HttpRequest $request) {
            if (($request->data()['stream'] ?? false) === true) {
                $sse = "event: message_start\ndata: {\"type\":\"message_start\",\"message\":{\"model\":\"claude-3-5-sonnet\",\"usage\":{\"input_tokens\":14}}}\n\n".
                    "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"index\":0,\"delta\":{\"type\":\"text_delta\",\"text\":\"Claude \"}}\n\n".
                    "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"index\":0,\"delta\":{\"type\":\"text_delta\",\"text\":\"Rocks\"}}\n\n".
                    "event: message_delta\ndata: {\"type\":\"message_delta\",\"delta\":{\"stop_reason\":\"end_turn\"},\"usage\":{\"output_tokens\":7}}\n\n";

                return Http::response($sse, 200, ['Content-Type' => 'text/event-stream']);
            }

            return Http::response([
                'model' => 'claude-3-5-sonnet',
                'content' => [
                    ['type' => 'text', 'text' => 'Checking vault...'],
                    ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'read_note', 'input' => ['path' => 'Readme.md']],
                ],
                'stop_reason' => 'tool_use',
                'usage' => ['input_tokens' => 30, 'output_tokens' => 15],
            ], 200);
        },
    ]);

    $team = Team::factory()->create(['plan' => 'free']);
    $config = TeamAiConfig::create([
        'team_id' => $team->id,
        'is_enabled' => true,
        'provider_mode' => 'byok',
        'chat_provider' => 'anthropic',
        'chat_model' => 'claude-3-5-sonnet',
        'chat_api_key' => 'sk-ant-test-key-1234567890123456',
        'embedding_provider' => 'none',
    ]);

    $gateway = app(AiGateway::class);
    $res = $gateway->chat($team, [
        ['role' => 'system', 'content' => 'Be concise.'],
        ['role' => 'user', 'content' => 'Read readme'],
    ]);

    expect($res->content)->toBe('Checking vault...')
        ->and($res->toolCalls[0]['name'])->toBe('read_note')
        ->and($res->toolCalls[0]['arguments'])->toBe(['path' => 'Readme.md']);

    $deltas = [];
    $streamRes = $gateway->streamChat($team, [['role' => 'user', 'content' => 'Hi']], function (string $d) use (&$deltas): void {
        $deltas[] = $d;
    });

    expect(implode('', $deltas))->toBe('Claude Rocks')
        ->and($streamRes->inputTokens)->toBe(14)
        ->and($streamRes->outputTokens)->toBe(7);

    // Direct Anthropic adapter call throws UNSUPPORTED for embeddings
    $route = new ResolvedAiRoute('byok', 'anthropic', 'https://api.anthropic.com/v1', $config->chat_api_key, 'claude-3-5-sonnet', false);
    expect(fn () => $gateway->adapterFor('anthropic')->embed($route, ['text']))
        ->toThrow(AiException::class);
});

test('AiGateway supports Gemini and Ollama adapters including legacy Ollama embeddings fallback', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => 'Gemini answer']]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 8, 'candidatesTokenCount' => 4],
        ], 200),
        'https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:batchEmbedContents' => Http::response([
            'embeddings' => [['values' => [0.5, 0.5]]],
        ], 200),
        'http://localhost:11434/api/chat' => Http::response([
            'model' => 'llama3.2',
            'message' => ['role' => 'assistant', 'content' => 'Ollama local answer'],
            'prompt_eval_count' => 11,
            'eval_count' => 6,
            'done_reason' => 'stop',
        ], 200),
        'http://localhost:11434/api/embed' => Http::response(['error' => 'not found'], 404),
        'http://localhost:11434/api/embeddings' => Http::response([
            'embedding' => [0.25, 0.75],
        ], 200),
    ]);

    $geminiTeam = Team::factory()->create(['plan' => 'free']);
    TeamAiConfig::create([
        'team_id' => $geminiTeam->id,
        'is_enabled' => true,
        'provider_mode' => 'byok',
        'chat_provider' => 'gemini',
        'chat_model' => 'gemini-2.0-flash',
        'chat_api_key' => 'AIzaTestKey123456',
        'embedding_provider' => 'gemini',
        'embedding_model' => 'text-embedding-004',
    ]);

    $gateway = app(AiGateway::class);
    $geminiChat = $gateway->chat($geminiTeam, [['role' => 'user', 'content' => 'Test']]);
    $geminiEmbed = $gateway->embed($geminiTeam, ['Test']);

    expect($geminiChat->content)->toBe('Gemini answer')
        ->and($geminiEmbed->vectors)->toBe([[0.5, 0.5]]);

    $ollamaTeam = Team::factory()->create(['plan' => 'free']);
    TeamAiConfig::create([
        'team_id' => $ollamaTeam->id,
        'is_enabled' => true,
        'provider_mode' => 'ollama',
        'chat_provider' => 'ollama',
        'chat_model' => 'llama3.2',
        'embedding_provider' => 'ollama',
        'embedding_model' => 'nomic-embed-text',
    ]);

    $ollamaChat = $gateway->chat($ollamaTeam, [['role' => 'user', 'content' => 'Local note AKIAIOSFODNN7EXAMPLE']]);
    $ollamaEmbed = $gateway->embed($ollamaTeam, ['Local text']);

    expect($ollamaChat->content)->toBe('Ollama local answer')
        ->and($ollamaEmbed->vectors)->toBe([[0.25, 0.75]]);
});

test('AiGateway blocks disabled workspaces and Zero-Knowledge E2EE vaults', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'cloud']);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $gateway = app(AiGateway::class);

    // Disabled by default
    expect(fn () => $gateway->chat($team, [['role' => 'user', 'content' => 'Hello']]))
        ->toThrow(AiException::class);

    TeamAiConfig::create([
        'team_id' => $team->id,
        'is_enabled' => true,
        'provider_mode' => 'ollama',
        'chat_provider' => 'ollama',
        'chat_model' => 'llama3.2',
    ]);

    $e2eeVault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Encrypted Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
        'is_e2ee' => true,
    ]);

    expect(fn () => $gateway->chat($team, [['role' => 'user', 'content' => 'Hello']], ['vault' => $e2eeVault]))
        ->toThrow(AiException::class);
});
