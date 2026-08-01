@extends('layouts.app')

@section('title', 'Dashboard profesor')

@section('content')
@foreach (['success', 'info', 'warning', 'danger'] as $type)
    @if(session($type))
        <div class="alert alert-{{ $type }} alert-dismissible fade show" role="alert">
            <strong>{{ session($type) }}</strong>
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
@endforeach

<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Panel docente</h3>
                </div>
                <div class="card-body">
                    @php
                        $todayClasses = $todayClasses ?? collect();
                        $pendingAttendanceCount = $todayClasses
                            ->filter(fn ($class) => ! $class->attendance_registered && ! $class->attendance_closed)
                            ->count();
                        $pendingActivityCount = $todayClasses
                            ->filter(fn ($class) => ! $class->activity_assigned && ! $class->attendance_closed)
                            ->count();
                        $completedClassCount = $todayClasses
                            ->filter(fn ($class) => ($class->attendance_registered || $class->attendance_closed) && $class->activity_assigned)
                            ->count();
                    @endphp

                    <div class="teacher-day-header mb-3">
                        <div>
                            <h4 class="font-weight-bold mb-1">Buen dia, {{ auth()->user()->name }}</h4>
                            <div class="text-muted small">{{ now()->translatedFormat('l d \\d\\e F') }}</div>
                        </div>
                        @if(($pendingDocumentItems ?? 0) > 0)
                            <a href="{{ route('teacher.document-requests.index') }}" class="teacher-document-pill">
                                {{ $pendingDocumentItems }} documento(s) pendiente(s)
                            </a>
                        @endif
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3 col-6 mb-2">
                            <div class="teacher-stat">
                                <div class="teacher-stat-value">{{ $todayClasses->count() }}</div>
                                <div class="teacher-stat-label">Clases hoy</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="teacher-stat teacher-stat-warning">
                                <div class="teacher-stat-value">{{ $pendingAttendanceCount }}</div>
                                <div class="teacher-stat-label">Asistencias pendientes</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="teacher-stat teacher-stat-info">
                                <div class="teacher-stat-value">{{ $pendingActivityCount }}</div>
                                <div class="teacher-stat-label">Actividades pendientes</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="teacher-stat teacher-stat-success">
                                <div class="teacher-stat-value">{{ $completedClassCount }}</div>
                                <div class="teacher-stat-label">Clases completas</div>
                            </div>
                        </div>
                    </div>

                    <section class="teacher-section mb-3">
                        <div class="teacher-section-header">
                            <h5 class="mb-0">Hoy</h5>
                            <a href="{{ route('teacher.classes.index') }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-calendar-alt mr-1"></i>Ver semana
                            </a>
                        </div>

                        @if($todayClasses->isEmpty())
                            <div class="teacher-empty-state">
                                No tienes clases programadas para hoy.
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm teacher-today-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Horario</th>
                                            <th>Clase</th>
                                            <th class="text-center">Asistencia</th>
                                            <th class="text-center">Actividad</th>
                                            <th class="text-right">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($todayClasses as $class)
                                            @php
                                                $attendanceBadge = $class->attendance_closed
                                                    ? ['Cerrada', 'secondary']
                                                    : ($class->attendance_registered ? ['Tomada', 'success'] : ['Pendiente', 'warning']);
                                                $activityBadge = $class->activity_assigned
                                                    ? ['Registrada', 'success']
                                                    : ($class->attendance_closed ? ['Cerrada', 'secondary'] : ['Pendiente', 'info']);
                                            @endphp
                                            <tr>
                                                <td class="teacher-time">{{ $class->time }}</td>
                                                <td>
                                                    <div class="font-weight-bold">{{ $class->subject }}</div>
                                                    <div class="text-muted small">Grupo {{ $class->group }}</div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge badge-{{ $attendanceBadge[1] }}">{{ $attendanceBadge[0] }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge badge-{{ $activityBadge[1] }}">{{ $activityBadge[0] }}</span>
                                                </td>
                                                <td class="text-right teacher-actions">
                                                    @if($class->attendance_closed)
                                                        <span class="btn btn-sm btn-secondary disabled">Asistencia</span>
                                                    @elseif($class->attendance_registered)
                                                        <a href="{{ route('attendance.edit', $class->session_id) }}" class="btn btn-sm btn-success">
                                                            <i class="fas fa-clipboard-check mr-1"></i>Editar lista
                                                        </a>
                                                    @else
                                                        <a href="{{ route('attendance.take', $class->session_id) }}" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-clipboard-list mr-1"></i>Tomar lista
                                                        </a>
                                                    @endif

                                                    <a href="{{ route('attendance.massive', ['assignment' => $class->assignment_id, 'mode' => 'week', 'date' => now()->toDateString()]) }}" class="btn btn-sm btn-dark">
                                                        <i class="fas fa-table mr-1"></i>Hoja semanal
                                                    </a>
                                                    <a href="{{ route('session.activities.massive', ['assignment' => $class->assignment_id, 'mode' => 'week', 'date' => now()->toDateString()]) }}" class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit mr-1"></i>Actividades
                                                    </a>

                                                    @if($class->attendance_closed)
                                                        <span class="btn btn-sm btn-secondary disabled">Actividad</span>
                                                    @elseif($class->activity_assigned)
                                                        <a href="{{ route('session.activities.create', $class->session_id) }}" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-tasks mr-1"></i>Editar actividad
                                                        </a>
                                                    @else
                                                        <a href="{{ route('session.activities.create', $class->session_id) }}" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-tasks mr-1"></i>Registrar actividad
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>

                    <section class="teacher-section mb-3">
                        <div class="teacher-section-header">
                            <h5 class="mb-0">Accesos rapidos</h5>
                        </div>
                        <div class="row">
                            <div class="col-md-3 col-sm-6 mb-2">
                                <a href="{{ route('teacher.classes.index') }}" class="btn btn-primary btn-block">
                                    <i class="fas fa-chalkboard-teacher mr-1"></i>Mis clases
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <a href="{{ route('teacher.evaluation.index') }}" class="btn btn-success btn-block">
                                    <i class="fas fa-tasks mr-1"></i>Evaluacion
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <a href="{{ route('teacher.didactic-plans.index') }}" class="btn btn-info btn-block">
                                    <i class="fas fa-file-alt mr-1"></i>Planeaciones
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <a href="{{ route('teacher.students.index') }}" class="btn btn-secondary btn-block">
                                    <i class="fas fa-users mr-1"></i>Mis alumnos
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <a href="{{ route('teacher.follow-ups.index') }}" class="btn btn-warning btn-block">
                                    <i class="fas fa-bullhorn mr-1"></i>Avisos
                                </a>
                            </div>
                        </div>
                    </section>

                    <section class="teacher-section mb-0">
                        <div class="teacher-section-header">
                            <h5 class="mb-0">Avisos institucionales</h5>
                        </div>
                        @if($notifications->count())
                            @foreach($notifications as $note)
                                <div class="teacher-notice">
                                    <div class="small font-weight-bold">{{ $note->title ?? 'Aviso' }}</div>
                                    <div class="small">{{ $note->message }}</div>
                                    <div class="text-muted small">{{ $note->date ?? '' }}</div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-muted small">No hay avisos por ahora.</div>
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
    .teacher-day-header {
        align-items: flex-start;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        gap: 12px;
        justify-content: space-between;
        padding-bottom: 14px;
    }

    .teacher-document-pill {
        background: #fff3cd;
        border: 1px solid #ffe08a;
        border-radius: 4px;
        color: #7a4d00;
        display: inline-block;
        font-size: 0.85rem;
        font-weight: 700;
        padding: 0.4rem 0.55rem;
        white-space: nowrap;
    }

    .teacher-stat {
        border: 1px solid #dee2e6;
        border-left: 4px solid #6c757d;
        border-radius: 4px;
        min-height: 76px;
        padding: 0.75rem;
    }

    .teacher-stat-warning {
        border-left-color: #ffc107;
    }

    .teacher-stat-info {
        border-left-color: #17a2b8;
    }

    .teacher-stat-success {
        border-left-color: #28a745;
    }

    .teacher-stat-value {
        font-size: 1.45rem;
        font-weight: 800;
        line-height: 1;
    }

    .teacher-stat-label {
        color: #6c757d;
        font-size: 0.82rem;
        margin-top: 0.35rem;
    }

    .teacher-section {
        border-top: 1px solid #e9ecef;
        padding-top: 14px;
    }

    .teacher-section-header {
        align-items: center;
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .teacher-empty-state {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 4px;
        color: #6c757d;
        padding: 1rem;
    }

    .teacher-today-table thead th {
        background: #f8f9fa;
        border-top: 0;
        color: #495057;
        font-size: 0.78rem;
        text-transform: uppercase;
    }

    .teacher-time {
        color: #343a40;
        font-weight: 700;
        white-space: nowrap;
    }

    .teacher-actions {
        white-space: nowrap;
    }

    .teacher-actions .btn {
        margin-bottom: 0.25rem;
        margin-left: 0.25rem;
    }

    .teacher-notice {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 4px;
        margin-bottom: 0.5rem;
        padding: 0.65rem 0.75rem;
    }

    @media (max-width: 767.98px) {
        .teacher-day-header,
        .teacher-section-header {
            align-items: stretch;
            flex-direction: column;
        }

        .teacher-document-pill,
        .teacher-section-header .btn {
            text-align: center;
            white-space: normal;
        }

        .teacher-actions {
            text-align: left !important;
            white-space: normal;
        }

        .teacher-actions .btn {
            display: block;
            margin-left: 0;
            width: 100%;
        }
    }
</style>
@endsection
