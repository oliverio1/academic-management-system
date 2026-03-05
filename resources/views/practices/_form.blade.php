<div class="form-group">
    <label># de práctica</label>
    <input type="integer"
            name="number"
            class="form-control"
            value="{{ old('number', $practice->number ?? '') }}">
</div>

<div class="form-group">
    <label>Título</label>
    <input type="text"
            name="title"
            class="form-control"
            value="{{ old('title', $practice->title ?? '') }}">
</div>

<div class="form-group">
    <label>Instrucciones</label>
    <textarea name="instructions"
                class="form-control"
                rows="4">{{ old('instructions', $practice->instructions ?? '') }}</textarea>
</div>

<div class="form-group">
    <label>Fecha límite</label>
    <input type="date"
            name="due_date"
            class="form-control"
            value="{{ old('due_date', optional($practice->due_date ?? null)->toDateString()) }}">
</div>

<hr>
<div>
    <h5>Cuestionario</h5>
    
    <div id="questionnaire-builder"></div>
    
    <button type="button"
            class="btn btn-sm btn-secondary mb-3"
            onclick="addQuestion()">
        Agregar pregunta
    </button>
    
    <input type="hidden" name="questionnaire" id="questionnaire-input">
</div>