@extends('layouts.app')

@section('title', 'Editar entregable')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="mb-0">Editar entregable</h4>
                        <small class="text-muted">
                            {{ $assignment->subject->name }} - Grupo {{ $assignment->group->name }}
                        </small>
                    </div>
                    <a href="{{ route('practices.index', $assignment) }}" class="btn btn-outline-secondary btn-sm">
                        Volver
                    </a>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('practices.update', $practice) }}">
                        @csrf
                        @method('PUT')

                        @include('practices._form')

                        <div class="text-right mt-3">
                            <button class="btn btn-primary">Guardar cambios</button>
                            <a href="{{ route('practices.index', $assignment) }}" class="btn btn-secondary">
                                Cancelar
                            </a>
                        </div>
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
