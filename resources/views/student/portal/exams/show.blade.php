@extends('layouts.app')

@section('title', 'Examen en línea')

@section('content')
<div class="content px-3 mt-3">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">{{ $paperExam->title }}</h4>
                <small class="text-muted">{{ $paperExam->assignment->subject->name ?? 'N/D' }} - Grupo {{ $paperExam->assignment->group->name ?? 'N/D' }}</small>
            </div>
            <a href="{{ route('student.exams.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
        </div>
        <div class="card-body">
            <p><strong>Instrucciones:</strong> {{ $paperExam->instructions ?: 'Sin instrucciones.' }}</p>
            <p class="mb-1"><strong>Intentos permitidos:</strong> {{ (int) ($paperExam->online_max_attempts ?: 1) }}</p>
            <p class="mb-0"><strong>Duración sugerida:</strong> {{ $paperExam->duration_minutes ?: 'N/D' }} min</p>
        </div>
    </div>

    @if($canStartAttempt)
        <form method="POST" action="{{ route('student.exams.start', $paperExam) }}" class="mb-3">
            @csrf
            <button class="btn btn-primary">Iniciar intento</button>
        </form>
    @elseif(!$activeAttempt && !empty($startBlockReason))
        <div class="alert alert-warning mb-3">
            {{ $startBlockReason }}
        </div>
    @endif

    @if($activeAttempt)
        <div class="card mb-3">
            <div class="card-header">
                <strong>Intento en curso #{{ $activeAttempt->attempt_number }}</strong>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    No cambies de pestaña ni minimices la ventana. Si sucede, el intento se bloqueará automáticamente.
                </div>
                @if($secondsRemaining !== null)
                    <div class="alert alert-info d-flex justify-content-between align-items-center">
                        <span><strong>Tiempo restante:</strong></span>
                        <span id="examTimer" class="h5 mb-0">--:--</span>
                    </div>
                @endif

                <form id="examForm" method="POST" action="{{ route('student.exams.submit', [$paperExam, $activeAttempt]) }}">
                    @csrf
                    @foreach($orderedQuestions as $index => $q)
                        <div class="mb-4 p-3 border rounded exam-question" data-question-index="{{ $index }}" style="{{ $index === 0 ? '' : 'display:none;' }}">
                            <div class="mb-2">
                                <strong>Pregunta {{ $index + 1 }} de {{ $orderedQuestions->count() }}:</strong> {{ $q->prompt }}
                                <span class="badge badge-info">{{ $q->type_label }}</span>
                            </div>

                            @if($q->type === 'multiple_choice')
                                @php
                                    $sequence = collect(data_get($activeAttempt->options_sequence, (string) $q->id, []))
                                        ->map(fn ($id) => (int) $id)
                                        ->values();
                                    $orderedOptions = $sequence->isNotEmpty()
                                        ? $sequence->map(fn ($optId) => $q->options->firstWhere('id', $optId))->filter()->values()
                                        : $q->options;
                                @endphp
                                @foreach($orderedOptions as $opt)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio"
                                               name="answers[{{ $q->id }}][option_id]"
                                               id="q{{ $q->id }}opt{{ $opt->id }}"
                                               value="{{ $opt->id }}">
                                        <label class="form-check-label" for="q{{ $q->id }}opt{{ $opt->id }}">
                                            {{ $opt->option_text }}
                                        </label>
                                    </div>
                                @endforeach
                            @endif

                            @if($q->type === 'open')
                                <textarea class="form-control" rows="4" name="answers[{{ $q->id }}][text]"></textarea>
                            @endif

                            @if($q->type === 'fill_blank')
                                @foreach($q->fillBlanks as $blankIndex => $blank)
                                    <div class="form-group mb-2">
                                        <label>Respuesta {{ $blankIndex + 1 }}</label>
                                        <input type="text" class="form-control" name="answers[{{ $q->id }}][blanks][{{ $blankIndex }}]">
                                    </div>
                                @endforeach
                            @endif

                            @if($q->type === 'matching')
                                @php $rightOptions = $q->matchingPairs->pluck('right_text')->shuffle()->values(); @endphp
                                @foreach($q->matchingPairs as $pair)
                                    <div class="form-row align-items-center mb-2">
                                        <div class="col-md-6">{{ $pair->left_text }}</div>
                                        <div class="col-md-6">
                                            <select class="form-control" name="answers[{{ $q->id }}][pairs][{{ $pair->id }}]">
                                                <option value="">Selecciona una opción</option>
                                                @foreach($rightOptions as $rightText)
                                                    <option value="{{ $rightText }}">{{ $rightText }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                @endforeach
                            @endif

                            <div class="mt-3 d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary exam-prev" {{ $index === 0 ? 'disabled' : '' }}>Anterior</button>
                                @if($index < $orderedQuestions->count() - 1)
                                    <button type="button" class="btn btn-primary exam-next">Siguiente</button>
                                @else
                                    <button class="btn btn-success">Enviar examen</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </form>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header"><strong>Intentos realizados</strong></div>
        <div class="card-body table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Intento</th>
                        <th>Fecha envío</th>
                        <th>Estado</th>
                        <th>Puntaje</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($submittedAttempts as $attempt)
                        <tr>
                            <td>#{{ $attempt->attempt_number }}</td>
                            <td>{{ optional($attempt->submitted_at)->format('d/m/Y H:i') ?: '-' }}</td>
                            <td>
                                @if($attempt->status === 'graded')
                                    <span class="badge badge-success">Calificado</span>
                                @elseif($attempt->status === 'submitted')
                                    <span class="badge badge-warning">En revisión</span>
                                @elseif($attempt->status === 'locked')
                                    <span class="badge badge-danger">Bloqueado</span>
                                @else
                                    <span class="badge badge-secondary">{{ $attempt->status }}</span>
                                @endif
                            </td>
                            <td>
                                @if($paperExam->online_show_result && $attempt->score !== null)
                                    @php
                                        $baseTen = (float) $attempt->max_score > 0
                                            ? (((float) $attempt->score / (float) $attempt->max_score) * 10)
                                            : null;
                                    @endphp
                                    {{ $baseTen !== null ? number_format($baseTen, 1) : '-' }}
                                @elseif($attempt->status === 'graded')
                                    Calificado
                                @elseif($attempt->status === 'locked')
                                    Bloqueado por cambio de pestaña
                                @else
                                    En revisión
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">Sin intentos enviados.</td></tr>
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
@if($activeAttempt)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('examForm');
    if (!form) return;

    const questions = Array.from(document.querySelectorAll('.exam-question'));
    let current = 0;

    function show(index) {
        questions.forEach((el, i) => {
            el.style.display = i === index ? '' : 'none';
        });
        current = index;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    document.querySelectorAll('.exam-next').forEach(btn => {
        btn.addEventListener('click', () => {
            if (current < questions.length - 1) show(current + 1);
        });
    });

    document.querySelectorAll('.exam-prev').forEach(btn => {
        btn.addEventListener('click', () => {
            if (current > 0) show(current - 1);
        });
    });

    let locked = false;
    let submittedByTimer = false;
    const lockUrl = @json(route('student.exams.lock', [$paperExam, $activeAttempt]));
    const token = @json(csrf_token());
    const secondsRemainingRaw = @json($secondsRemaining);
    const timerEl = document.getElementById('examTimer');

    function lockAttempt() {
        if (locked || submittedByTimer) return;
        locked = true;
        fetch(lockUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ reason: 'tab_switch' })
        }).finally(() => {
            window.location.reload();
        });
    }

    function formatTime(totalSeconds) {
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
    }

    function startTimer() {
        if (secondsRemainingRaw === null || timerEl === null) return;
        let remaining = Number(secondsRemainingRaw);
        timerEl.textContent = formatTime(Math.max(0, remaining));

        const interval = setInterval(() => {
            remaining -= 1;
            timerEl.textContent = formatTime(Math.max(0, remaining));
            if (remaining <= 0) {
                clearInterval(interval);
                submittedByTimer = true;
                form.submit();
            }
        }, 1000);
    }

    startTimer();

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            lockAttempt();
        }
    });

    window.addEventListener('blur', function () {
        lockAttempt();
    });
});
</script>
@endif
@endsection
