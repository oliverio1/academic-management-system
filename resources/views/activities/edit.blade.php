@extends('layouts.app')

@section('title', 'Editar actividad')

@section('content')
<div class="content px-3">
    <div class="card">
        <div class="card-header">
            <h4>Editar actividad</h4>
            <small>
                {{ $assignment->subject->name }} –
                {{ $assignment->group->name }}
            </small>
        </div>

        <form method="POST"
              action="{{ route('activities.update', $activity) }}">
            @csrf
            @method('PUT')

            <div class="card-body">
                @include('activities._form')
            </div>

            <div class="card-footer text-right">
                <a href="{{ route('activities.index', $assignment) }}"
                   class="btn btn-secondary">Cancelar</a>
                <button class="btn btn-primary">Actualizar</button>
            </div>
        </form>
    </div>
</div>
@endsection
