@extends('layouts.app')

@section('title', 'Editar tutor')

@section('content')
<div class="app-content-header">
    <div class="container-fluid">
        <h3 class="mb-0">Editar tutor</h3>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('tutors.update', $tutor) }}">
                    @csrf
                    @method('PUT')
                    @include('tutors._form')
                    <button class="btn btn-primary">Guardar cambios</button>
                    <a href="{{ route('tutors.index') }}" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

