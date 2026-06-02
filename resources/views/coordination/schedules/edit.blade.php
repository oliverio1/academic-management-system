@extends('layouts.app')

@section('title', 'Editar horario')

@section('content')
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0">Editar horario semanal</h3>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('coordination.schedules.update', $schedule) }}">
                        @csrf
                        @method('PUT')
                        @include('coordination.schedules._form')

                        <div class="mt-3">
                            <button class="btn btn-warning">Actualizar horario</button>
                            <a href="{{ route('coordination.schedules.index') }}" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

