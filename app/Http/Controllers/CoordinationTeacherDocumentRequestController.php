<?php

namespace App\Http\Controllers;

use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\TeachingAssignment;
use App\Models\TeacherDocumentRequest;
use App\Models\TeacherDocumentRequestItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoordinationTeacherDocumentRequestController extends Controller
{
    public const DOCUMENT_TYPES = [
        'reglamento' => 'Reglamento',
        'planeacion' => 'Planeación',
        'programa_operativo' => 'Programa operativo',
        'examenes' => 'Exámenes',
        'guias' => 'Guías',
        'instrumentos_evaluacion' => 'Instrumentos de evaluación',
        'evidencias' => 'Evidencias',
        'otros' => 'Otros',
    ];

    public function index()
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $today = now()->toDateString();

        $items = TeacherDocumentRequestItem::query()
            ->with([
                'request:id,title,due_date,status,campus_id',
                'assignment:id,teacher_id,subject_id,group_id',
                'assignment.subject:id,name',
                'assignment.group:id,name',
                'assignment.teacher:id,user_id',
                'assignment.teacher.user:id,name',
                'latestSubmission',
            ])
            ->whereHas('request', fn ($q) => $q->when($activeCampusId > 0, fn ($qq) => $qq->where('campus_id', $activeCampusId)))
            ->get()
            ->map(function ($item) use ($today) {
                $dueDate = optional($item->request)->due_date?->toDateString();
                $latest = $item->latestSubmission;

                if ($latest) {
                    $status = 'delivered';
                } elseif ($dueDate && $dueDate < $today) {
                    $status = 'overdue';
                } else {
                    $status = 'pending';
                }

                $item->tracking_status = $status;
                return $item;
            })
            ->sortBy([
                fn ($item) => match($item->tracking_status) {
                    'overdue' => 0,
                    'pending' => 1,
                    default => 2,
                },
                fn ($item) => optional($item->request)->due_date?->toDateString() ?? '9999-12-31',
            ])
            ->values();

        return view('coordination.teacher-documents.index', [
            'items' => $items,
            'documentTypes' => self::DOCUMENT_TYPES,
        ]);
    }

    public function create()
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $activeCycle = SchoolCycle::query()
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->orderByDesc('start_date')
            ->first();

        $assignments = collect();
        if ($activeCycle) {
            $cycleGroupIds = SchoolCycleGroup::query()
                ->where('school_cycle_id', (int) $activeCycle->id)
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $assignments = TeachingAssignment::query()
                ->with(['teacher.user:id,name', 'subject:id,name', 'group:id,name'])
                ->whereIn('school_cycle_group_id', $cycleGroupIds)
                ->where('is_active', true)
                ->orderBy('teacher_id')
                ->orderBy('subject_id')
                ->get();
        }

        return view('coordination.teacher-documents.create', [
            'activeCycle' => $activeCycle,
            'assignments' => $assignments,
            'documentTypes' => self::DOCUMENT_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $activeCycle = SchoolCycle::query()
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->orderByDesc('start_date')
            ->first();

        abort_if(! $activeCycle, 422, 'No hay ciclo activo para el campus seleccionado.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'due_date' => ['required', 'date'],
            'assignment_ids' => ['required', 'array', 'min:1'],
            'assignment_ids.*' => ['integer', 'exists:teaching_assignments,id'],
            'document_types' => ['required', 'array', 'min:1'],
            'document_types.*' => ['string'],
            'student_visible_types' => ['nullable', 'array'],
            'student_visible_types.*' => ['string'],
        ]);

        $allowedCycleGroupIds = SchoolCycleGroup::query()
            ->where('school_cycle_id', (int) $activeCycle->id)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $allowedAssignments = TeachingAssignment::query()
            ->whereIn('school_cycle_group_id', $allowedCycleGroupIds)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selectedAssignments = collect($data['assignment_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        if ($selectedAssignments->diff($allowedAssignments)->isNotEmpty()) {
            return back()->withInput()->withErrors([
                'assignment_ids' => 'Incluye asignaciones fuera del ciclo/campus activo.',
            ]);
        }

        $selectedDocumentTypes = collect($data['document_types'])
            ->filter(fn ($type) => array_key_exists($type, self::DOCUMENT_TYPES))
            ->unique()
            ->values();
        $studentVisibleTypes = collect($data['student_visible_types'] ?? [])
            ->filter(fn ($type) => array_key_exists($type, self::DOCUMENT_TYPES))
            ->unique()
            ->values();

        if ($selectedDocumentTypes->isEmpty()) {
            return back()->withInput()->withErrors([
                'document_types' => 'Selecciona al menos un tipo de documento válido.',
            ]);
        }

        DB::transaction(function () use ($data, $activeCampusId, $activeCycle, $selectedAssignments, $selectedDocumentTypes, $studentVisibleTypes) {
            $docRequest = TeacherDocumentRequest::create([
                'campus_id' => $activeCampusId,
                'school_cycle_id' => (int) $activeCycle->id,
                'title' => $data['title'],
                'instructions' => $data['instructions'] ?? null,
                'due_date' => $data['due_date'],
                'status' => 'open',
                'created_by' => auth()->id(),
            ]);

            foreach ($selectedAssignments as $assignmentId) {
                foreach ($selectedDocumentTypes as $documentType) {
                    TeacherDocumentRequestItem::create([
                        'request_id' => $docRequest->id,
                        'teaching_assignment_id' => $assignmentId,
                        'document_type' => $documentType,
                        'is_required' => true,
                        'is_student_visible' => $studentVisibleTypes->contains($documentType),
                    ]);
                }
            }
        });

        return redirect()->route('coordination.teacher-documents.index')
            ->with('success', 'Solicitud de expediente creada correctamente.');
    }

    public function tracking()
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $today = now()->toDateString();

        $items = TeacherDocumentRequestItem::query()
            ->with([
                'request:id,title,due_date,status,campus_id',
                'assignment:id,teacher_id,subject_id,group_id',
                'assignment.subject:id,name',
                'assignment.group:id,name',
                'assignment.teacher:id,user_id',
                'assignment.teacher.user:id,name',
                'latestSubmission',
            ])
            ->whereHas('request', fn ($q) => $q->when($activeCampusId > 0, fn ($qq) => $qq->where('campus_id', $activeCampusId)))
            ->get()
            ->map(function ($item) use ($today) {
                $dueDate = optional($item->request)->due_date?->toDateString();
                $latest = $item->latestSubmission;

                if ($latest) {
                    $status = 'delivered';
                } elseif ($dueDate && $dueDate < $today) {
                    $status = 'overdue';
                } else {
                    $status = 'pending';
                }

                $item->tracking_status = $status;
                return $item;
            })
            ->sortBy([
                fn ($item) => match($item->tracking_status) {
                    'overdue' => 0,
                    'pending' => 1,
                    default => 2,
                },
                fn ($item) => optional($item->request)->due_date?->toDateString() ?? '9999-12-31',
            ])
            ->values();

        return view('coordination.teacher-documents.tracking', [
            'items' => $items,
            'documentTypes' => self::DOCUMENT_TYPES,
        ]);
    }

    public function updateItemVisibility(Request $request, TeacherDocumentRequestItem $item)
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $belongsToCampus = $item->request()
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->exists();
        abort_unless($belongsToCampus, 404);

        $data = $request->validate([
            'is_student_visible' => ['required', 'boolean'],
        ]);

        $item->update([
            'is_student_visible' => (bool) $data['is_student_visible'],
        ]);

        return back()->with('success', 'Visibilidad para alumnos actualizada.');
    }
}
