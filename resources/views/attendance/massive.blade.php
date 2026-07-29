@extends('layouts.app')

@section('title', 'Asistencia masiva')

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
    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Asistencia masiva</h4>
                    <div class="text-muted small">
                        {{ $assignment->subject->name }} | Grupo {{ $assignment->group->name }}
                    </div>
                </div>
                <a href="{{ route('teacher.classes.sessions.index', $assignment) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i>Sesiones
                </a>
            </div>
        </div>

        <div class="card-body">
            @if($testCycleEditing)
                <div class="alert alert-info">
                    Ciclo de prueba: esta hoja permite registrar y editar asistencias sin candados.
                </div>
            @endif

            <form method="GET" action="{{ route('attendance.massive', $assignment) }}" class="massive-filter mb-3">
                <div class="form-row align-items-end">
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold mb-1" for="mode">Vista</label>
                        <select id="mode" name="mode" class="form-control form-control-sm">
                            <option value="week" {{ $mode === 'week' ? 'selected' : '' }}>Semana</option>
                            <option value="month" {{ $mode === 'month' ? 'selected' : '' }}>Mes</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold mb-1" for="date">Fecha base</label>
                        <input id="date" type="date" name="date" class="form-control form-control-sm" value="{{ $anchorDate->toDateString() }}">
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <button class="btn btn-primary btn-sm btn-block">
                            <i class="fas fa-filter mr-1"></i>Aplicar
                        </button>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2 text-md-right">
                        <div class="small text-muted">Periodo visible</div>
                        <div class="font-weight-bold">{{ $from->format('d/m/Y') }} - {{ $to->format('d/m/Y') }}</div>
                    </div>
                </div>
            </form>

            <div class="massive-legend mb-2">
                <span class="legend-item status-present">1/A Asistencia</span>
                <span class="legend-item status-absent">0/F Falta</span>
                <span class="legend-item status-justified">2/J Justificada</span>
                <span class="legend-item status-late">3/R Retardo</span>
            </div>

            @if($sessions->isEmpty())
                <div class="alert alert-light border mb-0">
                    No hay sesiones programadas en el rango seleccionado.
                </div>
            @else
                <form method="POST" action="{{ route('attendance.massive.store', $assignment) }}" id="massiveAttendanceForm">
                    @csrf
                    <input type="hidden" name="mode" value="{{ $mode }}">
                    <input type="hidden" name="date" value="{{ $anchorDate->toDateString() }}">

                    <div class="massive-grid-wrap">
                        <table class="table table-sm table-bordered massive-grid mb-0">
                            <thead>
                                <tr>
                                    <th class="student-col">Alumno</th>
                                    @foreach($sessions as $session)
                                        @php
                                            $lock = $sessionLocks[(int) $session->id] ?? ['locked' => false, 'reason' => null];
                                        @endphp
                                        <th class="session-col {{ $lock['locked'] ? 'session-locked' : '' }}">
                                            <div>{{ $session->session_date->format('d/m') }}</div>
                                            <div class="small">{{ substr((string) $session->start_time, 0, 5) }}</div>
                                            @if($session->sessionActivity)
                                                <a href="{{ route('session.activities.create', $session) }}" class="session-activity-link">
                                                    Actividad
                                                </a>
                                            @else
                                                <a href="{{ route('session.activities.create', $session) }}" class="session-activity-link text-muted">
                                                    Sin actividad
                                                </a>
                                            @endif
                                            @if($lock['locked'])
                                                <div class="small text-danger">{{ $lock['reason'] }}</div>
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($students as $student)
                                    <tr>
                                        <th class="student-col">
                                            <div class="student-name">{{ $student->user->name }}</div>
                                        </th>
                                        @foreach($sessions as $session)
                                            @php
                                                $key = (int) $session->id.'|'.(int) $student->id;
                                                $record = $attendance->get($key);
                                                $status = $record->status ?? 'present';
                                                $lock = $sessionLocks[(int) $session->id] ?? ['locked' => false, 'reason' => null];
                                                $rowLocked = $lock['locked']
                                                    || (! $testCycleEditing && $record && ((bool) $record->is_suspension_locked || $record->status === 'justified'));
                                            @endphp
                                            <td
                                                class="attendance-matrix-cell"
                                                tabindex="{{ $rowLocked ? '-1' : '0' }}"
                                                data-status="{{ $status }}"
                                                data-locked="{{ $rowLocked ? '1' : '0' }}"
                                                data-input-name="attendance[{{ $session->id }}][{{ $student->id }}]"
                                                title="1/A asistencia, 0/F falta, 2/J justificada, 3/R retardo">
                                                <input type="hidden"
                                                    name="attendance[{{ $session->id }}][{{ $student->id }}]"
                                                    value="{{ $status }}"
                                                    {{ $rowLocked ? 'disabled' : '' }}>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
                        <div class="text-muted small mb-2">
                            Click para alternar. Teclado: 1/A, 0/F, 2/J, 3/R. Enter o flechas avanzan por la hoja.
                        </div>
                        <button class="btn btn-primary mb-2" id="massiveSubmitButton">
                            <i class="fas fa-save mr-1"></i>Guardar asistencia
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection

