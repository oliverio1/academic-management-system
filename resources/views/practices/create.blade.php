@extends('layouts.app')

@section('title', 'Nuevo entregable')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4>Nuevo entregable</h4>
                    <small class="text-muted">
                        {{ $assignment->subject->name }} - Grupo {{ $assignment->group->name }}
                    </small>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('practices.store', $assignment) }}">
                        @csrf

                        @include('practices._form')

                        <button class="btn btn-primary">Guardar</button>
                        <a href="{{ route('practices.index', $assignment) }}" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_scripts')
    @include('practices.partials.questionnaire_script')
@endsection
