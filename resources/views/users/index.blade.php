@extends('layouts.app')

@section('title', 'Usuarios')

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
                                <h4>Usuarios</h4>
                            </div>
                            <div class="col-sm-6">
                                <a class="btn btn-primary float-right" href="{{ route('users.create') }}">Nuevo</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h3 class="text-center">Listado de usuarios</h3>
                        <hr>
                        <table id="users" class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Imagen</th>
                                    <th>Rol</th>
                                    <th>Estatus</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                    <tr>
                                        <td>{{ $user->id }}</td>
                                        <td>{{ $user->name }}</td>
                                        <td>

                                        </td>
                                        <td>{{ $user->getRoleNames()->first() }}</td>
                                        <td>{{ $user->is_active ? 'Activo' : 'Baja'}}</td>
                                        <td>
                                            @if($user->is_active)
                                                <form action="{{ route('users.deactivate', $user->id)}}" method="POST">
                                                    @csrf
                                                    <a href="{{ route('users.show', $user->id) }}" class='btn btn-primary btn-sm'><i class="far fa-eye"></i></a>
                                                    <a href="{{ route('users.edit', $user->id) }}" class='btn btn-warning btn-sm'><i class="far fa-edit"></i></a>
                                                    <button class="btn btn-sm btn-danger" type="submit"><i class="fa fa-times"></i></button>
                                                </form>
                                            @else
                                                <form action="{{ route('users.activate', $user->id)}}" method="POST">
                                                    @csrf
                                                    <a href="{{ route('users.show', $user->id) }}" class='btn btn-primary btn-sm'><i class="far fa-eye"></i></a>
                                                    <a href="{{ route('users.edit', $user->id) }}" class='btn btn-warning btn-sm'><i class="far fa-edit"></i></a>
                                                    <button class="btn btn-sm btn-success" type="submit"><i class="fa fa-check"></i></button>
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

@section('page_css')
@endsection

@section('page_scripts')
    <script>
        $(document).ready(function () {
            $('#users').DataTable({
                dom: '<"area-fluid"<"row"<"col"l><"col"B><"col"f>>>rtip',
                "columnDefs": [
                    { "type": "num", "targets": 0 }
                ],
                "order": [[ 0, "asc" ]],
                buttons: [
                    'excelHtml5',
                    'pdfHtml5'
                ],
                language: {
                    url: '/datatables.json'
                }
            });
        });
    </script>
@endsection