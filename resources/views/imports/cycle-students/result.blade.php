@extends('layouts.app')

@section('title', 'Resultado importacion alumnos')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-10">
            <div class="card {{ $summary['has_warnings'] ? 'card-warning' : 'card-success' }}">
                <div class="card-header">
                    <h3 class="card-title">Resultado de importacion de alumnos</h3>
                </div>
                <div class="card-body">
                    <div class="alert {{ $summary['has_warnings'] ? 'alert-warning' : 'alert-success' }}">
                        <strong>Campus:</strong> {{ $campus->name }} ({{ $campus->code }})
                        <span class="ml-3"><strong>Ciclo:</strong> {{ $cycle->name }} ({{ $cycle->code }})</span>
                    </div>

                    @if(!$summary['has_warnings'])
                        <div class="alert alert-success">
                            Importacion guardada correctamente.
                        </div>
                    @endif

                    @include('imports.cycle-students._summary', ['summary' => $summary])
                </div>
                <div class="card-footer">
                    <a href="{{ route('coordination.students.active-cycle', ['school_cycle_id' => $cycle->id]) }}" class="btn btn-primary">
                        Ver alumnos del ciclo
                    </a>
                    <a href="{{ route('imports.cycle-students.create') }}" class="btn btn-secondary ml-2">
                        Importar otro archivo
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
