<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Activity;
use App\Models\EconomicActa;
use App\Models\TeachingAssignment;

class EconomicActaLockService
{
    public function isSessionLocked(AcademicSession $session): bool
    {
        $schoolCycleId = (int) ($session->schedule?->school_cycle_id ?? 0);
        $periodId = (int) ($session->academic_period_id ?? 0);

        if ($schoolCycleId <= 0 || $periodId <= 0) {
            return false;
        }

        return $this->hasClosedOrSent(
            (int) $session->teaching_assignment_id,
            $schoolCycleId,
            $periodId
        );
    }

    public function isActivityLocked(Activity $activity): bool
    {
        $periodId = (int) ($activity->academic_period_id ?? 0);
        if ($periodId <= 0) {
            return false;
        }

        $schoolCycleId = 0;

        if ($activity->sessionActivity?->academicSession?->schedule?->school_cycle_id) {
            $schoolCycleId = (int) $activity->sessionActivity->academicSession->schedule->school_cycle_id;
        }

        if ($schoolCycleId <= 0) {
            $schoolCycleId = $this->resolveCycleIdFromAssignment($activity->assignment);
        }

        if ($schoolCycleId <= 0) {
            return false;
        }

        return $this->hasClosedOrSent(
            (int) $activity->teaching_assignment_id,
            $schoolCycleId,
            $periodId
        );
    }

    public function isAssignmentPeriodLocked(TeachingAssignment $assignment, int $academicPeriodId, ?int $schoolCycleId = null): bool
    {
        $cycleId = (int) ($schoolCycleId ?: $this->resolveCycleIdFromAssignment($assignment));
        if ($cycleId <= 0 || $academicPeriodId <= 0) {
            return false;
        }

        return $this->hasClosedOrSent($assignment->id, $cycleId, $academicPeriodId);
    }

    private function resolveCycleIdFromAssignment(TeachingAssignment $assignment): int
    {
        $activeCycle = $assignment->schedules()
            ->where('is_active', true)
            ->whereHas('schoolCycle', fn ($q) => $q->where('is_active', true))
            ->orderByDesc('id')
            ->value('school_cycle_id');

        if ($activeCycle) {
            return (int) $activeCycle;
        }

        $lastCycle = $assignment->schedules()
            ->orderByDesc('id')
            ->value('school_cycle_id');

        return (int) ($lastCycle ?: 0);
    }

    private function hasClosedOrSent(int $assignmentId, int $schoolCycleId, int $periodId): bool
    {
        return EconomicActa::query()
            ->where('teaching_assignment_id', $assignmentId)
            ->whereIn('status', ['submitted', 'closed', 'sent'])
            ->whereHas('partial', function ($query) use ($schoolCycleId, $periodId) {
                $query->where('school_cycle_id', $schoolCycleId)
                    ->where('academic_period_id', $periodId);
            })
            ->exists();
    }
}
