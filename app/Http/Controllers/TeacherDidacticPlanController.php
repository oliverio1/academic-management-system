<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\CyclePartial;
use App\Models\DidacticPlan;
use App\Models\SchoolCycle;
use App\Models\TeachingAssignment;
use App\Models\TemarioPoint;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeacherDidacticPlanController extends Controller
{
    public function index()
    {
        $teacherId = auth()->user()?->teacher?->id;
        abort_if(!$teacherId, 403);
        $activeCampusId = (int) session('active_campus_id', 0);

        $activeCycleId = SchoolCycle::query()
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $this->applyCampusFilterToCycleQuery($q, $activeCampusId))
            ->orderByDesc('start_date')
            ->value('id');

        $assignments = TeachingAssignment::query()
            ->where('teacher_id', $teacherId)
            ->whereHas('schedules', function ($query) use ($activeCampusId, $activeCycleId) {
                $query->where('is_active', true)
                    ->when($activeCycleId, fn ($q) => $q->where('school_cycle_id', (int) $activeCycleId))
                    ->when(
                        $activeCampusId > 0,
                        fn ($q) => $q->whereHas('schoolCycle', fn ($cycle) => $this->applyCampusFilterToCycleQuery($cycle, $activeCampusId))
                    );
            })
            ->with([
                'subject',
                'group',
                'didacticPlans' => function ($query) use ($activeCycleId) {
                    $query->when($activeCycleId, fn ($q) => $q->where('school_cycle_id', (int) $activeCycleId))
                        ->with('items');
                },
            ])
            ->orderByDesc('id')
            ->get()
            ->unique(fn ($assignment) => ((int) $assignment->subject_id) . '-' . ((int) $assignment->group_id))
            ->values();

        $activeCycle = $activeCycleId
            ? SchoolCycle::query()->whereKey((int) $activeCycleId)->first()
            : null;

        $assignments = $assignments->map(function ($assignment) use ($activeCycle) {
            $assignment->planning_status = $this->planningStatusForAssignment($assignment, $activeCycle);
            $latestPlan = ($assignment->didacticPlans ?? collect())
                ->sortByDesc(fn ($plan) => $plan->updated_at?->timestamp ?? 0)
                ->first();
            $assignment->latest_plan_id = $latestPlan?->id;
            return $assignment;
        });

        $completeBySubject = $assignments
            ->filter(fn ($a) => ($a->planning_status['key'] ?? null) === 'complete')
            ->groupBy('subject_id');

        $assignments = $assignments->map(function ($assignment) use ($completeBySubject) {
            $statusKey = $assignment->planning_status['key'] ?? null;
            $hasPlansInCycle = $assignment->didacticPlans->isNotEmpty();
            $candidates = collect($completeBySubject->get($assignment->subject_id, collect()))
                ->filter(fn ($a) => (int) $a->id !== (int) $assignment->id)
                ->values();

            $assignment->clone_source_assignment_id = null;
            $assignment->clone_source_label = null;

            if ($statusKey !== 'complete' && !$hasPlansInCycle && $candidates->isNotEmpty()) {
                $source = $candidates->first();
                $assignment->clone_source_assignment_id = (int) $source->id;
                $assignment->clone_source_label = ($source->group->name ?? '-') . ' · ' . ($source->subject->name ?? '-');
            }

            return $assignment;
        });

        return view('teacher.didactic_plans.index', compact('assignments'));
    }

    public function cloneFromPeer(Request $request, TeachingAssignment $assignment)
    {
        $this->authorizeAssignmentOwner($assignment);
        $activeCampusId = (int) session('active_campus_id', 0);
        $activeCycleId = SchoolCycle::query()
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $this->applyCampusFilterToCycleQuery($q, $activeCampusId))
            ->orderByDesc('start_date')
            ->value('id');

        $data = $request->validate([
            'source_assignment_id' => ['required', 'integer', 'exists:teaching_assignments,id'],
        ]);

        $source = TeachingAssignment::query()
            ->with(['didacticPlans.items'])
            ->whereKey((int) $data['source_assignment_id'])
            ->firstOrFail();

        $this->authorizeAssignmentOwner($source);

        abort_if((int) $source->subject_id !== (int) $assignment->subject_id, 422, 'Solo se puede clonar entre asignaciones de la misma materia.');
        abort_if((int) $source->id === (int) $assignment->id, 422, 'La asignación origen debe ser diferente.');

        $targetHasPlans = $assignment->didacticPlans()
            ->when($activeCycleId, fn ($q) => $q->where('school_cycle_id', (int) $activeCycleId))
            ->exists();
        abort_if($targetHasPlans, 422, 'La asignación destino ya tiene planeaciones. Elimina o ajusta manualmente antes de clonar.');

        $sourcePlan = DidacticPlan::query()
            ->where('teaching_assignment_id', (int) $source->id)
            ->when($activeCycleId, fn ($q) => $q->where('school_cycle_id', (int) $activeCycleId))
            ->with('items')
            ->orderByDesc('updated_at')
            ->first();

        abort_if(! $sourcePlan, 422, 'La asignación origen no tiene planeaciones para clonar.');

        DB::transaction(function () use ($assignment, $sourcePlan) {
            $newPlan = $assignment->didacticPlans()->create([
                'school_cycle_id' => $sourcePlan->school_cycle_id,
                'academic_period_id' => $sourcePlan->academic_period_id,
                'temario_unit_point_id' => $sourcePlan->temario_unit_point_id,
                'title' => $sourcePlan->title,
                'field_training' => $sourcePlan->field_training,
                'objective' => $sourcePlan->objective,
                'evaluation_instruments' => $sourcePlan->evaluation_instruments,
                'general_resources' => $sourcePlan->general_resources,
                'bibliography' => $sourcePlan->bibliography,
                'complementary_bibliography' => $sourcePlan->complementary_bibliography,
                'start_date' => $sourcePlan->start_date,
                'end_date' => $sourcePlan->end_date,
                'notes' => $sourcePlan->notes,
                'is_active' => (bool) $sourcePlan->is_active,
            ]);

            foreach ($sourcePlan->items as $item) {
                $newPlan->items()->create([
                    'position' => $item->position,
                    'field_training_point_id' => $item->field_training_point_id,
                    'objective' => $item->objective,
                    'temario_point_id' => $item->temario_point_id,
                    'temario_subtopic_ids' => $item->temario_subtopic_ids,
                    'opening' => $item->opening,
                    'development' => $item->development,
                    'closing' => $item->closing,
                    'resources' => $item->resources,
                    'evaluation' => $item->evaluation,
                    'start_date' => $item->start_date,
                    'end_date' => $item->end_date,
                ]);
            }
        });

        return redirect()
            ->route('teacher.didactic-plans.plans', $assignment)
            ->with('success', 'Planeación clonada correctamente desde la otra asignación.');
    }

    private function planningStatusForAssignment(TeachingAssignment $assignment, ?SchoolCycle $cycle): array
    {
        if (! $cycle || ! $cycle->start_date || ! $cycle->end_date) {
            return ['key' => 'na', 'label' => 'Sin ciclo activo', 'class' => 'badge-secondary'];
        }

        $plans = $assignment->didacticPlans ?? collect();
        if ($plans->isEmpty()) {
            return ['key' => 'not_started', 'label' => 'No comenzado', 'class' => 'badge-secondary'];
        }

        $cycleWeeks = $this->weekKeysBetween(
            Carbon::parse($cycle->start_date)->startOfDay(),
            Carbon::parse($cycle->end_date)->endOfDay()
        );

        if ($cycleWeeks->isEmpty()) {
            return ['key' => 'not_started', 'label' => 'No comenzado', 'class' => 'badge-secondary'];
        }

        $coveredWeeks = collect();
        foreach ($plans as $plan) {
            $items = $plan->items ?? collect();

            if ($items->isNotEmpty()) {
                foreach ($items as $item) {
                    if (! $item->start_date || ! $item->end_date) {
                        continue;
                    }
                    $coveredWeeks = $coveredWeeks->merge(
                        $this->weekKeysBetween(
                            Carbon::parse($item->start_date)->startOfDay(),
                            Carbon::parse($item->end_date)->endOfDay()
                        )
                    );
                }
            } elseif ($plan->start_date && $plan->end_date) {
                $coveredWeeks = $coveredWeeks->merge(
                    $this->weekKeysBetween(
                        Carbon::parse($plan->start_date)->startOfDay(),
                        Carbon::parse($plan->end_date)->endOfDay()
                    )
                );
            }
        }

        $coveredWeeks = $coveredWeeks->unique()->values();
        $cycleWeekSet = $cycleWeeks->flip();
        $coveredInsideCycle = $coveredWeeks
            ->filter(fn ($weekKey) => $cycleWeekSet->has($weekKey))
            ->unique()
            ->values();

        if ($coveredInsideCycle->isEmpty()) {
            return ['key' => 'not_started', 'label' => 'No comenzado', 'class' => 'badge-secondary'];
        }

        if ($coveredInsideCycle->count() >= $cycleWeeks->count()) {
            return ['key' => 'complete', 'label' => 'Completa', 'class' => 'badge-success'];
        }

        return ['key' => 'partial', 'label' => 'Parcial', 'class' => 'badge-warning'];
    }

    private function weekKeysBetween(Carbon $start, Carbon $end)
    {
        if ($start->gt($end)) {
            return collect();
        }

        $keys = collect();
        $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
        $limit = $end->copy()->endOfWeek(Carbon::SUNDAY);

        while ($cursor->lte($limit)) {
            $keys->push($cursor->format('o-\WW'));
            $cursor->addWeek();
        }

        return $keys->unique()->values();
    }

    public function plans(TeachingAssignment $assignment)
    {
        $this->authorizeAssignmentOwner($assignment);

        $plans = $assignment->didacticPlans()
            ->with(['academicPeriod', 'schoolCycle', 'items'])
            ->orderByDesc('created_at')
            ->get();

        return view('teacher.didactic_plans.plans', compact('assignment', 'plans'));
    }

    public function create(TeachingAssignment $assignment)
    {
        $this->authorizeAssignmentOwner($assignment);

        [$unitOptions, $topicOptions, $subtopicOptions] = $this->buildTemarioSelectors($assignment);

        $cycles = SchoolCycle::query()
            ->where('modality_id', $assignment->group->level->modality_id)
            ->when((int) session('active_campus_id', 0) > 0, fn ($q) => $this->applyCampusFilterToCycleQuery($q, (int) session('active_campus_id')))
            ->orderByDesc('start_date')
            ->get();

        $periods = AcademicPeriod::query()
            ->where('modality_id', $assignment->group->level->modality_id)
            ->orderBy('start_date')
            ->get();

        return view('teacher.didactic_plans.form', [
            'assignment' => $assignment,
            'plan' => new DidacticPlan(),
            'unitOptions' => $unitOptions,
            'topicOptions' => $topicOptions,
            'subtopicOptions' => $subtopicOptions,
            'cycles' => $cycles,
            'periods' => $periods,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request, TeachingAssignment $assignment)
    {
        $this->authorizeAssignmentOwner($assignment);
        $data = $this->validatePayload($request, $assignment);

        DB::transaction(function () use ($assignment, $data) {
            $plan = $assignment->didacticPlans()->create($data['plan']);
            $this->syncItems($plan, $data['items']);
        });

        return redirect()
            ->route('teacher.didactic-plans.plans', $assignment)
            ->with('success', 'Planeación didáctica creada correctamente.');
    }

    public function edit(DidacticPlan $plan)
    {
        $plan->loadMissing(['assignment.group.level', 'items']);
        $assignment = $plan->assignment;
        $this->authorizeAssignmentOwner($assignment);

        [$unitOptions, $topicOptions, $subtopicOptions] = $this->buildTemarioSelectors($assignment);

        $cycles = SchoolCycle::query()
            ->where('modality_id', $assignment->group->level->modality_id)
            ->when((int) session('active_campus_id', 0) > 0, fn ($q) => $this->applyCampusFilterToCycleQuery($q, (int) session('active_campus_id')))
            ->orderByDesc('start_date')
            ->get();

        $periods = AcademicPeriod::query()
            ->where('modality_id', $assignment->group->level->modality_id)
            ->orderBy('start_date')
            ->get();

        return view('teacher.didactic_plans.form', [
            'assignment' => $assignment,
            'plan' => $plan,
            'unitOptions' => $unitOptions,
            'topicOptions' => $topicOptions,
            'subtopicOptions' => $subtopicOptions,
            'cycles' => $cycles,
            'periods' => $periods,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, DidacticPlan $plan)
    {
        $plan->loadMissing('assignment.group.level');
        $assignment = $plan->assignment;
        $this->authorizeAssignmentOwner($assignment);

        $data = $this->validatePayload($request, $assignment);

        DB::transaction(function () use ($plan, $data) {
            $plan->update($data['plan']);
            $plan->items()->delete();
            $this->syncItems($plan, $data['items']);
        });

        return redirect()
            ->route('teacher.didactic-plans.plans', $assignment)
            ->with('success', 'Planeación didáctica actualizada correctamente.');
    }

    public function destroy(DidacticPlan $plan)
    {
        $plan->loadMissing('assignment');
        $assignment = $plan->assignment;
        $this->authorizeAssignmentOwner($assignment);

        $plan->delete();

        return back()->with('success', 'Planeación eliminada.');
    }

    public function pdf(DidacticPlan $plan)
    {
        $plan->loadMissing([
            'assignment.teacher.user',
            'assignment.group.level.modality',
            'assignment.subject',
            'schoolCycle',
            'academicPeriod',
            'temarioUnitPoint',
            'items.temarioPoint',
            'items.fieldTrainingPoint',
        ]);

        $assignment = $plan->assignment;
        $this->authorizeAssignmentOwner($assignment);

        $subtopicIds = $plan->items
            ->flatMap(fn ($item) => collect($item->temario_subtopic_ids ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $subtopicsById = TemarioPoint::query()
            ->whereIn('id', $subtopicIds)
            ->get()
            ->keyBy('id');

        $rows = $plan->items->map(function ($item, $index) use ($subtopicsById) {
            $subtopicsText = collect($item->temario_subtopic_ids ?? [])
                ->map(fn ($id) => $subtopicsById->get((int) $id))
                ->filter()
                ->map(fn ($point) => $this->pointText([
                    'label' => $point->label,
                    'content' => $point->content,
                ]))
                ->filter()
                ->implode('; ');

            return [
                'num' => $index + 1,
                'field_training' => $item->fieldTrainingPoint ? $this->pointText([
                    'label' => $item->fieldTrainingPoint->label,
                    'content' => $item->fieldTrainingPoint->content,
                ]) : '-',
                'objective' => $item->objective ?: '-',
                'topic' => $item->temarioPoint ? $this->pointText([
                    'label' => $item->temarioPoint->label,
                    'content' => $item->temarioPoint->content,
                ]) : '-',
                'subtopics' => $subtopicsText !== '' ? $subtopicsText : '-',
                'opening' => $item->opening ?: '-',
                'development' => $item->development ?: '-',
                'closing' => $item->closing ?: '-',
                'resources' => $item->resources ?: '-',
                'evaluation' => $item->evaluation ?: '-',
                'start_date' => optional($item->start_date)->format('d/m/Y') ?: '-',
                'end_date' => optional($item->end_date)->format('d/m/Y') ?: '-',
            ];
        });

        $itemStartDates = $plan->items
            ->map(fn ($item) => $item->start_date ? Carbon::parse($item->start_date)->toDateString() : null)
            ->filter()
            ->values();
        $itemEndDates = $plan->items
            ->map(fn ($item) => $item->end_date ? Carbon::parse($item->end_date)->toDateString() : null)
            ->filter()
            ->values();

        $planStartDate = $itemStartDates->isNotEmpty() ? $itemStartDates->sort()->first() : null;
        $planEndDate = $itemEndDates->isNotEmpty() ? $itemEndDates->sort()->last() : null;

        $cuatrimestreLabel = '-';
        if ($plan->school_cycle_id && $planStartDate && $planEndDate) {
            $matchingPartial = CyclePartial::query()
                ->where('school_cycle_id', $plan->school_cycle_id)
                ->whereDate('start_date', '<=', $planStartDate)
                ->whereDate('end_date', '>=', $planEndDate)
                ->orderBy('sort_order')
                ->orderBy('start_date')
                ->first();

            if (!$matchingPartial) {
                $matchingPartial = CyclePartial::query()
                    ->where('school_cycle_id', $plan->school_cycle_id)
                    ->whereDate('start_date', '<=', $planStartDate)
                    ->whereDate('end_date', '>=', $planStartDate)
                    ->orderBy('sort_order')
                    ->orderBy('start_date')
                    ->first();
            }

            if ($matchingPartial) {
                $cuatrimestreLabel = $matchingPartial->name ?: ($matchingPartial->code ?: '-');
            }
        }

        $referencesList = collect([
            $plan->bibliography,
            $plan->complementary_bibliography,
        ])->flatMap(function ($value) {
            if (!is_string($value) || trim($value) === '') {
                return [];
            }

            return preg_split('/[\r\n;]+/u', $value) ?: [];
        })->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique(fn ($value) => mb_strtolower($value, 'UTF-8'))
            ->values()
            ->all();

        $filename = 'PLANEACION_' . $assignment->group->name . '_' . preg_replace('/\s+/', '_', $assignment->subject->name) . '.pdf';

        return SnappyPdf::loadView('teacher.didactic_plans.pdf', [
            'plan' => $plan,
            'assignment' => $assignment,
            'rows' => $rows,
            'cuatrimestre_label' => $cuatrimestreLabel,
            'ciclo_escolar_label' => $plan->schoolCycle->name ?? '-',
            'references_list' => $referencesList,
        ])
            ->setPaper('letter', 'landscape')
            ->setOption('encoding', 'UTF-8')
            ->setOption('disable-javascript', true)
            ->setOption('enable-local-file-access', true)
            ->setOption('footer-right', 'Pagina [page] de [toPage]')
            ->inline($filename);
    }

    private function validatePayload(Request $request, TeachingAssignment $assignment): array
    {
        $payload = $request->input('units_payload');
        $decodedItems = [];
        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $decodedItems = $decoded;
            }
        }

        // Fallback: si no se guardo explicitamente en tabla temporal,
        // tomar los renglones actuales y asociarlos al campo formativo activo.
        if (count($decodedItems) === 0) {
            $rawItems = collect($request->input('items', []))
                ->filter(function ($item) {
                    if (!is_array($item)) {
                        return false;
                    }
                    return trim((string) ($item['temario_point_id'] ?? '')) !== ''
                        || !empty($item['temario_subtopic_ids'] ?? [])
                        || trim((string) ($item['opening'] ?? '')) !== ''
                        || trim((string) ($item['development'] ?? '')) !== ''
                        || trim((string) ($item['closing'] ?? '')) !== ''
                        || trim((string) ($item['resources'] ?? '')) !== ''
                        || trim((string) ($item['evaluation'] ?? '')) !== ''
                        || trim((string) ($item['start_date'] ?? '')) !== ''
                        || trim((string) ($item['end_date'] ?? '')) !== '';
                })
                ->values();

            if ($rawItems->isNotEmpty()) {
                $currentFieldTrainingId = (int) ($request->input('current_field_training_point_id') ?? 0);
                $currentObjective = trim((string) ($request->input('current_unit_objective') ?? ''));

                if ($currentFieldTrainingId <= 0) {
                    throw ValidationException::withMessages([
                        'current_field_training_point_id' => 'Selecciona un campo formativo para los renglones capturados.',
                    ]);
                }

                $decodedItems = $rawItems->map(function ($item) use ($currentFieldTrainingId, $currentObjective) {
                    return [
                        'field_training_point_id' => $currentFieldTrainingId,
                        'objective' => $currentObjective !== '' ? $currentObjective : null,
                        'temario_point_id' => $item['temario_point_id'] ?? null,
                        'temario_subtopic_ids' => $item['temario_subtopic_ids'] ?? [],
                        'opening' => $item['opening'] ?? null,
                        'development' => $item['development'] ?? null,
                        'closing' => $item['closing'] ?? null,
                        'resources' => $item['resources'] ?? null,
                        'evaluation' => $item['evaluation'] ?? null,
                        'start_date' => $item['start_date'] ?? null,
                        'end_date' => $item['end_date'] ?? null,
                    ];
                })->values()->all();
            }
        }

        // El formulario UI usa una fila de captura temporal (items[0]) que puede venir vacia.
        // Para guardar, solo debemos validar lo consolidado en units_payload.
        $request->merge(['items' => $decodedItems]);

        if (count($decodedItems) === 0) {
            throw ValidationException::withMessages([
                'units_payload' => 'Debes agregar al menos una unidad usando el boton "Guardar unidad en tabla temporal".',
            ]);
        }

        $temarioIds = $assignment->temarios()->pluck('id');

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'school_cycle_id' => 'required|integer|exists:school_cycles,id',
            'academic_period_id' => 'nullable|integer|exists:academic_periods,id',
            'evaluation_instruments' => 'nullable|string',
            'general_resources' => 'nullable|string',
            'bibliography' => 'nullable|string',
            'complementary_bibliography' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'array|min:1',
            'items.*.field_training_point_id' => [
                'required',
                'integer',
                Rule::exists('temario_points', 'id')->where(function ($query) use ($temarioIds) {
                    $query->whereIn('temario_id', $temarioIds)->where('level', 1);
                }),
            ],
            'items.*.objective' => 'nullable|string',
            'items.*.temario_point_id' => [
                'required',
                'integer',
                Rule::exists('temario_points', 'id')->where(function ($query) use ($temarioIds) {
                    $query->whereIn('temario_id', $temarioIds)->where('level', 2);
                }),
            ],
            'items.*.temario_subtopic_ids' => 'nullable|array',
            'items.*.temario_subtopic_ids.*' => [
                'integer',
                Rule::exists('temario_points', 'id')->where(function ($query) use ($temarioIds) {
                    $query->whereIn('temario_id', $temarioIds)->where('level', '>=', 3);
                }),
            ],
            'items.*.opening' => 'nullable|string',
            'items.*.development' => 'nullable|string',
            'items.*.closing' => 'nullable|string',
            'items.*.resources' => 'nullable|string',
            'items.*.evaluation' => 'nullable|string',
            'items.*.start_date' => 'required|date',
            'items.*.end_date' => 'required|date',
        ]);

        $items = collect($data['items'])->values()->map(function ($item, $idx) {
            $item['position'] = $idx + 1;
            $item['temario_subtopic_ids'] = collect($item['temario_subtopic_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
            return $item;
        });

        $schoolCycle = SchoolCycle::query()->find((int) $data['school_cycle_id']);
        $cycleStart = $schoolCycle?->start_date ? Carbon::parse($schoolCycle->start_date)->toDateString() : null;
        $cycleEnd = $schoolCycle?->end_date ? Carbon::parse($schoolCycle->end_date)->toDateString() : null;

        foreach ($items as $idx => $item) {
            if (!empty($item['start_date']) && !empty($item['end_date']) && $item['end_date'] < $item['start_date']) {
                throw ValidationException::withMessages([
                    "items.$idx.end_date" => 'La fecha término de cada tema debe ser mayor o igual a la fecha inicio.',
                ]);
            }

            if ($cycleStart && $cycleEnd) {
                if (!empty($item['start_date']) && ($item['start_date'] < $cycleStart || $item['start_date'] > $cycleEnd)) {
                    throw ValidationException::withMessages([
                        "items.$idx.start_date" => 'La fecha inicio debe estar dentro del ciclo escolar seleccionado.',
                    ]);
                }

                if (!empty($item['end_date']) && ($item['end_date'] < $cycleStart || $item['end_date'] > $cycleEnd)) {
                    throw ValidationException::withMessages([
                        "items.$idx.end_date" => 'La fecha término debe estar dentro del ciclo escolar seleccionado.',
                    ]);
                }
            }
        }

        return [
            'plan' => [
                'title' => $data['title'],
                'school_cycle_id' => $data['school_cycle_id'],
                'academic_period_id' => $data['academic_period_id'] ?? null,
                'temario_unit_point_id' => null,
                'field_training' => null,
                'objective' => null,
                'evaluation_instruments' => $data['evaluation_instruments'] ?? null,
                'general_resources' => $data['general_resources'] ?? null,
                'bibliography' => $data['bibliography'] ?? null,
                'complementary_bibliography' => $data['complementary_bibliography'] ?? null,
                'start_date' => null,
                'end_date' => null,
                'notes' => $data['notes'] ?? null,
            ],
            'items' => $items->all(),
        ];
    }

    private function syncItems(DidacticPlan $plan, array $items): void
    {
        foreach ($items as $item) {
            $plan->items()->create($item);
        }
    }

    private function buildTemarioSelectors(TeachingAssignment $assignment): array
    {
        $points = $assignment->temarios()
            ->with(['points' => function ($query) {
                $query->orderBy('position');
            }])
            ->orderBy('id')
            ->get()
            ->flatMap(function ($temario) {
                return $temario->points->map(function ($point) use ($temario) {
                    return [
                        'id' => (int) $point->id,
                        'label' => (string) ($point->label ?? ''),
                        'content' => (string) ($point->content ?? ''),
                        'level' => (int) ($point->level ?? 1),
                        'temario_title' => (string) ($temario->title ?? ''),
                        'key' => $this->labelKey((string) ($point->label ?? '')),
                    ];
                });
            })
            ->values();

        $unitOptions = $points
            ->filter(fn ($point) => (int) $point['level'] === 1)
            ->map(function ($unit) {
                $parsed = $this->parseUnitContent((string) ($unit['content'] ?? ''));
                $label = trim((string) ($unit['label'] ?? ''));
                $name = trim((string) ($parsed['name'] ?? ''));
                $text = trim(($label !== '' ? $label . ' ' : '') . $name);

                return [
                    'id' => (int) $unit['id'],
                    'text' => $text !== '' ? $text : $this->pointText($unit),
                    'key' => $unit['key'],
                    'objective' => $parsed['objective'],
                ];
            })
            ->values();

        $unitsByFirst = $points
            ->filter(fn ($point) => (int) $point['level'] === 1 && !empty($point['key']))
            ->mapWithKeys(function ($unit) {
                $first = explode('.', (string) $unit['key'])[0] ?? null;
                return $first ? [$first => $unit] : [];
            });

        $topicOptions = $points
            ->filter(fn ($point) => (int) $point['level'] === 2)
            ->map(function ($topic) use ($unitsByFirst) {
                $first = !empty($topic['key']) ? (explode('.', (string) $topic['key'])[0] ?? null) : null;
                $unit = $first ? ($unitsByFirst->get($first) ?? null) : null;

                return [
                    'id' => (int) $topic['id'],
                    'text' => $this->pointText($topic),
                    'unit_text' => $unit ? $this->pointText($unit) : null,
                    'unit_id' => $unit ? (int) $unit['id'] : null,
                    'key' => $topic['key'],
                ];
            })
            ->values();

        $topicKeyById = $topicOptions->mapWithKeys(fn ($topic) => [(int) $topic['id'] => (string) ($topic['key'] ?? '')]);

        $subtopicOptions = $points
            ->filter(fn ($point) => (int) $point['level'] >= 3)
            ->map(function ($subtopic) use ($topicKeyById) {
                $topicId = null;
                foreach ($topicKeyById as $candidateTopicId => $topicKey) {
                    if ($topicKey !== '' && !empty($subtopic['key']) && str_starts_with((string) $subtopic['key'], $topicKey . '.')) {
                        $topicId = (int) $candidateTopicId;
                        break;
                    }
                }

                return [
                    'id' => (int) $subtopic['id'],
                    'text' => $this->pointText($subtopic),
                    'topic_id' => $topicId,
                ];
            })
            ->filter(fn ($subtopic) => !empty($subtopic['topic_id']))
            ->values();

        return [$unitOptions, $topicOptions, $subtopicOptions];
    }

    private function pointText(array $point): string
    {
        $label = trim((string) ($point['label'] ?? ''));
        $content = trim((string) ($point['content'] ?? ''));
        return trim(($label !== '' ? $label . ' ' : '') . $content);
    }

    private function labelKey(string $label): ?string
    {
        $clean = trim($label);
        if ($clean === '') {
            return null;
        }

        if (preg_match('/^([0-9]+(?:\.[0-9]+)*)\.?$/', $clean, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function parseUnitContent(string $content): array
    {
        $raw = trim($content);
        if ($raw === '') {
            return ['name' => '', 'objective' => null];
        }

        if (preg_match('/^(.*?)\s*\|\s*Objetivo(?:\s+espec[íi]fico)?\s*:\s*(.+)$/ui', $raw, $matches) === 1) {
            $name = trim((string) ($matches[1] ?? ''));
            $objective = trim((string) ($matches[2] ?? ''));

            return [
                'name' => $name !== '' ? $name : $raw,
                'objective' => $objective !== '' ? $objective : null,
            ];
        }

        return [
            'name' => $raw,
            'objective' => null,
        ];
    }

    private function authorizeAssignmentOwner(TeachingAssignment $assignment): void
    {
        $teacherId = auth()->user()?->teacher?->id;
        $activeCampusId = (int) session('active_campus_id', 0);
        $belongsToCampus = $activeCampusId <= 0 || $assignment->schedules()
            ->whereHas('schoolCycle', fn ($query) => $this->applyCampusFilterToCycleQuery($query, $activeCampusId))
            ->exists();

        abort_if(!$teacherId || $assignment->teacher_id !== $teacherId || !$belongsToCampus, 403);
    }

    private function applyCampusFilterToCycleQuery($query, int $activeCampusId): void
    {
        $query->where(function ($nested) use ($activeCampusId) {
            $nested->where('campus_id', $activeCampusId)
                ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
        });
    }
}
