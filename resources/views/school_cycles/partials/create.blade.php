@extends('layouts.app')

@section('title', 'Nuevo parcial')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4>Nuevo parcial para {{ $schoolCycle->name }}</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('school-cycles.partials.store', $schoolCycle) }}">
                        @csrf
                        <div class="row">
                            @include('school_cycles.partials._form')
                        </div>
                        <button class="btn btn-primary">Guardar</button>
                        <a href="{{ route('school-cycles.partials.index', $schoolCycle) }}" class="btn btn-secondary">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
