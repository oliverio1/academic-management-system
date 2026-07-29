<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

class CoordinationClassSkipController extends Controller
{
    public function index()
    {
        $activeCampusId = (int) session('active_campus_id', 0);

        $activePeriodIds = AcademicPeriod::query()
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rows = collect();

        if (! empty($activePeriodIds)) {
            $driver = DB::connection()->getDriverName();
            $missedDatesSql = 'GROUP_CONCAT(
                DISTINCT DATE_FORMAT(academic_sessions.session_date, "%Y-%m-%d")
                ORDER BY academic_sessions.session_date
                SEPARATOR ", "
            )';
            $subjectsSql = 'GROUP_CONCAT(DISTINCT subjects.name ORDER BY subjects.name SEPARATOR ", ")';

            if ($driver === 'sqlite') {
                $missedDatesSql = 'GROUP_CONCAT(DISTINCT DATE(academic_sessions.session_date))';
                $subjectsSql = 'GROUP_CONCAT(DISTINCT subjects.name)';
            }

            $rows = Attendance::query()
                ->join('students', 'students.id', '=', 'attendances.student_id')
                ->join('users', 'users.id', '=', 'students.user_id')
                ->join('groups', 'groups.id', '=', 'students.group_id')
                ->join('academic_sessions', 'academic_sessions.id', '=', 'attendances.academic_session_id')
                ->join('teaching_assignments', 'teaching_assignments.id', '=', 'academic_sessions.teaching_assignment_id')
                ->join('school_cycle_groups', 'school_cycle_groups.id', '=', 'teaching_assignments.school_cycle_group_id')
                ->join('subjects', 'subjects.id', '=', 'teaching_assignments.subject_id')
                ->join('prefect_daily_attendances as pda', function ($join) {
                    $join->on('pda.student_id', '=', 'attendances.student_id')
                        ->whereRaw('DATE(pda.attendance_date) = DATE(academic_sessions.session_date)')
                        ->whereIn('pda.status', ['present', 'late', 'justified']);
                })
                ->where('attendances.status', 'absent')
                ->whereIn('academic_sessions.academic_period_id', $activePeriodIds)
                ->where('academic_sessions.is_cancelled', false)
                ->where('school_cycle_groups.is_active', true)
                ->when($activeCampusId > 0, fn ($q) => $q->where('school_cycle_groups.campus_id', $activeCampusId))
                ->selectRaw("
                    students.id as student_id,
                    users.name as student_name,
                    students.enrollment_number as enrollment_number,
                    groups.id as group_id,
                    groups.name as group_name,
                    COUNT(attendances.id) as missed_sessions,
                    COUNT(DISTINCT DATE(academic_sessions.session_date)) as missed_days,
                    {$missedDatesSql} as missed_dates,
                    {$subjectsSql} as subjects_affected
                ")
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
