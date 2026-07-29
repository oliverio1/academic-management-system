<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TeachingAssignment;
use App\Models\EvaluationCriterion;
use Illuminate\Support\Facades\DB;

class ActivityCloneController extends Controller
{
    public function clone(Request $request, TeachingAssignment $assignment) {
        $request->validate([
            'to_assignment_id' => 'required|exists:teaching_assignments,id',
            'activity_ids' => 'required|string',
            'due_date' => 'nullable|date',
        ]);
        $ids = explode(',', $request->activity_ids);
        $activities = $assignment->activities()->whereIn('id', $ids)->get();
        $teacherId = auth()->user()->teacher->id;
        abort_if($assignment->teacher_id !== $teacherId, 403);
        $to = TeachingAssignment::where('id', $request->to_assignment_id)->where('teacher_id', $teacherId)->firstOrFail();

        if ($to->activities()->whereHas('grades')->exists()) {
            return back()->withErrors('El grupo destino ya tiene calificaciones.');
        }
        DB::transaction(function () use ($activities, $to, $request) {
            foreach ($activities as $activity) {
                $to->activities()->create([
                    'title' => $activity->title,
                    'description' => $activity->description,
                    'evaluation_criterion_id' => $activity->evaluation_criterion_id,
                    'academic_period_id' => $activity->academic_period_id,
                    'max_score' => $activity->max_score,
                    'due_date' => $request->due_date ?? $activity->due_date,
                ]);
            }
        });
        return back()->with('success', 'Actividades clonadas correctamente.');
    }

    public function cloneToSameSubject(Request $request, TeachingAssignment $assignment)
    {
        $teacherId = auth()->user()?->teacher?->id;

        abort_if(! $teacherId || $assignment->teacher_id !== $teacherId, 403);

        $data = $request->validate([
            'to_assignment_ids' => ['required', 'array', 'min:1'],
            'to_assignment_ids.*' => ['integer', 'exists:teaching_assignments,id'],
            'activity_ids' => ['required', 'array', 'min:1'],
            'activity_ids.*' => ['integer', 'exists:activities,id'],
        ]);

        $activityIds = collect($data['activity_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $targetAssignmentIds = collect($data['to_assignment_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($activityIds->isEmpty()) {
            return back()->withErrors([
                'clone' => 'Selecciona al menos una actividad para clonar.',
            ]);
        }

        if ($targetAssignmentIds->isEmpty()) {
            return back()->withErrors([
                'clone' => 'Selecciona al menos un grupo destino.',
            ]);
        }

        $sourceCycleId = (int) optional($assignment->schoolCycleGroup)->school_cycle_id;

        $targets = TeachingAssignment::query()
            ->with(['group:id,name', 'schoolCycleGroup'])
            ->whereIn('id', $targetAssignmentIds->all())
            ->where('teacher_id', $teacherId)
            ->where('subject_id', $assignment->subject_id)
            ->where('is_active', true)
            ->whereHas('schoolCycleGroup', fn ($query) => $query->where('school_cycle_id', $sourceCycleId))
            ->get();

        if ($targets->count() !== $targetAssignmentIds->count()) {
            return back()->withErrors([
                'clone' => 'Selecciona solo grupos destino de la misma materia y ciclo.',
            ]);
        }

        $targetsWithActivities = $targets
            ->filter(fn (TeachingAssignment $target) => $target->activities()->where('is_active', true)->exists())
            ->map(fn (TeachingAssignment $target) => optional($target->group)->name)
            ->filter()
            ->values();

        if ($targetsWithActivities->isNotEmpty()) {
            return back()->withErrors([
                'clone' => 'Estos grupos destino ya tienen actividades: '.$targetsWithActivities->join(', ').'. Para evitar duplicados, clona solo hacia grupos sin actividades.',
            ]);
        }

        $activities = $assignment->activities()
            ->with('evaluationCriterion')
            ->whereIn('id', $activityIds->all())
            ->where('is_active', true)
            ->orderBy('academic_period_id')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        if ($activities->isEmpty()) {
            return back()->withErrors([
                'clone' => 'No se encontraron actividades validas para clonar.',
            ]);
        }

        $targetCriteriaByAssignment = EvaluationCriterion::query()
            ->whereIn('teaching_assignment_id', $targets->pluck('id')->all())
            ->get()
            ->groupBy('teaching_assignment_id')
            ->map(fn ($criteria) => $criteria->groupBy(
                fn (EvaluationCriterion $criterion) => $this->criterionKey($criterion->cycle_partial_id, $criterion->name)
            ));

        $missingCriteria = collect();

        foreach ($targets as $target) {
            $targetCriteria = $targetCriteriaByAssignment->get($target->id, collect());
            $missing = $activities
                ->filter(fn ($activity) => $activity->evaluationCriterion)
                ->reject(function ($activity) use ($targetCriteria) {
                    return $targetCriteria->has($this->criterionKey(
                        $activity->evaluationCriterion->cycle_partial_id,
                        $activity->evaluationCriterion->name
                    ));
                })
                ->map(fn ($activity) => optional($activity->evaluationCriterion)->name)
                ->filter()
                ->unique()
                ->values();

            if ($missing->isNotEmpty()) {
                $missingCriteria->push('Grupo '.optional($target->group)->name.': '.$missing->join(', '));
            }
        }

        if ($missingCriteria->isNotEmpty()) {
            return back()->withErrors([
                'clone' => 'Primero configura o clona los rubros en los grupos destino: '.$missingCriteria->join('; '),
            ]);
        }

        DB::transaction(function () use ($activities, $targets, $targetCriteriaByAssignment) {
            foreach ($targets as $target) {
                $targetCriteria = $targetCriteriaByAssignment->get($target->id, collect());

                foreach ($activities as $activity) {
                    $targetCriterion = null;

                    if ($activity->evaluationCriterion) {
                        $targetCriterion = $targetCriteria
                            ->get($this->criterionKey(
                                $activity->evaluationCriterion->cycle_partial_id,
                                $activity->evaluationCriterion->name
                            ))
                            ?->first();
                    }

                    $target->activities()->create([
                        'session_activity_id' => null,
                        'title' => $activity->title,
                        'description' => $activity->description,
                        'evaluation_criterion_id' => $targetCriterion?->id,
                        'academic_period_id' => $activity->academic_period_id,
                        'max_score' => $activity->max_score,
                        'due_date' => $activity->due_date,
                        'evaluation_mode' => $activity->evaluation_mode ?? 'individual',
                        'is_active' => true,
                    ]);
                }
            }
        });

        return back()->with('success', 'Actividades clonadas correctamente a '.$targets->count().' grupo(s).');
    }

    private function criterionKey(?int $cyclePartialId, string $name): string
    {
        return ((int) $cyclePartialId).'|'.mb_strtolower(trim($name));
    }
}
