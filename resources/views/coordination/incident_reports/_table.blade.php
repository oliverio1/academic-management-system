<form method="GET" action="{{ route('coordination.student-incident-reports.index') }}" class="coordination-report-filters">
    <div class="row">
        <div class="col-md-4 mb-2">
            <select name="status" class="form-control">
                <option value="">Todos los estatus</option>
                <option value="open" {{ ($filters['status'] ?? '') === 'open' ? 'selected' : '' }}>Pendiente</option>
                <option value="reviewed" {{ ($filters['status'] ?? '') === 'reviewed' ? 'selected' : '' }}>Revisado</option>
                <option value="resolved" {{ ($filters['status'] ?? '') === 'resolved' ? 'selected' : '' }}>Resuelto</option>
            </select>
        </div>
        <div class="col-md-4 mb-2">
            <select name="report_to" class="form-control">
                <option value="">Todos los destinatarios</option>
                @foreach($reportToOptions as $value => $label)
                    <option value="{{ $value }}" {{ ($filters['report_to'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 mb-2">
            <select name="category" class="form-control">
                <option value="">Todas las categorias</option>
                @foreach($categoryOptions as $value => $label)
                    <option value="{{ $value }}" {{ ($filters['category'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <button class="btn btn-outline-primary">Filtrar</button>
    <a href="{{ route('coordination.student-incident-reports.index') }}" class="btn btn-outline-secondary">Limpiar</a>
</form>

<div class="table-responsive coordination-report-table-wrap">
    <table data-datatable="true" class="table table-hover mb-0 reports-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Alumno</th>
                <th>Grupo</th>
                <th>Destinatario</th>
                <th>Categoria</th>
                <th>Asunto</th>
                <th>Descripcion</th>
                <th>Estatus</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reports as $report)
                @php
                    $rowClass = match ($report->status) {
                        'open' => 'report-row-open-medium',
                        'reviewed' => 'report-row-reviewed',
                        'resolved' => 'report-row-resolved',
                        default => '',
                    };
                @endphp
                <tr class="{{ $rowClass }}">
                    <td>{{ $report->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $report->student->user->name }}</td>
                    <td>{{ $report->student->group->name ?? '-' }}</td>
                    <td>{{ $reportToOptions[$report->report_to] ?? $report->report_to }}</td>
                    <td>{{ $categoryOptions[$report->category] ?? $report->category }}</td>
                    <td>{{ $report->subject }}</td>
                    <td style="max-width: 320px; white-space: normal;">{{ $report->description }}</td>
                    <td>
                        @if($report->status === 'open')
                            <span class="badge report-badge report-status-open">Pendiente</span>
                        @elseif($report->status === 'reviewed')
                            <span class="badge report-badge report-status-reviewed">Revisado</span>
                        @else
                            <span class="badge report-badge report-status-resolved">Resuelto</span>
                        @endif
                    </td>
                    <td>
                        <div class="report-actions">
                            @if($report->status === 'open')
                                <form method="POST" action="{{ route('coordination.student-incident-reports.update-status', $report) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="reviewed">
                                    <button class="btn btn-sm btn-outline-primary">Marcar revisado</button>
                                </form>
                            @endif

                            @if($report->status !== 'resolved')
                                <form method="POST" action="{{ route('coordination.student-incident-reports.update-status', $report) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="resolved">
                                    <button class="btn btn-sm btn-outline-success">Marcar resuelto</button>
                                </form>
                            @else
                                <small class="text-muted">Cerrado</small>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No hay reportes registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
