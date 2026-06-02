<?php

namespace App\Http\Controllers;

use App\Models\StudentIncidentReport;
use App\Models\User;
use App\Notifications\CoordinatorReviewNotification;
use Illuminate\Http\Request;

class StudentIncidentReportController extends Controller
{
    private const REPORT_TO_OPTIONS = [
        'teacher' => 'Profesor',
        'prefect' => 'Prefecto',
        'coordination' => 'Coordinacion',
        'psychologist' => 'Psicologo',
        'director' => 'Director',
        'other' => 'Otro',
    ];

    private const CATEGORY_OPTIONS = [
        'facilities' => 'Instalaciones',
        'classmates' => 'Companeros',
        'academic' => 'Academico',
        'behavioral' => 'Conductual',
        'other' => 'Otro',
    ];

    public function index()
    {
        $student = auth()->user()->student;

        $reports = StudentIncidentReport::query()
            ->where('student_id', $student->id)
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get();

        return view('student.incident_reports.index', [
            'reports' => $reports,
            'reportToOptions' => self::REPORT_TO_OPTIONS,
            'categoryOptions' => self::CATEGORY_OPTIONS,
        ]);
    }

    public function create()
    {
        return view('student.incident_reports.create', [
            'reportToOptions' => self::REPORT_TO_OPTIONS,
            'categoryOptions' => self::CATEGORY_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'report_to' => ['required', 'in:' . implode(',', array_keys(self::REPORT_TO_OPTIONS))],
            'category' => ['required', 'in:' . implode(',', array_keys(self::CATEGORY_OPTIONS))],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
        ]);

        $report = StudentIncidentReport::create([
            ...$data,
            'student_id' => auth()->user()->student->id,
            'status' => 'open',
        ]);

        $studentName = optional(auth()->user()->student?->user)->name ?? 'Alumno';

        User::role('coordinator')->get()->each(function (User $coordinator) use ($report, $studentName) {
            $coordinator->notify(new CoordinatorReviewNotification([
                'type' => 'student_incident_report',
                'title' => 'Nuevo reporte de alumno',
                'message' => "{$studentName} envio un reporte para revision.",
                'url' => route('coordination.student-incident-reports.index'),
                'meta' => [
                    'report_id' => $report->id,
                    'category' => $report->category,
                ],
            ]));
        });

        return redirect()
            ->route('student.incident-reports.index')
            ->with('info', 'Tu reporte fue enviado correctamente.');
    }

    public function coordinationIndex(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:open,reviewed,resolved'],
            'report_to' => ['nullable', 'in:' . implode(',', array_keys(self::REPORT_TO_OPTIONS))],
            'category' => ['nullable', 'in:' . implode(',', array_keys(self::CATEGORY_OPTIONS))],
        ]);

        $reports = StudentIncidentReport::query()
            ->with(['student.user', 'student.group', 'reviewer'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['report_to'] ?? null, fn ($q, $to) => $q->where('report_to', $to))
            ->when($filters['category'] ?? null, fn ($q, $cat) => $q->where('category', $cat))
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 WHEN status = 'reviewed' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->get();

        return view('coordination.incident_reports.index', [
            'reports' => $reports,
            'filters' => $filters,
            'reportToOptions' => self::REPORT_TO_OPTIONS,
            'categoryOptions' => self::CATEGORY_OPTIONS,
        ]);
    }

    public function updateStatus(Request $request, StudentIncidentReport $incidentReport)
    {
        $data = $request->validate([
            'status' => ['required', 'in:reviewed,resolved'],
        ]);

        $incidentReport->update([
            'status' => $data['status'],
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('info', 'Estatus del reporte actualizado.');
    }
}
