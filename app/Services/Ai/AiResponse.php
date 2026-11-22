<?php

namespace App\Services\Ai;

class AiResponse
{
    /**
     * @param  array<int, array{id: string, name: string, arguments: array<string, mixed>}>  $toolCalls
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $content,
        public readonly array $toolCalls,
        public readonly string $model,
        public readonly string $provider,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly string $finishReason = 'stop',
        public readonly array $raw = [],
    ) {}
}
