@extends('layouts.app')

@section('title', 'Asistencia')

@section('content')
<div class="content px-3">
    <div class="card">

        <div class="card-header">
            <h4>
                Asistencia —
                {{ $schedule->assignment->subject->name }}
                ({{ $schedule->assignment->group->name }})
            </h4>
        </div>

        <div class="card-body">

            @if(session('info'))
                <div class="alert alert-primary">{{ session('info') }}</div>
            @endif
            @if($isNonWorkingDay)
                <div class="alert alert-warning">
                    <i class="fas fa-calendar-times"></i>
                    Este día es <strong>no laborable</strong> para esta modalidad.
                    No se registra asistencia.
                </div>
            @endif


            <form method="GET"
                action="{{ route('attendance.index', $schedule) }}"
                class="mb-3">

                <input type="date" name="date"
                    value="{{ $date }}"
                    class="form-control"
                    onchange="this.form.submit()"
                    @disabled($isNonWorkingDay)>
            </form>

            <form method="POST"
                  action="{{ route('attendance.store', $schedule) }}">
                @csrf
                <input type="hidden" name="class_date" value="{{ $date }}">

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>Asistencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($students as $student)
                            <tr>
                                <td>{{ $student->user->name }}</td>
                                <td>
                                    <select name="attendance[{{ $student->id }}]"
                                            class="form-control">
                                            <option value="present"
                                                {{ optional($existing->get($student->id))->status == 'present' ? 'selected' : '' }}>
                                                Presente
                                            </option>

                                            <option value="absent"
                                                {{ optional($existing->get($student->id))->status == 'absent' ? 'selected' : '' }}>
                                                Falta
                                            </option>

                                            <option value="late"
                                                {{ optional($existing->get($student->id))->status == 'late' ? 'selected' : '' }}>
                                                Retardo
                                            </option>

                                            <option value="justified"
                                                {{ optional($existing->get($student->id))->status == 'justified' ? 'selected' : '' }}>
                                                Justificada
                                            </option>
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <button class="btn btn-primary" @disabled($isNonWorkingDay)>Guardar asistencia</button>
            </form>

        </div>
    </div>
</div>
@endsection
