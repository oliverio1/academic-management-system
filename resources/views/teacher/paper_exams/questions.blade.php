@extends('layouts.app')

@section('title', 'Seleccionar preguntas')

@section('content')
<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Seleccionar preguntas</h4>
                <small class="text-muted">
                    {{ $paperExam->title }} / {{ $paperExam->assignment->subject->name ?? 'N/D' }} / Grupo {{ $paperExam->assignment->group->name ?? 'N/D' }}
                </small>
            </div>
            <a href="{{ route('teacher.paper-exams.index', ['assignment_id' => $paperExam->teaching_assignment_id]) }}" class="btn btn-outline-secondary btn-sm">
                Volver a examenes
            </a>
        </div>

        <form method="POST" action="{{ route('teacher.paper-exams.questions.update', $paperExam) }}">
            @csrf
            @method('PUT')

            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($hasAttempts)
                    <div class="alert alert-warning">
                        Este examen ya tiene intentos registrados. Para proteger las respuestas de los alumnos, las preguntas no se pueden modificar.
                    </div>
                @endif

                @if($banks->isEmpty())
                    <div class="alert alert-info mb-0">
                        No tienes bancos activos con preguntas para esta materia y modalidad.
                        <a href="{{ route('teacher.question-banks.index') }}" class="alert-link">Crear o revisar bancos</a>.
                    </div>
                @else
                    @php
                        $checkedIds = collect(old('question_ids', $selectedQuestionIds))
                            ->map(fn ($id) => (int) $id)
                            ->all();
                    @endphp

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <button type="button" id="select_all_questions" class="btn btn-outline-primary btn-sm" @disabled($hasAttempts)>
                                Seleccionar todas
                            </button>
                            <button type="button" id="clear_all_questions" class="btn btn-outline-secondary btn-sm" @disabled($hasAttempts)>
                                Deseleccionar todas
                            </button>
                        </div>
                        <span class="badge badge-primary">
                            <span id="selected_count">0</span> seleccionadas
                        </span>
                    </div>

                    @foreach($banks as $bank)
                        <div class="border rounded mb-3">
                            <div class="bg-light px-3 py-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $bank->name }}</strong>
                                    <span class="text-muted">
                                        {{ $bank->schoolCycle ? ' / '.$bank->schoolCycle->name : '' }}
                                        {{ $bank->partial ? ' / '.$bank->partial->name : '' }}
                                    </span>
                                </div>
                                <span class="badge badge-light">{{ $bank->questions->count() }} preguntas</span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 42px;"></th>
                                            <th style="width: 180px;">Tipo</th>
                                            <th>Pregunta</th>
                                            <th style="width: 90px;" class="text-right">Puntos</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($bank->questions as $question)
                                            <tr>
                                                <td>
                                                    <input
                                                        type="checkbox"
                                                        name="question_ids[]"
                                                        value="{{ $question->id }}"
                                                        class="question-checkbox"
                                                        @checked(in_array((int) $question->id, $checkedIds, true))
                                                        @disabled($hasAttempts)
                                                    >
                                                </td>
                                                <td>{{ $question->type_label }}</td>
                                                <td>{{ $question->prompt }}</td>
                                                <td class="text-right">{{ number_format((float) $question->points, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('teacher.paper-exams.show', $paperExam) }}" class="btn btn-outline-secondary">
                    Ver intentos
                </a>
                <button class="btn btn-primary" @disabled($banks->isEmpty() || $hasAttempts)>
                    Guardar preguntas
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = Array.from(document.querySelectorAll('.question-checkbox'));
    const selectedCount = document.getElementById('selected_count');
    const selectAllButton = document.getElementById('select_all_questions');
    const clearAllButton = document.getElementById('clear_all_questions');

    function updateCount() {
        if (!selectedCount) return;
        selectedCount.textContent = checkboxes.filter((checkbox) => checkbox.checked).length;
    }

    if (selectAllButton) {
        selectAllButton.addEventListener('click', function () {
            checkboxes.forEach((checkbox) => checkbox.checked = true);
            updateCount();
        });
    }

    if (clearAllButton) {
        clearAllButton.addEventListener('click', function () {
            checkboxes.forEach((checkbox) => checkbox.checked = false);
            updateCount();
        });
    }

    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateCount));
    updateCount();
});
</script>
@endsection
