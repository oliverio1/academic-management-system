@extends('layouts.app')

@section('title', 'Detalle del alumno')

@section('content')
@php
    $attendanceTotal = (int) ($attendanceStats->total ?? 0);
    $attended = (int) ($attendanceStats->attended ?? 0);
    $attendancePercent = $attendanceTotal > 0 ? round(($attended / $attendanceTotal) * 100) : null;
    $activityPercent = $totalActivities > 0 ? round(($deliveredActivities / $totalActivities) * 100) : null;
    $statusLabels = [
        'present' => ['Presente', 'success'],
        'late' => ['Retardo', 'warning'],
        'absent' => ['Falta', 'danger'],
        'justified' => ['Justificada', 'info'],
    ];
@endphp

<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">{{ $student->user->name }}</h4>
                            <div class="text-muted small">
                                Grupo {{ $student->group->name ?? '-' }}
                                @if($activeCycle)
                                    - {{ $activeCycle->name }}
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('teacher.students.group', $student->group) }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left mr-1"></i>Volver al grupo
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="student-profile-header mb-3">
                        <div>
                            <div class="text-muted small">Matricula</div>
                            <div class="font-weight-bold">{{ $student->enrollment_number ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="text-muted small">Tutor</div>
                            <div class="font-weight-bold">{{ $student->guardian?->name ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="text-muted small">Estado</div>
                            <span class="badge badge-{{ $student->is_active ? 'success' : 'secondary' }}">
                                {{ $student->is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 col-6 mb-2">
                            <div class="student-stat {{ $attendancePercent !== null && $attendancePercent < 80 ? 'student-stat-risk' : 'student-stat-success' }}">
                                <div class="student-stat-value">{{ $attendancePercent !== null ? $attendancePercent.'%' : '-' }}</div>
                                <div class="student-stat-label">Asistencia</div>
                                <div class="text-muted small">{{ $attended }} / {{ $attendanceTotal }} registros</div>
                            </div>
                        </div>
                        <div class="col-md-4 col-6 mb-2">
                            <div class="student-stat {{ $activityPercent !== null && $activityPercent < 80 ? 'student-stat-warning' : 'student-stat-info' }}">
                                <div class="student-stat-value">{{ $deliveredActivities }} / {{ $totalActivities }}</div>
                                <div class="student-stat-label">Actividades entregadas</div>
                                <div class="text-muted small">{{ $activityPercent !== null ? $activityPercent.'%' : 'Sin actividades' }}</div>
                            </div>
                        </div>
                        <div class="col-md-4 col-12 mb-2">
                            <div class="student-stat student-stat-neutral">
                                <div class="student-stat-value">{{ $reports->count() }}</div>
                                <div class="student-stat-label">Reportes docentes recientes</div>
                                <div class="text-muted small">Solo registros del profesor actual</div>
                            </div>
                        </div>
                    </div>

                    <section class="student-section">
                        <div class="student-section-header">
                            <h5 class="mb-0">Resumen por materia</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Materia</th>
                                        <th class="text-center">Asistencia</th>
                                        <th class="text-center">Actividades</th>
                                        <th class="text-center">Promedio</th>
                                        <th class="text-right">Accion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($summaryBySubject as $item)
                                        <tr>
                                            <td class="font-weight-bold">{{ $item['subject'] }}</td>
                                            <td class="text-center">
                                                @if($item['attendance']['total'] > 0)
                                                    {{ $item['attendance']['attended'] }} / {{ $item['attendance']['total'] }}
                                                    <span class="font-weight-bold">({{ $item['attendance']['percent'] }}%)</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                {{ $item['activities']['delivered'] }} / {{ $item['activities']['total'] }}
                                            </td>
                                            <td class="text-center">
                                                {{ $item['activities']['average'] !== null ? $item['activities']['average'] : '-' }}
                                            </td>
                                            <td class="text-right">
                                                <a href="{{ route('teacher.classes.sessions.index', $item['assignment_id']) }}" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-calendar-alt mr-1"></i>Sesiones
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <div class="row">
                        <div class="col-lg-6">
                            <section class="student-section">
                                <div class="student-section-header">
                                    <h5 class="mb-0">Asistencia reciente</h5>
                                </div>
                                @if($recentAttendance->isEmpty())
                                    <div class="teacher-empty-state">Todavia no hay registros de asistencia para este alumno.</div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Materia</th>
                                                    <th>Actividad</th>
                                                    <th class="text-center">Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($recentAttendance as $attendance)
                                                    @php
                                                        $session = $attendance->academicSession;
                                                        $label = $statusLabels[$attendance->status] ?? [ucfirst((string) $attendance->status), 'secondary'];
                                                    @endphp
                                                    <tr>
                                                        <td class="text-nowrap">{{ optional($session?->session_date)->format('Y-m-d') }}</td>
                                                        <td>{{ $session?->teachingAssignment?->subject?->name ?? '-' }}</td>
                                                        <td>{{ $session?->sessionActivity?->title ?? '-' }}</td>
                                                        <td class="text-center">
                                                            <span class="badge badge-{{ $label[1] }}">{{ $label[0] }}</span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </section>
                        </div>

                        <div class="col-lg-6">
                            <section class="student-section">
                                <div class="student-section-header">
                                    <h5 class="mb-0">Actividades recientes</h5>
                                </div>
                                @if($activityRows->isEmpty())
                                    <div class="teacher-empty-state">Todavia no hay actividades registradas para este alumno.</div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Actividad</th>
                                                    <th>Materia</th>
                                                    <th class="text-center">Calificacion</th>
                                                    <th class="text-center">Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($activityRows as $row)
                                                    @php
                                                        $activity = $row['activity'];
                                                        $grade = $row['grade'];
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <div class="font-weight-bold">{{ $activity->title }}</div>
                                                            <div class="text-muted small">
                                                                {{ optional($activity->due_date)->format('Y-m-d') ?: 'Sin fecha' }}
                                                                @if($activity->evaluationCriterion)
                                                                    - {{ $activity->evaluationCriterion->name }}
                                                                @endif
                                                            </div>
                                                        </td>
                                                        <td>{{ $activity->assignment?->subject?->name ?? '-' }}</td>
                                                        <td class="text-center">
                                                            @if($grade)
                                                                {{ number_format((float) $grade->score, 1) }} / {{ number_format((float) $activity->max_score, 1) }}
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-center">
                                                            @if($grade)
                                                                <span class="badge badge-success">Capturada</span>
                                                            @else
                                                                <span class="badge badge-warning">Pendiente</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </section>
                        </div>
                    </div>

                    <section class="student-section mb-0">
                        <div class="student-section-header">
                            <h5 class="mb-0">Reportes y seguimiento</h5>
                            <a href="{{ route('teacher.reports.create', ['student_id' => $student->id]) }}" class="btn btn-warning btn-sm">
                                <i class="fas fa-flag mr-1"></i>Nuevo reporte
                            </a>
                        </div>
                        @if($reports->isEmpty())
                            <div class="teacher-empty-state">No hay reportes docentes recientes para este alumno.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Tipo</th>
                                            <th>Motivo</th>
                                            <th class="text-center">Severidad</th>
                                            <th class="text-center">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($reports as $report)
                                            <tr>
                                                <td class="text-nowrap">{{ optional($report->created_at)->format('Y-m-d') }}</td>
                                                <td>{{ ucfirst(str_replace('_', ' ', (string) $report->report_type)) }}</td>
                                                <td>{{ $report->reason }}</td>
                                                <td class="text-center">{{ $report->severity }}</td>
                                                <td class="text-center">
                                                    <span class="badge badge-{{ $report->status === 'reviewed' ? 'success' : 'secondary' }}">
                                                        {{ ucfirst((string) $report->status) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_css')
<style>
    .student-profile-header {
        border: 1px solid #e9ecef;
        border-radius: 6px;
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        padding: .85rem 1rem;
    }

    .student-stat {
        border: 1px solid #dee2e6;
        border-left: 4px solid #6c757d;
        border-radius: 4px;
        min-height: 88px;
        padding: .75rem;
    }

    .student-stat-success {
        border-left-color: #28a745;
    }

    .student-stat-info {
        border-left-color: #17a2b8;
    }

    .student-stat-warning {
        border-left-color: #ffc107;
    }

    .student-stat-risk {
        border-left-color: #dc3545;
    }

    .student-stat-neutral {
        border-left-color: #6c757d;
    }

    .student-stat-value {
        font-size: 1.45rem;
        font-weight: 800;
        line-height: 1;
    }

    .student-stat-label {
        color: #6c757d;
        font-size: .82rem;
        margin-top: .35rem;
    }

    .student-section {
        border-top: 1px solid #e9ecef;
        margin-top: 1rem;
        padding-top: 1rem;
    }

    .student-section-header {
        align-items: center;
        display: flex;
        gap: 10px;
        justify-content: space-between;
        margin-bottom: .75rem;
    }

    .teacher-empty-state {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 4px;
        color: #6c757d;
        padding: 1rem;
    }

    @media (max-width: 767.98px) {
        .student-profile-header {
            grid-template-columns: 1fr;
        }

        .student-section-header {
            align-items: stretch;
            flex-direction: column;
        }

        .student-section-header .btn {
            width: 100%;
        }
    }
</style>
@endsection
