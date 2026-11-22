<?php

namespace App\Services\Ai\Adapters;

use App\Exceptions\AiException;
use App\Services\Ai\AiEmbeddingResponse;
use App\Services\Ai\AiResponse;
use App\Services\Ai\ResolvedAiRoute;

class OllamaAdapter extends BaseHttpAdapter
{
    public function chat(ResolvedAiRoute $route, array $messages, array $options = []): AiResponse
    {
        $payload = $this->buildPayload($route, $messages, $options, stream: false);
        $timeout = (int) ($options['timeout'] ?? 60);

        $response = $this->client($route, $timeout, stream: false)
            ->post($this->baseUrl($route).'/api/chat', $payload);

        if (! $response->successful()) {
            throw new AiException(
                'Ollama request failed: '.$this->extractErrorMessage($response),
                'PROVIDER_ERROR',
                $response->status() >= 400 ? $response->status() : 502,
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        $message = is_array($json['message'] ?? null) ? $json['message'] : [];
        $toolCalls = $this->parseToolCalls($message['tool_calls'] ?? null);

        return new AiResponse(
            content: (string) ($message['content'] ?? ''),
            toolCalls: $toolCalls,
            model: (string) ($json['model'] ?? $route->model),
            provider: $route->provider,
            inputTokens: (int) ($json['prompt_eval_count'] ?? 0),
            outputTokens: (int) ($json['eval_count'] ?? 0),
            finishReason: (string) ($json['done_reason'] ?? 'stop'),
            raw: $json,
        );
    }

    public function streamChat(ResolvedAiRoute $route, array $messages, callable $onDelta, array $options = []): AiResponse
    {
        $payload = $this->buildPayload($route, $messages, $options, stream: true);
        $timeout = (int) ($options['timeout'] ?? 120);

        $response = $this->client($route, $timeout, stream: true)
            ->post($this->baseUrl($route).'/api/chat', $payload);

        if (! $response->successful()) {
            throw new AiException(
                'Ollama stream request failed: '.$this->extractErrorMessage($response),
                'PROVIDER_ERROR',
                $response->status() >= 400 ? $response->status() : 502,
            );
        }

        $fullContent = '';
        $model = $route->model;
        $toolCalls = [];
        $inputTokens = 0;
        $outputTokens = 0;
        $finishReason = 'stop';

        $this->readStreamLines(
            $response->toPsrResponse()->getBody(),
            function (string $line) use (&$fullContent, &$model, &$toolCalls, &$inputTokens, &$outputTokens, &$finishReason, $onDelta): void {
                $event = $this->decodeJsonObject($line);
                if (empty($event)) {
                    return;
                }

                if (is_string($event['model'] ?? null) && $event['model'] !== '') {
                    $model = $event['model'];
                }

                $message = is_array($event['message'] ?? null) ? $event['message'] : [];
                if (is_string($message['content'] ?? null) && $message['content'] !== '') {
                    $fullContent .= $message['content'];
                    $onDelta($message['content']);
                }

                if (! empty($message['tool_calls'])) {
                    $toolCalls = array_merge($toolCalls, $this->parseToolCalls($message['tool_calls']));
                }

                if (($event['done'] ?? false) === true) {
                    $inputTokens = (int) ($event['prompt_eval_count'] ?? $inputTokens);
                    $outputTokens = (int) ($event['eval_count'] ?? $outputTokens);
                    $finishReason = (string) ($event['done_reason'] ?? 'stop');
                }
            }
        );

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

        $timeout = (int) ($options['timeout'] ?? 45);
        $baseUrl = $this->baseUrl($route);

        $response = $this->client($route, $timeout, stream: false)
            ->post("{$baseUrl}/api/embed", [
                'model' => $route->model,
                'input' => array_values($texts),
            ]);

        if ($response->status() === 404) {
            return $this->embedLegacy($route, $texts, $timeout);
        }

        if (! $response->successful()) {
            throw new AiException(
                'Ollama embedding request failed: '.$this->extractErrorMessage($response),
                'PROVIDER_ERROR',
                $response->status() >= 400 ? $response->status() : 502,
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        $rawEmbeddings = is_array($json['embeddings'] ?? null) ? $json['embeddings'] : [];

        $vectors = [];
        foreach ($rawEmbeddings as $vec) {
            if (! is_array($vec)) {
                continue;
            }
            $vectors[] = array_map(fn (mixed $v): float => (float) $v, $vec);
        }

        $dimensions = ! empty($vectors[0]) ? count($vectors[0]) : 0;

        return new AiEmbeddingResponse(
            vectors: $vectors,
            model: (string) ($json['model'] ?? $route->model),
            provider: $route->provider,
            inputTokens: (int) ($json['prompt_eval_count'] ?? 0),
            dimensions: $dimensions,
        );
    }

    /**
     * Fallback to /api/embeddings for older Ollama versions.
     *
     * @param  array<int, string>  $texts
     */
    protected function embedLegacy(ResolvedAiRoute $route, array $texts, int $timeout): AiEmbeddingResponse
    {
        $baseUrl = $this->baseUrl($route);
        $vectors = [];
        $estimatedTokens = 0;

        foreach ($texts as $text) {
            $response = $this->client($route, $timeout, stream: false)
                ->post("{$baseUrl}/api/embeddings", [
                    'model' => $route->model,
                    'prompt' => $text,
                ]);

            if (! $response->successful()) {
                throw new AiException(
                    'Ollama legacy embedding request failed: '.$this->extractErrorMessage($response),
                    'PROVIDER_ERROR',
                    $response->status() >= 400 ? $response->status() : 502,
                );
            }

            /** @var array<string, mixed> $json */
            $json = $response->json() ?? [];
            $vec = is_array($json['embedding'] ?? null) ? $json['embedding'] : [];
            $vectors[] = array_map(fn (mixed $v): float => (float) $v, $vec);
            $estimatedTokens += $this->estimateTokensFromText($text);
        }

        $dimensions = ! empty($vectors[0]) ? count($vectors[0]) : 0;

        return new AiEmbeddingResponse(
            vectors: $vectors,
            model: $route->model,
            provider: $route->provider,
            inputTokens: $estimatedTokens,
            dimensions: $dimensions,
        );
    }

    protected function baseUrl(ResolvedAiRoute $route): string
    {
        return filled($route->baseUrl)
            ? rtrim($route->baseUrl, '/')
            : 'http://localhost:11434';
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function buildPayload(ResolvedAiRoute $route, array $messages, array $options, bool $stream): array
    {
        $payload = [
            'model' => $route->model,
            'messages' => array_values($messages),
            'stream' => $stream,
            'options' => [
                'temperature' => (float) ($options['temperature'] ?? 0.2),
            ],
        ];

        if (isset($options['max_tokens'])) {
            $payload['options']['num_predict'] = (int) $options['max_tokens'];
        }

        if (! empty($options['tools']) && is_array($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }

        if (is_array($options['response_format'] ?? null)
            && in_array($options['response_format']['type'] ?? null, ['json_object', 'json_schema'], true)) {
            $payload['format'] = 'json';
        }

        return $payload;
    }

    /**
     * @return array<int, array{id: string, name: string, arguments: array<string, mixed>}>
     */
    protected function parseToolCalls(mixed $rawCalls): array
    {
        if (! is_array($rawCalls)) {
            return [];
        }

        $toolCalls = [];
        foreach ($rawCalls as $call) {
            if (! is_array($call)) {
                continue;
            }
            $fn = is_array($call['function'] ?? null) ? $call['function'] : [];
            $name = (string) ($fn['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $rawArgs = $fn['arguments'] ?? [];
            $args = is_array($rawArgs) ? $rawArgs : $this->decodeJsonObject((string) $rawArgs);

            $toolCalls[] = [
                'id' => (string) ($call['id'] ?? 'ollama_call_'.count($toolCalls)),
                'name' => $name,
                'arguments' => $args,
            ];
        }

        return $toolCalls;
    }
}
