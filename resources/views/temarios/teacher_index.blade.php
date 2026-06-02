@extends('layouts.app')

@section('title', 'Temarios')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Temarios por materia</h3>
                </div>
                <div class="card-body">
                    @php
                        $configuredCount = $subjects->filter(fn ($subject) => (int) $subject->temarios_count > 0)->count();
                        $pendingCount = $subjects->count() - $configuredCount;
                    @endphp

                    <div class="mb-3 d-flex flex-wrap" style="gap: .5rem;">
                        <span class="badge badge-success p-2">Configurados: {{ $configuredCount }}</span>
                        <span class="badge badge-warning p-2">Pendientes: {{ $pendingCount }}</span>
                        <span class="badge badge-secondary p-2">Total materias: {{ $subjects->count() }}</span>
                    </div>

                    <div class="table-responsive">
                        <table data-datatable="true" class="table table-hover table-bordered" id="temarios-table">
                            <thead>
                                <tr>
                                    <th>Materia</th>
                                    <th>Modalidad</th>
                                    <th>Asignaciones</th>
                                    <th>Temarios registrados</th>
                                    <th>Estatus</th>
                                    <th>AcciÃ³n</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($subjects as $subject)
                                    @php
                                        $isConfigured = (int) $subject->temarios_count > 0;
                                    @endphp
                                    <tr>
                                        <td>{{ $subject->name }}</td>
                                        <td>{{ $subject->level->modality->name ?? '-' }}</td>
                                        <td>{{ $subject->assignments_count }}</td>
                                        <td>{{ $subject->temarios_count }}</td>
                                        <td>
                                            @if($isConfigured)
                                                <span class="badge badge-success">Configurado</span>
                                            @else
                                                <span class="badge badge-warning">Pendiente</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('temarios.import.form', $subject) }}" class="btn btn-outline-secondary btn-sm mr-1">
                                                Importar
                                            </a>
                                            <a href="{{ route('temarios.index', $subject) }}" class="btn btn-primary btn-sm">
                                                Gestionar
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No hay materias activas para gestionar temarios.</td>
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

@section('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    $('#temarios-table').DataTable({
        order: [],
        language: { url: '/datatables.json' }
    });
});
</script>
@endsection

