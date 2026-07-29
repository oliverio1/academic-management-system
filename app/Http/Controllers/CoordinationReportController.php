<?php

namespace App\Http\Controllers;

use App\Models\CoordinationReport;
use App\Services\CurrentSchoolCycle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoordinationReportController extends Controller
{
    private const RECEIVED_VIA_OPTIONS = [
        'phone' => 'Llamada',
        'in_person' => 'Presencial',
        'email' => 'Correo',
        'message' => 'Mensaje',
        'other' => 'Otro',
    ];

    private const CATEGORY_OPTIONS = [
        'academic' => 'Academico',
        'behavioral' => 'Conductual',
        'attendance' => 'Asistencia',
        'communication' => 'Comunicacion',
        'facilities' => 'Instalaciones',
        'technology' => 'Tecnologia',
        'administrative' => 'Administrativo',
        'safety' => 'Seguridad',
        'health' => 'Salud',
        'other' => 'Otro',
    ];

    public function create()
    {
        return view('coordination.reports.create', [
            'receivedViaOptions' => self::RECEIVED_VIA_OPTIONS,
            'categoryOptions' => self::CATEGORY_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'received_via' => ['required', Rule::in(array_keys(self::RECEIVED_VIA_OPTIONS))],
            'reporter_name' => ['nullable', 'string', 'max:160'],
            'reporter_contact' => ['nullable', 'string', 'max:180'],
            'category' => ['required', Rule::in(array_keys(self::CATEGORY_OPTIONS))],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:8000'],
            'priority' => ['required', 'integer', 'between:1,3'],
        ]);

        CoordinationReport::create($data + [
            'campus_id' => session('active_campus_id'),
            'school_cycle_id' => app(CurrentSchoolCycle::class)->id($request->user()),
            'reported_by' => $request->user()->id,
            'status' => 'open',
        ]);

        return redirect()
            ->route('coordination.reports.index')
            ->with('info', 'Reporte levantado correctamente.');
    }

    public function updateStatus(Request $request, CoordinationReport $report)
    {
        abort_if(
            session('active_campus_id') && $report->campus_id && (int) $report->campus_id !== (int) session('active_campus_id'),
            403
        );
        $cycleId = app(CurrentSchoolCycle::class)->id($request->user());
        abort_if($cycleId && $report->school_cycle_id && (int) $report->school_cycle_id !== (int) $cycleId, 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['reviewed', 'resolved'])],
        ]);

        $report->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('info', 'Reporte actualizado.');
    }
}
