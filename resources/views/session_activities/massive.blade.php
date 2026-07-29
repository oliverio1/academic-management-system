@extends('layouts.app')

@section('title', 'Actividades masivas')

@section('content')
@foreach (['success', 'info', 'warning', 'danger'] as $type)
    @if(session($type))
        <div class="alert alert-{{ $type }} alert-dismissible fade show" role="alert">
            <strong>{{ session($type) }}</strong>
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
@endforeach

<div class="content px-3">
    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Actividades masivas</h4>
                    <div class="text-muted small">
                        {{ $assignment->subject->name }} | Grupo {{ $assignment->group->name }}
                    </div>
                </div>
                <a href="{{ route('teacher.classes.sessions.index', $assignment) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i>Sesiones
                </a>
            </div>
        </div>

        <div class="card-body">
            @if($testCycleEditing)
                <div class="alert alert-info">
                    Ciclo de prueba: esta hoja permite registrar y editar actividades sin candados.
                </div>
            @endif

            <form method="GET" action="{{ route('session.activities.massive', $assignment) }}" class="activity-filter mb-3">
                <div class="form-row align-items-end">
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold mb-1" for="mode">Vista</label>
                        <select id="mode" name="mode" class="form-control form-control-sm">
                            <option value="week" {{ $mode === 'week' ? 'selected' : '' }}>Semana</option>
                            <option value="month" {{ $mode === 'month' ? 'selected' : '' }}>Mes</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold mb-1" for="date">Fecha base</label>
                        <input id="date" type="date" name="date" class="form-control form-control-sm" value="{{ $anchorDate->toDateString() }}">
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <button class="btn btn-primary btn-sm btn-block">
                            <i class="fas fa-filter mr-1"></i>Aplicar
                        </button>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2 text-md-right">
                        <div class="small text-muted">Periodo visible</div>
                        <div class="font-weight-bold">{{ $from->format('d/m/Y') }} - {{ $to->format('d/m/Y') }}</div>
                    </div>
                </div>
            </form>

            @if($sessions->isEmpty())
                <div class="alert alert-light border mb-0">
                    No hay sesiones programadas en el rango seleccionado.
                </div>
            @else
                <form method="POST" action="{{ route('session.activities.massive.store', $assignment) }}" id="massiveActivityForm">
                    @csrf
                    <input type="hidden" name="mode" value="{{ $mode }}">
                    <input type="hidden" name="date" value="{{ $anchorDate->toDateString() }}">

                    <div class="activity-grid-wrap">
                        <table class="table table-sm table-bordered activity-grid mb-0">
                            <thead>
                                <tr>
                                    <th class="field-col">Registro</th>
                                    @foreach($sessions as $session)
                                        @php
                                            $lock = $sessionLocks[(int) $session->id] ?? ['locked' => false, 'reason' => null];
                                        @endphp
                                        <th class="session-col {{ $lock['locked'] ? 'session-locked' : '' }}">
                                            <div>{{ $session->session_date->format('d/m') }}</div>
                                            <div class="small">{{ substr((string) $session->start_time, 0, 5) }} - {{ substr((string) $session->end_time, 0, 5) }}</div>
                                            @if($lock['locked'])
                                                <div class="small text-danger">{{ $lock['reason'] }}</div>
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th class="field-col">Realizado en clase</th>
                                    @foreach($sessions as $session)
                                        @php
                                            $activity = $session->sessionActivity;
                                            $lock = $sessionLocks[(int) $session->id] ?? ['locked' => false, 'reason' => null];
                                        @endphp
                                        <td>
                                            <textarea
                                                name="activities[{{ $session->id }}][title]"
                                                class="form-control form-control-sm activity-title"
                                                rows="3"
                                                placeholder="Portada, discusion grupal, exposicion..."
                                                {{ $lock['locked'] ? 'disabled' : '' }}>{{ old("activities.{$session->id}.title", optional($activity)->title) }}</textarea>
                                        </td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <th class="field-col">Observaciones</th>
                                    @foreach($sessions as $session)
                                        @php
                                            $activity = $session->sessionActivity;
                                            $lock = $sessionLocks[(int) $session->id] ?? ['locked' => false, 'reason' => null];
                                        @endphp
                                        <td>
                                            <textarea
                                                name="activities[{{ $session->id }}][description]"
                                                class="form-control form-control-sm"
                                                rows="2"
                                                placeholder="Indicaciones, evidencia, notas..."
                                                {{ $lock['locked'] ? 'disabled' : '' }}>{{ old("activities.{$session->id}.description", optional($activity)->description) }}</textarea>
                                        </td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <th class="field-col">Cuenta para evaluacion</th>
                                    @foreach($sessions as $session)
                                        @php
                                            $activity = $session->sessionActivity;
                                            $linked = optional($activity)->evaluableActivity;
                                            $lock = $sessionLocks[(int) $session->id] ?? ['locked' => false, 'reason' => null];
                                            $checked = old("activities.{$session->id}.is_evaluable", $linked ? '1' : null);
                                        @endphp
                                        <td class="text-center">
                                            <input type="hidden" name="activities[{{ $session->id }}][is_evaluable]" value="0" {{ $lock['locked'] ? 'disabled' : '' }}>
                                            <div class="custom-control custom-switch d-inline-block">
                                                <input
                                                    type="checkbox"
                                                    class="custom-control-input js-evaluable-switch"
                                                    id="evaluable-{{ $session->id }}"
                                                    name="activities[{{ $session->id }}][is_evaluable]"
                                                    value="1"
                                                    data-session-id="{{ $session->id }}"
                                                    {{ $checked ? 'checked' : '' }}
                                                    {{ $lock['locked'] ? 'disabled' : '' }}>
                                                <label class="custom-control-label" for="evaluable-{{ $session->id }}">Si</label>
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <th class="field-col">Nombre de evaluacion</th>
                                    @foreach($sessions as $session)
                                        @php
                                            $activity = $session->sessionActivity;
                                            $linked = optional($activity)->evaluableActivity;
                                            $lock = $sessionLocks[(int) $session->id] ?? ['locked' => false, 'reason' => null];
                                        @endphp
                                        <td>
                                            <input
                                                type="text"
                                                name="activities[{{ $session->id }}][evaluation_title]"
                                                class="form-control form-control-sm js-evaluation-field evaluation-field-{{ $session->id }}"
                                                placeholder="Ej. Examen diagnostico"
                                                value="{{ old("activities.{$session->id}.evaluation_title", optional($linked)->title) }}"
                                                data-locked="{{ $lock['locked'] ? '1' : '0' }}"
                                                {{ $lock['locked'] ? 'disabled' : '' }}>
                                        </td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <th class="field-col">Rubro</th>
                                    @foreach($sessions as $session)
                                        @php
                                            $activity = $session->sessionActivity;
                                            $linked = optional($activity)->evaluableActivity;
                                            $lock = $sessionLocks[(int) $session->id] ?? ['locked' => false, 'reason' => null];
                                            $criteria = $criteriaByPeriod->get((int) $session->academic_period_id, collect());
                                            $selectedCriterion = old(
                                                "activities.{$session->id}.evaluation_criterion_id",
                                                optional($linked)->evaluation_criterion_id ?: optional($activity)->evaluation_criterion_id
                                            );
                                        @endphp
                                        <td>
                                            <select
                                                name="activities[{{ $session->id }}][evaluation_criterion_id]"
                                                class="form-control form-control-sm js-evaluation-field evaluation-field-{{ $session->id }}"
                                                data-locked="{{ $lock['locked'] ? '1' : '0' }}"
                                                {{ $lock['locked'] ? 'disabled' : '' }}>
                                                <option value="">Sin rubro</option>
                                                @foreach($criteria as $criterion)
                                                    <option value="{{ $criterion->id }}" {{ (string) $selectedCriterion === (string) $criterion->id ? 'selected' : '' }}>
                                                        {{ $criterion->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
                        <div class="text-muted small mb-2">
                            Puedes registrar solo lo realizado, o activar evaluacion para crear la actividad calificable de esa sesion.
                        </div>
                        <button class="btn btn-primary mb-2" id="massiveActivitySubmitButton">
                            <i class="fas fa-save mr-1"></i>Guardar actividades
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection

@section('page_css')
<style>
    .activity-filter {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 4px;
        padding: 0.75rem;
    }

    .activity-grid-wrap {
        border: 1px solid #dee2e6;
        max-height: 74vh;
        overflow: auto;
    }

    .activity-grid th {
        background: #f8f9fa;
    }

    .activity-grid thead th {
        border-bottom: 1px solid #cfd4da;
        position: sticky;
        top: 0;
        z-index: 3;
    }

    .field-col {
        left: 0;
        min-width: 180px;
        position: sticky;
        z-index: 2;
    }

    thead .field-col {
        z-index: 4;
    }

    .session-col {
        min-width: 260px;
        text-align: center;
        vertical-align: top;
    }

    .session-locked {
        background: #f1f3f5 !important;
    }

    .activity-title {
        font-weight: 700;
    }

    @media (max-width: 767.98px) {
        .field-col {
            min-width: 140px;
        }

        .session-col {
            min-width: 220px;
        }
    }
</style>
@endsection

@section('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('massiveActivityForm');
    const submitButton = document.getElementById('massiveActivitySubmitButton');

    function setEvaluationFields(sessionId, enabled) {
        document.querySelectorAll('.evaluation-field-' + sessionId).forEach(field => {
            if (field.dataset.locked === '1') {
                return;
            }
            field.disabled = !enabled;
            if (!enabled) {
                field.value = '';
            }
        });
    }

    document.querySelectorAll('.js-evaluable-switch').forEach(toggle => {
        setEvaluationFields(toggle.dataset.sessionId, toggle.checked);
        toggle.addEventListener('change', function () {
            setEvaluationFields(toggle.dataset.sessionId, toggle.checked);
        });
    });

    if (form && submitButton) {
        form.addEventListener('submit', function () {
            submitButton.disabled = true;
            submitButton.textContent = 'Guardando...';
        });
    }
});
</script>
@endsection
