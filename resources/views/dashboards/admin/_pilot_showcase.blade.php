@php
    $summary = $pilot['summary'] ?? [];
    $documents = $pilot['documents'] ?? ['total' => 0, 'delivered' => 0, 'pending' => 0, 'overdue' => 0, 'percentage' => 0];
    $partials = $pilot['partials'] ?? collect();
    $teacherRows = $pilot['teacherRows'] ?? collect();
    $caseStatus = $pilot['caseStatus'] ?? collect();
    $caseLabels = [
        'new' => 'Nuevo',
        'reviewed' => 'Revisado',
        'assigned' => 'Asignado',
        'in_progress' => 'En seguimiento',
        'waiting_response' => 'Esperando respuesta',
        'resolved' => 'Resuelto',
        'closed' => 'Cerrado',
    ];
@endphp

<style>
    .pilot-band {
        border: 1px solid #d8e2ea;
        border-radius: 6px;
        background: #f8fafc;
        margin-bottom: 1.5rem;
    }
    .pilot-kpi {
        border: 1px solid #d8e2ea;
        border-radius: 6px;
        background: #fff;
        min-height: 112px;
    }
    .pilot-kpi .label {
        color: #64748b;
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        font-weight: 700;
    }
    .pilot-kpi .value {
        color: #0f172a;
        font-size: 1.8rem;
        line-height: 1;
        font-weight: 800;
    }
    .pilot-kpi .hint {
        color: #64748b;
        font-size: .78rem;
    }
    .pilot-link {
        border: 0;
        border-radius: 4px;
        color: #fff;
        font-weight: 700;
    }
    .pilot-link:hover {
        color: #fff;
        filter: brightness(.95);
    }
    .pilot-table th {
        background: #e9eef3;
        color: #334155;
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        border-bottom: 1px solid #d8e2ea;
    }
    .pilot-table td {
        vertical-align: middle;
    }
</style>

<div class="pilot-band p-3 p-lg-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3">
        <div>
            <h5 class="mb-1">Piloto operativo listo para demostracion</h5>
            <div class="text-muted">
                Datos ficticios conectados a asistencia, evaluacion, expediente docente, reportes y casos escolares.
            </div>
        </div>
        <div class="mt-3 mt-lg-0">
            <a href="{{ route('coordination.teacher-documents.index') }}" class="btn pilot-link mr-2 mb-2" style="background:#0070C0;">
                Expediente docente
            </a>
            <a href="{{ route('coordination.reports.index') }}" class="btn pilot-link mr-2 mb-2" style="background:#334155;">
                Reportes
            </a>
            <a href="{{ route('coordination.school-cases.index') }}" class="btn pilot-link mb-2" style="background:#0f766e;">
                Casos escolares
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="pilot-kpi p-3">
                <div class="label">Alumnos</div>
                <div class="value">{{ number_format($summary['students'] ?? 0) }}</div>
                <div class="hint">{{ number_format($summary['groups'] ?? 0) }} grupos activos</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="pilot-kpi p-3">
                <div class="label">Profesores</div>
                <div class="value">{{ number_format($summary['teachers'] ?? 0) }}</div>
                <div class="hint">{{ number_format($summary['subjects'] ?? 0) }} materias</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="pilot-kpi p-3">
                <div class="label">Sesiones</div>
                <div class="value">{{ number_format($summary['closed_sessions'] ?? 0) }}</div>
                <div class="hint">con asistencia cerrada</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="pilot-kpi p-3">
                <div class="label">Registros</div>
                <div class="value">{{ number_format($summary['class_attendance'] ?? 0) }}</div>
                <div class="hint">asistencias por clase</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="pilot-kpi p-3">
                <div class="label">Evaluacion</div>
                <div class="value">{{ number_format($summary['grades'] ?? 0) }}</div>
                <div class="hint">{{ number_format($summary['activities'] ?? 0) }} actividades</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="pilot-kpi p-3">
                <div class="label">Atencion</div>
                <div class="value">{{ number_format(($summary['reports'] ?? 0) + ($summary['cases'] ?? 0)) }}</div>
                <div class="hint">reportes y casos</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="bg-white border rounded p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Expediente docente</strong>
                    <span class="badge badge-primary">{{ $documents['percentage'] }}%</span>
                </div>
                <div class="progress mb-3" style="height: 12px;">
                    <div class="progress-bar" role="progressbar" style="width: {{ $documents['percentage'] }}%; background:#0070C0;"></div>
                </div>
                <div class="row text-center">
                    <div class="col">
                        <div class="h5 mb-0">{{ number_format($documents['delivered']) }}</div>
                        <small class="text-muted">Entregados</small>
                    </div>
                    <div class="col">
                        <div class="h5 mb-0">{{ number_format($documents['pending']) }}</div>
                        <small class="text-muted">Pendientes</small>
                    </div>
                    <div class="col">
                        <div class="h5 mb-0 text-danger">{{ number_format($documents['overdue']) }}</div>
                        <small class="text-muted">Atrasados</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-3">
            <div class="bg-white border rounded p-3 h-100">
                <strong>Ventanas de captura</strong>
                <div class="mt-2">
                    @forelse($partials as $partial)
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <div>
                                <div class="font-weight-bold">{{ $partial['name'] }}</div>
                                <small class="text-muted">{{ $partial['range'] }}</small>
                            </div>
                            <small class="text-muted text-right">{{ $partial['deadline'] }}</small>
                        </div>
                    @empty
                        <div class="text-muted mt-2">No hay parciales activos configurados.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-3 mb-3">
            <div class="bg-white border rounded p-3 h-100">
                <strong>Casos escolares</strong>
                <div class="mt-2">
                    @forelse($caseStatus as $status => $total)
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span>{{ $caseLabels[$status] ?? $status }}</span>
                            <strong>{{ number_format($total) }}</strong>
                        </div>
                    @empty
                        <div class="text-muted mt-2">Sin casos registrados.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white border rounded p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>Docentes para seguimiento</strong>
            <a href="{{ route('coordination.teacher-documents.index') }}" class="small">Ver expediente completo</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm pilot-table mb-0">
                <thead>
                    <tr>
                        <th>Profesor</th>
                        <th>Asignaciones</th>
                        <th>Entregado</th>
                        <th>Pendiente</th>
                        <th>Atrasado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teacherRows as $row)
                        <tr>
                            <td>{{ $row['teacher']?->user?->name ?? 'Sin profesor' }}</td>
                            <td>{{ number_format($row['assignments']) }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1 mr-2" style="height: 8px;">
                                        <div class="progress-bar" style="width: {{ $row['percentage'] }}%; background:#0070C0;"></div>
                                    </div>
                                    <strong>{{ $row['percentage'] }}%</strong>
                                </div>
                            </td>
                            <td>{{ number_format($row['pending']) }}</td>
                            <td>
                                <span class="{{ $row['overdue'] > 0 ? 'text-danger font-weight-bold' : 'text-muted' }}">
                                    {{ number_format($row['overdue']) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-muted">No hay solicitudes de documentos para el ciclo activo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
