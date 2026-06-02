@extends('layouts.app')

@section('title', 'Estados de cuenta')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header"><h4 class="mb-0">Estados de cuenta por alumno</h4></div>
                <div class="card-body table-responsive p-3">
                    <table data-datatable="true" class="table table-hover mb-0">
                        <thead><tr><th>Alumno</th><th>Grupo</th><th>Acción</th></tr></thead>
                        <tbody>
                            @forelse($students as $student)
                                <tr>
                                    <td>{{ optional($student->user)->name }}</td>
                                    <td>{{ optional($student->group)->name ?? '-' }}</td>
                                    <td><a href="{{ route('coordination.finance.statements.show', $student) }}" class="btn btn-sm btn-outline-primary">Ver estado</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">Sin alumnos para el campus activo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

