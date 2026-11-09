<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\AuditService;
use App\Services\PasswordRecovery;
use App\Services\RecoveryMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController
{
    public function form(string $mode = 'login')
    {
        if ($mode === 'login' && Auth::check()) {
            return redirect('/workspace');
        }

        $state = request()->session()->get('password_recovery', []);
        if ($mode === 'forgot' && request()->boolean('sent') && ! empty($state['email']) && ($state['sent_at'] ?? 0) > now()->subMinutes(15)->timestamp) {
            $mode = 'sent';
        }

        return $this->authView(['mode' => $mode, 'recoveryEmail' => $state['email'] ?? '', 'resendAfter' => max(0, 60 - (now()->timestamp - ($state['sent_at'] ?? 0)))]);
    }

    public function login(LoginRequest $r, UserRepositoryInterface $users)
    {
        $user = $users->findActiveByEmail($r->string('email')->toString());
        if (! $user || ! Hash::check($r->string('password')->toString(), $user->password)) {
            return back()->withErrors(['email' => 'Invalid credentials or inactive account.'])->onlyInput('email');
        }
        Auth::login($user, $r->boolean('remember'));
        $r->session()->regenerate();
        $r->session()->put('password_hash_web', $user->getAuthPassword());
        $r->session()->forget('password_recovery');
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
        $data = $r->validate(['email' => 'required|email|max:254']);
        if (! app(RecoveryMail::class)->ready()) {
            return redirect()->route('password.request')->withErrors(['delivery' => 'Password recovery is temporarily unavailable. Please contact your examination cell administrator.'])->onlyInput('email');
        }
        app(PasswordRecovery::class)->send($data['email']);
        $r->session()->put('password_recovery', ['email' => $data['email'], 'sent_at' => now()->timestamp]);

        return redirect()->route('password.request', ['sent' => 1])->with('status', 'If an active account matches that email, a password-reset link will arrive shortly.');
    }

    public function resetForm(Request $r, string $token)
    {
        $email = $r->input('email');
        $valid = is_string($email) && app(PasswordRecovery::class)->valid($email, $token);

        return $this->authView(['mode' => $valid ? 'reset' : 'reset-invalid', 'token' => $valid ? $token : null, 'email' => $valid ? $email : '']);
    }

    public function reset(Request $r)
    {
        $data = $r->validate(['token' => 'required|string|max:255', 'email' => 'required|email|max:254', 'password' => 'required|string|confirmed|min:12|max:255']);
        $status = app(PasswordRecovery::class)->reset(collect($data)->only(['token', 'email', 'password'])->all());
        if ($status !== Password::PASSWORD_RESET) {
            return redirect()->route('password.reset', ['token' => $data['token'], 'email' => $data['email']])
                ->withErrors(['email' => 'This reset link is invalid, expired or already used. Request a new link to continue.']);
        }
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Password updated successfully. Sign in with your new password.');
    }

    private function authView(array $data)
    {
        return response()->view('backend.auth', $data)
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
