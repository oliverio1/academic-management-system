@extends('layouts.app')

@section('title', 'Nuevo profesor')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">

                <div class="card-header">
                    <h4>Nuevo profesor</h4>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('teachers.store') }}">
                        @csrf

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
