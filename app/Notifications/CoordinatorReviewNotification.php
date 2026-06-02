<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CoordinatorReviewNotification extends Notification
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
            'type' => $this->payload['type'] ?? 'coordinator_review',
            'title' => $this->payload['title'] ?? 'Revision pendiente',
            'message' => $this->payload['message'] ?? 'Tienes un elemento pendiente por revisar.',
            'url' => $this->payload['url'] ?? route('dashboard'),
            'meta' => $this->payload['meta'] ?? [],
        ];
    }
}

