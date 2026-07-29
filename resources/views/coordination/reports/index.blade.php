@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
@include('coordination.reports._table_styles')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0">Reportes</h3>
                        <small class="text-muted">Bandeja central de reportes docentes, alumnos, prefectura y coordinacion.</small>
                    </div>
                    <a href="{{ route('coordination.reports.create') }}" class="btn btn-primary btn-sm">Levantar reporte</a>
                </div>
                <div class="card-body">
                    @if(session('info'))
                        <div class="alert alert-success">{{ session('info') }}</div>
                    @endif

                    <form method="GET" action="{{ route('coordination.reports.index') }}" class="coordination-report-filters">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <select name="status" class="form-control">
                                    <option value="">Todos los estatus</option>
                                    <option value="open" {{ ($filters['status'] ?? '') === 'open' ? 'selected' : '' }}>Pendiente</option>
                                    <option value="reviewed" {{ ($filters['status'] ?? '') === 'reviewed' ? 'selected' : '' }}>Revisado</option>
                                    <option value="resolved" {{ ($filters['status'] ?? '') === 'resolved' ? 'selected' : '' }}>Resuelto</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <select name="source" class="form-control">
                                    <option value="">Todos los origenes</option>
                                    @foreach($sourceOptions as $value => $label)
                                        <option value="{{ $value }}" {{ ($filters['source'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="severity" class="form-control">
                                    <option value="">Todas las prioridades</option>
                                    <option value="3" {{ (string) ($filters['severity'] ?? '') === '3' ? 'selected' : '' }}>Alta</option>
                                    <option value="2" {{ (string) ($filters['severity'] ?? '') === '2' ? 'selected' : '' }}>Media</option>
                                    <option value="1" {{ (string) ($filters['severity'] ?? '') === '1' ? 'selected' : '' }}>Baja</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Buscar por alumno, grupo, reporta, asunto o descripcion">
                            </div>
                        </div>
                        <button class="btn btn-primary">Filtrar</button>
                        <a href="{{ route('coordination.reports.index') }}" class="btn btn-secondary">Limpiar</a>
                    </form>

                    <div class="table-responsive coordination-report-table-wrap">
                        <table data-datatable="true" class="table table-hover mb-0 reports-table">
                            <thead>
                                <tr>
                                    <th>Estatus</th>
                                    <th>Prioridad</th>
                                    <th>Origen</th>
                                    <th>Reporta</th>
                                    <th>Alumno</th>
                                    <th>Grupo</th>
                                    <th>Categoria</th>
                                    <th>Asunto</th>
                                    <th>Descripcion</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reports as $report)
                                    @php
                                        $priorityClass = match ((int) $report['priority']) {
                                            3 => 'high',
                                            2 => 'medium',
                                            default => 'low',
                                        };
                                        $rowClass = $report['status'] === 'open'
                                            ? 'report-row-open-' . $priorityClass
                                            : ($report['status'] === 'resolved' ? 'report-row-resolved' : 'report-row-reviewed');
                                    @endphp
                                    <tr class="{{ $rowClass }}">
                                        <td>
                                            @if($report['status'] === 'open')
                                                <span class="badge report-badge report-status-open">Pendiente</span>
                                            @elseif($report['status'] === 'resolved')
                                                <span class="badge report-badge report-status-resolved">Resuelto</span>
                                            @else
                                                <span class="badge report-badge report-status-reviewed">Revisado</span>
                                            @endif
                                        </td>
                                        <td><span class="badge report-badge report-badge-{{ $priorityClass }}">{{ $report['priority_label'] }}</span></td>
                                        <td>{{ $report['source_label'] }}</td>
                                        <td>{{ $report['reporter'] }}</td>
                                        <td>{{ $report['student'] }}</td>
                                        <td>{{ $report['group'] }}</td>
                                        <td>{{ $report['category'] }}</td>
                                        <td style="max-width:220px;white-space:normal;"><strong>{{ $report['subject'] }}</strong></td>
                                        <td style="max-width:380px;white-space:normal;">{{ $report['description'] }}</td>
                                        <td>{{ $report['created_at']->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <div class="report-actions">
                                                @if($report['status'] !== 'resolved')
                                                    <a href="{{ $report['case_route'] }}" class="btn btn-sm btn-warning">Dar seguimiento</a>
                                                @endif

                                                @if($report['status'] === 'open')
                                                    <form method="POST" action="{{ $report['review_route'] }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="reviewed">
                                                        <button class="btn btn-sm btn-primary">Marcar atendido</button>
                                                    </form>
                                                @endif

                                                @if($report['resolve_route'] && $report['status'] !== 'resolved')
                                                    <form method="POST" action="{{ $report['resolve_route'] }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="resolved">
                                                        <button class="btn btn-sm btn-success">Marcar resuelto</button>
                                                    </form>
                                                @elseif($report['status'] !== 'open')
                                                    <small class="text-muted">{{ $report['status'] === 'resolved' ? 'Cerrado' : 'Revisado' }}</small>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center text-muted py-4">No hay reportes registrados.</td>
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
