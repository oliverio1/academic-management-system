@extends('layouts.app')

@section('title', 'Planeación didáctica')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Planeación didáctica por materia</h4>
                </div>
                <div class="card-body">
                    @if($assignments->isEmpty())
                        <div class="alert alert-light border mb-0">
                            No hay materias asignadas para generar planeaciones.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table data-datatable="true" class="table table-sm table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Materia</th>
                                        <th>Grupo</th>
                                        <th>Estatus planeación</th>
                                        <th class="text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($assignments as $assignment)
                                        <tr>
                                            <td>{{ $assignment->subject->name }}</td>
                                            <td>{{ $assignment->group->name }}</td>
                                            <td>
                                                <span class="badge {{ $assignment->planning_status['class'] ?? 'badge-secondary' }}">
                                                    {{ $assignment->planning_status['label'] ?? 'No comenzado' }}
                                                </span>
                                                @if(($assignment->latest_plan_status ?? null) === \App\Models\DidacticPlan::STATUS_TENTATIVE)
                                                    <div class="small text-muted mt-1">Generada automáticamente; pendiente de confirmar.</div>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                <a href="{{ route('teacher.didactic-plans.template', $assignment) }}"
                                                   class="btn btn-outline-success btn-sm mr-1">
                                                    Descargar Excel
                                                </a>
                                                <a href="{{ route('teacher.didactic-plans.import', $assignment) }}"
                                                   class="btn btn-outline-info btn-sm mr-1">
                                                    Cargar Excel
                                                </a>
                                                @if(!empty($assignment->clone_source_assignment_id))
                                                    <form action="{{ route('teacher.didactic-plans.clone-from-peer', $assignment) }}"
                                                          method="POST"
                                                          class="d-inline-block mr-1">
                                                        @csrf
                                                        <input type="hidden" name="source_assignment_id" value="{{ $assignment->clone_source_assignment_id }}">
                                                        <button type="submit"
                                                                class="btn btn-outline-secondary btn-sm"
                                                                onclick="return confirm('Se clonarÃ¡ la planeaciÃ³n desde {{ $assignment->clone_source_label }}. Â¿Deseas continuar?')">
                                                            Clonar planeaciÃ³n
                                                        </button>
                                                    </form>
                                                @endif
                                                @if(in_array(($assignment->planning_status['key'] ?? ''), ['complete','partial'], true))
                                                    @if(!empty($assignment->latest_plan_id))
                                                        <a href="{{ route('teacher.didactic-plans.edit', $assignment->latest_plan_id) }}"
                                                           class="btn btn-warning btn-sm mr-1">
                                                            Editar
                                                        </a>
                                                        <form action="{{ route('teacher.didactic-plans.destroy', $assignment->latest_plan_id) }}"
                                                              method="POST"
                                                              class="d-inline-block mr-1"
                                                              onsubmit="return confirm('Â¿Deseas eliminar esta planeaciÃ³n? Esta acciÃ³n no se puede deshacer.');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-danger btn-sm">
                                                                Eliminar
                                                            </button>
                                                        </form>
                                                    @endif
                                                    @if(!empty($assignment->latest_plan_id))
                                                        <a href="{{ route('teacher.didactic-plans.pdf', $assignment->latest_plan_id) }}"
                                                           class="btn btn-primary btn-sm">
                                                            Ver
                                                        </a>
                                                        <form action="{{ route('teacher.didactic-plans.clone-to-peer-groups', $assignment->latest_plan_id) }}"
                                                              method="POST"
                                                              class="d-inline-block ml-1"
                                                              onsubmit="return confirm('Se generaran planeaciones para los otros grupos de esta misma materia ajustando las fechas al horario de cada grupo. Deseas continuar?');">
                                                            @csrf
                                                            <label class="small text-muted mb-0 mr-1">
                                                                <input type="checkbox" name="replace_existing" value="1">
                                                                Reemplazar
                                                            </label>
                                                            <button type="submit" class="btn btn-outline-success btn-sm">
                                                                Clonar a otros grupos
                                                            </button>
                                                        </form>
                                                    @endif
                                                @else
                                                    <a href="{{ route('teacher.didactic-plans.create', $assignment) }}"
                                                       class="btn btn-primary btn-sm">
                                                        Configurar
                                                    </a>
                                                @endif
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

