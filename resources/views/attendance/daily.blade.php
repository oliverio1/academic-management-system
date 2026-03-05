@extends('layouts.app')

@section('title', 'Calificación masiva')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            {{ $assignment->group->name }} – {{ $assignment->subject->name }}
        </h3>
    </div>

    <div class="card-body table-responsive p-0">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th class="text-center">Asistencia</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($students as $student)
                    @php
                        $attendance = $schedule->attendances
                            ->firstWhere('student_id', $student->id);
                    @endphp
                    <tr>
                        <td>{{ $student->user->name }}</td>
                        <td class="text-center">
                            <select
                                class="form-control attendance-select"
                                data-student="{{ $student->id }}"
                            >
                                <option value="present"
                                    @selected(optional($attendance)->status === 'present')>
                                    Presente
                                </option>
                                <option value="absent"
                                    @selected(optional($attendance)->status === 'absent')>
                                    Falta
                                </option>
                                <option value="justified"
                                    @selected(optional($attendance)->status === 'justified')>
                                    Justificada
                                </option>
                            </select>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <button class="btn btn-primary" id="saveAttendance">
            Guardar asistencia
        </button>

    </div>
</div>
@endsection

@section('page_scripts')
<script>
    document.getElementById('saveAttendance').addEventListener('click', () => {

    const data = [];

    document.querySelectorAll('.attendance-select').forEach(select => {
        data.push({
            student_id: select.dataset.student,
            status: select.value
        });
    });

    fetch('{{ route('attendance.daily.store', $schedule) }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ attendances: data })
    })
    .then(() => alert('Asistencia guardada'));
    });
</script>
@endsection
