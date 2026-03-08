<?php

namespace App\Http\Controllers\Coordination;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

class CoordinationAttendanceRiskController extends Controller
{
    public function index()
    {
        // Reglas institucionales (luego pueden ir a config)
        $fromDate = now()->subDays(7);
        $minAbsences = 3;

        $students = Attendance::query()
            ->join(
                'academic_sessions',
                'academic_sessions.id',
                '=',
                'attendances.academic_session_id'
            )
            ->where('attendances.status', 'absent')
            ->whereDate('academic_sessions.session_date', '>=', $fromDate)

            // Excluir asistencias justificadas por rango de fechas
            ->whereNotExists(function ($query) {
                $query->selectRaw(1)
                    ->from('attendance_justifications')
                    ->whereColumn(
                        'attendance_justifications.student_id',
                        'attendances.student_id'
                    )
                    ->whereColumn(
                        'academic_sessions.session_date',
                        '>=',
                        'attendance_justifications.from_date'
                    )
                    ->whereColumn(
                        'academic_sessions.session_date',
                        '<=',
                        'attendance_justifications.to_date'
                    );
            })

            // Agrupar por alumno
            ->select(
                'attendances.student_id',
                DB::raw('COUNT(*) as absences'),
                DB::raw('MAX(academic_sessions.session_date) as last_absence')
            )
            ->groupBy('attendances.student_id')
            ->having('absences', '>=', $minAbsences)

            // Cargar relaciones necesarias para la vista
            ->with([
                'student.user',
                'student.group',
            ])

            // Priorizar a quienes tienen más faltas
            ->orderByDesc('absences')
            ->get();

        return view(
            'coordination.students.attendance-risk',
            compact('students')
        );
    }
}