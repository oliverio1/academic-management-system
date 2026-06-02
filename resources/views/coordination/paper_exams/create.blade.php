@extends('layouts.app')

@section('title', 'Nuevo examen imprimible')

@section('content')
<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header"><h4 class="mb-0">Crear examen imprimible</h4></div>
        <form method="POST" action="{{ route('coordination.paper-exams.store') }}">
            @csrf
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Título</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Asignación (materia-grupo)</label>
                        <select id="teaching_assignment_id" name="teaching_assignment_id" class="form-control" required>
                            @foreach($assignments as $assignment)
                                <option value="{{ $assignment->id }}" data-subject-id="{{ $assignment->subject_id }}">
                                    {{ $assignment->subject->name ?? 'N/D' }} - Grupo {{ $assignment->group->name ?? 'N/D' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label>Ciclo</label>
                        <input type="hidden" name="school_cycle_id" value="{{ optional($activeCycle)->id }}">
                        <input class="form-control" value="{{ optional($activeCycle)->name }} ({{ optional($activeCycle)->code }})" disabled>
                    </div>
                    <div class="form-group col-md-3">
                        <label>Duración (min)</label>
                        <input type="number" name="duration_minutes" min="1" max="600" class="form-control" value="50">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Instrucciones</label>
                        <input type="text" name="instructions" class="form-control">
                    </div>
                    <div class="form-group col-md-2">
                        <label>Intentos máx.</label>
                        <input type="number" name="online_max_attempts" min="1" max="10" class="form-control" value="1">
                    </div>
                    <div class="form-group col-md-2">
                        <label>Disponible desde</label>
                        <input type="datetime-local" name="online_available_from" class="form-control">
                    </div>
                    <div class="form-group col-md-2">
                        <label>Disponible hasta</label>
                        <input type="datetime-local" name="online_available_until" class="form-control">
                    </div>
                    <div class="form-group col-md-3">
                        <div class="custom-control custom-checkbox mt-4">
                            <input type="checkbox" class="custom-control-input" id="is_online_enabled" name="is_online_enabled" value="1">
                            <label class="custom-control-label" for="is_online_enabled">Habilitar examen en línea</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="online_show_result" name="online_show_result" value="1">
                            <label class="custom-control-label" for="online_show_result">Mostrar resultado al alumno</label>
                        </div>
                    </div>
                </div>
                <hr>
                <label>Seleccionar preguntas</label>
                <div class="mb-2">
                    <button type="button" id="select_all_visible" class="btn btn-outline-primary btn-sm">Seleccionar visibles</button>
                    <button type="button" id="clear_all_visible" class="btn btn-outline-secondary btn-sm">Deseleccionar visibles</button>
                </div>
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-sm table-striped">
                        <thead><tr><th></th><th>Banco</th><th>Tipo</th><th>Enunciado</th></tr></thead>
                        <tbody>
                            @foreach($banks as $bank)
                                @foreach($bank->questions->sortBy('sort_order') as $question)
                                    <tr class="question-row" data-subject-id="{{ $bank->subject_id }}">
                                        <td><input type="checkbox" name="question_ids[]" value="{{ $question->id }}"></td>
                                        <td>{{ $bank->name }} / {{ $bank->subject->name ?? 'N/D' }}</td>
                                        <td>{{ $question->type }}</td>
                                        <td>{{ $question->prompt }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary">Crear examen</button>
                <a href="{{ route('coordination.paper-exams.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('page_css')
<style>
    #teaching_assignment_id + .select2-container .select2-selection--single {
        height: calc(2.25rem + 2px) !important;
        border: 1px solid #ced4da !important;
        border-radius: .25rem !important;
    }

    #teaching_assignment_id + .select2-container .select2-selection__rendered {
        line-height: calc(2.25rem + 2px) !important;
        padding-left: .75rem !important;
        padding-right: 2rem !important;
    }

    #teaching_assignment_id + .select2-container .select2-selection__arrow {
        height: calc(2.25rem + 2px) !important;
        right: 6px !important;
    }
</style>
@endsection

@section('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const assignmentSelect = document.getElementById('teaching_assignment_id');
    if (!assignmentSelect) return;

    const rows = Array.from(document.querySelectorAll('.question-row'));
    const selectVisibleBtn = document.getElementById('select_all_visible');
    const clearVisibleBtn = document.getElementById('clear_all_visible');

    if (window.$ && $.fn.select2) {
        $('#teaching_assignment_id').select2({
            width: '100%',
            placeholder: 'Buscar materia/grupo...',
            allowClear: false,
            dropdownAutoWidth: true
        });
    }

    function filterQuestionsBySubject() {
        const selected = assignmentSelect.options[assignmentSelect.selectedIndex];
        const subjectId = selected ? String(selected.dataset.subjectId || '') : '';

        rows.forEach((row) => {
            const rowSubjectId = String(row.dataset.subjectId || '');
            const visible = subjectId !== '' && rowSubjectId === subjectId;
            row.style.display = visible ? '' : 'none';

            if (!visible) {
                const checkbox = row.querySelector('input[type="checkbox"]');
                if (checkbox) checkbox.checked = false;
            }
        });
    }

    function getVisibleRows() {
        return rows.filter((row) => row.style.display !== 'none');
    }

    if (selectVisibleBtn) {
        selectVisibleBtn.addEventListener('click', function () {
            getVisibleRows().forEach((row) => {
                const checkbox = row.querySelector('input[type="checkbox"]');
                if (checkbox) checkbox.checked = true;
            });
        });
    }

    if (clearVisibleBtn) {
        clearVisibleBtn.addEventListener('click', function () {
            getVisibleRows().forEach((row) => {
                const checkbox = row.querySelector('input[type="checkbox"]');
                if (checkbox) checkbox.checked = false;
            });
        });
    }

    assignmentSelect.addEventListener('change', filterQuestionsBySubject);
    if (window.$ && $('#teaching_assignment_id').data('select2')) {
        $('#teaching_assignment_id').on('change', filterQuestionsBySubject);
    }
    filterQuestionsBySubject();
});
</script>
@endsection
