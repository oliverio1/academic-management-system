<?php

namespace App\Notifications;

use App\Models\PracticeSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PracticeSubmittedNotification extends Notification
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
        $studentName = $this->submission->submittedBy?->name ?? 'Un alumno';

        return [
            'type' => 'practice_submitted',
            'title' => 'Entrega recibida',
            'message' => $studentName . ' envio ' . $practice->kind_label . ' ' . $practice->number . ': ' . $practice->title,
            'practice_id' => $practice->id,
            'submission_id' => $this->submission->id,
            'url' => route('practices.submissions.review', $this->submission),
        ];
    }
}
