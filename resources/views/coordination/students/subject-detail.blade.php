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
                        <h4 class="mb-1">{{ $assignment->subject->name }}</h4>
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
                       class="btn btn-secondary btn-sm">
                        Volver
                    </a>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="border rounded h-100 p-3">
                                <div class="text-muted small text-uppercase font-weight-bold">Asistencia</div>
                                <h3 class="mb-0">{{ $attendanceStats['percentage'] !== null ? number_format((float) $attendanceStats['percentage'], 1).'%' : '-' }}</h3>
                                <small class="text-muted">{{ $attendanceStats['present'] }} asistencias de {{ $attendanceStats['total'] }} sesiones</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="border rounded h-100 p-3">
                                <div class="text-muted small text-uppercase font-weight-bold">Captura docente</div>
                                <h3 class="mb-0">{{ $attendanceStats['closed'] }} / {{ $attendanceStats['total'] }}</h3>
                                <small class="text-muted">sesiones con asistencia cerrada</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="border rounded h-100 p-3">
                                <div class="text-muted small text-uppercase font-weight-bold">Actividades</div>
                                <h3 class="mb-0">{{ $activityStats['graded'] }} / {{ $activityStats['total'] }}</h3>
                                <small class="text-muted">calificadas para el alumno</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="border rounded h-100 p-3">
                                <div class="text-muted small text-uppercase font-weight-bold">Promedio actividades</div>
                                <h3 class="mb-0">{{ $activityStats['average'] !== null ? number_format((float) $activityStats['average'], 1) : '-' }}</h3>
                                <small class="text-muted">sobre actividades calificadas</small>
                            </div>
                        </div>
                    </div>

                    <h5 class="mb-3">
                        Desglose por rubros
                        <span class="ml-2">
                            Calificacion final:
                            @if($finalGrade !== null)
                                <span class="badge badge-primary">{{ number_format((float) $finalGrade, 1) }}</span>
                            @else
                                <span class="badge badge-secondary">Sin calcular</span>
                            @endif
                        </span>
                    </h5>
                    <div class="table-responsive mb-4">
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

                    <h5 class="mb-3">Sesiones y asistencia</h5>
                    <div class="table-responsive mb-4">
                        <table data-datatable="true" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Horario</th>
                            <th>Periodo</th>
                            <th>Asistencia</th>
                            <th>Captura docente</th>
                            <th>Actividad de sesion</th>
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
                                <td>{{ $session->academicPeriod?->name ?? '-' }}</td>
                                <td><span class="badge badge-{{ $badge }}">{{ $label }}</span></td>
                                <td>
                                    @if($session->attendance_closed_at)
                                        <span class="badge badge-success">Cerrada</span>
                                        <div class="small text-muted">{{ \Carbon\Carbon::parse($session->attendance_closed_at)->format('d/m/Y H:i') }}</div>
                                    @else
                                        <span class="badge badge-secondary">Abierta</span>
                                    @endif
                                </td>
                                <td>
                                    @if($session->sessionActivity)
                                        <strong>{{ $session->sessionActivity->title }}</strong>
                                        @if($session->sessionActivity->evaluationCriterion)
                                            <div class="small text-muted">
                                                Rubro: {{ $session->sessionActivity->evaluationCriterion->name }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-muted">Sin actividad</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No hay sesiones registradas para el ciclo consultado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                    </div>

                    <h5 class="mb-3">Actividades y calificaciones</h5>
                    <div class="table-responsive">
                        <table data-datatable="true" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Actividad</th>
                            <th>Periodo</th>
                            <th>Rubro</th>
                            <th>Fecha limite</th>
                            <th>Valor maximo</th>
                            <th>Calificacion</th>
                            <th>Comentarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activities as $activity)
                            @php $grade = $grades->get($activity->id); @endphp
                            <tr>
                                <td>
                                    <strong>{{ $activity->title }}</strong>
                                    @if($activity->description)
                                        <div class="small text-muted">{{ \Illuminate\Support\Str::limit($activity->description, 120) }}</div>
                                    @endif
                                </td>
                                <td>{{ $activity->academicPeriod?->name ?? '-' }}</td>
                                <td>
                                    {{ $activity->evaluationCriterion?->name ?? '-' }}
                                    @if($activity->evaluationCriterion?->percentage !== null)
                                        <div class="small text-muted">{{ number_format((float) $activity->evaluationCriterion->percentage, 2) }}%</div>
                                    @endif
                                </td>
                                <td>{{ optional($activity->due_date)->format('d/m/Y') ?? '-' }}</td>
                                <td>{{ $activity->max_score }}</td>
                                <td>
                                    @if($grade)
                                        <strong>{{ number_format((float) $grade->score, 1) }}</strong>
                                    @else
                                        <span class="text-muted">Sin calificar</span>
                                    @endif
                                </td>
                                <td>{{ $grade?->comments ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No hay actividades registradas para el ciclo consultado.</td>
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

