@extends('layouts.app')

@section('title', 'Asistencia global')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
        @if(session('info'))
            <div class="alert alert-success">{{ session('info') }}</div>
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

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0">Asistencia global - {{ $group->name }}</h3>
                        <small class="text-muted">Fecha: {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</small>
                    </div>
                    <a href="{{ route('prefect.groups.attendance', $group) }}" class="btn btn-outline-secondary">Volver</a>
                </div>
                <form method="POST" action="{{ route('prefect.groups.attendance.store', $group) }}" id="prefectAttendanceForm">
                    @csrf
                    <input type="hidden" name="attendance_date" value="{{ $date }}">

                    <div class="card-body table-responsive p-3">
                        <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Matricula</th>
                                <th>Alumno</th>
                                <th class="text-center">Asistencia</th>
                                <th class="text-center">Falta</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($students as $student)
                                @php
                                    $rawCurrent = old('attendance.' . $student->id, optional($existing->get($student->id))->status ?? 'present');
                                    $current = in_array($rawCurrent, ['present', 'late', 'justified'], true) ? 'present' : 'absent';
                                @endphp
                                <tr>
                                    <td>{{ $student->enrollment_number }}</td>
                                    <td>{{ $student->user->name }}</td>
                                    <td class="text-center">
                                        <div class="attendance-radio attendance-present">
                                            <input type="radio"
                                                id="present_{{ $student->id }}"
                                                name="attendance[{{ $student->id }}]"
                                                value="present"
                                                {{ $current === 'present' ? 'checked' : '' }}
                                                required>
                                            <label for="present_{{ $student->id }}"></label>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="attendance-radio attendance-absent">
                                            <input type="radio"
                                                id="absent_{{ $student->id }}"
                                                name="attendance[{{ $student->id }}]"
                                                value="absent"
                                                {{ $current === 'absent' ? 'checked' : '' }}
                                                required>
                                            <label for="absent_{{ $student->id }}"></label>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No hay alumnos activos en este grupo.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        </table>
                    </div>

                    <div class="card-footer">
                        <button class="btn btn-primary" id="prefectAttendanceSubmitButton">Guardar asistencia global</button>
                    </div>
                </form>
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

    .attendance-radio input[type="radio"] {
        display: none;
    }

    .attendance-radio label {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 2px solid #ccc;
        cursor: pointer;
        position: relative;
        margin: 0;
    }

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

    .attendance-present input:checked + label {
        border-color: #28a745;
    }

    .attendance-present input:checked + label::after {
        background: #28a745;
    }

    .attendance-absent input:checked + label {
        border-color: #dc3545;
    }

    .attendance-absent input:checked + label::after {
        background: #dc3545;
    }
</style>
@endsection

@section('page_scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('prefectAttendanceForm');
        const button = document.getElementById('prefectAttendanceSubmitButton');
        if (!form || !button) return;

        form.addEventListener('submit', function () {
            button.disabled = true;
            button.textContent = 'Guardando...';
        });
    });
</script>
@endsection
