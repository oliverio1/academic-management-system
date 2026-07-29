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
                                   class="btn btn-danger float-right ml-2">
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
                                @php
                                    $hasSessionsWithoutCriteria = $sessions->contains(
                                        fn ($session) => ! (bool) ($periodHasCriteria[(int) $session->teaching_assignment_id.'|'.(int) $session->academic_period_id] ?? false)
                                    );
                                @endphp
                                @if($hasSessionsWithoutCriteria)
                                    <div class="activity-rubric-warning mb-2">
                                        Configura los rubros de evaluacion para registrar actividades.
                                    </div>
                                @endif
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
                                                    $scheduleType = (string) ($session->schedule?->type ?? '');
                                                    $normalizedScheduleType = mb_strtolower(trim($scheduleType));
                                                    $sectionNumber = (int) ($session->schedule?->section_number ?? $session->teachingAssignment?->section_number ?? 0);
                                                    $sectionLabel = $sectionNumber > 0 && $sectionNumber <= 26
                                                        ? 'Sección '.chr(64 + $sectionNumber)
                                                        : ($sectionNumber > 0 ? 'Sección '.$sectionNumber : null);
                                                    $typeLabel = match ($normalizedScheduleType) {
                                                        'laboratory' => 'Laboratorio',
                                                        'workshop' => 'Taller',
                                                        'dividida' => 'Clase dividida',
                                                        'dividido' => 'Clase dividida',
                                                        'theory' => 'Teoría',
                                                        'grupo completo' => 'Grupo completo',
                                                        default => $scheduleType ? ucfirst($scheduleType) : null,
                                                    };
                                                    $isSectionedSession = in_array($normalizedScheduleType, ['laboratory', 'workshop', 'dividida', 'dividido'], true)
                                                        || ($sectionNumber > 1 && ($relatedAssignments ?? collect())->count() > 1);
                                                    $periodDisabled = $session->academicPeriod && ! $session->academicPeriod->is_active;
                                                    $classStart = \Carbon\Carbon::parse(
                                                        $session->session_date->toDateString().' '.substr((string) $session->start_time, 0, 8)
                                                    );
                                                    $sessionCycleCode = (string) (
                                                        $session->teachingAssignment?->schoolCycleGroup?->schoolCycle?->code
                                                        ?? $session->schedule?->schoolCycle?->code
                                                        ?? ''
                                                    );
                                                    $attendanceEditingOpenForTesting = $sessionCycleCode !== ''
                                                        && in_array($sessionCycleCode, $editableAttendanceCycleCodes ?? [], true);
                                                    $attendanceAllowedFrom = $classStart->copy()->subMinutes(10);
                                                    $attendanceWindowOpen = $attendanceEditingOpenForTesting
                                                        || ($allowFutureAttendanceCapture ?? false)
                                                        || now()->greaterThanOrEqualTo($attendanceAllowedFrom);
                                                    $criteriaKey = (int) $session->teaching_assignment_id.'|'.(int) $session->academic_period_id;
                                                    $hasCriteriaForPeriod = (bool) ($periodHasCriteria[$criteriaKey] ?? false);
                                                @endphp
                                                <tr class="{{ $isSectionedSession ? 'session-row-sectioned' : '' }}">
                                                    <td>
                                                        {{ $session->session_date->translatedFormat('l j \\d\\e F') }}
                                                        @if($isSectionedSession && $sectionLabel)
                                                            <span class="session-section-badge">{{ $sectionLabel }}</span>
                                                        @endif
                                                        @if($isSectionedSession && $typeLabel)
                                                            <div class="small text-muted mt-1">{{ $typeLabel }}</div>
                                                        @endif
                                                    </td>
                                                    <td>{{ substr($session->start_time, 0, 5) }} - {{ substr($session->end_time, 0, 5) }}</td>
                                                    <td class="text-center">
                                                        @if($periodDisabled)
                                                            <a href="{{ route('attendance.take', $session->id) }}" class="btn btn-secondary btn-sm">Consultar</a>
                                                        @elseif($session->attendance_closed_at && $attendanceEditingOpenForTesting)
                                                            <a href="{{ route('attendance.edit', $session->id) }}" class="btn btn-success btn-sm">Editar</a>
                                                            <div class="small text-muted mt-1">Ciclo de prueba</div>
                                                        @elseif($session->attendance_closed_at)
                                                            <span class="text-muted">Cerrada</span>
                                                        @elseif($session->attendances_count > 0)
                                                            <a href="{{ route('attendance.edit', $session->id) }}" class="btn btn-success btn-sm">Registrada</a>
                                                        @elseif(! $attendanceWindowOpen)
                                                            <button type="button" class="btn btn-secondary btn-sm" disabled title="Disponible desde {{ $attendanceAllowedFrom->format('d/m/Y H:i') }}">Tomar</button>
                                                            <div class="small text-muted mt-1">Disponible desde {{ $attendanceAllowedFrom->format('H:i') }}</div>
                                                        @else
                                                            <a href="{{ route('attendance.take', $session->id) }}" class="btn btn-warning btn-sm">Tomar</a>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        @if($periodDisabled)
                                                            <a href="{{ route('session.activities.create', $session->id) }}" class="btn btn-secondary btn-sm">Consultar</a>
                                                        @elseif($session->attendance_closed_at)
                                                            <span class="text-muted">Cerrada</span>
                                                        @elseif($session->session_activity_count > 0)
                                                            <a href="{{ route('session.activities.create', $session->id) }}" class="btn btn-success btn-sm">Editar</a>
                                                        @elseif(! $hasCriteriaForPeriod)
                                                            <button type="button" class="btn btn-secondary btn-sm" disabled>Asignar</button>
                                                        @else
                                                            <a href="{{ route('session.activities.create', $session->id) }}" class="btn btn-primary btn-sm">Asignar</a>
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
                                                            <a href="{{ route('activities.grade', $activity) }}" class="btn btn-primary btn-sm">
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
<style>
    .activity-rubric-warning {
        background: #fff3cd;
        border: 1px solid #ffec99;
        border-left: 4px solid #f59f00;
        border-radius: 4px;
        color: #7a4d00;
        font-size: 0.86rem;
        font-weight: 700;
        line-height: 1.2;
        padding: 0.55rem 0.7rem;
    }
    .session-row-sectioned {
        background-color: #eef7ff;
    }
    .session-row-sectioned:hover {
        background-color: #e1f0ff;
    }
    .session-section-badge {
        background: #0070C0;
        border-radius: 3px;
        color: #fff;
        display: inline-block;
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1;
        margin-left: 0.35rem;
        padding: 0.22rem 0.35rem;
        white-space: nowrap;
    }
</style>
@endsection

@section('page_scripts')
@endsection
