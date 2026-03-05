<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Nivel</label>
        <select name="level_id" class="form-control" required>
            <option value="">Seleccione nivel</option>
            @foreach($levels as $level)
                <option value="{{ $level->id }}" {{ old('level_id', $group->level_id ?? '') == $level->id ? 'selected' : '' }}>{{ $level->name }} ({{ $level->modality->name }})</option>
            @endforeach
        </select>
    </div>
</div>

<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Nombre del grupo</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $group->name ?? '') }}" required>
    </div>
</div>

<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Cupo máximo</label>
        <input type="number" name="capacity" class="form-control" value="{{ old('capacity', $group->capacity ?? '') }}" required>
    </div>
</div>

@if(isset($group))
<div class="col-md-12 mb-3">
    <div class="form-check">
        <input type="checkbox" name="is_active" class="form-check-input" {{ old('is_active', $group->is_active) ? 'checked' : '' }}>
        <label class="form-check-label">Activo</label>
    </div>
</div>
@endif
