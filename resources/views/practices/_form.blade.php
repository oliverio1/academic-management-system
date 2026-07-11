@if($errors->any())
    <div class="alert alert-danger">
        <strong>Revisa la información:</strong>
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label>Número</label>
            <input type="number"
                   name="number"
                   min="1"
                   class="form-control"
                   value="{{ old('number', $practice->number ?? '') }}"
                   required>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Tipo</label>
            <select name="kind" class="form-control" required>
                @foreach($kindLabels as $value => $label)
                    <option value="{{ $value }}" @selected(old('kind', $practice->kind ?? 'practice') === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Fecha de realización</label>
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
    <label>Título</label>
    <input type="text"
           name="title"
           class="form-control"
           value="{{ old('title', $practice->title ?? '') }}"
           required>
</div>

<div class="form-group">
    <label>Rubro de evaluación</label>
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
        <small class="text-danger">Primero configura los rubros de evaluación de esta clase.</small>
    @endif
</div>

<div class="alert alert-info">
    Puedes usar formato LaTeX en los textos, por ejemplo <code>\(v=\frac{d}{t}\)</code> o <code>\[g=\frac{4\pi^2L}{T^2}\]</code>.
</div>

<div class="form-group">
    <label>Introducción para el alumno</label>
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
    <label>Procedimiento</label>
    <textarea name="procedure"
              class="form-control"
              rows="5">{{ old('procedure', $practice->procedure ?? '') }}</textarea>
</div>

<hr>
<div class="mb-3">
    <h5>Campos por entregar</h5>
    <p class="text-muted small mb-2">
        Agrega tantos campos como necesites. El alumno verá cada campo como una sección para capturar texto.
    </p>
    <div id="delivery-fields-builder"></div>
    <button type="button"
            class="btn btn-sm btn-secondary"
            onclick="addDeliveryField()">
        Agregar campo
    </button>
    <input type="hidden" name="custom_submission_fields" id="delivery-fields-input">
</div>

<hr>
<div>
    <h5>Preguntas adicionales</h5>
    <p class="text-muted small mb-2">
        Opcional. Úsalas si quieres que el alumno responda preguntas específicas además del reporte.
    </p>

    <div id="questionnaire-builder"></div>

    <button type="button"
            class="btn btn-sm btn-secondary mb-3"
            onclick="addQuestion()">
        Agregar pregunta
    </button>

    <input type="hidden" name="questionnaire" id="questionnaire-input">
</div>
