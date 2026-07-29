@extends('layouts.app')

@section('title', 'Detalle desempeño docente')

@php
    $documentStatus = [
        'delivered' => ['Entregado', 'success'],
        'pending' => ['Pendiente', 'warning'],
        'overdue' => ['Atrasado', 'danger'],
    ];
    $actaStatus = [
        'pending' => ['Pendiente', 'secondary'],
        'draft' => ['Borrador', 'warning'],
        'submitted' => ['Enviada por docente', 'info'],
        'closed' => ['Cerrada', 'primary'],
        'sent' => ['Enviada a control', 'success'],
    ];
    $campusStatus = [
        'on_time' => ['A tiempo', 'success'],
        'late' => ['Retardo', 'warning'],
        'absent' => ['Falta', 'danger'],
        'pending' => ['Pendiente', 'secondary'],
    ];
@endphp

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="mb-1">{{ $teacher->user->name ?? 'Docente' }}</h4>
                        @if($activeCycle)
                            <small class="text-muted">Desempeño docente del ciclo {{ $activeCycle->name }} ({{ $activeCycle->code }})</small>
                        @endif
                    </div>
                    <a href="{{ route('coordination.teacher-performance.index') }}" class="btn btn-secondary btn-sm">
                        Volver
                    </a>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="border rounded p-3 h-100" style="min-height: 320px;">
                                <div class="text-muted small text-uppercase font-weight-bold">General</div>
                                <h3 class="mb-0">{{ number_format($summaryRow['overall'], 1) }}%</h3>
                                <small class="text-muted">{{ $summaryRow['assignments'] }} asignaciones activas</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="border rounded p-3 h-100" style="min-height: 320px;">
                                <div class="text-muted small text-uppercase font-weight-bold">Documentos</div>
                                <h3 class="mb-0">{{ number_format($summaryRow['documents']['percentage'], 1) }}%</h3>
                                <small class="text-muted">{{ $summaryRow['documents']['delivered'] }} de {{ $summaryRow['documents']['total'] }} entregados</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="border rounded p-3 h-100" style="min-height: 320px;">
                                <div class="text-muted small text-uppercase font-weight-bold">Asistencia</div>
                                <h3 class="mb-0">{{ number_format($summaryRow['attendance']['percentage'], 1) }}%</h3>
                                <small class="text-muted">{{ $summaryRow['attendance']['closed'] }} sesiones cerradas</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small text-uppercase font-weight-bold">Evaluación</div>
                                <h3 class="mb-0">{{ number_format($summaryRow['evaluation']['percentage'], 1) }}%</h3>
                                <small class="text-muted">{{ $summaryRow['evaluation']['graded'] }} calificaciones capturadas</small>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table data-datatable="true" class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Grupo</th>
                                    <th>Materia</th>
                                    <th>Documentos</th>
                                    <th>Asistencia</th>
                                    <th>Evaluación</th>
                                    <th>Actas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($assignmentRows as $row)
                                    @php $assignment = $row['assignment']; @endphp
                                    <tr>
                                        <td>{{ $assignment->group?->name ?? '-' }}</td>
                                        <td>
                                            <strong>{{ $assignment->subject?->name ?? '-' }}</strong>
                                            @if($assignment->section_type)
                                                <div class="small text-muted">{{ $assignment->section_display }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ number_format($row['documents']['percentage'], 1) }}%</strong>
                                            <div class="small text-muted">{{ $row['documents']['delivered'] }} / {{ $row['documents']['total'] }}</div>
                                        </td>
                                        <td>
                                            <strong>{{ number_format($row['attendance']['percentage'], 1) }}%</strong>
                                            <div class="small text-muted">{{ $row['attendance']['closed'] }} / {{ $row['attendance']['total'] }} sesiones</div>
                                        </td>
                                        <td>
                                            <strong>{{ number_format($row['evaluation']['percentage'], 1) }}%</strong>
                                            <div class="small text-muted">
                                                {{ $row['evaluation']['activities'] }} actividades, {{ $row['evaluation']['criteria'] }} rubros
                                            </div>
                                        </td>
                                        <td>
                                            @foreach($row['actas'] as $actaRow)
                                                @php [$label, $badge] = $actaStatus[$actaRow['status']] ?? [$actaRow['status'], 'secondary']; @endphp
                                                <span class="badge badge-{{ $badge }} mb-1">
                                                    {{ $actaRow['partial']->name }}: {{ $label }}
                                                </span>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <h5 class="mb-3">Documentos solicitados</h5>
                    <div class="table-responsive mb-4">
                        <table data-datatable="true" class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Materia</th>
                                    <th>Grupo</th>
                                    <th>Documento</th>
                                    <th>Fecha límite</th>
                                    <th>Estatus</th>
                                    <th>Entrega</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($documentRows as $document)
                                    @php [$label, $badge] = $documentStatus[$document['status']] ?? [$document['status'], 'secondary']; @endphp
                                    <tr>
                                        <td>{{ $document['assignment']?->subject?->name ?? '-' }}</td>
                                        <td>{{ $document['assignment']?->group?->name ?? '-' }}</td>
                                        <td>{{ $document['label'] }}</td>
                                        <td>{{ $document['due_date']?->format('d/m/Y') ?? '-' }}</td>
                                        <td><span class="badge badge-{{ $badge }}">{{ $label }}</span></td>
                                        <td>
                                            @if($document['delivered_at'])
                                                {{ $document['delivered_at']->format('d/m/Y H:i') }}
                                            @elseif($document['system_artifact'])
                                                Registrado en sistema
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No hay documentos solicitados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <h5 class="mb-3">Asistencia docente al campus</h5>
                    <div class="table-responsive">
                        <table data-datatable="true" class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Primera clase</th>
                                    <th>Entrada</th>
                                    <th>Salida</th>
                                    <th>Estatus</th>
                                    <th>Notas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($campusAttendanceRows as $record)
                                    @php [$label, $badge] = $campusStatus[$record->status] ?? [$record->status, 'secondary']; @endphp
                                    <tr>
                                        <td>{{ $record->attendance_date?->format('d/m/Y') }}</td>
                                        <td>{{ substr((string) $record->first_class_start_time, 0, 5) ?: '-' }}</td>
                                        <td>{{ substr((string) $record->check_in_time, 0, 5) ?: '-' }}</td>
                                        <td>{{ substr((string) $record->check_out_time, 0, 5) ?: '-' }}</td>
                                        <td><span class="badge badge-{{ $badge }}">{{ $label }}</span></td>
                                        <td>{{ $record->notes ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            No hay registros de asistencia al campus para este profesor.
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
