@extends('layouts.app')

@section('title', 'Mis reportes')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Mis reportes</h3>
                </div>
                <div class="card-body table-responsive p-3">
                <table data-datatable="true" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Gravedad</th>
                            <th>Docente</th>
                            <th>Motivo</th>
                            <th>Estatus</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reports as $report)
                            <tr>
                                <td>{{ $report->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $typeLabels[$report->report_type] ?? $report->report_type }}</td>
                                <td>{{ $report->severity }}</td>
                                <td>{{ $report->teacher->user->name }}</td>
                                <td style="max-width: 360px; white-space: normal;">{{ $report->reason }}</td>
                                <td>
                                    @if($report->status === 'open')
                                        <span class="badge badge-danger">Pendiente de revision</span>
                                    @else
                                        <span class="badge badge-success">Revisado</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No tienes reportes registrados.</td>
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



