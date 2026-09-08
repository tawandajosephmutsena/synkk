<?php

namespace App\Http\Middleware;

use App\Models\DeviceToken;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\Request;
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
            if (Auth::guard('web')->check()) {
                /** @var User $user */
                $user = Auth::guard('web')->user();
                $team = $user->currentTeam ?? $user->teams()->first();

                if ($team) {
                    $deviceToken = DeviceToken::firstOrCreate(
                        [
                            'user_id' => $user->id,
                            'team_id' => $team->id,
                            'name' => 'Web Browser Session',
                        ],
                        [
                            'token_hash' => hash('sha256', 'web_session_'.$user->id.'_'.(string) config('app.key')),
                            'token_preview' => 'web_sess...',
                            'access_scope' => 'read_write',
                        ]
                    );

                    $deviceToken->setRelation('user', $user);
                    $deviceToken->setRelation('team', $team);

                    $request->attributes->set('device_token', $deviceToken);
                    $request->setUserResolver(fn () => $user);
                    Auth::setUser($user);

                    return $next($request);
                }
            }

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

        // Remote Device Wipe Check
        if ($deviceToken->is_wiped) {
            return response()->json([
                'error' => 'Device Wiped',
                'action' => 'remote_wipe',
                'message' => 'This device token has been remotely wiped by an enterprise administrator.',
            ], 410);
        }

        // IP Range / Subnet Whitelist Check
        if (! $deviceToken->isIpAllowed($request->ip())) {
            return response()->json([
                'error' => 'IP Access Restricted',
                'message' => "Access from IP {$request->ip()} is not authorized for this device.",
            ], 403);
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
        $request->setUserResolver(fn () => $deviceToken->user);
        Auth::setUser($deviceToken->user);

        return $next($request);
    }

    private function heartbeatShouldBeUpdated(
        DeviceToken $deviceToken,
        CarbonInterface $heartbeatAt,
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
