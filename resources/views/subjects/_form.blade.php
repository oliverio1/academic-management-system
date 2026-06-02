@if($errors->any())
    <div class="col-md-12">
        <div class="alert alert-danger">
            <strong>No se pudo guardar la materia. Revisa:</strong>
            <ul class="mb-0 mt-2 pl-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Nivel</label>
        <select name="level_id" class="form-control @error('level_id') is-invalid @enderror" required>
            <option value="">Seleccione nivel</option>
            @foreach($levels as $level)
                <option value="{{ $level->id }}" {{ old('level_id', $subject->level_id ?? '') == $level->id ? 'selected' : '' }}>
                    {{ $level->name }} ({{ $level->modality->name }})
                </option>
            @endforeach
        </select>
        @error('level_id')
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Nombre de la materia</label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $subject->name ?? '') }}" required>
        @error('name')
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Horas por semana</label>
        <input type="number" name="hours_per_week" class="form-control @error('hours_per_week') is-invalid @enderror" value="{{ old('hours_per_week', $subject->hours_per_week ?? '') }}" required>
        @error('hours_per_week')
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Tipo de materia</label>
        <select name="type" class="form-control @error('type') is-invalid @enderror" required>
            <option value="">Seleccione el tipo de materia</option>
            <option value="Teórica" {{ old('type', $subject->type ?? '') == 'Teórica' ? 'selected' : '' }}>Teórica</option>
            <option value="Laboratorio" {{ old('type', $subject->type ?? '') == 'Laboratorio' ? 'selected' : '' }}>Laboratorio</option>
            <option value="Taller" {{ old('type', $subject->type ?? '') == 'Taller' ? 'selected' : '' }}>Taller</option>
        </select>
        @error('type')
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>

@if(isset($subject))
    <div class="col-md-12 mb-3">
        <div class="form-check">
            <input type="checkbox" name="is_active" class="form-check-input" {{ old('is_active', $subject->is_active) ? 'checked' : '' }}>
            <label class="form-check-label">Activo</label>
        </div>
    </div>
@endif
