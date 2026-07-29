@extends('layouts.app')

@section('title', 'Actividades de evaluacion')

@section('content')
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-8">
                                <h4 class="mb-0">
                                    {{ $assignment->subject->name }} - {{ $assignment->group->name }}
                                </h4>
                            </div>
                            <div class="col-sm-4 text-right">
                                <a href="{{ route('teacher.evaluation.manual.create', $assignment) }}" class="btn btn-primary mr-2">
                                    Nueva actividad manual
                                </a>
                                <a href="{{ route('teacher.evaluation.index') }}" class="btn btn-secondary">
                                    Volver
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success">
                                {{ session('success') }}
                            </div>
                        @endif
                        @error('clone')
                            <div class="alert alert-danger">
                                {{ $message }}
                            </div>
                        @enderror
                        @error('activity_ids')
                            <div class="alert alert-danger">
                                Selecciona al menos una actividad para clonar.
                            </div>
                        @enderror
                        @error('to_assignment_ids')
                            <div class="alert alert-danger">
                                Selecciona al menos un grupo destino.
                            </div>
                        @enderror

                        @if($activities->isEmpty())
                            <div class="alert alert-info mb-0">
                                Esta materia aun no tiene actividades registradas.
                            </div>
                        @else
                            @if(($cloneCandidates ?? collect())->isNotEmpty())
                                <form method="POST" action="{{ route('teacher.evaluation.activities.clone-same-subject', $assignment) }}" class="mb-0">
                                    @csrf
                                    <div class="alert alert-info border">
                                        <div class="row align-items-end">
                                            <div class="col-md-8 mb-2 mb-md-0">
                                                <label class="mb-1"><strong>Clonar actividades a la misma materia</strong></label>
                                                <select name="to_assignment_ids[]" class="form-control" multiple required size="{{ min(5, max(2, $cloneCandidates->count())) }}">
                                                    @foreach($cloneCandidates as $candidate)
                                                        <option value="{{ $candidate->id }}">
                                                            Grupo {{ $candidate->group?->name ?? '-' }}
                                                            @if($candidate->section_label || $candidate->section_number)
                                                                - {{ $candidate->section_display }}
                                                            @endif
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4 text-md-right">
                                                <button class="btn btn-info">
                                                    Clonar seleccionadas
                                                </button>
                                            </div>
                                        </div>
                                        <small class="text-muted d-block mt-2">
                                            Mantén Ctrl presionado para elegir varios grupos. Los grupos destino deben tener rubros equivalentes y no tener actividades registradas.
                                        </small>
                                    </div>
                            @else
                                <div class="alert alert-light border">
                                    No hay otros grupos de esta misma materia en el ciclo activo para clonar actividades.
                                </div>
                            @endif
                            <h6 class="mb-2">Actividades asociadas a sesión</h6>
                            @if($activitiesWithSession->isEmpty())
                                <div class="alert alert-light border">
                                    No hay actividades asociadas a una sesión.
                                </div>
                            @else
                                <div class="table-responsive mb-4">
                                    <table data-datatable="true" class="table table-sm table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                @if(($cloneCandidates ?? collect())->isNotEmpty())
                                                    <th class="text-center" style="width: 44px;">
                                                        <input type="checkbox" class="js-select-all-activities" aria-label="Seleccionar todas las actividades">
                                                    </th>
                                                @endif
                                                <th>Actividad</th>
                                                <th>Rubro</th>
                                                <th>Periodo</th>
                                                <th>Fecha de entrega</th>
                                                <th class="text-center">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($activitiesWithSession as $activity)
                                                <tr>
                                                    @if(($cloneCandidates ?? collect())->isNotEmpty())
                                                        <td class="text-center">
                                                            <input type="checkbox" name="activity_ids[]" value="{{ $activity->id }}" class="js-activity-checkbox">
                                                        </td>
                                                    @endif
                                                    <td>{{ $activity->title }}</td>
                                                    <td>{{ optional($activity->evaluationCriterion)->name ?? 'Sin rubro' }}</td>
                                                    <td>{{ optional($activity->academicPeriod)->name ?? '-' }}</td>
                                                    <td>{{ $activity->due_date ? $activity->due_date->format('Y-m-d') : '-' }}</td>
                                                    <td class="text-center">
                                                        <a href="{{ route('activities.grade', $activity) }}" class="btn btn-outline-primary btn-sm">
                                                            Evaluar
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            <h6 class="mb-2">Actividades manuales (sin sesión)</h6>
                            @if($manualActivities->isEmpty())
                                <div class="alert alert-light border mb-0">
                                    No hay actividades manuales registradas.
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table data-datatable="true" class="table table-sm table-hover mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                @if(($cloneCandidates ?? collect())->isNotEmpty())
                                                    <th class="text-center" style="width: 44px;">
                                                        <input type="checkbox" class="js-select-all-activities" aria-label="Seleccionar todas las actividades">
                                                    </th>
                                                @endif
                                                <th>Actividad</th>
                                                <th>Rubro</th>
                                                <th>Periodo</th>
                                                <th>Fecha de entrega</th>
                                                <th class="text-center">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($manualActivities as $activity)
                                                <tr>
                                                    @if(($cloneCandidates ?? collect())->isNotEmpty())
                                                        <td class="text-center">
                                                            <input type="checkbox" name="activity_ids[]" value="{{ $activity->id }}" class="js-activity-checkbox">
                                                        </td>
                                                    @endif
                                                    <td>{{ $activity->title }}</td>
                                                    <td>{{ optional($activity->evaluationCriterion)->name ?? 'Sin rubro' }}</td>
                                                    <td>{{ optional($activity->academicPeriod)->name ?? '-' }}</td>
                                                    <td>{{ $activity->due_date ? $activity->due_date->format('Y-m-d') : '-' }}</td>
                                                    <td class="text-center">
                                                        <a href="{{ route('activities.grade', $activity) }}" class="btn btn-outline-primary btn-sm">
                                                            Evaluar
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                            @if(($cloneCandidates ?? collect())->isNotEmpty())
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page_css')
@endsection

@section('page_scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const activityCheckboxes = Array.from(document.querySelectorAll('.js-activity-checkbox'));
        const selectAllCheckboxes = Array.from(document.querySelectorAll('.js-select-all-activities'));

        const refreshSelectAllState = () => {
            const checkedCount = activityCheckboxes.filter((checkbox) => checkbox.checked).length;

            selectAllCheckboxes.forEach((checkbox) => {
                checkbox.checked = activityCheckboxes.length > 0 && checkedCount === activityCheckboxes.length;
                checkbox.indeterminate = checkedCount > 0 && checkedCount < activityCheckboxes.length;
            });
        };

        selectAllCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', function () {
                activityCheckboxes.forEach((activityCheckbox) => {
                    activityCheckbox.checked = checkbox.checked;
                });
                refreshSelectAllState();
            });
        });

        activityCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', refreshSelectAllState);
        });
    });
</script>
@endsection
