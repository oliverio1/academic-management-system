@extends('layouts.app')

@section('title', 'Examen en linea')

@section('content')
@php
    $isTeacherPreview = $isTeacherPreview ?? false;
    $previewResult = $previewResult ?? null;
    $sectionLabels = [
        'multiple_choice' => 'Opcion multiple',
        'matching' => 'Relacion',
        'fill_blank' => 'Completar',
        'open' => 'Abiertas',
    ];
@endphp
<div class="content px-3 mt-3">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">{{ $paperExam->title }}</h4>
                <small class="text-muted">{{ $paperExam->assignment->subject->name ?? 'N/D' }} - Grupo {{ $paperExam->assignment->group->name ?? 'N/D' }}</small>
            </div>
            <a href="{{ $isTeacherPreview ? route('teacher.paper-exams.show', $paperExam) : route('student.exams.index') }}" class="btn btn-outline-secondary btn-sm">
                Volver
            </a>
        </div>
        <div class="card-body">
            @if($isTeacherPreview)
                <div class="alert alert-info">
                    Vista de prueba para profesor. Las respuestas no se guardan como intento de alumno.
                </div>
            @endif
            <p><strong>Instrucciones:</strong> {{ $paperExam->instructions ?: 'Sin instrucciones.' }}</p>
            <p class="mb-1"><strong>Intentos permitidos:</strong> {{ (int) ($paperExam->online_max_attempts ?: 1) }}</p>
            <p class="mb-0"><strong>Duracion sugerida:</strong> {{ $paperExam->duration_minutes ?: 'N/D' }} min</p>
        </div>
    </div>

    @if($isTeacherPreview && $previewResult)
        <div class="card mb-3">
            <div class="card-header"><strong>Resultado de prueba</strong></div>
            <div class="card-body">
                <p class="mb-1">
                    <strong>Puntaje automatico:</strong>
                    {{ number_format((float) $previewResult['score'], 2) }} / {{ number_format((float) $previewResult['max_score'], 2) }}
                </p>
                <p class="mb-1">
                    <strong>Calificacion estimada:</strong>
                    {{ $previewResult['base_ten'] !== null ? number_format((float) $previewResult['base_ten'], 1) : '-' }}
                </p>
                @if(($previewResult['open_questions'] ?? 0) > 0)
                    <p class="mb-0 text-muted">
                        Hay {{ $previewResult['open_questions'] }} pregunta(s) abierta(s); esas requieren revision manual.
                    </p>
                @endif
            </div>
        </div>
    @endif

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
                @unless($isTeacherPreview)
                    <div class="alert alert-warning">
                        No cambies de pestana ni minimices la ventana. Si sucede, el intento se bloqueara automaticamente.
                    </div>
                @else
                    <div class="alert alert-warning">
                        En esta vista de prueba se simula el bloqueo al cambiar de pestana o aplicacion.
                    </div>
                @endunless
                <div id="examLockNotice" class="alert alert-danger d-none">
                    El examen fue bloqueado por salir de la ventana del examen.
                </div>
                @unless($isTeacherPreview)
                    <div id="fullscreenNotice" class="alert alert-info">
                        Para continuar, activa pantalla completa. Si sales de pantalla completa durante el examen, el intento se bloqueara.
                        <button type="button" id="fullscreenStartButton" class="btn btn-sm btn-primary ml-2">
                            Activar pantalla completa
                        </button>
                    </div>
                    <div id="autosaveStatus" class="small text-muted mb-2">Guardado automatico pendiente.</div>
                    <div id="localDraftStatus" class="small text-muted mb-2">Borrador local listo.</div>
                @endunless
                @if($secondsRemaining !== null)
                    <div class="alert alert-info d-flex justify-content-between align-items-center">
                        <span><strong>Tiempo restante:</strong></span>
                        <span id="examTimer" class="h5 mb-0">--:--</span>
                    </div>
                @endif

                <form id="examForm" method="POST" action="{{ $isTeacherPreview ? route('teacher.paper-exams.preview.submit', $paperExam) : route('student.exams.submit', [$paperExam, $activeAttempt]) }}" class="{{ $isTeacherPreview ? '' : 'd-none' }}">
                    @csrf
                    @foreach($orderedQuestions as $index => $q)
                        <div class="mb-4 p-3 border rounded exam-question" data-question-index="{{ $index }}" style="{{ $index === 0 ? '' : 'display:none;' }}">
                            <div class="text-uppercase text-muted small font-weight-bold mb-2">
                                {{ $sectionLabels[$q->type] ?? $q->type_label }}
                            </div>
                            @include('partials.question_support_material', ['question' => $q])
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
                                @php
                                    $rightSequence = collect(data_get($activeAttempt->options_sequence, (string) $q->id, []))
                                        ->map(fn ($rightText) => (string) $rightText)
                                        ->filter()
                                        ->values();
                                    $rightOptions = $rightSequence->isNotEmpty()
                                        ? $rightSequence
                                        : $q->matchingPairs->pluck('right_text')->shuffle()->values();
                                @endphp
                                @foreach($q->matchingPairs as $pair)
                                    <div class="form-row align-items-center mb-2">
                                        <div class="col-md-6">{{ $pair->left_text }}</div>
                                        <div class="col-md-6">
                                            <select class="form-control" name="answers[{{ $q->id }}][pairs][{{ $pair->id }}]">
                                                <option value="">Selecciona una opcion</option>
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

    @unless($isTeacherPreview)
        <div class="card">
            <div class="card-header"><strong>Intentos realizados</strong></div>
            <div class="card-body table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Intento</th>
                            <th>Fecha envio</th>
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
                                        <span class="badge badge-warning">En revision</span>
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
                                        Bloqueado por cambio de pestana
                                    @else
                                        En revision
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
    @endunless
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

    const isTeacherPreview = @json($isTeacherPreview);
    let locked = false;
    let submittedByTimer = false;
    let ignoreBlurUntil = 0;
    const lockUrl = isTeacherPreview ? null : @json($isTeacherPreview ? null : route('student.exams.lock', [$paperExam, $activeAttempt]));
    const autosaveUrl = isTeacherPreview ? null : @json($isTeacherPreview ? null : route('student.exams.autosave', [$paperExam, $activeAttempt]));
    const eventUrl = isTeacherPreview ? null : @json($isTeacherPreview ? null : route('student.exams.event', [$paperExam, $activeAttempt]));
    const token = @json(csrf_token());
    const secondsRemainingRaw = @json($secondsRemaining);
    const savedAnswers = @json($savedAnswers ?? []);
    const localDraftKey = @json($activeAttempt ? 'exam_attempt_' . $activeAttempt->id . '_draft' : null);
    const clearDraftAttemptId = @json(session('clear_exam_draft_attempt_id'));
    const attemptId = Number(@json($activeAttempt?->id ?? 0));
    const autosaveEveryMs = 120000;
    const remoteBatchSize = 15;
    const remoteBatchSpacingMs = 8000;
    const timerEl = document.getElementById('examTimer');
    const autosaveStatus = document.getElementById('autosaveStatus');
    const localDraftStatus = document.getElementById('localDraftStatus');
    const fullscreenNotice = document.getElementById('fullscreenNotice');
    const fullscreenButton = document.getElementById('fullscreenStartButton');
    let eventQueue = [];
    let remoteDirty = false;
    let lastRemoteSignature = JSON.stringify(savedAnswers || {});

    function formPayload(extra = {}) {
        const payload = new FormData(form);
        payload.set('_token', token);
        Object.keys(extra).forEach(key => payload.set(key, extra[key]));
        return payload;
    }

    function urlEncodedPayload(extra = {}) {
        const payload = new URLSearchParams();
        const data = formPayload(extra);
        data.forEach((value, key) => payload.append(key, value));
        return payload;
    }

    function setAutosaveStatus(message) {
        if (autosaveStatus) {
            autosaveStatus.textContent = message;
        }
    }

    function setLocalDraftStatus(message) {
        if (localDraftStatus) {
            localDraftStatus.textContent = message;
        }
    }

    function stableBatchIndex() {
        if (!attemptId) return 0;

        return Math.floor(((attemptId - 1) % 75) / remoteBatchSize);
    }

    function jitterMs(max = 2500) {
        return Math.floor(Math.random() * max);
    }

    function remoteDelayMs(extraJitter = 2500) {
        return (stableBatchIndex() * remoteBatchSpacingMs) + jitterMs(extraJitter);
    }

    function describeDelay(milliseconds) {
        const seconds = Math.ceil(milliseconds / 1000);
        return seconds <= 1 ? '1 segundo' : seconds + ' segundos';
    }

    function parseAnswerInput(name) {
        const match = name.match(/^answers\[(.+?)\]\[(.+?)\](?:\[(.+?)\])?$/);
        if (!match) return null;

        return {
            questionId: match[1],
            field: match[2],
            child: match[3] || null
        };
    }

    function collectAnswers() {
        const answers = {};

        form.querySelectorAll('input[name^="answers["], textarea[name^="answers["], select[name^="answers["]').forEach(input => {
            const parsed = parseAnswerInput(input.name);
            if (!parsed) return;
            if ((input.type === 'radio' || input.type === 'checkbox') && !input.checked) return;

            answers[parsed.questionId] = answers[parsed.questionId] || {};

            if (parsed.child !== null) {
                answers[parsed.questionId][parsed.field] = answers[parsed.questionId][parsed.field] || {};
                answers[parsed.questionId][parsed.field][parsed.child] = input.value;
            } else {
                answers[parsed.questionId][parsed.field] = input.value;
            }
        });

        return answers;
    }

    function currentAnswersSignature() {
        return JSON.stringify(collectAnswers());
    }

    function markRemoteDirty() {
        remoteDirty = currentAnswersSignature() !== lastRemoteSignature;
    }

    function applyAnswers(answers) {
        if (!answers || typeof answers !== 'object') return;

        Object.keys(answers).forEach(questionId => {
            const payload = answers[questionId] || {};

            Object.keys(payload).forEach(field => {
                const value = payload[field];

                if (value && typeof value === 'object' && !Array.isArray(value)) {
                    Object.keys(value).forEach(child => {
                        const selector = `[name="answers[${CSS.escape(questionId)}][${CSS.escape(field)}][${CSS.escape(child)}]"]`;
                        const input = form.querySelector(selector);
                        if (input) input.value = value[child] ?? '';
                    });
                    return;
                }

                const selector = `[name="answers[${CSS.escape(questionId)}][${CSS.escape(field)}]"]`;
                const inputs = Array.from(form.querySelectorAll(selector));
                inputs.forEach(input => {
                    if (input.type === 'radio' || input.type === 'checkbox') {
                        input.checked = String(input.value) === String(value ?? '');
                    } else {
                        input.value = value ?? '';
                    }
                });
            });
        });
    }

    function saveLocalDraft() {
        if (!localDraftKey || isTeacherPreview) return;

        try {
            localStorage.setItem(localDraftKey, JSON.stringify({
                answers: collectAnswers(),
                saved_at: new Date().toISOString()
            }));
            setLocalDraftStatus('Borrador local guardado.');
        } catch (error) {
            setLocalDraftStatus('No se pudo guardar el borrador local.');
        }
    }

    function restoreDraft() {
        if (isTeacherPreview) return;

        const savedSignature = JSON.stringify(savedAnswers || {});
        applyAnswers(savedAnswers);

        if (!localDraftKey) return;

        try {
            const raw = localStorage.getItem(localDraftKey);
            if (!raw) return;
            const draft = JSON.parse(raw);
            if (draft && draft.answers) {
                applyAnswers(draft.answers);
                setLocalDraftStatus('Borrador local recuperado.');
            }
        } catch (error) {
            setLocalDraftStatus('No se pudo recuperar el borrador local.');
        }

        lastRemoteSignature = savedSignature;
        remoteDirty = currentAnswersSignature() !== savedSignature;
    }

    function clearConfirmedDraft() {
        if (!clearDraftAttemptId || isTeacherPreview) return;

        try {
            localStorage.removeItem('exam_attempt_' + clearDraftAttemptId + '_draft');
        } catch (error) {}
    }

    function logEvent(eventType, metadata = {}, immediate = false) {
        if (!eventUrl || locked || submittedByTimer) return Promise.resolve();

        eventQueue.push({
            event_type: eventType,
            metadata: metadata || {}
        });

        if (!immediate && eventQueue.length < 10) {
            return Promise.resolve();
        }

        return flushEventQueue();
    }

    function flushEventQueue() {
        if (!eventUrl || eventQueue.length === 0) return Promise.resolve();

        const events = eventQueue.splice(0, 50);
        const payload = new URLSearchParams();
        payload.append('_token', token);
        events.forEach((item, index) => {
            payload.append(`events[${index}][event_type]`, item.event_type);
            Object.keys(item.metadata || {}).forEach(key => {
                payload.append(`events[${index}][metadata][${key}]`, item.metadata[key]);
            });
        });

        return fetch(eventUrl, {
            method: 'POST',
            keepalive: true,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: payload.toString()
        }).catch(() => {
            eventQueue = events.concat(eventQueue).slice(0, 100);
        });
    }

    function autosave(reason = 'navigation') {
        if (!autosaveUrl || locked || submittedByTimer) return Promise.resolve();
        saveLocalDraft();

        const signature = currentAnswersSignature();
        if (!remoteDirty && signature === lastRemoteSignature) {
            setAutosaveStatus('Sin cambios nuevos por guardar.');
            return Promise.resolve();
        }

        setAutosaveStatus('Guardando respuestas...');

        return fetch(autosaveUrl, {
            method: 'POST',
            keepalive: true,
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: formPayload({
                question_index: String(current),
                reason: reason
            })
        })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 409) {
                        locked = true;
                        reloadWhenVisible();
                    }
                    throw new Error('autosave_failed');
                }
                return response.json();
            })
            .then(() => {
                lastRemoteSignature = signature;
                remoteDirty = false;
                const stamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                setAutosaveStatus('Guardado remoto: ' + stamp);
            })
            .catch(() => {
                setAutosaveStatus('No se pudo confirmar el guardado remoto. El borrador local sigue disponible.');
            });
    }

    function lockTeacherPreview() {
        const notice = document.getElementById('examLockNotice');
        if (notice) {
            notice.classList.remove('d-none');
        }

        form.querySelectorAll('input, textarea, select, button').forEach(element => {
            element.disabled = true;
        });
    }

    function reloadWhenVisible() {
        if (document.visibilityState === 'visible') {
            window.location.reload();
            return;
        }

        document.addEventListener('visibilitychange', function reloadOnReturn() {
            if (document.visibilityState === 'visible') {
                document.removeEventListener('visibilitychange', reloadOnReturn);
                window.location.reload();
            }
        });
    }

    function sendLockBeacon(reason) {
        if (!navigator.sendBeacon || !lockUrl) {
            return false;
        }

        const payload = formPayload({ reason: reason });

        return navigator.sendBeacon(lockUrl, payload);
    }

    function sendLockFetch(reason) {
        if (!lockUrl) {
            return Promise.resolve();
        }

        return fetch(lockUrl, {
            method: 'POST',
            keepalive: true,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: urlEncodedPayload({ reason: reason }).toString()
        });
    }

    function lockAttempt(reason = 'tab_switch') {
        if (locked || submittedByTimer) return;
        locked = true;

        if (isTeacherPreview) {
            lockTeacherPreview();
            return;
        }

        if (!lockUrl) return;
        flushEventQueue();

        const sentByBeacon = document.visibilityState === 'hidden' && sendLockBeacon(reason);
        if (sentByBeacon) {
            reloadWhenVisible();
            return;
        }

        sendLockFetch(reason)
            .catch(() => {})
            .finally(() => {
                reloadWhenVisible();
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
                saveLocalDraft();
                flushEventQueue();
                submittedByTimer = true;
                form.classList.remove('d-none');
                const delay = remoteDelayMs(4000);
                setAutosaveStatus('Tiempo terminado. Enviando por lote en ' + describeDelay(delay) + '...');
                form.querySelectorAll('input, textarea, select, button').forEach(element => {
                    element.readOnly = true;
                    if (element.tagName === 'BUTTON') {
                        element.disabled = true;
                    }
                });
                setTimeout(() => form.submit(), delay);
            }
        }, 1000);
    }

    function isFullscreenActive() {
        return Boolean(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
    }

    function requestFullscreen() {
        if (isTeacherPreview) return;
        const root = document.documentElement;
        const fn = root.requestFullscreen || root.webkitRequestFullscreen || root.msRequestFullscreen;
        if (!fn) {
            if (fullscreenNotice) fullscreenNotice.classList.add('d-none');
            form.classList.remove('d-none');
            logEvent('fullscreen_unavailable', {}, true);
            return;
        }

        ignoreBlurUntil = Date.now() + 2000;
        Promise.resolve(fn.call(root))
            .then(() => {
                if (fullscreenNotice) fullscreenNotice.classList.add('d-none');
                form.classList.remove('d-none');
                logEvent('fullscreen_enter');
            })
            .catch(() => {
                form.classList.remove('d-none');
                logEvent('fullscreen_denied', {}, true);
            });
    }

    startTimer();
    clearConfirmedDraft();
    restoreDraft();

    form.addEventListener('input', function () {
        saveLocalDraft();
        markRemoteDirty();
    });
    form.addEventListener('change', function () {
        saveLocalDraft();
        markRemoteDirty();
    });

    form.addEventListener('submit', function (event) {
        saveLocalDraft();
        flushEventQueue();
        if (!isTeacherPreview && !submittedByTimer && !form.dataset.queuedSubmit) {
            event.preventDefault();
            submittedByTimer = true;
            form.dataset.queuedSubmit = '1';
            const delay = remoteDelayMs(3000);
            setAutosaveStatus('Enviando examen por lote en ' + describeDelay(delay) + '...');
            form.querySelectorAll('button').forEach(button => {
                button.disabled = true;
            });
            setTimeout(() => form.submit(), delay);
            return;
        }

        submittedByTimer = true;
    });

    if (isTeacherPreview) {
        form.classList.remove('d-none');
    } else {
        if (fullscreenButton) {
            fullscreenButton.addEventListener('click', requestFullscreen);
        }

        document.addEventListener('fullscreenchange', function () {
            if (!isFullscreenActive() && !locked && !submittedByTimer && Date.now() > ignoreBlurUntil) {
                logEvent('fullscreen_exit');
                lockAttempt('fullscreen_exit');
            }
        });

        ['webkitfullscreenchange', 'MSFullscreenChange'].forEach(eventName => {
            document.addEventListener(eventName, function () {
                if (!isFullscreenActive() && !locked && !submittedByTimer && Date.now() > ignoreBlurUntil) {
                    logEvent('fullscreen_exit');
                    lockAttempt('fullscreen_exit');
                }
            });
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            saveLocalDraft();
            lockAttempt('visibility_hidden');
        }
    });

    window.addEventListener('blur', function () {
        if (Date.now() < ignoreBlurUntil) return;
        lockAttempt('window_blur');
    });

    window.addEventListener('pagehide', function () {
        saveLocalDraft();
        flushEventQueue();
        lockAttempt('pagehide');
    });

    document.addEventListener('copy', function (event) {
        if (isTeacherPreview || locked || submittedByTimer) return;
        event.preventDefault();
        logEvent('copy_blocked', { question_index: String(current) });
    });

    document.addEventListener('paste', function (event) {
        if (isTeacherPreview || locked || submittedByTimer) return;
        event.preventDefault();
        logEvent('paste_blocked', { question_index: String(current) });
    });

    document.addEventListener('contextmenu', function (event) {
        if (isTeacherPreview || locked || submittedByTimer) return;
        event.preventDefault();
        logEvent('context_menu_blocked', { question_index: String(current) });
    });

    window.addEventListener('beforeunload', function () {
        if (!locked && !submittedByTimer) {
            saveLocalDraft();
            flushEventQueue();
        }
    });

    if (!isTeacherPreview) {
        setInterval(flushEventQueue, 60000);
        const initialAutosaveDelay = remoteDelayMs(5000);
        setTimeout(() => {
            autosave('interval_120s');
            setInterval(() => autosave('interval_120s'), autosaveEveryMs);
        }, initialAutosaveDelay);
        saveLocalDraft();
    }
});
</script>
@endif
@endsection
