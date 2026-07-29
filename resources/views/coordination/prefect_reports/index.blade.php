@extends('layouts.app')

@section('title', 'Reportes de prefectura')

@section('content')
@include('coordination.reports._table_styles')

<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Reportes de prefectura</h3>
                    <small class="text-muted">Incidencias registradas por prefectura para su seguimiento.</small>
                </div>
                <div class="card-body">
                    @if(session('info'))
                        <div class="alert alert-success">{{ session('info') }}</div>
                    @endif

                    @include('coordination.prefect_reports._table')
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
