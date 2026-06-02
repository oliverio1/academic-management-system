<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\AcademicCalendarDay;
use App\Models\Activity;
use App\Models\Grade;
use App\Models\PrefectDailyAttendance;
use App\Models\SchoolCycle;
use App\Models\Student;
use App\Models\StudentFollowUp;
use App\Models\TeachingAssignment;
use App\Models\Finance\FinanceCharge;
use App\Models\Finance\FinancePayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TutorPortalController extends Controller
{
    public function subjects()
    {
        $student = $this->assignedStudent();
        if (! $student) {
            return view('tutor.portal.unassigned');
        }

        $activeCampusId = (int) session('active_campus_id', 0);

        $assignments = TeachingAssignment::query()
            ->with(['subject', 'teacher.user', 'schedules'])
            ->where('group_id', $student->group_id)
            ->where('is_active', true)
            ->when(
                $activeCampusId > 0,
                fn ($q) => $q->whereHas('schedules.schoolCycle', fn ($sq) => $this->applyCampusFilterToCycleQuery($sq, $activeCampusId))
            )
            ->orderBy('subject_id')
            ->get();

        $scheduleBlocks = $this->buildScheduleBlocks($assignments);

        return view('tutor.portal.subjects', [
            'student' => $student,
            'assignments' => $assignments,
            'scheduleBlocks' => $scheduleBlocks,
        ]);
    }

    public function subjectShow(TeachingAssignment $assignment)
    {
        $student = $this->assignedStudentOrFail();
        $activeCampusId = (int) session('active_campus_id', 0);

        abort_unless(
            $assignment->group_id === $student->group_id && $assignment->is_active,
            403
        );

        if ($activeCampusId > 0) {
            $hasCampusSchedule = $assignment->schedules()
                ->whereHas('schoolCycle', fn ($q) => $this->applyCampusFilterToCycleQuery($q, $activeCampusId))
                ->exists();
            abort_unless($hasCampusSchedule, 403);
        }

        $assignment->load(['subject', 'teacher.user', 'group.level.modality']);

        $period = AcademicPeriod::query()
            ->where('modality_id', $assignment->group->level->modality_id)
            ->where('is_active', true)
            ->first();

        if (! $period) {
            $period = AcademicPeriod::query()
                ->where('modality_id', $assignment->group->level->modality_id)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first();
        }

        $sessions = AcademicSession::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('is_cancelled', false)
            ->when($period, fn ($q) => $q->where('academic_period_id', $period->id))
            ->when(
                $activeCampusId > 0,
                fn ($q) => $q->whereHas('schedule.schoolCycle', fn ($sq) => $this->applyCampusFilterToCycleQuery($sq, $activeCampusId))
            )
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->with(['attendances' => fn ($q) => $q->where('student_id', $student->id)])
            ->get();

        $totalSessions = $sessions->count();
        $attendedSessions = $sessions->filter(function ($session) {
            $status = optional($session->attendances->first())->status;
            return in_array($status, ['present', 'late', 'justified'], true);
        })->count();

        $attendancePercentage = $totalSessions > 0
            ? round(($attendedSessions / $totalSessions) * 100, 1)
            : 0;

        $activities = Activity::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('is_active', true)
            ->when($period, fn ($q) => $q->where('academic_period_id', $period->id))
            ->orderBy('due_date')
            ->orderBy('title')
            ->get();

        $grades = Grade::query()
            ->where('student_id', $student->id)
            ->whereIn('activity_id', $activities->pluck('id'))
            ->get()
            ->keyBy('activity_id');

        return view('tutor.portal.subject_show', [
            'student' => $student,
            'assignment' => $assignment,
            'period' => $period,
            'sessions' => $sessions,
            'attendancePercentage' => $attendancePercentage,
            'activities' => $activities,
            'grades' => $grades,
        ]);
    }

    public function followUps()
    {
        $student = $this->assignedStudent();
        if (! $student) {
            return view('tutor.portal.unassigned');
        }

        $followUps = StudentFollowUp::query()
            ->with(['requester', 'teachers.teacher.user'])
            ->where('student_id', $student->id)
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get();

        return view('tutor.portal.followups', [
            'student' => $student,
            'followUps' => $followUps,
        ]);
    }

    public function attendance()
    {
        $student = $this->assignedStudent();
        if (! $student) {
            return view('tutor.portal.unassigned');
        }

        $activeCampusId = (int) session('active_campus_id', 0);
        $student->loadMissing(['user', 'group.level']);

        $cycle = SchoolCycle::query()
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $this->applyCampusFilterToCycleQuery($q, $activeCampusId))
            ->orderByDesc('start_date')
            ->first();

        if (! $cycle) {
            return view('tutor.portal.attendance', [
                'student' => $student,
                'cycle' => null,
                'dailyMatrixDates' => collect(),
                'dailyMatrixPrefect' => collect(),
                'dailyMatrixByAssignment' => collect(),
                'dailyMatrixSubjects' => collect(),
                'summary' => [
                    'prefect_percentage' => null,
                    'subjects_percentage' => null,
                    'school_days' => 0,
                ],
            ]);
        }

        $from = Carbon::parse($cycle->start_date)->startOfDay();
        $to = Carbon::parse($cycle->end_date)->endOfDay();
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
            ->where('student_id', $student->id)
            ->whereBetween('attendance_date', [$from->toDateString(), $matrixEnd->toDateString()])
            ->get()
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
                ->whereHas('schedule', fn ($q) => $q->where('school_cycle_id', (int) $cycle->id))
                ->whereBetween('session_date', [$from, $matrixEnd])
                ->groupBy('teaching_assignment_id')
                ->groupBy(\DB::raw('DATE(session_date)'))
                ->get()
                ->groupBy('teaching_assignment_id')
                ->map(fn ($rows) => $rows->pluck('session_day')->map(fn ($d) => (string) $d)->values());

            $attendanceRows = \App\Models\Attendance::query()
                ->selectRaw('academic_sessions.teaching_assignment_id as assignment_id, DATE(academic_sessions.session_date) as session_day, attendances.status')
                ->join('academic_sessions', 'attendances.academic_session_id', '=', 'academic_sessions.id')
                ->where('attendances.student_id', $student->id)
                ->whereIn('academic_sessions.teaching_assignment_id', $assignmentIdsForMatrix->all())
                ->where('academic_sessions.is_cancelled', false)
                ->whereExists(function ($q) use ($cycle) {
                    $q->selectRaw(1)
                        ->from('schedules')
                        ->whereColumn('schedules.id', 'academic_sessions.schedule_id')
                        ->where('schedules.school_cycle_id', (int) $cycle->id);
                })
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
                $daysWithClass = collect($sessionDaysByAssignment->get($assignmentId, []))->flip();

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

        $schoolDays = $dailyMatrixDates->count();
        $prefectAttended = $dailyMatrixDates->filter(function (string $date) use ($dailyMatrixPrefect) {
            return in_array($dailyMatrixPrefect[$date] ?? null, ['present', 'late', 'justified'], true);
        })->count();
        $prefectPercentage = $schoolDays > 0
            ? round(($prefectAttended / $schoolDays) * 100, 1)
            : null;

        $subjectsPercentageValues = collect();
        foreach ($dailyMatrixSubjects as $subjectRow) {
            $assignmentId = (int) $subjectRow['assignment_id'];
            $row = collect($dailyMatrixByAssignment[$assignmentId] ?? []);
            $classDays = $row->filter(fn ($status) => $status !== 'no-class')->count();
            $attendedDays = $row->filter(fn ($status) => in_array($status, ['present', 'late', 'justified'], true))->count();
            if ($classDays > 0) {
                $subjectsPercentageValues->push(round(($attendedDays / $classDays) * 100, 1));
            }
        }
        $subjectsPercentage = $subjectsPercentageValues->isNotEmpty()
            ? round((float) $subjectsPercentageValues->avg(), 1)
            : null;

        return view('tutor.portal.attendance', [
            'student' => $student,
            'cycle' => $cycle,
            'dailyMatrixDates' => $dailyMatrixDates,
            'dailyMatrixPrefect' => $dailyMatrixPrefect,
            'dailyMatrixByAssignment' => $dailyMatrixByAssignment,
            'dailyMatrixSubjects' => $dailyMatrixSubjects,
            'summary' => [
                'prefect_percentage' => $prefectPercentage,
                'subjects_percentage' => $subjectsPercentage,
                'school_days' => $schoolDays,
            ],
        ]);
    }

    public function accountStatement()
    {
        $student = $this->assignedStudent();
        if (! $student) {
            return view('tutor.portal.unassigned');
        }

        $activeCampusId = (int) session('active_campus_id', 0);

        $charges = FinanceCharge::query()
            ->with('concept')
            ->where('student_id', $student->id)
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->latest('due_date')
            ->get();

        $payments = FinancePayment::query()
            ->where('student_id', $student->id)
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->latest('payment_date')
            ->get();

        $totalCharged = (float) $charges->sum(fn ($c) => (float) $c->amount);
        $totalApplied = (float) $charges->sum(fn ($c) => (float) $c->applications()->sum('applied_amount'));
        $balance = max(0, $totalCharged - $totalApplied);

        return view('tutor.portal.account-statement', [
            'student' => $student,
            'charges' => $charges,
            'payments' => $payments,
            'summary' => [
                'charged' => $totalCharged,
                'applied' => $totalApplied,
                'balance' => $balance,
            ],
        ]);
    }

    private function assignedStudent(): ?Student
    {
        return auth()->user()
            ->guardedStudents()
            ->with('group')
            ->where('is_active', true)
            ->first();
    }

    private function assignedStudentOrFail(): Student
    {
        $student = $this->assignedStudent();
        abort_if(! $student, 403, 'No tienes un alumno asociado.');
        return $student;
    }

    private function buildScheduleBlocks(Collection $assignments): Collection
    {
        $dayMap = [
            'monday' => 'lunes',
            'tuesday' => 'martes',
            'wednesday' => 'miercoles',
            'thursday' => 'jueves',
            'friday' => 'viernes',
            'saturday' => 'sabado',
            'sunday' => 'domingo',
            'lunes' => 'lunes',
            'martes' => 'martes',
            'miercoles' => 'miercoles',
            'jueves' => 'jueves',
            'viernes' => 'viernes',
            'sabado' => 'sabado',
            'domingo' => 'domingo',
        ];

        $blocks = collect();

        foreach ($assignments as $assignment) {
            foreach ($assignment->schedules as $schedule) {
                if (! $schedule->is_active) {
                    continue;
                }

                $dayRaw = strtolower(trim((string) $schedule->day_of_week));
                $day = $dayMap[$dayRaw] ?? $dayRaw;
                $slot = substr((string) $schedule->start_time, 0, 5).'-'.substr((string) $schedule->end_time, 0, 5);

                $blocks->push([
                    'day' => $day,
                    'slot' => $slot,
                    'assignment' => $assignment,
                    'type' => $schedule->type,
                ]);
            }
        }

        return $blocks;
    }

    private function applyCampusFilterToCycleQuery($query, int $activeCampusId): void
    {
        $query->where(function ($nested) use ($activeCampusId) {
            $nested->where('campus_id', $activeCampusId)
                ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
        });
    }
}
