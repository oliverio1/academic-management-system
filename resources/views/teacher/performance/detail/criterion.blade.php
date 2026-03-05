@extends('layouts.app')

@section('title', 'Detalle de ' . $criterion->name)

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-sm-6">
                            <h4>
                                {{ $student->user->name }} —
                                {{ $criterion->name }}
                            </h4>
                        </div>
                        <div class="col-sm-6">
                            <a href="{{ url()->previous() }}"
                            class="btn btn-outline-secondary">
                                ← Volver
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Actividad</th>
                                <th>Fecha</th>
                                <th>Calificación</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activities as $activity)
                                <tr>
                                    <td>{{ $activity->title }}</td>
                                    <td>{{ \Carbon\Carbon::parse($activity->due_date)->format('d/m/Y') }}</td>
                                    <td
                                        class="grade-cell text-center"
                                        data-grade-id="{{ optional($activity->grades->first())->id }}"
                                        data-activity-id="{{ $activity->id }}"
                                        data-student-id="{{ $student->id }}"
                                    >
                                        {{ optional($activity->grades->first())->score ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_css')
<style>
.grade-cell {
    position: relative;
    transition: background-color 0.4s ease, transform 0.2s ease;
}

.grade-updated {
    background-color: #e6f4ea;
    transform: scale(1.05);
}

.grade-error {
    background-color: #fdecea;
}

.grade-check {
    position: absolute;
    top: 2px;
    right: 6px;
    font-size: 12px;
    color: #2e7d32;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.grade-check.show {
    opacity: 1;
}
</style>
@endsection

@section('page_scripts')
<script>
const updateUrl = "{{ route('grades.inline-update', ':id') }}";
const storeUrl  = "{{ route('grades.store', ':activity') }}";

document.querySelectorAll('.grade-cell').forEach(cell => {

    cell.addEventListener('dblclick', () => {

        if (cell.querySelector('input')) return;

        const current = cell.textContent.trim() === '—'
            ? ''
            : cell.textContent.trim();

        const input = document.createElement('input');
        input.type = 'number';
        input.min = 0;
        input.max = 10;
        input.step = '0.1';
        input.value = current;
        input.classList.add('form-control', 'form-control-sm');

        cell.textContent = '';
        cell.appendChild(input);
        input.focus();

        input.addEventListener('blur', () => saveGrade(cell, input.value, current));
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') saveGrade(cell, input.value, current);
            if (e.key === 'Escape') cell.textContent = current || '—';
        });
    });
});

function saveGrade(cell, score, previous) {

    // validación mínima
    if (score === '' || score < 0 || score > 10) {
        cell.textContent = previous || '—';
        cell.classList.add('grade-error');
        setTimeout(() => cell.classList.remove('grade-error'), 600);
        return;
    }

    const gradeId = cell.dataset.gradeId;
    const payload = { score };

    const request = gradeId
        ? fetch(updateUrl.replace(':id', gradeId), {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload)
        })
        : fetch(storeUrl.replace(':activity', cell.dataset.activityId), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                student_id: cell.dataset.studentId,
                score
            })
        });

    request
        .then(r => r.json())
        .then(d => {
            if (!gradeId) {
                cell.dataset.gradeId = d.id;
            }

            cell.textContent = d.score ?? '—';

            // animación visual
            cell.classList.add('grade-updated');

            const check = document.createElement('span');
            check.textContent = '✔';
            check.className = 'grade-check show';
            cell.appendChild(check);

            setTimeout(() => {
                cell.classList.remove('grade-updated');
                check.classList.remove('show');
            }, 200);

            setTimeout(() => check.remove(), 900);
        })
        .catch(() => {
            cell.textContent = previous || '—';
            cell.classList.add('grade-error');
            setTimeout(() => cell.classList.remove('grade-error'), 600);
        });
}
</script>
@endsection