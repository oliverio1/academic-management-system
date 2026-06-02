<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Student;
use App\Models\Group;
use App\Models\Attendance;
use App\Models\AttendanceJustification;
use App\Models\StudentFollowUp;
use App\Models\AcademicSession;
use App\Models\AcademicPeriod;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;

class DashboardService
{
    protected Carbon $fromDate;
    protected ?int $campusId;
    protected array $memo = [];

    public function __construct(?Carbon $fromDate = null, ?int $campusId = null)
    {
        $this->fromDate = $fromDate ?? now()->subDays(7);
        $this->campusId = $campusId && $campusId > 0 ? $campusId : null;
    }

    /* ===========================
     |  ALERTAS (CAPA 1)
     =========================== */

    public function alerts(): array
    {
        return [
            'critical_followups'        => $this->criticalFollowUps(),
            'students_attendance_risk'  => $this->studentsAttendanceRisk(),
            'students_class_skips'      => $this->studentsClassSkips(),
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
        if (array_key_exists(__FUNCTION__, $this->memo)) {
            return $this->memo[__FUNCTION__];
        }

        $query = StudentFollowUp::where('status', 'open')
            ->whereHas('teachers')
            ->whereDoesntHave('teacherResponses')
            ->where('created_at', '<=', now()->subDays(7));

        if ($this->campusId) {
            $groupIds = $this->campusGroupIds();
            if (empty($groupIds)) {
                return $this->memo[__FUNCTION__] = 0;
            }
            $query->whereHas('student', fn ($q) => $q->whereIn('group_id', $groupIds));
        }

        return $this->memo[__FUNCTION__] = $query->count();
    }

    protected function studentsAttendanceRisk(): int
    {
        if (array_key_exists(__FUNCTION__, $this->memo)) {
            return $this->memo[__FUNCTION__];
        }

        $query = Attendance::join(
                'academic_sessions',
                'academic_sessions.id',
                '=',
                'attendances.academic_session_id'
            )
            ->join('teaching_assignments', 'teaching_assignments.id', '=', 'academic_sessions.teaching_assignment_id')
            ->join('school_cycle_groups', 'school_cycle_groups.id', '=', 'teaching_assignments.school_cycle_group_id')
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
            ->when($this->campusId, fn ($q) => $q->where('school_cycle_groups.campus_id', $this->campusId))
            ->select('attendances.student_id')
            ->groupBy('attendances.student_id')
            ->havingRaw('COUNT(*) >= 3');

        return $this->memo[__FUNCTION__] = $query->count();
    }

    protected function teachersLowAttendance(): int
    {
        if (array_key_exists(__FUNCTION__, $this->memo)) {
            return $this->memo[__FUNCTION__];
        }

        $today = now()->toDateString();
        $activeCycleStarted = SchoolCycle::query()
            ->where('is_active', true)
            ->when($this->campusId, fn ($q) => $q->where('campus_id', $this->campusId))
            ->whereDate('start_date', '<=', $today)
            ->exists();

        if (! $activeCycleStarted) {
            return $this->memo[__FUNCTION__] = 0;
        }

        $graceLimitDate = now()->subDays(7)->toDateString();

        $activePeriodId = AcademicPeriod::query()
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->value('id');

        $query = AcademicSession::query()
            ->join('teaching_assignments', 'teaching_assignments.id', '=', 'academic_sessions.teaching_assignment_id')
            ->join('school_cycle_groups', 'school_cycle_groups.id', '=', 'teaching_assignments.school_cycle_group_id')
            ->join('teachers', 'teachers.id', '=', 'teaching_assignments.teacher_id')
            ->whereDate('academic_sessions.session_date', '<=', $graceLimitDate)
            ->where('academic_sessions.is_cancelled', false)
            ->where('teachers.is_active', true)
            ->where(function ($q) {
                $q->whereDoesntHave('attendances')
                    ->orWhereDoesntHave('sessionActivity');
            });

        if ($this->campusId) {
            $query->where('school_cycle_groups.campus_id', $this->campusId);
        }

        if ($activePeriodId) {
            $query->where('academic_sessions.academic_period_id', $activePeriodId);
        }

        return $this->memo[__FUNCTION__] = (int) $query
            ->distinct('teaching_assignments.teacher_id')
            ->count('teaching_assignments.teacher_id');
    }

    protected function studentsClassSkips(): int
    {
        if (array_key_exists(__FUNCTION__, $this->memo)) {
            return $this->memo[__FUNCTION__];
        }

        $activePeriodIds = AcademicPeriod::query()
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($activePeriodIds)) {
            return $this->memo[__FUNCTION__] = 0;
        }

        $count = Attendance::query()
            ->join('academic_sessions', 'academic_sessions.id', '=', 'attendances.academic_session_id')
            ->join('teaching_assignments', 'teaching_assignments.id', '=', 'academic_sessions.teaching_assignment_id')
            ->join('school_cycle_groups', 'school_cycle_groups.id', '=', 'teaching_assignments.school_cycle_group_id')
            ->join('prefect_daily_attendances as pda', function ($join) {
                $join->on('pda.student_id', '=', 'attendances.student_id')
                    ->whereRaw('DATE(pda.attendance_date) = DATE(academic_sessions.session_date)')
                    ->whereIn('pda.status', ['present', 'late', 'justified']);
            })
            ->where('attendances.status', 'absent')
            ->whereIn('academic_sessions.academic_period_id', $activePeriodIds)
            ->where('academic_sessions.is_cancelled', false)
            ->when($this->campusId, fn ($q) => $q->where('school_cycle_groups.campus_id', $this->campusId))
            ->distinct('attendances.student_id')
            ->count('attendances.student_id');

        return $this->memo[__FUNCTION__] = (int) $count;
    }

