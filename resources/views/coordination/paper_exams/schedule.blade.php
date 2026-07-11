@extends('layouts.app')

@section('title', 'Horarios de exámenes')

@section('content')
@php
    $normalizeSubjectName = function (?string $name): string {
        $value = trim((string) $name);

        return $value !== '' ? $value : '-';
    };
@endphp

<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Calendario de exámenes</h4>
                <small class="text-muted">Selecciona grupo, da click en un horario y captura la fecha.</small>
            </div>
        </div>

        <div class="card-body">
            @if(session('info'))
                <div class="alert alert-success">
                    {{ session('info') }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!$activeCycle)
                <div class="alert alert-warning mb-0">
                    No hay ciclo activo configurado.
                </div>
            @else
                <form method="GET" action="{{ route('coordination.paper-exams.schedule') }}" class="mb-3">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-5 mb-0">
                            <label>Grupo</label>
                            <select name="cycle_group_id" class="form-control" onchange="this.form.submit()">
                                @foreach($cycleGroups as $cycleGroup)
                                    <option value="{{ $cycleGroup->id }}" {{ optional($selectedCycleGroup)->id === $cycleGroup->id ? 'selected' : '' }}>
                                        {{ $cycleGroup->group->name ?? 'N/D' }}
                                        @if($cycleGroup->group?->level?->name)
                                            - {{ $cycleGroup->group->level->name }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-7 mb-0">
                            <div class="alert alert-info py-2 mb-0">
                                <strong>Ciclo activo:</strong> {{ $activeCycle->name }} ({{ $activeCycle->code }})
                            </div>
                        </div>
                    </div>
                </form>

                @if($cycleGroups->isEmpty())
                    <div class="alert alert-secondary mb-0">
                        No hay grupos activos para el campus seleccionado.
                    </div>
                @elseif(!$selectedCycleGroup)
                    <div class="alert alert-secondary mb-0">
                        Selecciona un grupo para consultar su calendario.
                    </div>
                @elseif(!$calendar || $calendar['timeSlots']->isEmpty())
                    <div class="alert alert-secondary mb-0">
                        El grupo seleccionado no tiene horarios registrados.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm exam-calendar mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 95px;">Hora</th>
                                    @foreach($dayOptions as $dayKey => $dayLabel)
                                        <th>{{ $dayLabel }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($calendar['timeSlots'] as $slot)
                                    <tr>
                                        <td class="exam-calendar-time">
                                            <strong>{{ $slot['start'] }}</strong><br>
                                            <small>{{ $slot['end'] }}</small>
                                        </td>
                                        @foreach($dayOptions as $dayKey => $dayLabel)
                                            @php
                                                $cells = $calendar['matrix'][$slot['key']][$dayKey] ?? collect();
                                            @endphp
                                            <td class="{{ $cells->isNotEmpty() ? 'exam-calendar-cell' : 'text-muted text-center align-middle' }}">
                                                @forelse($cells as $schedule)
                                                    @php
                                                        $assignment = $schedule->assignment;
                                                        $assignmentExams = $examsByAssignment->get((int) $assignment->id, collect());
                                                        $subjectScheduled = $scheduledSubjectIds->contains((int) $assignment->subject_id);
                                                        $subjectScheduledExams = $scheduledExamsBySubject->get((int) $assignment->subject_id, collect());
                                                    @endphp

                                                    <button
                                                        type="button"
                                                        class="exam-schedule-entry {{ $subjectScheduled ? 'is-disabled' : '' }}"
                                                        style="{{ $subjectScheduled ? '' : 'background-color: ' . subjectColor($assignment->subject_id) . ';' }}"
                                                        data-schedule-id="{{ $schedule->id }}"
                                                        data-subject="{{ $normalizeSubjectName($assignment->subject->name ?? '') }}"
                                                        data-teacher="{{ $assignment->teacher->user->name ?? '-' }}"
                                                        data-day-label="{{ $dayLabel }}"
                                                        data-start="{{ substr((string) $schedule->start_time, 0, 5) }}"
                                                        data-end="{{ substr((string) $schedule->end_time, 0, 5) }}"
                                                        {{ $subjectScheduled ? 'disabled' : '' }}
                                                    >
                                                        <strong>{{ $normalizeSubjectName($assignment->subject->name ?? '') }}</strong>
                                                        <span>{{ $assignment->teacher->user->name ?? '-' }}</span>
                                                        @if($subjectScheduled)
                                                            <small>Examen programado</small>
                                                            @foreach($subjectScheduledExams->take(2) as $exam)
                                                                <em>{{ optional($exam->online_available_from)->format('d/m H:i') }}</em>
                                                            @endforeach
                                                        @else
                                                            <small>Click para programar</small>
                                                        @endif
                                                    </button>
                                                @empty
                                                    -
                                                @endforelse
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <h5 class="mb-3">Asignaciones programadas</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Materia</th>
                                        <th>Día</th>
                                        <th>Hora</th>
                                        <th>Fecha programada</th>
                                        <th class="text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($scheduledExamsBySubject as $subjectId => $subjectExams)
                                        @php
                                            $exam = $subjectExams->sortByDesc('online_available_from')->first();
                                            $assignment = $exam?->assignment;
                                            $scheduledFrom = $exam?->online_available_from;
                                            $scheduledDayKey = $scheduledFrom ? [
                                                1 => 'monday',
                                                2 => 'tuesday',
                                                3 => 'wednesday',
                                                4 => 'thursday',
                                                5 => 'friday',
                                            ][$scheduledFrom->dayOfWeek] ?? null : null;
                                            $scheduleForExam = $calendar['schedules']->first(function ($schedule) use ($assignment, $scheduledFrom, $scheduledDayKey) {
                                                return (int) $schedule->teaching_assignment_id === (int) ($assignment?->id ?? 0)
                                                    && $scheduledFrom
                                                    && $schedule->day_of_week === $scheduledDayKey
                                                    && substr((string) $schedule->start_time, 0, 5) === $scheduledFrom->format('H:i');
                                            }) ?: $calendar['schedules']->firstWhere('teaching_assignment_id', $assignment?->id);
                                            $dayLabel = $scheduleForExam ? ($dayOptions[$scheduleForExam->day_of_week] ?? '-') : '-';
                                            $start = $scheduleForExam ? substr((string) $scheduleForExam->start_time, 0, 5) : optional($exam?->online_available_from)->format('H:i');
                                            $end = $scheduleForExam ? substr((string) $scheduleForExam->end_time, 0, 5) : optional($exam?->online_available_until)->format('H:i');
                                        @endphp
                                        <tr>
                                            <td>{{ $normalizeSubjectName($assignment?->subject?->name ?? '') }}</td>
                                            <td>{{ $dayLabel }}</td>
                                            <td>{{ $start }} a {{ $end }}</td>
                                            <td>{{ optional($exam?->online_available_from)->format('d/m/Y H:i') ?: '-' }}</td>
                                            <td class="text-right">
                                                @if($scheduleForExam)
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-primary btn-sm exam-edit-button"
                                                        data-schedule-id="{{ $scheduleForExam->id }}"
                                                        data-subject="{{ $normalizeSubjectName($assignment?->subject?->name ?? '') }}"
                                                        data-teacher="{{ $assignment?->teacher?->user?->name ?? '-' }}"
                                                        data-day-label="{{ $dayLabel }}"
                                                        data-start="{{ $start }}"
                                                        data-end="{{ $end }}"
                                                        data-date="{{ optional($exam?->online_available_from)->format('Y-m-d') }}"
                                                    >
                                                        Modificar
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">
                                                Todavía no hay exámenes programados para este grupo.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="examScheduleModal" tabindex="-1" role="dialog" aria-labelledby="examScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" action="{{ route('coordination.paper-exams.schedule.update') }}" class="modal-content">
            @csrf
            @method('PUT')
            <input type="hidden" name="schedule_id" id="modal_schedule_id">

            <div class="modal-header">
                <h5 class="modal-title" id="examScheduleModalLabel">Programar examen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <strong id="modal_subject">Materia</strong>
                    <div class="text-muted" id="modal_teacher">Profesor</div>
                    <div class="small text-muted" id="modal_time">Horario</div>
                </div>

                <div class="form-group mb-0">
                    <label>Fecha del examen</label>
                    <input type="date" name="exam_date" id="modal_exam_date" class="form-control" required>
                    <small class="form-text text-muted" id="modal_date_hint">
                        El horario se tomará del bloque seleccionado.
                    </small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary">Guardar fecha</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('page_css')
<style>
    .exam-calendar th,
    .exam-calendar td {
        vertical-align: middle !important;
    }

    .exam-calendar-time {
        background: #f8f9fa;
        text-align: center;
        white-space: nowrap;
    }

    .exam-calendar-cell {
        min-width: 190px;
    }

    .exam-schedule-entry {
        border: 1px solid rgba(0, 0, 0, .08);
        border-radius: .4rem;
        color: #111827;
        cursor: pointer;
        display: block;
        margin-bottom: .35rem;
        padding: .45rem .55rem;
        text-align: left;
        width: 100%;
    }

    .exam-schedule-entry:last-child {
        margin-bottom: 0;
    }

    .exam-schedule-entry strong,
    .exam-schedule-entry span,
    .exam-schedule-entry small,
    .exam-schedule-entry em {
        display: block;
    }

    .exam-schedule-entry span,
    .exam-schedule-entry small {
        color: #4b5563;
        font-size: .8rem;
    }

    .exam-schedule-entry em {
        color: #155724;
        font-size: .78rem;
        font-style: normal;
        font-weight: 600;
    }

    .exam-schedule-entry.is-disabled {
        background: #f1f3f5;
        border-style: dashed;
        color: #6c757d;
        cursor: not-allowed;
        opacity: .9;
    }

    .exam-schedule-entry.is-disabled em {
        color: #6c757d;
    }
</style>
@endsection

@section('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('examScheduleModal');
    if (!modal) return;

    const scheduleIdInput = document.getElementById('modal_schedule_id');
    const subjectText = document.getElementById('modal_subject');
    const teacherText = document.getElementById('modal_teacher');
    const timeText = document.getElementById('modal_time');
    const dateHint = document.getElementById('modal_date_hint');
    const examDateInput = document.getElementById('modal_exam_date');

    function openScheduleModal(button) {
            scheduleIdInput.value = button.dataset.scheduleId || '';
            subjectText.textContent = button.dataset.subject || 'Materia';
            teacherText.textContent = button.dataset.teacher || '';
            timeText.textContent = (button.dataset.dayLabel || '') + ' - ' + (button.dataset.start || '') + ' a ' + (button.dataset.end || '');
            dateHint.textContent = 'Selecciona una fecha que corresponda a ' + (button.dataset.dayLabel || 'este dia') + '. El horario se tomara del bloque seleccionado.';
            examDateInput.value = button.dataset.date || '';

            if (window.$) {
                $('#examScheduleModal').modal('show');
            }
    }

    document.querySelectorAll('.exam-schedule-entry:not(.is-disabled), .exam-edit-button').forEach(function (button) {
        button.addEventListener('click', function () {
            openScheduleModal(button);
        });
    });
});
</script>
@endsection
