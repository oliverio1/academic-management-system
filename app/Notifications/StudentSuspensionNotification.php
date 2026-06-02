<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StudentSuspensionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly array $payload
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'student_suspension',
            'title' => $this->payload['title'],
            'message' => $this->payload['message'],
            'student_id' => $this->payload['student_id'],
            'group_id' => $this->payload['group_id'],
            'start_date' => $this->payload['start_date'],
            'end_date' => $this->payload['end_date'],
            'url' => $this->payload['url'] ?? route('dashboard'),
        ];
    }
}

