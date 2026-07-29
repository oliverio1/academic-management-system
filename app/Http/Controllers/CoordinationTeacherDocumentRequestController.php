<?php

namespace App\Http\Controllers;

use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\CyclePartial;
use App\Models\PaperExam;
use App\Models\TeacherDocumentSubmission;
use App\Models\TeachingAssignment;
use App\Models\Teacher;
use App\Models\TeacherDocumentRequest;
use App\Models\TeacherDocumentRequestItem;
use App\Services\CurrentSchoolCycle;
use App\Models\DidacticPlan;
use App\Models\Temario;
use Barryvdh\Snappy\Facades\SnappyPdf;
use App\Services\TeacherDocumentChecklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CoordinationTeacherDocumentRequestController extends Controller
{
    public const DOCUMENT_TYPES = [
        'reglamento' => 'Reglamento de clase',
        'examen_parcial_1' => 'Examen primer parcial (con respuestas)',
        'examen_parcial_2' => 'Examen segundo parcial (con respuestas)',
        'examen_parcial_3' => 'Examen tercer parcial (con respuestas)',
        'examen_parcial_4' => 'Examen cuarto parcial (con respuestas)',
        'examenes' => 'Examenes por parcial (legado)',
        'criterios_evaluacion' => 'Criterios de evaluacion',
        'cuadernillo_actividades' => 'Cuadernillo de actividades',
        'guias_parciales' => 'Guias de estudio parciales',
        'planeacion' => 'Planeacion',
        'temario' => 'Temario',
        'examen_especial' => 'Examen especial',
        'actividad_especial_alumno' => 'Actividad especial para alumno',
        'programa_operativo' => 'Programa operativo',
        'guias' => 'Guias',
        'instrumentos_evaluacion' => 'Instrumentos de evaluacion',
        'evidencias' => 'Evidencias',
        'otros' => 'Otros',
    ];

    public function index()
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $today = now()->toDateString();
        $activeCycle = $this->activeCycle($activeCampusId);

        if ($activeCycle) {
            $this->ensureAutomaticChecklistForCycle($activeCycle, $activeCampusId);
        }

        $items = $this->documentItemsQuery($activeCampusId, $activeCycle?->id)
            ->get()
            ->map(fn ($item) => $this->withTrackingStatus($item, $today));
        $items = $this->deduplicateDocumentItems($items);

        $teacherRows = $items
            ->groupBy(fn ($item) => (int) ($item->assignment?->teacher_id ?? 0))
            ->filter(fn ($group, $teacherId) => (int) $teacherId > 0)
            ->map(function ($teacherItems, $teacherId) {
                $teacher = $teacherItems->first()->assignment?->teacher;
                $total = $teacherItems->count();
                $delivered = $teacherItems->filter(fn ($item) => $item->tracking_status === 'delivered')->count();
                $overdue = $teacherItems->filter(fn ($item) => $item->tracking_status === 'overdue')->count();

                return [
                    'teacher' => $teacher,
                    'total' => $total,
                    'delivered' => $delivered,
                    'pending' => $teacherItems->filter(fn ($item) => $item->tracking_status === 'pending')->count(),
                    'overdue' => $overdue,
                    'percentage' => $total > 0 ? round(($delivered / $total) * 100, 1) : 0,
                    'assignments_count' => $teacherItems
                        ->pluck('teaching_assignment_id')
                        ->filter()
                        ->unique()
                        ->count(),
                    'has_overdue' => $overdue > 0,
                ];
            })
            ->sortBy(fn ($row) => mb_strtolower((string) ($row['teacher']?->user?->name ?? '')))
            ->values();

        return view('coordination.teacher-documents.index', [
            'activeCycle' => $activeCycle,
            'teacherRows' => $teacherRows,
        ]);
    }

    public function showTeacher(Teacher $teacher)
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $today = now()->toDateString();
        $activeCycle = $this->activeCycle($activeCampusId);

        if ($activeCycle) {
            $this->ensureAutomaticChecklistForCycle($activeCycle, $activeCampusId, $teacher->id);
        }

        $items = $this->documentItemsQuery($activeCampusId, $activeCycle?->id)
            ->whereHas('assignment', fn ($query) => $query->where('teacher_id', (int) $teacher->id))
            ->get()
            ->map(fn ($item) => $this->withTrackingStatus($item, $today));
        $items = $this->deduplicateDocumentItems($items);

        $assignments = $items
            ->groupBy('teaching_assignment_id')
            ->map(function ($assignmentItems) {
                $assignment = $assignmentItems->first()->assignment;
                $total = $assignmentItems->count();
                $delivered = $assignmentItems->filter(fn ($item) => $item->tracking_status === 'delivered')->count();

                return [
                    'assignment' => $assignment,
                    'items' => $assignmentItems->sortBy('document_type')->values(),
                    'percentage' => $total > 0 ? round(($delivered / $total) * 100, 1) : 0,
                    'overdue' => $assignmentItems->filter(fn ($item) => $item->tracking_status === 'overdue')->count(),
                    'pending' => $assignmentItems->filter(fn ($item) => $item->tracking_status === 'pending')->count(),
                    'delivered' => $delivered,
                    'total' => $total,
                ];
            })
            ->sortBy(fn ($row) => mb_strtolower((string) ($row['assignment']?->subject?->name ?? '')))
            ->values();

        return view('coordination.teacher-documents.show-teacher', [
            'activeCycle' => $activeCycle,
            'teacher' => $teacher->loadMissing('user'),
            'assignments' => $assignments,
            'documentTypes' => self::DOCUMENT_TYPES,
        ]);
    }

    public function tracking()
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $today = now()->toDateString();

        $items = TeacherDocumentRequestItem::query()
            ->with([
                'request:id,title,due_date,status,campus_id',
                'assignment:id,teacher_id,subject_id,group_id,school_cycle_group_id',
                'assignment.schoolCycleGroup:id,school_cycle_id',
                'assignment.subject:id,name',
                'assignment.group:id,name',
                'assignment.teacher:id,user_id',
                'assignment.teacher.user:id,name',
                'editableContent',
                'latestSubmission',
            ])
            ->whereHas('request', fn ($q) => $q->when($activeCampusId > 0, fn ($qq) => $qq->where('campus_id', $activeCampusId)))
            ->get()
            ->map(fn ($item) => $this->withTrackingStatus($item, $today));
        $items = $this->deduplicateDocumentItems($items)
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

    public function documentPdf(TeacherDocumentRequestItem $item)
    {
        $item->load([
            'request:id,title,due_date,status,campus_id,school_cycle_id',
            'assignment:id,teacher_id,subject_id,group_id,school_cycle_group_id',
            'assignment.schoolCycleGroup:id,school_cycle_id,campus_id',
            'assignment.teacher.user',
            'assignment.group',
            'assignment.subject.temarios.points',
            'editableContent',
            'latestSubmission',
        ]);

        $this->authorizeItemCampus($item);

        $latest = $item->latestSubmission;
        if ($latest) {
            return $this->submissionPdfResponse($latest);
        }

        if ($item->document_type === 'temario') {
            $temario = Temario::query()
                ->with('points')
                ->where('subject_id', (int) $item->assignment?->subject_id)
                ->whereHas('points')
                ->orderByDesc('id')
                ->first();

            abort_unless($temario, 404);

            $documentLabel = self::DOCUMENT_TYPES[$item->document_type] ?? $item->document_type;
            $pdf = SnappyPdf::loadView('teacher.documents-requests.generated.pdf', [
                'item' => $item,
                'documentLabel' => $documentLabel,
                'payload' => [
                    'temario' => $temario,
                    'temarioTree' => $this->temarioTree($temario),
                    'plan' => null,
                    'partials' => collect(),
                    'exams' => collect(),
                    'evaluationRows' => [],
                    'planItemsByPartial' => [],
                ],
            ])
                ->setPaper('letter')
                ->setOption('encoding', 'UTF-8')
                ->setOption('enable-local-file-access', true);

            return $pdf->inline('TEMARIO_' . preg_replace('/\s+/', '_', (string) $item->assignment?->subject?->name) . '.pdf');
        }

        if ($item->document_type === 'planeacion') {
            $plan = $this->planForDocumentItem($item);
            abort_unless($plan, 404);

            return redirect()->route('coordination.teacher-documents.plans.pdf', $plan);
        }

        if ($this->isPartialExamDocumentType((string) $item->document_type)) {
            $exam = $this->examForDocumentItem($item);
            abort_unless($exam, 404);

            return redirect()->route('coordination.paper-exams.pdf', $exam);
        }

        abort(404);
    }

    public function create()
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $activeCycle = $this->activeCycle($activeCampusId);

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
        $activeCycle = $this->activeCycle($activeCampusId);

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

    private function activeCycle(int $activeCampusId): ?SchoolCycle
    {
        return app(CurrentSchoolCycle::class)->get(auth()->user(), $activeCampusId);
    }

    private function documentItemsQuery(int $activeCampusId, ?int $cycleId)
    {
        return TeacherDocumentRequestItem::query()
            ->with([
                'request:id,title,due_date,status,campus_id,school_cycle_id',
                'assignment:id,teacher_id,subject_id,group_id,school_cycle_group_id',
                'assignment.subject:id,name',
                'assignment.group:id,name',
                'assignment.teacher:id,user_id',
                'assignment.teacher.user:id,name',
                'editableContent',
                'latestSubmission',
            ])
            ->whereHas('assignment', function ($query) use ($cycleId, $activeCampusId) {
                $query->where('is_active', true)
                    ->whereHas('schoolCycleGroup', function ($cycleGroup) use ($cycleId, $activeCampusId) {
                        $cycleGroup->where('is_active', true)
                            ->when($cycleId, fn ($q) => $q->where('school_cycle_id', $cycleId))
                            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId));
                    });
            })
            ->whereHas('request', function ($query) use ($activeCampusId, $cycleId) {
                $query->where('status', 'open')
                    ->when($cycleId, fn ($q) => $q->where('school_cycle_id', $cycleId))
                    ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId));
            });
    }

    private function withTrackingStatus(TeacherDocumentRequestItem $item, string $today): TeacherDocumentRequestItem
    {
        $dueDate = optional($item->request)->due_date?->toDateString();
        $latest = $item->latestSubmission;
        $hasSubmissionPdf = $latest
            && (
                Storage::disk('public')->exists((string) $latest->file_path)
                || $item->editableContent
            );
        $systemArtifact = $this->systemArtifactLabel($item);

        if ($hasSubmissionPdf || $systemArtifact) {
            $status = 'delivered';
        } elseif ($dueDate && $dueDate < $today) {
            $status = 'overdue';
        } else {
            $status = 'pending';
        }

        $item->tracking_status = $status;
        $item->system_artifact_label = $systemArtifact;
        $item->document_pdf_url = $status === 'delivered' ? $this->documentPdfUrl($item) : null;

        return $item;
    }

    private function documentPdfUrl(TeacherDocumentRequestItem $item): ?string
    {
        if ($item->latestSubmission) {
            $fileExists = Storage::disk('public')->exists((string) $item->latestSubmission->file_path);
            if ($fileExists || $item->editableContent) {
                return route('coordination.teacher-documents.items.pdf', $item);
            }

            return null;
        }

        if ($item->document_type === 'temario' && ($item->system_artifact_label ?? null)) {
            return route('coordination.teacher-documents.items.pdf', $item);
        }

        if ($item->document_type === 'planeacion' && ($item->system_artifact_label ?? null)) {
            return route('coordination.teacher-documents.items.pdf', $item);
        }

        if ($this->isPartialExamDocumentType((string) $item->document_type) && ($item->system_artifact_label ?? null)) {
            return route('coordination.teacher-documents.items.pdf', $item);
        }

        return null;
    }

    private function deduplicateDocumentItems($items)
    {
        return $items
            ->groupBy(fn ($item) => implode('|', [
                (int) ($item->request_id ?? 0),
                (int) ($item->assignment?->teacher_id ?? 0),
                (int) ($item->assignment?->school_cycle_group_id ?? 0),
                (int) ($item->assignment?->subject_id ?? 0),
                (string) ($item->document_type ?? ''),
            ]))
            ->map(function ($duplicates) {
                return $duplicates
                    ->sortBy(fn ($item) => match ($item->tracking_status) {
                        'delivered' => 0,
                        'pending' => 1,
                        default => 2,
                    })
                    ->first();
            })
            ->values();
    }

    private function systemArtifactLabel(TeacherDocumentRequestItem $item): ?string
    {
        $assignment = $item->assignment;
        if (! $assignment) {
            return null;
        }

        if ($item->document_type === 'temario') {
            $exists = Temario::query()
                ->where('subject_id', (int) $assignment->subject_id)
                ->whereHas('points')
                ->exists();

            return $exists ? 'Registrado en temarios' : null;
        }

        if ($item->document_type === 'planeacion') {
            $cycleId = (int) ($item->request?->school_cycle_id ?? $assignment->schoolCycleGroup?->school_cycle_id ?? 0);
            $exists = DidacticPlan::query()
                ->where('school_cycle_id', $cycleId)
                ->whereHas('assignment', function ($query) use ($assignment) {
                    $query->where('teacher_id', (int) $assignment->teacher_id)
                        ->where('school_cycle_group_id', (int) $assignment->school_cycle_group_id)
                        ->where('subject_id', (int) $assignment->subject_id);
                })
                ->exists();

            return $exists ? 'Registrada en planeaciones' : null;
        }

        if ($this->isPartialExamDocumentType((string) $item->document_type)) {
            $exam = $this->examForDocumentItem($item);

            return $exam ? 'Examen configurado (' . $exam->exam_questions_count . ' pregunta(s))' : null;
        }

        return null;
    }

    private function isPartialExamDocumentType(string $documentType): bool
    {
        return (bool) preg_match('/^examen_parcial_[1-4]$/', $documentType);
    }

    private function partialIdForExamDocument(int $cycleId, string $documentType): int
    {
        if ($cycleId <= 0 || ! preg_match('/^examen_parcial_([1-4])$/', $documentType, $matches)) {
            return 0;
        }

        $partialNumber = (int) $matches[1];

        return (int) CyclePartial::query()
            ->where('school_cycle_id', $cycleId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->skip($partialNumber - 1)
            ->value('id');
    }

    private function planForDocumentItem(TeacherDocumentRequestItem $item): ?DidacticPlan
    {
        $assignment = $item->assignment;
        if (! $assignment) {
            return null;
        }

        $cycleId = (int) ($item->request?->school_cycle_id ?? $assignment->schoolCycleGroup?->school_cycle_id ?? 0);

        return DidacticPlan::query()
            ->where('school_cycle_id', $cycleId)
            ->whereHas('assignment', function ($query) use ($assignment) {
                $query->where('teacher_id', (int) $assignment->teacher_id)
                    ->where('school_cycle_group_id', (int) $assignment->school_cycle_group_id)
                    ->where('subject_id', (int) $assignment->subject_id);
            })
            ->orderByDesc('id')
            ->first();
    }

    private function examForDocumentItem(TeacherDocumentRequestItem $item): ?PaperExam
    {
        $assignment = $item->assignment;
        if (! $assignment) {
            return null;
        }

        $cycleId = (int) ($item->request?->school_cycle_id ?? $assignment->schoolCycleGroup?->school_cycle_id ?? 0);
        $partialId = $this->partialIdForExamDocument($cycleId, (string) $item->document_type);
        if ($cycleId <= 0 || $partialId <= 0) {
            return null;
        }

        return PaperExam::query()
            ->withCount('examQuestions')
            ->where('school_cycle_id', $cycleId)
            ->where('cycle_partial_id', $partialId)
            ->whereHas('examQuestions')
            ->whereHas('assignment', function ($query) use ($assignment) {
                $query->where('teacher_id', (int) $assignment->teacher_id)
                    ->where('school_cycle_group_id', (int) $assignment->school_cycle_group_id)
                    ->where('subject_id', (int) $assignment->subject_id)
                    ->where('group_id', (int) $assignment->group_id);
            })
            ->orderByDesc('id')
            ->first();
    }

    private function submissionPdfResponse(TeacherDocumentSubmission $submission)
    {
        abort_unless($submission->mime_type === 'application/pdf', 404);

        if (! Storage::disk('public')->exists($submission->file_path)) {
            $item = $submission->item ?: TeacherDocumentRequestItem::query()
                ->with(['assignment.teacher.user', 'assignment.group', 'assignment.subject', 'editableContent'])
                ->find($submission->item_id);

            if ($item?->editableContent) {
                $documentLabel = self::DOCUMENT_TYPES[$item->document_type] ?? $item->document_type;
                $pdf = SnappyPdf::loadView('teacher.documents-requests.editable_pdf', [
                    'item' => $item,
                    'content' => $item->editableContent,
                    'documentLabel' => $documentLabel,
                ])
                    ->setPaper('letter')
                    ->setOption('encoding', 'UTF-8')
                    ->setOption('enable-local-file-access', true)
                    ->setOption('footer-center', '[page] de [toPage]')
                    ->setOption('footer-font-size', 6);

                return $pdf->inline($submission->original_name ?: 'documento.pdf');
            }

            abort(404);
        }

        return response(Storage::disk('public')->get($submission->file_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($submission->original_name ?: 'documento.pdf') . '"',
        ]);
    }

    private function authorizeItemCampus(TeacherDocumentRequestItem $item): void
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        if ($activeCampusId <= 0) {
            return;
        }

        $requestCampusId = (int) ($item->request?->campus_id ?? 0);
        $assignmentCampusId = (int) ($item->assignment?->schoolCycleGroup?->campus_id ?? 0);

        abort_unless($requestCampusId === $activeCampusId || $assignmentCampusId === $activeCampusId, 403);
    }

    private function temarioTree(?Temario $temario): array
    {
        if (! $temario) {
            return [];
        }

        $points = $temario->points->values();
        $units = [];
        $currentUnit = null;
        $currentTopic = null;

        foreach ($points as $point) {
            $row = [
                'id' => $point->id,
                'label' => (string) $point->label,
                'type' => (string) ($point->type ?: 'conceptual'),
                'content' => (string) $point->content,
                'children' => [],
            ];

            if ((int) $point->level === 1) {
                $units[] = $row;
                $currentUnit = count($units) - 1;
                $currentTopic = null;
                continue;
            }

            if ((int) $point->level === 2 && $currentUnit !== null) {
                $units[$currentUnit]['children'][] = $row;
                $currentTopic = count($units[$currentUnit]['children']) - 1;
                continue;
            }

            if ($currentUnit !== null && $currentTopic !== null) {
                $units[$currentUnit]['children'][$currentTopic]['children'][] = $row;
            } elseif ($currentUnit !== null) {
                $units[$currentUnit]['children'][] = $row;
            }
        }

        return $units;
    }

    private function ensureAutomaticChecklistForCycle(SchoolCycle $cycle, int $activeCampusId, ?int $teacherId = null): void
    {
        $assignments = TeachingAssignment::query()
            ->with(['schoolCycleGroup.schoolCycle', 'subject', 'group'])
            ->where('is_active', true)
            ->when($teacherId, fn ($query) => $query->where('teacher_id', $teacherId))
            ->whereHas('schoolCycleGroup', function ($query) use ($cycle, $activeCampusId) {
                $query->where('school_cycle_id', (int) $cycle->id)
                    ->where('is_active', true)
                    ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId));
            })
            ->get();

        $service = app(TeacherDocumentChecklistService::class);
        foreach ($assignments as $assignment) {
            $service->ensureForAssignment($assignment);
        }
    }
}
