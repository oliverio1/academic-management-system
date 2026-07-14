<?php

namespace App\Http\Controllers;

use App\Models\CyclePartial;
use App\Models\SchoolCycle;
use App\Models\TeachingAssignment;
use App\Models\TemarioPoint;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Http\Request;

class TeacherPlanningDocumentController extends Controller
{
    public function programaOperativoPdf(TeachingAssignment $teachingAssignment, Request $request)
    {
        $this->authorizeAssignmentOwner($teachingAssignment);

        [$cycle, $partial, $plans, $temarioPoints] = $this->loadPlanningContext($teachingAssignment, $request);

        $units = $temarioPoints
            ->where('level', 1)
            ->values()
            ->map(function ($unit) use ($plans, $temarioPoints) {
                $unitText = $this->pointText($unit->label, $unit->content);
                $unitKey = $this->labelKey((string) $unit->label);
                $topics = $this->topicsForUnit($unitKey, $temarioPoints);

                $plan = $plans->firstWhere('temario_unit_point_id', $unit->id);
                if (!$plan) {
                    $plan = $plans->first(function ($candidate) use ($unitText) {
                        $title = mb_strtolower((string) ($candidate->title ?? ''));
                        return $title !== '' && mb_strpos($title, mb_strtolower($unitText)) !== false;
                    });
                }

                $startDate = optional($plan?->items->sortBy('start_date')->first()?->start_date)->format('d/m/Y');
                $endDate = optional($plan?->items->sortByDesc('end_date')->first()?->end_date)->format('d/m/Y');

                return [
                    'unit' => $unitText,
                    'topics' => $topics->implode('; '),
                    'objective' => $plan?->objective ?: '-',
                    'start_date' => $startDate ?: '-',
                    'end_date' => $endDate ?: '-',
                    'resources' => $plan?->general_resources ?: '-',
                    'evaluation' => $plan?->evaluation_instruments ?: '-',
                    'bibliography' => $plan?->bibliography ?: '-',
                ];
            });

        return SnappyPdf::loadView('teacher.documents.programa_operativo_pdf', [
            'assignment' => $teachingAssignment,
            'cycle' => $cycle,
            'partial' => $partial,
            'units' => $units,
        ])
            ->setPaper('letter')
            ->setOption('encoding', 'UTF-8')
            ->setOption('disable-javascript', true)
            ->setOption('enable-local-file-access', true)
            ->setOption('footer-right', 'Pagina [page] de [toPage]')
            ->inline('PROGRAMA_OPERATIVO_' . $teachingAssignment->group->name . '_' . $this->safeName($teachingAssignment->subject->name) . '.pdf');
    }

    public function planeacionFormatoPdf(TeachingAssignment $teachingAssignment, Request $request)
    {
        $this->authorizeAssignmentOwner($teachingAssignment);

        [$cycle, $partial, $plans, $temarioPoints] = $this->loadPlanningContext($teachingAssignment, $request);
        $pointsById = $temarioPoints->keyBy('id');

        $rowsByUnit = $plans
            ->sortBy(fn ($plan) => (string) optional($plan->temarioUnitPoint)->label)
            ->values()
            ->map(function ($plan) use ($pointsById) {
                $unitName = $plan->temarioUnitPoint
                    ? $this->pointText($plan->temarioUnitPoint->label, $plan->temarioUnitPoint->content)
                    : ($plan->title ?: 'Unidad');

                $rows = $plan->items
                    ->sortBy('position')
                    ->values()
                    ->map(function ($item) use ($pointsById) {
                        $topic = $item->temarioPoint
                            ? $this->pointText($item->temarioPoint->label, $item->temarioPoint->content)
                            : '-';

                        $subtopics = collect($item->temario_subtopic_ids ?? [])
                            ->map(fn ($id) => $pointsById->get((int) $id))
                            ->filter()
                            ->map(fn ($point) => $this->pointText($point->label, $point->content))
                            ->implode('; ');

                        return [
                            'topic' => $topic,
                            'subtopics' => $subtopics !== '' ? $subtopics : '-',
                            'opening' => $item->opening ?: '-',
                            'development' => $item->development ?: '-',
                            'closing' => $item->closing ?: '-',
                            'resources' => $item->resources ?: ($plan->general_resources ?: '-'),
                            'evaluation' => $item->evaluation ?: ($plan->evaluation_instruments ?: '-'),
                            'start_date' => optional($item->start_date)->format('d/m/Y') ?: '-',
                            'end_date' => optional($item->end_date)->format('d/m/Y') ?: '-',
                        ];
                    });

                return [
                    'unit' => $unitName,
                    'objective' => $plan->objective ?: '-',
                    'rows' => $rows,
                    'bibliography' => $plan->bibliography ?: '-',
                    'complementary_bibliography' => $plan->complementary_bibliography ?: '-',
                ];
            });

        return SnappyPdf::loadView('teacher.documents.planeacion_formato_pdf', [
            'assignment' => $teachingAssignment,
            'cycle' => $cycle,
            'partial' => $partial,
            'rowsByUnit' => $rowsByUnit,
        ])
            ->setPaper('letter', 'landscape')
            ->setOption('encoding', 'UTF-8')
            ->setOption('disable-javascript', true)
            ->setOption('enable-local-file-access', true)
            ->setOption('footer-right', 'Pagina [page] de [toPage]')
            ->inline('PLANEACION_' . $teachingAssignment->group->name . '_' . $this->safeName($teachingAssignment->subject->name) . '.pdf');
    }

