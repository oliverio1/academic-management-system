@extends('layouts.app')

@section('title', 'Dashboard Admin')

@section('content')
<div class="content px-3">
    <div class="row">

        <div class="col-md-3">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ \App\Models\Student::count() }}</h3>
                    <p>Alumnos</p>
                </div>
                <div class="icon"><i class="fas fa-user-graduate"></i></div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ \App\Models\Teacher::count() }}</h3>
                    <p>Profesores</p>
                </div>
                <div class="icon"><i class="fas fa-chalkboard-teacher"></i></div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ \App\Models\Group::count() }}</h3>
                    <p>Grupos</p>
                </div>
                <div class="icon"><i class="fas fa-layer-group"></i></div>
            </div>
        </div>

    </div>
</div>
@endsection
