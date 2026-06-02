@extends('layouts.app')

@section('title', 'Campus')

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
                        <div class="row">
                            <div class="col-sm-6">
                                <h4>Campus</h4>
                            </div>
                            <div class="col-sm-6">
                                <a class="btn btn-primary float-right" href="{{ route('campuses.create') }}">Nuevo</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table data-datatable="true" id="campuses" class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Codigo</th>
                                    <th>Estatus</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($campuses as $campus)
                                    <tr>
                                        <td>{{ $campus->id }}</td>
                                        <td>{{ $campus->name }}</td>
                                        <td>{{ $campus->code }}</td>
                                        <td>{{ $campus->is_active ? 'Activo' : 'Baja' }}</td>
                                        <td>
                                            @if($campus->is_active)
                                                <form action="{{ route('campuses.deactivate', $campus->id) }}" method="POST" style="display:inline">
                                                    @csrf
                                                    <a href="{{ route('campuses.show', $campus->id) }}" class="btn btn-primary btn-sm"><i class="far fa-eye"></i></a>
                                                    <a href="{{ route('campuses.edit', $campus->id) }}" class="btn btn-warning btn-sm"><i class="far fa-edit"></i></a>
                                                    <button class="btn btn-sm btn-danger" type="submit"><i class="fa fa-times"></i></button>
                                                </form>
                                            @else
                                                <form action="{{ route('campuses.activate', $campus->id) }}" method="POST" style="display:inline">
                                                    @csrf
                                                    <a href="{{ route('campuses.show', $campus->id) }}" class="btn btn-primary btn-sm"><i class="far fa-eye"></i></a>
                                                    <a href="{{ route('campuses.edit', $campus->id) }}" class="btn btn-warning btn-sm"><i class="far fa-edit"></i></a>
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

@section('page_scripts')
    <script>
        $(document).ready(function () {
            $('#campuses').DataTable({
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
