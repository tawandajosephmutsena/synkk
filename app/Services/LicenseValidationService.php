<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Throwable;

class LicenseValidationService
{
    /**
     * Activate and validate a LemonSqueezy license key ($49 Lifetime License).
     *
     * @return array{success: bool, message: string, license?: array<string, mixed>}
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
                    ]);

                    return [
                        'success' => true,
                        'message' => 'Lifetime License successfully activated!',
                        'license' => $data,
                    ];
                }

                $errorMsg = $data['error'] ?? 'License activation failed or license is invalid.';

                return [
                    'success' => false,
                    'message' => $errorMsg,
                ];
            }

            // In local/testing mode or fallback offline activation for self-hosted instances
            if (app()->environment('local', 'testing') && str_starts_with($licenseKey, 'SYNKK-LIFETIME-TEST')) {
                $team->update([
                    'license_key' => $licenseKey,
                    'license_status' => 'active',
                    'license_activated_at' => now(),
                ]);

                return [
                    'success' => true,
                    'message' => 'Test Lifetime License activated successfully.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Unable to verify license key with LemonSqueezy licensing server.',
            ];
        } catch (Throwable $e) {
            // Testing fallback
            if (app()->environment('local', 'testing') && str_starts_with($licenseKey, 'SYNKK-LIFETIME-TEST')) {
                $team->update([
                    'license_key' => $licenseKey,
                    'license_status' => 'active',
                    'license_activated_at' => now(),
                ]);

                return [
                    'success' => true,
                    'message' => 'Test Lifetime License activated successfully.',
                ];
            }

            return [
                'success' => false,
                'message' => 'License verification server connection error: '.$e->getMessage(),
            ];
        }
    }
}
