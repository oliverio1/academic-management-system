<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class EvaluationCriterion extends Model
{
    protected $fillable = [
        'teaching_assignment_id',
        'cycle_partial_id',
        'name',
        'percentage',
    ];

    public function TeachingAssignment() {
        return $this->belongsTo(TeachingAssignment::class);
    }

    public function assignment() {
        return $this->belongsTo(TeachingAssignment::class);
    }

    public function cyclePartial()
    {
        return $this->belongsTo(CyclePartial::class, 'cycle_partial_id');
    }

    public function activities() {
        return $this->hasMany(Activity::class, 'evaluation_criterion_id');
    }

    public function isAttendance(): bool {
        return mb_strtolower(trim($this->name)) === 'asistencia';
    }

    public function scopeForAssignmentAndPartial(
        Builder $query,
        TeachingAssignment $assignment,
        ?int $cyclePartialId,
        bool $fallbackLegacy = true
    ): Builder {
        $query->where('teaching_assignment_id', $assignment->id);

        if (! $cyclePartialId) {
            return $query;
        }

        $hasSpecific = (clone $query)
            ->where('cycle_partial_id', $cyclePartialId)
            ->exists();

        if ($hasSpecific || ! $fallbackLegacy) {
            return $query->where('cycle_partial_id', $cyclePartialId);
        }

        return $query->whereNull('cycle_partial_id');
    }

    public function scopeForAssignmentAndPeriod(
        Builder $query,
        TeachingAssignment $assignment,
        ?int $academicPeriodId
    ): Builder {
        $query->where('teaching_assignment_id', $assignment->id);

        if (! $academicPeriodId) {
            return $query;
        }

        $cycleId = (int) (
            $assignment->schoolCycleGroup?->school_cycle_id
            ?: $assignment->schedules()
                ->where('is_active', true)
                ->orderByDesc('school_cycle_id')
                ->value('school_cycle_id')
        );

        if (! $cycleId) {
            return $query->whereNull('cycle_partial_id');
        }

        $partialIds = CyclePartial::query()
            ->where('school_cycle_id', $cycleId)
            ->where('academic_period_id', $academicPeriodId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($partialIds)) {
            return $query->whereNull('cycle_partial_id');
        }

        $hasSpecific = (clone $query)
            ->whereIn('cycle_partial_id', $partialIds)
            ->exists();

        if ($hasSpecific) {
            return $query->whereIn('cycle_partial_id', $partialIds);
        }

        return $query->whereNull('cycle_partial_id');
    }
}
