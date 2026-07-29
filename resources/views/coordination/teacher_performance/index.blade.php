@extends('layouts.app')

@section('title', 'Desempeño docente')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">Desempeño docente</h4>
                        @if($activeCycle)
                            <small class="text-muted">Ciclo activo: {{ $activeCycle->name }} ({{ $activeCycle->code }})</small>
                        @endif
                    </div>
                    <a href="{{ route('coordination.teacher-documents.index') }}" class="btn btn-primary btn-sm">
                        Expediente docente
                    </a>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="border rounded p-3 h-100" style="min-height: 320px;">
                                <div class="text-muted small text-uppercase font-weight-bold">Profesores</div>
                                <h3 class="mb-0">{{ number_format($summary['teachers']) }}</h3>
                                <small class="text-muted">{{ number_format($summary['assignments']) }} asignaciones activas</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="border rounded p-3 h-100" style="min-height: 320px;">
                                <div class="text-muted small text-uppercase font-weight-bold">Documentos</div>
                                <h3 class="mb-0">{{ number_format($summary['documents_percentage'], 1) }}%</h3>
                                <small class="text-muted">promedio de entrega</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small text-uppercase font-weight-bold">Asistencia</div>
                                <h3 class="mb-0">{{ number_format($summary['attendance_capture_percentage'], 1) }}%</h3>
                                <small class="text-muted">sesiones con captura cerrada</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small text-uppercase font-weight-bold">Evaluación</div>
                                <h3 class="mb-0">{{ number_format($summary['evaluation_percentage'], 1) }}%</h3>
                                <small class="text-muted">calificaciones capturadas</small>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table data-datatable="true" class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Profesor</th>
                                    <th class="text-center">Asignaciones</th>
                                    <th>Documentos</th>
                                    <th>Asistencia</th>
                                    <th>Evaluación</th>
                                    <th class="text-center">General</th>
                                    <th>Alertas</th>
                                    <th class="text-center">Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($teacherRows as $row)
                                    <tr>
                                        <td>
                                            <strong>{{ $row['teacher']?->user?->name ?? 'Sin profesor' }}</strong>
                                            <div class="small text-muted">
                                                {{ $row['subjects'] }} materia(s), {{ $row['groups'] }} grupo(s)
                                            </div>
                                        </td>
                                        <td class="text-center">{{ number_format($row['assignments']) }}</td>
                                        <td style="min-width: 180px;">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span>{{ $row['documents']['delivered'] }} / {{ $row['documents']['total'] }}</span>
                                                <strong>{{ number_format($row['documents']['percentage'], 1) }}%</strong>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar {{ $row['documents']['overdue'] > 0 ? 'bg-danger' : 'bg-success' }}"
                                                     style="width: {{ $row['documents']['percentage'] }}%;"></div>
                                            </div>
                                        </td>
                                        <td style="min-width: 180px;">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span>{{ $row['attendance']['closed'] }} / {{ $row['attendance']['total'] }}</span>
                                                <strong>{{ number_format($row['attendance']['percentage'], 1) }}%</strong>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-info" style="width: {{ $row['attendance']['percentage'] }}%;"></div>
                                            </div>
                                        </td>
                                        <td style="min-width: 180px;">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span>{{ $row['evaluation']['graded'] }} / {{ $row['evaluation']['expected'] }}</span>
                                                <strong>{{ number_format($row['evaluation']['percentage'], 1) }}%</strong>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-primary" style="width: {{ $row['evaluation']['percentage'] }}%;"></div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ $row['overall'] >= 90 ? 'badge-success' : ($row['overall'] >= 70 ? 'badge-warning' : 'badge-danger') }}">
                                                {{ number_format($row['overall'], 1) }}%
                                            </span>
                                        </td>
                                        <td>
                                            @if($row['documents']['overdue'] > 0)
                                                <span class="badge badge-danger">{{ $row['documents']['overdue'] }} doc. atrasado(s)</span>
                                            @endif
                                            @if($row['attendance']['percentage'] < 80)
                                                <span class="badge badge-warning">Asistencia incompleta</span>
                                            @endif
                                            @if($row['evaluation']['percentage'] < 80)
                                                <span class="badge badge-warning">Evaluación pendiente</span>
                                            @endif
                                            @if($row['documents']['overdue'] === 0 && $row['attendance']['percentage'] >= 80 && $row['evaluation']['percentage'] >= 80)
                                                <span class="badge badge-success">En orden</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($row['teacher'])
                                                <a href="{{ route('coordination.teacher-performance.show', $row['teacher']) }}" class="btn btn-sm btn-primary">
                                                    Ver
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            No hay profesores con asignaciones en el ciclo activo.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
