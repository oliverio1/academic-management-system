
@extends('layouts.app')

@section('title', 'Modalidades')

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
                                <h4>Modalidades</h4>
                            </div>
                            <div class="col-sm-6">
                                <a class="btn btn-primary float-right" href="{{ route('modalities.create') }}">Nueva</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h3 class="text-center">Listado de modalidades</h3>
                        <hr>
                        <table id="modalities" class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Estatus</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($modalities as $modality)
                                    <tr>
                                        <td>{{ $modality->id }}</td>
                                        <td>{{ $modality->name }}</td>
                                        <td>{{ $modality->is_active ? 'Activo' : 'Baja'}}</td>
                                        <td>
                                            @if($modality->is_active)
                                                <form action="{{ route('modalities.deactivate', $modality->id)}}" method="POST">
                                                    @csrf
                                                    <a href="{{ route('modalities.show', $modality->id) }}" class='btn btn-primary btn-sm'><i class="far fa-eye"></i></a>
                                                    <a href="{{ route('modalities.edit', $modality->id) }}" class='btn btn-warning btn-sm'><i class="far fa-edit"></i></a>
                                                    <button class="btn btn-sm btn-danger" type="submit"><i class="fa fa-times"></i></button>
                                                </form>
                                            @else
                                                <form action="{{ route('modalities.activate', $modality->id)}}" method="POST">
                                                    @csrf
                                                    <a href="{{ route('modalities.show', $modality->id) }}" class='btn btn-primary btn-sm'><i class="far fa-eye"></i></a>
                                                    <a href="{{ route('modalities.edit', $modality->id) }}" class='btn btn-warning btn-sm'><i class="far fa-edit"></i></a>
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
            $('#modalities').DataTable({
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