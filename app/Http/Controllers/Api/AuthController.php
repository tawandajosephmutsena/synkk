<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
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
}
