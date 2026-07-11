@extends('layouts.app')

@section('title', 'Configurar examen')

@section('content')
<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Configurar examen</h4>
                <small class="text-muted">
                    {{ $questionBank->name }} · {{ $questionBank->subject->name ?? 'N/D' }}
                    @if($questionBank->partial)
                        · {{ $questionBank->partial->name }}
                    @endif
                </small>
            </div>
            <a href="{{ route('teacher.question-banks.index') }}" class="btn btn-outline-secondary btn-sm">
                Volver
            </a>
        </div>

        <form method="POST" action="{{ route('teacher.question-banks.exam.update', $questionBank) }}">
            @csrf
            @method('PUT')

            <div class="card-body">
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

                @if($exams->isEmpty())
                    <div class="alert alert-warning">
                        No hay exámenes programados para esta materia/parcial. Coordinación debe programar primero el horario del examen.
                    </div>
                @endif

                @if($questionBank->questions->isEmpty())
                    <div class="alert alert-info">
                        Este banco todavía no tiene preguntas cargadas.
                    </div>
                @endif

                <div class="form-group">
                    <label>Examen programado</label>
                    <select name="paper_exam_id" id="paper_exam_id" class="form-control" required {{ $exams->isEmpty() ? 'disabled' : '' }}>
                        @foreach($exams as $exam)
                            @php
                                $selectedIds = $exam->examQuestions
                                    ->pluck('question_id')
                                    ->map(fn ($id) => (int) $id)
                                    ->values()
                                    ->all();
                            @endphp
                            <option value="{{ $exam->id }}" data-question-ids='@json($selectedIds)'>
                                {{ $exam->title }}
                                · Grupo {{ $exam->assignment->group->name ?? 'N/D' }}
                                @if($exam->online_available_from)
                                    · {{ $exam->online_available_from->format('d/m/Y H:i') }}
                                @endif
                                · {{ $exam->exam_questions_count }} pregunta(s)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="mb-0">Preguntas cargadas</label>
                    <div>
                        <button type="button" id="select_all_questions" class="btn btn-outline-primary btn-sm" {{ $questionBank->questions->isEmpty() ? 'disabled' : '' }}>
                            Seleccionar todas
                        </button>
                        <button type="button" id="clear_all_questions" class="btn btn-outline-secondary btn-sm" {{ $questionBank->questions->isEmpty() ? 'disabled' : '' }}>
                            Limpiar
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th style="width: 42px;"></th>
                                <th style="width: 170px;">Tipo</th>
                                <th>Pregunta</th>
                                <th style="width: 90px;" class="text-right">Puntos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($questionBank->questions as $question)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="question_ids[]" value="{{ $question->id }}" class="question-checkbox">
                                    </td>
                                    <td>{{ $question->type_label }}</td>
                                    <td>{{ $question->prompt }}</td>
                                    <td class="text-right">{{ number_format((float) $question->points, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Sin preguntas cargadas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer text-right">
                <button class="btn btn-primary" {{ $exams->isEmpty() || $questionBank->questions->isEmpty() ? 'disabled' : '' }}>
                    Guardar preguntas del examen
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const examSelect = document.getElementById('paper_exam_id');
    const checkboxes = Array.from(document.querySelectorAll('.question-checkbox'));
    const selectAllButton = document.getElementById('select_all_questions');
    const clearAllButton = document.getElementById('clear_all_questions');

    function selectedIdsFromExam() {
        if (!examSelect || !examSelect.selectedOptions.length) return [];
        try {
            return JSON.parse(examSelect.selectedOptions[0].dataset.questionIds || '[]').map(Number);
        } catch (error) {
            return [];
        }
    }

    function syncQuestionsFromExam() {
        const selectedIds = selectedIdsFromExam();
        checkboxes.forEach(function (checkbox) {
            checkbox.checked = selectedIds.includes(Number(checkbox.value));
        });
    }

    if (examSelect) {
        examSelect.addEventListener('change', syncQuestionsFromExam);
        syncQuestionsFromExam();
    }

    if (selectAllButton) {
        selectAllButton.addEventListener('click', function () {
            checkboxes.forEach((checkbox) => checkbox.checked = true);
        });
    }

    if (clearAllButton) {
        clearAllButton.addEventListener('click', function () {
            checkboxes.forEach((checkbox) => checkbox.checked = false);
        });
    }
});
</script>
@endsection
