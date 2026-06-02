@extends('layouts.app')

@section('title', 'Editar campus')

@section('content')
    <div class="content px-3">
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <h3>Editar campus</h3>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('campuses.update', $campus) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                @include('campuses._form')
                            </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-info" type="submit">Guardar</button>
                        <a href="{{ route('campuses.index') }}" class="btn btn-danger">Cancelar</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

