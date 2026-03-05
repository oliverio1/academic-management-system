@extends('layouts.app')

@section('title', 'Editar materia')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4>Editar materia</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('subjects.update', $subject) }}">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            @include('subjects._form')
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-info" type="submit">Guardar</button>
                        <a href="{{ route('subjects.index') }}" class="btn btn-danger">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
