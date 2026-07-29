@extends('layouts.app')

@section('title', 'Preguntas del banco')

@section('content')
<div class="content px-3 mt-3">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">{{ $questionBank->name }}</h4>
                <small class="text-muted">{{ $questionBank->subject->name ?? 'N/D' }} | {{ $questionBank->partial->name ?? 'Sin parcial' }}</small>
            </div>
            <div>
                <a href="{{ route('teacher.question-banks.edit', $questionBank) }}" class="btn btn-outline-secondary btn-sm">Editar banco</a>
                <a href="{{ route('teacher.question-banks.exam.configure', $questionBank) }}" class="btn btn-outline-success btn-sm">Configurar examen</a>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Carga masiva</strong></div>
        <form method="POST" action="{{ route('teacher.question-banks.import', $questionBank) }}" enctype="multipart/form-data">
            @csrf
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Parcial de importacion:</strong>
                    {{ $questionBank->partial->name ?? 'Sin parcial asignado' }}.
                    Todas las preguntas del archivo se cargaran a este banco:
                    {{ $questionBank->subject->name ?? 'Materia' }} / {{ $questionBank->schoolCycle->name ?? 'Ciclo' }}.
                    @if(! $questionBank->cycle_partial_id)
                        <div class="mt-1">
                            <a href="{{ route('teacher.question-banks.edit', $questionBank) }}" class="alert-link">Asigna un parcial antes de importar.</a>
                        </div>
                    @endif
                </div>
                <div class="form-row align-items-end">
                    <div class="form-group col-md-7">
                        <label>Archivo Excel/CSV</label>
                        <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required {{ ! $questionBank->cycle_partial_id ? 'disabled' : '' }}>
                    </div>
                    <div class="form-group col-md-5">
                        <button class="btn btn-primary" {{ ! $questionBank->cycle_partial_id ? 'disabled' : '' }}>Importar preguntas</button>
                        <a href="{{ route('teacher.question-banks.template.download-for-bank', $questionBank) }}" class="btn btn-outline-secondary">Descargar plantilla</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Agregar pregunta</strong></div>
        <form method="POST" action="{{ route('teacher.question-banks.questions.store', $questionBank) }}" enctype="multipart/form-data">
            @csrf
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label>Tipo</label>
                        <select name="type" class="form-control" required>
                            <option value="open">Abierta</option>
                            <option value="multiple_choice">Opción múltiple</option>
                            <option value="matching">Relación de columnas</option>
                            <option value="fill_blank">Completado de oraciones</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label>Puntos</label>
                        <input type="number" step="0.01" min="0.1" max="100" name="points" class="form-control" value="1" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label>Enunciado</label>
                        <textarea id="prompt_editor" name="prompt" rows="3" class="form-control" required></textarea>
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
                <div class="border rounded p-3 mb-3 bg-light">
                    <div class="font-weight-bold mb-2">Material de apoyo</div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Titulo del apoyo</label>
                            <input type="text" name="support_title" class="form-control" value="{{ old('support_title') }}" placeholder="Lectura, imagen, fragmento...">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Imagen de apoyo</label>
                            <input type="file" name="support_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                        </div>
                        <div class="form-group col-md-4">
                            <label>URL de imagen</label>
                            <input type="url" name="support_image_url" class="form-control" value="{{ old('support_image_url') }}" placeholder="https://...">
                        </div>
                        <div class="form-group col-md-12">
                            <label>Texto largo de apoyo</label>
                            <textarea name="support_text" rows="8" class="form-control" placeholder="Pega aqui lecturas, fragmentos o instrucciones largas.">{{ old('support_text') }}</textarea>
                        </div>
                    </div>
                </div>
                <hr>
                <small class="text-muted">Para opción múltiple, relación y completado, usa estas filas (si no aplican, se ignoran).</small>
                <div class="row mt-2">
                    <div class="col-md-4">
                        <label>Opciones múltiple</label>
                        @for($i=0;$i<5;$i++)
                            <div class="input-group mb-1">
                                <input name="options[{{ $i }}][text]" class="form-control form-control-sm" placeholder="Opción {{ $i+1 }}">
                                <div class="input-group-append">
                                    <span class="input-group-text">
                                        <input type="checkbox" name="options[{{ $i }}][is_correct]" value="1">
                                    </span>
                                </div>
                            </div>
                        @endfor
                    </div>
                    <div class="col-md-4">
                        <label>Pares relación</label>
                        @for($i=0;$i<4;$i++)
                            <div class="input-group mb-1">
                                <input name="pairs[{{ $i }}][left]" class="form-control form-control-sm" placeholder="Columna A">
                                <input name="pairs[{{ $i }}][right]" class="form-control form-control-sm" placeholder="Columna B">
                            </div>
                        @endfor
                    </div>
                    <div class="col-md-4">
                        <label>Respuestas completado</label>
                        @for($i=0;$i<4;$i++)
                            <input name="blanks[{{ $i }}]" class="form-control form-control-sm mb-1" placeholder="Respuesta esperada {{ $i+1 }}">
                        @endfor
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary">Guardar pregunta</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><strong>Preguntas cargadas</strong></div>
        <div class="card-body">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tipo</th>
                        <th>Enunciado</th>
                        <th>Puntos</th>
                        <th class="text-right" style="width: 290px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($questionBank->questions as $question)
                        <tr>
                            <td>{{ $question->sort_order }}</td>
                            <td>{{ $question->type_label }}</td>
                            <td>
                                {{ $question->prompt }}
                                @php
                                    $meta = is_array($question->meta ?? null) ? $question->meta : [];
                                    $hasSupport = !empty($meta['support_text']) || !empty($meta['support_image_url']) || !empty($meta['support_title']);
                                @endphp
                                @if($hasSupport)
                                    <span class="badge badge-info ml-1">Apoyo</span>
                                @endif
                            </td>
                            <td>{{ number_format((float)$question->points, 2) }}</td>
                            <td class="text-right text-nowrap" style="width: 290px;">
                                <a href="{{ route('teacher.question-banks.questions.preview', [$questionBank, $question]) }}" class="btn btn-outline-info btn-sm mr-1">Vista alumno</a>
                                <a href="{{ route('teacher.question-banks.questions.edit', [$questionBank, $question]) }}" class="btn btn-outline-primary btn-sm mr-1">Editar</a>
                                <form method="POST" action="{{ route('teacher.question-banks.questions.destroy', [$questionBank, $question]) }}" class="d-inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">Aún no hay preguntas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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
