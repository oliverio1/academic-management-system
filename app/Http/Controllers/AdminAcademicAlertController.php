<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Modality;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Services\AcademicPerformanceService;

class AdminAcademicAlertController extends Controller
{
    public function groupsInAlert()
    {
        $performance = app(AcademicPerformanceService::class);
        $activeCampusId = $this->resolveActiveCampusId();
        if ($activeCampusId <= 0) {
            return view('admin.alerts.groups_in_alert', ['results' => []]);
        }

        $activeCycleId = SchoolCycle::query()
            ->where('is_active', true)
            ->where('campus_id', $activeCampusId)
            ->orderByDesc('start_date')
            ->value('id');

        $activeGroupIds = SchoolCycleGroup::query()
            ->when($activeCycleId, fn ($q) => $q->where('school_cycle_id', (int) $activeCycleId))
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->where('is_active', true)
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $groups = Group::query()
            ->where('is_active', true)
            ->when(!empty($activeGroupIds), fn ($q) => $q->whereIn('id', $activeGroupIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->with(['level.modality', 'students.groupHistories.group.level'])
            ->get();

        $results = [];

        foreach ($groups as $group) {
            $period = AcademicPeriod::activeForModality(
                (int) $group->level->modality_id
            );

            if (! $period) {
                continue;
            }

            $students = $group->students;

            if ($students->isEmpty()) {
                continue;
            }

            $sum = 0.0;
            $count = 0;

            foreach ($students as $student) {
                $avg = $performance->studentGeneralAverage($student);

                if ($avg !== null) {
                    $sum += $avg;
                    $count++;
                }
            }

            if ($count === 0) {
                continue;
            }

            $groupAverage = $sum / $count;

            $assignmentIds = TeachingAssignment::query()
                ->where('group_id', $group->id)
                ->pluck('id');

            if ($assignmentIds->isEmpty()) {
                continue;
            }

            $sessionIds = AcademicSession::query()
                ->whereIn('teaching_assignment_id', $assignmentIds)
                ->where('is_cancelled', false)
                ->whereBetween('session_date', [$period->start_date, $period->end_date])
                ->pluck('id');

            if ($sessionIds->isEmpty()) {
                continue;
            }

            $totalExpected = $sessionIds->count() * $students->count();

            if ($totalExpected === 0) {
                continue;
            }

            $presentOrLate = Attendance::query()
                ->whereIn('academic_session_id', $sessionIds)
                ->whereIn('student_id', $students->pluck('id'))
                ->whereIn('status', ['present', 'late'])
                ->count();

            $attendancePercentage = ($presentOrLate / $totalExpected) * 100;

            if ($groupAverage < 6 && $attendancePercentage < 80) {
                $results[] = [
                    'group' => $group,
                    'level' => $group->level->name ?? 'N/A',
                    'modality' => $group->level->modality->name ?? 'N/A',
                    'students_count' => $students->count(),
                    'average' => round($groupAverage, 2),
                    'attendance' => round($attendancePercentage, 2),
                    'period' => $period->name,
                ];
            }
        }

        usort($results, fn ($a, $b) => strcmp($a['group']->name, $b['group']->name));

        return view('admin.alerts.groups_in_alert', [
            'results' => $results,
        ]);
    }

    private function resolveActiveCampusId(): int
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $user = auth()->user();
        $allowedCampusIds = $user
            ? $user->campuses()->pluck('campuses.id')->map(fn ($id) => (int) $id)->all()
            : [];

        if ($activeCampusId <= 0 || ! in_array($activeCampusId, $allowedCampusIds, true)) {
            $activeCampusId = (int) ($allowedCampusIds[0] ?? 0);
            if ($activeCampusId > 0) {
                session(['active_campus_id' => $activeCampusId]);
            }
        }

        return $activeCampusId;
    }

    public function criticalSubjects(Modality $modality)
    {
        $performance = app(AcademicPerformanceService::class);
        $minScore = 6;

        $students = Student::whereHas(
                'group.level.modality',
                fn ($q) => $q->where('id', $modality->id)
            )
            ->with('group.assignments.subject')
            ->get();

        $results = [];

        foreach ($students as $student) {

            foreach ($student->group->assignments as $assignment) {

                $final = $performance
                    ->finalGradeForAssignment($student, $assignment);

                if ($final > 0 &&$final < $minScore && ! empty($breakdown['rows'])
                ) {
                    $results[$student->id]['student'] = $student;

                    $results[$student->id]['subjects'][] = [
                        'subject' => $assignment->subject,
                        'score'   => round($final, 2),
                    ];
                }
            }
        }

        $breakdown = $performance->riskBreakdown($student,$assignment);
        if (
            $final > 0 &&
            $final < $minScore &&
            ! empty($breakdown['rows'])
        ) {
            $results[$student->id]['student'] = $student;
        
            $results[$student->id]['subjects'][] = [
                'subject'   => $assignment->subject,
                'final'     => round($final, 2),
                'breakdown' => $breakdown['rows'],
            ];
        }

        return view('admin.alerts.critical_subjects', [
            'modality' => $modality,
            'results'  => $results,
        ]);
    }
}
