@extends('layouts.app')

@section('title', 'Ciclos escolares')

@section('content')
<div class="content px-3">
    @if(session('info'))
        <div class="alert alert-primary" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Ciclos escolares</h4>
                    <a class="btn btn-primary" href="{{ route('school-cycles.create') }}">
                        <i class="fas fa-plus mr-1"></i> Nuevo ciclo
                    </a>
                </div>
                <div class="card-body">
                    <table data-datatable="true" class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ciclo</th>
                                <th>Campus asociados</th>
                                <th>Modalidad</th>
                                <th>Inicio</th>
                                <th>Termino</th>
                                <th>Activo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cycles as $cycle)
                                <tr>
                                    <td>{{ $cycle->id }}</td>
                                    <td>{{ $cycle->name }} ({{ $cycle->code }})</td>
                                    <td>{{ $cycle->campuses->pluck('name')->implode(', ') ?: 'Sin campus' }}</td>
                                    <td>{{ $cycle->modalities->pluck('name')->implode(', ') }}</td>
                                    <td>{{ $cycle->start_date?->format('d/m/Y') }}</td>
                                    <td>{{ $cycle->end_date?->format('d/m/Y') }}</td>
                                    <td>{{ $cycle->is_active ? 'Si' : 'No' }}</td>
                                    <td>
                                        <a href="{{ route('school-cycles.show', $cycle) }}" class="btn btn-info btn-sm">Ver</a>
                                        <a href="{{ route('school-cycles.edit', $cycle) }}" class="btn btn-warning btn-sm">Editar</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No hay ciclos registrados.</td>
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
