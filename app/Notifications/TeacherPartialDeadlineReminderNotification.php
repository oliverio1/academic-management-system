<?php

namespace App\Notifications;

use App\Models\CyclePartial;
use App\Models\TeachingAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TeacherPartialDeadlineReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TeachingAssignment $assignment,
        public CyclePartial $partial,
        public int $daysRemaining
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $deadline = optional($this->partial->teacher_capture_deadline_at)?->format('d/m/Y H:i') ?: '-';
        $daysText = $this->daysRemaining === 0 ? 'hoy' : ('en ' . $this->daysRemaining . ' dia(s)');

        return [
            'title' => 'Recordatorio de cierre de parcial',
            'message' => 'La materia ' . ($this->assignment->subject->name ?? '-') .
                ' (' . ($this->assignment->group->name ?? '-') . ') cierra ' . $daysText .
                '. Fecha limite: ' . $deadline . '.',
            'type' => 'partial_deadline_reminder',
            'teaching_assignment_id' => $this->assignment->id,
            'cycle_partial_id' => $this->partial->id,
        ];
    }
}