    private function loadPlanningContext(TeachingAssignment $assignment, Request $request): array
    {
        $assignment->loadMissing([
            'teacher.user',
            'group.level.modality',
            'schoolCycleGroup.schoolCycle',
            'subject',
            'didacticPlans.items.temarioPoint',
            'didacticPlans.temarioUnitPoint',
            'didacticPlans.schoolCycle',
            'didacticPlans.academicPeriod',
            'temarios.points',
        ]);

        $cycle = null;
        $partial = null;

        $cycleId = (int) $request->query('school_cycle_id', 0);
        if ($cycleId > 0) {
            $cycle = $this->cycleBelongsToAssignment($assignment, $cycleId)
                ? SchoolCycle::find($cycleId)
                : null;
        }

        if (!$cycle) {
            $cycle = $assignment->schoolCycleGroup?->schoolCycle;
        }

        if (!$cycle) {
            $scheduleCycleId = $assignment->schedules()
                ->where('is_active', true)
                ->whereNotNull('school_cycle_id')
                ->orderByDesc('school_cycle_id')
                ->value('school_cycle_id');

            $cycle = $scheduleCycleId ? SchoolCycle::find((int) $scheduleCycleId) : null;
        }

        $partialId = (int) $request->query('cycle_partial_id', 0);
        if ($partialId > 0) {
            $partial = CyclePartial::query()
                ->where('id', $partialId)
                ->when($cycle, fn ($q) => $q->where('school_cycle_id', $cycle->id))
                ->first();
        }

        if (!$partial && $cycle) {
            $partial = $cycle->partials()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->first();
        }

        $plans = $assignment->didacticPlans;
        if ($cycle) {
            $plans = $plans->where('school_cycle_id', $cycle->id)->values();
        }
        if ($partial && $partial->academic_period_id) {
            $partialPlans = $plans->where('academic_period_id', $partial->academic_period_id)->values();
            if ($partialPlans->isNotEmpty()) {
                $plans = $partialPlans;
            }
        }

        $temarioPoints = $assignment->temarios
            ->flatMap(fn ($temario) => $temario->points)
            ->sortBy('position')
            ->values();

        return [$cycle, $partial, $plans, $temarioPoints];
    }

    private function topicsForUnit(?string $unitKey, $points)
    {
        if (!$unitKey) {
            return collect();
        }

        return $points
            ->filter(function ($point) use ($unitKey) {
                $key = $this->labelKey((string) ($point->label ?? ''));
                return (int) ($point->level ?? 0) === 2 && $key && str_starts_with($key, $unitKey . '.');
            })
            ->map(fn ($point) => $this->pointText($point->label, $point->content))
            ->values();
    }

    private function pointText(?string $label, ?string $content): string
    {
        $label = trim((string) $label);
        $content = trim((string) $content);
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

    private function authorizeAssignmentOwner(TeachingAssignment $assignment): void
    {
        $teacherId = auth()->user()?->teacher?->id;
        $activeCampusId = (int) session('active_campus_id', 0);
        $belongsToCampus = $activeCampusId <= 0 || $assignment->schedules()
            ->whereHas('schoolCycle', fn ($query) => $this->applyCampusFilterToCycleQuery($query, $activeCampusId))
            ->where('is_active', true)
            ->exists();

        abort_if(!$teacherId || $assignment->teacher_id !== $teacherId || !$belongsToCampus, 403);
    }

    private function cycleBelongsToAssignment(TeachingAssignment $assignment, int $cycleId): bool
    {
        if ((int) ($assignment->schoolCycleGroup?->school_cycle_id ?? 0) === $cycleId) {
            return true;
        }

        return $assignment->schedules()
            ->where('school_cycle_id', $cycleId)
            ->where('is_active', true)
            ->exists();
    }

    private function applyCampusFilterToCycleQuery($query, int $activeCampusId): void
    {
        $query->where(function ($nested) use ($activeCampusId) {
            $nested->where('campus_id', $activeCampusId)
                ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
        });
    }

    private function safeName(string $text): string
    {
        $text = preg_replace('/\s+/', '_', trim($text));
        return preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $text);
    }
}
