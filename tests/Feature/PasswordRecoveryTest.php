<?php

namespace Tests\Feature;

use App\Models as M;
use App\Notifications\PasswordChanged;
use App\Services\PasswordRecovery;
use App\Services\RecoveryMail;
use App\Services\Settings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Fixture;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use Fixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'smtp', 'mail.from.address' => 'sender@example.test', 'mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.username' => 'test', 'mail.mailers.smtp.password' => 'test']);
        Notification::fake();
    }

    public function test_request_is_private_for_missing_inactive_and_existing_accounts(): void
    {
        $f = $this->fixture();
        $f['other']->update(['active' => false]);
        foreach ([$f['student']->email, $f['other']->email, 'missing@example.test'] as $email) {
            $this->post('/forgot-password', ['email' => $email])->assertRedirect('/forgot-password?sent=1')->assertSessionHas('status', 'If an active account matches that email, a password-reset link will arrive shortly.');
            $this->get('/forgot-password?sent=1')->assertOk()->assertSee('Check Your Inbox');
        }
        Notification::assertSentTo($f['student'], ResetPassword::class);
        Notification::assertNotSentTo($f['other'], ResetPassword::class);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $f['other']->email]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'missing@example.test']);
    }

    public function test_each_role_can_request_reset_and_sign_in_with_a_new_password(): void
    {
        $f = $this->fixture();
        foreach (['admin', 'teacher', 'student'] as $role) {
            $user = $f[$role];
            $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
            $notification = Notification::sent($user, ResetPassword::class)->last();
            $token = $notification->token;
            $stored = \DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
            $this->assertNotSame($token, $stored);
            $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))->assertOk()->assertSee('Set Your Password')->assertHeader('Referrer-Policy', 'no-referrer');
            $new = 'ChangedPrivatePassword2026!';
            $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => $new, 'password_confirmation' => $new])->assertRedirect('/sign-in')->assertSessionHas('status');
            $this->assertGuest();
            $this->assertTrue(Hash::check($new, $user->fresh()->password));
            $this->assertFalse(Hash::check('TestPassword2026!', $user->fresh()->password));
            $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
            Notification::assertSentTo($user, PasswordChanged::class);
            $this->post('/sign-in', ['email' => $user->email, 'password' => $new])->assertRedirect('/'.$role.'/'.$role.'-dashboard');
            $this->post('/logout');
            $this->assertStringNotContainsString($new, M\AuditLog::all()->toJson());
            $this->assertStringNotContainsString($token, M\AuditLog::all()->toJson());
        }
    }

    public function test_invalid_expired_replaced_and_used_tokens_have_a_friendly_recovery_path(): void
    {
        $f = $this->fixture();
        $token = Password::createToken($f['student']);
        foreach (['invalid', str_repeat('a', 64)] as $bad) {
            $this->get('/reset-password/'.$bad.'?email='.urlencode($f['student']->email))->assertOk()->assertSee('Link Unavailable')->assertDontSee('name="password"', false);
        }
        $this->travel(61)->minutes();
        $this->get('/reset-password/'.$token.'?email='.urlencode($f['student']->email))->assertSee('Link Unavailable');
        $this->travelBack();
        $newToken = Password::createToken($f['student']);
        $this->assertFalse(app(PasswordRecovery::class)->valid($f['student']->email, $token));
        $this->assertTrue(app(PasswordRecovery::class)->valid($f['student']->email, $newToken));
        $data = ['email' => $f['student']->email, 'token' => $newToken, 'password' => 'PrivateNewPassword2026!', 'password_confirmation' => 'PrivateNewPassword2026!'];
        $this->post('/reset-password', $data)->assertRedirect('/sign-in');
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->assertFalse(app(PasswordRecovery::class)->valid($f['student']->email, $newToken));
    }

    public function test_password_validation_and_disabled_accounts_cannot_change_credentials(): void
    {
        $f = $this->fixture();
        $token = Password::createToken($f['student']);
        $base = ['email' => $f['student']->email, 'token' => $token];
        foreach ([['short', 'short'], ['GoodPassword2026!', 'DifferentPassword2026!'], ['TestPassword2026!', 'TestPassword2026!']] as [$password, $confirmation]) {
            $this->post('/reset-password', $base + ['password' => $password, 'password_confirmation' => $confirmation])->assertSessionHasErrors('password');
            $this->assertArrayNotHasKey('password', session()->getOldInput());
            $this->assertArrayNotHasKey('password_confirmation', session()->getOldInput());
        }
        $f['student']->update(['active' => false]);
        $this->post('/reset-password', $base + ['password' => 'PrivateNewPassword2026!', 'password_confirmation' => 'PrivateNewPassword2026!'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('TestPassword2026!', $f['student']->fresh()->password));
        $this->assertFalse($f['student']->fresh()->active);
    }

    public function test_reset_email_uses_canonical_host_and_tokens_are_not_cached_or_referrer_leaked(): void
    {
        $f = $this->fixture();
        config(['app.url' => 'https://examcell.example.test']);
        $token = Password::createToken($f['teacher']);
        $url = PasswordRecovery::url($f['teacher'], $token);
        $this->assertStringStartsWith('https://examcell.example.test/reset-password/', $url);
        $mail = (new ResetPassword($token))->toMail($f['teacher']);
        $this->assertSame($url, $mail->viewData['resetUrl']);
        $html = view('emails.password-reset', $mail->viewData)->render();
        $this->assertStringContainsString('Reset Password', $html);
        $this->assertStringContainsString('60 minutes', $html);
        $this->get('/reset-password/'.$token.'?email='.urlencode($f['teacher']->email))->assertHeader('Cache-Control', 'max-age=0, no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_request_throttles_resends_and_mail_unavailability_is_clear_for_every_address(): void
    {
        $f = $this->fixture();
        $this->post('/forgot-password', ['email' => $f['student']->email]);
        $this->post('/forgot-password', ['email' => $f['student']->email]);
        $this->assertCount(1, Notification::sent($f['student'], ResetPassword::class));
        $this->post('/forgot-password', ['email' => $f['student']->email])->assertStatus(429);
        $this->travel(61)->seconds();
        config(['mail.default' => 'log']);
        $this->post('/forgot-password', ['email' => 'another@example.test'])->assertSessionHasErrors('delivery');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'another@example.test']);
    }

    public function test_stale_sessions_are_rejected_after_password_changes(): void
    {
        $f = $this->fixture();
        $oldHash = $f['teacher']->password;
        $f['teacher']->update(['password' => 'ChangedElsewhere2026!']);
        $this->actingAs($f['teacher']->fresh())->withSession(['password_hash_web' => $oldHash]);
        $this->get('/teacher/teacher-dashboard')->assertRedirect('/sign-in');
        $this->assertGuest();
    }

    public function test_pre_upgrade_sessions_are_retired_after_recovery(): void
    {
        $f = $this->fixture();
        Settings::set('auth.password_reset.'.$f['teacher']->id, true);
        $this->actingAs($f['teacher'])->withSession(['password_hash_web' => null]);
        $this->get('/teacher/teacher-dashboard')->assertRedirect('/sign-in');
        $this->assertGuest();
    }

    public function test_delivery_failures_remove_tokens_without_exposing_transport_secrets(): void
    {
        $f = $this->fixture();
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('AUTH private-transport-secret'));
        $this->post('/forgot-password', ['email' => $f['student']->email])->assertRedirect('/forgot-password?sent=1')->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $f['student']->email]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.reset_link_delivery_failed']);
        $this->assertStringNotContainsString('private-transport-secret', M\AuditLog::all()->toJson());
    }

    public function test_google_settings_are_admin_only_encrypted_and_never_flushed_as_old_input(): void
    {
        $f = $this->fixture();
        foreach (['teacher', 'student'] as $role) {
            $this->actingAs($f[$role])->post('/admin/settings/email', [])->assertForbidden();
            $this->get('/'.$role.'/email-settings')->assertForbidden();
        }
        $this->actingAs($f['admin']);
        $secret = 'abcdefghijklmnop';
        $this->post('/admin/settings/email', ['sender_email' => 'sender@gmail.com', 'sender_name' => 'ExamCell', 'app_password' => $secret])->assertSessionHasNoErrors();
        $saved = Settings::get(RecoveryMail::KEY);
        $this->assertSame($secret, Crypt::decryptString($saved['secret']));
        $this->assertFalse(app(RecoveryMail::class)->ready());
        $this->assertStringNotContainsString($secret, M\AppSetting::find(RecoveryMail::KEY)->toJson());
        $this->get('/admin/settings?tab=email')->assertOk()->assertDontSee($secret);
        $this->assertStringNotContainsString($secret, M\AuditLog::all()->toJson());
        $this->post('/admin/settings/email', ['sender_email' => 'bad', 'sender_name' => 'ExamCell', 'app_password' => $secret])->assertSessionHasErrors('sender_email');
        $this->assertArrayNotHasKey('app_password', session()->getOldInput());
    }

    public function test_connection_verification_enables_recovery_and_clearing_secret_is_not_accidental(): void
    {
        $f = $this->fixture();
        Settings::set(RecoveryMail::KEY, ['address' => 'sender@gmail.com', 'name' => 'ExamCell', 'secret' => Crypt::encryptString('abcdefghijklmnop'), 'verified_at' => null]);
        $mock = \Mockery::mock(RecoveryMail::class)->makePartial();
        $mock->shouldReceive('testConnection')->once()->andReturn(['ok' => true, 'message' => 'Connection verified.']);
        $this->app->instance(RecoveryMail::class, $mock);
        $this->actingAs($f['admin'])->post('/admin/settings/email/test')->assertSessionHasNoErrors();
        $this->assertTrue(app(RecoveryMail::class)->ready());
        $before = Settings::get(RecoveryMail::KEY)['secret'];
        $this->post('/admin/settings/email', ['sender_email' => 'sender@gmail.com', 'sender_name' => 'ExamCell', 'app_password' => ''])->assertSessionHasNoErrors();
        $this->assertSame($before, Settings::get(RecoveryMail::KEY)['secret']);
        $this->post('/admin/settings/email', ['sender_email' => 'other@gmail.com', 'sender_name' => 'ExamCell', 'app_password' => ''])->assertSessionHasErrors('app_password');
    }
}
