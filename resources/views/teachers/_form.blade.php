<div class="form-group">
    <label for="name">Nombre completo</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $teacher->user->name ?? '') }}" required>
    @error('name')
        <span class="invalid-feedback">{{ $message }}</span>
    @enderror
</div>
<div class="form-group">
    <label for="email">Correo electrónico</label>
    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $teacher->user->email ?? '') }}" {{ isset($teacher) ? 'readonly' : '' }} required>
    @error('email')
        <span class="invalid-feedback">{{ $message }}</span>
    @enderror
</div>
<div class="form-group">
    <label for="phone">Teléfono</label>
    <input type="text" name="phone" class="form-control" value="{{ old('phone', $teacher->phone ?? '') }}">
</div>

<div class="form-group">
    <label for="address">Dirección</label>
    <textarea name="address" class="form-control" rows="3">{{ old('address', $teacher->address ?? '') }}</textarea>
</div>

@if(isset($teacher))
<div class="form-group form-check">
    <input type="checkbox" name="is_active" class="form-check-input" id="is_active" {{ old('is_active', $teacher->is_active) ? 'checked' : '' }}>
    <label class="form-check-label" for="is_active">Activo</label>
</div>
@endif
