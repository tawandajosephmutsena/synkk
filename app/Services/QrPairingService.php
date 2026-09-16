<?php

namespace App\Services;

use App\Exceptions\PairingSessionConsumedException;
use App\Exceptions\PairingSessionExpiredException;
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

class QrPairingService
{
    public const SESSION_TTL_SECONDS = 600; // 10 minutes

    /**
     * Create a new temporary QR pairing session and render high-contrast SVG.
     *
     * @param  array<int>|null  $allowedVaultIds
     * @return array{
     *     session: string,
     *     session_id: string,
     *     pairing_url: string,
     *     web_pairing_url: string,
     *     qr_svg: string,
     *     json_payload: string,
     *     payload: array<string, mixed>,
     *     expires_at: int
     * }
     */
    public function createPairingSession(
        User $user,
        Team $team,
        Vault|string|null $vault = null,
        string $accessScope = 'read_write',
        ?array $allowedVaultIds = null,
        ?DeviceToken $initiatorToken = null,
    ): array {
        $sessionId = 'synkk_pair_'.Str::random(32);

        $vaultModel = null;
        if (is_string($vault)) {
            $vaultModel = Vault::where('team_id', $team->id)->where('slug', $vault)->first();
            $vaultSlug = $vault;
        } elseif ($vault instanceof Vault) {
            $vaultModel = $vault;
            $vaultSlug = $vault->slug;
        } else {
            $vaultModel = $team->vaults()->first();
            $vaultSlug = $vaultModel instanceof Vault ? $vaultModel->slug : '';
        }

        // Scope inheritance and security constraints
        if ($initiatorToken) {
            if ($initiatorToken->access_scope === 'read_only') {
                abort(403, 'Read-only device tokens cannot initiate device pairing sessions.');
            }

            // Cap scope to equal-or-narrower than initiator
            if ($initiatorToken->access_scope === 'read_write' && $accessScope === 'full_access') {
                $accessScope = 'read_write';
            }

            // Vault-level restrictions
            if ($initiatorToken->allowed_vault_ids !== null) {
                if ($vaultModel && ! in_array($vaultModel->id, $initiatorToken->allowed_vault_ids, true)) {
                    abort(403, 'Cannot create pairing session for a vault this token cannot access.');
                }
                $allowedVaultIds = $vaultModel ? [$vaultModel->id] : $initiatorToken->allowed_vault_ids;
            }
        }

        if ($vaultModel && $allowedVaultIds === null) {
            $allowedVaultIds = [$vaultModel->id];
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
            'access_scope' => $accessScope,
        ];

        $jsonPayload = json_encode($payloadData, JSON_UNESCAPED_SLASHES) ?: '{}';
        // Format obsidian protocol URI for genuine one-scan pairing
        $pairingUrl = 'obsidian://synkk-pair?server='.urlencode($serverUrl).'&session='.urlencode($sessionId).'&vault='.urlencode($vaultSlug).'&v=2';
        $webPairingUrl = url('/pair').'?session='.urlencode($sessionId).'&server='.urlencode($serverUrl).'&v=2'.($vaultSlug ? '&vault='.urlencode($vaultSlug) : '');

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
            'access_scope' => $accessScope,
            'allowed_vault_ids' => $allowedVaultIds,
            'status' => 'waiting',
            'claimed_device_name' => null,
            'device_token_id' => null,
            'created_at' => time(),
        ], now()->addSeconds(self::SESSION_TTL_SECONDS));

        return [
            'session' => $sessionId,
            'session_id' => $sessionId,
            'pairing_url' => $pairingUrl,
            'web_pairing_url' => $webPairingUrl,
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
     *     access_scope: string,
     *     user: array{id: int, name: string, email: string},
     *     team: array{id: int, name: string, slug: string}
     * }
     */
    public function exchange(string $sessionId, string $deviceName, string $platform = 'mobile'): array
    {
        $cacheKey = "pairing_session_{$sessionId}";
        $lockKey = "pairing_lock_{$sessionId}";

        return Cache::lock($lockKey, 10)->block(5, function () use ($cacheKey, $deviceName, $platform) {
            /** @var array{user_id: int, team_id: int, status?: string, server_url: string, vault_slug: string, access_scope?: string, allowed_vault_ids?: array<int>|null, claimed_device_name?: string|null, device_token_id?: int|null, claimed_at?: int}|null $session */
            $session = Cache::get($cacheKey);

            if (! $session) {
                throw new PairingSessionExpiredException('Pairing session has expired or is invalid. Please generate a new QR code.');
            }

            if (($session['status'] ?? '') === 'claimed') {
                throw new PairingSessionConsumedException('Pairing session has already been used.');
            }

            $user = User::query()->whereKey($session['user_id'])->firstOrFail();
            $team = Team::query()->whereKey($session['team_id'])->firstOrFail();

            $tokenResult = DeviceToken::createToken(
                user: $user,
                team: $team,
                name: $deviceName,
                platform: in_array($platform, ['ios', 'android', 'mac', 'windows', 'linux'], true) ? $platform : 'ios'
            );

            $tokenResult['device_token']->update([
                'access_scope' => $session['access_scope'] ?? 'read_write',
                'allowed_vault_ids' => $session['allowed_vault_ids'] ?? null,
            ]);

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
                'access_scope' => $tokenResult['device_token']->access_scope,
                'broadcasting' => $this->getBroadcastingConfig(),
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
        });
    }

    /**
     * Get the public broadcasting / Reverb connection details for client devices.
     *
     * @return array{driver: string, key: string, host: string, port: int, scheme: string}
     */
    public function getBroadcastingConfig(?string $requestHost = null, int|string|null $requestPort = null, ?string $requestScheme = null): array
    {
        $default = (string) config('broadcasting.default', 'reverb');
        $reverbKey = (string) config('broadcasting.connections.reverb.key', '');
        $reverbHost = config('broadcasting.connections.reverb.options.host') ?: ($requestHost ?: 'localhost');
        $reverbPort = (int) (config('broadcasting.connections.reverb.options.port') ?: ($requestPort ? (int) $requestPort : 8080));
        $reverbScheme = (string) (config('broadcasting.connections.reverb.options.scheme') ?: ($requestScheme ?: 'http'));

        return [
            'driver' => $default,
            'key' => $reverbKey,
            'host' => (string) $reverbHost,
            'port' => $reverbPort,
            'scheme' => $reverbScheme,
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

        if (($session['status'] ?? '') === 'claimed') {
            if (! empty($session['device_token_id'])) {
                $tokenExists = DeviceToken::whereKey($session['device_token_id'])->exists();
                if (! $tokenExists) {
                    return [
                        'status' => 'revoked',
                        'claimed_device_name' => $session['claimed_device_name'] ?? null,
                        'device_token_id' => $session['device_token_id'] ?? null,
                    ];
                }
            }

            return [
                'status' => 'paired',
                'claimed_device_name' => $session['claimed_device_name'] ?? null,
                'device_token_id' => $session['device_token_id'] ?? null,
            ];
        }

        return [
            'status' => 'pending',
            'claimed_device_name' => null,
            'device_token_id' => null,
        ];
    }
}
