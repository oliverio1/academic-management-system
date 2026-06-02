<?php

namespace App\Services;

use App\Models\TeachingAssignment;
use App\Models\Student;
use App\Models\Grade;
use App\Models\TeamGrade;
use App\Models\Team;
use App\Models\AcademicPeriod;
use App\Models\EvaluationCriterion;
use App\Services\AttendanceService;
use Carbon\Carbon;

class GradeService
{
    protected AttendanceService $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /* =====================================================
     |  CALIFICACIÓN DE UNA ACTIVIDAD
     ===================================================== */

    public function gradeForActivity(
        TeachingAssignment $assignment,
        Student $student,
        $activity
    ): ?float {

        if ($activity->evaluation_mode === 'individual') {
            return Grade::where('activity_id', $activity->id)
                ->where('student_id', $student->id)
                ->value('score');
        }

        if ($activity->evaluation_mode === 'team') {

            $teamId = Team::where('teaching_assignment_id', $assignment->id)
                ->whereHas('students', function ($q) use ($student) {
                    $q->where('students.id', $student->id);
                })
                ->value('id');

            if (! $teamId) {
                return null;
            }

            return TeamGrade::where('activity_id', $activity->id)
                ->where('team_id', $teamId)
                ->value('score');
        }

        return null;
    }

    /* =====================================================
     |  PROMEDIO DE UN CRITERIO
     ===================================================== */

    public function criterionAverage(
        TeachingAssignment $assignment,
        Student $student,
        $criterion,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): ?float {

        $scores = [];

        foreach ($criterion->activities as $activity) {
            $activityDate = $activity->due_date
                ? Carbon::parse($activity->due_date)
                : Carbon::parse($activity->created_at);

            if ($from && $activityDate->lt($from->copy()->startOfDay())) {
                continue;
            }

            if ($to && $activityDate->gt($to->copy()->endOfDay())) {
                continue;
            }

            $grade = $this->gradeForActivity(
                $assignment,
                $student,
                $activity
            );

            if ($grade !== null) {
                $scores[] = $grade;
            }
        }

        if (empty($scores)) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 2);
    }

    /* =====================================================
     |  CALIFICACIÓN FINAL (CÁLCULO PURO)
     |  - NO decide contexto
     |  - NO aplica reglas académicas
     ===================================================== */

    public function finalGrade(
        TeachingAssignment $assignment,
        Student $student,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): ?float {

        $breakdown = $this->breakdown($assignment, $student, $from, $to);

        return $breakdown['final'];
    }

    /* =====================================================
     |  DESGLOSE DE CALIFICACIÓN
     |  (para boleta / vista detallada)
     ===================================================== */

    public function breakdown(
        TeachingAssignment $assignment,
        Student $student,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): array {

        $periodId = $this->resolveAcademicPeriodId($assignment, $from, $to);

        $criteria = EvaluationCriterion::query()
            ->forAssignmentAndPeriod($assignment, $periodId)
            ->with(['activities', 'cyclePartial.academicPeriod'])
            ->orderBy('id')
            ->get();

        $rows = [];
        $final = 0;

        foreach ($criteria as $criterion) {

            /*
            |--------------------------------------------------
            | CRITERIO DE ASISTENCIA
            |--------------------------------------------------
            */
            if ($criterion->isAttendance()) {
                // Usar exactamente el mismo porcentaje mostrado en actas/PDF.
                $attendancePercentage = $this->attendanceService
                    ->attendancePercentage($assignment, $student, $from, $to);
                
                $attendanceScore = $attendancePercentage / 10;
            
                $contribution = ($attendanceScore * $criterion->percentage) / 100;

                $final += $contribution;
                $rows[] = [
                    'partial'      => $criterion->cyclePartial?->name
                        ?? $criterion->cyclePartial?->academicPeriod?->name
                        ?? null,
                    'criterion'    => $criterion->name,          // "Asistencia"
                    'percentage'   => $criterion->percentage,
                    'average'      => round($attendanceScore, 2),
                    'contribution' => round($contribution, 2),
                ];
                continue;
            }

            /*
            |--------------------------------------------------
            | CRITERIOS ACADÉMICOS
            |--------------------------------------------------
            */
            $average = $this->criterionAverage(
                $assignment,
                $student,
                $criterion,
                $from,
                $to
            );

            $average = $average ?? 0;

            $contribution = ($average * $criterion->percentage) / 100;

            $rows[] = [
                'partial'      => $criterion->cyclePartial?->name
                    ?? $criterion->cyclePartial?->academicPeriod?->name
                    ?? null,
                'criterion'    => $criterion->name,
                'percentage'   => $criterion->percentage,
                'average'      => round($average, 2),
                'contribution' => round($contribution, 2),
            ];

            $final += $contribution;
        }

        return [
            'rows'  => $rows,
            'final' => round($final, 2),
        ];
    }

    private function resolveAcademicPeriodId(
        TeachingAssignment $assignment,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): ?int {
        if (! $from || ! $to) {
            return null;
        }

        return AcademicPeriod::query()
            ->where('modality_id', $assignment->group->level->modality_id)
            ->whereDate('start_date', '<=', $from->toDateString())
            ->whereDate('end_date', '>=', $to->toDateString())
            ->value('id');
    }
}
