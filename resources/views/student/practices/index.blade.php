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
                        </div>
                    </div>
                    <div class="card-body">
                        <h3 class="text-center">Listado de prácticas</h3>
                        <hr>
                        @foreach($practices as $practice)
                            <div class="card">
                                <div class="card-body">
                                    <h5>{{ $practice->title }}</h5>
                                    <p>{{ $practice->teachingAssignment->subject->name }}</p>

                                    <a href="{{ route('student.practices.show', $practice) }}"
                                    class="btn btn-primary">
                                        Entrar
                                    </a>
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