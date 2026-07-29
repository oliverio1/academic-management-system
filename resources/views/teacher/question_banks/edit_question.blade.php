@extends('layouts.app')

@section('title', 'Editar pregunta')

@section('content')
<div class="content px-3 mt-3">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Editar pregunta</h4>
            <a href="{{ route('teacher.question-banks.show', $questionBank) }}" class="btn btn-outline-secondary btn-sm">Volver</a>
        </div>
        <form method="POST" action="{{ route('teacher.question-banks.questions.update', [$questionBank, $question]) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label>Tipo</label>
                        <select name="type" class="form-control" required>
                            <option value="open" {{ $question->type === 'open' ? 'selected' : '' }}>Abierta</option>
                            <option value="multiple_choice" {{ $question->type === 'multiple_choice' ? 'selected' : '' }}>Opción múltiple</option>
                            <option value="matching" {{ $question->type === 'matching' ? 'selected' : '' }}>Relación de columnas</option>
                            <option value="fill_blank" {{ $question->type === 'fill_blank' ? 'selected' : '' }}>Completado de oraciones</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label>Puntos</label>
                        <input type="number" step="0.01" min="0.1" max="100" name="points" class="form-control" value="{{ old('points', (float)$question->points) }}" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label>Orden</label>
                        <input type="number" min="1" name="sort_order" class="form-control" value="{{ old('sort_order', $question->sort_order) }}">
                    </div>
                    <div class="form-group col-md-12">
                        <label>Enunciado</label>
                        <textarea id="prompt_editor" name="prompt" rows="3" class="form-control" required>{{ old('prompt', $question->prompt) }}</textarea>
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm latex-btn" data-target="#prompt_editor" data-insert="\\(  \\)">Inline</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm latex-btn" data-target="#prompt_editor" data-insert="\\[  \\]">Bloque</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm latex-btn" data-target="#prompt_editor" data-insert="\\frac{}{ }">\\frac</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm latex-btn" data-target="#prompt_editor" data-insert="\\int  \\,dx">\\int</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm latex-btn" data-target="#prompt_editor" data-insert="\\sqrt{}">\\sqrt</button>
                            <button type="button" class="btn btn-outline-info btn-sm" id="latex_examples_toggle">Ejemplos</button>
                        </div>
                        <div id="latex_examples_panel" class="border rounded p-2 mt-2 bg-white" style="display:none;">
                            <div class="small text-muted mb-2">Clic para insertar en el enunciado:</div>
                            <button type="button" class="btn btn-light btn-sm latex-example" data-target="#prompt_editor" data-insert="¿Cuál es la primitiva de \\(f(x)=3x^2\\)?">Primitiva</button>
                            <button type="button" class="btn btn-light btn-sm latex-example" data-target="#prompt_editor" data-insert="Resuelve \\(\\int (x^2+6x-3)\\,dx\\)">Integral</button>
                            <button type="button" class="btn btn-light btn-sm latex-example" data-target="#prompt_editor" data-insert="Calcula la derivada de \\(f(x)=\\frac{x^3+1}{x}\\)">Derivada</button>
                            <button type="button" class="btn btn-light btn-sm latex-example" data-target="#prompt_editor" data-insert="Simplifica \\(\\sqrt{a^2+b^2}\\)">Raíz</button>
                            <button type="button" class="btn btn-light btn-sm latex-example" data-target="#prompt_editor" data-insert="En física: \\(v_f=v_i+at\\)">Física</button>
                            <button type="button" class="btn btn-light btn-sm latex-example" data-target="#prompt_editor" data-insert="En química: \\(n=\\frac{m}{M}\\)">Química</button>
                        </div>
                        <small class="text-muted">Usa <code>\( ... \)</code> para línea y <code>\[ ... \]</code> para bloque.</small>
                        <div class="border rounded p-2 mt-2 bg-light">
                            <div class="small text-muted mb-1">Vista previa</div>
                            <div id="prompt_preview"></div>
                        </div>
                    </div>
                </div>

                @php
                    $meta = is_array($question->meta ?? null) ? $question->meta : [];
                @endphp
                <div class="border rounded p-3 mb-3 bg-light">
                    <div class="font-weight-bold mb-2">Material de apoyo</div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Titulo del apoyo</label>
                            <input type="text" name="support_title" class="form-control" value="{{ old('support_title', $meta['support_title'] ?? '') }}" placeholder="Lectura, imagen, fragmento...">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Imagen de apoyo</label>
                            <input type="file" name="support_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                        </div>
                        <div class="form-group col-md-4">
                            <label>URL de imagen</label>
                            <input type="url" name="support_image_url" class="form-control" value="{{ old('support_image_url', $meta['support_image_url'] ?? '') }}" placeholder="https://...">
                        </div>
                        @if(!empty($meta['support_image_url']))
                            <div class="form-group col-md-12">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" name="remove_support_image" value="1" class="custom-control-input" id="remove_support_image">
                                    <label class="custom-control-label" for="remove_support_image">Quitar imagen actual</label>
                                </div>
                            </div>
                        @endif
                        <div class="form-group col-md-12">
                            <label>Texto largo de apoyo</label>
                            <textarea name="support_text" rows="8" class="form-control" placeholder="Pega aqui lecturas, fragmentos o instrucciones largas.">{{ old('support_text', $meta['support_text'] ?? '') }}</textarea>
                        </div>
                    </div>
                    @include('partials.question_support_material', ['question' => $question])
                </div>

                <hr>
                <small class="text-muted">Solo se toman en cuenta los campos del tipo seleccionado.</small>
                <div class="row mt-2">
                    <div class="col-md-4">
                        <label>Opciones múltiple</label>
                        @php $options = old('options', $question->options->map(fn($o) => ['text' => $o->option_text, 'is_correct' => (bool)$o->is_correct])->values()->all()); @endphp
                        @for($i=0;$i<6;$i++)
                            @php $row = $options[$i] ?? ['text' => '', 'is_correct' => false]; @endphp
                            <div class="input-group mb-1">
                                <input name="options[{{ $i }}][text]" class="form-control form-control-sm" value="{{ $row['text'] ?? '' }}" placeholder="Opción {{ $i+1 }}">
                                <div class="input-group-append">
                                    <span class="input-group-text">
                                        <input type="checkbox" name="options[{{ $i }}][is_correct]" value="1" {{ !empty($row['is_correct']) ? 'checked' : '' }}>
                                    </span>
                                </div>
                            </div>
                        @endfor
                    </div>

                    <div class="col-md-4">
                        <label>Pares relación</label>
                        @php $pairs = old('pairs', $question->matchingPairs->map(fn($p) => ['left' => $p->left_text, 'right' => $p->right_text])->values()->all()); @endphp
                        @for($i=0;$i<6;$i++)
                            @php $row = $pairs[$i] ?? ['left' => '', 'right' => '']; @endphp
                            <div class="input-group mb-1">
                                <input name="pairs[{{ $i }}][left]" class="form-control form-control-sm" value="{{ $row['left'] ?? '' }}" placeholder="Columna A">
                                <input name="pairs[{{ $i }}][right]" class="form-control form-control-sm" value="{{ $row['right'] ?? '' }}" placeholder="Columna B">
                            </div>
                        @endfor
                    </div>

                    <div class="col-md-4">
                        <label>Respuestas completado</label>
                        @php $blanks = old('blanks', $question->fillBlanks->pluck('expected_answer')->values()->all()); @endphp
                        @for($i=0;$i<6;$i++)
                            <input name="blanks[{{ $i }}]" class="form-control form-control-sm mb-1" value="{{ $blanks[$i] ?? '' }}" placeholder="Respuesta esperada {{ $i+1 }}">
                        @endfor
                    </div>
                </div>
            </div>

            <div class="card-footer">
                <button class="btn btn-primary">Guardar cambios</button>
                <a href="{{ route('teacher.question-banks.show', $questionBank) }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('page_scripts')
