<?php

namespace App\Services\Ai;

use App\Exceptions\AiException;
use App\Models\Team;
use App\Models\TeamAiConfig;
use App\Models\Vault;
use App\Services\Ai\Adapters\AnthropicAdapter;
use App\Services\Ai\Adapters\GeminiAdapter;
use App\Services\Ai\Adapters\OllamaAdapter;
use App\Services\Ai\Adapters\OpenAiCompatibleAdapter;
use App\Services\Ai\Contracts\AiProviderAdapter;
use App\Services\SecretScannerService;

class AiGateway
{
    public function __construct(
        protected AiQuotaService $quotaService,
        protected SecretScannerService $secretScanner,
        protected OpenAiCompatibleAdapter $openAiCompatibleAdapter,
        protected AnthropicAdapter $anthropicAdapter,
        protected GeminiAdapter $geminiAdapter,
        protected OllamaAdapter $ollamaAdapter,
    ) {}

    /**
     * Execute a non-streaming chat completion with optional vault E2EE guard, DLP redaction, and usage logging.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param array{
     *     feature?: string,
     *     fast?: bool,
     *     vault?: Vault|null,
     *     user_id?: int|null,
     *     portal_id?: int|null,
     *     temperature?: float,
     *     max_tokens?: int,
     *     tools?: array<int, array<string, mixed>>,
     *     tool_choice?: string|array<string, mixed>,
     *     response_format?: array<string, mixed>,
     *     timeout?: int
     * } $options
     *
     * @throws AiException
     */
    public function chat(Team $team, array $messages, array $options = []): AiResponse
    {
        $vault = $options['vault'] ?? null;
        if ($vault instanceof Vault && $vault->is_e2ee) {
            throw new AiException(
                'Server-side AI is disabled on Zero-Knowledge E2EE vaults.',
                'E2EE_BLOCKED',
                403,
            );
        }

        $feature = (string) ($options['feature'] ?? 'copilot');
        $fast = (bool) ($options['fast'] ?? false);

        $route = $this->quotaService->resolveChatRoute($team, $fast, $feature);
        $config = $team->aiConfig()->first();

        [$sanitizedMessages, $redactedCount] = $this->redactMessagesIfRequired($messages, $route, $config);

        $response = $this->adapterFor($route->provider)->chat($route, $sanitizedMessages, $options);

        $this->quotaService->recordUsage($team, [
            'vault_id' => $vault?->id,
            'user_id' => $options['user_id'] ?? null,
            'portal_id' => $options['portal_id'] ?? null,
            'feature' => $feature,
            'provider_mode' => $route->providerMode,
            'provider' => $route->provider,
            'model' => $response->model,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'redacted_secrets_count' => $redactedCount,
        ]);

        return $response;
    }

    /**
     * Execute a streaming chat completion with token delta callback and usage logging.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  callable(string): void  $onDelta
     * @param array{
     *     feature?: string,
     *     fast?: bool,
     *     vault?: Vault|null,
     *     user_id?: int|null,
     *     portal_id?: int|null,
     *     temperature?: float,
     *     max_tokens?: int,
     *     tools?: array<int, array<string, mixed>>,
     *     tool_choice?: string|array<string, mixed>,
     *     response_format?: array<string, mixed>,
     *     timeout?: int
     * } $options
     *
     * @throws AiException
     */
    public function streamChat(Team $team, array $messages, callable $onDelta, array $options = []): AiResponse
    {
        $vault = $options['vault'] ?? null;
        if ($vault instanceof Vault && $vault->is_e2ee) {
            throw new AiException(
                'Server-side AI is disabled on Zero-Knowledge E2EE vaults.',
                'E2EE_BLOCKED',
                403,
            );
        }

        $feature = (string) ($options['feature'] ?? 'copilot');
        $fast = (bool) ($options['fast'] ?? false);

        $route = $this->quotaService->resolveChatRoute($team, $fast, $feature);
        $config = $team->aiConfig()->first();

        [$sanitizedMessages, $redactedCount] = $this->redactMessagesIfRequired($messages, $route, $config);

        $response = $this->adapterFor($route->provider)->streamChat($route, $sanitizedMessages, $onDelta, $options);

        $this->quotaService->recordUsage($team, [
            'vault_id' => $vault?->id,
            'user_id' => $options['user_id'] ?? null,
            'portal_id' => $options['portal_id'] ?? null,
            'feature' => $feature,
            'provider_mode' => $route->providerMode,
            'provider' => $route->provider,
            'model' => $response->model,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'redacted_secrets_count' => $redactedCount,
        ]);

        return $response;
    }

