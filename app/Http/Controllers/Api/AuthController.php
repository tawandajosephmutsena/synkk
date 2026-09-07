<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\QrPairingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Verify token and return connected device & user profile.
     */
    public function verify(Request $request): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');

        return response()->json([
            'status' => 'ok',
            'user' => [
                'id' => $deviceToken->user->id,
                'name' => $deviceToken->user->name,
                'email' => $deviceToken->user->email,
            ],
            'team' => [
                'id' => $deviceToken->team->id,
                'name' => $deviceToken->team->name,
                'slug' => $deviceToken->team->slug,
            ],
            'device' => [
                'id' => $deviceToken->id,
                'name' => $deviceToken->name,
                'platform' => $deviceToken->client_platform,
                'last_used_at' => $deviceToken->last_used_at?->toIso8601String(),
            ],
            'server' => [
                'name' => 'Synkk Vault Sync',
                'version' => '1.0.0',
            ],
        ]);
    }

    /**
     * Create a new QR pairing session for the authenticated user/device.
     */
    public function createPairingSession(Request $request, QrPairingService $pairingService): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');

        $session = $pairingService->createPairingSession(
            user: $deviceToken->user,
            team: $deviceToken->team
        );

        return response()->json([
            'status' => 'ok',
            'session' => $session,
        ]);
    }

    /**
     * Mobile device exchanges QR pairing session for permanent device token.
     */
    public function pairingExchange(Request $request, QrPairingService $pairingService): JsonResponse
    {
        $validated = $request->validate([
            'session' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'in:ios,android,mac,windows,linux'],
        ]);

        try {
            $result = $pairingService->exchange(
                sessionId: $validated['session'],
                deviceName: $validated['device_name'],
                platform: $validated['platform'] ?? 'ios'
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Check pairing session status (polled by browser modal).
     */
    public function pairingStatus(Request $request, QrPairingService $pairingService): JsonResponse
    {
        $validated = $request->validate([
            'session' => ['required', 'string'],
        ]);

        $status = $pairingService->checkStatus($validated['session']);

        return response()->json($status);
    }
}
