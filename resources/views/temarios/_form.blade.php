@if($errors->any())
    <div class="alert alert-danger">
        <strong>No se pudo guardar el temario. Revisa lo siguiente:</strong>
        <ul class="mb-0 mt-2 pl-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="form-group">
    <label>Titulo del temario</label>
    <input type="text"
           name="title"
           class="form-control @error('title') is-invalid @enderror"
           value="{{ old('title', $temario->title ?? '') }}"
           placeholder="Ejemplo: Unidad 1. Elementos quimicos en los dispositivos moviles">
    @error('title')
        <span class="invalid-feedback">{{ $message }}</span>
    @enderror
</div>

<div class="form-group">
    <label>Descripcion (opcional)</label>
    <textarea name="description"
              rows="3"
              class="form-control @error('description') is-invalid @enderror"
              placeholder="Notas generales del temario">{{ old('description', $temario->description ?? '') }}</textarea>
    @error('description')
        <span class="invalid-feedback">{{ $message }}</span>
    @enderror
</div>

<hr>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">Unidades y puntos del temario</h5>
    <button type="button" class="btn btn-sm btn-outline-primary" id="add-unit-btn">
        Agregar unidad
    </button>
</div>

@error('units')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror

<div id="units-container"></div>
