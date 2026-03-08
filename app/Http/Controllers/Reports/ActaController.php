<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\TeachingAssignment;
use App\Services\GradeService;
use App\Services\AttendanceService;
use Barryvdh\Snappy\Facades\SnappyPdf;

class ActaController extends Controller
{
    public function calificaciones(
        TeachingAssignment $teachingAssignment,
        GradeService $gradeService,
        AttendanceService $attendanceService
    ) {
        // Seguridad (coordinador / admin)
        abort_unless(
            auth()->user()->hasRole('coordinator') ||
            auth()->user()->teacher?->id === $teachingAssignment->teacher_id,
            403
        );

        $students = $teachingAssignment->group
            ->students()
            ->where('is_active', true)
            ->with('user')
            ->orderBy('enrollment_number')
            ->get();

        $rows = $students->map(function ($student, $index) use (
            $teachingAssignment,
            $gradeService,
            $attendanceService
        ) {
            return [
                'num'        => $index + 1,
                'enrollment' => $student->enrollment_number,
                'name'       => $student->user->name,
                'grade'      => $gradeService->finalGrade(
                    $teachingAssignment,
                    $student
                ),
                'attendance' => round(
                    ($attendanceService->attendancePercentage(
                        $teachingAssignment,
                        $student
                    ) / 10) * 100,
                    1
                ),
            ];
        });

        return SnappyPdf::loadView(
            'actas.calificaciones',
            compact('teachingAssignment', 'rows')
        )
        ->setPaper('letter')
        ->setOption('encoding', 'UTF-8')
        ->setOption('disable-javascript', true)
        ->setOption('enable-local-file-access', true)
        ->download(
            'ACTA_CALIFICACIONES_'.$teachingAssignment->group->name.'.pdf'
        );
    }
}