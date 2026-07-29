<?php

namespace App\Notifications;

use App\Models\PracticeSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PracticeReviewedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly PracticeSubmission $submission)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $practice = $this->submission->practice;

        return [
            'type' => 'practice_reviewed',
            'title' => 'Entrega revisada',
            'message' => $practice->kind_label . ' ' . $practice->number . ': ' . $practice->title . ' ya fue revisada.',
            'practice_id' => $practice->id,
            'submission_id' => $this->submission->id,
            'url' => route('student.practices.report', $practice),
        ];
    }
}
