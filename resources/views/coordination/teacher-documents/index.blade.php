@extends('layouts.app')

@section('title', 'Expediente docente')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">Expediente docente</h4>
                        @if($activeCycle)
                            <small class="text-muted">Ciclo activo: {{ $activeCycle->name }}</small>
                        @endif
                    </div>
                    <div>
                        <a href="{{ route('coordination.teacher-documents.create') }}" class="btn btn-primary btn-sm">Solicitud especial</a>
                    </div>
                </div>
                <div class="card-body table-responsive p-3">
                    <table data-datatable="true" class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Docente</th>
                                <th>Asignaciones</th>
                                <th>Entrega</th>
                                <th>Alerta</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($teacherRows as $row)
                            <tr>
                                <td>{{ $row['teacher']?->user?->name ?? '-' }}</td>
                                <td>{{ $row['assignments_count'] }}</td>
                                <td style="min-width: 220px;">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>{{ $row['delivered'] }} de {{ $row['total'] }}</span>
                                        <strong>{{ $row['percentage'] }}%</strong>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar {{ $row['has_overdue'] ? 'bg-danger' : 'bg-success' }}"
                                             role="progressbar"
                                             style="width: {{ $row['percentage'] }}%;"
                                             aria-valuenow="{{ $row['percentage'] }}"
                                             aria-valuemin="0"
                                             aria-valuemax="100"></div>
                                    </div>
                                </td>
                                <td>
                                    @if($row['has_overdue'])
                                        <span class="badge badge-danger">{{ $row['overdue'] }} atrasado(s)</span>
                                    @elseif($row['pending'] > 0)
                                        <span class="badge badge-warning">{{ $row['pending'] }} pendiente(s)</span>
                                    @else
                                        <span class="badge badge-success">Completo</span>
                                    @endif
                                </td>
                                <td>
                                    @if($row['teacher'])
                                        <a href="{{ route('coordination.teacher-documents.teachers.show', $row['teacher']) }}" class="btn btn-sm btn-outline-primary">Ver detalle</a>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">No hay profesores con asignaciones en el ciclo activo.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
