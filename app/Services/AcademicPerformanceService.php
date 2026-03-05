<?php

namespace App\Services;

use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\AcademicPeriod;
use App\Models\AcademicResolution;
use Carbon\Carbon;

class AcademicPerformanceService
{
    protected GradeService $gradeService;

    public function __construct(GradeService $gradeService)
    {
        $this->gradeService = $gradeService;
    }

    /* =====================================================
     |  CALIFICACIÓN FINAL DE UNA MATERIA (FUENTE ÚNICA)
     ===================================================== */

    public function finalGradeForAssignment(
        Student $student,
        TeachingAssignment $assignment
    ): ?float {

        $period = AcademicPeriod::activeForModality(
            $assignment->group->level->modality_id
        );

        if (! $period) {
            return null;
        }

        /*
        |--------------------------------------------------
        | 1️⃣ Resolución académica (prioridad absoluta)
        |--------------------------------------------------
        */
        if ($resolution = $this->resolutionFor($student, $assignment, $period)) {
            return match ($resolution->type) {
                'override'        => (float) $resolution->value,
                'repeat_previous' => $this->previousPeriodGrade(
                    $student,
                    $assignment,
                    $period
                ),
                'defer_next'      => null,
            };
        }

        /*
        |--------------------------------------------------
        | 2️⃣ Cambio de nivel dentro del periodo
        |     → SOLO grupo actual
        |--------------------------------------------------
        */
        if ($this->isLevelChangeDuringPeriod($student, $period)) {
            return $this->gradeService
                ->finalGrade($assignment, $student);
        }

        /*
        |--------------------------------------------------
        | 3️⃣ Cambio de grupo (mismo nivel)
        |     → tramos efectivos
        |--------------------------------------------------
        */
        $ranges = $this->effectiveRanges($student, $assignment, $period);

        if (empty($ranges)) {
            return null;
        }

        // Un solo tramo
        if (count($ranges) === 1) {
            return $this->gradeService->finalGrade(
                $assignment,
                $student,
                $ranges[0]['from'],
                $ranges[0]['to']
            );
        }

        // Múltiples tramos (promedio institucional)
        $weightedSum = 0;
        $totalWeeks = 0;
        
        foreach ($ranges as $range) {
        
            $partial = $this->gradeService->finalGrade(
                $assignment,
                $student,
                $range['from'],
                $range['to']
            );
        
            if ($partial === null) {
                continue;
            }
        
            $weeks = max(
                1,
                $range['from']->diffInWeeks($range['to'])
            );
        
            $weightedSum += $partial * $weeks;
            $totalWeeks  += $weeks;
        }
        
        return $totalWeeks > 0
            ? round($weightedSum / $totalWeeks, 2)
            : null;
    }

    /* =====================================================
     |  PROMEDIO GENERAL DEL ALUMNO
     ===================================================== */

    public function studentGeneralAverage(Student $student): ?float
    {
        $assignments = TeachingAssignment::where(
            'group_id',
            $student->group_id
        )->get();

        $sum = 0;
        $count = 0;

        foreach ($assignments as $assignment) {
            $avg = $this->finalGradeForAssignment($student, $assignment);

            if ($avg !== null) {
                $sum += $avg;
                $count++;
            }
        }

        return $count > 0
            ? round($sum / $count, 2)
            : null;
    }

    /* =====================================================
     |  ESTATUS ACADÉMICO
     ===================================================== */

    public function academicStatus(
        Student $student,
        TeachingAssignment $assignment
    ): string {

        if ($this->hasIncompleteCriteria($student, $assignment)) {
            return 'incomplete';
        }

        $final = $this->finalGradeForAssignment($student, $assignment);

        if ($final === null) {
            return 'complete';
        }

        return $final < 6 ? 'risk' : 'complete';
    }

    /* =====================================================
     |  CRITERIOS INCOMPLETOS
     ===================================================== */

