<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class QrPairingService
{
    public const SESSION_TTL_SECONDS = 600; // 10 minutes

    /**
     * Create a new temporary QR pairing session and render high-contrast SVG.
     *
     * @return array{
     *     session: string,
     *     session_id: string,
     *     pairing_url: string,
     *     qr_svg: string,
     *     json_payload: string,
     *     payload: array<string, mixed>,
     *     expires_at: int
     * }
     */
    public function createPairingSession(User $user, Team $team, Vault|string|null $vault = null): array
    {
        $sessionId = 'synkk_pair_'.Str::random(32);
        if (is_string($vault)) {
            $vaultSlug = $vault;
        } elseif ($vault instanceof Vault) {
            $vaultSlug = $vault->slug;
        } else {
            $firstVault = $team->vaults()->first();
            $vaultSlug = $firstVault instanceof Vault ? $firstVault->slug : '';
        }
        $serverUrl = url('/api/v1');

        $payloadData = [
            'v' => 2,
            'type' => 'synkk-pairing-session',
            'session' => $sessionId,
            'server' => $serverUrl,
            'vault' => $vaultSlug,
            'team' => $team->slug,
            'user' => $user->name,
        ];

        $jsonPayload = json_encode($payloadData, JSON_UNESCAPED_SLASHES) ?: '{}';
        $pairingUrl = 'synkk://pair?server='.urlencode($serverUrl).'&session='.urlencode($sessionId).'&vault='.urlencode($vaultSlug);

        // Render QR Code SVG using BaconQrCode
        $renderer = new ImageRenderer(
            new RendererStyle(220, 1, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(24, 24, 27))),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $fullSvg = $writer->writeString($pairingUrl);
        $cleanSvg = trim(substr($fullSvg, strpos($fullSvg, "\n") + 1));

        // Store session state in cache
        Cache::put("pairing_session_{$sessionId}", [
            'session_id' => $sessionId,
            'user_id' => $user->id,
            'team_id' => $team->id,
            'vault_slug' => $vaultSlug,
            'server_url' => $serverUrl,
            'status' => 'waiting',
            'claimed_device_name' => null,
            'device_token_id' => null,
            'created_at' => time(),
        ], now()->addSeconds(self::SESSION_TTL_SECONDS));

        return [
            'session' => $sessionId,
            'session_id' => $sessionId,
            'pairing_url' => $pairingUrl,
            'qr_svg' => $cleanSvg,
            'json_payload' => $jsonPayload,
            'payload' => $payloadData,
            'expires_at' => time() + self::SESSION_TTL_SECONDS,
        ];
    }

    /**
     * Exchange a temporary pairing session for an authenticated permanent device token.
     *
     * @return array{
     *     status: string,
     *     token: string,
     *     plain_token: string,
     *     device_id: int,
     *     server_url: string,
     *     vault_slug: string,
     *     device_name: string,
     *     team_slug: string,
     *     user: array{id: int, name: string, email: string},
     *     team: array{id: int, name: string, slug: string}
     * }
     */
    public function exchange(string $sessionId, string $deviceName, string $platform = 'mobile'): array
    {
        $cacheKey = "pairing_session_{$sessionId}";
        /** @var array{user_id: int, team_id: int, status?: string, server_url: string, vault_slug: string, claimed_device_name?: string|null, device_token_id?: int|null, claimed_at?: int}|null $session */
        $session = Cache::get($cacheKey);

        if (! $session) {
            throw new RuntimeException('Pairing session has expired or is invalid. Please generate a new QR code.');
        }

        if (($session['status'] ?? '') === 'claimed') {
            throw new RuntimeException('Pairing session has already been used.');
        }

        $user = User::query()->whereKey($session['user_id'])->firstOrFail();
        $team = Team::query()->whereKey($session['team_id'])->firstOrFail();

        $tokenResult = DeviceToken::createToken(
            user: $user,
            team: $team,
            name: $deviceName,
            platform: in_array($platform, ['ios', 'android', 'mac', 'windows', 'linux']) ? $platform : 'ios'
        );

        $session['status'] = 'claimed';
        $session['device_token_id'] = $tokenResult['device_token']->id;
        $session['claimed_device_name'] = $deviceName;
        $session['claimed_at'] = time();
        Cache::put($cacheKey, $session, now()->addSeconds(self::SESSION_TTL_SECONDS));

        return [
            'status' => 'paired',
            'token' => $tokenResult['plain_token'],
            'plain_token' => $tokenResult['plain_token'],
            'device_id' => $tokenResult['device_token']->id,
            'server_url' => $session['server_url'],
            'vault_slug' => $session['vault_slug'],
            'device_name' => $deviceName,
            'team_slug' => $team->slug,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
            ],
        ];
    }

    /**
     * Check pairing session status (polled by browser modal).
     *
     * @return array{status: string, claimed_device_name: string|null, device_token_id: int|null}
     */
    public function checkStatus(string $sessionId): array
    {
        $session = Cache::get("pairing_session_{$sessionId}");

        if (! $session) {
            return ['status' => 'expired', 'claimed_device_name' => null, 'device_token_id' => null];
        }

        $status = ($session['status'] ?? '') === 'waiting'
            ? 'pending'
            : (($session['status'] ?? '') === 'claimed' ? 'paired' : ($session['status'] ?? 'pending'));

        return [
            'status' => $status,
            'claimed_device_name' => $session['claimed_device_name'] ?? null,
            'device_token_id' => $session['device_token_id'] ?? null,
        ];
    }
}
