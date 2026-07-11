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
                            <div class="col-sm-6">
                                <h4 class="mb-0">Entregables</h4>
                                <small class="text-muted">
                                    {{ $assignment->subject->name }} - Grupo {{ $assignment->group->name }}
                                </small>
                            </div>
                            <div class="col-sm-6 text-right">
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
                        <table id="practices" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tipo</th>
                                    <th>Título</th>
                                    <th>Realización</th>
                                    <th>Entrega</th>
                                    <th class="text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($practices as $practice)
                                    <tr>
                                        <td>{{ $practice->number }}</td>
                                        <td>{{ $practice->kind_label }}</td>
                                        <td>{{ $practice->title }}</td>
                                        <td>{{ optional($practice->realization_date)->format('d/m/Y') ?? '-' }}</td>
                                        <td>{{ optional($practice->due_date)->format('d/m/Y') ?? '-' }}</td>
                                        <td class="text-right">
                                            <a href="{{ route('practices.submissions', $practice) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                Entregas
                                            </a>
                                            <a href="{{ route('practices.edit', $practice) }}"
                                               class="btn btn-sm btn-secondary">
                                                Editar
                                            </a>

                                            <form method="POST"
                                                  action="{{ route('practices.destroy', $practice) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Se eliminará este entregable. ¿Deseas continuar?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-danger">
                                                    Eliminar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
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
