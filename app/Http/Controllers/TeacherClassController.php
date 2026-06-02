<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TeachingAssignment;
use App\Models\AcademicSession;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use Illuminate\Support\Collection;

class TeacherClassController extends Controller
{
    public function index()
    {
        $teacher = auth()->user()->teacher;
        $activeCycle = $this->activeCycle();
        $activeCampusId = (int) session('active_campus_id', 0);
        $activeCyclePeriodIds = $this->activeCyclePeriodIds();
        $activePlanByCycleGroup = $this->activeCyclePlanByGroup();

        $assignments = TeachingAssignment::query()
            ->with(['subject', 'group'])
            ->withCount('evaluationCriteria')
            ->where('teacher_id', $teacher->id)
            ->whereHas('schedules', function ($q) use ($activeCycle) {
                $q->where('is_active', true);
                if ($activeCycle) {
                    $q->where('school_cycle_id', $activeCycle->id);
                }
            })
            ->when($activeCycle, function ($q) use ($activeCycle, $activeCampusId) {
                $q->whereHas('schoolCycleGroup', function ($cycleGroupQuery) use ($activeCycle, $activeCampusId) {
                    $cycleGroupQuery->where('school_cycle_id', $activeCycle->id)
                        ->where('is_active', true)
                        ->when($activeCampusId > 0, fn ($nested) => $nested->where('campus_id', $activeCampusId));
                });
            })
            ->orderBy('subject_id')
            ->get()
            ->filter(function (TeachingAssignment $assignment) use ($activeCycle, $activePlanByCycleGroup) {
                if (! $activeCycle) {
                    return false;
                }

                $cycleGroupId = (int) ($assignment->school_cycle_group_id ?? 0);
                $subjectIds = $activePlanByCycleGroup[$cycleGroupId] ?? null;
                if ($subjectIds === null) {
                    return false;
                }

                if (empty($subjectIds)) {
                    return true;
                }

                return in_array((int) $assignment->subject_id, $subjectIds, true);
            })
            ->values();

        $assignmentIds = $assignments->pluck('id');
        $baseSessionsQuery = AcademicSession::query()
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->where('is_cancelled', false)
            ->whereIn('academic_period_id', $activeCyclePeriodIds);

        $totalSessionsByAssignment = (clone $baseSessionsQuery)
            ->selectRaw('teaching_assignment_id, COUNT(*) as total_sessions')
            ->groupBy('teaching_assignment_id')
            ->pluck('total_sessions', 'teaching_assignment_id');

        $assignedSessionsByAssignment = (clone $baseSessionsQuery)
            ->whereHas('sessionActivity')
            ->selectRaw('teaching_assignment_id, COUNT(*) as assigned_sessions')
            ->groupBy('teaching_assignment_id')
            ->pluck('assigned_sessions', 'teaching_assignment_id');

        $assignments->each(function ($assignment) use ($totalSessionsByAssignment, $assignedSessionsByAssignment) {
            $assignment->total_sessions_in_period = (int) ($totalSessionsByAssignment[$assignment->id] ?? 0);
            $assignment->assigned_sessions_in_period = (int) ($assignedSessionsByAssignment[$assignment->id] ?? 0);
            $assignment->has_evaluation_criteria = $assignment->evaluation_criteria_count > 0;
        });

        $schedules = collect();
        if ($assignmentIds->isNotEmpty()) {
            $schedules = TeachingAssignment::query()
                ->whereIn('id', $assignmentIds)
                ->with([
                    'schedules' => function ($q) use ($activeCycle) {
                        $q->where('is_active', true);
                        if ($activeCycle) {
                            $q->where('school_cycle_id', $activeCycle->id);
                        }
                    },
                    'schedules.assignment.subject',
                    'schedules.assignment.group',
                ])
                ->get()
                ->pluck('schedules')
                ->flatten();
        }

        $scheduleBlocks = $this->buildScheduleBlocks($schedules);

        return view('teacher.classes.index', [
            'assignments' => $assignments,
            'schedules' => $schedules,
            'scheduleBlocks' => $scheduleBlocks,
        ]);
    }

    public function calendar()
    {
        $teacher = auth()->user()->teacher;
        $activeCyclePeriodIds = $this->activeCyclePeriodIds();
        $activePlanByCycleGroup = $this->activeCyclePlanByGroup();
        $allowedCycleGroupIds = array_keys($activePlanByCycleGroup);

        $sessions = AcademicSession::query()
            ->whereHas('teachingAssignment', function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            })
            ->when(! empty($allowedCycleGroupIds), function ($q) use ($allowedCycleGroupIds) {
                $q->whereHas('teachingAssignment', fn ($ta) => $ta->whereIn('school_cycle_group_id', $allowedCycleGroupIds));
            })
            ->when(empty($allowedCycleGroupIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->whereIn('academic_period_id', $activeCyclePeriodIds)
            ->whereBetween('session_date', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ])
            ->where('is_cancelled', false)
            ->with([
                'teachingAssignment.subject:id,name',
                'teachingAssignment.group:id,name',
            ])
            ->withCount(['attendances', 'sessionActivity'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        return view('teacher.classes.calendar', [
            'sessions' => $sessions,
        ]);
    }

    public function show(TeachingAssignment $teachingAssignment)
    {
        $teacher = auth()->user()->teacher;

        abort_if(
            $teachingAssignment->teacher_id !== $teacher->id,
            403
        );

        return view('teacher.classes.show', [
            'assignment' => $teachingAssignment,
        ]);
    }

    private function activeCyclePeriodIds(): array
    {
        $activeCycle = $this->activeCycle();

        if (! $activeCycle) {
            return [];
        }

        return $activeCycle->partials()
            ->whereNotNull('academic_period_id')
            ->pluck('academic_period_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function activeCyclePlanByGroup(): array
    {
        $activeCycle = $this->activeCycle();
        if (! $activeCycle) {
            return [];
        }

        $activeCampusId = (int) session('active_campus_id', 0);

        $cycleGroups = SchoolCycleGroup::query()
            ->where('school_cycle_id', $activeCycle->id)
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->with('subjects:id')
            ->get();

        return $cycleGroups
            ->mapWithKeys(function (SchoolCycleGroup $cycleGroup) {
                $subjectIds = $cycleGroup->subjects->pluck('id')->map(fn ($id) => (int) $id)->all();
                return [(int) $cycleGroup->id => $subjectIds];
            })
            ->all();
    }

    private function activeCycle(): ?SchoolCycle
    {
        $activeCampusId = (int) session('active_campus_id', 0);

        return SchoolCycle::query()
            ->where('is_active', true)
            ->when($activeCampusId > 0, function ($q) use ($activeCampusId) {
                $q->where(function ($nested) use ($activeCampusId) {
                    $nested->where('campus_id', $activeCampusId)
                        ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
                });
            })
            ->orderByDesc('start_date')
            ->first();
    }

    private function buildScheduleBlocks(Collection $schedules): Collection
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

        return $schedules
            ->filter(fn ($schedule) => (bool) $schedule->is_active)
            ->map(function ($schedule) use ($dayMap) {
                $dayRaw = strtolower(trim((string) $schedule->day_of_week));
                $day = $dayMap[$dayRaw] ?? $dayRaw;
                $slot = substr((string) $schedule->start_time, 0, 5).'-'.substr((string) $schedule->end_time, 0, 5);

                return [
                    'day' => $day,
                    'slot' => $slot,
                    'schedule' => $schedule,
                ];
            })
            ->values();
    }
}
