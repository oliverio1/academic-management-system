<?php

namespace App\Notifications;

use App\Models\CyclePartial;
use App\Models\TeachingAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PartialAutoClosedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TeachingAssignment $assignment,
        public CyclePartial $partial,
        public bool $forCoordination = false
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $base = 'Se cerro automaticamente el parcial ' . ($this->partial->name ?? '-') .
            ' de ' . ($this->assignment->subject->name ?? '-') .
            ' grupo ' . ($this->assignment->group->name ?? '-') . '.';

        $message = $this->forCoordination
            ? $base . ' Se genero acta por incumplimiento de captura.'
            : $base . ' Debes solicitar reapertura a coordinacion para continuar capturando.';

        return [
            'title' => 'Cierre automatico de parcial',
            'message' => $message,
            'type' => 'partial_auto_closed',
            'teaching_assignment_id' => $this->assignment->id,
            'cycle_partial_id' => $this->partial->id,
        ];
    }
}

