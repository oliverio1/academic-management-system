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
        <label>Tipo DGIRE de la materia</label>
        <select name="type" class="form-control @error('type') is-invalid @enderror" required>
            <option value="">Seleccione el tipo DGIRE</option>
            @foreach(\App\Models\Subject::dgireTypeOptions() as $value => $label)
                <option value="{{ $value }}" {{ old('type', $subject->type ?? \App\Models\Subject::TYPE_THEORETICAL) === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Determina si la planeacion usa el formato teorico o teorico-practico.</small>
        @error('type')
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="col-md-6 mb-3">
    <div class="form-group">
        <label>Caracter de la asignatura</label>
        <input type="text" name="subject_character" class="form-control @error('subject_character') is-invalid @enderror" value="{{ old('subject_character', $subject->subject_character ?? '') }}" placeholder="Obligatoria, optativa, obligatoria de eleccion">
        @error('subject_character')
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="col-md-6 mb-3">
    <div class="form-group">
        <label>Clave de la asignatura</label>
        <input type="text" name="subject_key" class="form-control @error('subject_key') is-invalid @enderror" value="{{ old('subject_key', $subject->subject_key ?? '') }}" placeholder="Clave DGIRE/UNAM">
        @error('subject_key')
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="col-md-4 mb-3">
    <div class="form-group">
        <label>Horas por semana</label>
        <input type="number" name="hours_per_week" class="form-control @error('hours_per_week') is-invalid @enderror" value="{{ old('hours_per_week', $subject->hours_per_week ?? '') }}" min="1" max="10" required>
        @error('hours_per_week')
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="col-md-4 mb-3">
    <div class="form-group">
        <label>Horas teoricas por semana</label>
        <input type="number" name="weekly_theory_hours" class="form-control @error('weekly_theory_hours') is-invalid @enderror" value="{{ old('weekly_theory_hours', $subject->weekly_theory_hours ?? '') }}" min="0" max="10">
        @error('weekly_theory_hours')
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="col-md-4 mb-3">
    <div class="form-group">
        <label>Horas practicas por semana</label>
        <input type="number" name="weekly_practice_hours" class="form-control @error('weekly_practice_hours') is-invalid @enderror" value="{{ old('weekly_practice_hours', $subject->weekly_practice_hours ?? '') }}" min="0" max="10">
        @error('weekly_practice_hours')
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="col-md-4 mb-3">
    <div class="form-group">
        <label>Total de horas anuales</label>
        <input type="number" name="annual_hours" class="form-control @error('annual_hours') is-invalid @enderror" value="{{ old('annual_hours', $subject->annual_hours ?? '') }}" min="0" max="2000">
        @error('annual_hours')
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="col-md-4 mb-3">
    <div class="form-group">
        <label>Horas teoricas anuales</label>
        <input type="number" name="annual_theory_hours" class="form-control @error('annual_theory_hours') is-invalid @enderror" value="{{ old('annual_theory_hours', $subject->annual_theory_hours ?? '') }}" min="0" max="2000">
        @error('annual_theory_hours')
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="col-md-4 mb-3">
    <div class="form-group">
        <label>Horas practicas anuales</label>
        <input type="number" name="annual_practice_hours" class="form-control @error('annual_practice_hours') is-invalid @enderror" value="{{ old('annual_practice_hours', $subject->annual_practice_hours ?? '') }}" min="0" max="2000">
        @error('annual_practice_hours')
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
