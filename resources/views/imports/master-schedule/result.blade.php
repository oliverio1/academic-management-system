@extends('layouts.app')

@section('title', 'Resultado importacion horario maestro')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-10">
            <div class="card {{ $exitCode === 0 ? 'card-success' : 'card-danger' }}">
                <div class="card-header">
                    <h3 class="card-title">Resultado de importacion</h3>
                </div>
                <div class="card-body">
                    <div class="alert {{ $exitCode === 0 ? 'alert-success' : 'alert-danger' }}">
                        <strong>Campus:</strong> {{ $campus->name }} ({{ $campus->code }})
                        <span class="ml-3"><strong>Ciclo:</strong> {{ $cycle->name }} ({{ $cycle->code }})</span>
                    </div>

                    <pre class="bg-dark text-white p-3 rounded" style="white-space: pre-wrap;">{{ $output }}</pre>
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
