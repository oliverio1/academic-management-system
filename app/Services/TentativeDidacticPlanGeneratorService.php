<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\DidacticPlan;
use App\Models\SchoolCycle;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Temario;
use App\Models\TemarioPoint;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TentativeDidacticPlanGeneratorService
{
    public function generateForCycle(SchoolCycle $cycle, array $options = []): array
    {
        $query = TeachingAssignment::query()
            ->with(['subject.temarios.points', 'group.level', 'schoolCycleGroup.schoolCycle'])
            ->where('is_active', true)
            ->where(function ($assignmentQuery) {
                $assignmentQuery->whereNull('section_type')->orWhere('section_type', '');
            })
            ->whereHas('subject.temarios.points')
            ->whereHas('academicSessions', function ($sessionQuery) use ($cycle) {
                $sessionQuery->whereHas('schedule', function ($scheduleQuery) use ($cycle) {
                    $scheduleQuery->where('school_cycle_id', $cycle->id)
                        ->where('is_active', true);
                });
            })
            ->when(! empty($options['teacher_id']), fn ($q) => $q->where('teacher_id', (int) $options['teacher_id']))
            ->when(! empty($options['assignment_id']), fn ($q) => $q->whereKey((int) $options['assignment_id']))
            ->orderBy('subject_id')
            ->orderBy('group_id')
            ->orderBy('id');

        $summary = [
            'reviewed' => 0,
            'created' => 0,
            'replaced' => 0,
            'skipped' => [],
            'created_plans' => [],
        ];

        $query->chunkById(100, function ($assignments) use ($cycle, $options, &$summary) {
            foreach ($assignments as $assignment) {
                $summary['reviewed']++;
                $result = $this->generateForAssignment($assignment, $cycle, $options);

                if (($result['status'] ?? null) === 'created') {
                    $summary['created']++;
                    $summary['created_plans'][] = $result;
                } elseif (($result['status'] ?? null) === 'replaced') {
                    $summary['created']++;
                    $summary['replaced']++;
                    $summary['created_plans'][] = $result;
                } else {
                    $summary['skipped'][] = $result['message'] ?? 'Asignacion omitida.';
                }
            }
        });

        return $summary;
    }

    public function generateForAssignment(TeachingAssignment $assignment, SchoolCycle $cycle, array $options = []): array
    {
        $assignment->loadMissing(['subject.temarios.points', 'group.level', 'schoolCycleGroup.schoolCycle']);

        $existingQuery = $assignment->didacticPlans()
            ->where('school_cycle_id', $cycle->id);

        $existingPlans = (clone $existingQuery)->get();
        $replaceTentative = (bool) ($options['replace_tentative'] ?? false);

        if ($existingPlans->isNotEmpty() && ! $replaceTentative) {
            return $this->skip($assignment, 'ya tiene planeacion en el ciclo.');
        }

        $hasFinalPlan = $existingPlans
            ->contains(fn ($plan) => ($plan->status ?? DidacticPlan::STATUS_FINAL) === DidacticPlan::STATUS_FINAL);

        if ($hasFinalPlan) {
            return $this->skip($assignment, 'ya tiene planeacion final en el ciclo.');
        }

        $temario = $this->temarioForAssignment($assignment);
        if (! $temario) {
            return $this->skip($assignment, 'no tiene temario.');
        }

        $sessions = $this->theorySessionsForAssignment($assignment, $cycle);
        if ($sessions->isEmpty()) {
            return $this->skip($assignment, 'no tiene sesiones teoricas de grupo completo.');
        }

        $catalog = $this->temarioCatalog($temario);
        if ($catalog['units']->isEmpty() || $catalog['topics']->isEmpty()) {
            return $this->skip($assignment, 'el temario no tiene unidades y temas suficientes.');
        }

        return DB::transaction(function () use ($assignment, $cycle, $existingPlans, $sessions, $catalog, $temario, $replaceTentative) {
            $replaced = false;

            if ($replaceTentative && $existingPlans->isNotEmpty()) {
                $assignment->didacticPlans()
                    ->where('school_cycle_id', $cycle->id)
                    ->where('status', DidacticPlan::STATUS_TENTATIVE)
                    ->delete();
                $replaced = true;
            }

            $items = $this->buildItems($sessions, $catalog);
            $firstSession = $sessions->first();
            $lastSession = $sessions->last();

            $plan = $assignment->didacticPlans()->create([
                'school_cycle_id' => $cycle->id,
                'academic_period_id' => $firstSession?->academic_period_id,
                'temario_unit_point_id' => null,
                'title' => 'Planeacion tentativa '.$assignment->subject->name.' - Grupo '.$assignment->group->name,
                'status' => DidacticPlan::STATUS_TENTATIVE,
                'generated_by_system' => true,
                'generated_at' => now(),
                'subject_character' => $assignment->subject->subject_character,
                'subject_key' => $assignment->subject->subject_key,
                'total_annual_hours' => $assignment->subject->annual_theory_hours ?: $assignment->subject->annual_hours,
                'field_training' => null,
                'objective' => $temario->description,
                'evaluation_instruments' => null,
                'general_resources' => 'Pizarron, cuaderno, libro de texto, presentacion, plataforma digital y material proporcionado por el docente.',
                'bibliography' => null,
                'complementary_bibliography' => null,
                'start_date' => $firstSession?->session_date,
                'end_date' => $lastSession?->session_date,
                'notes' => 'Planeacion tentativa generada automaticamente a partir del temario y las sesiones teoricas del horario. El docente debe revisarla, editarla y confirmarla.',
                'dgire_metadata' => $this->buildMetadata($sessions, $items, $catalog),
                'is_active' => true,
            ]);

            foreach ($items as $item) {
                $plan->items()->create($item);
            }

            return [
                'status' => $replaced ? 'replaced' : 'created',
                'plan_id' => $plan->id,
                'assignment_id' => $assignment->id,
                'group' => $assignment->group->name,
                'subject' => $assignment->subject->name,
                'items' => count($items),
            ];
        });
    }

    private function temarioForAssignment(TeachingAssignment $assignment): ?Temario
    {
        return $assignment->subject
            ->temarios
            ->sortByDesc('updated_at')
            ->first();
    }

    private function theorySessionsForAssignment(TeachingAssignment $assignment, SchoolCycle $cycle): Collection
    {
        return AcademicSession::query()
            ->with(['schedule', 'academicPeriod'])
            ->where('teaching_assignment_id', $assignment->id)
            ->where('is_cancelled', false)
            ->whereHas('schedule', function ($query) use ($cycle) {
                $query->where('school_cycle_id', $cycle->id)
                    ->where('is_active', true)
                    ->where(function ($sectionQuery) {
                        $sectionQuery->whereNull('section_type')->orWhere('section_type', '');
                    });
            })
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();
    }

    private function temarioCatalog(Temario $temario): array
    {
        $points = $temario->points->sortBy('position')->values();
        $units = $points->where('level', 1)->values();
        $unitByNumber = $units->keyBy(fn ($unit) => $this->firstNumber((string) $unit->label));

        $topicPoints = $points->where('level', 2)->values();
        if ($topicPoints->isEmpty()) {
            $topicPoints = $points
                ->filter(fn ($point) => $this->labelDepth((string) $point->label) === 2)
                ->values();
        }

        $topics = $topicPoints
            ->map(function (TemarioPoint $topic) use ($unitByNumber) {
                $topicKey = $this->labelKey((string) $topic->label);
                $unit = $unitByNumber->get(explode('.', $topicKey)[0] ?? '');

                return [
                    'point' => $topic,
                    'key' => $topicKey,
                    'unit' => $unit,
                ];
            })
            ->filter(fn ($topic) => $topic['unit'])
            ->values();

        $subtopics = $points
            ->filter(fn ($point) => (int) $point->level >= 3)
            ->groupBy(function (TemarioPoint $point) {
                $key = $this->labelKey((string) $point->label);
                $parts = explode('.', $key);

                return count($parts) >= 2 ? $parts[0].'.'.$parts[1] : $key;
            });

        return compact('units', 'topics', 'subtopics');
    }

    private function buildItems(Collection $sessions, array $catalog): array
    {
        $topics = $catalog['topics']->values();
        $topicCount = max(1, $topics->count());
        $sessionCount = max(1, $sessions->count());

        return $sessions->values()->map(function (AcademicSession $session, int $index) use ($topics, $topicCount, $sessionCount, $catalog) {
            $topicIndex = min($topicCount - 1, (int) floor($index * $topicCount / $sessionCount));
            $topic = $topics->get($topicIndex) ?: $topics->last();
            $topicPoint = $topic['point'];
            $unit = $topic['unit'];
            $subtopicIds = collect($catalog['subtopics']->get($topic['key'], collect()))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            return [
                'position' => $index + 1,
                'field_training_point_id' => $unit?->id,
                'objective' => $this->unitObjective($unit),
                'temario_point_id' => $topicPoint->id,
                'temario_subtopic_ids' => $subtopicIds,
                'opening' => 'Recuperacion de conocimientos previos y planteamiento del proposito de la sesion.',
                'development' => 'Desarrollo guiado del contenido '.$this->pointText($topicPoint).' con ejemplos, preguntas dirigidas y ejercicios de aplicacion.',
                'closing' => 'Sintesis de ideas clave, aclaracion de dudas y registro de evidencias de aprendizaje.',
                'resources' => 'Cuaderno, pizarron, presentacion y material de apoyo.',
                'evaluation' => 'Participacion, ejercicios en clase y evidencia de aprendizaje.',
                'start_date' => $session->session_date,
                'end_date' => $session->session_date,
            ];
        })->all();
    }

    private function buildMetadata(Collection $sessions, array $items, array $catalog): array
    {
        $itemsByUnit = collect($items)->groupBy('field_training_point_id');

        $units = $catalog['units']->map(function (TemarioPoint $unit) use ($itemsByUnit) {
            $rows = collect($itemsByUnit->get($unit->id, []));
            $dates = $rows->pluck('start_date')->filter()->sort()->values();

            return [
                'unit_id' => $unit->id,
                'number' => $this->firstNumber((string) $unit->label),
                'text' => $this->pointText($unit),
                'hours' => $rows->count(),
                'start_date' => $dates->first(),
                'end_date' => $dates->last(),
            ];
        })->values()->all();

        return [
            'generated_by' => 'TentativeDidacticPlanGeneratorService',
            'generated_at' => now()->toDateTimeString(),
            'generation_rule' => 'Distribucion secuencial de temas del temario sobre sesiones teoricas de grupo completo.',
            'session_count' => $sessions->count(),
            'units' => $units,
            'periods' => [],
        ];
    }

    private function unitObjective(?TemarioPoint $unit): ?string
    {
        if (! $unit || ! is_string($unit->content)) {
            return null;
        }

        if (preg_match('/Objetivo\s*:\s*(.+)$/isu', $unit->content, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    private function pointText(TemarioPoint $point): string
    {
        return trim((string) $point->label.' '.(string) $point->content);
    }

    private function labelKey(string $label): string
    {
        if (preg_match('/([0-9]+(?:\.[0-9]+)*)/u', $label, $matches) === 1) {
            return $matches[1];
        }

        return trim($label);
    }

    private function labelDepth(string $label): int
    {
        $key = $this->labelKey($label);

        if ($key === '' || ! preg_match('/^[0-9]+(?:\.[0-9]+)*$/', $key)) {
            return 0;
        }

        return substr_count($key, '.') + 1;
    }

    private function firstNumber(string $label): string
    {
        $key = $this->labelKey($label);

        return explode('.', $key)[0] ?? $key;
    }

    private function skip(TeachingAssignment $assignment, string $reason): array
    {
        $assignment->loadMissing(['subject', 'group']);

        return [
            'status' => 'skipped',
            'assignment_id' => $assignment->id,
            'message' => 'Grupo '.($assignment->group->name ?? '-').' - '.($assignment->subject->name ?? '-').': '.$reason,
        ];
    }
}
