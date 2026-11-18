<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\RecoveryMail;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class RecoveryMailController
{
    public function save(Request $request)
    {
        $data = $request->validate(['sender_email' => 'required|email|max:254', 'sender_name' => 'required|string|max:80', 'app_password' => 'nullable|string|max:100']);
        $saved = Settings::get(RecoveryMail::KEY, []);
        if (filled($data['app_password'] ?? null)) {
            $secret = preg_replace('/\s+/', '', $data['app_password']);
            if (! preg_match('/^[A-Za-z0-9]{16}$/', $secret)) {
                throw ValidationException::withMessages(['app_password' => 'Enter the 16-character Google app password. Spaces between groups are accepted.']);
            }
            $ciphertext = Crypt::encryptString($secret);
        } else {
            if (empty($saved['secret']) || ($saved['address'] ?? '') !== $data['sender_email']) {
                throw ValidationException::withMessages(['app_password' => 'Enter the Google app password for this sender address.']);
            }
            $ciphertext = $saved['secret'];
        }
        Settings::set(RecoveryMail::KEY, ['address' => $data['sender_email'], 'name' => $data['sender_name'], 'secret' => $ciphertext, 'verified_at' => null]);
        app(AuditService::class)->record('mail.settings_saved', 'settings', ['provider' => 'google', 'sender' => $data['sender_email'], 'secret_configured' => true]);

        return redirect('/admin/settings?tab=email')->with('status', 'Sender saved securely. Test the connection to enable password-reset emails.');
    }

    public function test()
    {
        $saved = Settings::get(RecoveryMail::KEY, []);
        $result = app(RecoveryMail::class)->testConnection();
        if (! empty($saved['secret'])) {
            $saved['verified_at'] = $result['ok'] ? now()->toIso8601String() : null;
            Settings::set(RecoveryMail::KEY, $saved);
        }
        app(AuditService::class)->record('mail.connection_tested', 'settings', ['success' => $result['ok']]);

        return redirect('/admin/settings?tab=email')->with($result['ok'] ? 'status' : 'mail_error', $result['message']);
    }

    public function sendTest()
    {
        $mail = app(RecoveryMail::class);
        if (! $mail->ready()) {
            return back()->with('mail_error', 'Save the sender and verify the connection first.');
        }
        $sender = $mail->sender();
        try {
            Mail::mailer($mail->prepare())->raw('ExamCell email delivery test. Your Google connection is ready for password-reset emails. No password or reset token is included.', function ($message) use ($sender) {
                $message->from($sender['address'], $sender['name'])->to($sender['address'])->subject('ExamCell email connection test');
            });
            app(AuditService::class)->record('mail.test_sent', 'settings');

            return back()->with('status', 'Google accepted the test email for delivery to the configured sender. Check its inbox and spam folder.');
        } catch (\Throwable) {
            app(AuditService::class)->record('mail.test_failed', 'settings');

            return back()->with('mail_error', 'The test email could not be sent. Test the connection and check the Google account settings.');
        }
    }
}
