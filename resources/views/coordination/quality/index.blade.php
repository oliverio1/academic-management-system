@extends('layouts.app')

@section('title', 'Sistema de Gestión de Calidad')

@section('content')
<div class="content px-3 mt-3">
    @if(session('info'))
        <div class="alert alert-primary" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Sistema de Gestión de Calidad Educativa</h4>
                <small class="text-muted">Base documental alineada a ISO 9001 e ISO 21001.</small>
            </div>
            <div class="btn-group" role="group">
                <form method="POST" action="{{ route('coordination.quality.bootstrap-iso-base') }}" class="d-inline" onsubmit="return confirm('Esto cargará o actualizará la estructura base ISO 9001 / ISO 21001. ¿Continuar?')">
                    @csrf
                    <button class="btn btn-outline-success btn-sm">Cargar base ISO</button>
                </form>
                <a href="{{ route('coordination.quality.processes.create') }}" class="btn btn-outline-primary btn-sm">+ Nuevo proceso</a>
                <a href="{{ route('coordination.quality.documents.create') }}" class="btn btn-primary btn-sm">+ Nuevo documento</a>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3 mb-2">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $summary['processes'] }}</h3>
                    <p>Procesos SGC</p>
                </div>
                <div class="icon"><i class="fas fa-project-diagram"></i></div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>{{ $summary['documents'] }}</h3>
                    <p>Documentos</p>
                </div>
                <div class="icon"><i class="fas fa-file-alt"></i></div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $summary['approved'] }}</h3>
                    <p>Aprobados</p>
                </div>
                <div class="icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $summary['pending'] }}</h3>
                    <p>Pendientes de aprobación</p>
                </div>
                <div class="icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <strong>Mapa de procesos</strong>
                </div>
                <div class="card-body p-2">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-2 py-2">
                            <a href="{{ route('coordination.quality.index') }}" class="d-block {{ $selectedProcessId === 0 ? 'font-weight-bold text-primary' : 'text-dark' }}">
                                Todos los procesos
                            </a>
                        </li>
                        @forelse($processes as $process)
                            <li class="list-group-item px-2 py-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <a href="{{ route('coordination.quality.index', ['process_id' => $process->id]) }}" class="{{ $selectedProcessId === $process->id ? 'font-weight-bold text-primary' : 'text-dark' }}">
                                            {{ $process->code ? $process->code.' - ' : '' }}{{ $process->name }}
                                        </a>
                                        <div>
                                            <span class="badge badge-light">{{ $processTypeLabels[$process->process_type] ?? $process->process_type }}</span>
                                            @if($process->iso_9001_clauses)
                                                <span class="badge badge-info">9001: {{ $process->iso_9001_clauses }}</span>
                                            @endif
                                            @if($process->iso_21001_clauses)
                                                <span class="badge badge-primary">21001: {{ $process->iso_21001_clauses }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <span class="badge badge-secondary">{{ $process->documents_count }}</span>
                                </div>
                                <div class="mt-2">
                                    <a href="{{ route('coordination.quality.processes.edit', $process) }}" class="btn btn-outline-warning btn-sm">Editar</a>
                                    <form action="{{ route('coordination.quality.processes.destroy', $process) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Eliminar proceso y sus documentos?')">Eliminar</button>
                                    </form>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-muted px-2 py-2">Sin procesos registrados.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-8 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <strong>Información documentada</strong>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Título</th>
                                <th>Tipo</th>
                                <th>Proceso</th>
                                <th>Versión</th>
                                <th>Estatus</th>
                                <th>Aprobación</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($documents as $doc)
                                <tr>
                                    <td>{{ $doc->code ?? '-' }}</td>
                                    <td>
                                        {{ $doc->title }}
                                        <div class="small text-muted">
                                            @if($doc->iso_9001_clauses) ISO 9001: {{ $doc->iso_9001_clauses }} @endif
                                            @if($doc->iso_21001_clauses) | ISO 21001: {{ $doc->iso_21001_clauses }} @endif
                                        </div>
                                    </td>
                                    <td>{{ $documentTypeLabels[$doc->document_type] ?? $doc->document_type }}</td>
                                    <td>{{ $doc->process->name ?? '-' }}</td>
                                    <td>{{ $doc->version ?? '-' }}</td>
                                    <td>
                                        <span class="badge badge-{{ $doc->status === 'active' ? 'success' : ($doc->status === 'obsolete' ? 'danger' : 'warning') }}">
                                            {{ $doc->status }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $doc->approval_status === 'approved' ? 'success' : ($doc->approval_status === 'rejected' ? 'danger' : ($doc->approval_status === 'pending_approval' ? 'info' : 'secondary')) }}">
                                            {{ $doc->approval_status }}
                                        </span>
                                    </td>
                                    <td class="text-nowrap text-right">
                                        <a href="{{ route('coordination.quality.documents.show', $doc) }}" class="btn btn-outline-info btn-sm">Ver</a>
                                        <a href="{{ route('coordination.quality.documents.edit', $doc) }}" class="btn btn-outline-warning btn-sm">Editar</a>
                                        <form action="{{ route('coordination.quality.documents.submit', $doc) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button class="btn btn-outline-primary btn-sm">Enviar</button>
                                        </form>
                                        <form action="{{ route('coordination.quality.documents.approve', $doc) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button class="btn btn-outline-success btn-sm">Aprobar</button>
                                        </form>
                                        <form action="{{ route('coordination.quality.documents.destroy', $doc) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Eliminar documento?')">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">Sin documentos registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($documents->hasPages())
                    <div class="card-footer">
                        {{ $documents->withQueryString()->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
