@extends('layouts.app')

@section('title', 'Editar suspension')

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
                    <h4 class="mb-0">Editar suspension</h4>
                </div>
                <form method="POST" action="{{ route('coordination.suspensions.update', $suspension) }}">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        @include('coordination.suspensions._form', ['suspension' => $suspension])
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <a href="{{ route('coordination.suspensions.index') }}" class="btn btn-secondary">Volver</a>
                        <button class="btn btn-primary">Actualizar suspension</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

