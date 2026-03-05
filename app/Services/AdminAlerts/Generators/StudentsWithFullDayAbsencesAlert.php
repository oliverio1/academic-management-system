<?php

namespace App\Services\AdminAlerts\Generators;

use App\DTOs\AdminAlert;
use App\Models\Attendance;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Services\AdminAlerts\Contracts\AlertGenerator;
use App\Services\AcademicCalendarService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class StudentsWithFullDayAbsencesAlert implements AlertGenerator
{
    protected int $modalityId;

    public function __construct(protected AcademicCalendarService $calendar) {}

    public function forModality(int $modalityId): self {
        $this->modalityId = $modalityId;
        return $this;
    }
    
    public function generate(): ?AdminAlert
    {
        $limitDays = 3;
    
        [$since, $endDate] = [
            Carbon::parse('2025-09-01'),
            Carbon::parse('2025-09-30'),
        ];
    
        $studentsWithCriticalAbsences = 0;
    
        $students = Student::whereHas('group.level.modality', fn ($q) =>
            $q->where('id', $this->modalityId)
        )->get();
    
        $studentIds = $students->pluck('id');
    
        /**
         * 🔑 SESIONES REALES POR DÍA
         */
        $sessionsByDate = AcademicSession::whereBetween('session_date', [$since, $endDate])
            ->where('is_cancelled', false)
            ->with(['attendances' => function ($q) use ($studentIds) {
                $q->whereIn('student_id', $studentIds);
            }])
            ->get()
            ->groupBy(fn ($s) => $s->session_date->toDateString());
    
        logger()->info('ALERTA 1 SESSIONS RAW', [
            'session_count' => $sessionsByDate->flatten()->count(),
            'dates' => $sessionsByDate->keys()->values()->all(),
        ]);
    
        foreach ($students as $student) {
    
            $consecutive = 0;
            $maxConsecutive = 0;
    
            $date = $since->copy();
    
            while ($date->lte($endDate)) {
    
                if ($this->calendar->isNonWorkingDay($date, $this->modalityId)) {
                    $date->addDay();
                    continue;
                }
    
                $daySessions = $sessionsByDate->get(
                    $date->toDateString(),
                    collect()
                );
    
                if ($daySessions->isEmpty()) {
                    // día escolar sin sesiones → no cuenta
                    $consecutive = 0;
                } else {
    
                    $attendances = $daySessions
                        ->flatMap->attendances
                        ->where('student_id', $student->id);
    
                    if ($attendances->isEmpty()) {
                        // hubo clases pero no hay registros → no cuenta como falta completa
                        $consecutive = 0;
                    } else {
                        $total  = $attendances->count();
                        $absent = $attendances->where('status', 'absent')->count();
    
                        if ($total === $absent) {
                            $consecutive++;
                            $maxConsecutive = max($maxConsecutive, $consecutive);
                        } else {
                            $consecutive = 0;
                        }
                    }
                }
    
                $date->addDay();
            }
    
            if ($maxConsecutive >= $limitDays) {
                $studentsWithCriticalAbsences++;
            }
        }
    
        if ($studentsWithCriticalAbsences === 0) {
            return null;
        }
    
        return new AdminAlert(
            icon: '<i class="fas fa-exclamation-circle text-danger mr-2"></i>',
            message: "{$studentsWithCriticalAbsences} alumnos con {$limitDays} días completos consecutivos de inasistencia",
            url: route('admin.alerts.attendance', ['modality' => $this->modalityId]),
            level: 'danger',
            dateRange: "Del {$since->translatedFormat('d M Y')} al {$endDate->translatedFormat('d M Y')}"
        );
    }    
}
