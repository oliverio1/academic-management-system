@extends('layouts.app')

@section('title', 'Periodos')

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
                                <h4>Periodos</h4>
                            </div>
                            <div class="col-sm-6">
                                <a class="btn btn-primary float-right" href="{{ route('academic-periods.create') }}">Nuevo</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h3 class="text-center">Listado de periodos</h3>
                        <hr>
                        <table id="periods" class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Inicio</th>
                                    <th>Termino</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($periods as $period)
                                    <tr>
                                        <td>{{ $period->id }}</td>
                                        <td>{{ $period->name }} ({{ $period->modality->name }})</td>
                                        <td>{{ \Carbon\Carbon::parse($period->start_date)->translatedFormat('d \d\e F \d\e Y') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($period->end_date)->translatedFormat('d \d\e F \d\e Y') }}</td>
                                        <td>
                                            @if($period->is_active)
                                                <form action="{{ route('academic-periods.deactivate', $period->id)}}" method="POST">
                                                    @csrf
                                                    <a href="{{ route('academic-periods.edit', $period->id) }}" class='btn btn-warning btn-sm'><i class="far fa-edit"></i></a>
                                                    <button class="btn btn-sm btn-danger" type="submit"><i class="fa fa-times"></i></button>
                                                </form>
                                            @else
                                                <form action="{{ route('academic-periods.activate', $period->id)}}" method="POST">
                                                    @csrf
                                                    <a href="{{ route('academic-periods.edit', $period->id) }}" class='btn btn-warning btn-sm'><i class="far fa-edit"></i></a>
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
            $('#periods').DataTable({
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