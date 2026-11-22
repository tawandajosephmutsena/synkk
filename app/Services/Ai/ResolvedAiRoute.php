<?php

namespace App\Services\Ai;

class ResolvedAiRoute
{
    public function __construct(
        public readonly string $providerMode,
        public readonly string $provider,
        public readonly string $baseUrl,
        public readonly ?string $apiKey,
        public readonly string $model,
        public readonly bool $isLocal,
    ) {}
}
