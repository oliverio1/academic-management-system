<div class="form-row">
    <div class="col-md-4 mb-3">
        <label>Código</label>
        <input type="text" name="code" value="{{ old('code', optional($concept)->code) }}" class="form-control" required>
    </div>
    <div class="col-md-8 mb-3">
        <label>Nombre</label>
        <input type="text" name="name" value="{{ old('name', optional($concept)->name) }}" class="form-control" required>
    </div>
    <div class="col-md-12 mb-3">
        <label>Descripción</label>
        <textarea name="description" class="form-control" rows="2">{{ old('description', optional($concept)->description) }}</textarea>
    </div>
    <div class="col-md-4 mb-3">
        <label>Monto base</label>
        <input type="number" step="0.01" min="0" name="default_amount" value="{{ old('default_amount', optional($concept)->default_amount) }}" class="form-control">
    </div>
    <div class="col-md-4 mb-3 d-flex align-items-end">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', optional($concept)->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Activo</label>
        </div>
    </div>
</div>
<button class="btn btn-primary">Guardar</button>
<a href="{{ route('coordination.finance.concepts.index') }}" class="btn btn-secondary">Cancelar</a>

