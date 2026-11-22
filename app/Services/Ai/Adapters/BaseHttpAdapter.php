<?php

namespace App\Services\Ai\Adapters;

use App\Services\Ai\Contracts\AiProviderAdapter;
use App\Services\Ai\ResolvedAiRoute;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

abstract class BaseHttpAdapter implements AiProviderAdapter
{
    protected function client(ResolvedAiRoute $route, int $timeoutSeconds = 45, bool $stream = false): PendingRequest
    {
        $request = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout($timeoutSeconds);

        if ($stream) {
            $request = $request->withOptions(['stream' => true]);
        }

        return $request;
    }

    /**
     * Read newline-delimited data from a PSR-7 HTTP body stream without buffering the whole response.
     *
     * @param  callable(string): void  $onLine
     */
    protected function readStreamLines(StreamInterface $stream, callable $onLine): void
    {
        $buffer = '';

        while (! $stream->eof()) {
            $chunk = $stream->read(4096);
            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $newlinePos), "\r");
                $buffer = substr($buffer, $newlinePos + 1);
                $onLine($line);
            }
        }

        $trailing = rtrim($buffer, "\r\n");
        if ($trailing !== '') {
            $onLine($trailing);
        }
    }

    /**
     * Decode a JSON string into an associative array safely.
     *
     * @return array<string, mixed>
     */
    protected function decodeJsonObject(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Extract a concise human-readable error message from a failed provider HTTP response.
     */
    protected function extractErrorMessage(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            $nestedMessage = $json['error']['message'] ?? $json['error'] ?? $json['message'] ?? null;
            if (is_string($nestedMessage) && $nestedMessage !== '') {
                return $nestedMessage;
            }
        }

        $body = trim($response->body());

        return $body !== '' ? mb_substr($body, 0, 300) : "HTTP {$response->status()}";
    }

    /**
     * Estimate tokens when a streaming provider omits usage metadata.
     */
    protected function estimateTokensFromText(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return max(1, (int) ceil(mb_strlen($text) / 4));
    }

    /**
     * Default connectivity check executing a minimal chat probe.
     *
     * @return array{ok: bool, message: string, latency_ms: int, model: string}
     */
    public function testConnection(ResolvedAiRoute $route): array
    {
        $start = microtime(true);

        try {
            $response = $this->chat(
                $route,
                [['role' => 'user', 'content' => 'Reply with OK.']],
                ['max_tokens' => 8, 'temperature' => 0.0, 'timeout' => 15],
            );

            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            return [
                'ok' => true,
                'message' => 'Connected successfully ('.$response->model.').',
                'latency_ms' => $latencyMs,
                'model' => $response->model,
            ];
        } catch (\Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'latency_ms' => $latencyMs,
                'model' => $route->model,
            ];
        }
    }
}
