<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class WorkflowNotice extends Notification
{
    public function __construct(public string $title, public string $message, public string $url = '/') {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return ['title' => $this->title, 'message' => $this->message, 'url' => $this->url];
    }
}
