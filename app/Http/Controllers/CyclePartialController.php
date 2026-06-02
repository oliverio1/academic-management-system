<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\CyclePartial;
use App\Models\SchoolCycle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CyclePartialController extends Controller
{
    public function index(SchoolCycle $schoolCycle)
    {
        $partials = $schoolCycle->partials()->with('academicPeriod')->get();
        return view('school_cycles.partials.index', compact('schoolCycle', 'partials'));
    }

    public function create(SchoolCycle $schoolCycle)
    {
        return redirect()
            ->route('school-cycles.partials.index', $schoolCycle)
            ->with('info', 'Los parciales se generan automaticamente segun modalidad (Bachillerato: 2, Preparatoria: 4).');
    }

    public function store(Request $request, SchoolCycle $schoolCycle)
    {
        return redirect()
            ->route('school-cycles.partials.index', $schoolCycle)
            ->with('info', 'La alta manual de parciales esta deshabilitada. Se generan automaticamente por modalidad.');
    }

    public function edit(SchoolCycle $schoolCycle, CyclePartial $partial)
    {
        abort_unless($partial->school_cycle_id === $schoolCycle->id, 404);

        return view('school_cycles.partials.edit', compact('schoolCycle', 'partial'));
    }

    public function update(Request $request, SchoolCycle $schoolCycle, CyclePartial $partial)
    {
        abort_unless($partial->school_cycle_id === $schoolCycle->id, 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:40',
            'sort_order' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'teacher_capture_deadline_at' => 'nullable|date|after_or_equal:end_date',
        ]);

        $this->validatePartialDates($schoolCycle, $data['start_date'], $data['end_date'], $partial->id);

        $partial->update($data + ['is_active' => $request->boolean('is_active')]);
        $period = $this->syncAcademicPeriod($schoolCycle, $partial, $data);
        $partial->update([
            'academic_period_id' => $period->id,
            'code' => $partial->code ?: $period->code,
        ]);

        return redirect()->route('school-cycles.partials.index', $schoolCycle)->with('info', 'Parcial actualizado correctamente');
    }

    public function destroy(SchoolCycle $schoolCycle, CyclePartial $partial)
    {
        abort_unless($partial->school_cycle_id === $schoolCycle->id, 404);

        $partial->update(['is_active' => false]);
        $partial->academicPeriod?->update(['is_active' => false]);

        return back()->with('info', 'Parcial desactivado');
    }

    public function activate(SchoolCycle $schoolCycle, CyclePartial $partial)
    {
        abort_unless($partial->school_cycle_id === $schoolCycle->id, 404);

        $partial->update(['is_active' => true]);
        $partial->academicPeriod?->update(['is_active' => true]);

        return back()->with('info', 'Parcial activado');
    }

    protected function validatePartialDates(SchoolCycle $schoolCycle, string $startDate, string $endDate, ?int $ignoreId = null): void
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        $cycleStart = Carbon::parse($schoolCycle->start_date)->startOfDay();
        $cycleEnd = Carbon::parse($schoolCycle->end_date)->endOfDay();

        if ($start->lt($cycleStart) || $end->gt($cycleEnd)) {
            throw ValidationException::withMessages([
                'start_date' => 'Las fechas del parcial deben estar dentro del rango del ciclo escolar.',
            ]);
        }

        $overlapQuery = $schoolCycle->partials()
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($qq) use ($startDate, $endDate) {
                        $qq->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            });

        if ($ignoreId) {
            $overlapQuery->where('id', '!=', $ignoreId);
        }

        if ($overlapQuery->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'Las fechas del parcial se traslapan con otro parcial del mismo ciclo.',
            ]);
        }
    }

    protected function syncAcademicPeriod(SchoolCycle $schoolCycle, CyclePartial $partial, array $data): AcademicPeriod
    {
        $period = $partial->academicPeriod ?: new AcademicPeriod();

        $baseCode = trim((string) ($data['code'] ?? ''));
        if ($baseCode === '') {
            $baseCode = strtoupper($schoolCycle->code . '-P' . $data['sort_order']);
        }

        $resolvedCode = $this->resolveUniquePeriodCode($baseCode, $period->id);

        $period->fill([
            'name' => $data['name'],
            'modality_id' => $schoolCycle->modality_id,
            'code' => $resolvedCode,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_active' => $partial->is_active ?? true,
        ]);

        $period->save();

        return $period;
    }

    protected function resolveUniquePeriodCode(string $baseCode, ?int $ignorePeriodId = null): string
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
}
