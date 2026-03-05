<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\Attendance;
use App\Models\AcademicSession;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    protected AcademicCalendarService $calendar;

    protected array $dayMap = [
        'domingo'   => Carbon::SUNDAY,    // 0
        'lunes'     => Carbon::MONDAY,    // 1
        'martes'    => Carbon::TUESDAY,   // 2
        'miércoles' => Carbon::WEDNESDAY, // 3
        'miercoles' => Carbon::WEDNESDAY, // por si acaso
        'jueves'    => Carbon::THURSDAY,  // 4
        'viernes'   => Carbon::FRIDAY,    // 5
        'sábado'    => Carbon::SATURDAY,  // 6
        'sabado'    => Carbon::SATURDAY,
    ];

    public function __construct(AcademicCalendarService $calendar)
    {
        $this->calendar = $calendar;
    }

    public function generateSessions($schedule, $period): array
    {
        $sessions = [];

        $dayKey = strtolower($schedule->day_of_week);
        if (! isset($this->dayMap[$dayKey])) {
            return [];
        }

        $targetDay = $this->dayMap[$dayKey];

        $periodDates = CarbonPeriod::create(
            $period->start_date,
            $period->end_date
        );

        foreach ($periodDates as $date) {

            if ($date->dayOfWeek !== $targetDay) {
                continue;
            }

            // 🔑 Aquí queda CENTRALIZADA la lógica escolar
            if ($this->calendar->isNonWorkingDay($date, $period->modality_id)) {
                continue;
            }

            $sessions[] = [
                'schedule_id' => $schedule->id,
                'class_date'  => $date->toDateString(),
            ];
        }

        return $sessions;
    }

    public function attendancePercentage(
        TeachingAssignment $assignment,
        Student $student,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): float {
        // -----------------------------
        // TOTAL DE SESIONES (DENOMINADOR)
        // -----------------------------
        $sessionsQuery = AcademicSession::where('teaching_assignment_id', $assignment->id)
            ->where('is_cancelled', false);
        
        // 🔒 Acotar SOLO si hay rango
        if ($from && $to) {
            $sessionsQuery->whereBetween('session_date', [$from, $to]);
        }
        
        $totalSessions = $sessionsQuery->count();
        
        if ($totalSessions === 0) {
            return 0.0;
        }

        // -----------------------------
        // SESIONES ASISTIDAS (NUMERADOR)
        // -----------------------------
        $attendedSessions = Attendance::where('student_id', $student->id)
            ->where('status', 'present')
            ->whereHas('academicSession', function ($q) use ($assignment, $from, $to) {
                $q->where('teaching_assignment_id', $assignment->id)
                ->where('is_cancelled', false);
        
                if ($from && $to) {
                    $q->whereBetween('session_date', [$from, $to]);
                }
            })
            ->count();

        // -----------------------------
        // PORCENTAJE FINAL
        // -----------------------------
        return round(($attendedSessions / $totalSessions) * 100, 2);
    }
}
