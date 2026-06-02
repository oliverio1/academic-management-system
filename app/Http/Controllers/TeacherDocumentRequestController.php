<?php

namespace App\Http\Controllers;

use App\Models\TeacherDocumentRequest;
use App\Models\TeacherDocumentRequestItem;
use App\Models\TeacherDocumentSubmission;
use Illuminate\Http\Request;

class TeacherDocumentRequestController extends Controller
{
    public function index()
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);

        $activeCampusId = (int) session('active_campus_id', 0);

        $items = TeacherDocumentRequestItem::query()
            ->with([
                'request:id,title,due_date,status,campus_id',
                'assignment:id,teacher_id,subject_id,group_id',
                'assignment.subject:id,name',
                'assignment.group:id,name',
                'latestSubmission',
            ])
            ->whereHas('assignment', fn ($q) => $q->where('teacher_id', (int) $teacher->id))
            ->whereHas('request', fn ($q) => $q->where('status', 'open')->when($activeCampusId > 0, fn ($qq) => $qq->where('campus_id', $activeCampusId)))
            ->orderByDesc(
                TeacherDocumentRequest::select('due_date')
                    ->whereColumn('teacher_document_requests.id', 'teacher_document_request_items.request_id')
                    ->limit(1)
            )
            ->get();

        $requests = $items->groupBy('request_id');

        return view('teacher.documents-requests.index', [
            'requests' => $requests,
            'documentTypes' => CoordinationTeacherDocumentRequestController::DOCUMENT_TYPES,
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

    public function cloneReglamento(TeacherDocumentRequestItem $item)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);
        abort_unless((int) optional($item->assignment)->teacher_id === (int) $teacher->id, 403);
        abort_unless($item->document_type === 'reglamento', 422, 'Solo se puede clonar reglamento.');

        $source = $item->latestSubmission()->first();
        abort_if(! $source, 422, 'Primero debes cargar un reglamento para clonar.');

        $activeCampusId = (int) session('active_campus_id', 0);

        $targets = TeacherDocumentRequestItem::query()
            ->where('id', '!=', $item->id)
            ->where('document_type', 'reglamento')
            ->whereHas('assignment', fn ($q) => $q->where('teacher_id', (int) $teacher->id))
            ->whereHas('request', fn ($q) => $q->where('status', 'open')->when($activeCampusId > 0, fn ($qq) => $qq->where('campus_id', $activeCampusId)))
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
}