    protected function groupsInAlert(): int
    {
        if (array_key_exists(__FUNCTION__, $this->memo)) {
            return $this->memo[__FUNCTION__];
        }

        $activePeriodsByModality = AcademicPeriod::query()
            ->where('is_active', true)
            ->get(['id', 'modality_id'])
            ->pluck('id', 'modality_id');

        if ($activePeriodsByModality->isEmpty()) {
            return $this->memo[__FUNCTION__] = 0;
        }

        $periodIds = $activePeriodsByModality->values()->all();

        $groups = Group::query()
            ->where('is_active', true)
            ->with('level:id,modality_id')
            ->withCount([
                'students as active_students_count' => fn ($q) => $q->where('is_active', true),
            ])
            ->get(['id', 'level_id']);

        if ($this->campusId) {
            $allowed = $this->campusGroupIds();
            $groups = $groups->whereIn('id', $allowed)->values();
        }

        if ($groups->isEmpty()) {
            return $this->memo[__FUNCTION__] = 0;
        }

        $groupIds = $groups->pluck('id')->all();

        $gradeAvgByGroup = \App\Models\Grade::query()
            ->join('activities', 'activities.id', '=', 'grades.activity_id')
            ->join('teaching_assignments', 'teaching_assignments.id', '=', 'activities.teaching_assignment_id')
            ->whereIn('teaching_assignments.group_id', $groupIds)
            ->whereIn('activities.academic_period_id', $periodIds)
            ->groupBy('teaching_assignments.group_id')
            ->selectRaw('teaching_assignments.group_id as group_id, AVG(grades.score) as avg_grade')
            ->pluck('avg_grade', 'group_id');

        $sessionsByGroup = AcademicSession::query()
            ->join('teaching_assignments', 'teaching_assignments.id', '=', 'academic_sessions.teaching_assignment_id')
            ->whereIn('teaching_assignments.group_id', $groupIds)
            ->whereIn('academic_sessions.academic_period_id', $periodIds)
            ->where('academic_sessions.is_cancelled', false)
            ->groupBy('teaching_assignments.group_id')
            ->selectRaw('teaching_assignments.group_id as group_id, COUNT(academic_sessions.id) as total_sessions')
            ->pluck('total_sessions', 'group_id');

        $presentByGroup = Attendance::query()
            ->join('academic_sessions', 'academic_sessions.id', '=', 'attendances.academic_session_id')
            ->join('teaching_assignments', 'teaching_assignments.id', '=', 'academic_sessions.teaching_assignment_id')
            ->whereIn('teaching_assignments.group_id', $groupIds)
            ->whereIn('academic_sessions.academic_period_id', $periodIds)
            ->where('academic_sessions.is_cancelled', false)
            ->whereIn('attendances.status', ['present', 'late'])
            ->groupBy('teaching_assignments.group_id')
            ->selectRaw('teaching_assignments.group_id as group_id, COUNT(attendances.id) as present_count')
            ->pluck('present_count', 'group_id');

        $groupsInRisk = 0;

        foreach ($groups as $group) {
            $modalityId = (int) ($group->level->modality_id ?? 0);
            $activePeriodId = (int) ($activePeriodsByModality[$modalityId] ?? 0);

            if (! $activePeriodId) {
                continue;
            }

            $studentsCount = (int) $group->active_students_count;
            $totalSessions = (int) ($sessionsByGroup[$group->id] ?? 0);
            $groupAverage = $gradeAvgByGroup[$group->id] ?? null;

            if ($studentsCount <= 0 || $totalSessions <= 0 || $groupAverage === null) {
                continue;
            }

            $presentCount = (int) ($presentByGroup[$group->id] ?? 0);
            $totalExpected = $totalSessions * $studentsCount;
            $attendancePercentage = $totalExpected > 0
                ? ($presentCount / $totalExpected) * 100
                : 0;

            if ((float) $groupAverage < 6 && $attendancePercentage < 80) {
                $groupsInRisk++;
            }
        }

        return $this->memo[__FUNCTION__] = $groupsInRisk;
    }

