<?php

namespace App\Services;

use App\Models\TeachingAssignment;
use App\Models\Student;
use App\Models\Grade;
use App\Models\TeamGrade;
use App\Models\Team;
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
        $criterion
    ): ?float {

        $scores = [];

        foreach ($criterion->activities as $activity) {
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

        $assignment->load('evaluationCriteria.activities');

        $final = 0;
        $hasData = false;

        foreach ($assignment->evaluationCriteria as $criterion) {

            // 🔹 CRITERIO DE ASISTENCIA
            if ($criterion->isAttendance()) {
                $attendanceScore = $this->attendanceService
                    ->attendancePercentage($assignment, $student, $from, $to);
            
                $final += ($attendanceScore * $criterion->percentage) / 100;
                continue;
            }

            // 🔹 CRITERIOS ACADÉMICOS
            $average = $this->criterionAverage(
                $assignment,
                $student,
                $criterion
            );

            if ($average === null) {
                continue;
            }

            $final += ($average * $criterion->percentage) / 100;
            $hasData = true;
        }

        return $hasData
            ? round($final, 2)
            : null;
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

        $assignment->load('evaluationCriteria.activities');

        $rows = [];
        $final = 0;

        foreach ($assignment->evaluationCriteria as $criterion) {

            /*
            |--------------------------------------------------
            | CRITERIO DE ASISTENCIA
            |--------------------------------------------------
            */
            if ($criterion->isAttendance()) {
                $attendancePercentage = $this->attendanceService
                    ->attendancePercentage($assignment, $student, $from, $to);
                
                $attendanceScore = $attendancePercentage / 10;
            
                $contribution = ($attendanceScore * $criterion->percentage) / 100;

                $final += $contribution;
                $rows[] = [
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
                $criterion
            );

            $average = $average ?? 0;

            $contribution = ($average * $criterion->percentage) / 100;

            $rows[] = [
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
}
