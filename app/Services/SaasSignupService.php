<?php

namespace App\Services;

use App\Actions\Teams\CreateTeam;
use App\Models\PendingSaasCheckout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaasSignupService
{
    public function __construct(
        private DodoPaymentsService $payments,
        private CreateTeam $createTeam,
    ) {}

    /**
     * Create a pending signup and its hosted Dodo checkout.
     *
     * @param  array{name: string, workspace_name: string, email: string, plan: string}  $data
     * @return array{pending: PendingSaasCheckout, token: string, checkout: array{session_id: string, checkout_url: string}}
     *
     * @throws \Throwable
     */
    public function startCheckout(array $data): array
    {
        $token = Str::random(64);
        $pending = PendingSaasCheckout::create([
            'token_hash' => hash('sha256', $token),
            'name' => $data['name'],
            'workspace_name' => $data['workspace_name'],
            'email' => Str::lower($data['email']),
            'plan' => $data['plan'],
            'status' => 'pending',
            'expires_at' => now()->addHours((int) config('synkk.saas_signup.pending_checkout_ttl_hours', 24)),
        ]);

        try {
            $checkout = $this->payments->createSignupCheckoutSession(
                $pending,
                $pending->name,
                $pending->email,
                $token,
            );
        } catch (\Throwable $exception) {
            $pending->delete();

            throw $exception;
        }

        $pending->forceFill([
            'checkout_session_id' => $checkout['session_id'],
        ])->save();

        return [
            'pending' => $pending,
            'token' => $token,
            'checkout' => $checkout,
        ];
    }

    public function findByToken(string $token): ?PendingSaasCheckout
    {
        return PendingSaasCheckout::findByToken($token);
    }

    /**
     * Create the account and paid workspace after a confirmed subscription.
     *
     * @param  array{password: string}  $data
     *
     * @throws ValidationException
     */
    public function completeSignup(PendingSaasCheckout $pending, array $data): User
    {
        return DB::transaction(function () use ($pending, $data): User {
            $pending = PendingSaasCheckout::query()
                ->lockForUpdate()
                ->find($pending->id);

            if (! $pending || ! $pending->isReadyToComplete()) {
                throw ValidationException::withMessages([
                    'billing' => __('Your payment is still being confirmed. Please refresh and try again.'),
                ]);
            }

            if (User::query()->where('email', $pending->email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => __('An account already exists for this email address. Please log in instead.'),
                ]);
            }

            $user = User::create([
                'name' => $pending->name,
                'email' => $pending->email,
                'password' => $data['password'],
            ]);

            $team = $this->createTeam->handle($user, $pending->workspace_name, isPersonal: true);
            $team->forceFill([
                'plan' => $pending->plan,
                'license_status' => 'active',
                'license_activated_at' => now(),
                'billing_provider' => 'dodo',
                'billing_customer_id' => $pending->billing_customer_id,
                'billing_subscription_id' => $pending->billing_subscription_id,
                'billing_product_id' => $pending->billing_product_id,
                'billing_status' => $pending->billing_status,
                'billing_next_billing_at' => $pending->billing_next_billing_at,
                'billing_last_event_at' => $pending->billing_last_event_at,
                'billing_previous_plan' => null,
            ])->save();

            $pending->forceFill([
                'status' => 'consumed',
                'user_id' => $user->id,
                'team_id' => $team->id,
                'consumed_at' => now(),
            ])->save();

            return $user->fresh();
        });
    }
}
