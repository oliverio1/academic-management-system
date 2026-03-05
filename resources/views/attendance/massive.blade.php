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
        <table class="table table-bordered table-sm text-center">
            <thead>
                <tr>
                    <th class="text-left">Alumno</th>
                    @foreach ($sessions as $session)
                        <th>
                            {{ \Carbon\Carbon::parse($session['class_date'])->format('d/m') }}
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @foreach ($students as $student)
                    <tr>
                        <td class="text-left">
                            {{ $student->user->name }}
                        </td>

                        @foreach ($sessions as $session)
                        @php
                            $attendance = $assignment->schedules
                                ->flatMap->attendances
                                ->first(fn ($a) =>
                                    $a->student_id === $student->id &&
                                    $a->class_date === $session['class_date'] &&
                                    $a->schedule_id === $session['schedule_id']
                                );

                            $statusMap = [
                                'absent' => 0,
                                'present' => 1,
                                'justified' => 2,
                            ];

                            $numericStatus = $attendance
                                ? $statusMap[$attendance->status]
                                : 1; // por defecto presente
                        @endphp

                            <td class="attendance-cell"
                                data-student="{{ $student->id }}"
                                data-date="{{ $session['class_date'] }}"
                                data-schedule="{{ $session['schedule_id'] }}"
                                data-status="{{ $numericStatus }}">
                                    {{ $numericStatus }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('page_css')
    <style>
        .attendance-cell {
            cursor: pointer;
            font-weight: bold;
        }

        .attendance-cell[data-status="1"] {
            background-color: #d4edda; /* verde */
        }

        .attendance-cell[data-status="0"] {
            background-color: #f8d7da; /* rojo */
        }

        .attendance-cell[data-status="2"] {
            background-color: #fff3cd; /* amarillo */
        }
    </style>
@endsection

@section('page_scripts')
<script>
    const statusMap = {
        0: 'absent',
        1: 'present',
        2: 'justified'
    };

    const nextStatus = {
        1: 0,
        0: 2,
        2: 1
    };
    document.querySelectorAll('.attendance-cell').forEach(cell => {

        cell.addEventListener('dblclick', () => {

            let current = parseInt(cell.dataset.status);
            let next = nextStatus[current];

            fetch('{{ route('attendance.inline') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    schedule_id: cell.dataset.schedule,
                    student_id: cell.dataset.student,
                    class_date: cell.dataset.date,
                    status: statusMap[next],
                })
            })
            .then(r => r.json())
            .then(() => {
                cell.dataset.status = next;
                cell.textContent = next;
            });
        });

    });
</script>
@endsection
