<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\AcademicResolution;
use App\Models\AssignmentRemedialExam;
use App\Models\CyclePartial;
use App\Models\EvaluationCriterion;
use App\Models\SchoolCycle;
use App\Models\Student;
use App\Models\TeachingAssignment;
use Carbon\Carbon;

class AcademicPerformanceService
{
    protected GradeService $gradeService;

    public function __construct(GradeService $gradeService)
    {
        $this->gradeService = $gradeService;
    }

    public function finalGradeForAssignment(Student $student, TeachingAssignment $assignment): ?float
    {
        return $this->promotionOutcomeForAssignment($student, $assignment)['final'];
    }

    /**
     * @return array{
     *     final: ?float,
     *     passed: bool,
     *     period: ?AcademicPeriod,
     *     base_final: ?float,
     *     route: string,
     *     label: string
     * }
     */
    public function promotionOutcomeForAssignment(Student $student, TeachingAssignment $assignment): array
    {
        $period = $this->resolveCurrentPeriodForAssignment($assignment);
        if (! $period) {
            return [
                'final' => null,
                'passed' => false,
                'period' => null,
                'base_final' => null,
                'route' => 'without_period',
                'label' => 'Sin parcial activo',
            ];
        }

        if ($resolution = $this->resolutionFor($student, $assignment, $period)) {
            $final = match ($resolution->type) {
                'override' => (float) $resolution->value,
                'repeat_previous' => $this->previousPeriodGrade($student, $assignment, $period),
                'defer_next' => null,
            };

            return [
                'final' => $final,
                'passed' => $final !== null && $final >= 6.0,
                'period' => $period,
                'base_final' => $final,
                'route' => 'academic_resolution',
                'label' => 'Resolucion academica',
            ];
        }

        $baseFinal = $this->baseFinalWithoutRecoveryRules($student, $assignment, $period);
        if ($baseFinal === null) {
            return [
                'final' => null,
                'passed' => false,
                'period' => $period,
                'base_final' => null,
                'route' => 'without_grades',
                'label' => 'Sin calificacion base',
            ];
        }

        $outcome = $this->resolvePromotionOutcome($student, $assignment, $period, $baseFinal);
        return [
            'final' => $outcome['final'],
            'passed' => $outcome['passed'],
            'period' => $period,
            'base_final' => round($baseFinal, 2),
            'route' => $outcome['route'],
            'label' => $outcome['label'],
        ];
    }

    public function studentGeneralAverage(Student $student): ?float
    {
        $assignments = TeachingAssignment::where('group_id', $student->group_id)->get();

        $sum = 0.0;
        $count = 0;
        foreach ($assignments as $assignment) {
            $avg = $this->finalGradeForAssignment($student, $assignment);
            if ($avg !== null) {
                $sum += $avg;
                $count++;
            }
        }

        return $count > 0 ? round($sum / $count, 2) : null;
    }

    public function academicStatus(Student $student, TeachingAssignment $assignment): string
    {
        if ($this->hasIncompleteCriteria($student, $assignment)) {
            return 'incomplete';
        }

        $outcome = $this->promotionOutcomeForAssignment($student, $assignment);
        if ($outcome['final'] === null && $outcome['base_final'] === null) {
            return 'complete';
        }

        return $outcome['passed'] ? 'complete' : 'risk';
    }

