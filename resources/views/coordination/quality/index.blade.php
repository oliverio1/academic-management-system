@extends('layouts.app')

@section('title', 'Control de calidad')

@section('content')
<div class="content px-3 mt-3">
    @if(session('info'))
        <div class="alert alert-primary" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Sistema de control de calidad (ISO)</h4>
            <div class="btn-group" role="group">
                <a href="{{ route('coordination.quality.processes.create') }}" class="btn btn-outline-primary btn-sm">+ Nuevo proceso</a>
                <a href="{{ route('coordination.quality.documents.create') }}" class="btn btn-primary btn-sm">+ Nuevo PNO</a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <strong>Directorio de procesos</strong>
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
                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="{{ route('coordination.quality.index', ['process_id' => $process->id]) }}" class="{{ $selectedProcessId === $process->id ? 'font-weight-bold text-primary' : 'text-dark' }}">
                                        {{ $process->code ? $process->code.' - ' : '' }}{{ $process->name }}
                                    </a>
                                    <span class="badge badge-secondary">{{ $process->documents_count }}</span>
                                </div>
                                <div class="mt-2">
                                    <a href="{{ route('coordination.quality.processes.edit', $process) }}" class="btn btn-outline-warning btn-sm">Editar</a>
                                    <form action="{{ route('coordination.quality.processes.destroy', $process) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Eliminar proceso y sus PNOs?')">Eliminar</button>
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
                    <strong>PNOs</strong>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Título</th>
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
                                    <td>{{ $doc->title }}</td>
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
                                            <button class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Eliminar PNO?')">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Sin PNOs registrados.</td>
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
