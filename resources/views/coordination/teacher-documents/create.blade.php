@extends('layouts.app')

@section('title', 'Nueva solicitud documental')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Nueva solicitud de documentos docentes</h4>
                    @if($activeCycle)
                        <small class="text-muted">Ciclo activo: {{ $activeCycle->name }} ({{ $activeCycle->code }})</small>
                    @endif
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('coordination.teacher-documents.store') }}">
                        @csrf
                        <div class="form-row">
                            <div class="col-md-6 mb-3">
                                <label>Título</label>
                                <input class="form-control" name="title" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Fecha límite</label>
                                <input type="date" class="form-control" name="due_date" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label>Instrucciones</label>
                                <textarea class="form-control" name="instructions" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label>Tipos de documento solicitados</label>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>Solicitar</th>
                                        <th>Tipo de documento</th>
                                        <th>Visible para alumnos</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                @foreach($documentTypes as $key => $label)
                                    <tr>
                                        <td style="width: 120px;">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="document_types[]" value="{{ $key }}" id="doc_{{ $key }}">
                                                <label class="form-check-label" for="doc_{{ $key }}">Sí</label>
                                            </div>
                                        </td>
                                        <td>{{ $label }}</td>
                                        <td style="width: 220px;">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="student_visible_types[]" value="{{ $key }}" id="student_visible_{{ $key }}">
                                                <label class="form-check-label" for="student_visible_{{ $key }}">Mostrar a alumnos</label>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label>Asignaciones (materia - grupo - docente)</label>
                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="select-all-assignments">Seleccionar todo</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="deselect-all-assignments">Deseleccionar todo</button>
                            </div>
                            <div class="table-responsive border rounded p-2" style="max-height: 360px;">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                    <tr>
                                        <th style="width:50px;"></th>
                                        <th>Materia</th>
                                        <th>Grupo</th>
                                        <th>Docente</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($assignments as $assignment)
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="assignment_ids[]" value="{{ $assignment->id }}">
                                            </td>
                                            <td>{{ optional($assignment->subject)->name }}</td>
                                            <td>{{ optional($assignment->group)->name }}</td>
                                            <td>{{ optional($assignment->teacher->user)->name }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted">Sin asignaciones disponibles.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <button class="btn btn-primary">Crear solicitud</button>
                        <a href="{{ route('coordination.teacher-documents.index') }}" class="btn btn-secondary">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_scripts')
<script>
    (function () {
        const selectAllBtn = document.getElementById('select-all-assignments');
        const deselectAllBtn = document.getElementById('deselect-all-assignments');
        const checkboxes = () => document.querySelectorAll('input[name="assignment_ids[]"]');

        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function () {
                checkboxes().forEach(cb => cb.checked = true);
            });
        }

        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', function () {
                checkboxes().forEach(cb => cb.checked = false);
            });
        }
    })();
</script>
@endsection
