<?php

namespace App\Services\Ai\Adapters;

use App\Exceptions\AiException;
use App\Services\Ai\AiEmbeddingResponse;
use App\Services\Ai\AiResponse;
use App\Services\Ai\ResolvedAiRoute;
use Illuminate\Http\Client\PendingRequest;

class AnthropicAdapter extends BaseHttpAdapter
{
    public function chat(ResolvedAiRoute $route, array $messages, array $options = []): AiResponse
    {
        $payload = $this->buildPayload($route, $messages, $options, stream: false);
        $timeout = (int) ($options['timeout'] ?? 45);

        $response = $this->authorizedClient($route, $timeout, stream: false)
            ->post($this->baseUrl($route).'/messages', $payload);

        if (! $response->successful()) {
            throw new AiException(
                'Anthropic request failed: '.$this->extractErrorMessage($response),
                'PROVIDER_ERROR',
                $response->status() >= 400 ? $response->status() : 502,
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        $contentParts = [];
        $toolCalls = [];

        if (is_array($json['content'] ?? null)) {
            foreach ($json['content'] as $block) {
                if (! is_array($block)) {
                    continue;
                }
                if (($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                    $contentParts[] = $block['text'];
                } elseif (($block['type'] ?? null) === 'tool_use') {
                    $toolCalls[] = [
                        'id' => (string) ($block['id'] ?? 'toolu_'.count($toolCalls)),
                        'name' => (string) ($block['name'] ?? ''),
                        'arguments' => is_array($block['input'] ?? null) ? $block['input'] : [],
                    ];
                }
            }
        }

        $usage = is_array($json['usage'] ?? null) ? $json['usage'] : [];

        return new AiResponse(
            content: implode('', $contentParts),
            toolCalls: $toolCalls,
            model: (string) ($json['model'] ?? $route->model),
            provider: $route->provider,
            inputTokens: (int) ($usage['input_tokens'] ?? 0),
            outputTokens: (int) ($usage['output_tokens'] ?? 0),
            finishReason: (string) ($json['stop_reason'] ?? 'end_turn'),
            raw: $json,
        );
    }

    public function streamChat(ResolvedAiRoute $route, array $messages, callable $onDelta, array $options = []): AiResponse
    {
        $payload = $this->buildPayload($route, $messages, $options, stream: true);
        $timeout = (int) ($options['timeout'] ?? 120);

        $response = $this->authorizedClient($route, $timeout, stream: true)
            ->post($this->baseUrl($route).'/messages', $payload);

        if (! $response->successful()) {
            throw new AiException(
                'Anthropic stream request failed: '.$this->extractErrorMessage($response),
                'PROVIDER_ERROR',
                $response->status() >= 400 ? $response->status() : 502,
            );
        }

        $fullContent = '';
        $model = $route->model;
        $finishReason = 'end_turn';
        $inputTokens = 0;
        $outputTokens = 0;
        /** @var array<int, array{id: string, name: string, arguments: string}> $pendingTools */
        $pendingTools = [];

        $this->readStreamLines(
            $response->toPsrResponse()->getBody(),
            function (string $line) use (&$fullContent, &$model, &$finishReason, &$inputTokens, &$outputTokens, &$pendingTools, $onDelta): void {
                if (! str_starts_with($line, 'data:')) {
                    return;
                }

                $rawJson = trim(substr($line, 5));
                $event = $this->decodeJsonObject($rawJson);
                if (empty($event)) {
                    return;
                }

                $type = (string) ($event['type'] ?? '');

                if ($type === 'message_start') {
                    $msg = is_array($event['message'] ?? null) ? $event['message'] : [];
                    if (is_string($msg['model'] ?? null) && $msg['model'] !== '') {
                        $model = $msg['model'];
                    }
                    $usage = is_array($msg['usage'] ?? null) ? $msg['usage'] : [];
                    $inputTokens = (int) ($usage['input_tokens'] ?? $inputTokens);
                } elseif ($type === 'content_block_start') {
                    $idx = (int) ($event['index'] ?? 0);
                    $block = is_array($event['content_block'] ?? null) ? $event['content_block'] : [];
                    if (($block['type'] ?? null) === 'tool_use') {
                        $pendingTools[$idx] = [
                            'id' => (string) ($block['id'] ?? "toolu_{$idx}"),
                            'name' => (string) ($block['name'] ?? ''),
                            'arguments' => '',
                        ];
                    }
                } elseif ($type === 'content_block_delta') {
                    $idx = (int) ($event['index'] ?? 0);
                    $delta = is_array($event['delta'] ?? null) ? $event['delta'] : [];
                    if (($delta['type'] ?? null) === 'text_delta' && is_string($delta['text'] ?? null) && $delta['text'] !== '') {
                        $fullContent .= $delta['text'];
                        $onDelta($delta['text']);
                    } elseif (($delta['type'] ?? null) === 'input_json_delta' && isset($pendingTools[$idx]) && is_string($delta['partial_json'] ?? null)) {
                        $pendingTools[$idx]['arguments'] .= $delta['partial_json'];
                    }
                } elseif ($type === 'message_delta') {
                    $delta = is_array($event['delta'] ?? null) ? $event['delta'] : [];
                    if (is_string($delta['stop_reason'] ?? null) && $delta['stop_reason'] !== '') {
                        $finishReason = $delta['stop_reason'];
                    }
                    $usage = is_array($event['usage'] ?? null) ? $event['usage'] : [];
                    $outputTokens = (int) ($usage['output_tokens'] ?? $outputTokens);
                }
            }
        );

        ksort($pendingTools);
        $toolCalls = [];
        foreach ($pendingTools as $tool) {
            if ($tool['name'] === '') {
                continue;
            }
            $toolCalls[] = [
                'id' => $tool['id'],
                'name' => $tool['name'],
                'arguments' => $this->decodeJsonObject($tool['arguments']),
            ];
        }

        if ($outputTokens === 0 && $fullContent !== '') {
            $outputTokens = $this->estimateTokensFromText($fullContent);
        }

        return new AiResponse(
            content: $fullContent,
            toolCalls: $toolCalls,
            model: $model,
            provider: $route->provider,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            finishReason: $finishReason,
        );
    }

    public function embed(ResolvedAiRoute $route, array $texts, array $options = []): AiEmbeddingResponse
    {
        throw new AiException(
            'Anthropic does not provide a native embeddings API. Configure an OpenAI, Gemini, OpenRouter, or Ollama embedding provider instead.',
            'UNSUPPORTED',
            422,
        );
    }

    protected function authorizedClient(ResolvedAiRoute $route, int $timeout, bool $stream): PendingRequest
    {
        return $this->client($route, $timeout, $stream)->withHeaders([
            'x-api-key' => (string) $route->apiKey,
            'anthropic-version' => '2023-06-01',
        ]);
    }

    protected function baseUrl(ResolvedAiRoute $route): string
    {
        return filled($route->baseUrl)
            ? rtrim($route->baseUrl, '/')
            : 'https://api.anthropic.com/v1';
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function buildPayload(ResolvedAiRoute $route, array $messages, array $options, bool $stream): array
    {
        $systemParts = [];
        $convertedMessages = [];

        foreach ($messages as $message) {
            $role = (string) ($message['role'] ?? 'user');

            if ($role === 'system') {
                $text = (string) ($message['content'] ?? '');
                if ($text !== '') {
                    $systemParts[] = $text;
                }

                continue;
            }

            if ($role === 'tool') {
                $convertedMessages[] = [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'tool_result',
                        'tool_use_id' => (string) ($message['tool_call_id'] ?? ''),
                        'content' => (string) ($message['content'] ?? ''),
                    ]],
                ];

                continue;
            }

            if ($role === 'assistant' && ! empty($message['tool_calls']) && is_array($message['tool_calls'])) {
                $blocks = [];
                $text = (string) ($message['content'] ?? '');
                if ($text !== '') {
                    $blocks[] = ['type' => 'text', 'text' => $text];
                }
                foreach ($message['tool_calls'] as $toolCall) {
                    if (! is_array($toolCall)) {
                        continue;
                    }
                    $fn = is_array($toolCall['function'] ?? null) ? $toolCall['function'] : [];
                    $name = (string) ($fn['name'] ?? $toolCall['name'] ?? '');
                    $rawArgs = $fn['arguments'] ?? $toolCall['arguments'] ?? [];
                    $input = is_array($rawArgs) ? $rawArgs : $this->decodeJsonObject((string) $rawArgs);

                    $blocks[] = [
                        'type' => 'tool_use',
                        'id' => (string) ($toolCall['id'] ?? 'toolu_'.count($blocks)),
                        'name' => $name,
                        'input' => empty($input) ? (object) [] : $input,
                    ];
                }
                $convertedMessages[] = ['role' => 'assistant', 'content' => $blocks];

                continue;
            }

            $convertedMessages[] = [
                'role' => $role === 'assistant' ? 'assistant' : 'user',
                'content' => (string) ($message['content'] ?? ''),
            ];
        }

        $payload = [
            'model' => $route->model,
            'max_tokens' => (int) ($options['max_tokens'] ?? 2048),
            'temperature' => (float) ($options['temperature'] ?? 0.2),
            'messages' => $convertedMessages,
            'stream' => $stream,
        ];

        if (! empty($systemParts)) {
            $payload['system'] = implode("\n\n", $systemParts);
        }

        if (! empty($options['tools']) && is_array($options['tools'])) {
            $anthropicTools = [];
            foreach ($options['tools'] as $tool) {
                if (! is_array($tool)) {
                    continue;
                }
                $fn = is_array($tool['function'] ?? null) ? $tool['function'] : $tool;
                $name = (string) ($fn['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $anthropicTools[] = [
                    'name' => $name,
                    'description' => (string) ($fn['description'] ?? ''),
                    'input_schema' => is_array($fn['parameters'] ?? null)
                        ? $fn['parameters']
                        : ['type' => 'object', 'properties' => (object) []],
                ];
            }
            if (! empty($anthropicTools)) {
                $payload['tools'] = $anthropicTools;
            }
        }

        return $payload;
    }
}
