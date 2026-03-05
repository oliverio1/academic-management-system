@extends('layouts.app')

@section('title', 'Editar profesor')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">

                <div class="card-header">
                    <h4>Editar profesor</h4>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('teachers.update', $teacher) }}">
                        @csrf
                        @method('PUT')

                        @include('teachers._form')

                        <button class="btn btn-primary">Guardar</button>
                        <a href="{{ route('teachers.index') }}" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
