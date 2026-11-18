<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordRecovery
{
    public function valid(string $email, #[\SensitiveParameter] string $token): bool
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || ! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return false;
        }
        $user = User::where('email', $email)->where('active', true)->first();

        return $user && Password::tokenExists($user, $token);
    }

    public function send(string $email): void
    {
        Password::sendResetLink(['email' => $email, 'active' => true], function ($user, #[\SensitiveParameter] $token) {
            try {
                $user->sendPasswordResetNotification($token);
                app(AuditService::class)->record('auth.reset_link_requested', $user);
            } catch (\Throwable) {
                // Keep the same public response for every address. Remove only this
                // token, so a delivery failure cannot leave a logged/usable secret.
                if (Password::tokenExists($user, $token)) {
                    Password::deleteToken($user);
                }
                app(AuditService::class)->record('auth.reset_link_delivery_failed', $user, ['channel' => 'email']);
            }
        });
    }

    public function reset(#[\SensitiveParameter] array $data): string
    {
        $changed = null;
        $status = DB::transaction(function () use ($data, &$changed) {
            // Serialize resets for this identity: two requests cannot both consume a token.
            $locked = User::where('email', $data['email'])->where('active', true)->lockForUpdate()->first();
            if (! $locked) {
                return Password::INVALID_TOKEN;
            }

            return Password::reset($data + ['active' => true], function ($user, #[\SensitiveParameter] $password) use (&$changed) {
                if (Hash::check($password, $user->password)) {
                    throw ValidationException::withMessages(['password' => 'Choose a password different from your current password.']);
                }
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                Settings::set('auth.password_reset.'.$user->id, true);
                app(AuditService::class)->record('auth.password_reset', $user, ['role' => $user->role]);
                event(new PasswordReset($user));
                $changed = $user;
            });
        });
        if ($status === Password::PASSWORD_RESET && $changed && app(RecoveryMail::class)->ready()) {
            try {
                $changed->notify(new PasswordChanged);
            } catch (\Throwable) {
                // A receipt failure must never undo a successful reset.
                app(AuditService::class)->record('auth.password_change_notice_failed', $changed, ['channel' => 'email']);
            }
        }

        return $status;
    }

    public static function url(User $user, #[\SensitiveParameter] string $token): string
    {
        return rtrim(config('app.url'), '/').route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false);
    }
}
