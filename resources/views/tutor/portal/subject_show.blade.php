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
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="mb-0">{{ $assignment->subject->name }}</h4>
                        <small class="text-muted">
                            Alumno: {{ $student->user->name }} |
                            Profesor: {{ $assignment->teacher->user->name }} |
                            Grupo: {{ $assignment->group->name }}
                            @if($period)
                                | Periodo: {{ $period->name }}
                            @endif
                        </small>
                    </div>
                    <a href="{{ route('tutor.subjects') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
                </div>
                <div class="card-body">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="mb-2">Porcentaje de asistencia</h5>
                            <div class="progress" style="height: 22px;">
                                <div class="progress-bar" role="progressbar" style="width: {{ $attendancePercentage }}%;" aria-valuenow="{{ $attendancePercentage }}" aria-valuemin="0" aria-valuemax="100">
                                    {{ number_format((float) $attendancePercentage, 0) }}%
                                </div>
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
                                            <td colspan="3" class="text-center text-muted py-4">No hay sesiones registradas para este periodo.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><strong>Actividades del periodo y calificaciones</strong></div>
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
                                            <td colspan="4" class="text-center text-muted py-4">No hay actividades registradas para este periodo.</td>
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
</div>
@endsection

