<?php

namespace App\Http\Controllers;

use App\Models\QualityDocument;
use App\Models\QualityDocumentEvent;
use App\Models\QualityDocumentVersion;
use App\Models\QualityProcess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoordinationQualityController extends Controller
{
    public function index(Request $request)
    {
        $selectedProcessId = (int) $request->input('process_id', 0);

        $processes = QualityProcess::query()
            ->withCount('documents')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $documents = QualityDocument::query()
            ->with(['process', 'currentVersion'])
            ->when($selectedProcessId > 0, fn ($q) => $q->where('quality_process_id', $selectedProcessId))
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('coordination.quality.index', compact('processes', 'documents', 'selectedProcessId'));
    }

    public function createProcess()
    {
        $parents = QualityProcess::query()->orderBy('sort_order')->orderBy('name')->get();
        return view('coordination.quality.process_form', [
            'process' => new QualityProcess(),
            'parents' => $parents,
            'mode' => 'create',
        ]);
    }

    public function storeProcess(Request $request)
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:quality_processes,id'],
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        QualityProcess::create([
            'parent_id' => $data['parent_id'] ?? null,
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 1),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('coordination.quality.index')->with('info', 'Proceso creado correctamente.');
    }

    public function editProcess(QualityProcess $process)
    {
        $parents = QualityProcess::query()
            ->where('id', '!=', $process->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('coordination.quality.process_form', compact('process', 'parents') + ['mode' => 'edit']);
    }

    public function updateProcess(Request $request, QualityProcess $process)
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:quality_processes,id'],
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['parent_id']) && (int) $data['parent_id'] === (int) $process->id) {
            return back()->withErrors(['parent_id' => 'Un proceso no puede ser padre de sí mismo.']);
        }

        $process->update([
            'parent_id' => $data['parent_id'] ?? null,
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 1),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('coordination.quality.index')->with('info', 'Proceso actualizado correctamente.');
    }

    public function destroyProcess(QualityProcess $process)
    {
        $process->delete();
        return redirect()->route('coordination.quality.index')->with('info', 'Proceso eliminado correctamente.');
    }

    public function createDocument()
    {
        $processes = QualityProcess::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        return view('coordination.quality.document_form', [
            'document' => new QualityDocument(),
            'processes' => $processes,
            'mode' => 'create',
        ]);
    }

    public function storeDocument(Request $request)
    {
        $data = $request->validate([
            'quality_process_id' => ['required', 'integer', 'exists:quality_processes,id'],
            'code' => ['nullable', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:draft,active,obsolete'],
            'effective_date' => ['nullable', 'date'],
            'review_date' => ['nullable', 'date'],
            'owner' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'change_summary' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($data) {
            $document = QualityDocument::create([
                'quality_process_id' => $data['quality_process_id'],
                'code' => $data['code'] ?? null,
                'title' => $data['title'],
                'version' => $data['version'] ?? '1.0',
                'status' => $data['status'],
                'approval_status' => 'draft',
                'effective_date' => $data['effective_date'] ?? null,
                'review_date' => $data['review_date'] ?? null,
                'owner' => $data['owner'] ?? null,
                'content' => $data['content'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $version = QualityDocumentVersion::create([
                'quality_document_id' => $document->id,
                'version' => $document->version ?: '1.0',
                'content' => $document->content,
                'change_summary' => $data['change_summary'] ?? 'Creación inicial',
                'approval_status' => 'draft',
            ]);

            $document->update(['current_version_id' => $version->id]);

            QualityDocumentEvent::create([
                'quality_document_id' => $document->id,
                'quality_document_version_id' => $version->id,
                'user_id' => auth()->id(),
                'event_type' => 'created',
                'notes' => 'PNO creado en borrador.',
            ]);
        });

        return redirect()->route('coordination.quality.index')->with('info', 'PNO creado correctamente.');
    }

    public function editDocument(QualityDocument $document)
    {
        $processes = QualityProcess::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $document->load(['currentVersion']);
        return view('coordination.quality.document_form', compact('document', 'processes') + ['mode' => 'edit']);
    }

    public function updateDocument(Request $request, QualityDocument $document)
    {
        $data = $request->validate([
            'quality_process_id' => ['required', 'integer', 'exists:quality_processes,id'],
            'code' => ['nullable', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:draft,active,obsolete'],
            'effective_date' => ['nullable', 'date'],
            'review_date' => ['nullable', 'date'],
            'owner' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'change_summary' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($document, $data) {
            $document->update([
                'quality_process_id' => $data['quality_process_id'],
                'code' => $data['code'] ?? null,
                'title' => $data['title'],
                'version' => $data['version'] ?? $document->version,
                'status' => $data['status'],
                'effective_date' => $data['effective_date'] ?? null,
                'review_date' => $data['review_date'] ?? null,
                'owner' => $data['owner'] ?? null,
                'content' => $data['content'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);

            $version = QualityDocumentVersion::create([
                'quality_document_id' => $document->id,
                'version' => $document->version ?: '1.0',
                'content' => $document->content,
                'change_summary' => $data['change_summary'] ?? 'Actualización de contenido',
                'approval_status' => 'draft',
            ]);

            $document->update([
                'current_version_id' => $version->id,
                'approval_status' => 'draft',
            ]);

            QualityDocumentEvent::create([
                'quality_document_id' => $document->id,
                'quality_document_version_id' => $version->id,
                'user_id' => auth()->id(),
                'event_type' => 'updated',
                'notes' => 'Se creó nueva versión en borrador.',
            ]);
        });

        return redirect()->route('coordination.quality.index')->with('info', 'PNO actualizado correctamente.');
    }

    public function destroyDocument(QualityDocument $document)
    {
        $document->delete();
        return redirect()->route('coordination.quality.index')->with('info', 'PNO eliminado correctamente.');
    }

    public function showDocument(QualityDocument $document)
    {
        $document->load(['process', 'versions.submittedBy', 'versions.approvedBy', 'events.user']);
        return view('coordination.quality.document_show', compact('document'));
    }

    public function submitForApproval(QualityDocument $document)
    {
        $version = $document->currentVersion ?: $document->versions()->latest('id')->first();
        abort_if(! $version, 422, 'No hay versión para enviar.');

        DB::transaction(function () use ($document, $version) {
            $version->update([
                'approval_status' => 'pending_approval',
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
                'rejection_comment' => null,
            ]);
            $document->update(['approval_status' => 'pending_approval']);
            QualityDocumentEvent::create([
                'quality_document_id' => $document->id,
                'quality_document_version_id' => $version->id,
                'user_id' => auth()->id(),
                'event_type' => 'submitted_for_approval',
                'notes' => 'Versión enviada a aprobación.',
            ]);
        });

        return back()->with('info', 'PNO enviado a aprobación.');
    }

    public function approveDocument(QualityDocument $document)
    {
        $version = $document->currentVersion ?: $document->versions()->latest('id')->first();
        abort_if(! $version, 422, 'No hay versión para aprobar.');

        DB::transaction(function () use ($document, $version) {
            $version->update([
                'approval_status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejection_comment' => null,
            ]);
            $document->update([
                'approval_status' => 'approved',
                'status' => 'active',
            ]);
            QualityDocumentEvent::create([
                'quality_document_id' => $document->id,
                'quality_document_version_id' => $version->id,
                'user_id' => auth()->id(),
                'event_type' => 'approved',
                'notes' => 'Versión aprobada.',
            ]);
        });

        return back()->with('info', 'PNO aprobado.');
    }

    public function rejectDocument(Request $request, QualityDocument $document)
    {
        $data = $request->validate([
            'rejection_comment' => ['required', 'string', 'max:2000'],
        ]);
        $version = $document->currentVersion ?: $document->versions()->latest('id')->first();
        abort_if(! $version, 422, 'No hay versión para rechazar.');

        DB::transaction(function () use ($document, $version, $data) {
            $version->update([
                'approval_status' => 'rejected',
                'rejection_comment' => $data['rejection_comment'],
            ]);
            $document->update(['approval_status' => 'rejected']);
            QualityDocumentEvent::create([
                'quality_document_id' => $document->id,
                'quality_document_version_id' => $version->id,
                'user_id' => auth()->id(),
                'event_type' => 'rejected',
                'notes' => $data['rejection_comment'],
            ]);
        });

        return back()->with('info', 'PNO rechazado y devuelto a revisión.');
    }
}
