<?php

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\CyclePartial;
use App\Models\SchoolCycle;
use App\Services\Imports\GradesImportService;
use Illuminate\Http\Request;

class GradesImportController extends Controller
{
    public function create()
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $schoolCycles = SchoolCycle::query()
            ->with(['modality:id,name', 'partials.academicPeriod:id,name,start_date,end_date'])
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->orderByDesc('start_date')
            ->get();

        return view('imports.grades.create', [
            'periods' => AcademicPeriod::orderBy('start_date')->get(),
            'schoolCycles' => $schoolCycles,
        ]);
    }

    public function store(
        Request $request,
        GradesImportService $service
    ) {
        $activeCampusId = (int) session('active_campus_id', 0);
        $request->validate([
            'school_cycle_id' => 'required|exists:school_cycles,id',
            'file' => 'required|file|mimes:xlsx,xls,ods',
            'academic_period_id' => 'required|exists:academic_periods,id',
        ]);

        if ($activeCampusId > 0) {
            $belongsToCampus = SchoolCycle::query()
                ->whereKey((int) $request->school_cycle_id)
                ->where('campus_id', $activeCampusId)
                ->exists();

            if (! $belongsToCampus) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'school_cycle_id' => 'El ciclo seleccionado no pertenece al campus activo.',
                    ]);
            }
        }

        $isPeriodInCycle = CyclePartial::query()
            ->where('school_cycle_id', (int) $request->school_cycle_id)
            ->where('academic_period_id', (int) $request->academic_period_id)
            ->exists();

        if (! $isPeriodInCycle) {
            return back()
                ->withInput()
                ->withErrors([
                    'academic_period_id' => 'El parcial seleccionado no pertenece al ciclo seleccionado.',
                ]);
        }

        $result = $service->import(
            $request->file('file'),
            (int) $request->academic_period_id,
            (int) $request->school_cycle_id
        );

        return view('imports.grades.result', compact('result'));
    }
}
