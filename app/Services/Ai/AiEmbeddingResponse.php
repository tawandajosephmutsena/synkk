<?php

namespace App\Services\Ai;

class AiEmbeddingResponse
{
    /**
     * @param  array<int, array<int, float>>  $vectors
     */
    public function __construct(
        public readonly array $vectors,
        public readonly string $model,
        public readonly string $provider,
        public readonly int $inputTokens = 0,
        public readonly int $dimensions = 0,
    ) {}
}
