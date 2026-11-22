<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\AiEmbeddingResponse;
use App\Services\Ai\AiResponse;
use App\Services\Ai\ResolvedAiRoute;

interface AiProviderAdapter
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chat(ResolvedAiRoute $route, array $messages, array $options = []): AiResponse;

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  callable(string): void  $onDelta
     * @param  array<string, mixed>  $options
     */
    public function streamChat(ResolvedAiRoute $route, array $messages, callable $onDelta, array $options = []): AiResponse;

    /**
     * @param  array<int, string>  $texts
     * @param  array<string, mixed>  $options
     */
    public function embed(ResolvedAiRoute $route, array $texts, array $options = []): AiEmbeddingResponse;

    /**
     * @return array{ok: bool, message: string, latency_ms: int, model: string}
     */
    public function testConnection(ResolvedAiRoute $route): array;
}
