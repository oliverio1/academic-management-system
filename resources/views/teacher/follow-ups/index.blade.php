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
                        <h3 class="mb-0">Seguimientos</h3>
                    </div>
                    <div class="card-body">
                        @php
                            $followUps = $pendingFollowUps->concat($answeredFollowUps);
                        @endphp

                        @if($followUps->isEmpty())
                            <div class="alert alert-success mb-0">
                                No tienes seguimientos registrados.
                            </div>
                        @else
                            <div class="table-responsive">
                                <table data-datatable="true" class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Alumno</th>
                                            <th>Grupo</th>
                                            <th>Estatus</th>
                                            <th class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($followUps as $assignment)
                                            @php
                                                $followUp = $assignment->studentFollowUp;
                                                $student = $followUp->student;
                                                $isPending = is_null($assignment->answered_at);
                                            @endphp
                                            <tr>
                                                <td>{{ $student->user->name }}</td>
                                                <td>{{ $student->group->name }}</td>
                                                <td>
                                                    @if($isPending)
                                                        <span class="badge badge-warning">Pendiente</span>
                                                    @else
                                                        <span class="badge badge-success">Respondido</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if($isPending)
                                                        <a href="{{ route('teacher.follow-ups.show', $assignment) }}" class="btn btn-primary btn-sm">
                                                            Responder
                                                        </a>
                                                    @else
                                                        <button class="btn btn-outline-secondary btn-sm"
                                                                data-toggle="modal"
                                                                data-target="#responseModal{{ $assignment->id }}">
                                                            Ver respuesta
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
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

