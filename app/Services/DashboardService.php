<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Student;
use App\Models\Attendance;
use App\Models\AttendanceJustification;
use App\Models\StudentFollowUp;
use App\Models\TeachingAssignment;
use App\Models\AcademicSession;

class DashboardService
{
    protected Carbon $fromDate;

    public function __construct(?Carbon $fromDate = null)
    {
        $this->fromDate = $fromDate ?? now()->subDays(7);
    }

    /* ===========================
     |  ALERTAS (CAPA 1)
     =========================== */

    public function alerts(): array
    {
        return [
            'critical_followups'        => $this->criticalFollowUps(),
            'students_attendance_risk'  => $this->studentsAttendanceRisk(),
            'teachers_low_attendance'   => $this->teachersLowAttendance(),
            'groups_in_alert'           => $this->groupsInAlert(),
        ];
    }

    /* ===========================
     |  MÉTRICAS (CAPA 2)
     =========================== */

    public function metrics(): array
    {
        return [
            'global_attendance'     => $this->globalAttendance(),
            'students_at_risk'      => $this->studentsAtRisk(),
            'active_followups'      => $this->activeFollowUps(),
            'active_justifications' => $this->activeJustifications(),
            'groups_in_alert'       => $this->groupsInAlert(),
        ];
    }

    /* ===========================
     |  ALERTAS
     =========================== */

    protected function criticalFollowUps(): int
    {
        return StudentFollowUp::where('status', 'open')
            ->whereHas('teachers')
            ->whereDoesntHave('teacherResponses')
            ->where('created_at', '<=', now()->subDays(7))
            ->count();
    }

    protected function studentsAttendanceRisk(): int
    {
        return Attendance::join(
                'academic_sessions',
                'academic_sessions.id',
                '=',
                'attendances.academic_session_id'
            )
            ->where('attendances.status', 'absent')
            ->whereDate('academic_sessions.session_date', '>=', $this->fromDate)
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
            ->select('attendances.student_id')
            ->groupBy('attendances.student_id')
            ->havingRaw('COUNT(*) >= 3')
            ->count();
    }

    protected function teachersLowAttendance(): int
    {
        $sessions = AcademicSession::whereDate('session_date', '>=', $this->fromDate)
            ->where('is_cancelled', false)
            ->get()
            ->groupBy('teaching_assignment_id');

        $teachersInRisk = 0;

        foreach ($sessions as $assignmentId => $sessionsGroup) {

            $totalSessions = $sessionsGroup->count();

            if ($totalSessions === 0) {
                continue;
            }

            $sessionsWithAttendance = Attendance::whereIn(
                'academic_session_id',
                $sessionsGroup->pluck('id')
            )
            ->distinct('academic_session_id')
            ->count();

            $percentage = ($sessionsWithAttendance / $totalSessions) * 100;

            if ($percentage < 80) {
                $teachersInRisk++;
            }
        }

        return $teachersInRisk;
    }

    protected function groupsInAlert(): int
    {
        $groups = TeachingAssignment::with('group')->get()->groupBy('group_id');

        $groupsInRisk = 0;

        foreach ($groups as $groupAssignments) {

            $sessions = AcademicSession::whereIn(
                    'teaching_assignment_id',
                    $groupAssignments->pluck('id')
                )
                ->whereDate('session_date', '>=', $this->fromDate)
                ->where('is_cancelled', false)
                ->get();

            if ($sessions->isEmpty()) {
                continue;
            }

            $total = Attendance::whereIn(
                'academic_session_id',
                $sessions->pluck('id')
            )->count();

            if ($total === 0) {
                continue;
            }

            $present = Attendance::whereIn(
                'academic_session_id',
                $sessions->pluck('id')
            )
            ->where('status', 'present')
            ->count();

            $percentage = ($present / $total) * 100;

            if ($percentage < 80) {
                $groupsInRisk++;
            }
        }

        return $groupsInRisk;
    }

    /* ===========================
     |  MÉTRICAS
     =========================== */

    protected function globalAttendance(): float
    {
        $total = Attendance::join(
                'academic_sessions',
                'academic_sessions.id',
                '=',
                'attendances.academic_session_id'
            )
            ->whereDate('academic_sessions.session_date', '>=', $this->fromDate)
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
            ->count();

        if ($total === 0) {
            return 0;
        }

        $present = Attendance::join(
                'academic_sessions',
                'academic_sessions.id',
                '=',
                'attendances.academic_session_id'
            )
            ->whereDate('academic_sessions.session_date', '>=', $this->fromDate)
            ->where('attendances.status', 'present')
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
            ->count();

        return round(($present / $total) * 100, 1);
    }

    protected function studentsAtRisk(): float
    {
        $totalStudents = Student::count();

        if ($totalStudents === 0) {
            return 0;
        }

        $studentsWithFollowUps = StudentFollowUp::where('status', 'open')
            ->distinct('student_id')
            ->count('student_id');

        $studentsWithAttendanceRisk = $this->studentsAttendanceRisk();

        $studentsInRisk = max(
            $studentsWithFollowUps,
            $studentsWithAttendanceRisk
        );

        return round(($studentsInRisk / $totalStudents) * 100, 1);
    }

    protected function activeFollowUps(): int
    {
        return StudentFollowUp::where('status', 'open')->count();
    }

    protected function activeJustifications(): int
    {
        return AttendanceJustification::whereDate('from_date', '<=', now())
            ->whereDate('to_date', '>=', now())
            ->count();
    }
}
