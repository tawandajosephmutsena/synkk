<?php

namespace App\Providers;

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
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
            $bearerToken = $request->bearerToken();

            if (! $deviceToken instanceof DeviceToken && filled($bearerToken)) {
                $deviceToken = DeviceToken::with('team')
                    ->where('token_hash', hash('sha256', $bearerToken))
                    ->first();

                abort_unless($deviceToken instanceof DeviceToken, 401, 'Unauthenticated.');
            }

            $user = $request->user();
            $routeTeam = $request->route('current_team');
            $team = match (true) {
                $deviceToken instanceof DeviceToken => $deviceToken->team,
                $routeTeam instanceof Team => $routeTeam,
                is_string($routeTeam) => Team::where('slug', $routeTeam)->first(),
                $user instanceof User => $user->currentTeam ?? $user->teams()->first(),
                default => null,
            };

            if ($team === null) {
                return Vault::where('slug', $value)->firstOrFail();
            }

            return Vault::query()
                ->where('team_id', $team->id)
                ->where('slug', $value)
                ->firstOrFail();
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
