<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\EvaluationCriterion;
use App\Models\TeachingAssignment;
use App\Services\EconomicActaLockService;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(TeachingAssignment $assignment)
    {
        $activities = $assignment->activities()->where('is_active', true)->get();
        $otherAssignments = auth()->user()->teacher
            ->teachingAssignments()
            ->where('id', '!=', $assignment->id)
            ->with('group', 'subject')
            ->get();

        return view('activities.index', compact('assignment', 'activities', 'otherAssignments'));
    }

    public function create(TeachingAssignment $assignment)
    {
        abort_if($assignment->teacher_id !== auth()->user()->teacher->id, 403);

        $periods = AcademicPeriod::query()
            ->where('modality_id', $assignment->group->level->modality_id)
            ->orderBy('start_date')
            ->get();

        $criteria = EvaluationCriterion::query()
            ->forAssignmentAndPeriod($assignment, (int) $periods->first()?->id)
            ->orderBy('name')
            ->get();

        $activePeriod = AcademicPeriod::query()
            ->where('modality_id', $assignment->group->level->modality_id)
            ->where('is_active', 1)
            ->firstOrFail();

        return view('activities.create', compact('assignment', 'periods', 'criteria', 'activePeriod'));
    }

    public function sessionsByPeriod(TeachingAssignment $assignment, AcademicPeriod $period)
    {
        abort_if($assignment->teacher_id !== auth()->user()->teacher->id, 403);

        $sessions = \App\Models\AcademicSession::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('academic_period_id', $period->id)
            ->where('is_cancelled', false)
            ->orderBy('session_date')
            ->pluck('session_date')
            ->map(fn ($date) => \Carbon\Carbon::parse($date))
            ->values();

        $activities = Activity::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('academic_period_id', $period->id)
            ->whereNotNull('due_date')
            ->get()
            ->keyBy(fn ($activity) => \Carbon\Carbon::parse($activity->due_date)->toDateString());

        $criteria = EvaluationCriterion::query()
            ->forAssignmentAndPeriod($assignment, (int) $period->id)
            ->orderBy('name')
            ->get();

        return view('activities.partials.sessions-table', compact('sessions', 'activities', 'period', 'criteria'));
    }

    public function store(Request $request, TeachingAssignment $assignment, EconomicActaLockService $lockService)
    {
        abort_if($assignment->teacher_id !== auth()->user()->teacher->id, 403);

        try {
            $data = $request->validate([
                'activity_id' => 'nullable|integer|exists:activities,id',
                'session_date' => 'required|date',
                'academic_period_id' => 'required|exists:academic_periods,id',
                'title' => 'required|string|max:255',
                'evaluation_criterion_id' => 'required|integer',
                'max_score' => 'nullable|numeric|min:0',
                'description' => 'nullable|string',
            ]);

            $period = AcademicPeriod::query()
                ->where('id', $data['academic_period_id'])
                ->where('modality_id', $assignment->group->level->modality_id)
                ->first();

            if (! $period) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Periodo inválido',
                ], 422);
            }

            if ($lockService->isAssignmentPeriodLocked($assignment, (int) $period->id)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El parcial de esta actividad ya tiene acta economica cerrada o enviada. Solo consulta.',
                ], 422);
            }

            $validCriterionIds = EvaluationCriterion::query()
                ->forAssignmentAndPeriod($assignment, (int) $period->id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (! in_array((int) $data['evaluation_criterion_id'], $validCriterionIds, true)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El rubro no pertenece al parcial seleccionado.',
                ], 422);
            }

            $activity = null;

            if (! empty($data['activity_id'])) {
                $activity = Activity::query()
                    ->where('id', (int) $data['activity_id'])
                    ->where('teaching_assignment_id', $assignment->id)
                    ->first();
            }

            if ($activity) {
                $activity->update([
                    'title' => $data['title'],
                    'evaluation_criterion_id' => $data['evaluation_criterion_id'],
                    'evaluation_mode' => 'individual',
                    'max_score' => $data['max_score'] ?? 10,
                    'due_date' => $data['session_date'],
                    'description' => $data['description'],
                    'is_active' => true,
                ]);
            } else {
                $activity = Activity::create([
                    'teaching_assignment_id' => $assignment->id,
                    'academic_period_id' => $period->id,
                    'due_date' => $data['session_date'],
                    'title' => $data['title'],
                    'evaluation_criterion_id' => $data['evaluation_criterion_id'],
                    'evaluation_mode' => 'individual',
                    'max_score' => $data['max_score'] ?? 10,
                    'description' => $data['description'],
                    'is_active' => true,
                ]);
            }

            return response()->json([
                'ok' => true,
                'activity_id' => $activity->id,
            ]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => 'Datos inválidos',
                'errors' => $exception->errors(),
            ], 422);
        } catch (\Throwable $exception) {
            \Log::error('Error guardando actividad', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    public function edit(Activity $activity)
    {
        $assignment = $activity->assignment;

        $criteria = EvaluationCriterion::query()
            ->forAssignmentAndPeriod($assignment, (int) $activity->academic_period_id)
            ->orderBy('name')
            ->get();

        $periods = AcademicPeriod::query()
            ->where('level_id', $assignment->group->level_id)
            ->orderBy('start_date')
            ->get();

        return view('activities.edit', compact('activity', 'assignment', 'periods', 'criteria'));
    }

    public function update(Request $request, Activity $activity)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'academic_period_id' => 'required|exists:academic_periods,id',
            'weight' => 'required|numeric|min:0|max:100',
            'due_date' => 'required|date',
            'description' => 'nullable|string',
            'evaluation_criterion_id' => 'required|exists:evaluation_criteria,id',
        ]);

        $assignment = $activity->assignment;
        $currentWeight = Activity::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('academic_period_id', $data['academic_period_id'])
            ->where('id', '!=', $activity->id)
            ->sum('weight');

        if ($currentWeight + $data['weight'] > 100) {
            return back()->withInput()->withErrors(['weight' => 'La ponderación del periodo excede el 100%']);
        }

        $activity->update($data);

        return redirect()
            ->route('activities.index', $assignment)
            ->with('success', 'Actividad actualizada correctamente');
    }

    public function show(Activity $activity)
    {
        $assignment = TeachingAssignment::findOrFail($activity->teaching_assignment_id);
        $students = $assignment->group->students()->with([
            'user',
            'grades' => fn ($query) => $query->where('activity_id', $activity->id),
        ])->get();

        return view('activities.show', compact('activity', 'students'));
    }

    public function destroy(Activity $activity, EconomicActaLockService $lockService)
    {
        $assignment = $activity->assignment;

        abort_if(! $assignment || $assignment->teacher_id !== auth()->user()->teacher->id, 403);

        if ($lockService->isAssignmentPeriodLocked($assignment, (int) $activity->academic_period_id)) {
            return back()->withErrors([
                'criteria' => 'El parcial de esta actividad ya tiene acta economica cerrada o enviada. Solo consulta.',
            ]);
        }

        if ($activity->grades()->exists()) {
            return back()->withErrors([
                'criteria' => 'No puedes eliminar una actividad que ya tiene calificaciones registradas.',
            ]);
        }

        $activity->delete();

        return back()->with('success', 'Actividad eliminada correctamente.');
    }

    public function inlineUpdate(Request $request, TeachingAssignment $assignment, EconomicActaLockService $lockService)
    {
        abort_if($assignment->teacher_id !== auth()->user()->teacher->id, 403);

        $period = AcademicPeriod::query()
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->firstOrFail();

        if ($lockService->isAssignmentPeriodLocked($assignment, (int) $period->id)) {
            return response()->json([
                'ok' => false,
                'message' => 'El parcial de esta actividad ya tiene acta economica cerrada o enviada. Solo consulta.',
            ], 422);
        }

        $activity = Activity::query()->firstOrCreate(
            [
                'teaching_assignment_id' => $assignment->id,
                'academic_period_id' => $period->id,
                'due_date' => $request->session_date,
            ],
            [
                'max_score' => 10,
                'is_active' => true,
            ]
        );

        $activity->update([
            $request->field => $request->value,
        ]);

        return response()->json(['ok' => true]);
    }
}

