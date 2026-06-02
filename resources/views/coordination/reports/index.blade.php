@extends('layouts.app')

@section('title', 'Reportes de docentes')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Reportes académicos y conductuales</h3>
                    <small class="text-muted">Ordenados por prioridad: pendientes y gravedad alta primero.</small>
                </div>
                <div class="card-body">
                    @if(session('info'))
                        <div class="alert alert-success">{{ session('info') }}</div>
                    @endif

                    <form method="GET" action="{{ route('coordination.reports.index') }}">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <select name="status" class="form-control">
                                    <option value="">Todos los estatus</option>
                                    <option value="open" {{ ($filters['status'] ?? '') === 'open' ? 'selected' : '' }}>Pendiente</option>
                                    <option value="reviewed" {{ ($filters['status'] ?? '') === 'reviewed' ? 'selected' : '' }}>Revisado</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="severity" class="form-control">
                                    <option value="">Todas las gravedades</option>
                                    <option value="3" {{ (string) ($filters['severity'] ?? '') === '3' ? 'selected' : '' }}>3 - Alta</option>
                                    <option value="2" {{ (string) ($filters['severity'] ?? '') === '2' ? 'selected' : '' }}>2 - Media</option>
                                    <option value="1" {{ (string) ($filters['severity'] ?? '') === '1' ? 'selected' : '' }}>1 - Baja</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <select name="group_id" class="form-control">
                                    <option value="">Todos los grupos</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}" {{ (string) ($filters['group_id'] ?? '') === (string) $group->id ? 'selected' : '' }}>
                                            {{ $group->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <select name="teacher_id" class="form-control">
                                    <option value="">Todos los docentes</option>
                                    @foreach($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" {{ (string) ($filters['teacher_id'] ?? '') === (string) $teacher->id ? 'selected' : '' }}>
                                            {{ $teacher->user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button class="btn btn-outline-primary">Filtrar</button>
                        <a href="{{ route('coordination.reports.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                    </form>

                    <hr>

                    <div class="table-responsive p-3">
                        <table data-datatable="true" class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Gravedad</th>
                                    <th>Estatus</th>
                                    <th>Alumno</th>
                                    <th>Grupo</th>
                                    <th>Docente</th>
                                    <th>Tipo</th>
                                    <th>Motivo</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reports as $report)
                                    <tr class="{{ $report->severity === 3 && $report->status === 'open' ? 'table-danger' : '' }}">
                                        <td><span class="badge {{ $report->severity === 3 ? 'badge-danger' : ($report->severity === 2 ? 'badge-warning' : 'badge-secondary') }}">{{ $report->severity }}</span></td>
                                        <td>
                                            @if($report->status === 'open')
                                                <span class="badge badge-danger">Pendiente</span>
                                            @else
                                                <span class="badge badge-success">Revisado</span>
                                            @endif
                                        </td>
                                        <td>{{ $report->student->user->name }}</td>
                                        <td>{{ $report->group->name }}</td>
                                        <td>{{ $report->teacher->user->name }}</td>
                                        <td>{{ $typeOptions[$report->report_type] ?? $report->report_type }}</td>
                                        <td style="max-width:360px;white-space:normal;">{{ $report->reason }}</td>
                                        <td>{{ $report->created_at->format('d/m/Y H:i') }}</td>
                                        <td>
                                            @if($report->status === 'open')
                                                <form method="POST" action="{{ route('coordination.reports.review', $report) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="btn btn-sm btn-outline-success">Marcar revisado</button>
                                                </form>
                                            @else
                                                <small class="text-muted">Revisado</small>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">No hay reportes registrados.</td>
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

