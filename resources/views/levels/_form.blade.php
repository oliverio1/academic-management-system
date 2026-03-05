<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Modalidad</label>
        <select name="modality_id" class="form-control" required>
            <option value="">Seleccione una modalidad</option>
            @foreach($modalities as $modality)
                <option value="{{ $modality->id }}" {{ old('modality_id', $level->modality_id ?? '') == $modality->id ? 'selected' : '' }}>{{ $modality->name }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Nombre del nivel</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $level->name ?? '') }}" required>
        @error('name')
            <div class="form-text text-danger">Este campo es obligatorio</div>
        @enderror
    </div>
</div>