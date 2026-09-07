<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Throwable;

class LicenseValidationService
{
    /**
     * Activate and validate a license key (LemonSqueezy or Synkk Commercial Key).
     *
     * @return array{success: bool, message: string, plan?: string, license_key?: string, license?: array<string, mixed>}
     */
    public function activateLicenseKey(Team $team, string $licenseKey, string $instanceName = 'Synkk Server'): array
    {
        $licenseKey = trim($licenseKey);

        if (empty($licenseKey)) {
            return [
                'success' => false,
                'message' => 'License key cannot be empty.',
            ];
        }

        // Prevent duplicate activation across different teams
        $alreadyActive = Team::where('license_key', $licenseKey)
            ->where('id', '!=', $team->id)
            ->where('license_status', 'active')
            ->exists();

        if ($alreadyActive) {
            return [
                'success' => false,
                'message' => 'This commercial license key has already been redeemed by another workspace.',
            ];
        }

        // Check internal Synkk SaaS license keys (SYNK-PRO-XXXX-XXXX-XXXX or SYNK-CLOUD-XXXX-XXXX-XXXX)
        if (preg_match('/^SYNK-(PRO|CLOUD)-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/i', $licenseKey, $matches)) {
            $plan = strtoupper($matches[1]) === 'CLOUD' ? 'cloud' : 'pro_ltd';
            $normalizedKey = strtoupper($licenseKey);

            $team->update([
                'license_key' => $normalizedKey,
                'license_status' => 'active',
                'license_activated_at' => now(),
                'plan' => $plan,
            ]);

            $planLabel = $plan === 'cloud' ? 'Synkk Cloud Managed SaaS' : 'Pro Lifetime Deal';

            return [
                'success' => true,
                'message' => "{$planLabel} license successfully activated!",
                'plan' => $plan,
                'license_key' => $normalizedKey,
            ];
        }

        // In local/testing mode or fallback offline activation for self-hosted instances
        if (app()->environment('local', 'testing') && str_starts_with($licenseKey, 'SYNKK-LIFETIME-TEST')) {
            $team->update([
                'license_key' => $licenseKey,
                'license_status' => 'active',
                'license_activated_at' => now(),
                'plan' => 'pro_ltd',
            ]);

            return [
                'success' => true,
                'message' => 'Test Lifetime License activated successfully.',
                'plan' => 'pro_ltd',
            ];
        }

        $apiUrl = config('synkk.lemon_squeezy.api_url', 'https://api.lemonsqueezy.com/v1/licenses/activate');

        try {
            $response = Http::asForm()->post($apiUrl, [
                'license_key' => $licenseKey,
                'instance_name' => $instanceName,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $activated = $data['activated'] ?? false;

                if ($activated) {
                    $team->update([
                        'license_key' => $licenseKey,
                        'license_status' => 'active',
                        'license_activated_at' => now(),
                        'plan' => 'pro_ltd',
                    ]);

                    return [
                        'success' => true,
                        'message' => 'Lifetime License successfully activated!',
                        'license' => $data,
                        'plan' => 'pro_ltd',
                    ];
                }

                $errorMsg = $data['error'] ?? 'License activation failed or license is invalid.';

                return [
                    'success' => false,
                    'message' => $errorMsg,
                ];
            }

            return [
                'success' => false,
                'message' => 'Unable to verify license key with LemonSqueezy licensing server.',
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'License verification is temporarily unavailable. Please try again later.',
            ];
        }
    }

    /**
     * Deactivate a team's license key and revert to Community Free tier.
     *
     * @return array{success: bool, message: string}
     */
    public function deactivateLicenseKey(Team $team): array
    {
        $team->update([
            'license_status' => 'revoked',
            'plan' => 'free',
        ]);

        return [
            'success' => true,
            'message' => 'License successfully deactivated. Workspace reverted to Community Free tier.',
        ];
    }
}
