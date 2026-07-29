@extends('layouts.app')

@section('title', 'Desempeño académico')

@php
    $statusBadge = function (string $status): array {
        return match ($status) {
            'risk' => ['En riesgo', 'danger'],
            'incomplete' => ['Pendiente', 'warning'],
            default => ['En seguimiento', 'success'],
        };
    };
@endphp

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h3 class="mb-0">Desempeño académico</h3>
                        <small class="text-muted">
                            Grupo: {{ $student->group->name ?? 'Sin grupo' }}
                        </small>
                    </div>
                    <a href="{{ route('student.subjects') }}" class="btn btn-outline-secondary btn-sm">
                        Ver horario
                    </a>
                </div>

                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small text-uppercase">Promedio parcial</div>
                                <div class="h2 mb-0">
                                    {{ $generalAverage !== null ? number_format((float) $generalAverage, 1) : '-' }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small text-uppercase">Materias activas</div>
                                <div class="h2 mb-0">{{ $rows->count() }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small text-uppercase">Materias en riesgo</div>
                                <div class="h2 mb-0">{{ $rows->where('status', 'risk')->count() }}</div>
                            </div>
                        </div>
                    </div>

                    @if($rows->isEmpty())
                        <div class="alert alert-info mb-0">
                            No tienes materias activas en el ciclo seleccionado.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Materia</th>
                                        <th>Parcial</th>
                                        <th class="text-center">Calificación</th>
                                        <th class="text-center">Asistencia</th>
                                        <th class="text-center">Actividades</th>
                                        <th>Estado</th>
                                        <th class="text-right">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $row)
                                        @php
                                            $assignment = $row['assignment'];
                                            [$statusText, $statusClass] = $statusBadge($row['status']);
                                        @endphp
                                        <tr>
                                            <td>
                                                <strong>{{ $assignment->subject->name ?? 'Materia' }}</strong>
                                                <div class="text-muted small">
                                                    {{ $assignment->teacher->user->name ?? 'Sin profesor' }}
                                                </div>
                                            </td>
                                            <td>
                                                {{ optional($row['period'])->name ?? 'Sin parcial activo' }}
                                            </td>
                                            <td class="text-center">
                                                @if($row['final'] !== null)
                                                    <span class="badge badge-{{ (float) $row['final'] < 6 ? 'danger' : 'primary' }}">
                                                        {{ number_format((float) $row['final'], 1) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($row['attendance_percentage'] !== null)
                                                    {{ number_format((float) $row['attendance_percentage'], 0) }}%
                                                    <div class="text-muted small">
                                                        {{ $row['attended_sessions'] }}/{{ $row['total_sessions'] }}
                                                    </div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                {{ $row['graded_activities_count'] }}/{{ $row['activities_count'] }}
                                                @if($row['pending_activities_count'] > 0)
                                                    <div class="text-muted small">{{ $row['pending_activities_count'] }} pendiente(s)</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $statusClass }}">{{ $statusText }}</span>
                                            </td>
                                            <td class="text-right">
                                                <a href="{{ route('student.subjects.show', $assignment) }}" class="btn btn-outline-primary btn-sm">
                                                    Ver
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
