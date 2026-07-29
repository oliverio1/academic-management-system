<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Activity;
use App\Models\CyclePartial;
use App\Models\DidacticPlan;
use App\Models\DidacticPlanItem;
use App\Models\EvaluationCriterion;
use App\Models\SessionActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DidacticPlanActivityGeneratorService
{
    public function generate(DidacticPlan $plan, array $options = []): array
    {
        $plan->loadMissing(['assignment', 'items']);
        $assignment = $plan->assignment;
        $replace = (bool) ($options['replace'] ?? false);

        if (($plan->status ?? DidacticPlan::STATUS_FINAL) !== DidacticPlan::STATUS_FINAL) {
            throw new \InvalidArgumentException('Solo se pueden generar actividades desde una planeacion final.');
        }

        $sessions = $this->theorySessionsForPlan($plan);
        $items = $plan->items->sortBy('position')->values();

        if ($sessions->isEmpty() || $items->isEmpty()) {
            return [
                'session_activities_created' => 0,
                'session_activities_updated' => 0,
                'activities_created' => 0,
                'activities_updated' => 0,
                'activities_skipped_without_criterion' => 0,
            ];
        }

        return DB::transaction(function () use ($assignment, $plan, $sessions, $items, $replace) {
            $summary = [
                'session_activities_created' => 0,
                'session_activities_updated' => 0,
                'activities_created' => 0,
                'activities_updated' => 0,
                'activities_skipped_without_criterion' => 0,
            ];

            foreach ($sessions as $index => $session) {
                $item = $this->itemForSession($items, $session, $index);
                if (! $item) {
                    continue;
                }

                $payload = $this->sessionActivityPayload($item, $session, $index + 1);
                $existingSessionActivity = SessionActivity::query()
                    ->where('academic_session_id', $session->id)
                    ->first();

                if ($existingSessionActivity && ! $replace) {
                    continue;
                }

                $criterion = $this->criterionForSession($assignment, $plan, $session);
                $payload['evaluation_criterion_id'] = $criterion?->id;

                $sessionActivity = SessionActivity::updateOrCreate(
                    ['academic_session_id' => $session->id],
                    $payload
                );

                $existingSessionActivity
                    ? $summary['session_activities_updated']++
                    : $summary['session_activities_created']++;

                if (! $criterion) {
                    $summary['activities_skipped_without_criterion']++;
                    continue;
                }

                $existingActivity = Activity::query()
                    ->where('session_activity_id', $sessionActivity->id)
                    ->first();

                Activity::updateOrCreate(
                    ['session_activity_id' => $sessionActivity->id],
                    [
                        'teaching_assignment_id' => $session->teaching_assignment_id,
                        'evaluation_criterion_id' => $criterion->id,
                        'academic_period_id' => $session->academic_period_id,
                        'title' => $payload['title'],
                        'max_score' => 10,
                        'due_date' => $session->session_date,
                        'description' => $payload['description'] ?? null,
                        'evaluation_mode' => 'individual',
                        'is_active' => true,
                    ]
                );

                $existingActivity
                    ? $summary['activities_updated']++
                    : $summary['activities_created']++;
            }

            return $summary;
        });
    }

    private function theorySessionsForPlan(DidacticPlan $plan): Collection
    {
        return AcademicSession::query()
            ->with(['schedule'])
            ->where('teaching_assignment_id', $plan->teaching_assignment_id)
            ->where('is_cancelled', false)
            ->when($plan->school_cycle_id, function ($query) use ($plan) {
                $query->whereHas('schedule', fn ($schedule) => $schedule->where('school_cycle_id', $plan->school_cycle_id));
            })
            ->whereHas('schedule', function ($query) {
                $query->where(function ($sectionQuery) {
                    $sectionQuery->whereNull('section_type')->orWhere('section_type', '');
                });
            })
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();
    }

    private function itemForSession(Collection $items, AcademicSession $session, int $index): ?DidacticPlanItem
    {
        $date = $session->session_date?->toDateString();

        if ($date) {
            $matching = $items->first(function (DidacticPlanItem $item) use ($date) {
                $start = $item->start_date?->toDateString();
                $end = $item->end_date?->toDateString() ?: $start;

                return $start && $date >= $start && $date <= $end;
            });

            if ($matching) {
                return $matching;
            }
        }

        return $items->get(min($index, $items->count() - 1));
    }

    private function sessionActivityPayload(DidacticPlanItem $item, AcademicSession $session, int $sessionNumber): array
    {
        $title = 'Sesion '.$sessionNumber;
        if ($item->temarioPoint?->label) {
            $title .= ' - '.$item->temarioPoint->label;
        }

        $description = collect([
            $item->opening ? 'Apertura: '.$item->opening : null,
            $item->development ? 'Desarrollo: '.$item->development : null,
            $item->closing ? 'Cierre: '.$item->closing : null,
            $item->resources ? 'Recursos: '.$item->resources : null,
            $item->evaluation ? 'Evaluacion: '.$item->evaluation : null,
        ])->filter()->implode("\n");

        return [
            'title' => $title,
            'description' => $description,
            'temario_point_id' => $item->temario_point_id,
            'temario_subtopic_ids' => collect($item->temario_subtopic_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all(),
        ];
    }

    private function criterionForSession($assignment, DidacticPlan $plan, AcademicSession $session): ?EvaluationCriterion
    {
        $partial = $this->partialForSession($plan, $session);

        $query = EvaluationCriterion::query()
            ->forAssignmentAndPartial($assignment, $partial?->id)
            ->orderByRaw(
                "CASE
                    WHEN LOWER(name) LIKE '%evaluaci%n continua%' THEN 0
                    WHEN LOWER(name) LIKE '%continua%' THEN 1
                    WHEN LOWER(name) LIKE '%actividad%' THEN 2
                    WHEN LOWER(name) LIKE '%tarea%' THEN 3
                    WHEN LOWER(name) LIKE '%ejercicio%' THEN 4
                    ELSE 9
                END"
            )
            ->orderBy('id');

        return $query->first();
    }

    private function partialForSession(DidacticPlan $plan, AcademicSession $session): ?CyclePartial
    {
        if (! $plan->school_cycle_id || ! $session->session_date) {
            return null;
        }

        return CyclePartial::query()
            ->where('school_cycle_id', $plan->school_cycle_id)
            ->whereDate('start_date', '<=', $session->session_date)
            ->whereDate('end_date', '>=', $session->session_date)
            ->orderBy('sort_order')
            ->orderBy('start_date')
            ->first();
    }
}
