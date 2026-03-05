@extends('layouts.app')

@section('title', 'Profesores')

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
                                <h4>Profesores</h4>
                            </div>
                            <div class="col-sm-6">
                                <a class="btn btn-primary float-right"href="{{ route('teachers.create') }}"> Nuevo profesor</a>
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
                                    <th>Nombre</th>
                                    <th>Estatus</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($teachers as $teacher)
                                    <tr>
                                        <td>{{ $teacher->id }}</td>
                                        <td>{{ $teacher->user->name }}</td>
                                        <td>{{ $teacher->is_active ? 'Activo' : 'Baja' }}</td>
                                        <td>
                                            @if($teacher->is_active)
                                                <form action="{{ route('teachers.deactivate', $teacher->id) }}"method="POST" style="display:inline">
                                                    @csrf
                                                    <a href="{{ route('teachers.show', $teacher->id) }}"class="btn btn-primary btn-sm"><i class="far fa-eye"></i></a>
                                                    <a href="{{ route('teachers.edit', $teacher->id) }}"class="btn btn-warning btn-sm"><i class="far fa-edit"></i></a>
                                                    <a href="{{ route('teachers.subjects.assign', $teacher) }}" class="btn btn-info btn-sm" title="Asignar materias"><i class="fas fa-book"></i></a>
                                                    <button class="btn btn-danger btn-sm" type="submit"><i class="fa fa-times"></i></button>
                                                </form>
                                            @else
                                                <form action="{{ route('teachers.activate', $teacher->id) }}"method="POST" style="display:inline">
                                                    @csrf
                                                    <a href="{{ route('teachers.show', $teacher->id) }}"class="btn btn-primary btn-sm"><i class="far fa-eye"></i></a>
                                                    <a href="{{ route('teachers.edit', $teacher->id) }}"class="btn btn-warning btn-sm"><i class="far fa-edit"></i></a>
                                                    <a href="{{ route('teachers.subjects.assign', $teacher) }}" class="btn btn-info btn-sm" title="Asignar materias"><i class="fas fa-book"></i></a>
                                                    <button class="btn btn-success btn-sm" type="submit"><i class="fa fa-check"></i></button>
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