<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PairingSessionConsumedException;
use App\Exceptions\PairingSessionExpiredException;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Vault;
use App\Services\QrPairingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Verify token and return connected device & user profile.
     */
    public function verify(Request $request, QrPairingService $pairingService): JsonResponse
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
                'access_scope' => $deviceToken->access_scope,
                'allowed_vault_ids' => $deviceToken->allowed_vault_ids,
            ],
            'server' => [
                'name' => 'Synkk Vault Sync',
                'version' => '1.0.0',
            ],
            'client' => [
                'name' => 'Synkk Vault Sync',
                'version' => '1.0.0',
            ],
            'broadcasting' => $pairingService->getBroadcastingConfig(
                $request->getHost(),
                $request->getPort(),
                $request->getScheme()
            ),
        ]);
    }

    /**
     * Create a new QR pairing session for the authenticated user/device.
     */
    public function createPairingSession(Request $request, QrPairingService $pairingService): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');

        if ($deviceToken->access_scope === 'read_only') {
            return response()->json([
                'error' => 'Permission Denied',
                'message' => 'Read-only device tokens cannot initiate device pairing sessions.',
            ], 403);
        }

        $validated = $request->validate([
            'vault' => ['nullable', 'string'],
            'access_scope' => ['nullable', 'string', 'in:full_access,read_write,read_only'],
        ]);

        $vault = null;
        if (! empty($validated['vault'])) {
            $vault = Vault::where('team_id', $deviceToken->team_id)
                ->where('slug', $validated['vault'])
                ->first();

            if (! $vault) {
                return response()->json([
                    'error' => 'Not Found',
                    'message' => 'Vault not found.',
                ], 404);
            }

            if (! $deviceToken->canAccessVault($vault->id)) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'Cannot create pairing session for an inaccessible vault.',
                ], 403);
            }
        }

        $requestedScope = $validated['access_scope'] ?? $deviceToken->access_scope;

        $session = $pairingService->createPairingSession(
            user: $deviceToken->user,
            team: $deviceToken->team,
            vault: $vault,
            accessScope: $requestedScope,
            allowedVaultIds: $vault ? [$vault->id] : $deviceToken->allowed_vault_ids,
            initiatorToken: $deviceToken,
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
        } catch (PairingSessionExpiredException|PairingSessionConsumedException $e) {
            return response()->json([
                'error' => 'Pairing session expired or already consumed.',
                'message' => $e->getMessage(),
            ], 410);
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
