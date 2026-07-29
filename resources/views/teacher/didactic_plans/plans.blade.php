@extends('layouts.app')

@section('title', 'Planeaciones')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0">Planeaciones didácticas</h4>
                            <small class="text-muted">{{ $assignment->subject->name }} - Grupo {{ $assignment->group->name }}</small>
                        </div>
                        <div>
                            <a href="{{ route('teacher.didactic-plans.template', $assignment) }}"
                               class="btn btn-outline-success btn-sm">
                                Descargar Excel
                            </a>
                            <a href="{{ route('teacher.didactic-plans.import', $assignment) }}"
                               class="btn btn-outline-info btn-sm">
                                Cargar Excel
                            </a>
                            <a href="{{ route('teacher.didactic-plans.create', $assignment) }}"
                               class="btn btn-primary btn-sm">
                                Nueva planeación
                            </a>
                            <a href="{{ route('teacher.didactic-plans.index') }}"
                               class="btn btn-secondary btn-sm">
                                Volver
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if($plans->isEmpty())
                        <div class="alert alert-light border mb-0">
                            Aún no hay planeaciones registradas para esta materia.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table data-datatable="true" class="table table-sm table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Título</th>
                                        <th>Estatus</th>
                                        <th>Ciclo</th>
                                        <th>Parcial</th>
                                        <th>Rango</th>
                                        <th>Renglones</th>
                                        <th class="text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($plans as $plan)
                                        <tr>
                                            <td>{{ $plan->title }}</td>
                                            <td>
                                                @if(($plan->status ?? \App\Models\DidacticPlan::STATUS_FINAL) === \App\Models\DidacticPlan::STATUS_TENTATIVE)
                                                    <span class="badge badge-info">Tentativa</span>
                                                    <div class="small text-muted">Generada por sistema</div>
                                                @else
                                                    <span class="badge badge-success">Final</span>
                                                @endif
                                            </td>
                                            <td>{{ $plan->schoolCycle->name ?? '-' }}</td>
                                            <td>{{ $plan->academicPeriod->name ?? '-' }}</td>
                                            <td>
                                                {{ optional($plan->start_date)->format('d/m/Y') ?? '-' }}
                                                -
                                                {{ optional($plan->end_date)->format('d/m/Y') ?? '-' }}
                                            </td>
                                            <td>{{ $plan->items->count() }}</td>
                                            <td class="text-right">
                                                @if(($plan->status ?? \App\Models\DidacticPlan::STATUS_FINAL) === \App\Models\DidacticPlan::STATUS_TENTATIVE)
                                                    <form method="POST"
                                                          action="{{ route('teacher.didactic-plans.confirm-final', $plan) }}"
                                                          class="d-inline-block"
                                                          onsubmit="return confirm('Se confirmara esta planeacion como final. Despues se tomara como base para generar actividades. Deseas continuar?');">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button class="btn btn-success btn-sm">Confirmar como final</button>
                                                    </form>
                                                @endif
                                                <a href="{{ route('teacher.didactic-plans.pdf', $plan) }}"
                                                   class="btn btn-outline-primary btn-sm"
                                                   target="_blank">
                                                    PDF
                                                </a>
                                                <a href="{{ route('teacher.didactic-plans.edit', $plan) }}"
                                                   class="btn btn-outline-secondary btn-sm">
                                                    Editar
                                                </a>
                                                <form method="POST"
                                                      action="{{ route('teacher.didactic-plans.clone-to-peer-groups', $plan) }}"
                                                      class="d-inline-block"
                                                      onsubmit="return confirm('Se generaran planeaciones para los otros grupos de esta misma materia ajustando las fechas al horario de cada grupo. Deseas continuar?');">
                                                    @csrf
                                                    <label class="small text-muted mb-0 mr-1">
                                                        <input type="checkbox" name="replace_existing" value="1">
                                                        Reemplazar
                                                    </label>
                                                    <button class="btn btn-outline-success btn-sm">Clonar a otros grupos</button>
                                                </form>
                                                <form method="POST"
                                                      action="{{ route('teacher.didactic-plans.destroy', $plan) }}"
                                                      class="d-inline"
                                                      onsubmit="return confirm('Se eliminará la planeación. ¿Continuar?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-outline-danger btn-sm">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
