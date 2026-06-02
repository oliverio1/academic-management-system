<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Activity;
use App\Models\StudentFollowUp;
use App\Models\TeachingAssignment;
use App\Models\AcademicSession;
use App\Models\AcademicPeriod;
use App\Models\SchoolCycle;
use App\Models\CyclePartial;
use App\Models\SchoolCycleGroup;
use App\Models\Group;
use App\Models\AcademicCalendarDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\AcademicCalendarService;
use App\Services\StudentFollowUpFlagService;
use App\Services\AcademicPerformanceService;
use App\Services\AttendanceService;
use App\Models\PrefectDailyAttendance;

class CoordinationStudentController extends Controller
{
    public function activeCycleRoster(Request $request)
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $activeCycle = $this->tenantCyclesQuery()
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first();

        $groupIds = collect();
        $groups = collect();

        if ($activeCycle) {
            $groupIds = SchoolCycleGroup::query()
                ->where('school_cycle_id', $activeCycle->id)
                ->where('is_active', true)
                ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
                ->pluck('group_id')
                ->map(fn ($id) => (int) $id)
                ->values();

            $groups = Group::query()
                ->whereIn('id', $groupIds->all())
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        $selectedGroupId = $request->integer('group_id') ?: null;
        $status = $request->query('status', 'all');

        if ($selectedGroupId && ! $groups->pluck('id')->contains($selectedGroupId)) {
            $selectedGroupId = null;
        }

        if (! in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
        }

        $students = Student::query()
            ->with(['user', 'group'])
            ->when(
                $groupIds->isNotEmpty(),
                fn ($q) => $q->whereIn('group_id', $groupIds->all()),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->when($selectedGroupId, fn ($q) => $q->where('group_id', $selectedGroupId))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->get()
            ->sortBy(fn ($student) => mb_strtolower($student->user->name ?? ''))
            ->values();

        return view('coordination.students.active-cycle', [
            'activeCycle' => $activeCycle,
            'groups' => $groups,
            'students' => $students,
            'selectedGroupId' => $selectedGroupId,
            'status' => $status,
        ]);
    }

    public function academicSummary(
        Request $request,
        AcademicPerformanceService $performance,
        AttendanceService $attendanceService
    ) {
        $schoolCycleId = $request->integer('school_cycle_id') ?: null;
        $activeCampusId = (int) session('active_campus_id', 0);
        $groupId = $request->integer('group_id') ?: null;
        $studentId = $request->integer('student_id') ?: null;

        $schoolCycles = $this->tenantCyclesQuery()
            ->with('modality')
            ->orderByDesc('start_date')
            ->get();

        $selectedCycle = null;
        if ($schoolCycleId) {
            $selectedCycle = $schoolCycles->firstWhere('id', $schoolCycleId);
        }
        if (! $selectedCycle) {
            $selectedCycle = $schoolCycles->firstWhere('is_active', true) ?: $schoolCycles->first();
        }

        $allowedGroupIds = collect();
        if ($selectedCycle) {
            $allowedGroupIds = SchoolCycleGroup::query()
                ->where('school_cycle_id', $selectedCycle->id)
                ->where('is_active', true)
                ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
                ->pluck('group_id')
                ->map(fn ($id) => (int) $id)
                ->values();
            $schoolCycleId = (int) $selectedCycle->id;
        }

        $groups = \App\Models\Group::query()
            ->where('is_active', true)
            ->when(
                $allowedGroupIds->isNotEmpty(),
                fn ($q) => $q->whereIn('id', $allowedGroupIds->all()),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->orderBy('name')
            ->get();

        if ($groupId && ! $groups->pluck('id')->contains($groupId)) {
            $groupId = null;
        }

        $tenantId = $this->tenantId();
        $historicalStudentIds = collect();
        if ($selectedCycle) {
            $historicalQuery = DB::table('student_assignment_historicals as h')
                ->join('teaching_assignments as ta', 'ta.id', '=', 'h.teaching_assignment_id')
                ->where('h.tenant_id', $tenantId)
                ->where('h.school_cycle_id', (int) $selectedCycle->id)
                ->when($groupId, fn ($q) => $q->where('ta.group_id', (int) $groupId));

            $historicalStudentIds = $historicalQuery
                ->distinct()
                ->pluck('h.student_id')
                ->map(fn ($id) => (int) $id)
                ->values();
        }

        $students = Student::query()
            ->with('user')
            ->when(
                $allowedGroupIds->isNotEmpty(),
                fn ($q) => $q->whereIn('group_id', $allowedGroupIds->all()),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->when($groupId, fn ($q) => $q->where('group_id', (int) $groupId))
            ->orderBy('group_id')
            ->get()
            ->sortBy(fn ($student) => mb_strtolower($student->user->name ?? ''))
            ->values();

        if ($studentId && ! $students->pluck('id')->contains($studentId)) {
            $studentId = null;
        }

        $selectedStudent = null;
        $subjectRows = collect();
        $globalAttendance = null;
        $globalAverage = null;
        $activePeriod = null;
        $activeCycle = null;
        $cyclePartials = collect();
        $partialColumns = collect();
        $globalByPartial = collect();
        $classAbsencesWhilePresentBySubject = collect();
        $dailyMatrixDates = collect();
        $dailyMatrixPrefect = collect();
        $dailyMatrixByAssignment = collect();
        $dailyMatrixSubjects = collect();

        if ($studentId) {
            $selectedStudent = Student::query()
                ->with(['user', 'group.level.modality'])
                ->whereKey($studentId)
                ->when($groupId, fn ($q) => $q->where('group_id', $groupId))
                ->first();

            if ($selectedStudent) {
                $historicalRows = collect();
                if ($selectedCycle) {
                    $historicalRows = DB::table('student_assignment_historicals as h')
                        ->join('teaching_assignments as ta', 'ta.id', '=', 'h.teaching_assignment_id')
                        ->join('subjects as s', 's.id', '=', 'ta.subject_id')
                        ->where('h.tenant_id', $tenantId)
                        ->where('h.school_cycle_id', (int) $selectedCycle->id)
                        ->where('h.student_id', (int) $selectedStudent->id)
                        ->select(
                            'h.teaching_assignment_id',
                            'h.partial_1_final',
                            'h.partial_2_final',
                            'h.final_grade',
                            'h.partial_1_attendance',
                            'h.partial_2_attendance',
                            'h.attendance_percentage',
                            's.name as subject_name'
                        )
                        ->orderBy('s.name')
                        ->get();
                }

                $activePeriod = AcademicPeriod::query()
                    ->where('modality_id', $selectedStudent->group->level->modality_id)
                    ->where('is_active', true)
                    ->first();

                $activeCycle = $selectedCycle ?: $this->tenantCyclesQuery()
                    ->where('modality_id', $selectedStudent->group->level->modality_id)
                    ->where('is_active', true)
                    ->orderByDesc('start_date')
                    ->first();

                $cyclePeriodIds = collect();
                if ($activeCycle) {
                    $cyclePeriodIds = $activeCycle->partials()
                        ->whereNotNull('academic_period_id')
                        ->pluck('academic_period_id')
                        ->map(fn ($id) => (int) $id)
                        ->values();

                    $today = now()->toDateString();
                    $cyclePartials = CyclePartial::query()
                        ->where('school_cycle_id', $activeCycle->id)
                        ->with('academicPeriod:id,name')
                        ->orderBy('sort_order')
                        ->orderBy('start_date')
                        ->get()
                        ->map(function (CyclePartial $partial) use ($today) {
                            $start = $partial->start_date?->toDateString();
                            $end = $partial->end_date?->toDateString();

                            if ($start && $today < $start) {
                                $status = 'pending';
                                $status_label = 'Aun no comienza';
                            } elseif ($end && $today > $end) {
                                $status = 'finished';
                                $status_label = 'Concluido';
                            } else {
                                $status = 'active';
                                $status_label = 'Activo';
                            }

                            return [
                                'id' => $partial->id,
                                'name' => $partial->name ?: ($partial->academicPeriod->name ?? 'Parcial'),
                                'code' => $partial->code,
                                'sort_order' => $partial->sort_order,
                                'academic_period_id' => $partial->academic_period_id,
                                'start_date' => $partial->start_date,
                                'end_date' => $partial->end_date,
                                'status' => $status,
                                'status_label' => $status_label,
                            ];
                        });

                    $partialColumns = $cyclePartials
                        ->filter(fn ($partial) => !empty($partial['academic_period_id']))
                        ->values();
                }

                $from = $activeCycle
                    ? Carbon::parse($activeCycle->start_date)->startOfDay()
                    : ($activePeriod ? Carbon::parse($activePeriod->start_date)->startOfDay() : null);
                $to = $activeCycle
                    ? Carbon::parse($activeCycle->end_date)->endOfDay()
                    : ($activePeriod ? Carbon::parse($activePeriod->end_date)->endOfDay() : null);

                $assignments = TeachingAssignment::query()
                    ->with('subject')
                    ->where('group_id', $selectedStudent->group_id)
                    ->where('is_active', true)
                    ->when(
                        $selectedCycle,
                        fn ($q) => $q->whereHas('schedules', fn ($sq) => $sq
                            ->where('school_cycle_id', (int) $selectedCycle->id)
                            ->where('is_active', true)
                        )
                    )
                    ->orderBy('subject_id')
                    ->get()
                    ->unique('subject_id')
                    ->values();

                if ($from && $to) {
                    $matrixEnd = $to->copy();
                    $today = now()->endOfDay();
                    if ($matrixEnd->greaterThan($today)) {
                        $matrixEnd = $today;
                    }

                    $modalityId = (int) optional($selectedStudent->group?->level)->modality_id;
                    $nonWorkingDates = AcademicCalendarDay::query()
                        ->whereIn('type', ['holiday', 'vacation'])
                        ->whereDate('date', '>=', $from->toDateString())
                        ->whereDate('date', '<=', $matrixEnd->toDateString())
                        ->where(function ($q) use ($modalityId) {
                            $q->whereNull('modality_id');
                            if ($modalityId > 0) {
                                $q->orWhere('modality_id', $modalityId);
                            }
                        })
                        ->pluck('date')
                        ->map(fn ($date) => Carbon::parse($date)->toDateString())
                        ->flip();

                    $dailyMatrixDates = collect();
                    $cursor = $from->copy()->startOfDay();
                    while ($cursor->lte($matrixEnd)) {
                        $date = $cursor->toDateString();
                        $isWeekend = in_array($cursor->dayOfWeekIso, [6, 7], true);
                        if (! $isWeekend && ! $nonWorkingDates->has($date)) {
                            $dailyMatrixDates->push($date);
                        }
                        $cursor->addDay();
                    }

                    $dailyMatrixPrefect = PrefectDailyAttendance::query()
                        ->where('student_id', $selectedStudent->id)
                        ->whereBetween('attendance_date', [$from->toDateString(), $matrixEnd->toDateString()])
                        ->get()
                        ->keyBy(fn ($row) => Carbon::parse($row->attendance_date)->toDateString())
                        ->map(fn ($row) => $row->status);

                    $assignmentIdsForMatrix = $assignments
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->values();

                    $dailyMatrixSubjects = $assignments->map(fn ($assignment) => [
                        'assignment_id' => (int) $assignment->id,
                        'subject_name' => $assignment->subject->name ?? 'N/D',
                    ])->values();

                    if ($assignmentIdsForMatrix->isNotEmpty()) {
                        $sessionDaysByAssignment = AcademicSession::query()
                            ->selectRaw('teaching_assignment_id, DATE(session_date) as session_day')
                            ->whereIn('teaching_assignment_id', $assignmentIdsForMatrix->all())
                            ->where('is_cancelled', false)
                            ->whereBetween('session_date', [$from, $matrixEnd])
                            ->groupBy('teaching_assignment_id', DB::raw('DATE(session_date)'))
                            ->get()
                            ->groupBy('teaching_assignment_id')
                            ->map(fn ($rows) => $rows->pluck('session_day')->map(fn ($d) => (string) $d)->values());

                        $attendanceRows = Attendance::query()
                            ->selectRaw('academic_sessions.teaching_assignment_id as assignment_id, DATE(academic_sessions.session_date) as session_day, attendances.status')
                            ->join('academic_sessions', 'attendances.academic_session_id', '=', 'academic_sessions.id')
                            ->where('attendances.student_id', $selectedStudent->id)
                            ->whereIn('academic_sessions.teaching_assignment_id', $assignmentIdsForMatrix->all())
                            ->where('academic_sessions.is_cancelled', false)
                            ->whereBetween('academic_sessions.session_date', [$from, $matrixEnd])
                            ->get()
                            ->groupBy(fn ($row) => (int) $row->assignment_id . '|' . (string) $row->session_day);

                        $statusPriority = [
                            'absent' => 4,
                            'late' => 3,
                            'justified' => 2,
                            'present' => 1,
                        ];

                        $dailyMatrixByAssignment = $assignmentIdsForMatrix->mapWithKeys(function (int $assignmentId) use (
                            $dailyMatrixDates,
                            $sessionDaysByAssignment,
                            $attendanceRows,
                            $statusPriority
                        ) {
                            $daysWithClass = collect($sessionDaysByAssignment->get($assignmentId, []))
                                ->flip();

                            $perDay = $dailyMatrixDates->mapWithKeys(function (string $date) use (
                                $assignmentId,
                                $daysWithClass,
                                $attendanceRows,
                                $statusPriority
                            ) {
                                if (! $daysWithClass->has($date)) {
                                    return [$date => 'no-class'];
                                }

                                $statuses = collect($attendanceRows->get($assignmentId . '|' . $date, []))
                                    ->pluck('status')
                                    ->filter()
                                    ->values();

                                if ($statuses->isEmpty()) {
                                    return [$date => null];
                                }

                                $selected = $statuses
                                    ->sortByDesc(fn ($status) => $statusPriority[$status] ?? 0)
                                    ->first();

                                return [$date => $selected];
                            });

                            return [$assignmentId => $perDay];
                        });
                    }
                }

                if ($partialColumns->isEmpty() && $activePeriod) {
                    $partialColumns = collect([[
                        'id' => 'active-period',
                        'name' => $activePeriod->name ?? 'Parcial',
                        'code' => $activePeriod->code ?? null,
                        'sort_order' => 1,
                        'academic_period_id' => $activePeriod->id,
                        'start_date' => $activePeriod->start_date,
                        'end_date' => $activePeriod->end_date,
                        'status' => 'active',
                        'status_label' => 'Activo',
                    ]]);
                }

                $assignmentIds = $assignments->pluck('id')->map(fn ($id) => (int) $id)->values();
                $periodIds = $partialColumns
                    ->pluck('academic_period_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();
                $finalPartialIds = $partialColumns
                    ->sortBy(fn ($partial) => (int) ($partial['sort_order'] ?? PHP_INT_MAX))
                    ->take(2)
                    ->pluck('id')
                    ->values();

                $gradeAveragesByAssignmentPeriod = collect();
                $attendanceTotalsByAssignmentPeriod = collect();
                $attendancePresentByAssignmentPeriod = collect();

                if ($assignmentIds->isNotEmpty() && $periodIds->isNotEmpty()) {
                    $gradeAveragesByAssignmentPeriod = Grade::query()
                        ->selectRaw('activities.teaching_assignment_id as assignment_id, activities.academic_period_id as period_id, AVG(grades.score) as avg_score')
                        ->join('activities', 'grades.activity_id', '=', 'activities.id')
                        ->where('grades.student_id', $selectedStudent->id)
                        ->whereIn('activities.teaching_assignment_id', $assignmentIds->all())
                        ->whereIn('activities.academic_period_id', $periodIds->all())
                        ->groupBy('activities.teaching_assignment_id', 'activities.academic_period_id')
                        ->get()
                        ->keyBy(fn ($row) => (int) $row->assignment_id.'-'.(int) $row->period_id);

                    $attendanceTotalsByAssignmentPeriod = AcademicSession::query()
                        ->selectRaw('teaching_assignment_id as assignment_id, academic_period_id as period_id, COUNT(id) as total_sessions')
                        ->whereIn('teaching_assignment_id', $assignmentIds->all())
                        ->whereIn('academic_period_id', $periodIds->all())
                        ->where('is_cancelled', false)
                        ->groupBy('teaching_assignment_id', 'academic_period_id')
                        ->get()
                        ->keyBy(fn ($row) => (int) $row->assignment_id.'-'.(int) $row->period_id);

                    $attendancePresentByAssignmentPeriod = Attendance::query()
                        ->selectRaw('academic_sessions.teaching_assignment_id as assignment_id, academic_sessions.academic_period_id as period_id, COUNT(attendances.id) as attended_sessions')
                        ->join('academic_sessions', 'attendances.academic_session_id', '=', 'academic_sessions.id')
                        ->where('attendances.student_id', $selectedStudent->id)
                        ->whereIn('attendances.status', ['present', 'late', 'justified'])
                        ->whereIn('academic_sessions.teaching_assignment_id', $assignmentIds->all())
                        ->whereIn('academic_sessions.academic_period_id', $periodIds->all())
                        ->where('academic_sessions.is_cancelled', false)
                        ->groupBy('academic_sessions.teaching_assignment_id', 'academic_sessions.academic_period_id')
                        ->get()
                        ->keyBy(fn ($row) => (int) $row->assignment_id.'-'.(int) $row->period_id);
                }

                $subjectRows = $assignments->map(function ($assignment) use (
                    $selectedStudent,
                    $performance,
                    $partialColumns,
                    $finalPartialIds,
                    $gradeAveragesByAssignmentPeriod,
                    $attendanceTotalsByAssignmentPeriod,
                    $attendancePresentByAssignmentPeriod
                ) {
                    $partials = $partialColumns->mapWithKeys(function ($partial) use (
                        $assignment,
                        $gradeAveragesByAssignmentPeriod,
                        $attendanceTotalsByAssignmentPeriod,
                        $attendancePresentByAssignmentPeriod
                    ) {
                        $periodId = (int) ($partial['academic_period_id'] ?? 0);
                        $compoundKey = (int) $assignment->id . '-' . $periodId;

                        $gradeRow = $gradeAveragesByAssignmentPeriod->get($compoundKey);
                        $grade = $gradeRow ? round((float) $gradeRow->avg_score, 2) : null;

                        $totalsRow = $attendanceTotalsByAssignmentPeriod->get($compoundKey);
                        $presentRow = $attendancePresentByAssignmentPeriod->get($compoundKey);
                        $totalSessions = (int) ($totalsRow->total_sessions ?? 0);
                        $attendedSessions = (int) ($presentRow->attended_sessions ?? 0);
                        $attendance = $totalSessions > 0
                            ? round(($attendedSessions / $totalSessions) * 100, 1)
                            : null;

                        return [
                            $partial['id'] => [
                                'attendance' => $attendance,
                                'grade' => $grade,
                            ],
                        ];
                    });

                    $finalPartialGrades = $finalPartialIds
                        ->map(fn ($partialId) => $partials[$partialId]['grade'] ?? null);
                    $finalPartialAttendance = $finalPartialIds
                        ->map(fn ($partialId) => $partials[$partialId]['attendance'] ?? null);

                    $finalGrade = $finalPartialGrades->count() > 0
                        && $finalPartialGrades->filter(fn ($value) => $value !== null)->count() === $finalPartialGrades->count()
                            ? round((float) $finalPartialGrades->avg(), 2)
                            : null;

                    $finalAttendance = $finalPartialAttendance->count() > 0
                        && $finalPartialAttendance->filter(fn ($value) => $value !== null)->count() === $finalPartialAttendance->count()
                            ? round((float) $finalPartialAttendance->avg(), 1)
                            : null;

                    return [
                        'assignment' => $assignment,
                        'subject_name' => $assignment->subject->name ?? 'N/D',
                        'attendance' => $finalAttendance,
                        'final_grade' => $finalGrade,
                        'partials' => $partials,
                    ];
                });

                if ($historicalRows->isNotEmpty()) {
                    $partialBySort = $partialColumns
                        ->sortBy(fn ($partial) => (int) ($partial['sort_order'] ?? 0))
                        ->values();
                    $partial1 = $partialBySort->get(0);
                    $partial2 = $partialBySort->get(1);

                    $subjectRows = $historicalRows->map(function ($row) use ($partial1, $partial2) {
                        $partials = collect();

                        if ($partial1) {
                            $partials->put($partial1['id'], [
                                'attendance' => $row->partial_1_attendance !== null ? round((float) $row->partial_1_attendance, 1) : null,
                                'grade' => $row->partial_1_final !== null ? round((float) $row->partial_1_final, 2) : null,
                            ]);
                        }

                        if ($partial2) {
                            $partials->put($partial2['id'], [
                                'attendance' => $row->partial_2_attendance !== null ? round((float) $row->partial_2_attendance, 1) : null,
                                'grade' => $row->partial_2_final !== null ? round((float) $row->partial_2_final, 2) : null,
                            ]);
                        }

                        return [
                            'assignment' => null,
                            'subject_name' => $row->subject_name ?? 'N/D',
                            'attendance' => $row->attendance_percentage !== null ? round((float) $row->attendance_percentage, 1) : null,
                            'final_grade' => $row->final_grade !== null ? round((float) $row->final_grade, 2) : null,
                            'partials' => $partials,
                        ];
                    })->values();
                }

                $globalByPartial = $partialColumns->mapWithKeys(function ($partial) use ($subjectRows) {
                    $partialId = $partial['id'];
                    $attendanceValues = $subjectRows
                        ->pluck("partials.$partialId.attendance")
                        ->filter(fn ($v) => $v !== null)
                        ->values();
                    $gradeValues = $subjectRows
                        ->pluck("partials.$partialId.grade")
                        ->filter(fn ($v) => $v !== null)
                        ->values();

                    return [
                        $partialId => [
                            'attendance' => $attendanceValues->isNotEmpty()
                                ? round($attendanceValues->avg(), 1)
                                : null,
                            'grade' => $gradeValues->isNotEmpty()
                                ? round($gradeValues->avg(), 2)
                                : null,
                        ],
                    ];
                });

                $totalAttendanceRows = PrefectDailyAttendance::query()
                    ->where('student_id', $selectedStudent->id)
                    ->when($from && $to, fn ($q) => $q->whereBetween('attendance_date', [
                        $from->toDateString(),
                        $to->toDateString(),
                    ]))
                    ->count();

                $attendedRows = PrefectDailyAttendance::query()
                    ->where('student_id', $selectedStudent->id)
                    ->whereIn('status', ['present', 'late', 'justified'])
                    ->when($from && $to, fn ($q) => $q->whereBetween('attendance_date', [
                        $from->toDateString(),
                        $to->toDateString(),
                    ]))
                    ->count();

                $globalAttendance = $totalAttendanceRows > 0
                    ? round(($attendedRows / $totalAttendanceRows) * 100, 1)
                    : 0.0;

                $gradeValues = $subjectRows
                    ->pluck('final_grade')
                    ->filter(fn ($v) => $v !== null)
                    ->values();

                $globalAverage = $gradeValues->isNotEmpty()
                    ? round($gradeValues->avg(), 2)
                    : null;

                $presentDates = PrefectDailyAttendance::query()
                    ->where('student_id', $selectedStudent->id)
                    ->whereIn('status', ['present', 'late', 'justified'])
                    ->when($from && $to, fn ($q) => $q->whereBetween('attendance_date', [
                        $from->toDateString(),
                        $to->toDateString(),
                    ]))
                    ->pluck('attendance_date')
                    ->map(fn ($date) => Carbon::parse($date)->toDateString())
                    ->unique()
                    ->values();

                if ($presentDates->isNotEmpty()) {
                    $classAbsencesWhilePresentBySubject = AcademicSession::query()
                        ->selectRaw('
                            subjects.id as subject_id,
                            subjects.name as subject_name,
                            COUNT(attendances.id) as missed_sessions,
                            COUNT(DISTINCT DATE(academic_sessions.session_date)) as missed_days
                        ')
                        ->join('attendances', function ($join) use ($selectedStudent) {
                            $join->on('attendances.academic_session_id', '=', 'academic_sessions.id')
                                ->where('attendances.student_id', '=', $selectedStudent->id)
                                ->where('attendances.status', '=', 'absent');
                        })
                        ->join('teaching_assignments', 'academic_sessions.teaching_assignment_id', '=', 'teaching_assignments.id')
                        ->join('subjects', 'teaching_assignments.subject_id', '=', 'subjects.id')
                        ->where('teaching_assignments.group_id', $selectedStudent->group_id)
                        ->where('academic_sessions.is_cancelled', false)
                        ->whereIn(DB::raw('DATE(academic_sessions.session_date)'), $presentDates->all())
                        ->when(
                            $cyclePeriodIds->isNotEmpty(),
                            fn ($q) => $q->whereIn('academic_sessions.academic_period_id', $cyclePeriodIds->all()),
                            fn ($q) => $q->when($from && $to, fn ($qq) => $qq->whereBetween('academic_sessions.session_date', [$from, $to]))
                        )
                        ->groupBy('subjects.id', 'subjects.name')
                        ->orderByDesc('missed_sessions')
                        ->orderBy('subjects.name')
                        ->get();
                }
            }
        }

        return view('coordination.students.academic-summary', [
            'schoolCycles' => $schoolCycles,
            'selectedSchoolCycleId' => $schoolCycleId,
            'groups' => $groups,
            'students' => $students,
            'selectedGroupId' => $groupId,
            'selectedStudentId' => $studentId,
            'selectedStudent' => $selectedStudent,
            'subjectRows' => $subjectRows,
            'globalAttendance' => $globalAttendance,
            'globalAverage' => $globalAverage,
            'activePeriod' => $activePeriod,
            'activeCycle' => $activeCycle,
            'cyclePartials' => $cyclePartials,
            'partialColumns' => $partialColumns,
            'globalByPartial' => $globalByPartial,
            'classAbsencesWhilePresentBySubject' => $classAbsencesWhilePresentBySubject,
            'dailyMatrixDates' => $dailyMatrixDates,
            'dailyMatrixPrefect' => $dailyMatrixPrefect,
            'dailyMatrixByAssignment' => $dailyMatrixByAssignment,
            'dailyMatrixSubjects' => $dailyMatrixSubjects,
        ]);
    }

    public function subjectDetail(
        Student $student,
        TeachingAssignment $assignment,
        AcademicPerformanceService $performance
    )
    {
        abort_unless($assignment->group_id === $student->group_id, 404);

        $student->load('user');
        $assignment->load(['subject', 'teacher.user', 'group.level.modality']);

        $activeCycle = $this->tenantCyclesQuery()
            ->where('modality_id', $assignment->group->level->modality_id)
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first();

        $cyclePeriodIds = collect();
        $period = null;
        $cycleFrom = null;
        $cycleTo = null;

        if ($activeCycle) {
            $cycleFrom = Carbon::parse($activeCycle->start_date)->startOfDay();
            $cycleTo = Carbon::parse($activeCycle->end_date)->endOfDay();

            $cyclePeriodIds = $activeCycle->partials()
                ->whereNotNull('academic_period_id')
                ->pluck('academic_period_id')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($cyclePeriodIds->isNotEmpty()) {
                $period = AcademicPeriod::query()
                    ->whereIn('id', $cyclePeriodIds->all())
                    ->orderBy('start_date')
                    ->first();
            }
        }

        if (! $period) {
            $period = AcademicPeriod::query()
                ->where('modality_id', $assignment->group->level->modality_id)
                ->where('is_active', true)
                ->first();
        }

        $sessions = AcademicSession::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('is_cancelled', false)
            ->when(
                $cyclePeriodIds->isNotEmpty(),
                fn ($q) => $q->whereIn('academic_period_id', $cyclePeriodIds->all()),
                fn ($q) => $q->when($period, fn ($qq) => $qq->where('academic_period_id', $period->id))
            )
            ->when($cycleFrom && $cycleTo, fn ($q) => $q->whereBetween('session_date', [$cycleFrom, $cycleTo]))
            ->with(['attendances' => fn ($q) => $q->where('student_id', $student->id)])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        $activities = Activity::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('is_active', true)
            ->when(
                $cyclePeriodIds->isNotEmpty(),
                fn ($q) => $q->whereIn('academic_period_id', $cyclePeriodIds->all()),
                fn ($q) => $q->when($period, fn ($qq) => $qq->where('academic_period_id', $period->id))
            )
            ->when($cycleFrom && $cycleTo, fn ($q) => $q->whereBetween('due_date', [$cycleFrom, $cycleTo]))
            ->orderBy('due_date')
            ->orderBy('title')
            ->get();

        $grades = Grade::query()
            ->where('student_id', $student->id)
            ->whereIn('activity_id', $activities->pluck('id'))
            ->get()
            ->keyBy('activity_id');

        $breakdown = $performance->breakdownForAssignment($student, $assignment);

        return view('coordination.students.subject-detail', [
            'student' => $student,
            'assignment' => $assignment,
            'period' => $period,
            'activeCycle' => $activeCycle,
            'sessions' => $sessions,
            'activities' => $activities,
            'grades' => $grades,
            'gradeBreakdownRows' => $breakdown['rows'] ?? [],
            'finalGrade' => $breakdown['final'] ?? null,
        ]);
    }

    public function prefectAttendanceDetail(Student $student, Request $request)
    {
        $schoolCycleId = (int) $request->integer('school_cycle_id');
        abort_if($schoolCycleId <= 0, 404);

        $cycle = $this->tenantCyclesQuery()
            ->where('id', $schoolCycleId)
            ->firstOrFail();

        $student->loadMissing(['user', 'group.level']);

        $belongsToCycle = SchoolCycleGroup::query()
            ->where('school_cycle_id', $cycle->id)
            ->where('group_id', (int) $student->group_id)
            ->where('is_active', true)
            ->when((int) session('active_campus_id', 0) > 0, fn ($q) => $q->where('campus_id', (int) session('active_campus_id')))
            ->exists();

        abort_unless($belongsToCycle, 404);

        $from = Carbon::parse($cycle->start_date)->startOfDay();
        $to = Carbon::parse($cycle->end_date)->endOfDay();

        $rows = PrefectDailyAttendance::query()
            ->with(['recorder:id,name'])
            ->where('student_id', $student->id)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('attendance_date')
            ->get();

        $dailyMatrixDates = collect();
        $matrixEnd = $to->copy();
        $today = now()->endOfDay();
        if ($matrixEnd->greaterThan($today)) {
            $matrixEnd = $today;
        }

        $modalityId = (int) optional($student->group?->level)->modality_id;
        $nonWorkingDates = AcademicCalendarDay::query()
            ->whereIn('type', ['holiday', 'vacation'])
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $matrixEnd->toDateString())
            ->where(function ($q) use ($modalityId) {
                $q->whereNull('modality_id');
                if ($modalityId > 0) {
                    $q->orWhere('modality_id', $modalityId);
                }
            })
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();

        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($matrixEnd)) {
            $date = $cursor->toDateString();
            $isWeekend = in_array($cursor->dayOfWeekIso, [6, 7], true);
            if (! $isWeekend && ! $nonWorkingDates->has($date)) {
                $dailyMatrixDates->push($date);
            }
            $cursor->addDay();
        }

        $dailyMatrixPrefect = $rows
            ->keyBy(fn ($row) => Carbon::parse($row->attendance_date)->toDateString())
            ->map(fn ($row) => $row->status);

        $assignments = TeachingAssignment::query()
            ->with('subject')
            ->where('group_id', $student->group_id)
            ->where('is_active', true)
            ->whereHas('schedules', fn ($q) => $q
                ->where('school_cycle_id', (int) $cycle->id)
                ->where('is_active', true)
            )
            ->orderBy('subject_id')
            ->get()
            ->unique('subject_id')
            ->values();

        $dailyMatrixSubjects = $assignments->map(fn ($assignment) => [
            'assignment_id' => (int) $assignment->id,
            'subject_name' => $assignment->subject->name ?? 'N/D',
        ])->values();

        $dailyMatrixByAssignment = collect();
        $assignmentIdsForMatrix = $assignments->pluck('id')->map(fn ($id) => (int) $id)->values();

        if ($assignmentIdsForMatrix->isNotEmpty()) {
            $sessionDaysByAssignment = AcademicSession::query()
                ->selectRaw('teaching_assignment_id, DATE(session_date) as session_day')
                ->whereIn('teaching_assignment_id', $assignmentIdsForMatrix->all())
                ->where('is_cancelled', false)
                ->whereBetween('session_date', [$from, $matrixEnd])
                ->groupBy('teaching_assignment_id', DB::raw('DATE(session_date)'))
                ->get()
                ->groupBy('teaching_assignment_id')
                ->map(fn ($rows) => $rows->pluck('session_day')->map(fn ($d) => (string) $d)->values());

            $attendanceRows = Attendance::query()
                ->selectRaw('academic_sessions.teaching_assignment_id as assignment_id, DATE(academic_sessions.session_date) as session_day, attendances.status')
                ->join('academic_sessions', 'attendances.academic_session_id', '=', 'academic_sessions.id')
                ->where('attendances.student_id', $student->id)
                ->whereIn('academic_sessions.teaching_assignment_id', $assignmentIdsForMatrix->all())
                ->where('academic_sessions.is_cancelled', false)
                ->whereBetween('academic_sessions.session_date', [$from, $matrixEnd])
                ->get()
                ->groupBy(fn ($row) => (int) $row->assignment_id . '|' . (string) $row->session_day);

            $statusPriority = [
                'absent' => 4,
                'late' => 3,
                'justified' => 2,
                'present' => 1,
            ];

            $dailyMatrixByAssignment = $assignmentIdsForMatrix->mapWithKeys(function (int $assignmentId) use (
                $dailyMatrixDates,
                $sessionDaysByAssignment,
                $attendanceRows,
                $statusPriority
            ) {
                $daysWithClass = collect($sessionDaysByAssignment->get($assignmentId, []))
                    ->flip();

                $perDay = $dailyMatrixDates->mapWithKeys(function (string $date) use (
                    $assignmentId,
                    $daysWithClass,
                    $attendanceRows,
                    $statusPriority
                ) {
                    if (! $daysWithClass->has($date)) {
                        return [$date => 'no-class'];
                    }

                    $statuses = collect($attendanceRows->get($assignmentId . '|' . $date, []))
                        ->pluck('status')
                        ->filter()
                        ->values();

                    if ($statuses->isEmpty()) {
                        return [$date => null];
                    }

                    $selected = $statuses
                        ->sortByDesc(fn ($status) => $statusPriority[$status] ?? 0)
                        ->first();

                    return [$date => $selected];
                });

                return [$assignmentId => $perDay];
            });
        }

        return view('coordination.students.prefect-attendance-detail', [
            'student' => $student,
            'cycle' => $cycle,
            'rows' => $rows,
            'dailyMatrixDates' => $dailyMatrixDates,
            'dailyMatrixPrefect' => $dailyMatrixPrefect,
            'dailyMatrixByAssignment' => $dailyMatrixByAssignment,
            'dailyMatrixSubjects' => $dailyMatrixSubjects,
            'backParams' => [
                'school_cycle_id' => $cycle->id,
                'group_id' => $request->integer('group_id') ?: null,
                'student_id' => $student->id,
            ],
        ]);
    }

    public function index(StudentFollowUpFlagService $flagService) {
        $activeCampusId = (int) session('active_campus_id', 0);

        $activeCycle = $this->tenantCyclesQuery()
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first();

        $activeCycleId = (int) optional($activeCycle)->id;
        $activeCycleGroupIds = collect();
        if ($activeCycle) {
            $activeCycleGroupIds = SchoolCycleGroup::query()
                ->where('school_cycle_id', $activeCycle->id)
                ->where('is_active', true)
                ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
                ->pluck('group_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
        }

        $students = Student::with([
            'user',
            'group',
        ])
            ->when(
                $activeCampusId > 0,
                fn ($q) => $q->whereHas('group', fn ($gq) => $gq->where('campus_id', $activeCampusId)),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->when(
                $activeCycleGroupIds->isNotEmpty(),
                fn ($q) => $q->whereIn('group_id', $activeCycleGroupIds->all()),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->get();

        $priorityOrder = [
            'high'   => 0,
            'medium' => 1,
            'low'    => 2,
            'none'   => 3,
        ];

        $students = $flagService->annotateStudents($students, $activeCycle, $activeCampusId);
        
        $students = $students->sortBy(function ($student) use ($priorityOrder) {
            return $priorityOrder[$student->priority] ?? 99;
        })->values();

        return view('admin.students.index', compact('students', 'activeCycleId', 'activeCycle'));
    }

    public function show(Student $student) {
        $student->load(['group.level', 'group.modality',]);
        return view('admin.students.show', compact('student'));
    }

    public function general(Student $student) {
        return view('admin.students.partials.general', compact('student'));
    }

    public function attendance(Student $student) {
        $attendance = Attendance::query()
            ->selectRaw('
                subjects.id AS subject_id,
                subjects.name AS subject_name,
                COUNT(attendances.id) AS total,
                SUM(
                    CASE
                        WHEN attendances.status IN ("present", "late", "justified")
                        THEN 1
                        ELSE 0
                    END
                ) AS presents
            ')
            ->join('academic_sessions', 'attendances.academic_session_id', '=', 'academic_sessions.id')
            ->join('teaching_assignments', 'academic_sessions.teaching_assignment_id', '=', 'teaching_assignments.id')
            ->join('subjects', 'teaching_assignments.subject_id', '=', 'subjects.id')
            ->where('attendances.student_id', $student->id)
            ->groupBy('subjects.id', 'subjects.name')
            ->orderBy('subjects.name')
            ->get();

        return view('admin.students.partials.attendance', compact(
            'student',
            'attendance'
        ));
    }

    public function grades(Student $student) {
        $grades = Grade::query()
            ->selectRaw('
                subjects.id AS subject_id,
                subjects.name AS subject_name,
                AVG(grades.score) AS average
            ')
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('teaching_assignments', 'activities.teaching_assignment_id', '=', 'teaching_assignments.id')
            ->join('subjects', 'teaching_assignments.subject_id', '=', 'subjects.id')
            ->where('grades.student_id', $student->id)
            ->groupBy('subjects.id', 'subjects.name')
            ->orderBy('subjects.name')
            ->get();

        return view('admin.students.partials.grades', compact(
            'student',
            'grades'
        ));
    }

    public function attendanceHistory(Student $student) {
        $endDate = request('end_date')
            ? Carbon::parse(request('end_date'))
            : now();

        $dates = Attendance::query()
            ->where('attendances.student_id', $student->id)
            ->join('academic_sessions', 'attendances.academic_session_id', '=', 'academic_sessions.id')
            ->whereDate('academic_sessions.session_date', '<=', $endDate)
            ->distinct()
            ->orderByDesc('academic_sessions.session_date')
            ->pluck('academic_sessions.session_date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->sort()
            ->values();

        $records = Attendance::query()
            ->select(
                'academic_sessions.session_date',
                'attendances.status',
                'subjects.name as subject_name'
            )
            ->join('academic_sessions', 'attendances.academic_session_id', '=', 'academic_sessions.id')
            ->join('teaching_assignments', 'academic_sessions.teaching_assignment_id', '=', 'teaching_assignments.id')
            ->join('subjects', 'teaching_assignments.subject_id', '=', 'subjects.id')
            ->where('attendances.student_id', $student->id)
            ->whereIn(DB::raw('DATE(academic_sessions.session_date)'), $dates)
            ->get();

        $subjects = \App\Models\Subject::query()
            ->join('teaching_assignments', 'subjects.id', '=', 'teaching_assignments.subject_id')
            ->join('groups', 'teaching_assignments.group_id', '=', 'groups.id')
            ->where('groups.id', $student->group_id)
            ->select('subjects.id', 'subjects.name')
            ->distinct()
            ->get();

        $impartedRows = Attendance::query()
            ->select(
                'subjects.name as subject_name',
                'academic_sessions.session_date'
            )
            ->join('academic_sessions', 'attendances.academic_session_id', '=', 'academic_sessions.id')
            ->join('teaching_assignments', 'academic_sessions.teaching_assignment_id', '=', 'teaching_assignments.id')
            ->join('subjects', 'teaching_assignments.subject_id', '=', 'subjects.id')
            ->where('attendances.student_id', $student->id)
            ->whereIn(DB::raw('DATE(academic_sessions.session_date)'), $dates)
            ->distinct()
            ->get();

        $imparted = [];
        foreach ($impartedRows as $row) {
            $dateKey = Carbon::parse($row->session_date)->format('Y-m-d');
            $imparted[$row->subject_name][$dateKey] = true;
        }

        $matrix = [];
        foreach ($subjects as $subject) {
            foreach ($dates as $date) {
                $matrix[$subject->name][$date] = null;
            }
        }

        foreach ($records as $row) {
            $dateKey = Carbon::parse($row->session_date)->format('Y-m-d');
            $matrix[$row->subject_name][$dateKey] =
                in_array($row->status, ['present', 'late', 'justified'], true) ? 1 : 0;
        }

        return view('admin.students.attendance-history', compact(
            'student',
            'dates',
            'matrix',
            'imparted',
            'endDate',
        ));
    }

    public function gradesHistory(Student $student, AcademicCalendarService $calendar)
    {
        $modalityId = $student->group?->level?->modality_id;

        $endDate = request('end_date')
            ? Carbon::parse(request('end_date'))
            : $calendar->getLastSchoolDay(now(), $modalityId);

        $startDate = $calendar->subtractSchoolDays(
            $endDate,
            20,
            $modalityId
        );

        $dates = [];
        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            if (
                !$cursor->isWeekend() &&
                !$calendar->isNonWorkingDay($cursor, $modalityId)
            ) {
                $dates[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        $activities = Activity::query()
            ->select(
                'activities.id',
                'activities.title',
                DB::raw('DATE(activities.due_date) as activity_date'),
                'subjects.name as subject_name'
            )
            ->join('teaching_assignments', 'activities.teaching_assignment_id', '=', 'teaching_assignments.id')
            ->join('subjects', 'teaching_assignments.subject_id', '=', 'subjects.id')
            ->whereBetween(
                DB::raw('DATE(activities.due_date)'),
                [$startDate->toDateString(), $endDate->toDateString()]
            )
            ->where('teaching_assignments.group_id', $student->group_id)
            ->get();

        $grades = Grade::query()
            ->where('student_id', $student->id)
            ->get()
            ->keyBy('activity_id');

        $attendanceStats = Attendance::query()
            ->select(
                'subjects.name as subject_name',
                DB::raw('COUNT(attendances.id) as total'),
                DB::raw("
                    SUM(
                        CASE
                            WHEN attendances.status IN ('present', 'late', 'justified')
                            THEN 1
                            ELSE 0
                        END
                    ) as attended
                ")
            )
            ->join('academic_sessions', 'attendances.academic_session_id', '=', 'academic_sessions.id')
            ->join('teaching_assignments', 'academic_sessions.teaching_assignment_id', '=', 'teaching_assignments.id')
            ->join('subjects', 'teaching_assignments.subject_id', '=', 'subjects.id')
            ->where('attendances.student_id', $student->id)
            ->whereBetween(DB::raw('DATE(academic_sessions.session_date)'), [
                $startDate->toDateString(),
                $endDate->toDateString()
            ])
            ->groupBy('subjects.name')
            ->get()
            ->keyBy('subject_name');

        $attendanceSummary = [];
        $matrix = [];

        foreach ($activities as $activity) {
            $subject = $activity->subject_name;
            if (!isset($matrix[$subject])) {
                $matrix[$subject] = [];
            }

            $matrix[$subject][] = [
                'date'  => $activity->activity_date,
                'title' => $activity->title,
                'score' => $grades->has($activity->id)
                    ? $grades[$activity->id]->score
                    : null,
            ];
        }

        foreach ($matrix as $subject => $values) {
            if (isset($attendanceStats[$subject]) && $attendanceStats[$subject]->total > 0) {
                $attendanceSummary[$subject] = round(
                    ($attendanceStats[$subject]->attended / $attendanceStats[$subject]->total) * 100,
                    1
                );
            } else {
                $attendanceSummary[$subject] = null;
            }
        }

        return view('admin.students.grades-history', compact(
            'student',
            'dates',
            'matrix',
            'attendanceSummary',
            'endDate'
        ));
    }
    
    public function followups(Student $student)
    {
        $followUps = StudentFollowUp::query()
            ->where('student_id', $student->id)
            ->with([
                'requester',
                'teachers.teacher.user',
            ])
            ->latest()
            ->get();
    
        return view('admin.students.partials.followups', compact(
            'student',
            'followUps'
        ));
    }

    private function tenantCyclesQuery()
    {
        $activeCampusId = (int) session('active_campus_id', 0);

        return SchoolCycle::query()
            ->whereHas('cycleGroups', fn ($q) => $q->where('tenant_id', $this->tenantId()))
            ->when($activeCampusId > 0, function ($q) use ($activeCampusId) {
                $q->where(function ($nested) use ($activeCampusId) {
                    $nested->where('campus_id', $activeCampusId)
                        ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
                });
            });
    }

    private function tenantId(): string
    {
        $tenantId = (string) tenant('id');
        abort_if($tenantId === '', 403, 'Tenant no identificado.');

        return $tenantId;
    }
}