<script>
window.MathJax = {
    tex: {
        inlineMath: [['\\(', '\\)'], ['$', '$']],
        displayMath: [['\\[', '\\]'], ['$$', '$$']]
    },
    options: {
        skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code']
    }
};
</script>
<script defer src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const prompt = document.getElementById('prompt_editor');
    const preview = document.getElementById('prompt_preview');
    const optionInputs = Array.from(document.querySelectorAll('input[name^="options["][name$="[text]"]'));

    function renderPreview() {
        if (!prompt || !preview) return;
        const optionHtml = optionInputs
            .map((input, i) => input.value ? `<div>${i + 1}) ${input.value}</div>` : '')
            .join('');
        preview.innerHTML = `<div>${prompt.value || ''}</div>${optionHtml}`;
        if (window.MathJax && window.MathJax.typesetPromise) {
            window.MathJax.typesetPromise([preview]).catch(() => {});
        }
    }

    function insertAtCursor(textarea, text) {
        const start = textarea.selectionStart ?? textarea.value.length;
        const end = textarea.selectionEnd ?? textarea.value.length;
        textarea.value = textarea.value.slice(0, start) + text + textarea.value.slice(end);
        const pos = start + text.length;
        textarea.focus();
        textarea.setSelectionRange(pos, pos);
        renderPreview();
    }

    document.querySelectorAll('.latex-btn').forEach((btn) => {
        btn.addEventListener('click', function () {
            const target = document.querySelector(btn.dataset.target);
            if (!target) return;
            insertAtCursor(target, btn.dataset.insert || '');
        });
    });
    document.querySelectorAll('.latex-example').forEach((btn) => {
        btn.addEventListener('click', function () {
            const target = document.querySelector(btn.dataset.target);
            if (!target) return;
            target.value = btn.dataset.insert || '';
            target.focus();
            renderPreview();
        });
    });
    const toggle = document.getElementById('latex_examples_toggle');
    const panel = document.getElementById('latex_examples_panel');
    if (toggle && panel) {
        toggle.addEventListener('click', function () {
            panel.style.display = panel.style.display === 'none' ? '' : 'none';
        });
    }

    if (prompt) prompt.addEventListener('input', renderPreview);
    optionInputs.forEach((input) => input.addEventListener('input', renderPreview));
    renderPreview();
});
</script>
@endsection
