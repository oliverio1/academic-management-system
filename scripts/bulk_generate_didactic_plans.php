<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DidacticPlan;
use App\Models\SchoolCycle;
use App\Models\TeachingAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function parseUnitContent(string $content): array
{
    $raw = trim($content);
    if ($raw === '') {
        return ['name' => '', 'objective' => null];
    }

    if (preg_match('/^(.*?)\s*\|\s*Objetivo\s+espec[ií]fico:\s*(.+)$/ui', $raw, $matches) === 1) {
        return [
            'name' => trim((string) ($matches[1] ?? '')),
            'objective' => trim((string) ($matches[2] ?? '')),
        ];
    }

    return ['name' => $raw, 'objective' => null];
}

function splitUnitAndTopics($points): array
{
    $units = [];
    $topics = [];
    $currentUnitId = null;

    foreach ($points as $point) {
        $level = (int) ($point->level ?? 1);
        if ($level === 1) {
            $parsed = parseUnitContent((string) $point->content);
            $units[$point->id] = [
                'id' => (int) $point->id,
                'label' => trim((string) $point->label),
                'name' => $parsed['name'] !== '' ? $parsed['name'] : trim((string) $point->content),
                'objective' => $parsed['objective'],
            ];
            $currentUnitId = (int) $point->id;
            continue;
        }

        if ($level === 2 && $currentUnitId) {
            $topics[] = [
                'id' => (int) $point->id,
                'unit_id' => $currentUnitId,
                'label' => trim((string) $point->label),
                'content' => trim((string) $point->content),
            ];
        }
    }

    return [$units, $topics];
}

function itemDatesForIndex(Carbon $cycleStart, Carbon $cycleEnd, int $index): array
{
    $start = $cycleStart->copy()->addWeeks($index);
    if ($start->gt($cycleEnd)) {
        $start = $cycleEnd->copy();
    }

    $end = $start->copy()->addDays(6);
    if ($end->gt($cycleEnd)) {
        $end = $cycleEnd->copy();
    }

    return [$start->toDateString(), $end->toDateString()];
}

$activeCycles = SchoolCycle::query()
    ->where('is_active', true)
    ->get();

$createdPlans = 0;
$clonedPlans = 0;
$skippedWithPlan = 0;
$skippedNoTemario = 0;
$processedAssignments = 0;
$details = [];

