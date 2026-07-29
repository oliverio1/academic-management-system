@extends('layouts.app')

@section('title', 'Casos escolares')

@section('content')
@include('coordination.reports._table_styles')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Casos escolares</h3>
                    <small class="text-muted">Seguimiento formal de reportes que requieren acciones, respuesta o cierre documentado.</small>
                </div>
                <div class="card-body">
                    @if(session('info'))
                        <div class="alert alert-success">{{ session('info') }}</div>
                    @endif

                    <form method="GET" action="{{ route('coordination.school-cases.index') }}" class="coordination-report-filters">
                        <div class="row">
                            <div class="col-md-2 mb-2">
                                <select name="status" class="form-control">
                                    <option value="">Todos los estatus</option>
                                    @foreach($statuses as $value => $label)
                                        <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="priority" class="form-control">
                                    <option value="">Todas las prioridades</option>
                                    @foreach($priorities as $value => $label)
                                        <option value="{{ $value }}" {{ ($filters['priority'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="source_type" class="form-control">
                                    <option value="">Todos los origenes</option>
                                    @foreach($sourceTypes as $value => $label)
                                        <option value="{{ $value }}" {{ ($filters['source_type'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="target_type" class="form-control">
                                    <option value="">Todos los asuntos</option>
                                    @foreach($targetTypes as $value => $label)
                                        <option value="{{ $value }}" {{ ($filters['target_type'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="assigned_to" class="form-control">
                                    <option value="">Todos los responsables</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ (string) ($filters['assigned_to'] ?? '') === (string) $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Buscar">
                            </div>
                        </div>
                        <button class="btn btn-primary">Filtrar</button>
                        <a href="{{ route('coordination.school-cases.index') }}" class="btn btn-secondary">Limpiar</a>
                    </form>

                    <div class="table-responsive coordination-report-table-wrap">
                        <table data-datatable="true" class="table table-hover mb-0 reports-table">
                            <thead>
                                <tr>
                                    <th>Folio</th>
                                    <th>Estatus</th>
                                    <th>Prioridad</th>
                                    <th>Origen</th>
                                    <th>Asunto</th>
                                    <th>Relacionado con</th>
                                    <th>Responsable</th>
                                    <th>Vence</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cases as $case)
                                    @php
                                        $priorityClass = match ($case->priority) {
                                            'critical', 'high' => 'high',
                                            'medium' => 'medium',
                                            default => 'low',
                                        };
                                        $rowClass = in_array($case->status, ['resolved', 'closed'], true)
                                            ? 'report-row-resolved'
                                            : 'report-row-open-'.$priorityClass;
                                        $related = $case->student?->user?->name
                                            ?? $case->group?->name
                                            ?? $case->teacher?->user?->name
                                            ?? $case->guardian?->name
                                            ?? $case->location
                                            ?? 'General';
                                    @endphp
                                    <tr class="{{ $rowClass }}">
                                        <td><strong>{{ $case->case_number }}</strong></td>
                                        <td><span class="badge report-badge report-status-{{ in_array($case->status, ['resolved', 'closed'], true) ? 'resolved' : 'open' }}">{{ $statuses[$case->status] ?? $case->status }}</span></td>
                                        <td><span class="badge report-badge report-badge-{{ $priorityClass }}">{{ $priorities[$case->priority] ?? $case->priority }}</span></td>
                                        <td>{{ $sourceTypes[$case->source_type] ?? $case->source_type }}</td>
                                        <td style="max-width:360px;white-space:normal;">
                                            <strong>{{ $case->subject }}</strong>
                                            <div class="text-muted small">{{ $categories[$case->category] ?? $case->category }}</div>
                                        </td>
                                        <td>{{ $related }}</td>
                                        <td>{{ $case->assignee?->name ?? 'Sin asignar' }}</td>
                                        <td>{{ $case->due_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                        <td>
                                            <a href="{{ route('coordination.school-cases.show', $case) }}" class="btn btn-sm btn-primary">Ver detalle</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">No hay casos escolares registrados.</td>
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
