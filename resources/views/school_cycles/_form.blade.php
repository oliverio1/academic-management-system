<div class="col-md-12 mb-3">
    <label class="form-label">Campus asociados (opcional al crear)</label>
    @php
        $selectedCampuses = collect(old('campus_ids', isset($schoolCycle) ? $schoolCycle->campuses->pluck('id')->all() : []))
            ->map(fn ($id) => (int) $id)
            ->all();
    @endphp
    <select name="campus_ids[]" class="form-control js-campus-select @error('campus_ids') is-invalid @enderror @error('campus_ids.*') is-invalid @enderror" multiple>
        @foreach($campuses as $campus)
            <option value="{{ $campus->id }}" {{ in_array((int) $campus->id, $selectedCampuses, true) ? 'selected' : '' }}>
                {{ $campus->name }}
            </option>
        @endforeach
    </select>
    <small class="text-muted d-block mt-1">Puedes crear el ciclo sin campus y asociarlos después al editarlo.</small>
    @error('campus_ids')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @error('campus_ids.*')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="col-md-12 mb-3">
    <label class="form-label">Modalidades del ciclo</label>
    @php
        $selectedModalities = collect(old('modality_ids', isset($schoolCycle) ? $schoolCycle->modalities->pluck('id')->all() : []))
            ->map(fn ($id) => (int) $id)
            ->all();
    @endphp
    <select name="modality_ids[]" class="form-control js-modality-select @error('modality_ids') is-invalid @enderror @error('modality_ids.*') is-invalid @enderror" multiple required>
        @foreach($modalities as $modality)
            <option value="{{ $modality->id }}" {{ in_array((int) $modality->id, $selectedModalities, true) ? 'selected' : '' }}>
                {{ $modality->name }}
            </option>
        @endforeach
    </select>
    <small class="text-muted d-block mt-1">La primera modalidad seleccionada se usa como modalidad principal para compatibilidad.</small>
    @error('modality_ids')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @error('modality_ids.*')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="col-md-12 mb-3">
    <label class="form-label">Nombre del ciclo</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $schoolCycle->name ?? '') }}" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@if(!isset($schoolCycle))
<div class="col-md-12 mb-3">
    <div class="form-check">
        <input type="checkbox"
               id="clone_configuration"
               name="clone_configuration"
               value="1"
               class="form-check-input"
               {{ old('clone_configuration') ? 'checked' : '' }}>
        <label class="form-check-label" for="clone_configuration">
            Duplicar configuracion academica desde ciclo anterior (grupos y materias)
        </label>
    </div>
</div>

<div class="col-md-12 mb-3" id="source_cycle_wrapper" style="{{ old('clone_configuration') ? '' : 'display:none;' }}">
    <label class="form-label">Ciclo origen para duplicar (opcional)</label>
    <select id="source_cycle_id" name="source_cycle_id" class="form-control @error('source_cycle_id') is-invalid @enderror">
        <option value="">Automatico: tomar el ciclo mas reciente de esta modalidad</option>
        @foreach(($cycles ?? collect()) as $cycleItem)
            <option value="{{ $cycleItem->id }}"
                    data-modality-ids="{{ $cycleItem->modalities->pluck('id')->implode(',') }}"
                    data-campus-ids="{{ $cycleItem->campuses->pluck('id')->implode(',') }}"
                    {{ (string) old('source_cycle_id') === (string) $cycleItem->id ? 'selected' : '' }}>
                {{ $cycleItem->name }} ({{ $cycleItem->code }}) - {{ $cycleItem->campuses->pluck('name')->implode(', ') ?: 'Sin campus' }} - {{ $cycleItem->modalities->pluck('name')->implode(', ') }}
            </option>
        @endforeach
    </select>
    @error('source_cycle_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <small class="text-muted">Solo se mostraran ciclos que compartan al menos una modalidad seleccionada.</small>
</div>

<div class="col-md-12 mb-3" id="clone_groups_wrapper" style="{{ old('clone_configuration') ? '' : 'display:none;' }}">
    <div class="form-check">
        <input type="checkbox"
               id="clone_as_new_groups"
               name="clone_as_new_groups"
               value="1"
               class="form-check-input"
               {{ old('clone_as_new_groups', 1) ? 'checked' : '' }}>
        <label class="form-check-label" for="clone_as_new_groups">
            Crear grupos nuevos para este ciclo (copia fisica independiente)
        </label>
    </div>
    <small class="text-muted">
        Recomendado para poder editar/eliminar grupos en el ciclo nuevo sin tocar los del ciclo anterior.
    </small>
</div>
@endif

<div class="col-md-12 mb-3">
    <label class="form-label">Codigo</label>
    <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $schoolCycle->code ?? '') }}" required>
    @error('code')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="col-md-6 mb-3">
    <label class="form-label">Fecha de inicio</label>
    <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', isset($schoolCycle) ? $schoolCycle->start_date?->format('Y-m-d') : '') }}" required>
    @error('start_date')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="col-md-6 mb-3">
    <label class="form-label">Fecha de termino</label>
    <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date', isset($schoolCycle) ? $schoolCycle->end_date?->format('Y-m-d') : '') }}" required>
    @error('end_date')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <small class="text-muted d-block mt-1">
        Los parciales se generan automaticamente al guardar el ciclo:
        Bachillerato = 2 parciales, Preparatoria = 4 parciales.
    </small>
</div>

@if(isset($schoolCycle))
<div class="col-md-12 mb-3">
    <div class="form-check">
        <input type="checkbox" name="is_active" class="form-check-input" {{ old('is_active', $schoolCycle->is_active) ? 'checked' : '' }}>
        <label class="form-check-label">Activo</label>
    </div>
</div>
@endif
