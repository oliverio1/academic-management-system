<?php

namespace App\Services;

use App\Models\TeachingAssignment;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\AcademicSession;

class TeacherPerformanceService
{
    public function buildTable(TeachingAssignment $assignment): array
    {
        $assignment->load([
            'group.students.user',
            'evaluationCriteria.activities',
            'activities.grades',
            'academicSessions.attendances',
        ]);
    
        $students = $assignment->group->students;
        $rows = [];
    
        foreach ($students as $student) {
    
            // -----------------------------
            // ASISTENCIA
            // -----------------------------
            $attendances = $assignment->academicSessions
                ->flatMap->attendances
                ->where('student_id', $student->id);
    
            $totalSessions = $attendances
                ->unique(fn ($a) => $a->class_date.'-'.$a->academic_session_id)
                ->count();
    
            $present = $attendances
                ->where('status', 'present')
                ->unique(fn ($a) => $a->class_date.'-'.$a->academic_session_id)
                ->count();
    
            $attendancePct = $totalSessions
                ? round(($present / $totalSessions) * 100, 1)
                : 0;
    
            // escala 0–10
            $attendanceScore = round($attendancePct / 10, 2);
    
            // -----------------------------
            // CRITERIOS
            // -----------------------------
            $criteriaScores = [];
            $final = 0;
    
            foreach ($assignment->evaluationCriteria as $criterion) {
    
                // 🔑 CRITERIO ASISTENCIA
                if (strtolower(trim($criterion->name)) === 'asistencia') {
    
                    $criteriaScores[$criterion->name] = $attendanceScore;
    
                    $final += ($attendanceScore * $criterion->percentage) / 100;
                    continue;
                }
    
                // CRITERIOS ACADÉMICOS
                $activityIds = $criterion->activities->pluck('id');
    
                if ($activityIds->isEmpty()) {
                    $criteriaScores[$criterion->name] = 0;
                    continue;
                }
    
                $avg = Grade::whereIn('activity_id', $activityIds)
                    ->where('student_id', $student->id)
                    ->avg('score') ?? 0;
    
                $avg = round($avg, 2);
    
                $criteriaScores[$criterion->name] = $avg;
                $final += ($avg * $criterion->percentage) / 100;
            }
    
            $rows[] = [
                'student_id' => $student->id,
                'student'    => $student->user->name,
                'attendance' => $attendancePct, // si aún lo necesitas en %
                'criteria'   => $criteriaScores,
                'final'      => round($final, 2),
            ];
        }
    
        return $rows;
    }    
}
