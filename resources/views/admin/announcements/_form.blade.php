@php
    $selectedUsers = old('user_ids', $selectedUserIds ?? []);
@endphp

<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Título</label>
        <input
            type="text"
            name="title"
            class="form-control @error('title') is-invalid @enderror"
            value="{{ old('title', $announcement->title ?? '') }}"
            required
        >
        @error('title')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Mensaje</label>
        <textarea
            name="body"
            class="form-control @error('body') is-invalid @enderror"
            rows="5"
            required
        >{{ old('body', $announcement->body ?? '') }}</textarea>
        @error('body')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="col-md-6 mb-3">
    <div class="form-group">
        <label>Tipo de publicación</label>
        <select name="scope" id="scope" class="form-control @error('scope') is-invalid @enderror" required>
            <option value="">Seleccione una opción</option>
            <option value="public" @selected(old('scope', $announcement->scope ?? '') === 'public')>
                Público (landing)
            </option>
            <option value="internal" @selected(old('scope', $announcement->scope ?? '') === 'internal')>
                Interno (dashboard)
            </option>
        </select>
        @error('scope')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="col-md-6 mb-3">
    <div class="form-group">
        <label>Dirigido a</label>
        <select name="audience" id="audience" class="form-control @error('audience') is-invalid @enderror" required>
            <option value="">Seleccione</option>
            @foreach([
                'all' => 'Todos',
                'teachers' => 'Profesores',
                'students' => 'Estudiantes',
                'specific' => 'Usuarios específicos'
            ] as $key => $label)
                <option value="{{ $key }}" @selected(old('audience', $announcement->audience ?? '') === $key)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('audience')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
<input type="hidden" id="audience_public_fallback" name="audience_public_fallback" value="all" disabled>

<div class="col-md-12 mb-3" id="specific-users-wrapper" style="display: none;">
    <div class="form-group">
        <label>Selecciona usuarios</label>
        <select
            name="user_ids[]"
            id="user_ids"
            class="form-control @error('user_ids') is-invalid @enderror @error('user_ids.*') is-invalid @enderror"
            multiple
            size="8"
        >
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected(in_array($user->id, $selectedUsers))>
                    {{ $user->name }} ({{ $user->email }})
                </option>
            @endforeach
        </select>
        <small class="text-muted">
            Mantén Ctrl (o Cmd) para seleccionar varios usuarios.
        </small>
        @error('user_ids')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @error('user_ids.*')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Imágenes (opcional)</label>
        <input
            type="file"
            name="images[]"
            class="form-control @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror"
            multiple
            accept="image/*"
        >
        <small class="text-muted">
            Puedes subir una o varias imágenes (jpg, png, webp).
        </small>
        @error('images')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @error('images.*')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>

@if(isset($announcement))
    <div class="col-md-12 mb-3">
        <div class="form-check">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $announcement->is_active))>
            <label class="form-check-label">Activo</label>
        </div>
    </div>
@endif

@if(isset($announcement) && $announcement->images->count())
    <div class="col-md-12 mb-3">
        <label>Imágenes actuales</label>
        <div class="d-flex flex-wrap gap-2">
            @foreach($announcement->images as $image)
                <div class="border p-1">
                    <img src="{{ asset('storage/' . $image->path) }}" alt="" style="height: 80px;">
                </div>
            @endforeach
        </div>
    </div>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const audience = document.getElementById('audience');
        const scope = document.getElementById('scope');
        const wrapper = document.getElementById('specific-users-wrapper');
        const audienceFallback = document.getElementById('audience_public_fallback');

        const toggleSpecificUsers = () => {
            const show = audience.value === 'specific' && scope.value === 'internal';
            wrapper.style.display = show ? 'block' : 'none';
        };

        const toggleAudienceByScope = () => {
            const isPublic = scope.value === 'public';
            if (isPublic) {
                audience.value = 'all';
                audience.setAttribute('disabled', 'disabled');
                audienceFallback.removeAttribute('disabled');
            } else {
                audience.removeAttribute('disabled');
                audienceFallback.setAttribute('disabled', 'disabled');
            }
        };

        audience.addEventListener('change', toggleSpecificUsers);
        scope.addEventListener('change', function () {
            toggleAudienceByScope();
            toggleSpecificUsers();
        });

        toggleAudienceByScope();
        toggleSpecificUsers();
    });
</script>
