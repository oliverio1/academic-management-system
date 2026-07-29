<?php

namespace App\Notifications;

use App\Models\Practice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PracticePublishedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Practice $practice)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'practice_published',
            'title' => 'Nuevo entregable',
            'message' => $this->practice->kind_label . ' ' . $this->practice->number . ': ' . $this->practice->title,
            'practice_id' => $this->practice->id,
            'url' => route('student.practices.show', $this->practice),
        ];
    }
}
