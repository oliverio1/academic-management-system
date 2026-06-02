@extends('layouts.app')

@section('title', 'Mis reportes')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Mis reportes académicos y conductuales</h3>
                    <a href="{{ route('teacher.reports.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus mr-1"></i> Nuevo reporte
                    </a>
                </div>
                <div class="card-body">
                    @if(session('info'))
                        <div class="alert alert-success">{{ session('info') }}</div>
                    @endif

                    <div class="table-responsive">
                        <table data-datatable="true" class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <th>Grupo</th>
                                    <th>Tipo</th>
                                    <th>Gravedad</th>
                                    <th>Estatus</th>
                                    <th>Fecha</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reports as $report)
                                    <tr>
                                        <td>{{ $report->student->user->name }}</td>
                                        <td>{{ $report->group->name }}</td>
                                        <td>{{ $typeOptions[$report->report_type] ?? $report->report_type }}</td>
                                        <td>{{ $report->severity }}</td>
                                        <td>
                                            @if($report->status === 'open')
                                                <span class="badge badge-danger">Pendiente</span>
                                            @else
                                                <span class="badge badge-success">Revisado</span>
                                            @endif
                                        </td>
                                        <td>{{ $report->created_at->format('d/m/Y H:i') }}</td>
                                        <td>
                                            @if($report->status === 'open' && is_null($report->reviewed_at))
                                                <a href="{{ route('teacher.reports.edit', $report) }}" class="btn btn-sm btn-outline-primary">
                                                    Revisar / Editar
                                                </a>
                                            @else
                                                <a href="{{ route('teacher.reports.show', $report) }}" class="btn btn-sm btn-outline-secondary">
                                                    Ver
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">Aún no has enviado reportes.</td>
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


