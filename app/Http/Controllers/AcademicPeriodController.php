<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcademicPeriodRequest;
use Illuminate\Http\Request;
use App\Models\Modality;
use App\Models\AcademicPeriod;
use App\Http\Controllers\Traits\ActivatableController;

class AcademicPeriodController extends Controller
{
    use ActivatableController;

    protected $activeColumn = 'is_active';

    public function index() {
        $periods = AcademicPeriod::get();
        return view('periods.index', compact('periods'));
    }

    public function create() {
        $modalities = Modality::get();
        return view('periods.create', compact('modalities'));
    }

    public function store(AcademicPeriodRequest $request) {
        $period = AcademicPeriod::create([
            'name' => $request->name,
            'modality_id' => $request->modality_id,
            'code' => $request->code,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => true
        ]);
        return redirect()->route('academic-periods.index')->with('info', 'Registro guardado exitosamente');
    }

    public function edit($id) {
        $modalities = Modality::get();
        $period = AcademicPeriod::findOrFail($id);
        return view('periods.edit', compact('period', 'modalities'));
    }

    public function update(AcademicPeriodRequest $request, $id) {
        $period = AcademicPeriod::findOrFail($id);
        $period->update([
            'name' => $request->name,
            'modality_id' => $request->modality_id,
            'code' => $request->code,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => true
        ]);
        return redirect()->route('academic-periods.index')->with('info', 'Registro editado exitosamente');
    }
}
