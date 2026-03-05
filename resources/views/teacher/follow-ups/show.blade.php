
@extends('layouts.app')

@section('title', 'Modalidades')

@section('content')
    @if(session('info'))
        <div class="alert alert-primary" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>    
    @endif
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-6">
                                <h4 class="mb-1">Seguimiento académico</h4>
                                <small class="text-muted">
                                    Solicitud institucional de Coordinación
                                </small>
                            </div>
                            <div class="col-sm-6">
                                <a href="{{ route('teacher.follow-ups.index') }}" class="btn btn-secondary float-right">Volver</a>
                            </div>
                            <div class="col-md-12">
                                <hr>
                                <strong>
                                    {{ $assignment->studentFollowUp->student->user->name }}
                                </strong><br>
                                <span class="text-muted">
                                    {{ $assignment->studentFollowUp->student->group->name }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        {{-- RESPUESTA DEL PROFESOR --}}
                        @if(is_null($assignment->answered_at))
                            <div class="card mb-4">
                                <div class="card-header">
                                    Respuesta del profesor
                                </div>
                                <div class="card-body">
                                    <form method="POST"
                                        action="{{ route('teacher.follow-ups.respond', $assignment) }}">
                                        @csrf
                                        {{-- COMPORTAMIENTO CONDUCTUAL --}}
                                        <div class="form-group">
                                            <label for="behavior">
                                                Comportamiento conductual
                                            </label>
                                            <textarea name="behavior"
                                                    id="behavior"
                                                    class="form-control"
                                                    rows="3"
                                                    required></textarea>
                                        </div>
                                        {{-- APROVECHAMIENTO ACADÉMICO --}}
                                        <div class="form-group">
                                            <label for="academic">
                                                Aprovechamiento académico
                                            </label>
                                            <textarea name="academic"
                                                    id="academic"
                                                    class="form-control"
                                                    rows="3"
                                                    required></textarea>
                                        </div>
                                        {{-- COMENTARIOS ADICIONALES --}}
                                        <div class="form-group">
                                            <label for="comments">
                                                Comentarios adicionales
                                            </label>
                                            <textarea name="comments"
                                                    id="comments"
                                                    class="form-control"
                                                    rows="3"></textarea>
                                        </div>
                                        <button class="btn btn-primary">
                                            Enviar respuesta
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @else
                            {{-- RESPUESTA YA ENVIADA --}}
                            <div class="card mb-4 border-success">
                                <div class="card-header bg-success text-white">
                                    Respuesta enviada
                                </div>
                                <div class="card-body">
                                    <p class="mb-1">
                                        <strong>Respondido el:</strong>
                                        {{ $assignment->answered_at->format('d/m/Y H:i') }}
                                    </p>
                                    <hr>
                                    <p class="mb-2">
                                        <strong>Cuestionario:</strong><br>
                                        {{ $assignment->response->questionnaire }}
                                    </p>
                                    @if($assignment->response->comments)
                                        <p class="mb-0">
                                            <strong>Comentarios:</strong><br>
                                            {{ $assignment->response->comments }}
                                        </p>
                                    @endif
                                </div>
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