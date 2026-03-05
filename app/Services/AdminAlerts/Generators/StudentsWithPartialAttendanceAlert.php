<?php

namespace App\Services\AdminAlerts\Generators;

use App\DTOs\AdminAlert;
use App\Models\Attendance;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Services\AdminAlerts\Contracts\AlertGenerator;
use App\Services\AcademicCalendarService;
use Illuminate\Support\Facades\Log;

class StudentsWithPartialAttendanceAlert implements AlertGenerator
{
    protected int $modalityId;

    public function __construct(protected AcademicCalendarService $calendar) {}

    public function forModality(int $modalityId): self {
        $this->modalityId = $modalityId;
        return $this;
    }

    public function generate(): ?AdminAlert{
        $problematicDaysLimit = 6;
        $absenceRatio = 0.5;

        // 🔑 intervalo académico correcto
        [$since, $endDate] = $this->calendar->getAlertDateRange(
            20,
            $this->modalityId
        );

        // 1️⃣ alumnos de la modalidad
        $students = Student::whereHas('group.level.modality', fn ($q) =>
            $q->where('id', $this->modalityId)
        )->get(['id']);

        if ($students->isEmpty()) {
            return null;
        }

        $studentIds = $students->pluck('id');

        // 2️⃣ TODAS las asistencias en una sola query
        $sessionsByDate = AcademicSession::whereBetween('session_date', [$since, $endDate])
            ->where('is_cancelled', false)
            ->with(['attendances' => function ($q) use ($studentIds) {
                $q->whereIn('student_id', $studentIds)
                ->select('academic_session_id', 'student_id', 'status');
            }])
            ->get()
            ->groupBy(fn ($s) => $s->session_date->toDateString());
        
        $studentsWithIssues = 0;
        
        // 3️⃣ procesar en memoria
        foreach ($studentIds as $studentId) {
        
            $problematicDays = 0;
        
            foreach ($sessionsByDate as $date => $sessions) {
        
                $records = $sessions
                    ->flatMap->attendances
                    ->where('student_id', $studentId);
        
                if ($records->isEmpty()) {
                    continue; // día sin registros → no problemático
                }
        
                $total  = $records->count();
                $absent = $records->where('status', 'absent')->count();
        
                if ($total === $absent) {
                    $problematicDays++;
                }
            }
        
            if ($problematicDays > 0) {
                $studentsWithIssues++;
            }
        }
        if ($studentsWithIssues === 0) {
            return null;
        }

        return new AdminAlert(
            icon: '<i class="fas fa-user-clock text-warning mr-2"></i>',
            message: "{$studentsWithIssues} alumnos con ausentismo parcial frecuente",
            url: route('admin.alerts.attendance', [
                'modality' => $this->modalityId,
                'type' => 'partial'
            ]),
            level: 'warning',
            dateRange: "Del {$since->translatedFormat('d M Y')} al {$endDate->translatedFormat('d M Y')}"
        );
    }
}
