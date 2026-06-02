@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Mis reportes</h3>
                    <a href="{{ route('prefect.reports.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus mr-1"></i> Nuevo reporte
                    </a>
                </div>
                <div class="card-body">
        @if(session('info'))
            <div class="alert alert-success">{{ session('info') }}</div>
        @endif

        <div class="card-body table-responsive p-3">
            <table data-datatable="true" class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Destinatario</th>
                        <th>Categoria</th>
                        <th>Asunto</th>
                        <th>Estatus</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                        <tr>
                            <td>{{ $report->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $reportToOptions[$report->report_to] ?? $report->report_to }}</td>
                            <td>{{ $categoryOptions[$report->category] ?? $report->category }}</td>
                            <td>{{ $report->subject }}</td>
                            <td>
                                @if($report->status === 'open')
                                    <span class="badge badge-danger">Pendiente</span>
                                @elseif($report->status === 'reviewed')
                                    <span class="badge badge-warning">Revisado</span>
                                @else
                                    <span class="badge badge-success">Resuelto</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Aun no has enviado reportes.</td>
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



