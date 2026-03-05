@extends('layouts.app')

@section('title', 'Equipos')

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
                                <h4>Equipos</h4>
                            </div>
                            <div class="col-sm-6">
                                <a href="{{ route('teams.create', $assignment) }}" class="btn btn-primary mb-3">Crear equipo</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h3 class="text-center">Listado de equipos del grupo {{ $assignment->group->name }}</h3>
                        <hr>
                        @foreach($teams as $team)
                            <div class="card mb-2">
                                <div class="card-body">
                                    <strong>{{ $team->name }}</strong>

                                    <div class="mt-2">
                                        @foreach($team->students as $student)
                                            <span class="badge badge-info">
                                                {{ $student->user->name }}
                                            </span>
                                        @endforeach
                                    </div>

                                    <div class="mt-2">

                                        <form method="POST"
                                            action="{{ route('teams.destroy', $team) }}"
                                            class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
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