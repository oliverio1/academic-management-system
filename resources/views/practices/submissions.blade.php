@extends('layouts.app')

@section('title', 'Entregas')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="mb-0">Entregas</h4>
                        <small class="text-muted">
                            {{ $practice->kind_label }} {{ $practice->number }}: {{ $practice->title }}
                        </small>
                    </div>
                    <a href="{{ route('practices.index', $assignment) }}" class="btn btn-outline-secondary btn-sm">
                        Volver
                    </a>
                </div>

                @if(session('info'))
                    <div class="alert alert-primary m-3 mb-0">
                        {{ session('info') }}
                    </div>
                @endif

                <div class="card-body table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Alumno/equipo</th>
                                <th>Estatus</th>
                                <th>Calificación</th>
                                <th>Fecha de envío</th>
                                <th>Revisión</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($submissions as $submission)
                                @php
                                    $students = $submission->team->students
                                        ->map(fn ($student) => $student->user->name)
                                        ->join(', ');
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $submission->team->name }}</strong>
                                        <div class="text-muted small">{{ $students ?: 'Sin alumnos' }}</div>
                                    </td>
                                    <td>
                                        @if($submission->status === 'reviewed')
                                            <span class="badge badge-primary">Revisado</span>
                                        @elseif($submission->status === 'submitted')
                                            <span class="badge badge-success">Enviado</span>
                                        @else
                                            <span class="badge badge-secondary">Borrador</span>
                                        @endif
                                    </td>
                                    <td>{{ $submission->score !== null ? number_format((float) $submission->score, 1) : '-' }}</td>
                                    <td>{{ optional($submission->submitted_at)->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>{{ optional($submission->reviewed_at)->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('practices.submissions.show', $submission) }}" class="btn btn-sm btn-outline-primary">
                                            Ver
                                        </a>
                                        @if(in_array($submission->status, ['submitted', 'reviewed'], true))
                                            <a href="{{ route('practices.submissions.review', $submission) }}" class="btn btn-sm btn-success">
                                                Revisar/calificar
                                            </a>
                                        @endif
                                        <a href="{{ route('practices.submissions.pdf', $submission) }}" class="btn btn-sm btn-outline-danger">
                                            PDF
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        Aún no hay entregas para este entregable.
                                    </td>
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
