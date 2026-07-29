<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\AcademicCalendarDay;
use App\Models\CyclePartial;
use App\Models\DidacticPlan;
use App\Models\Schedule;
use App\Models\SchoolCycle;
use App\Models\TeachingAssignment;
use App\Models\TemarioPoint;
use App\Services\DidacticPlanActivityGeneratorService;
use App\Services\CurrentSchoolCycle;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TeacherDidacticPlanController extends Controller
{
    public function index()
    {
        $teacherId = auth()->user()?->teacher?->id;
        abort_if(!$teacherId, 403);
        $activeCampusId = (int) session('active_campus_id', 0);

        $activeCycleId = app(CurrentSchoolCycle::class)->id(auth()->user(), $activeCampusId);

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
            $assignment->latest_plan_status = $latestPlan?->status;
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
        $activeCycleId = app(CurrentSchoolCycle::class)->id($request->user(), $activeCampusId);

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
                'unam_incorporation_key' => $sourcePlan->unam_incorporation_key,
                'teacher_dgire_file' => $sourcePlan->teacher_dgire_file,
                'technical_review_date' => $sourcePlan->technical_review_date,
                'subject_character' => $sourcePlan->subject_character,
                'subject_key' => $sourcePlan->subject_key,
                'total_annual_hours' => $sourcePlan->total_annual_hours,
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

    public function cloneToPeerGroups(Request $request, DidacticPlan $plan)
    {
        $plan->loadMissing([
            'assignment.subject',
            'assignment.group',
            'assignment.schoolCycleGroup.schoolCycle',
            'items',
            'schoolCycle',
            'academicPeriod',
        ]);

        $sourceAssignment = $plan->assignment;
        $this->authorizeAssignmentOwner($sourceAssignment);

        $data = $request->validate([
            'replace_existing' => 'nullable|boolean',
        ]);

        $cycle = $plan->schoolCycle ?: $sourceAssignment->schoolCycleGroup?->schoolCycle;
        abort_if(! $cycle, 422, 'La planeacion origen no tiene ciclo escolar.');

        $sourceItems = $plan->items->sortBy('position')->values();
        abort_if($sourceItems->isEmpty(), 422, 'La planeacion origen no tiene sesiones para clonar.');

        $targets = TeachingAssignment::query()
            ->with(['group', 'subject', 'schoolCycleGroup.schoolCycle'])
            ->where('teacher_id', $sourceAssignment->teacher_id)
            ->where('subject_id', $sourceAssignment->subject_id)
            ->where('id', '!=', $sourceAssignment->id)
            ->where('is_active', true)
            ->whereHas('schoolCycleGroup', fn ($query) => $query->where('school_cycle_id', $cycle->id)->where('is_active', true))
            ->orderBy('group_id')
            ->get();

        $replaceExisting = (bool) ($data['replace_existing'] ?? false);
        $created = 0;
        $skipped = [];

        DB::transaction(function () use ($targets, $plan, $cycle, $sourceItems, $replaceExisting, &$created, &$skipped) {
            foreach ($targets as $target) {
                $existingQuery = $target->didacticPlans()->where('school_cycle_id', $cycle->id);

                if ($existingQuery->exists()) {
                    if (! $replaceExisting) {
                        $skipped[] = ($target->group->name ?? ('ID ' . $target->id)) . ' ya tenia planeacion';
                        continue;
                    }

                    $existingQuery->delete();
                }

                $targetSessions = $this->recalendarizedSessionsForAssignment($target, $cycle);
                if ($targetSessions->isEmpty()) {
                    $skipped[] = ($target->group->name ?? ('ID ' . $target->id)) . ' no tiene sesiones calendarizadas';
                    continue;
                }

                $newPlan = $target->didacticPlans()->create([
                    'school_cycle_id' => $cycle->id,
                    'academic_period_id' => $plan->academic_period_id,
                    'temario_unit_point_id' => $plan->temario_unit_point_id,
                    'title' => 'Planeacion ' . ($target->subject->name ?? 'Materia') . ' - Grupo ' . ($target->group->name ?? ''),
                    'unam_incorporation_key' => $plan->unam_incorporation_key,
                    'teacher_dgire_file' => $plan->teacher_dgire_file,
                    'technical_review_date' => $plan->technical_review_date,
                    'subject_character' => $plan->subject_character,
                    'subject_key' => $plan->subject_key,
                    'total_annual_hours' => $plan->total_annual_hours,
                    'field_training' => $plan->field_training,
                    'objective' => $plan->objective,
                    'evaluation_instruments' => $plan->evaluation_instruments,
                    'general_resources' => $plan->general_resources,
                    'bibliography' => $plan->bibliography,
                    'complementary_bibliography' => $plan->complementary_bibliography,
                    'start_date' => null,
                    'end_date' => null,
                    'notes' => $plan->notes,
                    'is_active' => (bool) $plan->is_active,
                ]);

                $newItems = $targetSessions->values()->map(function (array $session, int $index) use ($sourceItems, $targetSessions) {
                    $sourceIndex = (int) floor($index * $sourceItems->count() / max(1, $targetSessions->count()));
                    $sourceIndex = min($sourceIndex, $sourceItems->count() - 1);
                    $sourceItem = $sourceItems->get($sourceIndex);

                    return [
                        'position' => $index + 1,
                        'field_training_point_id' => $sourceItem->field_training_point_id,
                        'objective' => $sourceItem->objective,
                        'temario_point_id' => $sourceItem->temario_point_id,
                        'temario_subtopic_ids' => $sourceItem->temario_subtopic_ids,
                        'opening' => $sourceItem->opening,
                        'development' => $sourceItem->development,
                        'closing' => $sourceItem->closing,
                        'resources' => $sourceItem->resources,
                        'evaluation' => $sourceItem->evaluation,
                        'start_date' => $session['date'],
                        'end_date' => $session['date'],
                    ];
                });

                foreach ($newItems as $newItem) {
                    $newPlan->items()->create($newItem);
                }

                $newPlan->update([
                    'dgire_metadata' => $this->buildDgireMetadataForClonedPlan($newItems, $cycle),
                ]);

                $created++;
            }
        });

        $message = "Planeaciones generadas: {$created}.";
        if (! empty($skipped)) {
            $message .= ' Omitidas: ' . implode('; ', $skipped) . '.';
        }

        return redirect()
            ->route('teacher.didactic-plans.plans', $sourceAssignment)
            ->with('success', $message);
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

        $hasFinalPlan = $plans
            ->contains(fn ($plan) => ($plan->status ?? DidacticPlan::STATUS_FINAL) === DidacticPlan::STATUS_FINAL);

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
            return [
                'key' => 'complete',
                'label' => $hasFinalPlan ? 'Completa' : 'Tentativa completa',
                'class' => $hasFinalPlan ? 'badge-success' : 'badge-info',
            ];
        }

        return [
            'key' => 'partial',
            'label' => $hasFinalPlan ? 'Parcial' : 'Tentativa parcial',
            'class' => $hasFinalPlan ? 'badge-warning' : 'badge-info',
        ];
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

    public function downloadTemplate(TeachingAssignment $assignment)
    {
        $this->authorizeAssignmentOwner($assignment);
        $assignment->loadMissing(['subject', 'group.level', 'schoolCycleGroup.schoolCycle', 'teacher.user']);

        [$unitOptions, $topicOptions, $subtopicOptions] = $this->buildTemarioSelectors($assignment);

        $cycles = $this->cyclesForAssignment($assignment);
        $defaultCycle = $this->defaultCycleForAssignment($assignment, $cycles);
        $prefillPlan = $this->prefillPlanForTemplate($assignment, $defaultCycle);
        $periods = AcademicPeriod::query()
            ->where('modality_id', $assignment->group->level->modality_id)
            ->orderBy('start_date')
            ->get();

        $spreadsheet = $this->buildPlanningTemplateWorkbook(
            $assignment,
            $unitOptions,
            $topicOptions,
            $subtopicOptions,
            $cycles,
            $defaultCycle,
            $periods,
            $prefillPlan
        );

        $filename = sprintf(
            'plantilla-planeacion-%s-%s.xlsx',
            $this->slugForFilename($assignment->subject->name ?? 'materia'),
            $this->slugForFilename($assignment->group->name ?? 'grupo')
        );

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function create(TeachingAssignment $assignment)
    {
        $this->authorizeAssignmentOwner($assignment);
        $assignment->loadMissing(['schoolCycleGroup.schoolCycle', 'group.level']);

        [$unitOptions, $topicOptions, $subtopicOptions] = $this->buildTemarioSelectors($assignment);

        $cycles = $this->cyclesForAssignment($assignment);
        $defaultCycleId = $this->defaultCycleForAssignment($assignment, $cycles)?->id;

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
            'defaultCycleId' => $defaultCycleId,
            'periods' => $periods,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request, TeachingAssignment $assignment, DidacticPlanActivityGeneratorService $activityGenerator)
    {
        $this->authorizeAssignmentOwner($assignment);
        $data = $this->validatePayload($request, $assignment);

        $summary = DB::transaction(function () use ($assignment, $data, $activityGenerator) {
            $data['plan']['status'] = DidacticPlan::STATUS_FINAL;
            $data['plan']['generated_by_system'] = false;
            $data['plan']['generated_at'] = null;
            $plan = $assignment->didacticPlans()->create($data['plan']);
            $this->syncItems($plan, $data['items']);

            return $activityGenerator->generate($plan->refresh(), ['replace' => false]);
        });

        return redirect()
            ->route('teacher.didactic-plans.plans', $assignment)
            ->with('success', 'Planeación didáctica creada correctamente. Actividades generadas: '
                .($summary['session_activities_created'] + $summary['session_activities_updated']).'.');
    }

    public function importForm(TeachingAssignment $assignment)
    {
        $this->authorizeAssignmentOwner($assignment);
        $assignment->loadMissing(['subject', 'group.level', 'schoolCycleGroup.schoolCycle']);

        return view('teacher.didactic_plans.import', [
            'assignment' => $assignment,
        ]);
    }

    public function import(Request $request, TeachingAssignment $assignment, DidacticPlanActivityGeneratorService $activityGenerator)
    {
        $this->authorizeAssignmentOwner($assignment);
        $assignment->loadMissing(['subject', 'group.level', 'schoolCycleGroup.schoolCycle']);

        $validated = $request->validate([
            'planning_file' => 'required|file|mimes:xlsx,xls|max:20480',
            'replace_existing' => 'nullable|boolean',
        ]);

        $data = $this->parsePlanningWorkbook(
            $request->file('planning_file')->getRealPath(),
            $assignment
        );

        $replaceExisting = (bool) ($validated['replace_existing'] ?? false);

        $summary = DB::transaction(function () use ($assignment, $data, $replaceExisting, $activityGenerator) {
            if ($replaceExisting) {
                $assignment->didacticPlans()
                    ->where('school_cycle_id', (int) $data['plan']['school_cycle_id'])
                    ->delete();
            } else {
                $assignment->didacticPlans()
                    ->where('school_cycle_id', (int) $data['plan']['school_cycle_id'])
                    ->where('status', DidacticPlan::STATUS_TENTATIVE)
                    ->delete();
            }

            $data['plan']['status'] = DidacticPlan::STATUS_FINAL;
            $data['plan']['generated_by_system'] = false;
            $data['plan']['generated_at'] = null;

            $plan = $assignment->didacticPlans()->create($data['plan']);
            $this->syncItems($plan, $data['items']);

            return $activityGenerator->generate($plan->refresh(), ['replace' => false]);
        });

        return redirect()
            ->route('teacher.didactic-plans.plans', $assignment)
            ->with(
                'success',
                'Planeacion importada correctamente: ' . count($data['items']) . ' sesiones registradas. Actividades generadas: '
                    .($summary['session_activities_created'] + $summary['session_activities_updated'])
                    .'.'
            );
    }

    public function edit(DidacticPlan $plan)
    {
        $plan->loadMissing(['assignment.group.level', 'items']);
        $assignment = $plan->assignment;
        $this->authorizeAssignmentOwner($assignment);

        $assignment->loadMissing(['schoolCycleGroup.schoolCycle', 'group.level']);

        [$unitOptions, $topicOptions, $subtopicOptions] = $this->buildTemarioSelectors($assignment);

        $cycles = $this->cyclesForAssignment($assignment, $plan->school_cycle_id ? [(int) $plan->school_cycle_id] : []);
        $defaultCycleId = (int) ($plan->school_cycle_id ?: optional($this->defaultCycleForAssignment($assignment, $cycles))->id);

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
            'defaultCycleId' => $defaultCycleId,
            'periods' => $periods,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, DidacticPlan $plan, DidacticPlanActivityGeneratorService $activityGenerator)
    {
        $plan->loadMissing('assignment.group.level');
        $assignment = $plan->assignment;
        $this->authorizeAssignmentOwner($assignment);

        $data = $this->validatePayload($request, $assignment);

        $summary = DB::transaction(function () use ($plan, $data, $activityGenerator) {
            $data['plan']['status'] = DidacticPlan::STATUS_FINAL;
            $data['plan']['generated_by_system'] = false;
            $data['plan']['generated_at'] = null;
            $plan->update($data['plan']);
            $plan->items()->delete();
            $this->syncItems($plan, $data['items']);

            return $activityGenerator->generate($plan->refresh(), ['replace' => true]);
        });

        return redirect()
            ->route('teacher.didactic-plans.plans', $assignment)
            ->with('success', 'Planeación didáctica actualizada correctamente. Actividades actualizadas: '
                .($summary['session_activities_created'] + $summary['session_activities_updated']).'.');
    }

    public function confirmFinal(DidacticPlan $plan, DidacticPlanActivityGeneratorService $activityGenerator)
    {
        $plan->loadMissing(['assignment', 'items']);
        $assignment = $plan->assignment;
        $this->authorizeAssignmentOwner($assignment);

        abort_if(
            ($plan->status ?? DidacticPlan::STATUS_FINAL) !== DidacticPlan::STATUS_TENTATIVE,
            422,
            'Solo se pueden confirmar planeaciones tentativas.'
        );

        abort_if($plan->items->isEmpty(), 422, 'La planeacion no tiene sesiones para confirmar.');

        $confirmationNote = 'Confirmada como final por el docente el '.now()->format('Y-m-d H:i').'.';

        $plan->update([
            'status' => DidacticPlan::STATUS_FINAL,
            'generated_by_system' => false,
            'generated_at' => null,
            'notes' => trim((string) $plan->notes) !== ''
                ? rtrim((string) $plan->notes)."\n".$confirmationNote
                : $confirmationNote,
        ]);

        $summary = $activityGenerator->generate($plan->refresh(), ['replace' => false]);

        return redirect()
            ->route('teacher.didactic-plans.plans', $assignment)
            ->with(
                'success',
                'Planeacion confirmada como final. Actividades generadas: '
                    .($summary['session_activities_created'] + $summary['session_activities_updated'])
                    .'. Actividades evaluables: '
                    .($summary['activities_created'] + $summary['activities_updated'])
                    .'.'
            );
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
            'assignment.subject.temarios.points',
            'schoolCycle',
            'academicPeriod',
            'temarioUnitPoint',
            'items.temarioPoint',
            'items.fieldTrainingPoint',
        ]);

        $assignment = $plan->assignment;
        $this->authorizeAssignmentOwner($assignment);

        $pdfSchedules = \App\Models\Schedule::query()
            ->with('assignment')
            ->where('is_active', true)
            ->whereHas('assignment', function ($query) use ($assignment) {
                $query->where('group_id', $assignment->group_id)
                    ->where('subject_id', $assignment->subject_id)
                    ->where('teacher_id', $assignment->teacher_id);
            })
            ->when($plan->school_cycle_id, fn ($query) => $query->where('school_cycle_id', $plan->school_cycle_id))
            ->get();

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
                'field_training_id' => $item->field_training_point_id,
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
            'pdf_schedules' => $pdfSchedules,
            'rows' => $rows,
            'cuatrimestre_label' => $cuatrimestreLabel,
            'ciclo_escolar_label' => $plan->schoolCycle->name ?? '-',
            'references_list' => $referencesList,
        ])
            ->setPaper('letter', 'landscape')
            ->setOption('encoding', 'UTF-8')
            ->setOption('disable-javascript', true)
            ->setOption('enable-local-file-access', true)
            ->setOption('footer-center', '[page] de [toPage]')
            ->setOption('footer-font-size', 6)
            ->inline($filename);
    }

    private function buildPlanningTemplateWorkbook(
        TeachingAssignment $assignment,
        $unitOptions,
        $topicOptions,
        $subtopicOptions,
        $cycles,
        ?SchoolCycle $defaultCycle,
        $periods,
        ?DidacticPlan $prefillPlan = null
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('AMS')
            ->setTitle('Plantilla de planeacion didactica')
            ->setSubject(($assignment->subject->name ?? 'Materia') . ' - Grupo ' . ($assignment->group->name ?? ''));

        $headerFill = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']];
        $subHeaderFill = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9EAF7']];
        $border = ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2EC']]];

        $unitById = collect($unitOptions)->keyBy('id');
        $subtopicsByTopic = collect($subtopicOptions)->groupBy('topic_id');
        $unitLabels = collect($unitOptions)
            ->map(fn ($unit) => !empty($unit['key']) ? 'Unidad ' . $unit['key'] : null)
            ->filter()
            ->values();
        $subject = $assignment->subject;

        $institution = $spreadsheet->getActiveSheet();
        $institution->setTitle('Institucion');
        $institution->fromArray([
            ['Campo', 'Valor', 'Notas'],
            ['Nombre de la ISI', 'Universidad Latinoamericana', '1.1 Datos de la institucion'],
            ['Clave de incorporacion a la UNAM', $prefillPlan?->unam_incorporation_key ?? '', '1.1 Datos de la institucion'],
            ['Ciclo lectivo', $defaultCycle?->name ?? '', '1.1 Datos de la institucion'],
            ['Nombre del docente', $assignment->teacher->user->name ?? '', '1.2 Datos del docente'],
            ['No. de expediente DGIRE-UNAM', $prefillPlan?->teacher_dgire_file ?? '', '1.2 Datos del docente'],
            ['Fecha de elaboracion', now()->format('Y-m-d'), '1.2 Datos del docente'],
            ['Fecha de revision de la DT', optional($prefillPlan?->technical_review_date)->format('Y-m-d') ?? '', '1.2 Datos del docente'],
            ['Direccion Tecnica', 'Miriam Paola Perez Luna', 'Responsable de revision'],
            ['Cantidad de grupos con la misma asignatura', '1', '1.4 Grupo(s) y horarios'],
            ['Clave de grupo', $assignment->group->name ?? '', 'Dado de alta ante DGIRE'],
        ], null, 'A1');
        $this->styleTemplateRange($institution, 'A1:C1', $headerFill, true);
        $institution->getStyle('A1:C11')->getBorders()->applyFromArray($border);
        $institution->getStyle('A:C')->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        foreach (['A' => 42, 'B' => 58, 'C' => 58] as $column => $width) {
            $institution->getColumnDimension($column)->setWidth($width);
        }
        $institution->freezePane('A2');

        $subjectSheet = $spreadsheet->createSheet();
        $subjectSheet->setTitle('Materia');
        $subjectSheet->fromArray([
            ['Campo', 'Valor', 'Notas'],
            ['Nombre de la asignatura', $subject->name ?? '', '1.3 Programa de la asignatura'],
            ['Tipo DGIRE de la asignatura', $subject->type ?? \App\Models\Subject::TYPE_THEORETICAL, 'Teorica o Teorico-practica. Determina el formato DGIRE.'],
            ['Caracter de la asignatura', $prefillPlan?->subject_character ?? $subject->subject_character ?? '', 'Obligatoria, obligatoria de eleccion, optativa, etc.'],
            ['Clave de la asignatura', $prefillPlan?->subject_key ?? $subject->subject_key ?? '', 'Clave DGIRE/UNAM si aplica'],
            ['Total de horas anuales', $prefillPlan?->total_annual_hours ?? $subject->annual_hours ?? '', 'Dato del programa indicativo'],
            ['Horas teoricas anuales', $subject->annual_theory_hours ?? '', 'Solo para asignatura teorico-practica'],
            ['Horas practicas anuales', $subject->annual_practice_hours ?? '', 'Solo para asignatura teorico-practica'],
            ['Horas por semana', $subject->hours_per_week ?? '', 'Dato del programa o carga horaria'],
            ['Horas teoricas por semana', $subject->weekly_theory_hours ?? '', 'Solo para asignatura teorico-practica'],
            ['Horas practicas por semana', $subject->weekly_practice_hours ?? '', 'Solo para asignatura teorico-practica'],
            ['Objetivo general de la asignatura', $prefillPlan?->objective ?? '', 'Dato del programa indicativo'],
            ['Bibliografia global', $prefillPlan?->bibliography ?? '', '1.9 Bibliografia del ciclo'],
            ['Recursos globales', $prefillPlan?->general_resources ?? '', '1.9 Recursos y materiales del ciclo. Escribe en parrafo, separando elementos con comas.'],
            ['Notas globales', $prefillPlan?->notes ?? '', 'Observaciones internas'],
        ], null, 'A1');
        $this->styleTemplateRange($subjectSheet, 'A1:C1', $headerFill, true);
        $subjectSheet->getStyle('A1:C15')->getBorders()->applyFromArray($border);
        $subjectSheet->getStyle('A:C')->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        foreach (['A' => 38, 'B' => 66, 'C' => 58] as $column => $width) {
            $subjectSheet->getColumnDimension($column)->setWidth($width);
        }
        $subjectSheet->freezePane('A2');

        $cycleSheet = $spreadsheet->createSheet();
        $cycleSheet->setTitle('Ciclo parciales');
        $isTheoreticalPractical = ($subject->type ?? \App\Models\Subject::TYPE_THEORETICAL) === \App\Models\Subject::TYPE_THEORETICAL_PRACTICAL;
        $cycleHeaders = $isTheoreticalPractical
            ? [
                'Periodo',
                'Unidad y/o fraccion de unidad',
                'Fechas de sesiones teoricas',
                'Practicas/laboratorio: numero, seccion, etapa y fecha(s)',
            ]
            : [
                'Periodo',
                'Unidad y/o fraccion de unidad',
                'Fechas que comprende el periodo',
            ];
        $cycleSheet->fromArray([$cycleHeaders], null, 'A1');
        $cycleLastColumn = $isTheoreticalPractical ? 'D' : 'C';
        $this->styleTemplateRange($cycleSheet, 'A1:' . $cycleLastColumn . '1', $headerFill, true);
        $fixedPeriods = [
            ['Periodo 1', $unitLabels->get(0, 'Unidad 1'), '', ''],
            ['Periodo 2', $unitLabels->get(0, 'Unidad 1'), '', ''],
            ['Periodo 3', $unitLabels->get(1, 'Unidad 2'), '', ''],
            ['Periodo 4', $unitLabels->get(2, 'Unidad 3'), '', ''],
            ['Observaciones:', '', '', ''],
        ];
        foreach ($fixedPeriods as $index => $periodRow) {
            $cycleSheet->fromArray([array_slice($periodRow, 0, $isTheoreticalPractical ? 4 : 3)], null, 'A' . ($index + 2));
        }
        foreach ($periods->values() as $index => $period) {
            if ($index > 3) {
                break;
            }
            $row = $index + 2;
            $cycleSheet->setCellValue('C' . $row, trim((optional($period->start_date)->format('Y-m-d') ?: '') . ' a ' . (optional($period->end_date)->format('Y-m-d') ?: '')));
        }
        $cycleSheet->getStyle('A1:' . $cycleLastColumn . '6')->getBorders()->applyFromArray($border);
        $cycleSheet->getStyle('A:' . $cycleLastColumn)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        foreach (['A' => 30, 'B' => 46, 'C' => 48, 'D' => 58] as $column => $width) {
            $cycleSheet->getColumnDimension($column)->setWidth($width);
        }
        $cycleSheet->freezePane('A2');

        $evaluationSheet = $spreadsheet->createSheet();
        $evaluationSheet->setTitle('Evaluacion');
        $evaluationRow = 1;
        for ($periodNumber = 1; $periodNumber <= 4; $periodNumber++) {
            $evaluationSheet->fromArray([['Periodo ' . $periodNumber, 'Tipo de evaluacion', 'Elementos por evaluar', 'Ponderacion (%)']], null, 'A' . $evaluationRow);
            $this->styleTemplateRange($evaluationSheet, 'A' . $evaluationRow . ':D' . $evaluationRow, $headerFill, true);
            $evaluationRow++;

            $periodRows = [
                ['1. Evaluacion continua', '', ''],
                ['', '', ''],
                ['', '', ''],
                ['', '', ''],
                ['', '', ''],
                ['2. Examen de periodo', '', ''],
            ];

            foreach ($periodRows as $periodRow) {
                $evaluationSheet->fromArray([[
                    '',
                    $periodRow[0],
                    $periodRow[1],
                    $periodRow[2],
                ]], null, 'A' . $evaluationRow);
                if ($periodRow[0] !== '') {
                    $this->applyListValidation($evaluationSheet, 'B' . $evaluationRow, '=TiposEvaluacionCatalogo', 'Tipo de evaluacion', 'Selecciona o escribe otro tipo de evaluacion.');
                }
                $evaluationRow++;
            }

            $evaluationRow++;
        }

        $evaluationSheet->setCellValue('B' . $evaluationRow, 'Criterios de exencion');
        $evaluationSheet->setCellValue('C' . $evaluationRow, '80% de asistencias; 90% de actividades y trabajos entregados; Promedio de 8.5 en los cuatro bimestres.');
        $evaluationRow++;
        $evaluationSheet->setCellValue('B' . $evaluationRow, 'Asignacion de calificaciones');
        $evaluationSheet->setCellValue('C' . $evaluationRow, 'Cuando el promedio de evaluacion de los cuatro periodos sea al menos de 8.5, esta sera la calificacion final del estudiante. En caso de no alcanzar esa calificacion, la calificacion final sera 50.0% la calificacion promedio de los cuatro periodos y 50.0% la calificacion de la primera o segunda vuelta. En caso de examen extraordinario, la calificacion de este examen corresponde al 100.0% de la calificacion final.');

        $lastEvaluationRow = $evaluationRow;
        $evaluationSheet->getStyle('A1:D' . $lastEvaluationRow)->getBorders()->applyFromArray($border);
        $evaluationSheet->getStyle('A:D')->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $evaluationSheet->getStyle('D2:D' . $lastEvaluationRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        foreach (['A' => 13, 'B' => 34, 'C' => 78, 'D' => 20] as $column => $width) {
            $evaluationSheet->getColumnDimension($column)->setWidth($width);
        }
        $evaluationSheet->freezePane('A2');

        $planning = $spreadsheet->createSheet();
        $planning->setTitle('Planeacion');
        $headers = [
            'tema_id',
            'unidad_id',
            'No. de sesion',
            'Clave de grupo(s)',
            'Fecha programada',
            'Contenidos (Solo los numerales)',
            'Estrategias de ensenanza-aprendizaje',
        ];
        $planning->fromArray([$headers], null, 'A1');
        $this->styleTemplateRange($planning, 'A1:G1', $headerFill, true);

        $planningRows = $prefillPlan
            ? $this->buildPlanningRowsFromPlan($prefillPlan, $topicOptions, $subtopicsByTopic)
            : $this->buildPlanningSessionRows($assignment, $defaultCycle, $topicOptions, $subtopicsByTopic);
        $row = 2;
        foreach ($planningRows as $planningRow) {
            $topic = $planningRow['topic'];
            $topicId = $topic ? (int) ($topic['id'] ?? 0) : 0;
            $planning->fromArray([[
                $topicId,
                $topic ? (int) ($topic['unit_id'] ?? 0) : 0,
                $row - 1,
                $assignment->group->name ?? '',
                $planningRow['date'],
                $planningRow['content_key'] ?? ($topic['key'] ?? ''),
                $planningRow['strategy'] ?? '',
            ]], null, 'A' . $row);

            $subtopicCount = $topicId > 0 ? collect($subtopicsByTopic->get($topicId, collect()))->count() : 0;
            if ($subtopicCount > 0) {
                $this->applyListValidation(
                    $planning,
                    'F' . $row,
                    '=Subtemas_Fila_' . $row,
                    'Contenidos tematicos',
                    'El machote pide solo el numeral. Puedes conservar el tema o seleccionar/escribir subtemas separados por punto y coma.'
                );
            }

            $this->applyDateValidation($planning, 'E' . $row);
            $row++;
        }

        $lastSessionRow = max(2, $row - 1);
        $planning->getStyle('A1:G' . $lastSessionRow)->getBorders()->applyFromArray($border);
        $planning->getStyle('A:G')->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $planning->getStyle('E2:E' . max(80, $lastSessionRow))->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        foreach (['A' => 14, 'B' => 14, 'C' => 14, 'D' => 20, 'E' => 18, 'F' => 26, 'G' => 72] as $column => $width) {
            $planning->getColumnDimension($column)->setWidth($width);
        }
        $planning->getColumnDimension('A')->setVisible(false);
        $planning->getColumnDimension('B')->setVisible(false);
        $planning->freezePane('C2');

        $catalog = $spreadsheet->createSheet();
        $catalog->setTitle('Catalogos');
        $catalog->fromArray([
            ['Ciclos', 'Parciales', 'Unidades', 'Temas', 'Subtemas', 'Recursos', 'Instrumentos', 'Tipos evaluacion', 'Ayuda'],
        ], null, 'A1');
        $this->styleTemplateRange($catalog, 'A1:I1', $headerFill, true);

        $catalogUnits = collect($unitOptions)
            ->map(fn ($unit) => $this->temarioCatalogLabel($unit))
            ->filter()
            ->unique(fn ($label) => mb_strtolower($label, 'UTF-8'))
            ->values();
        $catalogTopics = collect($topicOptions)
            ->map(fn ($topic) => $this->temarioCatalogLabel($topic))
            ->filter()
            ->unique(fn ($label) => mb_strtolower($label, 'UTF-8'))
            ->values();
        $catalogSubtopics = collect($subtopicOptions)
            ->map(fn ($subtopic) => $this->temarioCatalogLabel($subtopic))
            ->filter()
            ->unique(fn ($label) => mb_strtolower($label, 'UTF-8'))
            ->values();

        $maxCatalogRows = max($cycles->count(), $periods->count(), $catalogUnits->count(), $catalogTopics->count(), $catalogSubtopics->count(), 1);
        foreach ($cycles->values() as $index => $cycle) {
            $catalog->setCellValue('A' . ($index + 2), $cycle->name);
        }
        foreach ($periods->values() as $index => $period) {
            $catalog->setCellValue('B' . ($index + 2), $period->name);
        }
        foreach ($catalogUnits as $index => $unit) {
            $catalog->setCellValue('C' . ($index + 2), $unit);
        }
        foreach ($catalogTopics as $index => $topic) {
            $catalog->setCellValue('D' . ($index + 2), $topic);
        }
        foreach ($catalogSubtopics as $index => $subtopic) {
            $catalog->setCellValue('E' . ($index + 2), $subtopic);
        }

        $resources = $this->planningResourcesCatalog();
        $evaluations = $this->planningEvaluationCatalog();
        $evaluationTypes = $this->planningEvaluationTypesCatalog();
        foreach ($resources as $index => $resource) {
            $catalog->setCellValue('F' . ($index + 2), $resource);
        }
        foreach ($evaluations as $index => $evaluation) {
            $catalog->setCellValue('G' . ($index + 2), $evaluation);
        }
        foreach ($evaluationTypes as $index => $type) {
            $catalog->setCellValue('H' . ($index + 2), $type);
        }

        $catalog->fromArray([
            ['Institucion corresponde a 1.1, 1.2 y datos de grupo/horario.'],
            ['Materia corresponde a 1.3 y 1.9.'],
            ['Ciclo parciales corresponde a 1.6.'],
            ['Evaluacion corresponde a 1.7 y 1.8.'],
            ['Planeacion corresponde a 2.2; contenidos tematicos debe llevar solo numerales.'],
            ['Puedes escribir valores fuera del catalogo cuando sea necesario.'],
            ['No borres las columnas ocultas tema_id y unidad_id; se usaran para importar con precision.'],
        ], null, 'I2');

        if ($cycles->isNotEmpty()) {
            $spreadsheet->addNamedRange(new NamedRange('CiclosCatalogo', $catalog, '$A$2:$A$' . ($cycles->count() + 1)));
        }
        if ($periods->isNotEmpty()) {
            $spreadsheet->addNamedRange(new NamedRange('ParcialesCatalogo', $catalog, '$B$2:$B$' . ($periods->count() + 1)));
        }
        $spreadsheet->addNamedRange(new NamedRange('RecursosCatalogo', $catalog, '$F$2:$F$' . (count($resources) + 1)));
        $spreadsheet->addNamedRange(new NamedRange('InstrumentosCatalogo', $catalog, '$G$2:$G$' . (count($evaluations) + 1)));
        $spreadsheet->addNamedRange(new NamedRange('TiposEvaluacionCatalogo', $catalog, '$H$2:$H$' . (count($evaluationTypes) + 1)));

        $subtopicStartColumnIndex = 11; // K
        $planningRow = 2;
        foreach ($planningRows as $rowData) {
            $topic = $rowData['topic'] ?? null;
            if (! $topic) {
                $planningRow++;
                continue;
            }

            $subtopics = collect($subtopicsByTopic->get((int) ($topic['id'] ?? 0), collect()))
                ->map(fn ($subtopic) => $subtopic['key'] ?: $subtopic['text'])
                ->filter()
                ->unique(fn ($subtopic) => mb_strtolower((string) $subtopic, 'UTF-8'))
                ->values();

            if ($subtopics->isEmpty()) {
                $planningRow++;
                continue;
            }

            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($subtopicStartColumnIndex);
            $catalog->setCellValue($column . '1', 'Subtemas fila ' . $planningRow);
            foreach ($subtopics as $index => $subtopic) {
                $catalog->setCellValue($column . ($index + 2), $subtopic);
            }
            $range = '$' . $column . '$2:$' . $column . '$' . ($subtopics->count() + 1);
            $spreadsheet->addNamedRange(new NamedRange('Subtemas_Fila_' . $planningRow, $catalog, $range));
            $subtopicStartColumnIndex++;
            $planningRow++;
        }

        $catalogRows = max($maxCatalogRows, count($resources), count($evaluations), count($evaluationTypes), 6) + 1;
        $catalog->getStyle('A1:I' . $catalogRows)->getBorders()->applyFromArray($border);
        $catalog->getStyle('A:I')->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        foreach (['A' => 30, 'B' => 30, 'C' => 42, 'D' => 42, 'E' => 42, 'F' => 34, 'G' => 34, 'H' => 30, 'I' => 72] as $column => $width) {
            $catalog->getColumnDimension($column)->setWidth($width);
        }
        for ($col = 9; $col < $subtopicStartColumnIndex; $col++) {
            $catalog->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col))->setWidth(42);
        }
        $catalog->freezePane('A2');

        if ($cycles->isNotEmpty()) {
            $this->applyListValidation($institution, 'B4', '=CiclosCatalogo', 'Ciclo lectivo', 'Selecciona el ciclo lectivo.');
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function prefillPlanForTemplate(TeachingAssignment $assignment, ?SchoolCycle $cycle): ?DidacticPlan
    {
        if (! $cycle) {
            return null;
        }

        return DidacticPlan::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('school_cycle_id', $cycle->id)
            ->whereHas('items')
            ->with(['items.temarioPoint'])
            ->orderByRaw("CASE WHEN status = ? THEN 0 ELSE 1 END", [DidacticPlan::STATUS_TENTATIVE])
            ->orderByDesc('updated_at')
            ->first();
    }

    private function buildPlanningRowsFromPlan(DidacticPlan $plan, $topicOptions, $subtopicsByTopic)
    {
        $topicOptions = collect($topicOptions)->keyBy(fn ($topic) => (int) ($topic['id'] ?? 0));
        $subtopicOptionsById = collect($subtopicsByTopic)
            ->flatMap(fn ($subtopics) => collect($subtopics))
            ->keyBy(fn ($subtopic) => (int) ($subtopic['id'] ?? 0));

        return $plan->items
            ->sortBy('position')
            ->values()
            ->map(function ($item) use ($topicOptions, $subtopicOptionsById) {
                $topic = $topicOptions->get((int) $item->temario_point_id);
                $subtopicKeys = collect($item->temario_subtopic_ids ?? [])
                    ->map(fn ($id) => $subtopicOptionsById->get((int) $id))
                    ->filter()
                    ->map(fn ($subtopic) => (string) ($subtopic['key'] ?: $subtopic['text']))
                    ->filter(fn ($value) => trim($value) !== '')
                    ->unique()
                    ->values();

                $strategy = collect([
                    $item->opening,
                    $item->development,
                    $item->closing,
                ])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->implode("\n");

                return [
                    'date' => optional($item->start_date)->format('Y-m-d') ?? '',
                    'duration' => '',
                    'session_type' => '',
                    'practice_detail' => '',
                    'is_practice' => false,
                    'topic' => $topic,
                    'content_key' => $subtopicKeys->isNotEmpty()
                        ? $subtopicKeys->implode('; ')
                        : (string) ($topic['key'] ?? ''),
                    'strategy' => $strategy,
                ];
            });
    }

    private function buildPlanningSessionRows(TeachingAssignment $assignment, ?SchoolCycle $cycle, $topicOptions, $subtopicsByTopic)
    {
        $sessions = $this->calendarizedSessionsForAssignment($assignment, $cycle);
        $topics = collect($topicOptions)->values();
        $topicsById = $topics->keyBy(fn ($topic) => (int) ($topic['id'] ?? 0));
        $contentOptions = collect($subtopicsByTopic)
            ->flatMap(fn ($subtopics) => collect($subtopics))
            ->map(function ($subtopic) use ($topicsById) {
                $topic = $topicsById->get((int) ($subtopic['topic_id'] ?? 0));

                return $topic ? [
                    'topic' => $topic,
                    'content_key' => $subtopic['key'] ?: $subtopic['text'],
                ] : null;
            })
            ->filter()
            ->values();

        if ($contentOptions->isEmpty()) {
            $contentOptions = $topics
                ->map(fn ($topic) => [
                    'topic' => $topic,
                    'content_key' => $topic['key'] ?? '',
                ])
                ->values();
        }

        $isTheoreticalPractical = ($assignment->subject->type ?? \App\Models\Subject::TYPE_THEORETICAL) === \App\Models\Subject::TYPE_THEORETICAL_PRACTICAL;

        if ($isTheoreticalPractical) {
            $sessions = $sessions
                ->reject(fn (array $session) => $session['is_practice'] ?? false)
                ->values();
        }

        if ($sessions->isEmpty()) {
            return $contentOptions->map(fn ($contentOption) => [
                'date' => '',
                'duration' => '',
                'session_type' => '',
                'practice_detail' => '',
                'topic' => $contentOption['topic'],
                'content_key' => $contentOption['content_key'],
            ])->values();
        }

        return $sessions->values()->map(function (array $session, int $index) use ($sessions, $contentOptions) {
            $topic = null;
            $contentKey = '';
            if ($contentOptions->isNotEmpty()) {
                $contentIndex = (int) floor($index * $contentOptions->count() / max(1, $sessions->count()));
                $contentOption = $contentOptions->get(min($contentIndex, $contentOptions->count() - 1));
                $topic = $contentOption['topic'] ?? null;
                $contentKey = (string) ($contentOption['content_key'] ?? '');
            }

            return [
                'date' => $session['date'],
                'duration' => $session['duration'],
                'session_type' => $session['session_type'],
                'practice_detail' => $session['practice_detail'],
                'is_practice' => $session['is_practice'] ?? false,
                'topic' => $topic,
                'content_key' => $contentKey,
            ];
        });
    }

    private function recalendarizedSessionsForAssignment(TeachingAssignment $assignment, SchoolCycle $cycle)
    {
        $sessions = $this->calendarizedSessionsForAssignment($assignment, $cycle);
        $isTheoreticalPractical = ($assignment->subject->type ?? \App\Models\Subject::TYPE_THEORETICAL) === \App\Models\Subject::TYPE_THEORETICAL_PRACTICAL;

        if ($isTheoreticalPractical) {
            $sessions = $sessions
                ->reject(fn (array $session) => $session['is_practice'] ?? false)
                ->values();
        }

        return $sessions;
    }

    private function buildDgireMetadataForClonedPlan($items, SchoolCycle $cycle): array
    {
        $items = collect($items)->values();
        $unitIds = $items
            ->pluck('field_training_point_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $unitsById = TemarioPoint::query()
            ->whereIn('id', $unitIds)
            ->get()
            ->keyBy('id');

        $encuadreRows = $items
            ->filter(fn ($item) => str_contains(mb_strtolower((string) ($item['development'] ?? ''), 'UTF-8'), 'presentacion del programa'));

        $unitRows = $items
            ->reject(fn ($item) => str_contains(mb_strtolower((string) ($item['development'] ?? ''), 'UTF-8'), 'presentacion del programa'))
            ->groupBy(fn ($item) => (int) ($item['field_training_point_id'] ?? 0));

        $units = $unitIds->map(function (int $unitId) use ($unitRows, $unitsById) {
            $unit = $unitsById->get($unitId);
            $rows = collect($unitRows->get($unitId, collect()));
            $dates = $rows->pluck('start_date')->filter()->sort()->values();

            return [
                'unit_id' => $unitId,
                'number' => (string) ($unit->label ?? ''),
                'text' => trim((string) ($unit->label ?? '') . ' ' . (string) ($unit->content ?? '')),
                'hours' => $rows->count(),
                'start_date' => $dates->first(),
                'end_date' => $dates->last(),
            ];
        })->values()->all();

        $encuadreDates = $encuadreRows->pluck('start_date')->filter()->sort()->values();
        $metadata = [
            'encuadre' => [
                'hours' => $encuadreRows->count(),
                'start_date' => $encuadreDates->first(),
                'end_date' => $encuadreDates->last(),
            ],
            'units' => $units,
            'periods' => [],
        ];

        $partials = CyclePartial::query()
            ->where('school_cycle_id', $cycle->id)
            ->orderBy('sort_order')
            ->orderBy('start_date')
            ->take(4)
            ->get()
            ->values();

        foreach ($partials as $index => $partial) {
            $periodItems = $items
                ->filter(function ($item) use ($partial) {
                    $date = $item['start_date'] ?? null;
                    return $date
                        && $date >= Carbon::parse($partial->start_date)->toDateString()
                        && $date <= Carbon::parse($partial->end_date)->toDateString();
                })
                ->values();

            $unitText = $periodItems
                ->pluck('field_training_point_id')
                ->filter()
                ->unique()
                ->map(fn ($unitId) => $unitsById->get((int) $unitId))
                ->filter()
                ->map(fn ($unit) => 'Unidad ' . (string) ($unit->label ?? ''))
                ->unique()
                ->implode(', ');

            $dates = $periodItems->pluck('start_date')->filter()->sort()->values();

            $metadata['periods'][] = [
                'period' => $partial->name ?: ('Periodo ' . ($index + 1)),
                'unit_text' => $unitText,
                'theory_dates' => $this->formatDateRangeForMetadata($dates->first(), $dates->last()),
                'practices' => '',
            ];
        }

        return $metadata;
    }

    private function calendarizedSessionsForAssignment(TeachingAssignment $assignment, ?SchoolCycle $cycle)
    {
        if (! $cycle?->start_date || ! $cycle?->end_date) {
            return collect();
        }

        $cycleId = (int) $cycle->id;
        $schedules = $this->schedulesForPlanning($assignment, $cycleId);

        if ($schedules->isEmpty()) {
            $schedules = $this->schedulesForPlanning($assignment, null);
        }

        $schedulesByIsoDay = $schedules
            ->map(function ($schedule) {
                $isoDay = $this->resolveDayOfWeekIso($schedule->day_of_week);

                return $isoDay ? [
                    'iso_day' => $isoDay,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'type' => $schedule->type,
                    'section_type' => $schedule->section_type ?: optional($schedule->assignment)->section_type,
                    'section_label' => $schedule->section_label ?: optional($schedule->assignment)->section_label,
                    'section_number' => $schedule->section_number ?: optional($schedule->assignment)->section_number,
                ] : null;
            })
            ->filter()
            ->groupBy('iso_day')
            ->map(fn ($daySchedules) => $daySchedules->sortBy('start_time')->values());

        if ($schedulesByIsoDay->isEmpty()) {
            return collect();
        }

        $start = Carbon::parse($cycle->start_date)->startOfDay();
        $end = Carbon::parse($cycle->end_date)->startOfDay();
        $modalityId = (int) ($cycle->modality_id ?: optional($assignment->group?->level)->modality_id);
        $nonWorkingDates = AcademicCalendarDay::query()
            ->whereIn('type', ['holiday', 'vacation'])
            ->where('affects_students', true)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where(function ($query) use ($modalityId) {
                $query->whereNull('modality_id');
                if ($modalityId > 0) {
                    $query->orWhere('modality_id', $modalityId);
                }
            })
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();

        $sessions = collect();
        $current = $start->copy();
        while ($current->lte($end)) {
            if (! $nonWorkingDates->has($current->toDateString())) {
                $daySchedules = $schedulesByIsoDay->get($current->dayOfWeekIso, collect());
                foreach ($daySchedules as $schedule) {
                    $isPractice = $this->isPracticeSchedule($schedule);
                    $sessions->push([
                        'date' => $current->toDateString(),
                        'duration' => $this->formatScheduleDuration($schedule['start_time'], $schedule['end_time']),
                        'session_type' => $isPractice ? 'Practica / laboratorio' : 'Teorica',
                        'practice_detail' => $isPractice ? $this->formatPracticeScheduleDetail($schedule) : '',
                        'is_practice' => $isPractice,
                    ]);
                }
            }

            $current->addDay();
        }

        return $sessions
            ->unique(fn (array $session) => implode('|', [
                $session['date'] ?? '',
                $session['duration'] ?? '',
                $session['session_type'] ?? '',
                $session['practice_detail'] ?? '',
            ]))
            ->values();
    }

    private function schedulesForPlanning(TeachingAssignment $assignment, ?int $cycleId)
    {
        return Schedule::query()
            ->with('assignment')
            ->where('is_active', true)
            ->when($cycleId, fn ($query) => $query->where('school_cycle_id', $cycleId))
            ->whereHas('assignment', function ($query) use ($assignment) {
                $query->where('subject_id', $assignment->subject_id)
                    ->where('group_id', $assignment->group_id)
                    ->where('is_active', true);

                if ($assignment->school_cycle_group_id) {
                    $query->where('school_cycle_group_id', $assignment->school_cycle_group_id);
                }
            })
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    private function isPracticeSchedule(array $schedule): bool
    {
        $values = [
            $schedule['type'] ?? '',
            $schedule['section_type'] ?? '',
            $schedule['section_label'] ?? '',
        ];

        $key = $this->normalizeWeekday(implode(' ', array_map('strval', $values)));

        return str_contains($key, 'lab')
            || str_contains($key, 'laboratorio')
            || str_contains($key, 'taller')
            || str_contains($key, 'practica')
            || str_contains($key, 'practical')
            || str_contains($key, 'lab_taller')
            || str_contains($key, 'dividid');
    }

    private function formatPracticeScheduleDetail(array $schedule): string
    {
        $section = trim((string) ($schedule['section_label'] ?? ''));
        if ($section === '' && ! empty($schedule['section_number'])) {
            $section = 'Seccion ' . $schedule['section_number'];
        }

        return trim('LAB' . ($section !== '' ? ' - ' . $section : ''));
    }

    private function resolveDayOfWeekIso(mixed $rawDay): ?int
    {
        if (is_numeric($rawDay)) {
            $day = (int) $rawDay;
            return ($day >= 1 && $day <= 7) ? $day : null;
        }

        $day = $this->normalizeWeekday((string) $rawDay);
        $map = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
            'lunes' => 1,
            'martes' => 2,
            'miercoles' => 3,
            'jueves' => 4,
            'viernes' => 5,
            'sabado' => 6,
            'domingo' => 7,
        ];

        return $map[$day] ?? null;
    }

    private function normalizeWeekday(string $day): string
    {
        $day = mb_strtolower(trim($day), 'UTF-8');
        $day = strtr($day, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            '?' => 'e',
        ]);

        return preg_replace('/\s+/', '', $day) ?: '';
    }

    private function formatScheduleDuration(mixed $startTime, mixed $endTime): string
    {
        $start = $this->formatTimeForTemplate($startTime);
        $end = $this->formatTimeForTemplate($endTime);

        return trim($start . ($start !== '' && $end !== '' ? '-' : '') . $end);
    }

    private function formatTimeForTemplate(mixed $time): string
    {
        $value = trim((string) $time);
        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function parsePlanningWorkbook(string $path, TeachingAssignment $assignment): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $institution = $spreadsheet->getSheetByName('Institucion');
        $subject = $spreadsheet->getSheetByName('Materia');
        $cycleSheet = $spreadsheet->getSheetByName('Ciclo parciales');
        $planning = $spreadsheet->getSheetByName('Planeacion');

        if (! $institution || ! $subject || ! $planning) {
            throw ValidationException::withMessages([
                'planning_file' => 'El archivo debe contener las hojas Institucion, Materia y Planeacion.',
            ]);
        }

        $cycles = $this->cyclesForAssignment($assignment);
        $cycleName = $this->sheetCellText($institution, 'B4');
        $cycle = $cycles->first(fn ($candidate) => mb_strtolower((string) $candidate->name, 'UTF-8') === mb_strtolower($cycleName, 'UTF-8'))
            ?: $this->defaultCycleForAssignment($assignment, $cycles);

        if (! $cycle) {
            throw ValidationException::withMessages([
                'planning_file' => 'No pude identificar el ciclo escolar de la planeacion para esta asignacion.',
            ]);
        }

        [$unitSelectorOptions, $topicSelectorOptions, $subtopicSelectorOptions] = $this->buildTemarioSelectors($assignment);
        $topicOptions = collect($topicSelectorOptions)->keyBy('id');
        $unitOptions = collect($unitSelectorOptions)->keyBy('id');
        $subtopicOptions = collect($subtopicSelectorOptions)->keyBy('id');
        $topicByKey = $topicOptions
            ->filter(fn ($topic) => !empty($topic['key']))
            ->keyBy(fn ($topic) => (string) $topic['key']);
        $unitByKey = $unitOptions
            ->filter(fn ($unit) => !empty($unit['key']))
            ->keyBy(fn ($unit) => (string) $unit['key']);
        $subtopicByKey = $subtopicOptions
            ->filter(fn ($subtopic) => !empty($subtopic['key']))
            ->keyBy(fn ($subtopic) => (string) $subtopic['key']);

        $items = [];
        $planningMetadataRows = [];
        $usesHiddenLeadingIds = $this->sheetCellText($planning, 'A1') === 'tema_id';
        $usesDgireHiddenLeadingIds = $usesHiddenLeadingIds && $this->sheetCellText($planning, 'C1') === 'No. de sesion';
        $usesCompactTrailingIds = $this->sheetCellText($planning, 'G1') === 'tema_id';
        $usesExpandedSessionTypeColumns = $this->sheetCellText($planning, 'D1') === 'Tipo de sesion';

        if ($usesDgireHiddenLeadingIds) {
            $dateColumn = 'E';
            $objectiveColumn = null;
            $contentColumn = 'F';
            $activityColumn = 'G';
            $productColumn = null;
            $instrumentColumn = null;
            $observationsColumn = null;
            $topicIdColumn = 'A';
            $unitIdColumn = 'B';
        } elseif ($usesHiddenLeadingIds) {
            $dateColumn = 'D';
            $objectiveColumn = null;
            $contentColumn = 'G';
            $activityColumn = 'H';
            $productColumn = null;
            $instrumentColumn = null;
            $observationsColumn = null;
            $topicIdColumn = 'A';
            $unitIdColumn = 'B';
        } elseif ($usesCompactTrailingIds) {
            $dateColumn = 'B';
            $objectiveColumn = null;
            $contentColumn = 'E';
            $activityColumn = 'F';
            $productColumn = null;
            $instrumentColumn = null;
            $observationsColumn = null;
            $topicIdColumn = 'G';
            $unitIdColumn = 'H';
        } elseif ($usesExpandedSessionTypeColumns) {
            $dateColumn = 'B';
            $objectiveColumn = 'F';
            $contentColumn = 'G';
            $activityColumn = 'H';
            $productColumn = 'I';
            $instrumentColumn = 'J';
            $observationsColumn = 'K';
            $topicIdColumn = 'L';
            $unitIdColumn = 'M';
        } else {
            $dateColumn = 'B';
            $objectiveColumn = 'D';
            $contentColumn = 'E';
            $activityColumn = 'F';
            $productColumn = 'G';
            $instrumentColumn = 'H';
            $observationsColumn = 'I';
            $topicIdColumn = 'J';
            $unitIdColumn = 'K';
        }

        for ($row = 2; $row <= $planning->getHighestRow(); $row++) {
            $date = $this->parseSpreadsheetDate($planning->getCell($dateColumn . $row)->getValue(), $planning->getCell($dateColumn . $row)->getFormattedValue());
            $content = $this->sheetCellText($planning, $contentColumn . $row);
            $activity = $this->sheetCellText($planning, $activityColumn . $row);
            $objective = $objectiveColumn ? $this->sheetCellText($planning, $objectiveColumn . $row) : '';
            $product = $productColumn ? $this->sheetCellText($planning, $productColumn . $row) : '';
            $instrument = $instrumentColumn ? $this->sheetCellText($planning, $instrumentColumn . $row) : '';
            $observations = $observationsColumn ? $this->sheetCellText($planning, $observationsColumn . $row) : '';

            if (! $date && $content === '' && $activity === '' && $objective === '') {
                continue;
            }

            if (! $date) {
                throw ValidationException::withMessages([
                    'planning_file' => "La fila {$row} de Planeacion no tiene una fecha valida.",
                ]);
            }

            $topicId = (int) $planning->getCell($topicIdColumn . $row)->getValue();
            $topic = $topicId > 0 ? $topicOptions->get($topicId) : null;
            $contentKeys = collect(preg_split('/[;,]+/u', $content) ?: [])
                ->map(fn ($value) => $this->labelKey(trim((string) $value)) ?: trim((string) $value))
                ->filter(fn ($value) => $value !== '')
                ->values();
            $subtopicIds = $contentKeys
                ->map(fn ($key) => $subtopicByKey->get((string) $key))
                ->filter()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if (! $topic && preg_match('/^([0-9]+(?:\.[0-9]+)?)/', $content, $matches) === 1) {
                $topic = $topicByKey->get($matches[1]);
            }

            if (! $topic && $contentKeys->isNotEmpty()) {
                $topicKey = collect(explode('.', (string) $contentKeys->first()))
                    ->take(2)
                    ->implode('.');
                $topic = $topicKey !== '' ? $topicByKey->get($topicKey) : null;
            }

            if (! $topic) {
                $topic = $topicOptions->first();
            }

            if (! $topic) {
                throw ValidationException::withMessages([
                    'planning_file' => 'No hay temas de temario disponibles para importar la planeacion.',
                ]);
            }

            $unitId = (int) $planning->getCell($unitIdColumn . $row)->getValue();
            $unit = $unitId > 0 ? $unitOptions->get($unitId) : null;

            if (! $unit && !empty($topic['unit_id'])) {
                $unit = $unitOptions->get((int) $topic['unit_id']);
            }

            if (! $unit && !empty($topic['key'])) {
                $unitKey = explode('.', (string) $topic['key'])[0] ?? null;
                $unit = $unitKey ? $unitByKey->get($unitKey) : null;
            }

            if (! $unit) {
                $unit = $unitOptions->first();
            }

            if (! $unit) {
                throw ValidationException::withMessages([
                    'planning_file' => 'No hay unidades de temario disponibles para importar la planeacion.',
                ]);
            }

            $items[] = [
                'field_training_point_id' => (int) $unit['id'],
                'objective' => $objective !== '' ? $objective : null,
                'temario_point_id' => (int) $topic['id'],
                'temario_subtopic_ids' => $subtopicIds,
                'opening' => null,
                'development' => $activity !== '' ? $activity : null,
                'closing' => $observations !== '' ? $observations : null,
                'resources' => $product !== '' ? $product : null,
                'evaluation' => $instrument !== '' ? $instrument : null,
                'start_date' => $date,
                'end_date' => $date,
            ];

            $planningMetadataRows[] = [
                'unit_id' => (int) $unit['id'],
                'unit_key' => (string) ($unit['key'] ?? ''),
                'unit_text' => (string) ($unit['text'] ?? ''),
                'content' => $content,
                'date' => $date,
            ];
        }

        if (count($items) === 0) {
            throw ValidationException::withMessages([
                'planning_file' => 'No encontre renglones importables en la hoja Planeacion.',
            ]);
        }

        $subjectTypeCandidate = $this->sheetCellText($subject, 'B3');
        $usesNewSubjectSheet = in_array($subjectTypeCandidate, array_keys(\App\Models\Subject::dgireTypeOptions()), true);
        $subjectCharacterCell = $usesNewSubjectSheet ? 'B4' : 'B3';
        $subjectKeyCell = $usesNewSubjectSheet ? 'B5' : 'B4';
        $annualHoursCell = $usesNewSubjectSheet ? 'B6' : 'B5';
        $objectiveCell = $usesNewSubjectSheet ? 'B12' : 'B7';
        $bibliographyCell = $usesNewSubjectSheet ? 'B13' : 'B8';
        $resourcesCell = $usesNewSubjectSheet ? 'B14' : 'B9';
        $notesCell = $usesNewSubjectSheet ? 'B15' : 'B10';

        $firstDate = collect($items)->pluck('start_date')->filter()->sort()->first();
        $period = $firstDate
            ? AcademicPeriod::query()
                ->where('modality_id', optional($assignment->group?->level)->modality_id)
                ->whereDate('start_date', '<=', $firstDate)
                ->whereDate('end_date', '>=', $firstDate)
                ->orderBy('start_date')
                ->first()
            : null;

        return [
            'plan' => [
                'title' => 'Planeacion ' . ($assignment->subject->name ?? 'Materia') . ' - Grupo ' . ($assignment->group->name ?? ''),
                'school_cycle_id' => (int) $cycle->id,
                'academic_period_id' => $period?->id,
                'temario_unit_point_id' => null,
                'unam_incorporation_key' => $this->sheetCellText($institution, 'B3') ?: null,
                'teacher_dgire_file' => $this->sheetCellText($institution, 'B6') ?: null,
                'technical_review_date' => $this->parseSpreadsheetDate($institution->getCell('B8')->getValue(), $institution->getCell('B8')->getFormattedValue()),
                'subject_character' => $this->sheetCellText($subject, $subjectCharacterCell) ?: null,
                'subject_key' => $this->sheetCellText($subject, $subjectKeyCell) ?: null,
                'total_annual_hours' => $this->integerOrNull($this->sheetCellText($subject, $annualHoursCell)),
                'field_training' => null,
                'objective' => $this->sheetCellText($subject, $objectiveCell) ?: null,
                'evaluation_instruments' => $this->collectEvaluationSummary($spreadsheet),
                'general_resources' => $this->sheetCellText($subject, $resourcesCell) ?: null,
                'bibliography' => $this->sheetCellText($subject, $bibliographyCell) ?: null,
                'complementary_bibliography' => null,
                'start_date' => null,
                'end_date' => null,
                'notes' => $this->sheetCellText($subject, $notesCell) ?: null,
                'dgire_metadata' => $this->buildDgireMetadataFromWorkbook($cycleSheet, $planningMetadataRows, $unitOptions),
            ],
            'items' => collect($items)->values()->map(function ($item, $index) {
                $item['position'] = $index + 1;
                return $item;
            })->all(),
        ];
    }

    private function integerOrNull(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $number = preg_replace('/[^0-9]/', '', $value);

        return $number !== '' ? (int) $number : null;
    }

    private function buildDgireMetadataFromWorkbook($cycleSheet, array $planningRows, $unitOptions): array
    {
        $unitOptionsById = collect($unitOptions)->keyBy('id');
        $unitRows = collect($planningRows)
            ->filter(fn ($row) => !empty($row['date']));

        $encuadreRows = $unitRows
            ->filter(fn ($row) => str_contains(mb_strtolower((string) ($row['content'] ?? ''), 'UTF-8'), 'encuadre'));

        $rowsByUnit = $unitRows
            ->reject(fn ($row) => str_contains(mb_strtolower((string) ($row['content'] ?? ''), 'UTF-8'), 'encuadre'))
            ->groupBy(fn ($row) => (int) ($row['unit_id'] ?? 0));

        $units = $unitOptionsById
            ->map(function ($unit) use ($rowsByUnit) {
                $rows = collect($rowsByUnit->get((int) ($unit['id'] ?? 0), collect()));
                $dates = $rows->pluck('date')->filter()->sort()->values();

                return [
                    'unit_id' => (int) ($unit['id'] ?? 0),
                    'number' => (string) ($unit['key'] ?? ''),
                    'text' => (string) ($unit['text'] ?? ''),
                    'hours' => $rows->count(),
                    'start_date' => $dates->first(),
                    'end_date' => $dates->last(),
                ];
            })
            ->values()
            ->all();

        $encuadreDates = $encuadreRows->pluck('date')->filter()->sort()->values();
        $metadata = [
            'encuadre' => [
                'hours' => $encuadreRows->count(),
                'start_date' => $encuadreDates->first(),
                'end_date' => $encuadreDates->last(),
            ],
            'units' => $units,
            'periods' => [],
        ];

        if (! $cycleSheet) {
            return $metadata;
        }

        for ($row = 2; $row <= $cycleSheet->getHighestRow(); $row++) {
            $period = $this->sheetCellText($cycleSheet, 'A' . $row);
            $unitText = $this->sheetCellText($cycleSheet, 'B' . $row);
            $practiceText = $this->sheetCellText($cycleSheet, 'D' . $row);

            if ($period === '' && $unitText === '' && $practiceText === '') {
                continue;
            }

            if (str_starts_with(mb_strtolower($period, 'UTF-8'), 'observaciones')) {
                $metadata['period_observations'] = trim($unitText . ' ' . $this->sheetCellText($cycleSheet, 'C' . $row) . ' ' . $practiceText);
                continue;
            }

            $unitNumbers = $this->extractUnitNumbers($unitText);
            $matchingUnits = collect($units)
                ->filter(fn ($unit) => in_array((string) ($unit['number'] ?? ''), $unitNumbers, true))
                ->values();

            $dateValues = $matchingUnits
                ->flatMap(fn ($unit) => [$unit['start_date'] ?? null, $unit['end_date'] ?? null])
                ->filter()
                ->sort()
                ->values();

            $metadata['periods'][] = [
                'period' => $period,
                'unit_text' => $unitText,
                'theory_dates' => $this->formatDateRangeForMetadata($dateValues->first(), $dateValues->last()),
                'practices' => $practiceText,
            ];
        }

        return $metadata;
    }

    private function extractUnitNumbers(string $value): array
    {
        preg_match_all('/Unidad\s+([0-9]+)/ui', $value, $matches);

        if (! empty($matches[1])) {
            return collect($matches[1])->map(fn ($number) => (string) ((int) $number))->unique()->values()->all();
        }

        preg_match_all('/\b([0-9]+)\b/u', $value, $matches);

        return collect($matches[1] ?? [])->map(fn ($number) => (string) ((int) $number))->unique()->values()->all();
    }

    private function formatDateRangeForMetadata(?string $startDate, ?string $endDate): string
    {
        if (! $startDate && ! $endDate) {
            return '';
        }

        $format = fn ($date) => $date ? Carbon::parse($date)->format('d/m/Y') : '';

        if ($startDate === $endDate) {
            return $format($startDate);
        }

        return trim($format($startDate) . ' a ' . $format($endDate), ' a');
    }

    private function sheetCellText($sheet, string $cell): string
    {
        return trim((string) $sheet->getCell($cell)->getFormattedValue());
    }

    private function parseSpreadsheetDate(mixed $value, string $formatted): ?string
    {
        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            } catch (\Throwable) {
                //
            }
        }

        $candidate = trim((string) ($formatted !== '' ? $formatted : $value));
        if ($candidate === '') {
            return null;
        }

        try {
            return Carbon::parse($candidate)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function collectEvaluationSummary(Spreadsheet $spreadsheet): ?string
    {
        $periodSheet = $spreadsheet->getSheetByName('Evaluacion periodos');
        if ($periodSheet) {
            $rows = [];
            for ($row = 2; $row <= $periodSheet->getHighestRow(); $row++) {
                $type = $this->sheetCellText($periodSheet, 'A' . $row);
                $element = $this->sheetCellText($periodSheet, 'B' . $row);
                $weight = $this->sheetCellText($periodSheet, 'C' . $row);
                $notes = $this->sheetCellText($periodSheet, 'D' . $row);

                if ($type === '' && $element === '') {
                    continue;
                }

                $rows[] = trim($type . ': ' . $element . ($weight !== '' ? ' (' . $this->formatPercentText($weight) . ')' : '') . ($notes !== '' ? ' - ' . $notes : ''));
            }

            if ($rows) {
                return implode("\n", $rows);
            }
        }

        $sheet = $spreadsheet->getSheetByName('Evaluacion');
        if (! $sheet) {
            return null;
        }

        $rows = [];
        $usesPeriodFormat = ($this->sheetCellText($sheet, 'A1') === 'Periodo' || str_starts_with($this->sheetCellText($sheet, 'A1'), 'Periodo '))
            && $this->sheetCellText($sheet, 'B1') === 'Tipo de evaluacion';
        $currentPeriod = $usesPeriodFormat && str_starts_with($this->sheetCellText($sheet, 'A1'), 'Periodo ')
            ? $this->sheetCellText($sheet, 'A1')
            : '';
        for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
            if ($usesPeriodFormat && str_starts_with($this->sheetCellText($sheet, 'A' . $row), 'Periodo ')) {
                $currentPeriod = $this->sheetCellText($sheet, 'A' . $row);
                continue;
            }

            $period = $usesPeriodFormat ? $this->sheetCellText($sheet, 'A' . $row) : '';
            $type = $this->sheetCellText($sheet, ($usesPeriodFormat ? 'B' : 'A') . $row);
            $element = $this->sheetCellText($sheet, ($usesPeriodFormat ? 'C' : 'B') . $row);
            $weight = $this->sheetCellText($sheet, ($usesPeriodFormat ? 'D' : 'C') . $row);

            if ($usesPeriodFormat && $type === 'Tipo de evaluacion') {
                $currentPeriod = $period;
                continue;
            }

            if ($type === '' && $element === '') {
                continue;
            }

            if ($usesPeriodFormat && $element === '' && $weight === '') {
                continue;
            }

            $prefix = ($period !== '' ? $period : $currentPeriod) !== '' ? ($period !== '' ? $period : $currentPeriod) . ' - ' : '';
            $rows[] = trim($prefix . $type . ': ' . $element . ($weight !== '' ? ' (' . $weight . ')' : ''));
        }

        return $rows ? implode("\n", $rows) : null;
    }

    private function formatPercentText(string $value): string
    {
        $value = trim($value);

        return str_contains($value, '%') ? $value : $value . '%';
    }

    private function styleTemplateRange($sheet, string $range, array $fill, bool $whiteText = false): void
    {
        $sheet->getStyle($range)->getFill()->applyFromArray($fill);
        $sheet->getStyle($range)->getFont()->setBold(true);
        if ($whiteText) {
            $sheet->getStyle($range)->getFont()->getColor()->setRGB('FFFFFF');
        }
    }

    private function applyListValidation($sheet, string $cell, string $formula, string $title, string $prompt): void
    {
        $validation = $sheet->getCell($cell)->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_WARNING);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(false);
        $validation->setPromptTitle($title);
        $validation->setPrompt($prompt);
        $validation->setFormula1($formula);
    }

    private function applyDateValidation($sheet, string $cell): void
    {
        $validation = $sheet->getCell($cell)->getDataValidation();
        $validation->setType(DataValidation::TYPE_DATE);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setPromptTitle('Fecha');
        $validation->setPrompt('Usa formato AAAA-MM-DD.');
        $validation->setErrorTitle('Fecha invalida');
        $validation->setError('Usa una fecha valida en formato AAAA-MM-DD.');
    }

    private function planningResourcesCatalog(): array
    {
        return [
            'Libro de texto',
            'Cuaderno',
            'Presentacion',
            'Proyector',
            'Pizarron',
            'Laboratorio',
            'Material de laboratorio',
            'Plataforma digital',
            'Video',
            'Lectura',
            'Guia de ejercicios',
        ];
    }

    private function planningEvaluationCatalog(): array
    {
        return [
            'Lista de cotejo',
            'Rubrica',
            'Examen escrito',
            'Practica de laboratorio',
            'Reporte',
            'Proyecto',
            'Participacion',
            'Tarea',
            'Ejercicios en clase',
            'Portafolio de evidencias',
        ];
    }

    private function planningEvaluationTypesCatalog(): array
    {
        return [
            'Evaluacion continua',
            'Evaluacion final del periodo',
            'Examen de periodo',
            'Prueba objetiva',
            'Producto especifico',
            'Proyecto',
            'Portafolio',
        ];
    }

    private function slugForFilename(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?: 'archivo', '-'));

        return $slug !== '' ? $slug : 'archivo';
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

        $allowedCycleIds = $this->cycleIdsForAssignment($assignment);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'unam_incorporation_key' => 'nullable|string|max:255',
            'teacher_dgire_file' => 'nullable|string|max:255',
            'technical_review_date' => 'nullable|date',
            'subject_character' => 'nullable|string|max:255',
            'subject_key' => 'nullable|string|max:255',
            'total_annual_hours' => 'nullable|integer|min:0|max:2000',
            'school_cycle_id' => [
                'required',
                'integer',
                'exists:school_cycles,id',
                Rule::in($allowedCycleIds->all()),
            ],
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
                'unam_incorporation_key' => $data['unam_incorporation_key'] ?? null,
                'teacher_dgire_file' => $data['teacher_dgire_file'] ?? null,
                'technical_review_date' => $data['technical_review_date'] ?? null,
                'subject_character' => $data['subject_character'] ?? null,
                'subject_key' => $data['subject_key'] ?? null,
                'total_annual_hours' => $data['total_annual_hours'] ?? null,
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

        $topicPoints = $points
            ->filter(fn ($point) => (int) $point['level'] === 2)
            ->values();

        if ($topicPoints->isEmpty()) {
            $topicPoints = $points
                ->filter(fn ($point) => $this->labelDepth((string) ($point['label'] ?? '')) === 2)
                ->values();
        }

        $topicOptions = $topicPoints
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
            ->filter(fn ($point) => (int) $point['level'] === 3)
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
                    'key' => $subtopic['key'],
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

    private function temarioCatalogLabel(array $point): string
    {
        $key = trim((string) ($point['key'] ?? ''));
        $text = trim((string) ($point['text'] ?? ''));

        if ($key === '') {
            return $text;
        }

        $textWithoutRepeatedKey = trim((string) preg_replace(
            '/^\s*' . preg_quote($key, '/') . '\.?\s*/u',
            '',
            $text
        ));

        return trim($key . ' ' . ($textWithoutRepeatedKey !== '' ? $textWithoutRepeatedKey : ''));
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

    private function labelDepth(string $label): int
    {
        $key = $this->labelKey($label);

        if ($key === null || $key === '') {
            return 0;
        }

        return substr_count($key, '.') + 1;
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
        $user = auth()->user();
        $teacherId = $user?->teacher?->id;
        $activeCampusId = (int) session('active_campus_id', 0);
        $belongsToCampus = $activeCampusId <= 0
            || (int) ($assignment->schoolCycleGroup?->campus_id ?? 0) === $activeCampusId
            || $assignment->schedules()
                ->whereHas('schoolCycle', fn ($query) => $this->applyCampusFilterToCycleQuery($query, $activeCampusId))
                ->exists();

        if ($user?->hasAnyRole(['coordinator', 'admin'])) {
            abort_unless($belongsToCampus, 403);
            return;
        }

        abort_if(!$teacherId || $assignment->teacher_id !== $teacherId || !$belongsToCampus, 403);
    }

    private function cyclesForAssignment(TeachingAssignment $assignment, array $extraCycleIds = [])
    {
        $cycleIds = $this->cycleIdsForAssignment($assignment)
            ->merge($extraCycleIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($cycleIds->isEmpty()) {
            return collect();
        }

        $assignmentCycleId = (int) ($assignment->schoolCycleGroup?->school_cycle_id ?? 0);

        return SchoolCycle::query()
            ->whereIn('id', $cycleIds->all())
            ->get()
            ->sort(function (SchoolCycle $a, SchoolCycle $b) use ($assignmentCycleId) {
                $aIsAssignmentCycle = (int) $a->id === $assignmentCycleId;
                $bIsAssignmentCycle = (int) $b->id === $assignmentCycleId;

                if ($aIsAssignmentCycle !== $bIsAssignmentCycle) {
                    return $aIsAssignmentCycle ? -1 : 1;
                }

                $startComparison = (optional($b->start_date)->timestamp ?? 0) <=> (optional($a->start_date)->timestamp ?? 0);
                if ($startComparison !== 0) {
                    return $startComparison;
                }

                return ((int) $b->id) <=> ((int) $a->id);
            })
            ->values();
    }

    private function cycleIdsForAssignment(TeachingAssignment $assignment)
    {
        $ids = collect();

        $cycleGroupCycleId = (int) ($assignment->schoolCycleGroup?->school_cycle_id ?? 0);
        if ($cycleGroupCycleId > 0) {
            $ids->push($cycleGroupCycleId);
        }

        $scheduleCycleIds = $assignment->schedules()
            ->where('is_active', true)
            ->whereNotNull('school_cycle_id')
            ->pluck('school_cycle_id')
            ->map(fn ($id) => (int) $id);

        return $ids
            ->merge($scheduleCycleIds)
            ->filter()
            ->unique()
            ->values();
    }

    private function defaultCycleForAssignment(TeachingAssignment $assignment, $cycles): ?SchoolCycle
    {
        $current = app(CurrentSchoolCycle::class)->get(auth()->user(), (int) session('active_campus_id', 0));
        if ($current && $cycles->contains(fn (SchoolCycle $cycle) => (int) $cycle->id === (int) $current->id)) {
            return $current;
        }

        $assignmentCycleId = (int) ($assignment->schoolCycleGroup?->school_cycle_id ?? 0);

        if ($assignmentCycleId > 0) {
            $cycle = $cycles->firstWhere('id', $assignmentCycleId);
            if ($cycle) {
                return $cycle;
            }
        }

        return $cycles->firstWhere('is_active', true) ?: $cycles->first();
    }

    private function applyCampusFilterToCycleQuery($query, int $activeCampusId): void
    {
        $query->where(function ($nested) use ($activeCampusId) {
            $nested->where('campus_id', $activeCampusId)
                ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
        });
    }
}
