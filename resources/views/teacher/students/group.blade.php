@extends('layouts.app')

@section('title', 'Alumnos del grupo')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">Grupo {{ $group->name }}</h4>
                            <div class="text-muted small">
                                {{ $activeCycle?->name ?? 'Sin ciclo activo' }}
                                @if(($assignments ?? collect())->isNotEmpty())
                                    - {{ $assignments->pluck('subject.name')->filter()->unique()->implode(', ') }}
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('teacher.students.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left mr-1"></i>Volver
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3 col-6 mb-2">
                            <div class="student-stat">
                                <div class="student-stat-value">{{ $students->count() }}</div>
                                <div class="student-stat-label">Alumnos activos</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="student-stat student-stat-info">
                                <div class="student-stat-value">{{ $assignments->count() }}</div>
                                <div class="student-stat-label">Asignaciones</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="student-stat student-stat-success">
                                <div class="student-stat-value">{{ $totalActivities }}</div>
                                <div class="student-stat-label">Actividades</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="student-stat student-stat-warning">
                                <div class="student-stat-value">{{ $attendanceStats->sum('total') }}</div>
                                <div class="student-stat-label">Registros de asistencia</div>
                            </div>
                        </div>
                    </div>

                    @if($students->isEmpty())
                        <div class="teacher-empty-state">
                            El grupo no tiene alumnos activos.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table data-datatable="true" class="table table-sm table-hover teacher-student-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Alumno</th>
                                        <th>Matrícula</th>
                                        <th class="text-center">Asistencia</th>
                                        <th class="text-center">Actividades</th>
                                        <th class="text-center">Promedio</th>
                                        <th class="text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($students as $student)
                                        @php
                                            $attendance = $attendanceStats[$student->id] ?? null;
                                            $attendanceTotal = (int) ($attendance->total ?? 0);
                                            $attended = (int) ($attendance->attended ?? 0);
                                            $attendancePercent = $attendanceTotal > 0 ? round(($attended / $attendanceTotal) * 100) : null;
                                            $activity = $activityStats[$student->id] ?? null;
                                            $delivered = (int) ($activity->delivered ?? 0);
                                            $average = $activity && $activity->average_score !== null ? round((float) $activity->average_score, 1) : null;
                                        @endphp
                                        <tr>
                                            <td>
                                                <a href="{{ route('teacher.students.show', $student) }}" class="font-weight-bold">
                                                    {{ $student->user->name }}
                                                </a>
                                            </td>
                                            <td class="text-muted">
                                                {{ $student->enrollment_number ?: '-' }}
                                            </td>
                                            <td class="text-center">
                                                @if($attendancePercent !== null)
                                                    <span class="metric-pill {{ $attendancePercent >= 80 ? 'metric-good' : 'metric-risk' }}">
                                                        {{ $attendancePercent }}%
                                                    </span>
                                                    <div class="text-muted small">{{ $attended }} / {{ $attendanceTotal }}</div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="metric-pill {{ $totalActivities > 0 && $delivered < $totalActivities ? 'metric-warn' : 'metric-good' }}">
                                                    {{ $delivered }} / {{ $totalActivities }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @if($average !== null)
                                                    <span class="metric-pill {{ $average >= 6 ? 'metric-good' : 'metric-risk' }}">
                                                        {{ $average }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                <a href="{{ route('teacher.students.show', $student) }}" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-search mr-1"></i>Detalle
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

@section('page_css')
<style>
    .student-stat {
        border: 1px solid #dee2e6;
        border-left: 4px solid #6c757d;
        border-radius: 4px;
        min-height: 76px;
        padding: .75rem;
    }

    .student-stat-info {
        border-left-color: #17a2b8;
    }

    .student-stat-success {
        border-left-color: #28a745;
    }

    .student-stat-warning {
        border-left-color: #ffc107;
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

    .teacher-empty-state {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 4px;
        color: #6c757d;
        padding: 1rem;
    }

    .teacher-student-table thead th {
        font-size: .78rem;
        text-transform: uppercase;
    }

    .metric-pill {
        border-radius: 4px;
        display: inline-block;
        font-weight: 700;
        min-width: 52px;
        padding: .2rem .45rem;
    }

    .metric-good {
        background: #d4edda;
        color: #155724;
    }

    .metric-warn {
        background: #fff3cd;
        color: #7a4d00;
    }

    .metric-risk {
        background: #f8d7da;
        color: #721c24;
    }

    @media (max-width: 767.98px) {
        .teacher-student-table td,
        .teacher-student-table th {
            white-space: nowrap;
        }
    }
</style>
@endsection
