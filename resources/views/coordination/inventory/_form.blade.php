@if($errors->any())
    <div class="col-md-12">
        <div class="alert alert-danger">
            <strong>No se pudo guardar. Revisa:</strong>
            <ul class="mb-0 mt-2 pl-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<div class="col-md-4 mb-3">
    <label>Codigo interno</label>
    <input type="text" name="code" class="form-control" value="{{ old('code', $item->code ?? '') }}" placeholder="LAB-001">
</div>

<div class="col-md-8 mb-3">
    <label>Nombre del articulo</label>
    <input type="text" name="name" class="form-control" value="{{ old('name', $item->name ?? '') }}" required>
</div>

<div class="col-md-4 mb-3">
    <label>Tipo</label>
    <select name="item_type" class="form-control" required>
        @foreach($itemTypes as $key => $label)
            <option value="{{ $key }}" {{ old('item_type', $item->item_type ?? 'material') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>

<div class="col-md-4 mb-3">
    <label>Categoria</label>
    <select name="category_id" class="form-control" required>
        <option value="">Seleccione categoria</option>
        @foreach($categories as $category)
            <option value="{{ $category->id }}" {{ old('category_id', $item->category_id ?? '') == $category->id ? 'selected' : '' }}>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="col-md-4 mb-3">
    <label>Ubicacion</label>
    <select name="location_id" class="form-control">
        <option value="">Sin ubicacion</option>
        @foreach($locations as $location)
            <option value="{{ $location->id }}" {{ old('location_id', $item->location_id ?? '') == $location->id ? 'selected' : '' }}>
                {{ $location->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="col-md-3 mb-3">
    <label>Unidad</label>
    <input type="text" name="unit" class="form-control" value="{{ old('unit', $item->unit ?? 'pieza') }}" required>
</div>

<div class="col-md-3 mb-3">
    <label>Existencia</label>
    <input type="number" step="0.01" name="quantity" class="form-control" value="{{ old('quantity', $item->quantity ?? 0) }}" {{ isset($item) ? 'readonly' : 'required' }}>
    @isset($item)
        <small class="text-muted">Se cambia desde movimientos.</small>
    @endisset
</div>

<div class="col-md-3 mb-3">
    <label>Minimo</label>
    <input type="number" step="0.01" name="minimum_quantity" class="form-control" value="{{ old('minimum_quantity', $item->minimum_quantity ?? 0) }}">
</div>

<div class="col-md-3 mb-3">
    <label>Caducidad</label>
    <input type="date" name="expiration_date" class="form-control" value="{{ old('expiration_date', isset($item?->expiration_date) ? $item->expiration_date->format('Y-m-d') : '') }}">
</div>

<div class="col-md-4 mb-3">
    <label>Condicion</label>
    <select name="condition" class="form-control" required>
        @foreach(['nuevo' => 'Nuevo', 'bueno' => 'Bueno', 'regular' => 'Regular', 'mantenimiento' => 'Mantenimiento', 'danado' => 'Danado'] as $key => $label)
            <option value="{{ $key }}" {{ old('condition', $item->condition ?? 'bueno') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>

<div class="col-md-4 mb-3">
    <label>Estatus</label>
    <select name="status" class="form-control" required>
        @foreach(['disponible' => 'Disponible', 'prestado' => 'Prestado', 'mantenimiento' => 'Mantenimiento', 'agotado' => 'Agotado', 'baja' => 'Baja'] as $key => $label)
            <option value="{{ $key }}" {{ old('status', $item->status ?? 'disponible') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>

<div class="col-md-4 mb-3">
    <label>Activo</label>
    <select name="is_active" class="form-control">
        <option value="1" {{ old('is_active', $item->is_active ?? true) ? 'selected' : '' }}>Si</option>
        <option value="0" {{ ! old('is_active', $item->is_active ?? true) ? 'selected' : '' }}>No</option>
    </select>
</div>

<div class="col-md-6 mb-3">
    <label>Notas de almacenamiento</label>
    <textarea name="storage_notes" class="form-control" rows="3">{{ old('storage_notes', $item->storage_notes ?? '') }}</textarea>
</div>

<div class="col-md-6 mb-3">
    <label>Riesgos / manejo especial</label>
    <textarea name="hazard_notes" class="form-control" rows="3">{{ old('hazard_notes', $item->hazard_notes ?? '') }}</textarea>
</div>

<div class="col-md-12 mb-3">
    <label>Descripcion</label>
    <textarea name="description" class="form-control" rows="3">{{ old('description', $item->description ?? '') }}</textarea>
</div>
