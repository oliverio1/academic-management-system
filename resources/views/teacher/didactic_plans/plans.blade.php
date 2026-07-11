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
                                            <td>{{ $plan->schoolCycle->name ?? '-' }}</td>
                                            <td>{{ $plan->academicPeriod->name ?? '-' }}</td>
                                            <td>
                                                {{ optional($plan->start_date)->format('d/m/Y') ?? '-' }}
                                                -
                                                {{ optional($plan->end_date)->format('d/m/Y') ?? '-' }}
                                            </td>
                                            <td>{{ $plan->items->count() }}</td>
                                            <td class="text-right">
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
