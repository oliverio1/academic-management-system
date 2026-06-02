<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Attendance;
use App\Models\AcademicSession;
use App\Models\AttendanceJustification;
use App\Models\PrefectDailyAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Notifications\AttendanceJustifiedNotification;
use Illuminate\Support\Collection;

class AttendanceJustificationService
{
    /**
     * Emitir justificante y justificar asistencias automáticamente.
     */
    public function justify(
        Student $student,
        Carbon $from,
        Carbon $to,
        array $data,
        User $issuedBy
    ): AttendanceJustification {
    
        // -------------------------------------------------
        // 1️⃣ TRANSACCIÓN: justificante + asistencias
        // -------------------------------------------------
        $justification = DB::transaction(function () use (
            $student,
            $from,
            $to,
            $data,
            $issuedBy
        ) {
    
            // 1️⃣ Crear el justificante institucional
            $justification = AttendanceJustification::create([
                'student_id'    => $student->id,
                'from_date'     => $from->toDateString(),
                'to_date'       => $to->toDateString(),
                'reason'        => $data['reason'],
                'document_path' => $data['document_path'] ?? null,
                'issued_by'     => $issuedBy->id,
                'issued_at'     => now(),
            ]);
    
            // 2️⃣ Obtener sesiones reales del periodo
            $sessions = AcademicSession::whereBetween(
                    'session_date',
                    [$from->toDateString(), $to->toDateString()]
                )
                ->where('is_cancelled', false)
                ->with('teachingAssignment')
                ->get();
    
            // 3️⃣ Justificar asistencias reales del alumno
            $daysToJustifyInPrefecture = [];
            foreach ($sessions as $session) {
    
                $groupIdForDate = $student->groupHistories
                    ->first(fn ($h) =>
                        $session->session_date->gte($h->start_date) &&
                        (! $h->end_date || $session->session_date->lte($h->end_date))
                    )?->group_id;
    
                if (! $groupIdForDate) {
                    continue;
                }
    
                if ($session->teachingAssignment->group_id !== $groupIdForDate) {
                    continue;
                }
    
                Attendance::updateOrCreate(
                    [
                        'academic_session_id' => $session->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'status' => 'justified',
                    ]
                );

                $sessionDay = $session->session_date->toDateString();
                $daysToJustifyInPrefecture[$sessionDay] = (int) $groupIdForDate;
            }

            foreach ($daysToJustifyInPrefecture as $date => $groupId) {
                PrefectDailyAttendance::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'attendance_date' => $date,
                    ],
                    [
                        'group_id' => $groupId,
                        'status' => 'justified',
                        'recorded_by' => $issuedBy->id,
                    ]
                );
            }
    
            return $justification;
        });
    
        // -------------------------------------------------
        // 2️⃣ NOTIFICAR A PROFESORES (FUERA DE LA TX)
        // -------------------------------------------------
        $teacherIds = AcademicSession::whereBetween(
                'session_date',
                [$from->toDateString(), $to->toDateString()]
            )
            ->where('is_cancelled', false)
            ->with('teachingAssignment')
            ->get()
            ->filter(function ($session) use ($student) {
    
                $groupIdForDate = $student->groupHistories
                    ->first(fn ($h) =>
                        $session->session_date->gte($h->start_date) &&
                        (! $h->end_date || $session->session_date->lte($h->end_date))
                    )?->group_id;
    
                return $groupIdForDate &&
                    $session->teachingAssignment->group_id === $groupIdForDate;
            })
            ->pluck('teachingAssignment.teacher_id')
            ->unique();
    
        $teachers = \App\Models\Teacher::whereIn('id', $teacherIds)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();
    
        foreach ($teachers as $user) {
            $user->notify(
                new AttendanceJustifiedNotification($student, $justification)
            );
        }
    
        return $justification;
    }
}
