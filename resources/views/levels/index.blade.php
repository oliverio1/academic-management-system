
@extends('layouts.app')

@section('title', 'Niveles')

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
                                <h4>Niveles</h4>
                            </div>
                            <div class="col-sm-6">
                                <a class="btn btn-primary float-right" href="{{ route('levels.create') }}">Nuevo</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h3 class="text-center">Listado de niveles</h3>
                        <hr>
                        <table id="levels" class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Modalidad</th>
                                    <th>Estatus</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($levels as $level)
                                    <tr>
                                        <td>{{ $level->id }}</td>
                                        <td>{{ $level->name }}</td>
                                        <td>{{ $level->modality->name }}</td>
                                        <td>{{ $level->is_active ? 'Activo' : 'Baja'}}</td>
                                        <td>
                                            @if($level->is_active)
                                                <form action="{{ route('levels.deactivate', $level->id)}}" method="POST">
                                                    @csrf
                                                    <a href="{{ route('levels.show', $level->id) }}" class='btn btn-primary btn-sm'><i class="far fa-eye"></i></a>
                                                    <a href="{{ route('levels.edit', $level->id) }}" class='btn btn-warning btn-sm'><i class="far fa-edit"></i></a>
                                                    <button class="btn btn-sm btn-danger" type="submit"><i class="fa fa-times"></i></button>
                                                </form>
                                            @else
                                                <form action="{{ route('levels.activate', $level->id)}}" method="POST">
                                                    @csrf
                                                    <a href="{{ route('levels.show', $level->id) }}" class='btn btn-primary btn-sm'><i class="far fa-eye"></i></a>
                                                    <a href="{{ route('levels.edit', $level->id) }}" class='btn btn-warning btn-sm'><i class="far fa-edit"></i></a>
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
            $('#levels').DataTable({
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