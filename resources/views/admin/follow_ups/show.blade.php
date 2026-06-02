@extends('layouts.app')

@section('title', 'Detalle del seguimiento')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">

                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-start">
                        <h4 class="mb-0">Seguimiento del alumno</h4>
                        <a href="{{ route('coordination.follow-ups.pdf', $followUp) }}"
                           class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-file-pdf mr-1"></i> Descargar PDF
                        </a>
                    </div>
                    <p class="mb-1">
                        <strong>Alumno:</strong>
                        {{ $followUp->student->user->name }}
                    </p>
                    <p class="mb-1">
                        <strong>Grupo:</strong>
                        {{ $followUp->student->group->name }}
                    </p>
                    <p class="mb-0">
                        <strong>Progreso:</strong>
                        {{ $answered }} / {{ $total }} profesores respondieron
                        ({{ $progress }}%)
                    </p>
                </div>

                <div class="card-body">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Profesor</th>
                                <th>Conductual</th>
                                <th>Academico</th>
                                <th>Comentarios</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($followUp->teachers as $assignment)
                                @php
                                    $response = $assignment->response;
                                    $questionnaire = $response?->questionnaire ?? [];

                                    $behavioral = $questionnaire['behavior']
                                        ?? $questionnaire['behavioral_performance']
                                        ?? null;

                                    $academic = $questionnaire['academic']
                                        ?? $questionnaire['academic_performance']
                                        ?? null;

                                    $comments = $response?->comments
                                        ?? ($questionnaire['comments'] ?? null);
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $assignment->teacher->user->name }}</strong>
                                        <br>
                                        @if($assignment->answered_at)
                                            <small class="text-muted">
                                                Respondio: {{ $assignment->answered_at->format('d/m/Y H:i') }}
                                            </small>
                                        @else
                                            <span class="badge bg-secondary">Pendiente</span>
                                        @endif
                                    </td>
                                    <td>{{ $behavioral ?: 'Sin respuesta' }}</td>
                                    <td>{{ $academic ?: 'Sin respuesta' }}</td>
                                    <td>{{ $comments ?: 'Sin comentarios' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No hay profesores asignados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
