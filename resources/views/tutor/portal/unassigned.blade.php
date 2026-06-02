@extends('layouts.app')

@section('title', 'Tutor')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Portal tutor</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning mb-0">
                        No tienes un alumno asociado. Solicita a coordinación que te asigne un alumno para visualizar su aprovechamiento.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
