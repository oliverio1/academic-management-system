@extends('layouts.app')

@section('title', 'Dashboard')

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
                                <h4>Dashboard de Coordinación</h4>
                                <p class="text-muted mb-0">
                                    Visión académica institucional
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        {{-- ALERTAS --}}
                        @include('dashboards.admin._alerts')

                        {{-- MÉTRICAS --}}
                        @include('dashboards.admin._metrics')

                        {{-- ACCESOS --}}
                        @include('dashboards.admin._actions')
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
