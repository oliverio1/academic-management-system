@extends('layouts.app')

@section('title', 'Centro de mando académico')

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
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h4 class="mb-1">Centro de mando académico</h4>
                            <p class="text-muted mb-0">
                                Riesgo estudiantil, seguimiento docente y operación diaria en un solo tablero.
                            </p>
                        </div>
                        <div class="col-lg-4 text-lg-right mt-3 mt-lg-0">
                            <div class="small text-muted">Ciclo activo</div>
                            <div class="font-weight-bold">
                                {{ $activeCycle?->name ?? 'Sin ciclo activo' }}
                            </div>
                            <div class="small text-muted">
                                Corte: {{ optional($generatedAt)->format('d/m/Y H:i') }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @include('dashboards.admin._pilot_showcase')
                    @include('dashboards.admin._notifications')
                    @include('dashboards.admin._alerts')
                    @include('dashboards.admin._metrics')
                    @include('dashboards.admin._actions')
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
