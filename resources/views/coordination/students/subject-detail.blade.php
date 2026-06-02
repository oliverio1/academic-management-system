@extends('layouts.app')

@section('title', 'Detalle de materia')

@php
    $statusLabel = function (?string $status) {
        return match ($status) {
            'present' => ['Asistencia', 'success'],
            'late' => ['Retardo', 'warning'],
            'absent' => ['Falta', 'danger'],
            'justified' => ['Justificado', 'info'],
            default => ['Sin registro', 'secondary'],
        };
    };
@endphp

@section('content')
<div class="app-content-header">
    <div class="container-fluid d-flex justify-content-between align-items-start">
        <div>
            <h3 class="mb-0">{{ $assignment->subject->name }}</h3>
            <small class="text-muted">
                Alumno: {{ $student->user->name }} |
                Profesor: {{ $assignment->teacher->user->name }} |
                Grupo: {{ $assignment->group->name }}
                @if(!empty($activeCycle))
                    | Ciclo: {{ $activeCycle->name }} ({{ $activeCycle->code }})
                @endif
                @if($period)
                    | Periodo: {{ $period->name }}
                @endif
            </small>
        </div>
        <a href="{{ route('coordination.students.academic-summary', ['group_id' => $student->group_id, 'student_id' => $student->id]) }}"
           class="btn btn-outline-secondary btn-sm">
            Volver
        </a>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        <div class="card mb-3">
            <div class="card-header"><strong>Desglose por rubros</strong></div>
            <div class="card-body">
                <h5 class="mb-3">
                    Calificacion final:
                    @if($finalGrade !== null)
                        <span class="badge badge-primary">{{ number_format((float) $finalGrade, 1) }}</span>
                    @else
                        <span class="badge badge-secondary">Sin calcular</span>
                    @endif
                </h5>
                <div class="table-responsive p-0">
                    <table data-datatable="true" class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Rubro</th>
                                <th class="text-center">Porcentaje</th>
                                <th class="text-center">Calificacion</th>
                                <th class="text-center">Aporta</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($gradeBreakdownRows as $row)
                                <tr>
                                    <td>{{ $row['criterion'] }}</td>
                                    <td class="text-center">{{ number_format((float) $row['percentage'], 2) }}%</td>
                                    <td class="text-center">{{ $row['average'] !== null ? number_format((float) $row['average'], 1) : '-' }}</td>
                                    <td class="text-center">{{ $row['contribution'] !== null ? number_format((float) $row['contribution'], 1) : '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No hay rubros o calificaciones para este periodo.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>Sesiones y asistencia</strong></div>
            <div class="card-body table-responsive p-3">
                <table data-datatable="true" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Horario</th>
                            <th>Asistencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sessions as $session)
                            @php
                                $attendance = $session->attendances->first();
                                [$label, $badge] = $statusLabel(optional($attendance)->status);
                            @endphp
                            <tr>
                                <td>{{ optional($session->session_date)->format('d/m/Y') }}</td>
                                <td>{{ substr((string) $session->start_time, 0, 5) }} - {{ substr((string) $session->end_time, 0, 5) }}</td>
                                <td><span class="badge badge-{{ $badge }}">{{ $label }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No hay sesiones registradas para el ciclo consultado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Actividades y calificaciones</strong></div>
            <div class="card-body table-responsive p-3">
                <table data-datatable="true" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Actividad</th>
                            <th>Fecha limite</th>
                            <th>Valor maximo</th>
                            <th>Calificacion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activities as $activity)
                            @php $grade = $grades->get($activity->id); @endphp
                            <tr>
                                <td>{{ $activity->title }}</td>
                                <td>{{ optional($activity->due_date)->format('d/m/Y') ?? '-' }}</td>
                                <td>{{ $activity->max_score }}</td>
                                <td>
                                    @if($grade)
                                        <strong>{{ number_format((float) $grade->score, 1) }}</strong>
                                    @else
                                        <span class="text-muted">Sin calificar</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No hay actividades registradas para el ciclo consultado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