    public function hasIncompleteCriteria(
        Student $student,
        TeachingAssignment $assignment
    ): bool {

        $period = AcademicPeriod::activeForModality(
            $assignment->group->level->modality_id
        );

        if (! $period) {
            return false;
        }

        $ranges = $this->effectiveRanges($student, $assignment, $period);

        if (empty($ranges)) {
            return false;
        }

        $assignment->load('evaluationCriteria.activities');

        foreach ($assignment->evaluationCriteria as $criterion) {

            if ($criterion->activities->isEmpty()) {
                continue;
            }

            foreach ($criterion->activities as $activity) {
                foreach ($ranges as $range) {

                    if (
                        $activity->created_at->lt($range['from']) ||
                        $activity->created_at->gt($range['to'])
                    ) {
                        continue;
                    }

                    if (! $activity->grades()
                        ->where('student_id', $student->id)
                        ->exists()
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /* =====================================================
     |  UTILIDADES INTERNAS
     ===================================================== */

    protected function resolutionFor(
        Student $student,
        TeachingAssignment $assignment,
        AcademicPeriod $period
    ): ?AcademicResolution {

        return AcademicResolution::where('student_id', $student->id)
            ->where('teaching_assignment_id', $assignment->id)
            ->where('academic_period_id', $period->id)
            ->latest()
            ->first();
    }

    protected function isLevelChangeDuringPeriod(
        Student $student,
        AcademicPeriod $period
    ): bool {

        return $student->groupHistories
            ->filter(function ($history) use ($period) {

                $start = Carbon::parse($history->start_date);
                $end = $history->end_date
                    ? Carbon::parse($history->end_date)
                    : Carbon::now();

                return $start->lte($period->end_date)
                    && $end->gte($period->start_date);
            })
            ->pluck('group.level_id')
            ->unique()
            ->count() > 1;
    }

    protected function effectiveRanges(
        Student $student,
        TeachingAssignment $assignment,
        AcademicPeriod $period
    ): array {

        return $student->groupHistories
            ->where('group_id', $assignment->group_id)
            ->map(function ($history) use ($period) {

                $from = Carbon::parse($history->start_date)
                    ->max(Carbon::parse($period->start_date));

                $to = $history->end_date
                    ? Carbon::parse($history->end_date)
                        ->min(Carbon::parse($period->end_date))
                    : Carbon::parse($period->end_date);

                if ($from->gt($to)) {
                    return null;
                }

                return [
                    'from' => $from,
                    'to'   => $to,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function studentHasCriticalSubject(
        Student $student,
        float $minAverage = 6.0
    ): bool {
    
        $assignments = TeachingAssignment::where(
            'group_id',
            $student->group_id
        )->get();
    
        foreach ($assignments as $assignment) {
    
            $final = $this->finalGradeForAssignment(
                $student,
                $assignment
            );
    
            // sin evidencia → no se considera crítica
            if ($final === null) {
                continue;
            }
    
            if ($final < $minAverage) {
                return true;
            }
        }
    
        return false;
    }

    public function riskBreakdown(
        Student $student,
        TeachingAssignment $assignment
    ): array {
    
        $period = AcademicPeriod::activeForModality(
            $assignment->group->level->modality_id
        );
    
        if (! $period) {
            return [];
        }
    
        /*
        |--------------------------------------------------
        | Cambio de nivel → solo grupo actual
        |--------------------------------------------------
        */
        if ($this->isLevelChangeDuringPeriod($student, $period)) {
            return $this->gradeService
                ->breakdown($assignment, $student);
        }
    
        /*
        |--------------------------------------------------
        | Cambio de grupo → tramos efectivos
        |--------------------------------------------------
        */
        $ranges = $this->effectiveRanges($student, $assignment, $period);
    
        if (empty($ranges)) {
            return [];
        }
    
        // Un solo tramo
        if (count($ranges) === 1) {
            return $this->gradeService->breakdown(
                $assignment,
                $student,
                $ranges[0]['from'],
                $ranges[0]['to']
            );
        }
    
        /*
        |--------------------------------------------------
        | Múltiples tramos
        | (para alertas usamos el tramo más reciente)
        |--------------------------------------------------
        */
        $lastRange = collect($ranges)->last();
    
        return $this->gradeService->breakdown(
            $assignment,
            $student,
            $lastRange['from'],
            $lastRange['to']
        );
    }
}