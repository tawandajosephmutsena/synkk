<?php

namespace App\Services\Ai\Adapters;

use App\Exceptions\AiException;
use App\Services\Ai\AiEmbeddingResponse;
use App\Services\Ai\AiResponse;
use App\Services\Ai\ResolvedAiRoute;
use Illuminate\Http\Client\PendingRequest;

class OpenAiCompatibleAdapter extends BaseHttpAdapter
{
    public function chat(ResolvedAiRoute $route, array $messages, array $options = []): AiResponse
    {
        $payload = $this->buildChatPayload($route, $messages, $options, stream: false);
        $timeout = (int) ($options['timeout'] ?? 45);

        $response = $this->authorizedClient($route, $timeout, stream: false)
            ->post($this->baseUrl($route).'/chat/completions', $payload);

        if (! $response->successful()) {
            throw new AiException(
                'Provider request failed: '.$this->extractErrorMessage($response),
                'PROVIDER_ERROR',
                $response->status() >= 400 ? $response->status() : 502,
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        $choice = is_array($json['choices'][0] ?? null) ? $json['choices'][0] : [];
        $message = is_array($choice['message'] ?? null) ? $choice['message'] : [];

        $content = is_string($message['content'] ?? null) ? $message['content'] : '';
        $toolCalls = [];

        if (is_array($message['tool_calls'] ?? null)) {
            foreach ($message['tool_calls'] as $toolCall) {
                if (! is_array($toolCall)) {
                    continue;
                }
                $fn = is_array($toolCall['function'] ?? null) ? $toolCall['function'] : [];
                $name = (string) ($fn['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $rawArgs = $fn['arguments'] ?? '{}';
                $args = is_array($rawArgs) ? $rawArgs : $this->decodeJsonObject((string) $rawArgs);

                $toolCalls[] = [
                    'id' => (string) ($toolCall['id'] ?? 'call_'.count($toolCalls)),
                    'name' => $name,
                    'arguments' => $args,
                ];
            }
        }

        $usage = is_array($json['usage'] ?? null) ? $json['usage'] : [];

        return new AiResponse(
            content: $content,
            toolCalls: $toolCalls,
            model: (string) ($json['model'] ?? $route->model),
            provider: $route->provider,
            inputTokens: (int) ($usage['prompt_tokens'] ?? 0),
            outputTokens: (int) ($usage['completion_tokens'] ?? 0),
            finishReason: (string) ($choice['finish_reason'] ?? 'stop'),
            raw: $json,
        );
    }

    public function streamChat(ResolvedAiRoute $route, array $messages, callable $onDelta, array $options = []): AiResponse
    {
        $payload = $this->buildChatPayload($route, $messages, $options, stream: true);
        $timeout = (int) ($options['timeout'] ?? 120);

        $response = $this->authorizedClient($route, $timeout, stream: true)
            ->post($this->baseUrl($route).'/chat/completions', $payload);

        if (! $response->successful()) {
            throw new AiException(
                'Provider stream request failed: '.$this->extractErrorMessage($response),
                'PROVIDER_ERROR',
                $response->status() >= 400 ? $response->status() : 502,
            );
        }

        $fullContent = '';
        $model = $route->model;
        $finishReason = 'stop';
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
                if ($rawJson === '' || $rawJson === '[DONE]') {
                    return;
                }

                $event = $this->decodeJsonObject($rawJson);
                if (empty($event)) {
                    return;
                }

                if (is_string($event['model'] ?? null) && $event['model'] !== '') {
                    $model = $event['model'];
                }

                if (is_array($event['usage'] ?? null)) {
                    $inputTokens = (int) ($event['usage']['prompt_tokens'] ?? $inputTokens);
                    $outputTokens = (int) ($event['usage']['completion_tokens'] ?? $outputTokens);
                }

                $choice = is_array($event['choices'][0] ?? null) ? $event['choices'][0] : [];
                if (is_string($choice['finish_reason'] ?? null) && $choice['finish_reason'] !== '') {
                    $finishReason = $choice['finish_reason'];
                }

                $delta = is_array($choice['delta'] ?? null) ? $choice['delta'] : [];
                if (is_string($delta['content'] ?? null) && $delta['content'] !== '') {
                    $fullContent .= $delta['content'];
                    $onDelta($delta['content']);
                }

                if (is_array($delta['tool_calls'] ?? null)) {
                    foreach ($delta['tool_calls'] as $toolDelta) {
                        if (! is_array($toolDelta)) {
                            continue;
                        }
                        $idx = (int) ($toolDelta['index'] ?? 0);
                        if (! isset($pendingTools[$idx])) {
                            $pendingTools[$idx] = [
                                'id' => (string) ($toolDelta['id'] ?? "call_{$idx}"),
                                'name' => '',
                                'arguments' => '',
                            ];
                        }
                        if (is_string($toolDelta['id'] ?? null) && $toolDelta['id'] !== '') {
                            $pendingTools[$idx]['id'] = $toolDelta['id'];
                        }
                        $fn = is_array($toolDelta['function'] ?? null) ? $toolDelta['function'] : [];
                        if (is_string($fn['name'] ?? null) && $fn['name'] !== '') {
                            $pendingTools[$idx]['name'] .= $fn['name'];
                        }
                        if (is_string($fn['arguments'] ?? null) && $fn['arguments'] !== '') {
                            $pendingTools[$idx]['arguments'] .= $fn['arguments'];
                        }
                    }
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
        if (empty($texts)) {
            return new AiEmbeddingResponse(
                vectors: [],
                model: $route->model,
                provider: $route->provider,
                inputTokens: 0,
                dimensions: 0,
            );
        }

        $timeout = (int) ($options['timeout'] ?? 30);

        $response = $this->authorizedClient($route, $timeout, stream: false)
            ->post($this->baseUrl($route).'/embeddings', [
                'model' => $route->model,
                'input' => array_values($texts),
            ]);

        if (! $response->successful()) {
            throw new AiException(
                'Embedding request failed: '.$this->extractErrorMessage($response),
                'PROVIDER_ERROR',
                $response->status() >= 400 ? $response->status() : 502,
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        $items = is_array($json['data'] ?? null) ? $json['data'] : [];

        usort($items, fn (mixed $a, mixed $b): int => ((int) (is_array($a) ? ($a['index'] ?? 0) : 0)) <=> ((int) (is_array($b) ? ($b['index'] ?? 0) : 0)));

        $vectors = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! is_array($item['embedding'] ?? null)) {
                continue;
            }
            $vectors[] = array_map(fn (mixed $v): float => (float) $v, $item['embedding']);
        }

        $dimensions = ! empty($vectors[0]) ? count($vectors[0]) : 0;
        $usage = is_array($json['usage'] ?? null) ? $json['usage'] : [];
        $inputTokens = (int) ($usage['prompt_tokens'] ?? $usage['total_tokens'] ?? 0);

        return new AiEmbeddingResponse(
            vectors: $vectors,
            model: (string) ($json['model'] ?? $route->model),
            provider: $route->provider,
            inputTokens: $inputTokens,
            dimensions: $dimensions,
        );
    }

    protected function authorizedClient(ResolvedAiRoute $route, int $timeout, bool $stream): PendingRequest
    {
        $client = $this->client($route, $timeout, $stream);

        if (filled($route->apiKey)) {
            $client = $client->withToken((string) $route->apiKey);
        }

        if ($route->provider === 'openrouter' || str_contains($this->baseUrl($route), 'openrouter.ai')) {
            $client = $client->withHeaders([
                'HTTP-Referer' => (string) config('app.url', 'https://synkk.space'),
                'X-Title' => (string) config('app.name', 'Synkk'),
            ]);
        }

        return $client;
    }

    protected function baseUrl(ResolvedAiRoute $route): string
    {
        if (filled($route->baseUrl)) {
            return rtrim($route->baseUrl, '/');
        }

        return $route->provider === 'openrouter'
            ? 'https://openrouter.ai/api/v1'
            : 'https://api.openai.com/v1';
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function buildChatPayload(ResolvedAiRoute $route, array $messages, array $options, bool $stream): array
    {
        $payload = [
            'model' => $route->model,
            'messages' => array_values($messages),
            'temperature' => (float) ($options['temperature'] ?? 0.2),
            'stream' => $stream,
        ];

        if ($stream && in_array($route->provider, ['openai', 'openrouter'], true)) {
            $payload['stream_options'] = ['include_usage' => true];
        }

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        if (! empty($options['tools']) && is_array($options['tools'])) {
            $payload['tools'] = $options['tools'];
            if (isset($options['tool_choice'])) {
                $payload['tool_choice'] = $options['tool_choice'];
            }
        }

        if (! empty($options['response_format']) && is_array($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        return $payload;
    }
}
