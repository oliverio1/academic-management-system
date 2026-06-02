@extends('layouts.app')

@section('title', 'Nuevo tutor')

@section('content')
<div class="app-content-header">
    <div class="container-fluid">
        <h3 class="mb-0">Alta de tutor</h3>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('tutors.store') }}">
                    @csrf
                    @include('tutors._form')
                    <button class="btn btn-primary">Guardar</button>
                    <a href="{{ route('tutors.index') }}" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

