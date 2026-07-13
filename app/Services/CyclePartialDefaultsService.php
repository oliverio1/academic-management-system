<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\CyclePartial;
use App\Models\SchoolCycle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CyclePartialDefaultsService
{
    public function syncForCycle(SchoolCycle $cycle): void
    {
        $cycle->loadMissing(['modality', 'modalities']);

        $count = $this->defaultCountForCycle($cycle);
        if (!$count) {
            return;
        }

        $ranges = $this->buildRanges($cycle, $count);
        $existing = $cycle->partials()->orderBy('sort_order')->get()->values();

        foreach ($ranges as $index => $range) {
            $position = $index + 1;
            $partial = $existing->get($index);

            if (!$partial) {
                $partial = new CyclePartial([
                    'school_cycle_id' => $cycle->id,
                ]);
            }

            $name = $this->partialName($position);
            $partial->fill([
                'name' => $name,
                'code' => strtoupper($cycle->code . '-P' . $position),
                'sort_order' => $position,
                'start_date' => $range['start_date'],
                'end_date' => $range['end_date'],
                'is_active' => (bool) $cycle->is_active,
            ]);

            if (!$partial->teacher_capture_deadline_at) {
                $partial->teacher_capture_deadline_at = Carbon::parse($range['end_date'])
                    ->addDays(3)
                    ->endOfDay();
            }

            $partial->save();

            $period = $this->syncAcademicPeriod($cycle, $partial);
            $partial->update([
                'academic_period_id' => $period->id,
                'code' => $partial->code ?: $period->code,
            ]);
        }

        // Quitar parciales extra si existian de una configuracion anterior.
        $extraPartials = $existing->slice($count)->values();
        foreach ($extraPartials as $extra) {
            $periodId = $extra->academic_period_id;
            $extra->delete();

            if ($periodId) {
                $usedElsewhere = CyclePartial::query()
                    ->where('academic_period_id', $periodId)
                    ->exists();
                if (!$usedElsewhere) {
                    AcademicPeriod::query()->whereKey($periodId)->delete();
                }
            }
        }
    }

    public function defaultCountForModality(?string $modalityName): ?int
    {
        $name = $this->normalize((string) $modalityName);

        if (str_contains($name, 'preparatoria')) {
            return 4;
        }

        if (str_contains($name, 'bachillerato')) {
            return 2;
        }

        return null;
    }

    public function defaultCountForCycle(SchoolCycle $cycle): ?int
    {
        $counts = collect();

        if ($cycle->modality) {
            $counts->push($this->defaultCountForModality($cycle->modality->name));
        }

        foreach ($cycle->modalities as $modality) {
            $counts->push($this->defaultCountForModality($modality->name));
        }

        return $counts
            ->filter()
            ->max();
    }

    private function buildRanges(SchoolCycle $cycle, int $count): Collection
    {
        $start = Carbon::parse($cycle->start_date)->startOfDay();
        $end = Carbon::parse($cycle->end_date)->startOfDay();
        $totalDays = max(1, $start->diffInDays($end) + 1);

        $rows = collect();
        for ($i = 0; $i < $count; $i++) {
            $fromOffset = (int) floor(($i * $totalDays) / $count);
            $toOffset = (int) floor((($i + 1) * $totalDays) / $count) - 1;

            $rangeStart = $start->copy()->addDays($fromOffset);
            $rangeEnd = $start->copy()->addDays(max($fromOffset, $toOffset));
            if ($rangeEnd->gt($end)) {
                $rangeEnd = $end->copy();
            }

            $rows->push([
                'start_date' => $rangeStart->toDateString(),
                'end_date' => $rangeEnd->toDateString(),
            ]);
        }

        return $rows;
    }

    private function partialName(int $position): string
    {
        return match ($position) {
            1 => 'Primer parcial',
            2 => 'Segundo parcial',
            3 => 'Tercer parcial',
            4 => 'Cuarto parcial',
            5 => 'Quinto parcial',
            6 => 'Sexto parcial',
            default => 'Parcial ' . $position,
        };
    }

    private function syncAcademicPeriod(SchoolCycle $cycle, CyclePartial $partial): AcademicPeriod
    {
        $period = $partial->academicPeriod ?: new AcademicPeriod();
        $baseCode = strtoupper($cycle->code . '-P' . $partial->sort_order);
        $resolvedCode = $this->resolveUniquePeriodCode($baseCode, $period->id);

        $period->fill([
            'name' => $partial->name,
            'modality_id' => $cycle->modality_id,
            'code' => $resolvedCode,
            'start_date' => $partial->start_date,
            'end_date' => $partial->end_date,
            'is_active' => (bool) $partial->is_active,
        ]);

        $period->save();

        return $period;
    }

    private function resolveUniquePeriodCode(string $baseCode, ?int $ignorePeriodId = null): string
    {
        $candidate = $baseCode;
        $suffix = 1;

        while (AcademicPeriod::query()
            ->when($ignorePeriodId, fn ($q) => $q->where('id', '!=', $ignorePeriodId))
            ->where('code', $candidate)
            ->exists()) {
            $candidate = $baseCode . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $map = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
        ];

        return strtr($value, $map);
    }
}
