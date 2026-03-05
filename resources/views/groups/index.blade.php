@extends('layouts.app')

@section('title', 'Grupos')

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
                                <h4>Grupos</h4>
                            </div>
                            <div class="col-sm-6">
                                <a class="btn btn-primary float-right"href="{{ route('groups.create') }}"> Nuevo grupo</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h3 class="text-center">Listado de grupos</h3>
                        <hr>
                        <table id="groups" class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Capacidad</th>
                                    <th>Estatus</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($groups as $group)
                                    @php
                                        $percentage = $group->capacity > 0
                                            ? ($group->students->count() / $group->capacity) * 100
                                            : 0;
                                        if ($percentage < 50) {
                                            $barClass = 'bg-success';
                                        } elseif ($percentage < 80) {
                                            $barClass = 'bg-warning';
                                        } else {
                                            $barClass = 'bg-danger';
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ $group->id }}</td>
                                        <td>{{ $group->name }}</td>
                                        <td>
                                            <div class="progress">
                                                <div
                                                    class="progress-bar {{ $barClass }}"
                                                    role="progressbar"
                                                    style="width: {{ $percentage }}%;"
                                                    aria-valuenow="{{ $group->students->count() }}"
                                                    aria-valuemin="0"
                                                    aria-valuemax="{{ $group->capacity }}"
                                                >
                                                    {{ $group->students->count() }} / {{ $group->capacity }}
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $group->is_active ? 'Activo' : 'Baja' }}</td>
                                        <td>
                                            @if($group->is_active)
                                                <form action="{{ route('groups.deactivate', $group->id) }}"method="POST" style="display:inline">
                                                    @csrf
                                                    <a href="{{ route('groups.show', $group->id) }}"class="btn btn-primary btn-sm"><i class="far fa-eye"></i></a>
                                                    <a href="{{ route('groups.edit', $group->id) }}"class="btn btn-warning btn-sm"><i class="far fa-edit"></i></a>
                                                    <a href="{{ route('groups.subjects.edit', $group) }}" class="btn btn-info btn-sm" title="Asignar materias"><i class="fas fa-book"></i></a>
                                                    <a href="{{ route('groups.assignments.edit', $group) }}" class="btn btn-secondary btn-sm" title="Asignar profesores"><i class="fas fa-user"></i></a>
                                                    <!-- <a href="{{ route('groups.students.edit', $group) }}" class="btn btn-secondary btn-sm" title="Asignar alumnos"><i class="fas fa-user-graduate"></i></a> -->
                                                    <button class="btn btn-danger btn-sm" type="submit"><i class="fa fa-times"></i></button>
                                                </form>
                                            @else
                                                <form action="{{ route('groups.activate', $group->id) }}"method="POST" style="display:inline">
                                                    @csrf
                                                    <a href="{{ route('groups.show', $group->id) }}"class="btn btn-primary btn-sm"><i class="far fa-eye"></i></a>
                                                    <a href="{{ route('groups.edit', $group->id) }}"class="btn btn-warning btn-sm"><i class="far fa-edit"></i></a>
                                                    <a href="{{ route('groups.subjects.edit', $group) }}" class="btn btn-info btn-sm" title="Asignar materias"><i class="fas fa-book"></i></a>
                                                    <a href="{{ route('groups.assignments.edit', $group) }}" class="btn btn-secondary btn-sm" title="Asignar profesores"><i class="fas fa-user"></i></a>
                                                    <!-- <a href="{{ route('groups.students.edit', $group) }}" class="btn btn-secondary btn-sm" title="Asignar alumnos"><i class="fas fa-user-graduate"></i></a> -->
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
        $('#groups').DataTable({
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