
@extends('layouts.app')

@section('title', 'Asistencia')

@section('content')
    @php
        $isReadOnly = $isReadOnly ?? $session->isAttendanceClosed();
        $periodDisabled = $periodDisabled ?? false;
        $testCycleEditing = $testCycleEditing ?? false;
    @endphp
    @if(session('warning'))
        <div class="alert alert-warning">
            {{ session('warning') }}
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            Revisa la información antes de guardar.
        </div>
    @endif
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">

                <div class="card">

                    {{-- Header --}}
                    <div class="card-header">
                        <h5 class="mb-0">
                            Pasar lista —
                            {{ $session->teachingAssignment->subject->name }}
                            <small class="text-muted">
                                ({{ $session->teachingAssignment->group->name }})
                            </small>
                        </h5>
                        <hr>
                        Sesión: {{ $session->session_date->translatedFormat('l j \\d\\e F \\d\\e Y') }} | {{ substr($session->start_time, 0, 5) }} – {{ substr($session->end_time, 0, 5) }}
                    </div>

                    {{-- Body --}}
                    <div class="card-body">
                        @php
                            $hasSuspensionLockedRows = collect($students)->contains(function ($student) use ($attendance) {
                                $record = $attendance[$student->id] ?? null;
                                return (bool) optional($record)->is_suspension_locked;
                            });
                        @endphp

                        {{-- Aviso institucional --}}
                        @if($periodDisabled)
                            <div class="alert alert-secondary">
                                Este periodo esta deshabilitado por coordinacion.
                                <br>
                                <small>Vista en modo consulta.</small>
                            </div>
                        @elseif($session->isAttendanceClosed())
                            <div class="alert alert-secondary">
                                🔒 La asistencia de esta sesión ya está cerrada.
                                <br>
                                <small>No se permiten modificaciones.</small>
                            </div>
                        @elseif($hasSuspensionLockedRows)
                            <div class="alert alert-warning">
                                Hay alumnos con <strong>suspension activa</strong> para esta fecha.
                                <br>
                                <small>
                                    @if($testCycleEditing)
                                        En este ciclo de prueba tambien puedes editarlos.
                                    @else
                                        Esos registros quedan en falta y no se pueden editar desde esta vista.
                                    @endif
                                </small>
                            </div>
                        @endif
                        @if($testCycleEditing)
                            <div class="alert alert-info">
                                Ciclo de prueba: la asistencia esta abierta para captura y edicion sin candados.
                            </div>
                        @endif

                        {{-- Form --}}
                        <form method="POST"
                            action="{{ route('attendance.store', $session) }}"
                            id="attendanceForm">
                            @csrf

                            <div class="attendance-mode-toolbar">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Modo de pase de lista">
                                    <button type="button" class="btn btn-primary js-attendance-mode" data-mode="list">Vista lista</button>
                                    <button type="button" class="btn btn-secondary js-attendance-mode" data-mode="grid">Vista tabla</button>
                                </div>
                                <div class="attendance-shortcuts text-muted">
                                    Atajos: <strong>1/A</strong> asistencia, <strong>0/F</strong> falta, <strong>2/J</strong> justificada, <strong>3/R</strong> retardo
                                </div>
                            </div>

                            <div id="attendanceListMode">
                                <table class="table table-sm align-middle">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="text-left">Alumno</th>
                                            <th class="text-success text-center">ASISTENCIA</th>
                                            <th class="text-danger text-center">FALTA</th>
                                            <th class="text-info text-center">JUSTIFICADA</th>
                                            <th class="text-warning text-center">RETARDO</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($students as $student)
                                            @php
                                                $record = $attendance[$student->id] ?? null;
                                                $status = $record->status ?? ($isReadOnly ? null : 'present');
                                                $isSuspensionLocked = (bool) optional($record)->is_suspension_locked;
                                                $isJustifiedLocked = optional($record)->status === 'justified';
                                                $disabled = $isReadOnly || (! $testCycleEditing && ($isSuspensionLocked || $isJustifiedLocked));
                                            @endphp
                                            <tr>
                                                <td class="text-left">
                                                    {{ $student->user->name }}
                                                    @if($isSuspensionLocked)
                                                        <span class="badge badge-danger ml-2">Suspendido</span>
                                                        <div class="small text-muted">Falta bloqueada por coordinacion</div>
                                                    @elseif($isJustifiedLocked)
                                                        <span class="badge badge-info ml-2">Justificado</span>
                                                        <div class="small text-muted">Justificacion registrada por coordinacion</div>
                                                    @endif
                                                </td>

                                                {{-- ASISTENCIA --}}
                                                <td>
                                                    @if($disabled)
                                                        @if($status === 'present')
                                                            <span class="badge badge-success">&nbsp;</span>
                                                        @endif
                                                    @else
                                                        <div class="attendance-radio attendance-present">
                                                            <input type="radio"
                                                                name="attendance[{{ $student->id }}]"
                                                                id="present-{{ $student->id }}"
                                                                value="present"
                                                                {{ $status === 'present' ? 'checked' : '' }}
                                                                {{ $disabled ? 'disabled' : '' }}>
                                                            <label for="present-{{ $student->id }}"></label>
                                                        </div>
                                                    @endif
                                                </td>

                                                {{-- FALTA --}}
                                                <td>
                                                    @if($disabled)
                                                        @if($status === 'absent')
                                                            <span class="badge badge-danger">&nbsp;</span>
                                                        @endif
                                                    @else
                                                        <div class="attendance-radio attendance-absent">
                                                            <input type="radio"
                                                                name="attendance[{{ $student->id }}]"
                                                                id="absent-{{ $student->id }}"
                                                                value="absent"
                                                                {{ $status === 'absent' ? 'checked' : '' }}
                                                                {{ $disabled ? 'disabled' : '' }}>
                                                            <label for="absent-{{ $student->id }}"></label>
                                                        </div>
                                                    @endif
                                                </td>

                                                {{-- JUSTIFICADA --}}
                                                <td>
                                                    @if($disabled)
                                                        @if($status === 'justified')
                                                            <span class="badge badge-info">&nbsp;</span>
                                                        @endif
                                                    @else
                                                        <div class="attendance-radio attendance-justified">
                                                            <input type="radio"
                                                                name="attendance[{{ $student->id }}]"
                                                                id="justified-{{ $student->id }}"
                                                                value="justified"
                                                                {{ $status === 'justified' ? 'checked' : '' }}
                                                                {{ $disabled ? 'disabled' : '' }}>
                                                            <label for="justified-{{ $student->id }}"></label>
                                                        </div>
                                                    @endif
                                                </td>

                                                {{-- RETARDO --}}
                                                <td>
                                                    @if($disabled)
                                                        @if($status === 'late')
                                                            <span class="badge badge-warning">&nbsp;</span>
                                                        @endif
                                                    @else
                                                        <div class="attendance-radio attendance-late">
                                                            <input type="radio"
                                                                name="attendance[{{ $student->id }}]"
                                                                id="late-{{ $student->id }}"
                                                                value="late"
                                                                {{ $status === 'late' ? 'checked' : '' }}
                                                                {{ $disabled ? 'disabled' : '' }}>
                                                            <label for="late-{{ $student->id }}"></label>
                                                        </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                </table>
                            </div>

                            <div id="attendanceGridMode" class="d-none">
                                <div class="table-responsive attendance-grid-wrap">
                                    <table class="table table-sm table-bordered attendance-grid">
                                        <thead>
                                            <tr>
                                                <th class="attendance-grid-number">#</th>
                                                <th>Alumno</th>
                                                <th class="attendance-grid-status">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($students as $student)
                                                @php
                                                    $record = $attendance[$student->id] ?? null;
                                                    $status = $record->status ?? ($isReadOnly ? null : 'present');
                                                    $isSuspensionLocked = (bool) optional($record)->is_suspension_locked;
                                                    $isJustifiedLocked = optional($record)->status === 'justified';
                                                    $disabled = $isReadOnly || (! $testCycleEditing && ($isSuspensionLocked || $isJustifiedLocked));
                                                @endphp
                                                <tr>
                                                    <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                                    <td>
                                                        {{ $student->user->name }}
                                                        @if($isSuspensionLocked)
                                                            <span class="badge badge-danger ml-2">Suspendido</span>
                                                        @elseif($isJustifiedLocked)
                                                            <span class="badge badge-info ml-2">Justificado</span>
                                                        @endif
                                                    </td>
                                                    <td
                                                        class="attendance-grid-cell"
                                                        tabindex="{{ $disabled ? '-1' : '0' }}"
                                                        data-student-id="{{ $student->id }}"
                                                        data-status="{{ $status }}"
                                                        data-disabled="{{ $disabled ? '1' : '0' }}"
                                                        title="1/A asistencia, 0/F falta, 2/J justificada, 3/R retardo">
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- Footer --}}
                            <div class="d-flex justify-content-between mt-3">
                                <a href="{{ route('teacher.classes.sessions.index', $session->teachingAssignment) }}"
                                    class="btn btn-secondary">
                                    Volver
                                </a>

                                @unless($isReadOnly)
                                    <button class="btn btn-primary" id="attendanceSubmitButton">
                                        Guardar asistencia
                                    </button>
                                @endunless
                            </div>

                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection

@section('page_css')
<style>
    .attendance-radio {
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* Ocultamos el radio nativo */
    .attendance-radio input[type="radio"] {
        display: none;
    }

    /* Círculo base */
    .attendance-radio label {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 2px solid #ccc;
        cursor: pointer;
        position: relative;
    }

    /* Punto interior (apagado) */
    .attendance-radio label::after {
        content: '';
        width: 10px;
        height: 10px;
        border-radius: 50%;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: transparent;
    }

    /* ===== COLORES ===== */

    /* Presente */
    .attendance-present input:checked + label {
        border-color: #28a745;
    }
    .attendance-present input:checked + label::after {
        background: #28a745;
    }

    /* Retardo */
    .attendance-late input:checked + label {
        border-color: #ffc107;
    }
    .attendance-late input:checked + label::after {
        background: #ffc107;
    }

    /* Falta */
    .attendance-absent input:checked + label {
        border-color: #dc3545;
    }
    .attendance-absent input:checked + label::after {
        background: #dc3545;
    }

    /* Justificada */
    .attendance-justified input:checked + label {
        border-color: #17a2b8;
    }
    .attendance-justified input:checked + label::after {
        background: #17a2b8;
    }

    /* Deshabilitado (asistencia cerrada) */
    .attendance-radio input:disabled + label {
        cursor: not-allowed;
        opacity: 0.6;
    }

    .attendance-mode-toolbar {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: space-between;
        margin-bottom: 14px;
    }

    .attendance-shortcuts {
        font-size: 0.85rem;
    }

    .attendance-grid-wrap {
        border: 1px solid #dee2e6;
        max-height: 68vh;
        overflow: auto;
    }

    .attendance-grid {
        margin-bottom: 0;
    }

    .attendance-grid thead th {
        background: #f1f3f5;
        border-bottom: 1px solid #cfd4da;
        color: #343a40;
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .attendance-grid-number {
        width: 52px;
    }

    .attendance-grid-status {
        width: 170px;
    }

    .attendance-grid-cell {
        cursor: cell;
        font-weight: 700;
        text-align: center;
        user-select: none;
        vertical-align: middle;
    }

    .attendance-grid-cell:focus {
        box-shadow: inset 0 0 0 2px #0070C0;
        outline: none;
    }

    .attendance-grid-cell[data-disabled="1"] {
        cursor: not-allowed;
        opacity: 0.78;
    }

    .attendance-grid-cell.status-present {
        background: #d8f3df;
        color: #155724;
    }

    .attendance-grid-cell.status-absent {
        background: #f8d7da;
        color: #721c24;
    }

    .attendance-grid-cell.status-justified {
        background: #d6eef3;
        color: #0c5460;
    }

    .attendance-grid-cell.status-late {
        background: #fff3cd;
        color: #856404;
    }

    .attendance-grid-cell.status-empty {
        background: #f8f9fa;
        color: #6c757d;
    }

    @media (max-width: 767.98px) {
        .attendance-mode-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        .attendance-mode-toolbar .btn-group {
            display: flex;
        }

        .attendance-mode-toolbar .btn {
            flex: 1;
        }
    }
</style>
@endsection

@section('page_scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('attendanceForm');
        const button = document.getElementById('attendanceSubmitButton');

        const listMode = document.getElementById('attendanceListMode');
        const gridMode = document.getElementById('attendanceGridMode');
        const modeButtons = document.querySelectorAll('.js-attendance-mode');
        const gridCells = Array.from(document.querySelectorAll('.attendance-grid-cell'));
        const modeStorageKey = 'teacher-attendance-mode';
        const statuses = {
            present: { label: 'Asistencia', code: '1/A', className: 'status-present' },
            absent: { label: 'Falta', code: '0/F', className: 'status-absent' },
            justified: { label: 'Justificada', code: '2/J', className: 'status-justified' },
            late: { label: 'Retardo', code: '3/R', className: 'status-late' },
            empty: { label: '-', code: '', className: 'status-empty' },
        };
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
        const cycleOrder = ['present', 'absent', 'justified', 'late'];

        function radioFor(studentId, status) {
            return form.querySelector(`input[name="attendance[${studentId}]"][value="${status}"]`);
        }

        function selectedStatus(studentId, fallback) {
            const checked = form.querySelector(`input[name="attendance[${studentId}]"]:checked`);
            return checked ? checked.value : fallback;
        }

        function paintCell(cell, status) {
            const config = statuses[status] || statuses.empty;
            Object.values(statuses).forEach(item => cell.classList.remove(item.className));
            cell.classList.add(config.className);
            cell.textContent = config.code ? `${config.code} - ${config.label}` : config.label;
            cell.dataset.status = status || '';
        }

        function syncCellFromRadio(cell) {
            const studentId = cell.dataset.studentId;
            paintCell(cell, selectedStatus(studentId, cell.dataset.status || ''));
        }

        function setStudentStatus(studentId, status) {
            const radio = radioFor(studentId, status);
            if (!radio || radio.disabled) return false;

            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        }

        function setMode(mode) {
            const normalizedMode = mode === 'grid' ? 'grid' : 'list';
            listMode.classList.toggle('d-none', normalizedMode !== 'list');
            gridMode.classList.toggle('d-none', normalizedMode !== 'grid');

            modeButtons.forEach(modeButton => {
                const isActive = modeButton.dataset.mode === normalizedMode;
                modeButton.classList.toggle('btn-primary', isActive);
                modeButton.classList.toggle('btn-secondary', !isActive);
            });

            localStorage.setItem(modeStorageKey, normalizedMode);
        }

        gridCells.forEach((cell, index) => {
            syncCellFromRadio(cell);

            if (cell.dataset.disabled === '1') {
                return;
            }

            cell.addEventListener('click', function () {
                cell.focus();
                const current = selectedStatus(cell.dataset.studentId, cell.dataset.status);
                const currentIndex = cycleOrder.indexOf(current);
                const next = cycleOrder[(currentIndex + 1) % cycleOrder.length] || 'present';
                setStudentStatus(cell.dataset.studentId, next);
            });

            cell.addEventListener('keydown', function (event) {
                const mappedStatus = keyStatusMap[event.key];

                if (mappedStatus) {
                    event.preventDefault();
                    if (setStudentStatus(cell.dataset.studentId, mappedStatus)) {
                        const nextCell = gridCells[index + 1];
                        if (nextCell && nextCell.dataset.disabled !== '1') {
                            nextCell.focus();
                        }
                    }
                    return;
                }

                const navigation = {
                    ArrowDown: index + 1,
                    Enter: index + 1,
                    ArrowUp: index - 1,
                };

                if (Object.prototype.hasOwnProperty.call(navigation, event.key)) {
                    event.preventDefault();
                    const nextCell = gridCells[navigation[event.key]];
                    if (nextCell && nextCell.dataset.disabled !== '1') {
                        nextCell.focus();
                    }
                }
            });
        });

        form.querySelectorAll('input[type="radio"][name^="attendance["]').forEach(radio => {
            radio.addEventListener('change', function () {
                const match = radio.name.match(/^attendance\[(\d+)\]$/);
                if (!match) return;

                const cell = document.querySelector(`.attendance-grid-cell[data-student-id="${match[1]}"]`);
                if (cell) {
                    paintCell(cell, radio.value);
                }
            });
        });

        modeButtons.forEach(modeButton => {
            modeButton.addEventListener('click', function () {
                setMode(modeButton.dataset.mode);
            });
        });

        setMode(localStorage.getItem(modeStorageKey) || 'list');

        if (!form || !button) return;

        form.addEventListener('submit', function () {
            button.disabled = true;
            button.textContent = 'Guardando...';
        });
    });
</script>
@endsection