    /**
     * Generate dense embeddings using the team's active embedding route.
     *
     * @param  array<int, string>  $texts
     *
     * @throws AiException
     */
    public function embed(Team $team, array $texts, ?Vault $vault = null, ?int $userId = null): AiEmbeddingResponse
    {
        if ($vault instanceof Vault && $vault->is_e2ee) {
            throw new AiException(
                'Server-side embedding is disabled on Zero-Knowledge E2EE vaults.',
                'E2EE_BLOCKED',
                403,
            );
        }

        $route = $this->quotaService->resolveEmbeddingRoute($team, throwOnQuotaExceeded: true);
        if ($route === null) {
            throw new AiException('No embedding provider is configured for this workspace.', 'NOT_CONFIGURED', 422);
        }

        $config = $team->aiConfig()->first();
        $redactedCount = 0;
        $sanitizedTexts = $texts;

        if (! $route->isLocal && ($config === null || $config->dlp_redaction_enabled)) {
            $sanitizedTexts = [];
            foreach ($texts as $text) {
                $scan = $this->secretScanner->redact($text);
                $sanitizedTexts[] = $scan['redacted'];
                $redactedCount += $scan['count'];
            }
        }

        $response = $this->adapterFor($route->provider)->embed($route, $sanitizedTexts);

        $this->quotaService->recordUsage($team, [
            'vault_id' => $vault?->id,
            'user_id' => $userId,
            'feature' => 'embedding',
            'provider_mode' => $route->providerMode,
            'provider' => $route->provider,
            'model' => $response->model,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => 0,
            'redacted_secrets_count' => $redactedCount,
        ]);

        return $response;
    }

    /**
     * Test connectivity against a workspace's configured chat or embedding endpoint without logging usage.
     *
     * @return array{ok: bool, message: string, latency_ms: int, model: string}
     */
    public function testConnection(TeamAiConfig $config, string $kind = 'chat'): array
    {
        $team = $config->team;

        try {
            if ($kind === 'embedding') {
                $route = $this->quotaService->resolveEmbeddingRoute($team, throwOnQuotaExceeded: true);
                if ($route === null) {
                    return [
                        'ok' => false,
                        'message' => 'Embedding provider is disabled or not configured.',
                        'latency_ms' => 0,
                        'model' => '',
                    ];
                }

                $start = microtime(true);
                $res = $this->adapterFor($route->provider)->embed($route, ['Synkk embedding connection check']);
                $latencyMs = (int) round((microtime(true) - $start) * 1000);

                return [
                    'ok' => ! empty($res->vectors),
                    'message' => "Embedding endpoint responded ({$res->dimensions} dimensions).",
                    'latency_ms' => $latencyMs,
                    'model' => $res->model,
                ];
            }

            $route = $this->quotaService->resolveChatRoute($team, fast: false, feature: 'copilot');

            return $this->adapterFor($route->provider)->testConnection($route);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'latency_ms' => 0,
                'model' => (string) ($kind === 'embedding' ? $config->embedding_model : $config->chat_model),
            ];
        }
    }

    public function adapterFor(string $provider): AiProviderAdapter
    {
        return match ($provider) {
            'anthropic' => $this->anthropicAdapter,
            'gemini' => $this->geminiAdapter,
            'ollama' => $this->ollamaAdapter,
            default => $this->openAiCompatibleAdapter,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    protected function redactMessagesIfRequired(array $messages, ResolvedAiRoute $route, ?TeamAiConfig $config): array
    {
        $shouldRedact = ! $route->isLocal && ($config === null || $config->dlp_redaction_enabled);
        if (! $shouldRedact) {
            return [$messages, 0];
        }

        $redactedCount = 0;
        $sanitized = [];

        foreach ($messages as $message) {
            if (is_string($message['content'] ?? null) && $message['content'] !== '') {
                $scan = $this->secretScanner->redact($message['content']);
                $message['content'] = $scan['redacted'];
                $redactedCount += $scan['count'];
            }

            $sanitized[] = $message;
        }

        return [$sanitized, $redactedCount];
    }
}
