
@extends('layouts.app')

@section('title', 'Actividad')

@section('content')
    @php
        $isReadOnly = $isReadOnly ?? $session->isAttendanceClosed();
        $periodDisabled = $periodDisabled ?? false;
    @endphp
    @if(session('warning'))
        <div class="alert alert-warning">
            {{ session('warning') }}
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            Revisa la información antes de guardar.
        </div>
    @endif
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">

                <div class="card">

                    {{-- Header --}}
                    <div class="card-header">
                        <h5 class="mb-0">
                            Actividad de la sesión —
                            {{ $session->teachingAssignment->subject->name }}
                            <small class="text-muted">
                                ({{ $session->teachingAssignment->group->name }})
                            </small>
                        </h5>
                        <hr>
                        Sesión: {{ $session->session_date->translatedFormat('l j \\d\\e F \\d\\e Y') }} | {{ substr($session->start_time, 0, 5) }} – {{ substr($session->end_time, 0, 5) }}
                    </div>

                    {{-- Body --}}
                    <div class="card-body">
                        {{-- Aviso de cierre --}}
                        @if($periodDisabled)
                            <div class="alert alert-secondary">
                                Este periodo esta deshabilitado por coordinacion.
                                <br>
                                <small>Vista en modo consulta.</small>
                            </div>
                        @elseif($session->isAttendanceClosed())
                            <div class="alert alert-secondary">
                                🔒 La semana académica está cerrada.
                                <br>
                                <small>No es posible asignar o modificar actividades.</small>
                            </div>
                        @endif

                        {{-- Formulario --}}
                        <form method="POST"
                            action="{{ route('session.activities.store', $session) }}">
                            @csrf

                            {{-- Título --}}
                            <div class="form-group">
                                <label for="title">
                                    Actividad realizada / asignada en clase
                                </label>
                                <input type="text"
                                    id="title"
                                    name="title"
                                    class="form-control"
                                    placeholder="Ej. Resolver ejercicios 5–10 del cuaderno"
                                    value="{{ old('title', optional($activity)->title) }}"
                                    {{ $isReadOnly ? 'disabled' : '' }}
                                    required>
                            </div>

                            <div class="form-group">
                                <label for="evaluation_criterion_id">
                                    Rubro de la actividad
                                </label>
                                <select id="evaluation_criterion_id"
                                    name="evaluation_criterion_id"
                                    class="form-control"
                                    {{ $isReadOnly ? 'disabled' : '' }}>
                                    <option value="">Sin rubro</option>
                                    @foreach($criteria as $criterion)
                                        <option value="{{ $criterion->id }}"
                                            {{ (string) old('evaluation_criterion_id', optional($activity)->evaluation_criterion_id) === (string) $criterion->id ? 'selected' : '' }}>
                                            {{ $criterion->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    Puedes dejar esta actividad sin rubro asignado.
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="temario_point_id">
                                    Tema del temario visto en esta sesion
                                </label>
                                @php
                                    $selectedSubtopics = collect(old('temario_subtopic_ids', optional($activity)->temario_subtopic_ids ?? []))
                                        ->map(fn ($id) => (string) $id)
                                        ->values();
                                @endphp
                                <select id="temario_point_id"
                                    name="temario_point_id"
                                    class="form-control"
                                    {{ $isReadOnly ? 'disabled' : '' }}>
                                    <option value="">Sin tema especifico</option>
                                    @foreach(($topicOptions ?? collect()) as $topic)
                                        <option value="{{ $topic['id'] }}"
                                            {{ (string) old('temario_point_id', optional($activity)->temario_point_id) === (string) $topic['id'] ? 'selected' : '' }}>
                                            {{ $topic['text'] }}
                                            @if(!empty($topic['unit_text']))
                                                | Unidad: {{ $topic['unit_text'] }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    Selecciona un tema (nivel 2 del temario).
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="temario_subtopic_ids">
                                    Subtemas vistos en esta sesion
                                </label>
                                <select id="temario_subtopic_ids"
                                    name="temario_subtopic_ids[]"
                                    class="form-control"
                                    size="8"
                                    multiple
                                    {{ $isReadOnly ? 'disabled' : '' }}>
                                    @foreach(($subtopicOptions ?? collect()) as $subtopic)
                                        <option value="{{ $subtopic['id'] }}"
                                            data-topic-id="{{ $subtopic['topic_id'] }}"
                                            {{ $selectedSubtopics->contains((string) $subtopic['id']) ? 'selected' : '' }}>
                                            {{ $subtopic['text'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    Puedes elegir varios subtemas manteniendo presionada la tecla Ctrl.
                                </small>
                            </div>

                            {{-- Descripción --}}
                            <div class="form-group">
                                <label for="description">
                                    Descripción (opcional)
                                </label>
                                <textarea id="description"
                                    name="description"
                                    rows="3"
                                    class="form-control"
                                    placeholder="Indicaciones adicionales, observaciones, etc."
                                    {{ $isReadOnly ? 'disabled' : '' }}>{{ old('description', optional($activity)->description) }}</textarea>
                            </div>

                            {{-- Footer --}}
                            <div class="d-flex justify-content-between mt-4">
                                <a href="{{ route('teacher.classes.sessions.index', $session->teachingAssignment) }}"
                                class="btn btn-secondary">
                                    Volver
                                </a>

                                @unless($isReadOnly)
                                    <button class="btn btn-primary">
                                        {{ $activity ? 'Actualizar actividad' : 'Guardar actividad' }}
                                    </button>
                                @endunless
                            </div>

                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection

@section('page_css')
<style>
    .attendance-radio {
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* Ocultamos el radio nativo */
    .attendance-radio input[type="radio"] {
        display: none;
    }

    /* Círculo base */
    .attendance-radio label {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 2px solid #ccc;
        cursor: pointer;
        position: relative;
    }

    /* Punto interior (apagado) */
    .attendance-radio label::after {
        content: '';
        width: 10px;
        height: 10px;
        border-radius: 50%;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: transparent;
    }

    /* ===== COLORES ===== */

    /* Presente */
    .attendance-present input:checked + label {
        border-color: #28a745;
    }
    .attendance-present input:checked + label::after {
        background: #28a745;
    }

    /* Retardo */
    .attendance-late input:checked + label {
        border-color: #ffc107;
    }
    .attendance-late input:checked + label::after {
        background: #ffc107;
    }

    /* Falta */
    .attendance-absent input:checked + label {
        border-color: #dc3545;
    }
    .attendance-absent input:checked + label::after {
        background: #dc3545;
    }

    /* Deshabilitado (asistencia cerrada) */
    .attendance-radio input:disabled + label {
        cursor: not-allowed;
        opacity: 0.6;
    }
</style>
@endsection

@section('page_scripts')
    <script>
        (function () {
            const topicSelect = document.getElementById('temario_point_id');
            const subtopicSelect = document.getElementById('temario_subtopic_ids');

            if (!topicSelect || !subtopicSelect) {
                return;
            }

            function filterSubtopicsByTopic() {
                const topicId = topicSelect.value;
                Array.from(subtopicSelect.options).forEach((option) => {
                    const belongsTo = option.getAttribute('data-topic-id');
                    const visible = topicId !== '' && belongsTo === topicId;
                    option.hidden = !visible;
                    if (!visible) {
                        option.selected = false;
                    }
                });
            }

            topicSelect.addEventListener('change', filterSubtopicsByTopic);
            filterSubtopicsByTopic();
        })();
    </script>
@endsection


