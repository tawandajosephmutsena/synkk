<?php

namespace App\Services\Ai\Adapters;

use App\Exceptions\AiException;
use App\Services\Ai\AiEmbeddingResponse;
use App\Services\Ai\AiResponse;
use App\Services\Ai\ResolvedAiRoute;
use Illuminate\Http\Client\PendingRequest;

class GeminiAdapter extends BaseHttpAdapter
{
    public function chat(ResolvedAiRoute $route, array $messages, array $options = []): AiResponse
    {
        $payload = $this->buildPayload($messages, $options);
        $timeout = (int) ($options['timeout'] ?? 45);
        $modelName = $this->normalizedModelName($route->model);

        $response = $this->authorizedClient($route, $timeout, stream: false)
            ->post($this->baseUrl($route)."/models/{$modelName}:generateContent", $payload);

        if (! $response->successful()) {
            throw new AiException(
                'Gemini request failed: '.$this->extractErrorMessage($response),
                'PROVIDER_ERROR',
                $response->status() >= 400 ? $response->status() : 502,
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        $candidate = is_array($json['candidates'][0] ?? null) ? $json['candidates'][0] : [];
        $parts = is_array($candidate['content']['parts'] ?? null) ? $candidate['content']['parts'] : [];

        $textParts = [];
        $toolCalls = [];

        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }
            if (is_string($part['text'] ?? null)) {
                $textParts[] = $part['text'];
            }
            if (is_array($part['functionCall'] ?? null)) {
                $fnCall = $part['functionCall'];
                $name = (string) ($fnCall['name'] ?? '');
                if ($name !== '') {
                    $toolCalls[] = [
                        'id' => 'gemini_call_'.count($toolCalls),
                        'name' => $name,
                        'arguments' => is_array($fnCall['args'] ?? null) ? $fnCall['args'] : [],
                    ];
                }
            }
        }

        $usage = is_array($json['usageMetadata'] ?? null) ? $json['usageMetadata'] : [];

        return new AiResponse(
            content: implode('', $textParts),
            toolCalls: $toolCalls,
            model: $route->model,
            provider: $route->provider,
            inputTokens: (int) ($usage['promptTokenCount'] ?? 0),
            outputTokens: (int) ($usage['candidatesTokenCount'] ?? 0),
            finishReason: strtolower((string) ($candidate['finishReason'] ?? 'stop')),
            raw: $json,
        );
    }

