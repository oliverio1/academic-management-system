<?php

namespace App\Notifications;

use App\Models\SchoolCase;
use App\Models\SchoolCaseAction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SchoolCaseAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SchoolCase $schoolCase,
        private readonly ?SchoolCaseAction $action = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $isAction = $this->action !== null;

        return [
            'type' => $isAction ? 'school_case_action_assigned' : 'school_case_assigned',
            'title' => $isAction ? 'Seguimiento asignado' : 'Caso escolar asignado',
            'message' => $isAction
                ? $this->action->title.' - '.$this->schoolCase->subject
                : $this->schoolCase->subject,
            'school_case_id' => $this->schoolCase->id,
            'school_case_action_id' => $this->action?->id,
            'url' => route('coordination.school-cases.show', $this->schoolCase),
        ];
    }
}
