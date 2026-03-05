<?php

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Services\Imports\GradesImportService;
use Illuminate\Http\Request;

class GradesImportController extends Controller
{
    public function create()
    {
        return view('imports.grades.create', [
            'periods' => AcademicPeriod::orderBy('start_date')->get(),
        ]);
    }

    public function store(
        Request $request,
        GradesImportService $service
    ) {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'academic_period_id' => 'required|exists:academic_periods,id',
        ]);

        $result = $service->import(
            $request->file('file'),
            (int) $request->academic_period_id
        );

        return view('imports.grades.result', compact('result'));
    }
}
