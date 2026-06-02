<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Nombre del campus</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $campus->name ?? '') }}" required>
        @error('name')
            <div class="form-text text-danger">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Codigo corto</label>
        <input type="text" name="code" class="form-control" value="{{ old('code', $campus->code ?? '') }}" required maxlength="30">
        @error('code')
            <div class="form-text text-danger">{{ $message }}</div>
        @enderror
    </div>
</div>

