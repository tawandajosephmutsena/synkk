<?php

namespace App\Http\Middleware;

use App\Models\DeviceToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDeviceToken
{
    private const HEARTBEAT_INTERVAL_SECONDS = 60;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'A valid Bearer token is required in the Authorization header.',
            ], 401);
        }

        $deviceToken = DeviceToken::with(['user', 'team'])->where('token_hash', hash('sha256', $token))->first();

        if (! $deviceToken || ! $deviceToken->user || ! $deviceToken->team) {
            return response()->json([
                'error' => 'Invalid Token',
                'message' => 'The provided device sync token is invalid or has been revoked.',
            ], 401);
        }

        $heartbeatAt = now();
        $clientIp = $request->ip();
        $clientPlatform = $request->header('X-Client-Platform', $deviceToken->client_platform);

        if ($this->heartbeatShouldBeUpdated($deviceToken, $heartbeatAt, $clientIp, $clientPlatform)) {
            $deviceToken->updateQuietly([
                'last_used_at' => $heartbeatAt,
                'last_ip' => $clientIp,
                'client_platform' => $clientPlatform,
            ]);
        }

        // Bind attributes to request
        $request->attributes->set('device_token', $deviceToken);
        $request->attributes->set('current_team', $deviceToken->team);
        Auth::setUser($deviceToken->user);

        return $next($request);
    }

    private function heartbeatShouldBeUpdated(
        DeviceToken $deviceToken,
        \Carbon\CarbonInterface $heartbeatAt,
        ?string $clientIp,
        ?string $clientPlatform,
    ): bool {
        if ($deviceToken->last_ip !== $clientIp || $deviceToken->client_platform !== $clientPlatform) {
            return true;
        }

        if ($deviceToken->last_used_at === null) {
            return true;
        }

        return $deviceToken->last_used_at->lte(
            $heartbeatAt->copy()->subSeconds(self::HEARTBEAT_INTERVAL_SECONDS),
        );
    }
}
