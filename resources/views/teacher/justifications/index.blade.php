
@extends('layouts.app')

@section('title', 'Justificantes')

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
                                <h4>Justificantes</h4>
                            </div>
                            <div class="col-sm-6">
                                <a class="btn btn-primary float-right" href="{{ route('modalities.create') }}">Nueva</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($justifications->isEmpty())
                            <div class="alert alert-info">
                                No hay justificantes registrados.
                            </div>
                        @else
                            <div class="card">
                                <div class="card-body p-0">

                                    <table class="table table-sm mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Alumno</th>
                                                <th>Grupo</th>
                                                <th>Materia</th>
                                                <th>Fechas</th>
                                                <th>Registrado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($justifications as $justification)
                                                <tr>
                                                    <td>{{ $justification->student->user->name }}</td>
                                                    <td>
                                                        {{ $justification->from_date->format('d/m/Y') }}
                                                        –
                                                        {{ $justification->to_date->format('d/m/Y') }}
                                                    </td>
                                                    <td>{{ $justification->reason }}</td>
                                                    <td class="text-muted">
                                                        {{ $justification->created_at->format('d/m/Y H:i') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page_css')
@endsection

@section('page_scripts')
@endsection