@extends('layouts.app')

@section('title', 'Editar grupo')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">

                <div class="card-header">
                    <h4>Editar grupo</h4>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('groups.update', $group) }}">
                        @csrf
                        @method('PUT')

                        @include('groups._form')

                        <button class="btn btn-primary">Guardar</button>
                        <a href="{{ route('groups.index') }}" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
