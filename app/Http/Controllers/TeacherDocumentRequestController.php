<?php

namespace App\Http\Controllers;

use App\Models\DidacticPlan;
use App\Models\CyclePartial;
use App\Models\PaperExam;
use App\Models\TeacherDocumentContent;
use App\Models\TeacherDocumentRequest;
use App\Models\TeacherDocumentRequestItem;
use App\Models\TeacherDocumentSubmission;
use App\Models\Temario;
use App\Services\CurrentSchoolCycle;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TeacherDocumentRequestController extends Controller
{
    private const EDITABLE_TYPES = [
        'reglamento',
        'criterios_evaluacion',
        'cuadernillo_actividades',
        'guias_parciales',
    ];

    private const GENERATABLE_TYPES = [
    ];

    public function index()
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);

        $activeCampusId = (int) session('active_campus_id', 0);
        $activeCycleId = (int) (app(CurrentSchoolCycle::class)->id(auth()->user(), $activeCampusId) ?? 0);

        $items = TeacherDocumentRequestItem::query()
            ->with([
                'request:id,title,due_date,status,campus_id,school_cycle_id',
                'assignment:id,teacher_id,subject_id,group_id,school_cycle_group_id',
                'assignment.schoolCycleGroup:id,school_cycle_id',
                'assignment.subject:id,name',
                'assignment.group:id,name',
                'editableContent',
                'latestSubmission',
            ])
            ->whereHas('assignment', fn ($q) => $q
                ->where('teacher_id', (int) $teacher->id)
                ->whereHas('schoolCycleGroup', fn ($qq) => $qq->where('school_cycle_id', $activeCycleId)))
            ->whereHas('request', fn ($q) => $q
                ->where('status', 'open')
                ->where('school_cycle_id', $activeCycleId)
                ->when($activeCampusId > 0, fn ($qq) => $qq->where('campus_id', $activeCampusId)))
            ->orderByDesc(
                TeacherDocumentRequest::select('due_date')
                    ->whereColumn('teacher_document_requests.id', 'teacher_document_request_items.request_id')
                    ->limit(1)
            )
            ->get();

        $items = $this->deduplicateDocumentItems(
            $items->map(fn ($item) => $this->withTrackingStatus($item))
        );

        $requests = $items->groupBy('request_id');

        return view('teacher.documents-requests.index', [
            'requests' => $requests,
            'documentTypes' => CoordinationTeacherDocumentRequestController::DOCUMENT_TYPES,
            'generatableTypes' => self::GENERATABLE_TYPES,
            'editableTypes' => self::EDITABLE_TYPES,
        ]);
    }

    public function upload(Request $request, TeacherDocumentRequestItem $item)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);
        abort_unless((int) optional($item->assignment)->teacher_id === (int) $teacher->id, 403);
        abort_unless(optional($item->request)->status === 'open', 422);
        abort_if(now()->toDateString() > optional($item->request)->due_date?->toDateString(), 422, 'La fecha límite ya venció.');

        $data = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $data['document'];
        $path = $file->store('teacher_documents', 'public');

        TeacherDocumentSubmission::create([
            'tenant_id' => (string) $item->tenant_id,
            'item_id' => $item->id,
            'teacher_id' => $teacher->id,
            'uploaded_by' => auth()->id(),
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        return back()->with('success', 'Documento cargado correctamente.');
    }

    public function showPdf(TeacherDocumentSubmission $submission)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);

        $submission->load([
            'item.request.cycle',
            'item.assignment.teacher.user',
            'item.assignment.group',
            'item.assignment.subject',
            'item.assignment.schoolCycleGroup.schoolCycle',
            'item.editableContent',
        ]);

        abort_unless((int) optional($submission->item?->assignment)->teacher_id === (int) $teacher->id, 403);
        abort_unless($submission->mime_type === 'application/pdf', 404);
        abort_unless(Storage::disk('public')->exists($submission->file_path), 404);

        $item = $submission->item;
        $isSystemEditablePdf = Str::startsWith((string) $submission->file_path, 'teacher_documents/editable/');
        $isSystemGeneratedPdf = Str::startsWith((string) $submission->file_path, 'teacher_documents/generated/');

        if ($isSystemEditablePdf && $item?->editableContent && in_array($item->document_type, self::EDITABLE_TYPES, true)) {
            $documentLabel = CoordinationTeacherDocumentRequestController::DOCUMENT_TYPES[$item->document_type] ?? $item->document_type;
            $pdf = SnappyPdf::loadView('teacher.documents-requests.editable_pdf', [
                'item' => $item,
                'content' => $item->editableContent,
                'documentLabel' => $documentLabel,
            ]);
            $this->applyGenericUlaPdfOptions($pdf, $documentLabel);

            return $this->inlinePdfResponse($pdf->output(), $submission->original_name ?: 'documento.pdf');
        }

        if ($isSystemGeneratedPdf && $item?->document_type === 'criterios_evaluacion') {
            $cycleId = (int) ($item->request?->school_cycle_id ?: $item->assignment?->schoolCycleGroup?->school_cycle_id);
            $payload = $this->buildGeneratedDocumentPayload($item, $cycleId);

            if (! $this->missingSourceMessage($item->document_type, $payload)) {
                $documentLabel = CoordinationTeacherDocumentRequestController::DOCUMENT_TYPES[$item->document_type] ?? $item->document_type;
                $pdf = SnappyPdf::loadView('teacher.documents-requests.generated.pdf', [
                    'item' => $item,
                    'documentLabel' => $documentLabel,
                    'payload' => $payload,
                ]);
                $this->applyGenericUlaPdfOptions($pdf, $documentLabel);

                return $this->inlinePdfResponse($pdf->output(), $submission->original_name ?: 'documento.pdf');
            }
        }

        return $this->inlinePdfResponse(
            Storage::disk('public')->get($submission->file_path),
            $submission->original_name ?: 'documento.pdf'
        );
    }

    public function showContentPdf(TeacherDocumentRequestItem $item)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);

        $item->load([
            'request.cycle',
            'assignment.teacher.user',
            'assignment.group',
            'assignment.subject',
            'assignment.schoolCycleGroup.schoolCycle',
            'editableContent',
        ]);

        abort_unless((int) optional($item->assignment)->teacher_id === (int) $teacher->id, 403);
        abort_unless(in_array($item->document_type, self::EDITABLE_TYPES, true), 422, 'Este documento no se edita desde esta vista.');
        abort_unless($item->editableContent?->submitted_at, 404);

        $documentLabel = CoordinationTeacherDocumentRequestController::DOCUMENT_TYPES[$item->document_type] ?? $item->document_type;
        $pdf = SnappyPdf::loadView('teacher.documents-requests.editable_pdf', [
            'item' => $item,
            'content' => $item->editableContent,
            'documentLabel' => $documentLabel,
        ]);
        $this->applyGenericUlaPdfOptions($pdf, $documentLabel);

        $fileName = Str::slug(implode('-', [
            $documentLabel,
            $item->assignment?->subject?->name,
            $item->assignment?->group?->name,
        ])) ?: 'documento';

        return $this->inlinePdfResponse($pdf->output(), $fileName . '.pdf');
    }

    public function editContent(TeacherDocumentRequestItem $item)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);

        $item->load([
            'request:id,title,due_date,status,campus_id,school_cycle_id',
            'assignment.teacher.user',
            'assignment.group',
            'assignment.subject',
            'assignment.schoolCycleGroup.schoolCycle',
            'editableContent',
        ]);

        abort_unless((int) optional($item->assignment)->teacher_id === (int) $teacher->id, 403);
        abort_unless(in_array($item->document_type, self::EDITABLE_TYPES, true), 422, 'Este documento no se edita desde esta vista.');

        $documentLabel = CoordinationTeacherDocumentRequestController::DOCUMENT_TYPES[$item->document_type] ?? $item->document_type;

        return view('teacher.documents-requests.editor', [
            'item' => $item,
            'content' => $item->editableContent,
            'documentLabel' => $documentLabel,
            'defaultHtml' => $this->defaultEditableContent($item),
        ]);
    }

    public function updateContent(Request $request, TeacherDocumentRequestItem $item)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);

        $item->load([
            'request:id,title,due_date,status,campus_id,school_cycle_id',
            'assignment.teacher.user',
            'assignment.group',
            'assignment.subject',
            'assignment.schoolCycleGroup.schoolCycle',
            'editableContent',
        ]);

        abort_unless((int) optional($item->assignment)->teacher_id === (int) $teacher->id, 403);
        abort_unless(optional($item->request)->status === 'open', 422);
        abort_unless(in_array($item->document_type, self::EDITABLE_TYPES, true), 422, 'Este documento no se edita desde esta vista.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'content_html' => ['required', 'string', 'min:20'],
            'action' => ['required', 'in:draft,submit'],
        ]);

        $content = TeacherDocumentContent::query()->updateOrCreate(
            ['item_id' => (int) $item->id],
            [
                'tenant_id' => (string) $item->tenant_id,
                'teacher_id' => (int) $teacher->id,
                'updated_by' => auth()->id(),
                'title' => $data['title'],
                'content_html' => $this->cleanDocumentHtml($data['content_html']),
                'submitted_at' => $data['action'] === 'submit' ? now() : $item->editableContent?->submitted_at,
            ]
        );

        if ($data['action'] === 'submit') {
            $this->submitEditableContentAsPdf(
                $item->fresh(['request', 'assignment.teacher.user', 'assignment.group', 'assignment.subject']),
                $content
            );

            return redirect()
                ->route('teacher.document-requests.index')
                ->with('success', 'Documento guardado y entregado correctamente.');
        }

        return redirect()
            ->route('teacher.document-requests.content.edit', $item)
            ->with('success', 'Borrador guardado correctamente.');
    }

    public function generate(TeacherDocumentRequestItem $item)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);

        $item->load([
            'request.cycle',
            'assignment.teacher.user',
            'assignment.group',
            'assignment.subject',
            'assignment.schoolCycleGroup.schoolCycle',
        ]);

        abort_unless((int) optional($item->assignment)->teacher_id === (int) $teacher->id, 403);
        abort_unless(optional($item->request)->status === 'open', 422);
        abort_if(now()->toDateString() > optional($item->request)->due_date?->toDateString(), 422, 'La fecha limite ya vencio.');
        abort_unless(in_array($item->document_type, self::GENERATABLE_TYPES, true), 422, 'Este documento no se puede generar automaticamente.');

        $assignment = $item->assignment;
        $cycleId = (int) ($item->request?->school_cycle_id ?: $assignment?->schoolCycleGroup?->school_cycle_id);

        $payload = $this->buildGeneratedDocumentPayload($item, $cycleId);
        $missingSourceMessage = $this->missingSourceMessage($item->document_type, $payload);
        if ($missingSourceMessage) {
            return back()->withErrors(['generate' => $missingSourceMessage]);
        }

        $documentLabel = CoordinationTeacherDocumentRequestController::DOCUMENT_TYPES[$item->document_type] ?? $item->document_type;

        $pdf = SnappyPdf::loadView('teacher.documents-requests.generated.pdf', [
            'item' => $item,
            'documentLabel' => $documentLabel,
            'payload' => $payload,
        ])
            ->setPaper('letter')
            ->setOption('encoding', 'UTF-8')
            ->setOption('enable-local-file-access', true);

        if ($item->document_type === 'criterios_evaluacion') {
            $this->applyGenericUlaPdfOptions($pdf, $documentLabel);
        }

        $baseName = Str::slug(implode('-', [
            $documentLabel,
            $assignment?->subject?->name,
            $assignment?->group?->name,
            now()->format('YmdHis'),
        ]));
        $fileName = ($baseName ?: 'documento-generado') . '.pdf';
        $path = 'teacher_documents/generated/' . $fileName;

        Storage::disk('public')->put($path, $pdf->output());

        TeacherDocumentSubmission::create([
            'tenant_id' => (string) $item->tenant_id,
            'item_id' => $item->id,
            'teacher_id' => $teacher->id,
            'uploaded_by' => auth()->id(),
            'file_path' => $path,
            'original_name' => $fileName,
            'mime_type' => 'application/pdf',
            'file_size' => Storage::disk('public')->size($path),
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        return back()->with('success', 'Documento generado y entregado correctamente.');
    }

    public function cloneReglamento(TeacherDocumentRequestItem $item)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);
        abort_unless((int) optional($item->assignment)->teacher_id === (int) $teacher->id, 403);
        abort_unless($item->document_type === 'reglamento', 422, 'Solo se puede clonar reglamento.');

        $source = $item->latestSubmission()->first();
        abort_if(! $source, 422, 'Primero debes cargar un reglamento para clonar.');

        $activeCampusId = (int) session('active_campus_id', 0);
        $activeCycleId = (int) (app(CurrentSchoolCycle::class)->id(auth()->user(), $activeCampusId) ?? 0);

        $targets = TeacherDocumentRequestItem::query()
            ->where('id', '!=', $item->id)
            ->where('document_type', 'reglamento')
            ->whereHas('assignment', fn ($q) => $q
                ->where('teacher_id', (int) $teacher->id)
                ->whereHas('schoolCycleGroup', fn ($qq) => $qq->where('school_cycle_id', $activeCycleId)))
            ->whereHas('request', fn ($q) => $q
                ->where('status', 'open')
                ->where('school_cycle_id', $activeCycleId)
                ->when($activeCampusId > 0, fn ($qq) => $qq->where('campus_id', $activeCampusId)))
            ->whereDoesntHave('submissions')
            ->get();

        $cloned = 0;
        foreach ($targets as $target) {
            TeacherDocumentSubmission::create([
                'tenant_id' => (string) $target->tenant_id,
                'item_id' => $target->id,
                'teacher_id' => $teacher->id,
                'uploaded_by' => auth()->id(),
                'file_path' => $source->file_path,
                'original_name' => $source->original_name,
                'mime_type' => $source->mime_type,
                'file_size' => $source->file_size,
                'submitted_at' => now(),
                'status' => 'submitted',
            ]);
            $cloned++;
        }

        return back()->with('success', "Reglamento clonado a {$cloned} materia(s) pendiente(s).");
    }

    public function cloneCriteria(Request $request, TeacherDocumentRequestItem $item)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);

        $item->load(['assignment', 'editableContent']);

        abort_unless((int) optional($item->assignment)->teacher_id === (int) $teacher->id, 403);
        abort_unless($item->document_type === 'criterios_evaluacion', 422, 'Solo se pueden clonar criterios de evaluacion.');

        $data = $request->validate([
            'scope' => ['required', 'in:same_subject,all_subjects'],
        ]);

        $source = $item->latestSubmission()->first();
        abort_if(! $source, 422, 'Primero debes entregar los criterios para poder clonarlos.');

        $activeCampusId = (int) session('active_campus_id', 0);
        $activeCycleId = (int) (app(CurrentSchoolCycle::class)->id(auth()->user(), $activeCampusId) ?? 0);
        $sourceAssignment = $item->assignment;

        $targets = TeacherDocumentRequestItem::query()
            ->with(['assignment', 'editableContent'])
            ->where('id', '!=', $item->id)
            ->where('document_type', 'criterios_evaluacion')
            ->whereHas('assignment', function ($q) use ($teacher, $sourceAssignment, $data, $activeCycleId) {
                $q->where('teacher_id', (int) $teacher->id);

                if ($data['scope'] === 'same_subject') {
                    $q->where('subject_id', (int) $sourceAssignment->subject_id);
                }

                $q->whereHas('schoolCycleGroup', fn ($qq) => $qq->where('school_cycle_id', $activeCycleId));
            })
            ->whereHas('request', fn ($q) => $q
                ->where('status', 'open')
                ->where('school_cycle_id', $activeCycleId)
                ->when($activeCampusId > 0, fn ($qq) => $qq->where('campus_id', $activeCampusId)))
            ->whereDoesntHave('submissions')
            ->get();

        $cloned = 0;
        foreach ($targets as $target) {
            if ($item->editableContent) {
                TeacherDocumentContent::query()->updateOrCreate(
                    ['item_id' => (int) $target->id],
                    [
                        'tenant_id' => (string) $target->tenant_id,
                        'teacher_id' => (int) $teacher->id,
                        'updated_by' => auth()->id(),
                        'title' => $item->editableContent->title,
                        'content_html' => $item->editableContent->content_html,
                        'submitted_at' => now(),
                    ]
                );
            }

            TeacherDocumentSubmission::create([
                'tenant_id' => (string) $target->tenant_id,
                'item_id' => $target->id,
                'teacher_id' => $teacher->id,
                'uploaded_by' => auth()->id(),
                'file_path' => $source->file_path,
                'original_name' => $source->original_name,
                'mime_type' => $source->mime_type,
                'file_size' => $source->file_size,
                'submitted_at' => now(),
                'status' => 'submitted',
            ]);

            $cloned++;
        }

        $scopeLabel = $data['scope'] === 'same_subject' ? 'la misma materia' : 'todas tus materias';

        return back()->with('success', "Criterios clonados a {$cloned} documento(s) pendiente(s) de {$scopeLabel}.");
    }

    private function buildGeneratedDocumentPayload(TeacherDocumentRequestItem $item, int $cycleId): array
    {
        $assignment = $item->assignment;
        $temario = Temario::query()
            ->with('points')
            ->where('subject_id', (int) $assignment->subject_id)
            ->orderByDesc('id')
            ->first();

        $plan = DidacticPlan::query()
            ->with(['items.temarioPoint', 'items.fieldTrainingPoint'])
            ->where('teaching_assignment_id', (int) $assignment->id)
            ->when($cycleId > 0, fn ($query) => $query->where('school_cycle_id', $cycleId))
            ->orderByDesc('id')
            ->first();

        $cycle = $item->request?->cycle;
        $partials = $cycle
            ? $cycle->partials()->where('is_active', true)->orderBy('sort_order')->get()
            : collect();

        $exams = $this->examsForAssignment($item, $cycleId);

        return [
            'temario' => $temario,
            'temarioTree' => $this->temarioTree($temario),
            'plan' => $plan,
            'partials' => $partials,
            'exams' => $exams,
            'evaluationRows' => $this->evaluationRows($plan),
            'planItemsByPartial' => $this->planItemsByPartial($plan, $partials),
        ];
    }

    private function submitEditableContentAsPdf(TeacherDocumentRequestItem $item, TeacherDocumentContent $content): void
    {
        $documentLabel = CoordinationTeacherDocumentRequestController::DOCUMENT_TYPES[$item->document_type] ?? $item->document_type;

        $pdf = SnappyPdf::loadView('teacher.documents-requests.editable_pdf', [
            'item' => $item,
            'content' => $content,
            'documentLabel' => $documentLabel,
        ]);
        $this->applyGenericUlaPdfOptions($pdf, $documentLabel);

        $baseName = Str::slug(implode('-', [
            $documentLabel,
            $item->assignment?->subject?->name,
            $item->assignment?->group?->name,
            now()->format('YmdHis'),
        ]));
        $fileName = ($baseName ?: 'documento-editable') . '.pdf';
        $path = 'teacher_documents/editable/' . $fileName;

        Storage::disk('public')->put($path, $pdf->output());

        TeacherDocumentSubmission::create([
            'tenant_id' => (string) $item->tenant_id,
            'item_id' => $item->id,
            'teacher_id' => (int) $content->teacher_id,
            'uploaded_by' => auth()->id(),
            'file_path' => $path,
            'original_name' => $fileName,
            'mime_type' => 'application/pdf',
            'file_size' => Storage::disk('public')->size($path),
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);
    }

    private function inlinePdfResponse(string $contents, string $fileName)
    {
        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($fileName) . '"',
        ]);
    }

    private function applyGenericUlaPdfOptions($pdf, string $documentLabel): void
    {
        $pdf->setPaper('letter')
            ->setOption('encoding', 'UTF-8')
            ->setOption('enable-local-file-access', true)
            ->setOption('margin-top', '22mm')
            ->setOption('margin-right', '26mm')
            ->setOption('margin-bottom', '24mm')
            ->setOption('margin-left', '26mm')
            ->setOption('footer-line', true)
            ->setOption('footer-left', mb_strtoupper($documentLabel))
            ->setOption('footer-right', '[page]')
            ->setOption('footer-font-size', 9)
            ->setOption('footer-spacing', 6);
    }

    private function defaultEditableContent(TeacherDocumentRequestItem $item): string
    {
        if ($item->document_type === 'reglamento') {
            return '<h2>Reglamento de clase</h2>'
                . '<p>El presente reglamento establece los acuerdos de trabajo para la asignatura.</p>'
                . '<ol>'
                . '<li>Asistir puntualmente a clase y participar con respeto.</li>'
                . '<li>Entregar actividades y tareas en las fechas establecidas.</li>'
                . '<li>Mantener una comunicacion respetuosa con companeros y docente.</li>'
                . '<li>Cuidar los materiales, espacios y recursos de la institucion.</li>'
                . '<li>Atender las indicaciones academicas y de seguridad durante las sesiones.</li>'
                . '</ol>';
        }

        if ($item->document_type === 'criterios_evaluacion') {
            $assignment = $item->assignment;
            $cycleId = (int) ($item->request?->school_cycle_id ?? $assignment?->schoolCycleGroup?->school_cycle_id ?? 0);
            $plan = DidacticPlan::query()
                ->where('school_cycle_id', $cycleId)
                ->whereHas('assignment', function ($query) use ($assignment) {
                    $query->where('teacher_id', (int) $assignment->teacher_id)
                        ->where('school_cycle_group_id', (int) $assignment->school_cycle_group_id)
                        ->where('subject_id', (int) $assignment->subject_id);
                })
                ->orderByDesc('id')
                ->first();

            if ($plan && $plan->evaluation_instruments) {
                $items = collect(preg_split('/\r\n|\r|\n/u', (string) $plan->evaluation_instruments) ?: [])
                    ->map(fn ($row) => trim((string) $row))
                    ->filter()
                    ->map(fn ($row) => '<li>' . e($row) . '</li>')
                    ->implode('');

                return '<h2>Criterios de evaluacion</h2><ul>' . $items . '</ul>';
            }

            return '<h2>Criterios de evaluacion</h2>'
                . '<p>Describe los elementos por evaluar, porcentajes, condiciones de entrega y criterios de acreditacion.</p>'
                . '<table border="1" cellpadding="6" cellspacing="0" style="width:100%; border-collapse:collapse;">'
                . '<thead><tr><th>Elemento</th><th>Porcentaje</th><th>Descripcion</th></tr></thead>'
                . '<tbody><tr><td>Actividades</td><td></td><td></td></tr><tr><td>Examen</td><td></td><td></td></tr></tbody>'
                . '</table>';
        }

        if (in_array($item->document_type, ['cuadernillo_actividades', 'guias_parciales'], true)) {
            $documentLabel = CoordinationTeacherDocumentRequestController::DOCUMENT_TYPES[$item->document_type] ?? 'Documento';

            return '<h2>' . e($documentLabel) . '</h2>'
                . '<p>Captura el contenido que compartirás con tus alumnos. Puedes incluir indicaciones, actividades, fechas, criterios y materiales de apoyo.</p>';
        }

        return '<p></p>';
    }

    private function cleanDocumentHtml(string $html): string
    {
        $html = preg_replace('/<\s*(script|style|iframe|object|embed)[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html) ?? $html;
        $html = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/is', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;

        return trim($html);
    }

    private function withTrackingStatus(TeacherDocumentRequestItem $item): TeacherDocumentRequestItem
    {
        $dueDate = optional($item->request)->due_date?->toDateString();
        $latest = $item->latestSubmission;
        $editableContent = $item->editableContent;
        $systemArtifact = $this->systemArtifactLabel($item);

        if ($latest || $editableContent?->submitted_at || $systemArtifact) {
            $status = 'delivered';
        } elseif ($dueDate && $dueDate < now()->toDateString()) {
            $status = 'overdue';
        } else {
            $status = 'pending';
        }

        $item->tracking_status = $status;
        $item->system_artifact_label = $systemArtifact;
        $item->exam_partial_id = $this->isPartialExamDocumentType((string) $item->document_type)
            ? $this->partialIdForExamDocument(
                (int) ($item->request?->school_cycle_id ?? $item->assignment?->schoolCycleGroup?->school_cycle_id ?? 0),
                (string) $item->document_type
            )
            : null;

        return $item;
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
            $cycleId = (int) ($item->request?->school_cycle_id ?? $assignment->schoolCycleGroup?->school_cycle_id ?? 0);
            $partialId = $this->partialIdForExamDocument($cycleId, (string) $item->document_type);
            if ($cycleId <= 0 || $partialId <= 0) {
                return null;
            }

            $exam = PaperExam::query()
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

    private function missingSourceMessage(string $documentType, array $payload): ?string
    {
        $temario = $payload['temario'] ?? null;
        $plan = $payload['plan'] ?? null;
        $exams = $payload['exams'] ?? collect();
        $evaluationRows = $payload['evaluationRows'] ?? [];

        if ($documentType === 'temario' && ! $temario) {
            return 'No hay temario cargado para esta materia.';
        }

        if ($documentType === 'criterios_evaluacion' && count($evaluationRows) === 0) {
            return 'No hay criterios de evaluacion capturados en la planeacion.';
        }

        if (in_array($documentType, ['cuadernillo_actividades', 'guias_parciales'], true)
            && (! $plan || $plan->items->isEmpty())) {
            return 'No hay planeacion capturada para generar este documento.';
        }

        if ($documentType === 'examenes' && $exams->isEmpty()) {
            return 'No hay examenes capturados para esta materia, grupo y ciclo.';
        }

        return null;
    }

    private function examsForAssignment(TeacherDocumentRequestItem $item, int $cycleId)
    {
        $assignment = $item->assignment;

        return PaperExam::query()
            ->with([
                'partial',
                'examQuestions.question.options',
                'examQuestions.question.matchingPairs',
                'examQuestions.question.fillBlanks',
            ])
            ->where('school_cycle_id', $cycleId)
            ->whereHas('assignment', function ($query) use ($assignment) {
                $query->where('school_cycle_group_id', (int) $assignment->school_cycle_group_id)
                    ->where('subject_id', (int) $assignment->subject_id);
            })
            ->orderBy('cycle_partial_id')
            ->orderByDesc('id')
            ->get();
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

    private function evaluationRows(?DidacticPlan $plan): array
    {
        if (! $plan || ! $plan->evaluation_instruments) {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/u', (string) $plan->evaluation_instruments) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all();
    }

    private function planItemsByPartial(?DidacticPlan $plan, $partials): array
    {
        if (! $plan || $partials->isEmpty()) {
            return [];
        }

        $items = $plan->items;

        return $partials->mapWithKeys(function ($partial) use ($items) {
            $partialItems = $items
                ->filter(function ($item) use ($partial) {
                    if (! $item->start_date) {
                        return false;
                    }

                    return $item->start_date->betweenIncluded($partial->start_date, $partial->end_date)
                        || ($item->end_date && $item->end_date->betweenIncluded($partial->start_date, $partial->end_date));
                })
                ->values();

            return [$partial->id => $partialItems];
        })->all();
    }
}
