@extends('layouts.app')

@section('title', 'Alumnos con riesgo por asistencia')

@section('content')
<div class="content px-3">

    {{-- Encabezado --}}
    <div class="row mb-3">
        <div class="col-md-12">
            <h4 class="mb-1">Alumnos con riesgo por asistencia</h4>
            <p class="text-muted mb-0">
                Alumnos con <strong>inasistencias no justificadas recurrentes</strong>
                en los últimos <strong>7 días</strong>.
            </p>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Alumno</th>
                        <th>Grupo</th>
                        <th class="text-center">Faltas</th>
                        <th>Última falta</th>
                        <th class="text-center">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $row)
                        @php
                            $dangerClass = $row->absences >= 5 ? 'text-danger font-weight-bold' : 'text-danger';
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $row->student->user->name }}</strong>
                            </td>

                            <td>
                                {{ $row->student->group->name ?? '—' }}
                            </td>

                            <td class="text-center {{ $dangerClass }}">
                                {{ $row->absences }}
                            </td>

                            <td>
                                {{ optional($row->last_absence)->format('d M Y') ?? '—' }}
                            </td>

                            <td class="text-center">
                                <a href="{{ route('coordination.students.show', $row->student_id) }}"
                                   class="btn btn-sm btn-outline-danger">
                                    Ver alumno
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted p-4">
                                No hay alumnos en riesgo por asistencia.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
