@extends('layouts.app')

@section('title', 'Suspensiones')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            @if(session('info'))
                <div class="alert alert-success">{{ session('info') }}</div>
            @endif

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Suspensiones de alumnos</h4>
                    <a href="{{ route('coordination.suspensions.create') }}" class="btn btn-primary btn-sm">
                        Nueva suspension
                    </a>
                </div>

                <div class="card-body table-responsive p-3">
                    <table data-datatable="true" class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Alumno</th>
                                <th>Grupo</th>
                                <th>Desde</th>
                                <th>Hasta</th>
                                <th>Motivo</th>
                                <th>Registrada por</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($suspensions as $suspension)
                                <tr>
                                    <td>{{ $suspension->student->user->name ?? 'N/D' }}</td>
                                    <td>{{ $suspension->group->name ?? 'N/D' }}</td>
                                    <td>{{ optional($suspension->start_date)->format('d/m/Y') }}</td>
                                    <td>{{ optional($suspension->end_date)->format('d/m/Y') }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($suspension->reason, 100) }}</td>
                                    <td>{{ $suspension->creator->name ?? 'N/D' }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('coordination.suspensions.edit', $suspension) }}" class="btn btn-sm btn-warning">
                                            Editar
                                        </a>
                                        <form method="POST"
                                              action="{{ route('coordination.suspensions.destroy', $suspension) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('Deseas eliminar esta suspension?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        No hay suspensiones registradas.
                                    </td>
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

