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
                            <div class="col-sm-6">
                                <a class="btn btn-primary float-right"href="{{ route('subjects.create') }}"> Nueva materia</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h3 class="text-center">Listado de materias</h3>
                        <hr>
                        <table id="subjects" class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Estatus</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($subjects as $subject)
                                    <tr>
                                        <td>{{ $subject->id }}</td>
                                        <td>{{ $subject->name }}</td>
                                        <td>{{ $subject->is_active ? 'Activo' : 'Baja' }}</td>

                                        <td class="text-nowrap">
                                            <a href="{{ route('subjects.teachers.assign', $subject) }}"
                                            class="btn btn-info btn-sm"
                                            title="Asignar profesores">
                                                <i class="fas fa-chalkboard-teacher"></i>
                                            </a>
                                            <a href="{{ route('subjects.edit', $subject->id) }}"
                                            class="btn btn-warning btn-sm"
                                            title="Editar">
                                                <i class="far fa-edit"></i>
                                            </a>

                                            @if($subject->is_active)
                                                <form action="{{ route('subjects.deactivate', $subject->id) }}"
                                                    method="POST"
                                                    class="d-inline">
                                                    @csrf
                                                    <button class="btn btn-danger btn-sm"
                                                            title="Dar de baja">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('subjects.activate', $subject->id) }}"
                                                    method="POST"
                                                    class="d-inline">
                                                    @csrf
                                                    <button class="btn btn-success btn-sm"
                                                            title="Activar">
                                                        <i class="fa fa-check"></i>
                                                    </button>
                                                </form>
                                            @endif
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
        $('#subjects').DataTable({
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