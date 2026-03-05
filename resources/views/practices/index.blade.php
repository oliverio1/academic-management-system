@extends('layouts.app')

@section('title', 'Prácticas')

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
                                <h4>Prácticas</h4>
                            </div>
                            <div class="col-sm-6">
                                <a class="btn btn-primary float-right" href="{{ route('practices.create', $assignment) }}">Nueva práctica</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h3 class="text-center">{{ $assignment->subject->name }} – Prácticas</h3>
                        <hr>
                        <table id="practices" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Título</th>
                                    <th>Fecha límite</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($practices as $practice)
                                    <tr>
                                        <td>{{ $practice->number }}</td>
                                        <td>{{ $practice->title }}</td>
                                        <td>{{ optional($practice->due_date)->format('d/m/Y') }}</td>
                                        <td class="text-right">
                                            <a href="{{ route('practices.edit', $practice) }}"
                                            class="btn btn-sm btn-secondary">Editar</a>

                                            <form method="POST"
                                                action="{{ route('practices.destroy', $practice) }}"
                                                class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-danger">
                                                    Eliminar
                                                </button>
                                            </form>
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
            $('#practices').DataTable({
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