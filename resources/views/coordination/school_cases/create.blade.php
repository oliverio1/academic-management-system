@extends('layouts.app')

@section('title', 'Nuevo caso escolar')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Nuevo caso escolar</h3>
                    <small class="text-muted">Registra situaciones academicas, administrativas, de comunicacion, instalaciones o convivencia.</small>
                </div>
                <form method="POST" action="{{ route('coordination.school-cases.store') }}">
                    @csrf
                    <div class="card-body">
                        @include('coordination.school_cases.partials.form')
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <a href="{{ route('coordination.school-cases.index') }}" class="btn btn-secondary">Cancelar</a>
                        <button class="btn btn-primary">Guardar caso</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
