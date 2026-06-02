@extends('layouts.app')

@section('title', 'Nueva suspension')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            @if($errors->any())
                <div class="alert alert-danger">
                    Revisa la informacion del formulario.
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Registrar suspension</h4>
                    <small class="text-muted">
                        Ciclo activo: {{ $activeCycle->name ?? 'No definido' }}
                    </small>
                </div>
                <form method="POST" action="{{ route('coordination.suspensions.store') }}">
                    @csrf
                    <div class="card-body">
                        @if($groups->isEmpty())
                            <div class="alert alert-warning">
                                No hay grupos activos asociados al ciclo escolar activo.
                            </div>
                        @endif
                        @include('coordination.suspensions._form', ['suspension' => null])
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <a href="{{ route('coordination.suspensions.index') }}" class="btn btn-secondary">Volver</a>
                        <button class="btn btn-primary">Guardar suspension</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
