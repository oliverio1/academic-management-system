<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Nombre de la modalidad</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $group->name ?? '') }}" required>
        @error('name')
            <div class="form-text text-danger">Este campo es obligatorio</div>
        @enderror
    </div>
</div>