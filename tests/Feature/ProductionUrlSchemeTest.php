<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

test('production login uses the configured origin scheme', function (string $applicationUrl) {
    $originalEnvironment = app()->environment();
    $originalUrl = config('app.url');

    app()->detectEnvironment(fn (): string => 'production');
    config()->set('app.url', $applicationUrl);
    URL::forceScheme(null);

    try {
        app()->getProvider(AppServiceProvider::class)->boot();

        $this->withServerVariables(['HTTP_HOST' => '127.0.0.1:8000'])
            ->get('http://127.0.0.1:8000/login')
            ->assertOk()
            ->assertSee('action="'.$applicationUrl.'/login"', escape: false);
    } finally {
        app()->detectEnvironment(fn (): string => $originalEnvironment);
        config()->set('app.url', $originalUrl);
        URL::forceScheme(null);
        DB::prohibitDestructiveCommands(false);
    }
})->with([
    'local loopback HTTP' => ['http://127.0.0.1:8000'],
    'public reverse proxy HTTPS' => ['https://127.0.0.1:8000'],
]);
