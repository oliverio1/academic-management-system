@extends('layouts.app')

@section('title', 'Edición de entregable')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4>Edición de entregable</h4>
                    <small class="text-muted">
                        {{ $assignment->subject->name }} - Grupo {{ $assignment->group->name }}
                    </small>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('practices.update', $practice) }}">
                        @csrf
                        @method('PUT')

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
