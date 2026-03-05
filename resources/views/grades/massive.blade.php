@extends('layouts.app')

@section('title', 'Calificación masiva')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            {{ $assignment->group->name }} – {{ $assignment->subject->name }}
        </h3>
    </div>

    <div class="card-body table-responsive p-0" style="max-height:70vh;">
        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>Alumno</th>
                    @foreach ($activities as $activity)
                        <th class="text-center">
                            {{ $activity->title }}<br>
                            {{ $activity->due_date->format('Y-m-d')}}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($students as $student)
                    <tr>
                        <td>{{ $student->user->name }}</td>

                        @foreach ($activities as $activity)
                            @php
                                $grade = $activity->grades
                                    ->firstWhere('student_id', $student->id);
                            @endphp
                            <td class="text-center">
                            <span
                                class="grade-span"
                                data-grade-id="{{ $grade?->id ?? '' }}"
                                data-student-id="{{ $student->id }}"
                                data-activity-id="{{ $activity->id }}"
                            >
                                    {{ optional($grade)->score ?? '—' }}
                                </span>

                                <i
                                    class="fas fa-comment-dots text-muted ml-1 comment-icon"
                                    data-grade-id="{{ optional($grade)->id }}"
                                    data-student-id="{{ $student->id }}"
                                    data-activity-id="{{ $activity->id }}"
                                    style="cursor:pointer;"
                                ></i>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="commentModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Comentario</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <textarea
                    id="commentInput"
                    class="form-control"
                    rows="4"
                    placeholder="Observación sobre la actividad"
                ></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary btn-sm" id="saveComment">
                    Guardar
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_css')
    <style>
        table thead th {
            position: sticky;
            top: 0;
            background: #f4f6f9; /* AdminLTE */
            z-index: 10;
            vertical-align: middle;
        }
        table td:first-child,
        table th:first-child {
            position: sticky;
            left: 0;
            background: #fff;
            z-index: 11;
        }
    </style>
@endsection

@section('page_scripts')
<script>
const updateUrl = "{{ route('grades.inline-update', ':id') }}";
const storeUrl  = "{{ route('grades.store', ':activity') }}";

document.querySelectorAll('.grade-span').forEach(span => {

    span.addEventListener('dblclick', () => {

        if (span.querySelector('input')) return;

        const currentValue = span.textContent.trim() === '—'
            ? ''
            : span.textContent.trim();

        const input = document.createElement('input');
        input.type = 'number';
        input.step = '0.1';
        input.min = 0;
        input.max = 10;
        input.value = currentValue;
        input.classList.add('form-control', 'form-control-sm');

        span.textContent = '';
        span.appendChild(input);
        input.focus();

        input.addEventListener('blur', () => save(span, input));
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') save(span, input);
            if (e.key === 'Escape') span.textContent = currentValue || '—';
        });
    });
});

function save(span, input) {
    const gradeId = span.dataset.gradeId;
    const score   = input.value;

    if (gradeId) {
        // actualizar
        fetch(updateUrl.replace(':id', gradeId), {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ score })
        })
        .then(r => r.json())
        .then(d => span.textContent = d.score ?? '—');
    } else {
        // crear
        fetch(storeUrl.replace(':activity', span.dataset.activityId), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                student_id: span.dataset.studentId,
                score: score
            })
        })
        .then(r => r.json())
        .then(d => {
            span.dataset.gradeId = d.id;
            span.textContent = d.score;
        });
    }
}
</script>

<script>
let currentGradeId = null;
let currentStudentId = null;
let currentActivityId = null;

document.querySelectorAll('.comment-icon').forEach(icon => {
    icon.addEventListener('click', () => {

        currentGradeId = icon.dataset.gradeId || null;
        currentStudentId = icon.dataset.studentId;
        currentActivityId = icon.dataset.activityId;

        document.getElementById('commentInput').value = '';

        // Si existe calificación, cargamos comentario
        if (currentGradeId) {
            fetch(`/grades/${currentGradeId}`)
                .then(r => r.json())
                .then(d => {
                    document.getElementById('commentInput').value =
                        d.comments ?? '';
                });
        }

        $('#commentModal').modal('show');
    });
});

document.getElementById('saveComment').addEventListener('click', () => {
    const comments = document.getElementById('commentInput').value;

    // SI YA EXISTE grade → actualizar
    if (currentGradeId) {
        fetch(`/grades/${currentGradeId}`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ comments })
        }).then(() => $('#commentModal').modal('hide'));

    } else {
        // NO EXISTE → crear grade con solo comentario
        fetch(`/activities/${currentActivityId}/grades`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                student_id: currentStudentId,
                score: null,
                comments: comments
            })
        })
        .then(r => r.json())
        .then(d => {
            currentGradeId = d.id;
            $('#commentModal').modal('hide');
        });
    }
});
</script>
@endsection
