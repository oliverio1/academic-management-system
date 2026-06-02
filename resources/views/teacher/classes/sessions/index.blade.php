@extends('layouts.app')

@section('title', 'Sesiones')

@section('content')
    @if(session('info'))
        <div class="alert alert-primary" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning" role="alert">
            <strong>{{ session('warning') }}</strong>
        </div>
    @endif
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-6">
                                <h4 class="mb-0">
                                    {{ $assignment->subject->name }} - {{ $assignment->group->name }}
                                </h4>
                            </div>
                            <div class="col-sm-6">
                                <a href="{{ route('teacher.evaluation.manual.create', $assignment) }}"
                                   class="btn btn-primary float-right ml-2">
                                    Nueva actividad manual
                                </a>
                                <a href="{{ route('teacher.classes.kardex', $assignment) }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="btn btn-outline-danger float-right ml-2">
                                    Ver Kardex
                                </a>
                                <a href="{{ route('teacher.classes.index') }}" class="btn btn-secondary float-right">
                                    Volver
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-2">Sesiones</h6>
                                <div class="table-responsive">
                                    <table data-datatable="true" class="table table-sm table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Horario</th>
                                                <th class="text-center">Asistencia</th>
                                                <th class="text-center">Actividad</th>
                                                <th>Rubro</th>
                                                <th>Temario</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($sessions as $session)
                                                @php
                                                    $periodDisabled = $session->academicPeriod && ! $session->academicPeriod->is_active;
                                                    $classStart = \Carbon\Carbon::parse(
                                                        $session->session_date->toDateString().' '.substr((string) $session->start_time, 0, 8)
                                                    );
                                                    $attendanceAllowedFrom = $classStart->copy()->subMinutes(10);
                                                    $attendanceWindowOpen = now()->greaterThanOrEqualTo($attendanceAllowedFrom);
                                                @endphp
                                                <tr>
                                                    <td>{{ $session->session_date->translatedFormat('l j \\d\\e F') }}</td>
                                                    <td>{{ substr($session->start_time, 0, 5) }} - {{ substr($session->end_time, 0, 5) }}</td>
                                                    <td class="text-center">
                                                        @if($periodDisabled)
                                                            <a href="{{ route('attendance.take', $session->id) }}" class="btn btn-outline-secondary btn-sm">Consultar</a>
                                                        @elseif($session->attendance_closed_at)
                                                            <span class="text-muted">Cerrada</span>
                                                        @elseif($session->attendances_count > 0)
                                                            <a href="{{ route('attendance.edit', $session->id) }}" class="btn btn-outline-success btn-sm">Registrada</a>
                                                        @elseif(! $attendanceWindowOpen)
                                                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Disponible desde {{ $attendanceAllowedFrom->format('d/m/Y H:i') }}">Tomar</button>
                                                            <div class="small text-muted mt-1">Disponible desde {{ $attendanceAllowedFrom->format('H:i') }}</div>
                                                        @else
                                                            <a href="{{ route('attendance.take', $session->id) }}" class="btn btn-warning btn-sm">Tomar</a>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        @if($periodDisabled)
                                                            <a href="{{ route('session.activities.create', $session->id) }}" class="btn btn-outline-secondary btn-sm">Consultar</a>
                                                        @elseif($session->attendance_closed_at)
                                                            <span class="text-muted">Cerrada</span>
                                                        @elseif($session->session_activity_count > 0)
                                                            <a href="{{ route('session.activities.create', $session->id) }}" class="btn btn-outline-success btn-sm">Editar</a>
                                                        @else
                                                            <a href="{{ route('session.activities.create', $session->id) }}" class="btn btn-outline-primary btn-sm">Asignar</a>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($session->sessionActivity)
                                                            {{ optional($session->sessionActivity->evaluationCriterion)->name ?? 'Sin rubro' }}
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @php
                                                            $resume = $session->temario_resume ?? ['unit' => '-', 'topic' => '-', 'subtopics_count' => 0];
                                                        @endphp
                                                        <div><strong>Unidad:</strong> {{ $resume['unit'] }}</div>
                                                        <div><strong>Tema:</strong> {{ $resume['topic'] }}</div>
                                                        <div><strong>Subtemas:</strong> {{ $resume['subtopics_count'] }}</div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-2">Actividades sin sesión</h6>
                                @if($manualActivities->isEmpty())
                                    <div class="alert alert-light border mb-0">
                                        No hay actividades manuales registradas.
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <table data-datatable="true" class="table table-sm table-hover mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Actividad</th>
                                                    <th>Rubro</th>
                                                    <th>Periodo</th>
                                                    <th>Entrega</th>
                                                    <th class="text-center">Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($manualActivities as $activity)
                                                    <tr>
                                                        <td>{{ $activity->title }}</td>
                                                        <td>{{ optional($activity->evaluationCriterion)->name ?? 'Sin rubro' }}</td>
                                                        <td>{{ optional($activity->academicPeriod)->name ?? '-' }}</td>
                                                        <td>{{ $activity->due_date ? $activity->due_date->format('Y-m-d') : '-' }}</td>
                                                        <td class="text-center">
                                                            <a href="{{ route('activities.grade', $activity) }}" class="btn btn-outline-primary btn-sm">
                                                                Evaluar
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
        </div>
    </div>
@endsection

@section('page_css')
@endsection

@section('page_scripts')
@endsection