    public function streamChat(ResolvedAiRoute $route, array $messages, callable $onDelta, array $options = []): AiResponse
    {
        $payload = $this->buildPayload($messages, $options);
        $timeout = (int) ($options['timeout'] ?? 120);
        $modelName = $this->normalizedModelName($route->model);

        $response = $this->authorizedClient($route, $timeout, stream: true)
            ->post($this->baseUrl($route)."/models/{$modelName}:streamGenerateContent?alt=sse", $payload);

        if (! $response->successful()) {
            throw new AiException(
                'Gemini stream request failed: '.$this->extractErrorMessage($response),
                'PROVIDER_ERROR',
                $response->status() >= 400 ? $response->status() : 502,
            );
        }

        $fullContent = '';
        $toolCalls = [];
        $inputTokens = 0;
        $outputTokens = 0;
        $finishReason = 'stop';

        $this->readStreamLines(
            $response->toPsrResponse()->getBody(),
            function (string $line) use (&$fullContent, &$toolCalls, &$inputTokens, &$outputTokens, &$finishReason, $onDelta): void {
                if (! str_starts_with($line, 'data:')) {
                    return;
                }

                $rawJson = trim(substr($line, 5));
                $event = $this->decodeJsonObject($rawJson);
                if (empty($event)) {
                    return;
                }

                $usage = is_array($event['usageMetadata'] ?? null) ? $event['usageMetadata'] : [];
                if (! empty($usage)) {
                    $inputTokens = (int) ($usage['promptTokenCount'] ?? $inputTokens);
                    $outputTokens = (int) ($usage['candidatesTokenCount'] ?? $outputTokens);
                }

                $candidate = is_array($event['candidates'][0] ?? null) ? $event['candidates'][0] : [];
                if (is_string($candidate['finishReason'] ?? null) && $candidate['finishReason'] !== '') {
                    $finishReason = strtolower($candidate['finishReason']);
                }

                $parts = is_array($candidate['content']['parts'] ?? null) ? $candidate['content']['parts'] : [];
                foreach ($parts as $part) {
                    if (! is_array($part)) {
                        continue;
                    }
                    if (is_string($part['text'] ?? null) && $part['text'] !== '') {
                        $fullContent .= $part['text'];
                        $onDelta($part['text']);
                    }
                    if (is_array($part['functionCall'] ?? null)) {
                        $fnCall = $part['functionCall'];
                        $name = (string) ($fnCall['name'] ?? '');
                        if ($name !== '') {
                            $toolCalls[] = [
                                'id' => 'gemini_call_'.count($toolCalls),
                                'name' => $name,
                                'arguments' => is_array($fnCall['args'] ?? null) ? $fnCall['args'] : [],
                            ];
                        }
                    }
                }
            }
        );

        if ($outputTokens === 0 && $fullContent !== '') {
            $outputTokens = $this->estimateTokensFromText($fullContent);
        }

        return new AiResponse(
            content: $fullContent,
            toolCalls: $toolCalls,
            model: $route->model,
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
        $modelName = $this->normalizedModelName($route->model);

        $requests = [];
        foreach (array_values($texts) as $text) {
            $requests[] = [
                'model' => "models/{$modelName}",
                'content' => [
                    'parts' => [['text' => $text]],
                ],
            ];
        }

        $response = $this->authorizedClient($route, $timeout, stream: false)
            ->post($this->baseUrl($route)."/models/{$modelName}:batchEmbedContents", [
                'requests' => $requests,
            ]);

        if (! $response->successful()) {
            throw new AiException(
                'Gemini embedding request failed: '.$this->extractErrorMessage($response),
                'PROVIDER_ERROR',
                $response->status() >= 400 ? $response->status() : 502,
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        $embeddings = is_array($json['embeddings'] ?? null) ? $json['embeddings'] : [];

        $vectors = [];
        foreach ($embeddings as $item) {
            if (! is_array($item) || ! is_array($item['values'] ?? null)) {
                continue;
            }
            $vectors[] = array_map(fn (mixed $v): float => (float) $v, $item['values']);
        }

        $dimensions = ! empty($vectors[0]) ? count($vectors[0]) : 0;
        $estimatedTokens = 0;
        foreach ($texts as $text) {
            $estimatedTokens += $this->estimateTokensFromText($text);
        }

        return new AiEmbeddingResponse(
            vectors: $vectors,
            model: $route->model,
            provider: $route->provider,
            inputTokens: $estimatedTokens,
            dimensions: $dimensions,
        );
    }

    protected function authorizedClient(ResolvedAiRoute $route, int $timeout, bool $stream): PendingRequest
    {
        return $this->client($route, $timeout, $stream)->withHeaders([
            'x-goog-api-key' => (string) $route->apiKey,
        ]);
    }

    protected function baseUrl(ResolvedAiRoute $route): string
    {
        return filled($route->baseUrl)
            ? rtrim($route->baseUrl, '/')
            : 'https://generativelanguage.googleapis.com/v1beta';
    }

    protected function normalizedModelName(string $model): string
    {
        return preg_replace('/^models\//', '', trim($model)) ?? trim($model);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function buildPayload(array $messages, array $options): array
    {
        $systemParts = [];
        $contents = [];

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
                $toolName = (string) ($message['name'] ?? 'tool');
                $decoded = $this->decodeJsonObject((string) ($message['content'] ?? ''));
                $contents[] = [
                    'role' => 'user',
                    'parts' => [[
                        'functionResponse' => [
                            'name' => $toolName,
                            'response' => empty($decoded) ? ['result' => (string) ($message['content'] ?? '')] : $decoded,
                        ],
                    ]],
                ];

                continue;
            }

            if ($role === 'assistant' && ! empty($message['tool_calls']) && is_array($message['tool_calls'])) {
                $parts = [];
                $text = (string) ($message['content'] ?? '');
                if ($text !== '') {
                    $parts[] = ['text' => $text];
                }
                foreach ($message['tool_calls'] as $toolCall) {
                    if (! is_array($toolCall)) {
                        continue;
                    }
                    $fn = is_array($toolCall['function'] ?? null) ? $toolCall['function'] : [];
                    $name = (string) ($fn['name'] ?? $toolCall['name'] ?? '');
                    $rawArgs = $fn['arguments'] ?? $toolCall['arguments'] ?? [];
                    $args = is_array($rawArgs) ? $rawArgs : $this->decodeJsonObject((string) $rawArgs);

                    $parts[] = [
                        'functionCall' => [
                            'name' => $name,
                            'args' => empty($args) ? (object) [] : $args,
                        ],
                    ];
                }
                $contents[] = ['role' => 'model', 'parts' => $parts];

                continue;
            }

            $contents[] = [
                'role' => $role === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => (string) ($message['content'] ?? '')]],
            ];
        }

        $generationConfig = [
            'temperature' => (float) ($options['temperature'] ?? 0.2),
        ];

        if (isset($options['max_tokens'])) {
            $generationConfig['maxOutputTokens'] = (int) $options['max_tokens'];
        }

        if (is_array($options['response_format'] ?? null)
            && in_array($options['response_format']['type'] ?? null, ['json_object', 'json_schema'], true)) {
            $generationConfig['responseMimeType'] = 'application/json';
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => $generationConfig,
        ];

        if (! empty($systemParts)) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => implode("\n\n", $systemParts)]],
            ];
        }

        if (! empty($options['tools']) && is_array($options['tools'])) {
            $declarations = [];
            foreach ($options['tools'] as $tool) {
                if (! is_array($tool)) {
                    continue;
                }
                $fn = is_array($tool['function'] ?? null) ? $tool['function'] : $tool;
                $name = (string) ($fn['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $declarations[] = [
                    'name' => $name,
                    'description' => (string) ($fn['description'] ?? ''),
                    'parameters' => is_array($fn['parameters'] ?? null)
                        ? $fn['parameters']
                        : ['type' => 'object', 'properties' => (object) []],
                ];
            }
            if (! empty($declarations)) {
                $payload['tools'] = [['functionDeclarations' => $declarations]];
            }
        }

        return $payload;
    }
}
