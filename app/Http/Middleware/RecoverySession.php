<?php

namespace App\Http\Middleware;

use App\Services\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Also retire pre-upgrade sessions that have never stored a password hash. */
class RecoverySession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $request->hasSession() && ! $request->session()->has('password_hash_web')
            && ! Auth::guard()->viaRemember() && Settings::get('auth.password_reset.'.$user->id, false)) {
            Auth::guard()->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Your password was changed. Please sign in again.');
        }

        return $next($request);
    }
}