    public function hasIncompleteCriteria(Student $student, TeachingAssignment $assignment): bool
    {
        $period = $this->resolveCurrentPeriodForAssignment($assignment);
        if (! $period) {
            return false;
        }

        $ranges = $this->effectiveRanges($student, $assignment, $period);
        if (empty($ranges)) {
            return false;
        }

        $criteria = EvaluationCriterion::query()
            ->forAssignmentAndPeriod($assignment, (int) $period->id)
            ->with('activities')
            ->get();

        foreach ($criteria as $criterion) {
            if ($criterion->activities->isEmpty()) {
                continue;
            }

            foreach ($criterion->activities as $activity) {
                foreach ($ranges as $range) {
                    if ($activity->created_at->lt($range['from']) || $activity->created_at->gt($range['to'])) {
                        continue;
                    }

                    if (! $activity->grades()->where('student_id', $student->id)->exists()) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function studentHasCriticalSubject(Student $student, float $minAverage = 6.0): bool
    {
        $assignments = TeachingAssignment::where('group_id', $student->group_id)->get();
        foreach ($assignments as $assignment) {
            $final = $this->finalGradeForAssignment($student, $assignment);
            if ($final === null) {
                continue;
            }
            if ($final < $minAverage) {
                return true;
            }
        }
        return false;
    }

    public function breakdownForAssignment(Student $student, TeachingAssignment $assignment): array
    {
        $period = $this->resolveCurrentPeriodForAssignment($assignment);
        if (! $period) {
            return ['rows' => [], 'final' => null];
        }

        if ($resolution = $this->resolutionFor($student, $assignment, $period)) {
            if ($resolution->type === 'override') {
                $value = (float) $resolution->value;
                return [
                    'rows' => [[
                        'criterion' => 'Resolucion academica',
                        'percentage' => 100,
                        'average' => round($value, 2),
                        'contribution' => round($value, 2),
                    ]],
                    'final' => round($value, 2),
                ];
            }

            if ($resolution->type === 'repeat_previous') {
                $previous = $this->previousPeriodGrade($student, $assignment, $period);
                return [
                    'rows' => [[
                        'criterion' => 'Resolucion academica (repite periodo anterior)',
                        'percentage' => 100,
                        'average' => $previous,
                        'contribution' => $previous,
                    ]],
                    'final' => $previous,
                ];
            }

            if ($resolution->type === 'defer_next') {
                return [
                    'rows' => [[
                        'criterion' => 'Resolucion academica (diferida)',
                        'percentage' => 100,
                        'average' => null,
                        'contribution' => null,
                    ]],
                    'final' => null,
                ];
            }
        }

        if ($this->isLevelChangeDuringPeriod($student, $period)) {
            return $this->gradeService->breakdown($assignment, $student);
        }

        $ranges = $this->effectiveRanges($student, $assignment, $period);
        if (empty($ranges)) {
            return ['rows' => [], 'final' => null];
        }

        if (count($ranges) === 1) {
            return $this->gradeService->breakdown(
                $assignment,
                $student,
                $ranges[0]['from'],
                $ranges[0]['to']
            );
        }

        $weightedSum = 0.0;
        $totalWeeks = 0;
        $rowsAccumulator = [];

        foreach ($ranges as $range) {
            $partial = $this->gradeService->breakdown($assignment, $student, $range['from'], $range['to']);
            $partialFinal = $partial['final'];
            if ($partialFinal === null) {
                continue;
            }

            $weeks = max(1, $range['from']->diffInWeeks($range['to']));
            $weightedSum += $partialFinal * $weeks;
            $totalWeeks += $weeks;

            foreach ($partial['rows'] as $row) {
                $key = (string) $row['criterion'];
                if (! isset($rowsAccumulator[$key])) {
                    $rowsAccumulator[$key] = [
                        'criterion' => $row['criterion'],
                        'percentage' => $row['percentage'],
                        'average_weighted_sum' => 0.0,
                        'contribution_weighted_sum' => 0.0,
                        'weeks' => 0,
                    ];
                }

                $rowsAccumulator[$key]['average_weighted_sum'] += ((float) ($row['average'] ?? 0)) * $weeks;
                $rowsAccumulator[$key]['contribution_weighted_sum'] += ((float) ($row['contribution'] ?? 0)) * $weeks;
                $rowsAccumulator[$key]['weeks'] += $weeks;
            }
        }

        if ($totalWeeks === 0) {
            return ['rows' => [], 'final' => null];
        }

        $orderedCriteria = EvaluationCriterion::query()
            ->forAssignmentAndPeriod($assignment, (int) $period->id)
            ->orderBy('id')
            ->pluck('name')
            ->all();

        $rows = [];
        foreach ($orderedCriteria as $criterionName) {
            if (! isset($rowsAccumulator[$criterionName])) {
                continue;
            }

            $bucket = $rowsAccumulator[$criterionName];
            $w = max(1, $bucket['weeks']);

            $rows[] = [
                'criterion' => $bucket['criterion'],
                'percentage' => $bucket['percentage'],
                'average' => round($bucket['average_weighted_sum'] / $w, 2),
                'contribution' => round($bucket['contribution_weighted_sum'] / $w, 2),
            ];
        }

        return [
            'rows' => $rows,
            'final' => round($weightedSum / $totalWeeks, 2),
        ];
    }

    public function riskBreakdown(Student $student, TeachingAssignment $assignment): array
    {
        $period = $this->resolveCurrentPeriodForAssignment($assignment);
        if (! $period) {
            return [];
        }

        if ($this->isLevelChangeDuringPeriod($student, $period)) {
            return $this->gradeService->breakdown($assignment, $student);
        }

        $ranges = $this->effectiveRanges($student, $assignment, $period);
        if (empty($ranges)) {
            return [];
        }

        if (count($ranges) === 1) {
            return $this->gradeService->breakdown($assignment, $student, $ranges[0]['from'], $ranges[0]['to']);
        }

        $lastRange = collect($ranges)->last();
        return $this->gradeService->breakdown($assignment, $student, $lastRange['from'], $lastRange['to']);
    }

    public function periodForAssignment(TeachingAssignment $assignment): ?AcademicPeriod
    {
        return $this->resolveCurrentPeriodForAssignment($assignment);
    }

    protected function resolutionFor(Student $student, TeachingAssignment $assignment, AcademicPeriod $period): ?AcademicResolution
    {
        return AcademicResolution::where('student_id', $student->id)
            ->where('teaching_assignment_id', $assignment->id)
            ->where('academic_period_id', $period->id)
            ->latest()
            ->first();
    }

    protected function isLevelChangeDuringPeriod(Student $student, AcademicPeriod $period): bool
    {
        return $student->groupHistories
            ->filter(function ($history) use ($period) {
                $start = Carbon::parse($history->start_date);
                $end = $history->end_date ? Carbon::parse($history->end_date) : Carbon::now();

                return $start->lte($period->end_date) && $end->gte($period->start_date);
            })
            ->pluck('group.level_id')
            ->unique()
            ->count() > 1;
    }

    protected function effectiveRanges(Student $student, TeachingAssignment $assignment, AcademicPeriod $period): array
    {
        $ranges = $student->groupHistories
            ->where('group_id', $assignment->group_id)
            ->map(function ($history) use ($period) {
                $from = Carbon::parse($history->start_date)->max(Carbon::parse($period->start_date));
                $to = $history->end_date
                    ? Carbon::parse($history->end_date)->min(Carbon::parse($period->end_date))
                    : Carbon::parse($period->end_date);

                if ($from->gt($to)) {
                    return null;
                }

                return ['from' => $from, 'to' => $to];
            })
            ->filter()
            ->values()
            ->all();

        if (! empty($ranges)) {
            return $ranges;
        }

        if ((int) $student->group_id === (int) $assignment->group_id) {
            return [[
                'from' => Carbon::parse($period->start_date),
                'to' => Carbon::parse($period->end_date),
            ]];
        }

        return [];
    }

    protected function previousPeriodGrade(Student $student, TeachingAssignment $assignment, AcademicPeriod $currentPeriod): ?float
    {
        $previousPeriod = AcademicPeriod::query()
            ->where('modality_id', $currentPeriod->modality_id)
            ->where('end_date', '<', $currentPeriod->start_date)
            ->orderByDesc('end_date')
            ->first();

        if (! $previousPeriod) {
            return null;
        }

        if ($this->isLevelChangeDuringPeriod($student, $previousPeriod)) {
            return $this->gradeService->finalGrade(
                $assignment,
                $student,
                Carbon::parse($previousPeriod->start_date),
                Carbon::parse($previousPeriod->end_date)
            );
        }

        $ranges = $this->effectiveRanges($student, $assignment, $previousPeriod);
        if (empty($ranges)) {
            return null;
        }

        if (count($ranges) === 1) {
            return $this->gradeService->finalGrade(
                $assignment,
                $student,
                $ranges[0]['from'],
                $ranges[0]['to']
            );
        }

        $weightedSum = 0.0;
        $totalWeeks = 0;
        foreach ($ranges as $range) {
            $partial = $this->gradeService->finalGrade($assignment, $student, $range['from'], $range['to']);
            if ($partial === null) {
                continue;
            }

            $weeks = max(1, $range['from']->diffInWeeks($range['to']));
            $weightedSum += $partial * $weeks;
            $totalWeeks += $weeks;
        }

        return $totalWeeks > 0 ? round($weightedSum / $totalWeeks, 2) : null;
    }

    protected function resolveCurrentPeriodForAssignment(TeachingAssignment $assignment): ?AcademicPeriod
    {
        $modalityId = (int) $assignment->group->level->modality_id;

        $period = AcademicPeriod::activeForModality($modalityId);
        if ($period) {
            return $period;
        }

        $activeCycle = SchoolCycle::query()
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first();

        if (! $activeCycle || (int) $activeCycle->modality_id !== $modalityId) {
            return AcademicPeriod::query()
                ->where('modality_id', $modalityId)
                ->orderByDesc('start_date')
                ->first();
        }

        $partials = CyclePartial::query()
            ->where('school_cycle_id', $activeCycle->id)
            ->whereNotNull('academic_period_id')
            ->with('academicPeriod')
            ->orderBy('sort_order')
            ->get();

        $cyclePeriods = $partials->pluck('academicPeriod')->filter()->values();
        if ($cyclePeriods->isEmpty()) {
            return AcademicPeriod::query()
                ->where('modality_id', $modalityId)
                ->orderByDesc('start_date')
                ->first();
        }

        $today = now()->toDateString();
        $current = $cyclePeriods->first(function ($p) use ($today) {
            return $p->start_date
                && $p->end_date
                && $p->start_date->toDateString() <= $today
                && $p->end_date->toDateString() >= $today;
        });

        if ($current) {
            return $current;
        }

        return $cyclePeriods
            ->sortByDesc(fn ($p) => optional($p->start_date)->toDateString())
            ->first();
    }

    protected function baseFinalWithoutRecoveryRules(Student $student, TeachingAssignment $assignment, AcademicPeriod $period): ?float
    {
        if ($this->isLevelChangeDuringPeriod($student, $period)) {
            return $this->gradeService->finalGrade($assignment, $student);
        }

        $ranges = $this->effectiveRanges($student, $assignment, $period);
        if (empty($ranges)) {
            return null;
        }

        if (count($ranges) === 1) {
            return $this->gradeService->finalGrade(
                $assignment,
                $student,
                $ranges[0]['from'],
                $ranges[0]['to']
            );
        }

        $weightedSum = 0.0;
        $totalWeeks = 0;

        foreach ($ranges as $range) {
            $partial = $this->gradeService->finalGrade(
                $assignment,
                $student,
                $range['from'],
                $range['to']
            );

            if ($partial === null) {
                continue;
            }

            $weeks = max(1, $range['from']->diffInWeeks($range['to']));
            $weightedSum += $partial * $weeks;
            $totalWeeks += $weeks;
        }

        return $totalWeeks > 0 ? round($weightedSum / $totalWeeks, 2) : null;
    }

    /**
     * @return array{final: float, passed: bool, route: string, label: string}
     */
    protected function resolvePromotionOutcome(
        Student $student,
        TeachingAssignment $assignment,
        AcademicPeriod $period,
        float $baseFinal
    ): array {
        $remedial = AssignmentRemedialExam::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->where('academic_period_id', $period->id)
            ->first();

        $baseFinal = round($baseFinal, 2);
        $ordA = $this->sanitizeExamScore(optional($remedial)->ordinario_a_score);
        $ordB = $this->sanitizeExamScore(optional($remedial)->ordinario_b_score);
        $extra = $this->sanitizeExamScore(optional($remedial)->extraordinario_score);

        if ($extra !== null) {
            return [
                'final' => $extra,
                'passed' => $extra >= 6.0,
                'route' => 'extraordinario_final',
                'label' => $extra >= 6.0
                    ? 'Final por extraordinario (acreditado)'
                    : 'Final por extraordinario (reprobado)',
            ];
        }

        if ($ordB !== null) {
            $avgB = round(($baseFinal + $ordB) / 2, 2);
            return [
                'final' => $avgB,
                'passed' => $avgB >= 6.0,
                'route' => 'ordinario_b_final',
                'label' => $avgB >= 6.0
                    ? 'Final por ordinario B (acreditado)'
                    : 'Final por ordinario B (reprobado)',
            ];
        }

        if ($ordA !== null) {
            $avgA = round(($baseFinal + $ordA) / 2, 2);
            if ($avgA >= 6.0) {
                return [
                    'final' => $avgA,
                    'passed' => true,
                    'route' => 'ordinario_a_final',
                    'label' => 'Final por ordinario A (acreditado)',
                ];
            }

            return [
                'final' => $avgA,
                'passed' => false,
                'route' => 'pending_ordinario_b',
                'label' => 'Reprobado en ordinario A, pendiente ordinario B',
            ];
        }

        $final = $baseFinal;
        return [
            'final' => $final,
            'passed' => $final >= 6.0,
            'route' => $final >= 6.0 ? 'base_direct' : 'pending_ordinario_a',
            'label' => $final >= 6.0 ? 'Acreditado directo' : 'Pendiente de ordinario A',
        ];
    }

    protected function sanitizeExamScore($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $score = (float) $value;
        if ($score < 0 || $score > 10) {
            return null;
        }

        return round($score, 2);
    }

}
