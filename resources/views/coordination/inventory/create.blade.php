@extends('layouts.app')

@section('title', 'Nuevo articulo')

@section('content')
<div class="content px-3">
    <div class="row mt-3">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h4 class="mb-0">Nuevo articulo de inventario</h4></div>
                <form method="POST" action="{{ route('coordination.inventory.store') }}">
                    @csrf
                    <div class="card-body">
                        <div class="row">
                            @include('coordination.inventory._form')
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-info" type="submit">Guardar</button>
                        <a href="{{ route('coordination.inventory.index') }}" class="btn btn-danger">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-4">
            @include('coordination.inventory.location_form')
        </div>
    </div>
</div>
@endsection
