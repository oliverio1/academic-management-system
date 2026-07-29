@extends('layouts.app')

@section('title', 'Vista previa de pregunta')

@section('content')
<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Vista previa (alumno)</h4>
                <small class="text-muted">{{ $questionBank->name }} | Tipo: {{ $question->type_label }}</small>
            </div>
            <a href="{{ route('teacher.question-banks.show', $questionBank) }}" class="btn btn-outline-secondary btn-sm">Volver</a>
        </div>
        <div class="card-body">
            <div class="mb-3 p-3 border rounded">
                @include('partials.question_support_material', ['question' => $question])
                <div class="mb-2">
                    <strong>Pregunta:</strong> {{ $question->prompt }}
                </div>
                <div class="mb-2">
                    <span class="badge badge-info">{{ $question->type_label }}</span>
                    <span class="badge badge-secondary">{{ number_format((float)$question->points, 2) }} pts</span>
                </div>

                @if($question->type === 'multiple_choice')
                    @foreach($question->options as $opt)
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" disabled>
                            <label class="form-check-label">{{ $opt->option_text }}</label>
                        </div>
                    @endforeach
                @endif

                @if($question->type === 'open')
                    <textarea class="form-control" rows="4" disabled placeholder="El alumno escribirá su respuesta aquí"></textarea>
                @endif

                @if($question->type === 'fill_blank')
                    @foreach($question->fillBlanks as $index => $blank)
                        <div class="form-group mb-2">
                            <label>Respuesta {{ $index + 1 }}</label>
                            <input type="text" class="form-control" disabled>
                        </div>
                    @endforeach
                @endif

                @if($question->type === 'matching')
                    @php $rightOptions = $question->matchingPairs->pluck('right_text')->values(); @endphp
                    @foreach($question->matchingPairs as $pair)
                        <div class="form-row align-items-center mb-2">
                            <div class="col-md-6">{{ $pair->left_text }}</div>
                            <div class="col-md-6">
                                <select class="form-control" disabled>
                                    <option>Selecciona una opción</option>
                                    @foreach($rightOptions as $rightText)
                                        <option>{{ $rightText }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
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
@endsection
