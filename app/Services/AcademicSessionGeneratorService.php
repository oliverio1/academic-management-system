<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Schedule;
use App\Models\SchoolCycle;
use App\Models\TeachingAssignment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AcademicSessionGeneratorService
{
    public function generateForAssignment(TeachingAssignment $assignment): int
    {
        $assignment->loadMissing(['group.level', 'schedules']);

        $created = 0;

        foreach ($assignment->schedules->where('is_active', true) as $schedule) {
            $created += $this->generateForSchedule($schedule);
        }

        return $created;
    }

    public function generateForSchedule(Schedule $schedule): int
    {
        if (! $schedule->is_active) {
            return 0;
        }

        $schedule->loadMissing('assignment.group.level');

        $periods = $this->periodsForAssignment($schedule->assignment, $schedule->school_cycle_id ? (int) $schedule->school_cycle_id : null);

        if ($periods->isEmpty()) {
            return 0;
        }

        return $this->generateForScheduleAcrossPeriods($schedule, $periods);
    }

    private function periodsForAssignment(TeachingAssignment $assignment, ?int $schoolCycleId = null): Collection
    {
        $modalityId = optional($assignment->group?->level)->modality_id;

        if (! $modalityId) {
            return collect();
        }

        $activeCycle = null;
        if ($schoolCycleId) {
            $activeCycle = SchoolCycle::query()
                ->where('modality_id', $modalityId)
                ->where('id', $schoolCycleId)
                ->first();
        }

        if (! $activeCycle) {
            $activeCycle = SchoolCycle::query()
                ->where('modality_id', $modalityId)
                ->where('is_active', true)
                ->orderByDesc('start_date')
                ->first();
        }

        if ($activeCycle) {
            $periodIds = $activeCycle->partials()
                ->whereNotNull('academic_period_id')
                ->pluck('academic_period_id')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($periodIds->isNotEmpty()) {
                return AcademicPeriod::query()
                    ->whereIn('id', $periodIds)
                    ->orderBy('start_date')
                    ->get();
            }
        }

        // Fallback: solo periodos activos de la modalidad (evita generar historico completo).
        return AcademicPeriod::query()
            ->where('modality_id', $modalityId)
            ->where('is_active', true)
            ->orderBy('start_date')
            ->get();
    }

    private function generateForScheduleAcrossPeriods(Schedule $schedule, Collection $periods): int
    {
        $targetDay = $this->resolveDayOfWeekIso($schedule->day_of_week);

        if ($targetDay === null) {
            return 0;
        }

        $created = 0;

        foreach ($periods as $period) {
            $start = Carbon::parse($period->start_date);
            $end = Carbon::parse($period->end_date);

            $current = $start->copy();
            while ($current->dayOfWeekIso !== $targetDay) {
                $current->addDay();
            }

            while ($current->lte($end)) {
                $sessionDate = $current->toDateString();
                $session = AcademicSession::query()
                    ->where('schedule_id', $schedule->id)
                    ->whereDate('session_date', $sessionDate)
                    ->first();

                if (! $session) {
                    $session = AcademicSession::create([
                        'schedule_id' => $schedule->id,
                        'session_date' => $sessionDate,
                        'teaching_assignment_id' => $schedule->teaching_assignment_id,
                        'academic_period_id' => $period->id,
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                    ]);
                    $created++;
                }

                $current->addWeek();
            }
        }

        return $created;
    }

    private function resolveDayOfWeekIso(mixed $rawDay): ?int
    {
        if (is_numeric($rawDay)) {
            $day = (int) $rawDay;
            return ($day >= 1 && $day <= 7) ? $day : null;
        }

        $day = strtolower(trim((string) $rawDay));

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
            'mi?rcoles' => 3,
            'jueves' => 4,
            'viernes' => 5,
            'sabado' => 6,
            's?bado' => 6,
            'domingo' => 7,
        ];

        return $map[$day] ?? null;
    }
}