    /* ===========================
     |  MÉTRICAS
     =========================== */

    protected function globalAttendance(): float
    {
        if (array_key_exists(__FUNCTION__, $this->memo)) {
            return $this->memo[__FUNCTION__];
        }

        $total = Attendance::join(
                'academic_sessions',
                'academic_sessions.id',
                '=',
                'attendances.academic_session_id'
            )
            ->join('teaching_assignments', 'teaching_assignments.id', '=', 'academic_sessions.teaching_assignment_id')
            ->join('school_cycle_groups', 'school_cycle_groups.id', '=', 'teaching_assignments.school_cycle_group_id')
            ->whereDate('academic_sessions.session_date', '>=', $this->fromDate)
            ->when($this->campusId, fn ($q) => $q->where('school_cycle_groups.campus_id', $this->campusId))
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
            return $this->memo[__FUNCTION__] = 0;
        }

        $present = Attendance::join(
                'academic_sessions',
                'academic_sessions.id',
                '=',
                'attendances.academic_session_id'
            )
            ->join('teaching_assignments', 'teaching_assignments.id', '=', 'academic_sessions.teaching_assignment_id')
            ->join('school_cycle_groups', 'school_cycle_groups.id', '=', 'teaching_assignments.school_cycle_group_id')
            ->whereDate('academic_sessions.session_date', '>=', $this->fromDate)
            ->where('attendances.status', 'present')
            ->when($this->campusId, fn ($q) => $q->where('school_cycle_groups.campus_id', $this->campusId))
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

        return $this->memo[__FUNCTION__] = round(($present / $total) * 100, 1);
    }

    protected function studentsAtRisk(): float
    {
        if (array_key_exists(__FUNCTION__, $this->memo)) {
            return $this->memo[__FUNCTION__];
        }

        $totalStudents = $this->campusId
            ? Student::query()->whereIn('group_id', $this->campusGroupIds())->count()
            : Student::count();

        if ($totalStudents === 0) {
            return $this->memo[__FUNCTION__] = 0;
        }

        $studentsWithFollowUps = StudentFollowUp::query()
            ->where('status', 'open')
            ->when(
                $this->campusId,
                fn ($q) => $q->whereHas('student', fn ($qq) => $qq->whereIn('group_id', $this->campusGroupIds()))
            )
            ->distinct('student_id')
            ->count('student_id');

        $studentsWithAttendanceRisk = $this->studentsAttendanceRisk();

        $studentsInRisk = max(
            $studentsWithFollowUps,
            $studentsWithAttendanceRisk
        );

        return $this->memo[__FUNCTION__] = round(($studentsInRisk / $totalStudents) * 100, 1);
    }

    protected function activeFollowUps(): int
    {
        if (array_key_exists(__FUNCTION__, $this->memo)) {
            return $this->memo[__FUNCTION__];
        }

        $query = StudentFollowUp::query()->where('status', 'open');

        if ($this->campusId) {
            $query->whereHas('student', fn ($q) => $q->whereIn('group_id', $this->campusGroupIds()));
        }

        return $this->memo[__FUNCTION__] = $query->count();
    }

    protected function activeJustifications(): int
    {
        if (array_key_exists(__FUNCTION__, $this->memo)) {
            return $this->memo[__FUNCTION__];
        }

        $query = AttendanceJustification::whereDate('from_date', '<=', now())
            ->whereDate('to_date', '>=', now())
            ->when(
                $this->campusId,
                fn ($q) => $q->whereHas('student', fn ($qq) => $qq->whereIn('group_id', $this->campusGroupIds()))
            );

        return $this->memo[__FUNCTION__] = $query->count();
    }

    protected function campusGroupIds(): array
    {
        $key = __FUNCTION__;
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        if (! $this->campusId) {
            return $this->memo[$key] = [];
        }

        $groupIds = SchoolCycleGroup::query()
            ->where('campus_id', $this->campusId)
            ->where('is_active', true)
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->memo[$key] = $groupIds;
    }
}
