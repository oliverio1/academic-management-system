@extends('layouts.app')

@section('title', 'Evaluacion')

@section('content')
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Evaluacion</h4>
                        @if($activeCycle)
                            <small class="text-muted">
                                Ciclo activo: {{ $activeCycle->name }} ({{ $activeCycle->code }})
                            </small>
                        @endif
                    </div>
                    <div class="card-body">
                        @if(!$activeCycle)
                            <div class="alert alert-warning mb-0">
                                No hay un ciclo escolar activo configurado.
                            </div>
                        @elseif($assignments->isEmpty())
                            <div class="alert alert-info mb-0">
                                No tienes materias asignadas en el ciclo activo.
                            </div>
                        @else
                            <div class="row">
                                @foreach($assignments as $assignment)
                                    <div class="col-md-4 mt-3 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body d-flex flex-column">
                                                <h5 class="card-title mb-1">{{ $assignment->subject->name }}</h5>
                                                <p class="text-muted mb-3">({{ $assignment->group->name }})</p>

                                                <p class="mb-3">
                                                    <strong>Actividades:</strong>
                                                    {{ $assignment->activities_count }}
                                                </p>

                                                <a href="{{ route('assignments.show', [$assignment, 'tab' => 'evaluation']) }}" class="btn btn-primary btn-sm mt-auto">
                                                    Ver actividades
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page_css')
@endsection

@section('page_scripts')
@endsection
