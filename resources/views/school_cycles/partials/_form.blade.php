<div class="col-md-12 mb-3">
    <label class="form-label">Nombre del parcial</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $partial->name ?? '') }}" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="col-md-6 mb-3">
    <label class="form-label">Codigo (opcional)</label>
    <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $partial->code ?? '') }}">
    <small class="text-muted">Si lo dejas vacio, se generara automaticamente.</small>
    @error('code')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="col-md-6 mb-3">
    <label class="form-label">Orden</label>
    <input type="number" name="sort_order" min="1" class="form-control @error('sort_order') is-invalid @enderror" value="{{ old('sort_order', $partial->sort_order ?? 1) }}" required>
    @error('sort_order')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="col-md-6 mb-3">
    <label class="form-label">Fecha de inicio</label>
    <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', isset($partial) ? $partial->start_date?->format('Y-m-d') : '') }}" required>
    @error('start_date')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="col-md-6 mb-3">
    <label class="form-label">Fecha de termino</label>
    <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date', isset($partial) ? $partial->end_date?->format('Y-m-d') : '') }}" required>
    @error('end_date')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="col-md-6 mb-3">
    <label class="form-label">Fecha limite de cierre docente</label>
    <input type="datetime-local"
           name="teacher_capture_deadline_at"
           class="form-control @error('teacher_capture_deadline_at') is-invalid @enderror"
           value="{{ old('teacher_capture_deadline_at', isset($partial) && $partial->teacher_capture_deadline_at ? $partial->teacher_capture_deadline_at->format('Y-m-d\\TH:i') : '') }}">
    <small class="text-muted">Despues de esta fecha el sistema podra cerrar automaticamente y requerir reapertura por coordinacion.</small>
    @error('teacher_capture_deadline_at')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@if(isset($partial))
<div class="col-md-12 mb-3">
    <div class="form-check">
        <input type="checkbox" name="is_active" class="form-check-input" {{ old('is_active', $partial->is_active) ? 'checked' : '' }}>
        <label class="form-check-label">Activo</label>
    </div>
</div>
@endif
