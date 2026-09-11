<?php

namespace App\Providers;

use App\Models\DeviceToken;
use App\Models\Vault;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Route::bind('vault', function (string $value): Vault {
            $request = request();
            /** @var DeviceToken|null $deviceToken */
            $deviceToken = $request->attributes->get('device_token');

            if (! $deviceToken && $request->bearerToken()) {
                $deviceToken = DeviceToken::with(['user', 'team'])
                    ->where('token_hash', hash('sha256', $request->bearerToken()))
                    ->first();
            }

            $user = $request->user() ?? $deviceToken?->user;
            $team = $deviceToken?->team ?? $user?->currentTeam ?? $user?->teams()->first();
            $userId = $deviceToken?->user_id ?? $user?->id;

            // First: If vault slug exists anywhere in database, return it (controllers enforce team_id matching)
            $existingVault = Vault::where('slug', $value)->first();
            if ($existingVault) {
                return $existingVault;
            }

            // Second: If slug does not exist anywhere and request is authenticated, auto-create for current team
            if ($team) {
                $slug = Str::slug($value);
                if (empty($slug)) {
                    $slug = 'vault-'.Str::random(6);
                }

                $name = Str::headline($value);
                if (empty($name)) {
                    $name = 'New Vault';
                }

                return Vault::firstOrCreate(
                    [
                        'team_id' => $team->id,
                        'slug' => $slug,
                    ],
                    [
                        'name' => $name,
                        'description' => "Auto-created vault for {$name}",
                        'default_permission' => 'read_write',
                        'created_by' => $userId ?? 1,
                    ]
                );
            }

            abort(404, "Vault '{$value}' not found.");
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(300)->by($request->bearerToken() ?: ($request->ip() ?? 'unknown'));
        });

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
