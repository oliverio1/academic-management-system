<?php

namespace App\Notifications;

use App\Models\Student;
use App\Models\AttendanceJustification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AttendanceJustifiedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Student $student,
        protected AttendanceJustification $justification
    ) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'attendance_justified',
            'student_id' => $this->student->id,
            'student_name' => $this->student->user->name,
            'enrollment_number' => $this->student->enrollment_number,
            'from_date' => $this->justification->from_date->toDateString(),
            'to_date'   => $this->justification->to_date->toDateString(),
            'reason'    => $this->justification->reason,
            'justification_id' => $this->justification->id,
        ];
    }
}
