@php
    $selectedModalityId = old('modality_id', $level->modality_id ?? '');
    $selectedName = old('name', $level->name ?? '');
@endphp

<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Modalidad</label>
        <select name="modality_id" id="modality_id" class="form-control" required>
            <option value="">Seleccione una modalidad</option>
            @foreach($modalities as $modality)
                <option
                    value="{{ $modality->id }}"
                    data-modality-name="{{ strtolower($modality->name) }}"
                    {{ (string) $selectedModalityId === (string) $modality->id ? 'selected' : '' }}
                >
                    {{ $modality->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Nombre del nivel</label>
        <input
            type="text"
            name="name"
            id="level_name"
            list="level-name-suggestions"
            class="form-control"
            value="{{ $selectedName }}"
            required
        >
        <datalist id="level-name-suggestions"></datalist>
        <small class="text-muted" id="level-suggestions-help">
            Selecciona una modalidad para ver sugerencias, o captura el nivel manualmente.
        </small>
        @error('name')
            <div class="form-text text-danger">Este campo es obligatorio</div>
        @enderror
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalitySelect = document.getElementById('modality_id');
        const suggestions = document.getElementById('level-name-suggestions');
        const helpText = document.getElementById('level-suggestions-help');

        const suggestedByModality = {
            primaria: ['Primero', 'Segundo', 'Tercero', 'Cuarto', 'Quinto', 'Sexto'],
            secundaria: ['Primero', 'Segundo', 'Tercero'],
            bachillerato: ['Primero', 'Segundo', 'Tercero', 'Cuarto', 'Quinto', 'Sexto'],
            preparatoria: ['Cuarto', 'Quinto', 'Sexto'],
            licenciatura: ['Primero', 'Segundo', 'Tercero', 'Cuarto', 'Quinto', 'Sexto', 'Séptimo', 'Octavo', 'Noveno', 'Décimo'],
        };

        const rebuildSuggestions = () => {
            suggestions.innerHTML = '';

            const selected = modalitySelect.options[modalitySelect.selectedIndex];
            const modalityName = selected ? (selected.dataset.modalityName || '').trim() : '';
            const items = suggestedByModality[modalityName] || [];

            items.forEach((item) => {
                const option = document.createElement('option');
                option.value = item;
                suggestions.appendChild(option);
            });

            helpText.textContent = items.length
                ? 'Sugerencias cargadas para esta modalidad. También puedes escribir otro nivel.'
                : 'Sin sugerencias predefinidas para esta modalidad. Puedes capturar el nivel manualmente.';
        };

        modalitySelect.addEventListener('change', rebuildSuggestions);
        rebuildSuggestions();
    });
</script>
