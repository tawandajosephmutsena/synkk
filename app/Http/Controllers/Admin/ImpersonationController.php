<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ImpersonationController extends Controller
{
    /**
     * Start impersonating a tenant user.
     */
    public function start(Request $request, User $user): RedirectResponse
    {
        $superAdmin = $request->user();

        if ($superAdmin->id === $user->id) {
            return back()->with('error', __('You cannot impersonate yourself.'));
        }

        session(['impersonator_id' => $superAdmin->id]);

        Log::warning("Super Admin [{$superAdmin->id}: {$superAdmin->email}] started impersonating User [{$user->id}: {$user->email}]");

        Auth::login($user);

        $team = $user->currentTeam ?? $user->personalTeam() ?? $user->teams->first();

        if ($team) {
            return redirect()->route('dashboard', ['current_team' => $team->slug])
                ->with('status', __('Now impersonating :name (:email).', ['name' => $user->name, 'email' => $user->email]));
        }

        return redirect()->route('dashboard');
    }

    /**
     * Stop impersonating and restore the super admin session.
     */
    public function stop(Request $request): RedirectResponse
    {
        $impersonatorId = session('impersonator_id');

        if (! $impersonatorId) {
            return redirect()->route('home');
        }

        $superAdmin = User::find($impersonatorId);

        if (! $superAdmin || ! $superAdmin->isSuperAdmin()) {
            session()->forget('impersonator_id');
            Auth::logout();

            return redirect()->route('login');
        }

        session()->forget('impersonator_id');
        Auth::login($superAdmin);

        Log::info("Super Admin [{$superAdmin->id}: {$superAdmin->email}] stopped impersonation.");

        return redirect()->route('admin.dashboard')
            ->with('status', __('Returned to Super Admin session.'));
    }
}
