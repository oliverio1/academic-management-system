
@extends('layouts.app')

@section('title', 'Asistencia')

@section('content')
    @php
        $isReadOnly = $isReadOnly ?? $session->isAttendanceClosed();
        $periodDisabled = $periodDisabled ?? false;
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
                                <small>Esos registros quedan en falta y no se pueden editar desde esta vista.</small>
                            </div>
                        @endif

                        {{-- Form --}}
                        <form method="POST"
                            action="{{ route('attendance.store', $session) }}">
                            @csrf

                            <table class="table table-sm align-middle">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-left">Alumno</th>
                                        <th class="text-success text-center">PRESENTE</th>
                                        <th class="text-warning text-center">RETARDO</th>
                                        <th class="text-danger text-center">FALTA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($students as $student)
                                        @php
                                            $record = $attendance[$student->id] ?? null;
                                            $status = $record->status ?? ($isReadOnly ? null : 'present');
                                            $isSuspensionLocked = (bool) optional($record)->is_suspension_locked;
                                            $disabled = $isReadOnly || $isSuspensionLocked;
                                        @endphp
                                        <tr>
                                            <td class="text-left">
                                                {{ $student->user->name }}
                                                @if($isSuspensionLocked)
                                                    <span class="badge badge-danger ml-2">Suspendido</span>
                                                    <div class="small text-muted">Falta bloqueada por coordinacion</div>
                                                @endif
                                            </td>

                                            {{-- PRESENTE --}}
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
                                        </tr>
                                    @endforeach
                                    </tbody>
                            </table>

                            {{-- Footer --}}
                            <div class="d-flex justify-content-between mt-3">
                                <a href="{{ route('teacher.classes.sessions.index', $session->teachingAssignment) }}"
                                    class="btn btn-secondary">
                                    Volver
                                </a>

                                @unless($isReadOnly)
                                    <button class="btn btn-primary">
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

    /* Deshabilitado (asistencia cerrada) */
    .attendance-radio input:disabled + label {
        cursor: not-allowed;
        opacity: 0.6;
    }
</style>
@endsection

@section('page_scripts')
    <script>
        $(document).ready(function () {
            $('#modalities').DataTable({
                dom: '<"area-fluid"<"row"<"col"l><"col"B><"col"f>>>rtip',
                "columnDefs": [
                    { "type": "num", "targets": 0 }
                ],
                "order": [[ 0, "asc" ]],
                buttons: [
                    'excelHtml5',
                    'pdfHtml5'
                ],
                language: {
                    url: '/datatables.json'
                }
            });
        });
    </script>
@endsection
