<?php

namespace App\Notifications;

use App\Services\RecoveryMail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChanged extends Notification
{
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = app(RecoveryMail::class);
        $sender = $mail->sender();

        return (new MailMessage)->mailer($mail->prepare())->from($sender['address'], $sender['name'])
            ->subject('Your ExamCell password was changed')
            ->view('emails.password-changed', ['loginUrl' => rtrim(config('app.url'), '/').'/sign-in']);
    }
}
