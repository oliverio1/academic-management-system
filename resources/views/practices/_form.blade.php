@if($errors->any())
    <div class="alert alert-danger">
        <strong>Revisa la informacion:</strong>
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $currentKind = old('kind', $practice->kind ?? 'task');
@endphp

<div class="delivery-editor">
    <div class="delivery-editor__band mb-3">
        <div>
            <h5 class="mb-1">Datos generales</h5>
            <p class="text-muted mb-0">Define que se entrega, cuando se entrega y con que rubro se calificara.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-2">
            <div class="form-group">
                <label>Numero</label>
                <input type="number"
                       name="number"
                       min="1"
                       class="form-control"
                       value="{{ old('number', $practice->number ?? '') }}"
                       required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label>Tipo</label>
                <select name="kind" id="kind" class="form-control" required>
                    @foreach($kindLabels as $value => $label)
                        <option value="{{ $value }}" @selected($currentKind === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">Usalo para tareas, practicas o proyectos. El flujo es el mismo.</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Fecha de realizacion</label>
                <input type="date"
                       name="realization_date"
                       class="form-control"
                       value="{{ old('realization_date', optional($practice->realization_date ?? null)->toDateString()) }}">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Fecha de entrega</label>
                <input type="date"
                       name="due_date"
                       class="form-control"
                       value="{{ old('due_date', optional($practice->due_date ?? null)->toDateString()) }}"
                       required>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label>Titulo</label>
        <input type="text"
               name="title"
               class="form-control"
               value="{{ old('title', $practice->title ?? '') }}"
               required>
    </div>

    <div class="form-group">
        <label>Rubro de evaluacion</label>
        <select name="evaluation_criterion_id" class="form-control" required>
            <option value="">Selecciona un rubro</option>
            @foreach($evaluationCriteria as $criterion)
                <option value="{{ $criterion->id }}"
                    @selected((int) old('evaluation_criterion_id', $practice->activity->evaluation_criterion_id ?? 0) === (int) $criterion->id)>
                    {{ $criterion->name }}
                    @if($criterion->cyclePartial)
                        - {{ $criterion->cyclePartial->name }}
                    @endif
                </option>
            @endforeach
        </select>
        @if($evaluationCriteria->isEmpty())
            <small class="text-danger">Primero configura los rubros de evaluacion de esta clase.</small>
        @endif
    </div>

    <div class="alert alert-info mb-3">
        Puedes usar LaTeX en los textos, por ejemplo <code>\(v=\frac{d}{t}\)</code> o <code>\[g=\frac{4\pi^2L}{T^2}\]</code>.
    </div>

    <div class="delivery-editor__band mb-3">
        <div>
            <h5 class="mb-1">Indicaciones para el alumno</h5>
            <p class="text-muted mb-0">Este contenido aparece como guia antes de que el alumno capture su entrega.</p>
        </div>
    </div>

    <div class="form-group">
        <label>Contexto o introduccion</label>
        <textarea name="introduction"
                  class="form-control"
                  rows="4">{{ old('introduction', $practice->introduction ?? '') }}</textarea>
    </div>

    <div class="form-group">
        <label>Instrucciones</label>
        <textarea name="instructions"
                  class="form-control"
                  rows="4">{{ old('instructions', $practice->instructions ?? '') }}</textarea>
    </div>

    <div class="form-group">
        <label>Procedimiento, criterios o pasos sugeridos</label>
        <textarea name="procedure"
                  class="form-control"
                  rows="5">{{ old('procedure', $practice->procedure ?? '') }}</textarea>
    </div>

    <div class="delivery-editor__band mb-3">
        <div>
            <h5 class="mb-1">Plantilla de captura</h5>
            <p class="text-muted mb-0">Define los apartados que el alumno debe llenar. Funcionan para tarea, practica o proyecto.</p>
        </div>
    </div>

    <div class="mb-4">
        <h5 class="mb-1">Campos por entregar</h5>
        <p class="text-muted small mb-2">
            Agrega tantos campos como necesites. Marca como obligatorio lo que no pueda quedar vacio.
        </p>
        <div id="delivery-fields-builder"></div>
        <button type="button"
                class="btn btn-sm btn-outline-primary"
                onclick="addDeliveryField()">
            Agregar campo
        </button>
        <input type="hidden" name="custom_submission_fields" id="delivery-fields-input">
    </div>

    <div>
        <h5 class="mb-1">Preguntas adicionales</h5>
        <p class="text-muted small mb-2">
            Opcional. Usalas si quieres respuestas puntuales ademas del texto del entregable.
        </p>

        <div id="questionnaire-builder"></div>

        <button type="button"
                class="btn btn-sm btn-outline-primary mb-3"
                onclick="addQuestion()">
            Agregar pregunta
        </button>

        <input type="hidden" name="questionnaire" id="questionnaire-input">
    </div>
</div>

<style>
    .delivery-editor__band {
        align-items: center;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        display: flex;
        justify-content: space-between;
        padding: .85rem 1rem;
    }

    .delivery-builder-card {
        border: 1px solid #dbe3ee;
        border-radius: 6px;
        margin-bottom: .65rem;
        padding: .85rem;
    }
</style>
