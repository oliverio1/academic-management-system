@extends('layouts.app')

@section('title', 'Detalle documento SGC')

@section('content')
<div class="content px-3 mt-3">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">{{ $document->title }}</h4>
                <small class="text-muted">{{ $document->code ?: 'Sin código' }} | Versión {{ $document->version ?: 'N/D' }}</small>
            </div>
            <div>
                <a href="{{ route('coordination.quality.documents.edit', $document) }}" class="btn btn-outline-warning btn-sm">Editar</a>
                <a href="{{ route('coordination.quality.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Proceso:</strong> {{ $document->process->name ?? '-' }}</p>
                    <p><strong>Tipo:</strong> {{ \App\Models\QualityDocument::DOCUMENT_TYPE_LABELS[$document->document_type] ?? $document->document_type }}</p>
                    <p><strong>Responsable:</strong> {{ $document->owner ?: '-' }}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Estatus:</strong> {{ $document->status }} | <strong>Aprobación:</strong> {{ $document->approval_status }}</p>
                    <p><strong>ISO 9001:</strong> {{ $document->iso_9001_clauses ?: '-' }}</p>
                    <p><strong>ISO 21001:</strong> {{ $document->iso_21001_clauses ?: '-' }}</p>
                </div>
            </div>
            <p><strong>Contenido:</strong></p>
            <div class="border rounded p-3" style="white-space: pre-wrap;">{{ $document->content ?: 'Sin contenido.' }}</div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Flujo de aprobación</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('coordination.quality.documents.submit', $document) }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-primary btn-sm">Enviar a aprobación</button>
            </form>
            <form method="POST" action="{{ route('coordination.quality.documents.approve', $document) }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-success btn-sm">Aprobar</button>
            </form>
            <form method="POST" action="{{ route('coordination.quality.documents.reject', $document) }}" class="mt-3">
                @csrf
                <div class="form-group mb-2">
                    <label>Comentario de rechazo</label>
                    <textarea name="rejection_comment" rows="2" class="form-control" required></textarea>
                </div>
                <button class="btn btn-outline-danger btn-sm">Rechazar</button>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Historial de versiones</strong></div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Versión</th>
                        <th>Estatus</th>
                        <th>Resumen</th>
                        <th>Enviada por</th>
                        <th>Aprobada por</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($document->versions as $version)
                        <tr>
                            <td>{{ $version->version }}</td>
                            <td>{{ $version->approval_status }}</td>
                            <td>{{ $version->change_summary ?: '-' }}</td>
                            <td>{{ optional($version->submittedBy)->name ?: '-' }}</td>
                            <td>{{ optional($version->approvedBy)->name ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">Sin versiones.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Bitácora</strong></div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-bordered">
                <thead>
                    <tr><th>Fecha</th><th>Evento</th><th>Usuario</th><th>Notas</th></tr>
                </thead>
                <tbody>
                    @forelse($document->events as $event)
                        <tr>
                            <td>{{ optional($event->created_at)->format('d/m/Y H:i') }}</td>
                            <td>{{ $event->event_type }}</td>
                            <td>{{ optional($event->user)->name ?: '-' }}</td>
                            <td>{{ $event->notes ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">Sin eventos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
