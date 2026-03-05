<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Título</label>
        <input type="text" name="title" class="form-control" value="{{ old('title', $announcement->title ?? '') }}" required>
    </div>
</div>

<div class="col-md-12 mb-3">
    <div class="form-group">
        <label>Mensaje</label>
        <textarea name="body" class="form-control" rows="5" required>{{ old('body', $announcement->body ?? '') }}</textarea>
    </div>
</div>

<div class="col-md-6 mb-3">
    <div class="form-group">
        <label>Tipo de publicación</label>
        <select name="scope" class="form-control" required>
            <option value="">Seleccione una opción</option>
            <option value="public"
                @selected(old('scope', $announcement->scope ?? '') === 'public')>
                Público (landing)
            </option>
            <option value="internal"
                @selected(old('scope', $announcement->scope ?? '') === 'internal')>
                Interno (dashboard)
            </option>
        </select>
    </div>
</div>
    
<div class="col-md-6 mb-3">
    <div class="form-group">
        <label>Dirigido a</label>
        <select name="audience"
                class="form-control"
                required>
            <option value="">Seleccione</option>
            @foreach([
                'all' => 'Todos',
                'teachers' => 'Profesores',
                'students' => 'Estudiantes',
                'specific' => 'Usuario específico'
            ] as $k => $v)
                <option value="{{ $k }}"
                    @selected(old('audience', $announcement->audience ?? '') === $k)>
                    {{ $v }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group">
    <label>Imágenes (opcional)</label>
    <input type="file"
           name="images[]"
           class="form-control"
           multiple
           accept="image/*">
    <small class="text-muted">
        Puedes subir una o varias imágenes (jpg, png, webp).
    </small>
</div>
    
@if(isset($announcement))
    <div class="col-md-12 mb-3">
        <div class="form-check">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked($announcement->is_active)>
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
                    <img src="{{ asset('storage/'.$image->path) }}"
                         alt=""
                         style="height: 80px;">
                </div>
            @endforeach
        </div>
    </div>
@endif
