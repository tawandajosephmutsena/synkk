<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

if (app()->environment('local')) {
    Route::get('dev-login', function () {
        auth()->loginUsingId(1);

        $team = auth()->user()->currentTeam ?? auth()->user()->teams->first();

        return redirect()->route('dashboard', ['current_team' => $team->slug]);
    });
}

Route::get('dashboard', function () {
    if (auth()->check()) {
        $team = auth()->user()->currentTeam ?? auth()->user()->personalTeam() ?? auth()->user()->teams->first();
        if ($team) {
            return redirect()->route('dashboard', ['current_team' => $team->slug]);
        }
    }

    return redirect()->route('login');
});

Route::get('vaults', function () {
    if (auth()->check()) {
        $team = auth()->user()->currentTeam ?? auth()->user()->personalTeam() ?? auth()->user()->teams->first();
        if ($team) {
            return redirect()->route('vaults.index', ['current_team' => $team->slug]);
        }
    }

    return redirect()->route('login');
});

Route::get('devices', function () {
    if (auth()->check()) {
        $team = auth()->user()->currentTeam ?? auth()->user()->personalTeam() ?? auth()->user()->teams->first();
        if ($team) {
            return redirect()->route('devices.index', ['current_team' => $team->slug]);
        }
    }

    return redirect()->route('login');
});

Route::get('docs', function () {
    if (auth()->check()) {
        $team = auth()->user()->currentTeam ?? auth()->user()->personalTeam() ?? auth()->user()->teams->first();
        if ($team) {
            return redirect()->route('docs', ['current_team' => $team->slug]);
        }
    }

    return redirect()->route('login');
})->name('docs.redirect');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->scopeBindings()
    ->group(function () {
        Route::livewire('dashboard', 'pages::dashboard.index')->name('dashboard');
        Route::livewire('vaults', 'pages::vaults.index')->name('vaults.index');
        Route::livewire('vaults/{vault}', 'pages::vaults.show')->name('vaults.show');
        Route::livewire('devices', 'pages::devices.index')->name('devices.index');
        Route::livewire('docs', 'pages::docs.index')->name('docs');
    });

use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Middleware\EnsureSuperAdmin;

Route::prefix('admin')
    ->middleware(['auth', 'verified', EnsureSuperAdmin::class])
    ->group(function () {
        Route::livewire('/', 'pages::admin.dashboard')->name('admin.dashboard');
        Route::livewire('dashboard', 'pages::admin.dashboard');
        Route::post('impersonate/{user}', [ImpersonationController::class, 'start'])->name('admin.impersonate');
    });

Route::post('admin/stop-impersonation', [ImpersonationController::class, 'stop'])
    ->middleware(['auth'])
    ->name('admin.stop-impersonation');

require __DIR__.'/settings.php';