@section('page_css')
<style>
    .massive-filter {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 4px;
        padding: 0.75rem;
    }

    .massive-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .legend-item {
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 700;
        padding: 0.3rem 0.45rem;
    }

    .massive-grid-wrap {
        border: 1px solid #dee2e6;
        max-height: 72vh;
        overflow: auto;
    }

    .massive-grid th {
        background: #f8f9fa;
    }

    .massive-grid thead th {
        border-bottom: 1px solid #cfd4da;
        position: sticky;
        top: 0;
        z-index: 3;
    }

    .student-col {
        left: 0;
        min-width: 240px;
        position: sticky;
        z-index: 2;
    }

    thead .student-col {
        z-index: 4;
    }

    .student-name {
        font-size: 0.86rem;
        line-height: 1.15;
    }

    .session-col {
        min-width: 118px;
        text-align: center;
        vertical-align: top;
    }

    .session-locked {
        background: #f1f3f5 !important;
    }

    .session-activity-link {
        display: block;
        font-size: 0.72rem;
        font-weight: 700;
        margin-top: 0.15rem;
        text-decoration: underline;
    }

    .attendance-matrix-cell {
        cursor: cell;
        font-weight: 800;
        min-width: 72px;
        text-align: center;
        user-select: none;
        vertical-align: middle;
    }

    .attendance-matrix-cell:focus {
        box-shadow: inset 0 0 0 2px #0070C0;
        outline: none;
    }

    .attendance-matrix-cell[data-locked="1"] {
        cursor: not-allowed;
        opacity: 0.72;
    }

    .status-present {
        background: #d8f3df;
        color: #155724;
    }

    .status-absent {
        background: #f8d7da;
        color: #721c24;
    }

    .status-justified {
        background: #d6eef3;
        color: #0c5460;
    }

    .status-late {
        background: #fff3cd;
        color: #856404;
    }

    @media (max-width: 767.98px) {
        .student-col {
            min-width: 180px;
        }

        .session-col {
            min-width: 104px;
        }
    }
</style>
@endsection

@section('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('massiveAttendanceForm');
    const submitButton = document.getElementById('massiveSubmitButton');
    const cells = Array.from(document.querySelectorAll('.attendance-matrix-cell'));
    const statuses = {
        present: { code: '1/A', label: 'Asistencia', className: 'status-present' },
        absent: { code: '0/F', label: 'Falta', className: 'status-absent' },
        justified: { code: '2/J', label: 'Justificada', className: 'status-justified' },
        late: { code: '3/R', label: 'Retardo', className: 'status-late' },
    };
    const order = ['present', 'absent', 'justified', 'late'];
    const keyStatusMap = {
        '1': 'present',
        a: 'present',
        A: 'present',
        '0': 'absent',
        f: 'absent',
        F: 'absent',
        '2': 'justified',
        j: 'justified',
        J: 'justified',
        '3': 'late',
        r: 'late',
        R: 'late',
    };

    function inputFor(cell) {
        return cell.querySelector('input[type="hidden"]');
    }

    function paint(cell, status) {
        const normalized = statuses[status] ? status : 'present';
        Object.values(statuses).forEach(item => cell.classList.remove(item.className));
        cell.classList.add(statuses[normalized].className);
        cell.dataset.status = normalized;
        cell.childNodes.forEach(node => {
            if (node.nodeType === Node.TEXT_NODE) {
                node.remove();
            }
        });
        cell.insertBefore(document.createTextNode(statuses[normalized].code), cell.firstChild);

        const input = inputFor(cell);
        if (input) {
            input.value = normalized;
        }
    }

    function setStatus(cell, status) {
        if (!cell || cell.dataset.locked === '1') {
            return false;
        }

        paint(cell, status);
        return true;
    }

    function nextEditableCell(index, direction) {
        let nextIndex = index + direction;
        while (nextIndex >= 0 && nextIndex < cells.length) {
            if (cells[nextIndex].dataset.locked !== '1') {
                return cells[nextIndex];
            }
            nextIndex += direction;
        }

        return null;
    }

    cells.forEach((cell, index) => {
        paint(cell, cell.dataset.status || 'present');

        if (cell.dataset.locked === '1') {
            return;
        }

        cell.addEventListener('click', function () {
            const current = cell.dataset.status || 'present';
            const next = order[(order.indexOf(current) + 1) % order.length] || 'present';
            setStatus(cell, next);
        });

        cell.addEventListener('keydown', function (event) {
            const mappedStatus = keyStatusMap[event.key];
            if (mappedStatus) {
                event.preventDefault();
                setStatus(cell, mappedStatus);
                const next = nextEditableCell(index, 1);
                if (next) {
                    next.focus();
                }
                return;
            }

            const navigation = {
                Enter: 1,
                ArrowRight: 1,
                ArrowLeft: -1,
                ArrowDown: 1,
                ArrowUp: -1,
            };

            if (Object.prototype.hasOwnProperty.call(navigation, event.key)) {
                event.preventDefault();
                const next = nextEditableCell(index, navigation[event.key]);
                if (next) {
                    next.focus();
                }
            }
        });
    });

    if (form && submitButton) {
        form.addEventListener('submit', function () {
            submitButton.disabled = true;
            submitButton.textContent = 'Guardando...';
        });
    }
});
</script>
@endsection
