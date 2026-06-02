@extends('layouts.app')

@section('title', 'Parciales del ciclo')

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
                    <div>
                        <h4 class="mb-0">Parciales de {{ $schoolCycle->name }}</h4>
                        <small class="text-muted">Rango del ciclo: {{ $schoolCycle->start_date?->format('d/m/Y') }} a {{ $schoolCycle->end_date?->format('d/m/Y') }}</small>
                        <br>
                        <small class="text-muted">Generacion automatica por modalidad: Bachillerato = 2 parciales, Preparatoria = 4 parciales.</small>
                    </div>
                    <div>
                        <a href="{{ route('school-cycles.index') }}" class="btn btn-secondary">Volver</a>
                    </div>
                </div>
                <div class="card-body">
                    <table data-datatable="true" class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>Orden</th>
                                <th>Nombre</th>
                                <th>Codigo</th>
                                <th>Inicio</th>
                                <th>Termino</th>
                                <th>Limite docente</th>
                                <th>Activo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($partials as $partial)
                                <tr>
                                    <td>{{ $partial->sort_order }}</td>
                                    <td>{{ $partial->name }}</td>
                                    <td>{{ $partial->code ?: '—' }}</td>
                                    <td>{{ $partial->start_date?->format('d/m/Y') }}</td>
                                    <td>{{ $partial->end_date?->format('d/m/Y') }}</td>
                                    <td>{{ $partial->teacher_capture_deadline_at?->format('d/m/Y H:i') ?: '—' }}</td>
                                    <td>{{ $partial->is_active ? 'Si' : 'No' }}</td>
                                    <td>
                                        <a href="{{ route('school-cycles.partials.edit', [$schoolCycle, $partial]) }}" class="btn btn-warning btn-sm">Editar</a>
                                        @if($partial->is_active)
                                            <form action="{{ route('school-cycles.partials.destroy', [$schoolCycle, $partial]) }}" method="POST" style="display:inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger btn-sm" onclick="return confirm('Desactivar parcial?')">Desactivar</button>
                                            </form>
                                        @else
                                            <form action="{{ route('school-cycles.partials.activate', [$schoolCycle, $partial]) }}" method="POST" style="display:inline">
                                                @csrf
                                                <button class="btn btn-success btn-sm" onclick="return confirm('Activar parcial?')">Activar</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No hay parciales registrados.</td>
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
