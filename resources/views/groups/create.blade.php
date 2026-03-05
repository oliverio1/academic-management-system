@extends('layouts.app')

@section('title', 'Nuevo grupo')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">

                <div class="card-header">
                    <h4>Nuevo grupo</h4>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('groups.store') }}">
                        @csrf
                        <div class="row">
                            @include('groups._form')
                        </div>
                    </div>
                    <div class="card-footer">
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
