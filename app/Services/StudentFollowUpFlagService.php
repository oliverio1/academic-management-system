<?php

namespace App\Services;

use App\Models\Student;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\StudentFollowUp;
use App\Models\TeachingAssignment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentFollowUpFlagService
{
    public function annotateStudents(
        Collection $students,
        ?SchoolCycle $activeCycle = null,
        ?int $activeCampusId = null
    ): Collection
    {
        if ($students->isEmpty()) {
            return $students;
        }

        $studentIds = $students->pluck('id')->map(fn ($id) => (int) $id)->values();
        $groupIds = $students->pluck('group_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $cycleGroupIds = collect();
        if ($activeCycle) {
            $cycleGroupIds = SchoolCycleGroup::query()
                ->where('school_cycle_id', (int) $activeCycle->id)
                ->where('is_active', true)
                ->when(
                    (int) $activeCampusId > 0,
                    fn ($q) => $q->where('campus_id', (int) $activeCampusId)
                )
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();
        }

        $assignmentIds = TeachingAssignment::query()
            ->whereIn('group_id', $groupIds->all())
            ->where('is_active', true)
            ->when(
                $cycleGroupIds->isNotEmpty(),
                fn ($q) => $q->whereIn('school_cycle_group_id', $cycleGroupIds->all()),
                fn ($q) => $q
            )
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $closedBehavioralIds = StudentFollowUp::query()
            ->whereIn('student_id', $studentIds->all())
            ->whereIn('type', ['behavioral', 'mixed'])
            ->where('status', 'closed')
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->flip();

        $activeFollowupIds = StudentFollowUp::query()
            ->whereIn('student_id', $studentIds->all())
            ->where('status', 'open')
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->flip();

        $latestGroupHistoryByStudent = DB::table('student_group_histories')
            ->selectRaw('student_id, MAX(start_date) as last_start_date')
            ->whereIn('student_id', $studentIds->all())
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $academicRiskIds = collect();
        if ($assignmentIds->isNotEmpty()) {
            $avgByAssignment = DB::table('grades')
                ->join('activities', 'grades.activity_id', '=', 'activities.id')
                ->join('teaching_assignments', 'activities.teaching_assignment_id', '=', 'teaching_assignments.id')
                ->join('students', 'students.id', '=', 'grades.student_id')
                ->whereIn('grades.student_id', $studentIds->all())
                ->whereIn('activities.teaching_assignment_id', $assignmentIds->all())
                ->whereColumn('students.group_id', 'teaching_assignments.group_id')
                ->groupBy('grades.student_id', 'activities.teaching_assignment_id')
                ->selectRaw('grades.student_id as student_id, AVG(grades.score) as avg_score');

            $academicRiskIds = DB::query()
                ->fromSub($avgByAssignment, 'assignment_avg')
                ->where('avg_score', '<', 6)
                ->pluck('student_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->flip();

            if ($activeCycle) {
                $periodIds = $activeCycle->partials()
                    ->whereNotNull('academic_period_id')
                    ->pluck('academic_period_id')
                    ->map(fn ($id) => (int) $id)
                    ->values();

                if ($periodIds->isNotEmpty()) {
                    $avgByAssignment->whereIn('activities.academic_period_id', $periodIds->all());
                }

                if ($activeCycle->start_date && $activeCycle->end_date) {
                    $avgByAssignment->whereBetween('activities.due_date', [
                        Carbon::parse($activeCycle->start_date)->startOfDay(),
                        Carbon::parse($activeCycle->end_date)->endOfDay(),
                    ]);
                }

                $academicRiskIds = DB::query()
                    ->fromSub($avgByAssignment, 'assignment_avg')
                    ->where('avg_score', '<', 6)
                    ->pluck('student_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->flip();
            }
        }

        $totalSessionsByGroup = collect();
        $attendedSessionsByStudent = collect();

        if ($groupIds->isNotEmpty()) {
            $totalSessionsQuery = DB::table('academic_sessions')
                ->join('teaching_assignments', 'academic_sessions.teaching_assignment_id', '=', 'teaching_assignments.id')
                ->whereIn('teaching_assignments.group_id', $groupIds->all())
                ->whereIn('teaching_assignments.id', $assignmentIds->all())
                ->where('teaching_assignments.is_active', true)
                ->where('academic_sessions.is_cancelled', false)
                ->groupBy('teaching_assignments.group_id')
                ->selectRaw('teaching_assignments.group_id as group_id, COUNT(academic_sessions.id) as total_sessions');

            $attendedSessionsQuery = DB::table('attendances')
                ->join('academic_sessions', 'attendances.academic_session_id', '=', 'academic_sessions.id')
                ->join('teaching_assignments', 'academic_sessions.teaching_assignment_id', '=', 'teaching_assignments.id')
                ->whereIn('attendances.student_id', $studentIds->all())
                ->whereIn('attendances.status', ['present', 'late', 'justified'])
                ->whereIn('teaching_assignments.id', $assignmentIds->all())
                ->where('teaching_assignments.is_active', true)
                ->where('academic_sessions.is_cancelled', false)
                ->groupBy('attendances.student_id')
                ->selectRaw('attendances.student_id as student_id, COUNT(attendances.id) as attended_sessions');

            if ($activeCycle && $activeCycle->start_date && $activeCycle->end_date) {
                $from = Carbon::parse($activeCycle->start_date)->startOfDay();
                $to = Carbon::parse($activeCycle->end_date)->endOfDay();

                $totalSessionsQuery->whereBetween('academic_sessions.session_date', [$from, $to]);
                $attendedSessionsQuery->whereBetween('academic_sessions.session_date', [$from, $to]);
            }

            $totalSessionsByGroup = $totalSessionsQuery->get()->keyBy('group_id');
            $attendedSessionsByStudent = $attendedSessionsQuery->get()->keyBy('student_id');
        }

        return $students->map(function (Student $student) use (
            $academicRiskIds,
            $closedBehavioralIds,
            $activeFollowupIds,
            $latestGroupHistoryByStudent,
            $totalSessionsByGroup,
            $attendedSessionsByStudent
        ) {
            $flags = [];

            $studentId = (int) $student->id;
            $groupId = (int) $student->group_id;

            if ($academicRiskIds->has($studentId)) {
                $flags[] = 'academic_risk';
            }

            $totalSessions = (int) optional($totalSessionsByGroup->get($groupId))->total_sessions;
            $attended = (int) optional($attendedSessionsByStudent->get($studentId))->attended_sessions;
            if ($totalSessions > 0) {
                $attendancePct = ($attended / $totalSessions) * 100;
                if ($attendancePct < 80) {
                    $flags[] = 'low_attendance';
                }
            }

            $history = $latestGroupHistoryByStudent->get($studentId);
            if ($history && ! empty($history->last_start_date)) {
                $lastStartDate = Carbon::parse($history->last_start_date);
                if ($lastStartDate->greaterThan(now()->subWeeks(6))) {
                    $flags[] = 'group_change';
                }
            }

            if ($closedBehavioralIds->has($studentId)) {
                $flags[] = 'behavioral';
            }

            $student->followup_flags = $flags;
            $student->priority = $this->priorityFromFlags($flags);
            $student->has_active_follow_up = $activeFollowupIds->has($studentId);

            return $student;
        });
    }

    public function flagsFor(Student $student): array
    {
        $collection = $this->annotateStudents(collect([$student]));
        return (array) ($collection->first()->followup_flags ?? []);
    }

    public function priorityFor(Student $student): string
    {
        $collection = $this->annotateStudents(collect([$student]));
        return (string) ($collection->first()->priority ?? 'none');
    }

    protected function priorityFromFlags(array $flags): string
    {
        $count = count($flags);

        if (in_array('academic_risk', $flags, true)) {
            if (
                in_array('low_attendance', $flags, true) ||
                in_array('group_change', $flags, true) ||
                $count >= 3
            ) {
                return 'high';
            }

            return 'medium';
        }

        if (
            in_array('low_attendance', $flags, true) ||
            in_array('behavioral', $flags, true)
        ) {
            return 'medium';
        }

        if (in_array('group_change', $flags, true)) {
            return 'low';
        }

        return 'none';
    }
}
