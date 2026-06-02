<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\Attendance;

class CoordinationClassSkipController extends Controller
{
    public function index()
    {
        $activePeriodIds = AcademicPeriod::query()
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rows = collect();

        if (! empty($activePeriodIds)) {
            $rows = Attendance::query()
                ->join('students', 'students.id', '=', 'attendances.student_id')
                ->join('users', 'users.id', '=', 'students.user_id')
                ->join('groups', 'groups.id', '=', 'students.group_id')
                ->join('academic_sessions', 'academic_sessions.id', '=', 'attendances.academic_session_id')
                ->join('teaching_assignments', 'teaching_assignments.id', '=', 'academic_sessions.teaching_assignment_id')
                ->join('subjects', 'subjects.id', '=', 'teaching_assignments.subject_id')
                ->join('prefect_daily_attendances as pda', function ($join) {
                    $join->on('pda.student_id', '=', 'attendances.student_id')
                        ->whereRaw('DATE(pda.attendance_date) = DATE(academic_sessions.session_date)')
                        ->whereIn('pda.status', ['present', 'late', 'justified']);
                })
                ->where('attendances.status', 'absent')
                ->whereIn('academic_sessions.academic_period_id', $activePeriodIds)
                ->where('academic_sessions.is_cancelled', false)
                ->selectRaw('
                    students.id as student_id,
                    users.name as student_name,
                    students.enrollment_number as enrollment_number,
                    groups.id as group_id,
                    groups.name as group_name,
                    COUNT(attendances.id) as missed_sessions,
                    COUNT(DISTINCT DATE(academic_sessions.session_date)) as missed_days,
                    GROUP_CONCAT(
                        DISTINCT DATE_FORMAT(academic_sessions.session_date, "%Y-%m-%d")
                        ORDER BY academic_sessions.session_date
                        SEPARATOR ", "
                    ) as missed_dates,
                    GROUP_CONCAT(DISTINCT subjects.name ORDER BY subjects.name SEPARATOR ", ") as subjects_affected
                ')
                ->groupBy(
                    'students.id',
                    'users.name',
                    'students.enrollment_number',
                    'groups.id',
                    'groups.name'
                )
                ->orderByDesc('missed_sessions')
                ->orderBy('users.name')
                ->get();
        }

        return view('coordination.students.class-skips', [
            'rows' => $rows,
        ]);
    }
}
