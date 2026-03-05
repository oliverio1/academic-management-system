<?php

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Services\Imports\AttendanceImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttendanceImportController extends Controller
{
    /**
     * Mostrar formulario de importación de asistencias
     */
    public function create()
    {
        return view('imports.attendances.create', [
            'academicPeriods' => AcademicPeriod::orderBy('start_date')->get(),
        ]);
    }

    /**
     * Procesar archivo Excel e importar asistencias
     */
    public function store(
        Request $request,
        AttendanceImportService $importService
    ) {
        set_time_limit(0); // 👈 CLAVE
        ini_set('memory_limit', '1024M');
        $request->validate([
            'academic_period_id' => 'required|exists:academic_periods,id',
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $result = $importService->import(
            $request->file('file'),
            (int) $request->academic_period_id
        );
        
        return view('imports.attendances.result', [
            'result' => $result,
        ]);
    }
}