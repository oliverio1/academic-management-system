<?php

namespace App\Services\AdminAlerts\Generators;

use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Services\AcademicPerformanceService;
use App\Services\AdminAlerts\AdminAlert;
use Carbon\Carbon;

class StudentsWithCriticalSubjectAlert
{
    public function generate(int $modalityId): ?AdminAlert
    {
        $minAverage = 6.0;
        $weeksThreshold = 2;

        // 1️⃣ Periodo activo
        $period = AcademicPeriod::where('modality_id', $modalityId)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        if (! $period) {
            return null;
        }

        if (
            Carbon::parse($period->start_date)
                ->addWeeks($weeksThreshold)
                ->isFuture()
        ) {
            return null;
        }

        // 2️⃣ Alumnos de la modalidad
        $students = Student::whereHas(
            'group.level.modality',
            fn ($q) => $q->where('id', $modalityId)
        )->with('group.assignments')->get();

        if ($students->isEmpty()) {
            return null;
        }

        $performance = app(AcademicPerformanceService::class);

        $criticalCount = 0;

        foreach ($students as $student) {
            if ($performance->studentHasCriticalSubject(
                $student,
                $minAverage
            )) {
                $criticalCount++;
            }
        }

        if ($criticalCount === 0) {
            return null;
        }

        return new AdminAlert(
            icon: '<i class="fas fa-book-dead text-danger mr-2"></i>',
            message: "{$criticalCount} alumnos con materias en riesgo académico",
            url: route('admin.alerts.grades', [
                'modality' => $modalityId,
                'type' => 'critical',
            ]),
            level: 'danger'
        );
    }
}
