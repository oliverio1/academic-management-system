<?php

namespace App\Http\Controllers;

use App\Models\CyclePartial;
use App\Models\EvaluationCriterion;
use App\Models\TeachingAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EvaluationCriterionController extends Controller
{
    public function index(TeachingAssignment $assignment)
    {
        $this->authorizeAssignmentOwner($assignment);

        $partials = $this->partialsForAssignment($assignment);
        $selectedPartial = $this->selectedPartial($partials, (int) request('partial_id', 0));
        $selectedPartialId = $selectedPartial?->id;

        $criteria = EvaluationCriterion::query()
            ->forAssignmentAndPartial($assignment, $selectedPartialId, false)
            ->orderBy('id')
            ->get();

        $total = $criteria->sum('percentage');
        $cloneCandidates = $selectedPartialId
            ? $this->cloneTargetCandidates($assignment, (int) $selectedPartialId)
            : collect();

        return view('teacher.evaluation_criteria.index', compact(
            'assignment',
            'criteria',
            'total',
            'partials',
            'selectedPartial',
            'selectedPartialId',
            'cloneCandidates'
        ));
    }

    public function store(Request $request, TeachingAssignment $assignment)
    {
        $this->authorizeAssignmentOwner($assignment);

        $allowedPartialIds = $this->partialsForAssignment($assignment)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $data = $request->validate([
            'cycle_partial_id' => [
                'required',
                'integer',
                Rule::in($allowedPartialIds),
            ],
            'criteria' => 'required|array|min:1',
            'criteria.*.name' => 'required|string|max:255',
            'criteria.*.percentage' => 'required|numeric|min:0',
        ]);

        $partialId = (int) $data['cycle_partial_id'];
        $partial = $this->partialsForAssignment($assignment)->firstWhere('id', $partialId);

        abort_if(
            $assignment->evaluationCriteria()
                ->where('cycle_partial_id', $partialId)
                ->exists(),
            403,
            'Este parcial ya tiene criterios definidos.'
        );

        $total = collect($data['criteria'])->sum(function ($criterion) {
            return (float) ($criterion['percentage'] ?? 0);
        });

        if ($this->hasDuplicateNames($data['criteria'])) {
            return back()
                ->withInput()
                ->withErrors([
                    'criteria' => 'No puedes repetir nombres de rubro dentro del mismo parcial.',
                ]);
        }

        if (round($total, 2) !== 100.00) {
            return back()
                ->withInput()
                ->withErrors([
                    'criteria' => 'La suma de los porcentajes debe ser exactamente 100%.',
                ]);
        }

        DB::transaction(function () use ($data, $assignment, $partialId) {
            foreach ($data['criteria'] as $criterion) {
                $assignment->evaluationCriteria()->create([
                    'cycle_partial_id' => $partialId,
                    'name' => trim($criterion['name']),
                    'percentage' => $criterion['percentage'],
                ]);
            }
        });

        return redirect()
            ->route('teacher.classes.evaluation.index', [
                'assignment' => $assignment,
                'partial_id' => $partialId,
            ])
            ->with('success', 'Evaluacion configurada correctamente.');
    }

    public function update(Request $request, TeachingAssignment $assignment)
    {
        $this->authorizeAssignmentOwner($assignment);

        $allowedPartialIds = $this->partialsForAssignment($assignment)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $data = $request->validate([
            'cycle_partial_id' => [
                'required',
                'integer',
                Rule::in($allowedPartialIds),
            ],
            'criteria' => 'required|array|min:1',
            'criteria.*.name' => 'required|string|max:255',
            'criteria.*.percentage' => 'required|numeric|min:0',
        ]);

        $partialId = (int) $data['cycle_partial_id'];

        $total = collect($data['criteria'])->sum(function ($criterion) {
            return (float) ($criterion['percentage'] ?? 0);
        });

        if ($this->hasDuplicateNames($data['criteria'])) {
            return back()
                ->withInput()
                ->withErrors([
                    'criteria' => 'No puedes repetir nombres de rubro dentro del mismo parcial.',
                ]);
        }

        if (round($total, 2) !== 100.00) {
            return back()
                ->withInput()
                ->withErrors([
                    'criteria' => 'La suma de los porcentajes debe ser exactamente 100%.',
                ]);
        }

        DB::transaction(function () use ($data, $assignment, $partialId) {
            $normalizedIncomingIds = collect($data['criteria'])
                ->keys()
                ->map(fn ($key) => is_numeric($key) ? (int) $key : null)
                ->filter()
                ->values();

            $existing = $assignment->evaluationCriteria()
                ->where('cycle_partial_id', $partialId)
                ->get();

            foreach ($existing as $criterion) {
                if (!$normalizedIncomingIds->contains((int) $criterion->id)) {
                    if ($criterion->activities()->exists()) {
                        abort(403, 'No puedes eliminar un criterio con actividades asociadas.');
                    }

                    $criterion->delete();
                    continue;
                }

                if (isset($data['criteria'][$criterion->id])) {
                    $criterion->update([
                        'name' => trim($data['criteria'][$criterion->id]['name']),
                        'percentage' => $data['criteria'][$criterion->id]['percentage'],
                    ]);
                }
            }

            foreach ($data['criteria'] as $key => $criterion) {
                $incomingId = is_numeric($key) ? (int) $key : null;
                $alreadyExists = $incomingId
                    ? $existing->contains('id', $incomingId)
                    : false;

                if (!$alreadyExists) {
                    $assignment->evaluationCriteria()->create([
                        'cycle_partial_id' => $partialId,
                        'name' => trim($criterion['name']),
                        'percentage' => $criterion['percentage'],
                    ]);
                }
            }
        });

        return redirect()
            ->route('teacher.classes.evaluation.index', [
                'assignment' => $assignment,
                'partial_id' => $partialId,
            ])
            ->with('success', 'Criterios de evaluacion actualizados correctamente.');
    }

    public function cloneFromSameSubject(Request $request, TeachingAssignment $assignment)
    {
        $this->authorizeAssignmentOwner($assignment);

        $allowedPartialIds = $this->partialsForAssignment($assignment)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $data = $request->validate([
            'cycle_partial_id' => ['required', 'integer', Rule::in($allowedPartialIds)],
            'to_assignment_ids' => ['required', 'array', 'min:1'],
            'to_assignment_ids.*' => ['integer', 'exists:teaching_assignments,id'],
        ]);

        $partialId = (int) $data['cycle_partial_id'];
        $targetAssignmentIds = collect($data['to_assignment_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($targetAssignmentIds->isEmpty()) {
            return back()->withErrors([
                'clone' => 'Selecciona al menos un grupo destino.',
            ]);
        }

        $sourceCriteria = $assignment->evaluationCriteria()
            ->where('cycle_partial_id', $partialId)
            ->orderBy('id')
            ->get();

        if ($sourceCriteria->isEmpty()) {
            return back()->withErrors([
                'clone' => 'Esta materia no tiene rubros configurados en este parcial para clonar.',
            ]);
        }

        $targets = $this->cloneTargetCandidates($assignment, $partialId)
            ->whereIn('id', $targetAssignmentIds)
            ->values();

        if ($targets->count() !== $targetAssignmentIds->count()) {
            return back()->withErrors([
                'clone' => 'Selecciona solo grupos destino de la misma materia, ciclo y sin rubros configurados en este parcial.',
            ]);
        }

        DB::transaction(function () use ($targets, $sourceCriteria, $partialId) {
            foreach ($targets as $target) {
                foreach ($sourceCriteria as $criterion) {
                    $target->evaluationCriteria()->create([
                        'cycle_partial_id' => $partialId,
                        'name' => $criterion->name,
                        'percentage' => $criterion->percentage,
                    ]);
                }
            }
        });

        return redirect()
            ->route('teacher.classes.evaluation.index', [
                'assignment' => $assignment,
                'partial_id' => $partialId,
            ])
            ->with('success', 'Rubros clonados correctamente a '.$targets->count().' grupo(s).');
    }

    public function destroy(EvaluationCriterion $criterion)
    {
        $assignment = $criterion->assignment;

        $this->authorizeAssignmentOwner($assignment);

        if ($criterion->activities()->exists()) {
            return back()->withErrors([
                'criteria' => 'No puedes eliminar un criterio con actividades asociadas.',
            ]);
        }

        $criterion->delete();

        return back()->with('success', 'Criterio eliminado.');
    }

    private function authorizeAssignmentOwner(TeachingAssignment $assignment): void
    {
        $teacherId = auth()->user()?->teacher?->id;

        abort_if(
            !$teacherId || $assignment->teacher_id !== $teacherId,
            403
        );
    }

    private function partialsForAssignment(TeachingAssignment $assignment)
    {
        $cycleId = (int) (
            $assignment->schoolCycleGroup?->school_cycle_id
            ?: $assignment->schedules()
                ->where('is_active', true)
                ->orderByDesc('school_cycle_id')
                ->value('school_cycle_id')
        );

        if (! $cycleId) {
            return collect();
        }

        return CyclePartial::query()
            ->where('school_cycle_id', $cycleId)
            ->whereNotNull('academic_period_id')
            ->with('academicPeriod')
            ->orderBy('sort_order')
            ->get();
    }

    private function selectedPartial($partials, int $requestedId): ?CyclePartial
    {
        if ($partials->isEmpty()) {
            return null;
        }

        if ($requestedId > 0) {
            $byRequest = $partials->firstWhere('id', $requestedId);
            if ($byRequest) {
                return $byRequest;
            }
        }

        $today = now()->toDateString();
        $active = $partials->first(function (CyclePartial $partial) use ($today) {
            return $partial->start_date
                && $partial->end_date
                && $partial->start_date->toDateString() <= $today
                && $partial->end_date->toDateString() >= $today;
        });

        return $active ?: $partials->first();
    }

    private function cloneTargetCandidates(TeachingAssignment $assignment, int $partialId)
    {
        $cycleId = (int) optional($assignment->schoolCycleGroup)->school_cycle_id;
        $sourceCycleGroupId = (int) $assignment->school_cycle_group_id;

        return TeachingAssignment::query()
            ->with(['group:id,name'])
            ->whereKeyNot($assignment->id)
            ->where('teacher_id', $assignment->teacher_id)
            ->where('subject_id', $assignment->subject_id)
            ->where('is_active', true)
            ->when($sourceCycleGroupId > 0, fn ($query) => $query->where('school_cycle_group_id', '!=', $sourceCycleGroupId))
            ->when($cycleId > 0, fn ($query) => $query->whereHas('schoolCycleGroup', fn ($inner) => $inner->where('school_cycle_id', $cycleId)))
            ->orderBy('group_id')
            ->orderByRaw("CASE WHEN section_type IS NULL OR section_type = '' THEN 0 ELSE 1 END")
            ->orderBy('section_number')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (TeachingAssignment $candidate) => (int) ($candidate->school_cycle_group_id ?: $candidate->group_id))
            ->map(function ($groupAssignments) use ($partialId) {
                $assignmentIds = $groupAssignments
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values();

                $alreadyConfigured = EvaluationCriterion::query()
                    ->whereIn('teaching_assignment_id', $assignmentIds)
                    ->where('cycle_partial_id', $partialId)
                    ->exists();

                if ($alreadyConfigured) {
                    return null;
                }

                return $groupAssignments
                    ->sortBy(fn (TeachingAssignment $candidate) => [
                        ($candidate->section_type === null || $candidate->section_type === '') ? 0 : 1,
                        (int) ($candidate->section_number ?? 0),
                        (int) $candidate->id,
                    ])
                    ->first();
            })
            ->filter()
            ->sortBy(fn (TeachingAssignment $candidate) => $candidate->group?->name ?? '')
            ->values();
    }

    private function hasDuplicateNames(array $criteria): bool
    {
        $names = collect($criteria)
            ->map(fn ($row) => mb_strtolower(trim((string) ($row['name'] ?? ''))))
            ->filter()
            ->values();

        return $names->count() !== $names->unique()->count();
    }
}
