@extends('layouts.app')

@section('title', 'Materias')

@section('content')
    @if(session('info'))
        <div class="alert alert-primary" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>
    @endif
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-6">
                                <h4>Materias</h4>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h3 class="text-center">Listado de profesores</h3>
                        <hr>
                        <table id="teachers" class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Grupo</th>
                                    <th>Materia</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($assignments as $assignment)
                                    <tr>
                                        <td>{{ $assignment->id }}</td>
                                        <td>{{ $assignment->group->name }}</td>
                                        <td>{{ $assignment->subject->name }}</td>
                                        <td>
                                            <a href="{{ route('activities.index', $assignment) }}"
                                            class="btn btn-sm btn-primary">
                                                Actividades
                                            </a>

                                            <a href="{{ route('schedules.index', [
                                                'group' => $assignment->group_id,
                                                'assignment' => $assignment->id
                                            ]) }}"
                                            class="btn btn-sm btn-secondary">
                                                Horarios
                                            </a>
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
        $('#teachers').DataTable({
            dom: '<"area-fluid"<"row"<"col"l><"col"B><"col"f>>>rtip',
            order: [[0, "asc"]],
            buttons: ['excelHtml5', 'pdfHtml5'],
            language: {
                url: '/datatables.json'
            }
        });
    });
</script>
@endsection