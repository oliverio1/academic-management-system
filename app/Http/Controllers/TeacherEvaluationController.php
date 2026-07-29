<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\SchoolCycle;
use App\Models\TeachingAssignment;
use App\Services\CurrentSchoolCycle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherEvaluationController extends Controller
{
    public function index()
    {
        $teacher = auth()->user()->teacher;
        $activeCycle = $this->activeCycle();

        $assignments = collect();

        if ($teacher && $activeCycle) {
            $assignments = TeachingAssignment::query()
                ->with(['subject:id,name', 'group:id,name'])
                ->withCount('activities')
                ->where('teacher_id', $teacher->id)
                ->whereHas('schedules', function ($q) use ($activeCycle) {
                    $q->where('school_cycle_id', $activeCycle->id)
                        ->where('is_active', true);
                })
                ->orderBy('subject_id')
                ->get();
        }

        return view('teacher.evaluation.index', [
            'assignments' => $assignments,
            'activeCycle' => $activeCycle,
        ]);
    }

    public function show(TeachingAssignment $assignment)
    {
        $teacher = auth()->user()->teacher;
        $activeCycle = $this->activeCycle();

        abort_if(! $this->assignmentBelongsToActiveCycle($assignment, $teacher?->id, $activeCycle?->id), 403);

        $activities = $assignment->activities()
            ->with([
                'evaluationCriterion:id,name',
                'academicPeriod:id,name',
                'sessionActivity:id,academic_session_id',
            ])
            ->withCount(['grades'])
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->get();

        $activitiesWithSession = $activities
            ->filter(fn ($activity) => !is_null($activity->session_activity_id))
            ->values();

        $manualActivities = $activities
            ->filter(fn ($activity) => is_null($activity->session_activity_id))
            ->values();

        $cloneCandidates = $activeCycle
            ? TeachingAssignment::query()
                ->with(['group:id,name', 'subject:id,name'])
                ->whereKeyNot($assignment->id)
                ->where('teacher_id', $teacher->id)
                ->where('subject_id', $assignment->subject_id)
                ->where('is_active', true)
                ->whereHas('schoolCycleGroup', fn ($query) => $query->where('school_cycle_id', $activeCycle->id))
                ->orderBy('group_id')
                ->get()
            : collect();

        return view('teacher.evaluation.activities', [
            'assignment' => $assignment,
            'activities' => $activities,
            'activitiesWithSession' => $activitiesWithSession,
            'manualActivities' => $manualActivities,
            'cloneCandidates' => $cloneCandidates,
        ]);
    }

    public function createManual(TeachingAssignment $assignment)
    {
        $teacher = auth()->user()->teacher;
        $activeCycle = $this->activeCycle();

        abort_if(! $this->assignmentBelongsToActiveCycle($assignment, $teacher?->id, $activeCycle?->id), 403);

        $periodIds = $activeCycle
            ? $activeCycle->partials()
                ->whereNotNull('academic_period_id')
                ->orderBy('sort_order')
                ->orderBy('start_date')
                ->pluck('academic_period_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
            : collect();

        $periods = AcademicPeriod::query()
            ->when(
                $periodIds->isNotEmpty(),
                fn ($q) => $q->whereIn('id', $periodIds->all()),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->orderBy('start_date')
            ->get();

        $criteriaByPeriod = $periods
            ->mapWithKeys(function (AcademicPeriod $period) use ($assignment) {
                $periodLabel = (string) ($period->name ?? 'Parcial');
                $criteria = $assignment->evaluationCriteria()
                    ->forAssignmentAndPeriod($assignment, (int) $period->id)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($criterion) => [
                        'id' => (int) $criterion->id,
                        'name' => (string) $criterion->name,
                        'label' => $periodLabel . ' · ' . (string) $criterion->name,
                    ])
                    ->values()
                    ->all();

                return [(int) $period->id => $criteria];
            })
            ->all();

        $initialPeriodId = (int) old('academic_period_id', (int) $periods->first()?->id);
        $criteria = collect($criteriaByPeriod[$initialPeriodId] ?? []);
        $hasAnyCriteria = collect($criteriaByPeriod)->flatten(1)->isNotEmpty();

        return view('teacher.evaluation.manual_create', [
            'assignment' => $assignment,
            'criteria' => $criteria,
            'periods' => $periods,
            'criteriaByPeriod' => $criteriaByPeriod,
            'hasAnyCriteria' => $hasAnyCriteria,
        ]);
    }

    public function storeManual(Request $request, TeachingAssignment $assignment)
    {
        $teacher = auth()->user()->teacher;
        $activeCycle = $this->activeCycle();

        abort_if(! $this->assignmentBelongsToActiveCycle($assignment, $teacher?->id, $activeCycle?->id), 403);

        $allowedPeriodIds = $activeCycle
            ? $activeCycle->partials()
                ->whereNotNull('academic_period_id')
                ->pluck('academic_period_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all()
            : [];

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'evaluation_criterion_id' => [
                'required',
                'integer',
            ],
            'academic_period_id' => [
                'required',
                'integer',
                Rule::in($allowedPeriodIds),
            ],
            'max_score' => 'required|numeric|min:0.01|max:999.99',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $validCriterionIds = $assignment->evaluationCriteria()
            ->forAssignmentAndPeriod($assignment, (int) $data['academic_period_id'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! in_array((int) $data['evaluation_criterion_id'], $validCriterionIds, true)) {
            return back()
                ->withInput()
                ->withErrors([
                    'evaluation_criterion_id' => 'El rubro no corresponde al parcial seleccionado.',
                ]);
        }

        Activity::create([
            'teaching_assignment_id' => $assignment->id,
            'session_activity_id' => null,
            'evaluation_criterion_id' => $data['evaluation_criterion_id'],
            'academic_period_id' => $data['academic_period_id'],
            'title' => $data['title'],
            'max_score' => $data['max_score'],
            'due_date' => $data['due_date'] ?? null,
            'description' => $data['description'] ?? null,
            'evaluation_mode' => 'individual',
            'is_active' => true,
        ]);

        return redirect()
            ->route('teacher.evaluation.activities', $assignment)
            ->with('success', 'Actividad manual creada correctamente.');
    }

    private function assignmentBelongsToActiveCycle(
        TeachingAssignment $assignment,
        ?int $teacherId,
        ?int $activeCycleId
    ): bool {
        if (! $teacherId || ! $activeCycleId) {
            return false;
        }

        return TeachingAssignment::query()
            ->whereKey($assignment->id)
            ->where('teacher_id', $teacherId)
            ->whereHas('schedules', function ($q) use ($activeCycleId) {
                $q->where('school_cycle_id', $activeCycleId)
                    ->where('is_active', true);
            })
            ->exists();
    }

    private function activeCycle(): ?SchoolCycle
    {
        $activeCampusId = (int) session('active_campus_id', 0);

        return app(CurrentSchoolCycle::class)->get(auth()->user(), $activeCampusId);
    }
}
