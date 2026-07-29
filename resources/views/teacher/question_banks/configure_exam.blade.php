@extends('layouts.app')

@section('title', 'Configurar examen')

@section('content')
<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Configurar examen</h4>
                <small class="text-muted">
                    {{ $questionBank->name }} / {{ $questionBank->subject->name ?? 'N/D' }}
                    @if($questionBank->partial)
                        / {{ $questionBank->partial->name }}
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

                @if($examTargets->isEmpty())
                    <div class="alert alert-warning">
                        No hay grupos activos disponibles para esta materia en el ciclo del banco.
                    </div>
                @elseif($exams->isEmpty())
                    <div class="alert alert-info">
                        Coordinacion aun no ha programado este examen. Puedes generarlo ahora para el grupo seleccionado.
                    </div>
                @endif

                @if($questionBank->questions->isEmpty())
                    <div class="alert alert-info">
                        Este banco todavia no tiene preguntas cargadas.
                    </div>
                @endif

                <div class="form-group">
                    <label>Examen / grupo</label>
                    <select name="exam_target" id="exam_target" class="form-control" required {{ $examTargets->isEmpty() ? 'disabled' : '' }}>
                        @foreach($examTargets as $target)
                            <option value="{{ $target['value'] }}" data-question-ids='@json($target['question_ids'])'>
                                {{ $target['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">
                        Si el examen aun no existe, se creara al guardar las preguntas.
                    </small>
                </div>

                <div class="custom-control custom-checkbox mb-3">
                    <input type="checkbox" name="apply_scope" value="all_groups" class="custom-control-input" id="apply_scope_all_groups">
                    <label class="custom-control-label" for="apply_scope_all_groups">
                        Aplicar estas preguntas a todos mis grupos activos de esta materia
                    </label>
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
                <button class="btn btn-primary" {{ $examTargets->isEmpty() || $questionBank->questions->isEmpty() ? 'disabled' : '' }}>
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
    const examSelect = document.getElementById('exam_target');
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
