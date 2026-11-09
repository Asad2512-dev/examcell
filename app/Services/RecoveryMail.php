<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/** Password recovery uses its own mailer; credentials never enter audit payloads. */
class RecoveryMail
{
    public const KEY = 'mail.gmail';

    public function publicSettings(): array
    {
        $saved = Settings::get(self::KEY, []);

        return [
            'sender_email' => $saved['address'] ?? '', 'sender_name' => $saved['name'] ?? 'ExamCell',
            'configured' => ! empty($saved['secret']), 'verified_at' => $saved['verified_at'] ?? null,
            'ready' => $this->ready(), 'provider' => ! empty($saved['secret']) ? 'Google Workspace / Gmail' : config('mail.default'),
        ];
    }

    public function ready(): bool
    {
        $saved = Settings::get(self::KEY, []);
        if (! empty($saved['secret'])) {
            return ! empty($saved['verified_at']);
        }

        return config('mail.default') === 'smtp'
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
            && filled(config('mail.mailers.smtp.host')) && filled(config('mail.mailers.smtp.username'))
            && filled(config('mail.mailers.smtp.password'));
    }

    public function prepare(): string
    {
        $saved = Settings::get(self::KEY, []);
        if (empty($saved['secret'])) {
            return (string) config('mail.default');
        }
        config(['mail.mailers.recovery' => [
            'transport' => 'smtp', 'scheme' => 'smtp', 'host' => 'smtp.gmail.com', 'port' => 587,
            'username' => $saved['address'], 'password' => Crypt::decryptString($saved['secret']),
            'timeout' => 10, 'require_tls' => true,
        ]]);
        Mail::purge('recovery');

        return 'recovery';
    }

    public function sender(): array
    {
        $saved = Settings::get(self::KEY, []);

        return ['address' => $saved['address'] ?? config('mail.from.address'), 'name' => $saved['name'] ?? config('mail.from.name', 'ExamCell')];
    }

    public function testConnection(): array
    {
        try {
            $transport = Mail::mailer($this->prepare())->getSymfonyTransport();
            if (! $transport instanceof EsmtpTransport) {
                return ['ok' => false, 'message' => 'Configure the Google sender and app password first.'];
            }
            try {
                $transport->start();
            } finally {
                $transport->stop();
            }

            return ['ok' => true, 'message' => 'Google connection verified. Password-reset delivery is enabled.'];
        } catch (\Throwable $exception) {
            // SMTP exception strings can include AUTH data. Never report/log them.
            $authentication = str_contains(strtolower($exception->getMessage()), 'auth') || str_contains($exception->getMessage(), '535');

            return ['ok' => false, 'message' => $authentication
                ? 'Google rejected the sign-in. Check the sender address, Google app password and your organization’s app-password policy.'
                : 'Could not establish a secure Google mail connection. Check the credentials and hosting outbound SMTP access.'];
        }
    }
}
