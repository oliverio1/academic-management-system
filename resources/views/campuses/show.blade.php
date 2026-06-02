@extends('layouts.app')

@section('title', 'Detalle de campus')

@section('content')
    <div class="content px-3">
        <div class="row">
            <div class="col-md-8 mt-3">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Campus</h4>
                    </div>
                    <div class="card-body">
                        <p><strong>ID:</strong> {{ $campus->id }}</p>
                        <p><strong>Nombre:</strong> {{ $campus->name }}</p>
                        <p><strong>Codigo:</strong> {{ $campus->code }}</p>
                        <p><strong>Estatus:</strong> {{ $campus->is_active ? 'Activo' : 'Baja' }}</p>
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('campuses.edit', $campus) }}" class="btn btn-warning">Editar</a>
                        <a href="{{ route('campuses.index') }}" class="btn btn-secondary">Volver</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

