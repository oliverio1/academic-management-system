@extends('layouts.app')

@section('title', 'Actividades')

@section('content')
    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>s  
    @endif

    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12 connectedSortable mt-3">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                {{ $activity->name }}
                            </h3>
                        </div>

                        <div class="card-body p-0">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="60%">Alumno</th>
                                        <th width="40%">Calificación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $student)
                                        @php
                                            $grade = $student->grades->first();
                                        @endphp
                                        <tr>
                                            <td>{{ $student->user->name }}</td>
                                            <td>
                                                <span
                                                    class="grade-span"
                                                    data-grade-id="{{ optional($grade)->id }}"
                                                    data-student-id="{{ $student->id }}"
                                                    data-activity-id="{{ $activity->id }}"
                                                >
                                                    {{ $grade->score ?? '—' }}
                                                </span>
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
    </div>
@endsection

@section('page_css')

@endsection

@section('page_scripts')
<script>
    const gradeUpdateUrl = "{{ route('grades.inline-update', ':id') }}";
</script>
<script>
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

        input.addEventListener('blur', () => saveGrade(span, input));
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') saveGrade(span, input);
            if (e.key === 'Escape') span.textContent = currentValue || '—';
        });
    });
});

function saveGrade(span, input) {
    const gradeId = span.dataset.gradeId;

    fetch(gradeUpdateUrl.replace(':id', gradeId), {
        method: 'PATCH',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            score: input.value
        })
    })
    .then(res => res.json())
    .then(data => {
        span.textContent = data.score ?? '—';
    })
    .catch(() => {
        span.textContent = input.value || '—';
    });
}
</script>
@endsection