foreach ($activeCycles as $cycle) {
    $assignments = TeachingAssignment::query()
        ->whereHas('schedules', function ($query) use ($cycle) {
            $query->where('is_active', true)
                ->where('school_cycle_id', (int) $cycle->id);
        })
        ->with(['subject', 'group.level', 'temarios.points'])
        ->get()
        ->unique(fn ($a) => ((int) $a->teacher_id) . '-' . ((int) $a->group_id) . '-' . ((int) $a->subject_id))
        ->values();

    foreach ($assignments as $assignment) {
        $processedAssignments++;

        $already = DidacticPlan::query()
            ->where('teaching_assignment_id', (int) $assignment->id)
            ->where('school_cycle_id', (int) $cycle->id)
            ->exists();

        if ($already) {
            $skippedWithPlan++;
            continue;
        }

        $sourcePlan = DidacticPlan::query()
            ->where('school_cycle_id', (int) $cycle->id)
            ->whereHas('assignment', function ($query) use ($assignment) {
                $query->where('subject_id', (int) $assignment->subject_id);
            })
            ->with('items')
            ->orderByDesc(DB::raw('(select count(*) from didactic_plan_items where didactic_plan_items.didactic_plan_id = didactic_plans.id)'))
            ->orderByDesc('updated_at')
            ->first();

        if ($sourcePlan && $sourcePlan->items->isNotEmpty()) {
            DB::transaction(function () use ($assignment, $sourcePlan, &$clonedPlans, &$details) {
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
                    'is_active' => true,
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

                $clonedPlans++;
                $details[] = [
                    'assignment_id' => (int) $assignment->id,
                    'cycle_id' => (int) $sourcePlan->school_cycle_id,
                    'subject' => $assignment->subject->name ?? null,
                    'group' => $assignment->group->name ?? null,
                    'mode' => 'cloned',
                    'plan_id' => (int) $newPlan->id,
                ];
            });
            continue;
        }

        $temario = $assignment->temarios()
            ->with(['points' => fn ($q) => $q->orderBy('position')])
            ->latest('id')
            ->first();

        if (! $temario || $temario->points->isEmpty()) {
            $skippedNoTemario++;
            continue;
        }

        [$units, $topics] = splitUnitAndTopics($temario->points);
        if (empty($topics)) {
            $skippedNoTemario++;
            continue;
        }

        $cycleStart = Carbon::parse($cycle->start_date)->startOfDay();
        $cycleEnd = Carbon::parse($cycle->end_date)->endOfDay();
        $periodId = optional($cycle->partials()->orderBy('sort_order')->first())->academic_period_id;

        DB::transaction(function () use (
            $assignment,
            $cycle,
            $periodId,
            $units,
            $topics,
            $cycleStart,
            $cycleEnd,
            &$createdPlans,
            &$details
        ) {
            $plan = $assignment->didacticPlans()->create([
                'school_cycle_id' => (int) $cycle->id,
                'academic_period_id' => $periodId,
                'temario_unit_point_id' => null,
                'title' => 'Planeación ' . ($assignment->subject->name ?? 'Materia') . ' - ' . ($assignment->group->name ?? 'Grupo'),
                'field_training' => null,
                'objective' => null,
                'evaluation_instruments' => 'Lista de cotejo, rúbrica y evaluación continua.',
                'general_resources' => 'Pizarrón, cuaderno, libros, TIC y recursos digitales.',
                'bibliography' => null,
                'complementary_bibliography' => null,
                'start_date' => null,
                'end_date' => null,
                'notes' => 'Planeación generada automáticamente a partir del temario.',
                'is_active' => true,
            ]);

            foreach (array_values($topics) as $idx => $topic) {
                $unit = $units[$topic['unit_id']] ?? null;
                [$itemStart, $itemEnd] = itemDatesForIndex($cycleStart, $cycleEnd, $idx);

                $plan->items()->create([
                    'position' => $idx + 1,
                    'field_training_point_id' => $unit['id'] ?? null,
                    'objective' => $unit['objective'] ?? null,
                    'temario_point_id' => (int) $topic['id'],
                    'temario_subtopic_ids' => [],
                    'opening' => 'Activación de conocimientos previos y contextualización del tema.',
                    'development' => 'Desarrollo del contenido mediante explicación guiada y actividades prácticas.',
                    'closing' => 'Síntesis, retroalimentación y conclusiones del aprendizaje.',
                    'resources' => 'Pizarrón, apuntes, recursos digitales y material de apoyo.',
                    'evaluation' => 'Participación, evidencias de trabajo y revisión de actividades.',
                    'start_date' => $itemStart,
                    'end_date' => $itemEnd,
                ]);
            }

            $createdPlans++;
            $details[] = [
                'assignment_id' => (int) $assignment->id,
                'cycle_id' => (int) $cycle->id,
                'subject' => $assignment->subject->name ?? null,
                'group' => $assignment->group->name ?? null,
                'mode' => 'generated',
                'plan_id' => (int) $plan->id,
            ];
        });
    }
}

echo json_encode([
    'active_cycles' => $activeCycles->pluck('id')->values(),
    'processed_assignments' => $processedAssignments,
    'cloned_plans' => $clonedPlans,
    'generated_plans' => $createdPlans,
    'skipped_with_existing_plan' => $skippedWithPlan,
    'skipped_without_temario_or_topics' => $skippedNoTemario,
    'created_total' => $clonedPlans + $createdPlans,
    'details' => $details,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;

