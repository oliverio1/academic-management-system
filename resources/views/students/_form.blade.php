<div class="form-group">
    <label for="name">Nombre completo</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $student->user->name ?? '') }}" required>
    @error('name')
        <span class="invalid-feedback">{{ $message }}</span>
    @enderror
</div>
<div class="form-group">
    <label for="email">Correo electrónico</label>
    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $student->user->email ?? '') }}" {{ isset($student) ? 'readonly' : '' }} required>
    @error('email')
        <span class="invalid-feedback">{{ $message }}</span>
    @enderror
</div>
<div class="form-group">
    <label for="enrollment_number">Matrícula</label>
    <input type="text" name="enrollment_number" class="form-control @error('enrollment_number') is-invalid @enderror" value="{{ old('enrollment_number', $student->enrollment_number ?? '') }}" required>
    @error('enrollment_number')
        <span class="invalid-feedback">{{ $message }}</span>
    @enderror
</div>
<div class="form-group">
    <label for="group_id">Grupo</label>
    @if(isset($student))
        {{-- Edición: NO se permite cambiar grupo --}}
        <input type="text"
               class="form-control"
               value="{{ $student->group->name }}"
               readonly>

        <input type="hidden" name="group_id" value="{{ $student->group_id }}">
    @else
        {{-- Alta: sí se selecciona grupo --}}
        <select name="group_id"
                class="form-control @error('group_id') is-invalid @enderror"
                required>
            <option value="">Seleccione un grupo</option>
            @foreach($groups as $group)
                <option value="{{ $group->id }}"
                    {{ old('group_id') == $group->id ? 'selected' : '' }}>
                    {{ $group->name }}
                </option>
            @endforeach
        </select>
        @error('group_id')
            <span class="invalid-feedback">{{ $message }}</span>
        @enderror
    @endif

</div>
<div class="form-group">
    <label for="phone">Teléfono</label>
    <input type="text" name="phone" class="form-control" value="{{ old('phone', $student->phone ?? '') }}">
</div>

<div class="form-group">
    <label for="address">Dirección</label>
    <textarea name="address" class="form-control" rows="3">{{ old('address', $student->address ?? '') }}</textarea>
</div>

@if(isset($student))
<div class="form-group form-check">
    <input type="checkbox" name="is_active" class="form-check-input" id="is_active" {{ old('is_active', $student->is_active) ? 'checked' : '' }}>
    <label class="form-check-label" for="is_active">Activo</label>
</div>
@endif
