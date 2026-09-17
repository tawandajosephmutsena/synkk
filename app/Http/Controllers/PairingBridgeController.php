<?php

namespace App\Http\Controllers;

use App\Services\QrPairingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PairingBridgeController extends Controller
{
    /**
     * Display the mobile pairing bridge page with auto-redirect to Obsidian.
     */
    public function show(Request $request, QrPairingService $pairingService): View
    {
        $sessionId = (string) $request->query('session', '');
        $rawServer = $request->query('server');
        $serverUrl = is_string($rawServer) && $rawServer !== '' ? $rawServer : url('/api/v1');
        $vaultSlug = (string) $request->query('vault', '');
        $version = (string) $request->query('v', '2');

        $sessionStatus = ! empty($sessionId)
            ? $pairingService->checkStatus($sessionId)
            : ['status' => 'invalid', 'claimed_device_name' => null, 'device_token_id' => null];

        $obsidianUrl = '';
        if (! empty($sessionId) && $sessionStatus['status'] === 'pending') {
            $params = [
                'server' => $serverUrl,
                'session' => $sessionId,
                'v' => $version,
            ];
            if (! empty($vaultSlug)) {
                $params['vault'] = $vaultSlug;
            }

            $obsidianUrl = 'obsidian://synkk-pair?'.http_build_query($params);
        }

        return view('pages.pairing.bridge', [
            'sessionId' => $sessionId,
            'serverUrl' => $serverUrl,
            'vaultSlug' => $vaultSlug,
            'sessionStatus' => $sessionStatus,
            'obsidianUrl' => $obsidianUrl,
        ]);
    }
}
