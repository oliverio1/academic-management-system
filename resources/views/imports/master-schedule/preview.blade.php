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

                    <pre class="bg-dark text-white p-3 rounded" style="white-space: pre-wrap;">{{ $output }}</pre>
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
