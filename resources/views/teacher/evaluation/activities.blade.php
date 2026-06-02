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
                        @if($activities->isEmpty())
                            <div class="alert alert-info mb-0">
                                Esta materia aun no tiene actividades registradas.
                            </div>
                        @else
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
                                                <th>Actividad</th>
                                                <th>Rubro</th>
                                                <th>Periodo</th>
                                                <th>Fecha de entrega</th>
                                                <th class="text-center">Accion</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($activitiesWithSession as $activity)
                                                <tr>
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
                                                <th>Actividad</th>
                                                <th>Rubro</th>
                                                <th>Periodo</th>
                                                <th>Fecha de entrega</th>
                                                <th class="text-center">Accion</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($manualActivities as $activity)
                                                <tr>
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
@endsection
