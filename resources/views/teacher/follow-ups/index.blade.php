@extends('layouts.app')

@section('title', 'Seguimientos')

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
                                <h4>Seguimientos pendientes</h4>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($pendingFollowUps->isEmpty())
                            <div class="alert alert-success">
                                No tienes seguimientos pendientes por atender.
                            </div>
                        @else
                            <div class="list-group mb-4">
                                @foreach($pendingFollowUps as $assignment)
                                    @php
                                        $followUp = $assignment->studentFollowUp;
                                        $student  = $followUp->student;
                                        $days = $followUp->created_at->diffInDays(now());
                                    @endphp

                                    <a href="{{ route('teacher.follow-ups.show', $assignment) }}"
                                    class="list-group-item list-group-item-action">

                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>{{ $student->user->name }}</strong><br>
                                                <small class="text-muted">
                                                    {{ $student->group->name }}
                                                </small>
                                            </div>

                                            <span class="badge badge-warning">
                                                {{ (int) $days }} día{{ (int) $days !== 1 ? 's' : '' }}
                                            </span>
                                        </div>

                                        <div class="mt-2 text-warning small">
                                            Requiere respuesta
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h4>Historial de seguimientos</h4>
                    </div>
                    <div class="card-body">
                        @if($answeredFollowUps->isEmpty())
                            <p class="text-muted">
                                No hay seguimientos contestados.
                            </p>
                        @else
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Alumno</th>
                                        <th>Grupo</th>
                                        <th class="text-center">Respuesta</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($answeredFollowUps as $assignment)
                                        @php
                                            $student = $assignment->studentFollowUp->student;
                                        @endphp
                                        <tr>
                                            <td>{{ $student->user->name }}</td>
                                            <td>{{ $student->group->name }}</td>
                                            <td class="text-center">
                                                <button class="btn btn-outline-secondary btn-sm"
                                                        data-toggle="modal"
                                                        data-target="#responseModal{{ $assignment->id }}">
                                                    Ver respuesta
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @foreach($answeredFollowUps as $assignment)
        @php
            $q = $assignment->response->questionnaire;
        @endphp

        <div class="modal fade"
            id="responseModal{{ $assignment->id }}"
            tabindex="-1"
            role="dialog"
            aria-labelledby="responseModalLabel{{ $assignment->id }}"
            aria-hidden="true">

            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title"
                            id="responseModalLabel{{ $assignment->id }}">
                            Respuesta de seguimiento
                        </h5>
                        <button type="button"
                                class="close"
                                data-dismiss="modal"
                                aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">

                        <p>
                            <strong>Alumno:</strong><br>
                            {{ $assignment->studentFollowUp->student->user->name }}
                        </p>

                        <hr>

                        <p>
                            <strong>Comportamiento conductual:</strong><br>
                            {{ $q['behavior'] ?? '—' }}
                        </p>

                        <p>
                            <strong>Aprovechamiento académico:</strong><br>
                            {{ $q['academic'] ?? '—' }}
                        </p>

                        @if(!empty($q['comments']))
                            <p>
                                <strong>Comentarios adicionales:</strong><br>
                                {{ $q['comments'] }}
                            </p>
                        @endif

                    </div>

                    <div class="modal-footer">
                        <button type="button"
                                class="btn btn-secondary btn-sm"
                                data-dismiss="modal">
                            Cerrar
                        </button>
                    </div>

                </div>
            </div>
        </div>
    @endforeach

@endsection

@section('page_scripts')
@endsection
