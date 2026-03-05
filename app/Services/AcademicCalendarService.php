<?php

namespace App\Services;

use App\Models\AcademicCalendarDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AcademicCalendarService
{
    protected array $nonWorkingDaysCache = [];

    public function __construct()
    {
        //
    }

    protected function loadNonWorkingDays(int $modalityId): void {
        if (isset($this->nonWorkingDaysCache[$modalityId])) {
            return;
        }

        $days = AcademicCalendarDay::where(function ($q) use ($modalityId) {
                $q->whereNull('modality_id')
                  ->orWhere('modality_id', $modalityId);
            })
            ->where('affects_students', true)
            ->whereIn('type', ['holiday', 'vacation'])
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->toArray();

        $this->nonWorkingDaysCache[$modalityId] = array_flip($days);
    }

    public function isNonWorkingDay(Carbon $date, ?int $modalityId): bool {
        $this->loadNonWorkingDays($modalityId);

        return isset(
            $this->nonWorkingDaysCache[$modalityId][$date->toDateString()]
        );
    }

    public function subtractSchoolDays(Carbon $from,int $days,?int $modalityId): Carbon {
        $date = $from->copy();
        $count = 0;
        while ($count < $days) {
            $date->subDay();
            if ($date->isWeekend()) {
                continue;
            }
            if ($this->isNonWorkingDay($date, $modalityId)) {
                continue;
            }
            $count++;
        }
        return $date;
    }

    public function getLastSchoolDay(Carbon $from, ?int $modalityId): Carbon {
        $date = $from->copy();
        while (true) {
            Log::info('Evaluando fecha', [
                'date' => $date->toDateString(),
                'weekend' => $date->isWeekend()
            ]);
            if ($date->isWeekend()) {
                $date->subDay();
                continue;
            }
            if ($this->isNonWorkingDay($date, $modalityId)) {
                $date->subDay();
                continue;
            }
            Log::info('Día lectivo encontrado', [
                'date' => $date->toDateString()
            ]);
            return $date;
        }
    }

    public function getAlertDateRange(int $schoolDays, ?int $modalityId): array {
        $end = $this->getLastSchoolDay(now(), $modalityId);
        $start = $this->subtractSchoolDays(
            $end,
            $schoolDays,
            $modalityId
        );
        return [$start, $end];
    }
}
