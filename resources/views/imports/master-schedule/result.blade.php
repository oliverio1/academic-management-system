@extends('layouts.app')

@section('title', 'Resultado importacion horario maestro')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Resultado de importacion</h3>
                </div>
                <div class="card-body">
                    <div class="alert {{ $exitCode === 0 ? 'alert-success' : 'alert-danger' }}">
                        <strong>Campus:</strong> {{ $campus->name }} ({{ $campus->code }})
                        <span class="ml-3"><strong>Ciclo:</strong> {{ $cycle->name }} ({{ $cycle->code }})</span>
                    </div>

                    @if($exitCode === 0)
                        <div class="alert alert-success">
                            Importacion guardada correctamente.
                        </div>
                    @endif

                    @include('imports.master-schedule._summary', ['summary' => $summary, 'output' => $output])
                </div>
                <div class="card-footer">
                    <a href="{{ route('coordination.schedules.groups-calendar', ['school_cycle_id' => $cycle->id]) }}" class="btn btn-primary">
                        Ver calendario de grupos
                    </a>
                    <a href="{{ route('imports.master-schedule.create') }}" class="btn btn-secondary ml-2">
                        Importar otro archivo
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
