@extends('layouts.app')

@section('title', 'Entregables')

@section('content')
    @if(session('info'))
        <div class="alert alert-primary" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>
    @endif

    <div class="content px-3">
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <h4 class="mb-0">Entregables digitales</h4>
                                <small class="text-muted">
                                    {{ $assignment->subject->name }} - Grupo {{ $assignment->group->name }}
                                </small>
                            </div>
                            <div class="col-md-5 text-md-right mt-2 mt-md-0">
                                <a class="btn btn-primary" href="{{ route('practices.create', $assignment) }}">
                                    Nuevo entregable
                                </a>
                                <a class="btn btn-outline-secondary" href="{{ route('teacher.classes.index') }}">
                                    Volver
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($practices->isEmpty())
                            <div class="alert alert-info mb-0">
                                Aun no has creado entregables para esta clase.
                            </div>
                        @else
                            <div class="table-responsive">
                                <table id="practices" class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 72px;">Numero</th>
                                            <th style="width: 120px;">Tipo</th>
                                            <th>Entregable</th>
                                            <th style="width: 125px;">Realizacion</th>
                                            <th style="width: 125px;">Entrega</th>
                                            <th style="width: 130px;">Estado</th>
                                            <th class="text-right" style="width: 250px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($practices as $practice)
                                            @php
                                                $submittedCount = (int) ($practice->submitted_submissions_count ?? 0);
                                                $reviewedCount = (int) ($practice->reviewed_submissions_count ?? 0);
                                                $isPastDue = $practice->due_date
                                                    ? $practice->due_date->copy()->endOfDay()->isPast()
                                                    : false;
                                            @endphp
                                            <tr>
                                                <td>{{ $practice->number }}</td>
                                                <td><span class="badge badge-info">{{ $practice->kind_label }}</span></td>
                                                <td>
                                                    <strong>{{ $practice->title }}</strong>
                                                    @if($practice->activity?->evaluationCriterion)
                                                        <div class="text-muted small">
                                                            Rubro: {{ $practice->activity->evaluationCriterion->name }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>{{ optional($practice->realization_date)->format('d/m/Y') ?? '-' }}</td>
                                                <td>{{ optional($practice->due_date)->format('d/m/Y') ?? '-' }}</td>
                                                <td>
                                                    @if($isPastDue)
                                                        <span class="badge badge-secondary">Cerrado</span>
                                                    @else
                                                        <span class="badge badge-warning">Abierto</span>
                                                    @endif
                                                    <div class="text-muted small">
                                                        {{ $submittedCount }} enviadas / {{ $reviewedCount }} revisadas
                                                    </div>
                                                </td>
                                                <td class="text-right">
                                                    <a href="{{ route('practices.submissions', $practice) }}"
                                                       class="btn btn-sm btn-outline-primary">
                                                        Entregas
                                                    </a>
                                                    <a href="{{ route('practices.edit', $practice) }}"
                                                       class="btn btn-sm btn-outline-secondary">
                                                        Editar
                                                    </a>

                                                    <form method="POST"
                                                          action="{{ route('practices.destroy', $practice) }}"
                                                          class="d-inline"
                                                          onsubmit="return confirm('Se eliminara este entregable. Deseas continuar?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-sm btn-outline-danger">
                                                            Eliminar
                                                        </button>
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

@section('page_scripts')
    <script>
        $(document).ready(function () {
            $('#practices').DataTable({
                order: [[0, 'asc']],
                language: {
                    url: '/datatables.json'
                }
            });
        });
    </script>
@endsection
