<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\AuditService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController
{
    public function form(string $mode = 'login')
    {
        return view('backend.auth', ['mode' => $mode]);
    }

    public function login(LoginRequest $r, UserRepositoryInterface $users)
    {
        $user = $users->findActiveByEmail($r->string('email')->toString());
        if (! $user || ! Hash::check($r->string('password')->toString(), $user->password)) {
            return back()->withErrors(['email' => 'Invalid credentials or inactive account.'])->onlyInput('email');
        }
        Auth::login($user, $r->boolean('remember'));
        $r->session()->regenerate();
        app(AuditService::class)->record('auth.login', $r->user());

        return redirect('/'.$r->user()->role.'/'.$r->user()->role.'-dashboard');
    }

    public function logout(Request $r)
    {
        app(AuditService::class)->record('auth.logout', $r->user());
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/sign-in');
    }

    public function forgot(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        Password::sendResetLink($r->only('email'));

        return back()->with('status', 'If this account exists, a reset link has been sent through the configured mail transport.');
    }

    public function resetForm(Request $r, string $token)
    {
        return view('backend.auth', ['mode' => 'reset', 'token' => $token, 'email' => $r->email]);
    }

    public function reset(Request $r)
    {
        $r->validate(['token' => 'required', 'email' => 'required|email', 'password' => 'required|confirmed|min:12|max:255']);
        $s = Password::reset($r->only('email', 'password', 'password_confirmation', 'token'), function ($u, $p) {
            $u->forceFill(['password' => Hash::make($p), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($u));
        });

        return $s === Password::PASSWORD_RESET ? redirect('/sign-in')->with('status', __($s)) : back()->withErrors(['email' => __($s)]);
    }
}
