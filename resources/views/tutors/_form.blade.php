<div class="form-group">
    <label for="name">Nombre completo</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $tutor->name ?? '') }}" required>
    @error('name')
        <span class="invalid-feedback">{{ $message }}</span>
    @enderror
</div>

<div class="form-group">
    <label for="email">Correo electronico</label>
    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $tutor->email ?? '') }}" {{ isset($tutor) ? 'readonly' : '' }} required>
    @error('email')
        <span class="invalid-feedback">{{ $message }}</span>
    @enderror
</div>

