@extends('layouts.app')

@section('title', 'Faltas En Clase')

@section('content')
@php
    $canOpenStudentAcademicSummary = auth()->user()?->hasAnyRole(['coordinator', 'admin']) ?? false;
@endphp
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">Alumnos con faltas en clase</h4>
                        <small class="text-muted">
                            Casos donde prefectura registra presencia, pero hay falta por materia en el periodo activo.
                        </small>
                    </div>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Volver al dashboard</a>
                </div>
                <div class="card-body table-responsive p-3">
                    <table data-datatable="true" class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>MatrÃ­cula</th>
                                <th>Alumno</th>
                                <th>Grupo</th>
                                <th class="text-center">Sesiones con falta</th>
                                <th class="text-center">DÃ­as con falta</th>
                                <th>Fechas de falta</th>
                                <th>Materias afectadas</th>
                                @if($canOpenStudentAcademicSummary)
                                    <th class="text-center">AcciÃ³n</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $row)
                                <tr>
                                    <td>{{ $row->enrollment_number ?: '-' }}</td>
                                    <td>{{ $row->student_name }}</td>
                                    <td>{{ $row->group_name }}</td>
                                    <td class="text-center">{{ (int) $row->missed_sessions }}</td>
                                    <td class="text-center">{{ (int) $row->missed_days }}</td>
                                    <td>{{ $row->missed_dates ?: '-' }}</td>
                                    <td>{{ $row->subjects_affected ?: '-' }}</td>
                                    @if($canOpenStudentAcademicSummary)
                                        <td class="text-center">
                                            <a href="{{ route('coordination.students.academic-summary', ['group_id' => $row->group_id, 'student_id' => $row->student_id]) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                Ver alumno
                                            </a>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canOpenStudentAcademicSummary ? 8 : 7 }}" class="text-center text-muted py-4">
                                        No hay casos detectados en el periodo activo.
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


