@extends('layouts.app')

@section('title', 'Validacion de horario maestro')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-10">
            <div class="card {{ $exitCode === 0 ? 'card-primary' : 'card-danger' }}">
                <div class="card-header">
                    <h3 class="card-title">Validacion del libro maestro</h3>
                </div>
                <div class="card-body">
                    <div class="alert {{ $exitCode === 0 ? 'alert-info' : 'alert-danger' }}">
                        <strong>Campus:</strong> {{ $campus->name }} ({{ $campus->code }})
                        <span class="ml-3"><strong>Ciclo:</strong> {{ $cycle->name }} ({{ $cycle->code }})</span>
                    </div>

                    @if($exitCode === 0)
                        <div class="alert {{ $summary['has_warnings'] ? 'alert-warning' : 'alert-success' }}">
                            Esta validacion no guardo cambios. Revisa el resumen antes de confirmar la importacion.
                            @if($options['generate_sessions'] ?? false)
                                <br>Al confirmar, tambien se generaran las sesiones academicas del ciclo.
                            @endif
                        </div>
                    @endif

                    @include('imports.master-schedule._summary', ['summary' => $summary, 'output' => $output])
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('imports.master-schedule.create') }}" class="btn btn-secondary">Volver</a>
                    @if($exitCode === 0)
                        <form method="POST" action="{{ route('imports.master-schedule.import') }}">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                Confirmar importacion
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
