@extends('layouts.app')

@section('title', 'Detalle PNO')

@section('content')
<div class="content px-3 mt-3">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">{{ $document->title }}</h4>
                <small class="text-muted">{{ $document->code ?: 'Sin código' }} | Versión {{ $document->version ?: 'N/D' }}</small>
            </div>
            <a href="{{ route('coordination.quality.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
        </div>
        <div class="card-body">
            <p><strong>Proceso:</strong> {{ $document->process->name ?? '-' }}</p>
            <p><strong>Estatus:</strong> {{ $document->status }} | <strong>Aprobación:</strong> {{ $document->approval_status }}</p>
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
                    @forelse($document->versions as $v)
                        <tr>
                            <td>{{ $v->version }}</td>
                            <td>{{ $v->approval_status }}</td>
                            <td>{{ $v->change_summary ?: '-' }}</td>
                            <td>{{ optional($v->submittedBy)->name ?: '-' }}</td>
                            <td>{{ optional($v->approvedBy)->name ?: '-' }}</td>
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

