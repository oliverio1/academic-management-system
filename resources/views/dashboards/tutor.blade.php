@extends('layouts.app')

@section('title', 'Dashboard Tutor')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Dashboard de tutor</h3>
                </div>
                <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Aprovechamiento</h5>
                        <p class="card-text text-muted">Consulta materias, asistencia y calificaciones del alumno asociado.</p>
                        <a href="{{ route('tutor.subjects') }}" class="btn btn-primary">Ver aprovechamiento</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Seguimientos</h5>
                        <p class="card-text text-muted">Visualiza los seguimientos academicos/conductuales del alumno.</p>
                        <a href="{{ route('tutor.followups') }}" class="btn btn-primary">Ver seguimientos</a>
                    </div>
                </div>
            </div>
        </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
