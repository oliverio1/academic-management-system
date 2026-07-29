@extends('layouts.app')

@section('title', 'Entregas')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="mb-0">Entregas recibidas</h4>
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
                                <th>Calificacion</th>
                                <th>Archivos</th>
                                <th>Fecha de envio</th>
                                <th>Revision</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rosterRows as $row)
                                @php
                                    $student = $row['student'];
                                    $submission = $row['submission'];
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $student->user->name ?? 'Alumno sin usuario' }}</strong>
                                        <div class="text-muted small">{{ $student->enrollment_number ?? 'Sin matricula' }}</div>
                                    </td>
                                    <td>
                                        @if(! $submission)
                                            <span class="badge badge-light">Pendiente</span>
                                        @elseif($submission->status === 'reviewed' && $submission->is_resubmission_allowed)
                                            <span class="badge badge-warning">Reentrega solicitada</span>
                                        @elseif($submission->status === 'reviewed')
                                            <span class="badge badge-primary">Revisado</span>
                                        @elseif($submission->status === 'submitted')
                                            <span class="badge badge-success">Enviado</span>
                                        @else
                                            <span class="badge badge-secondary">Borrador</span>
                                        @endif
                                    </td>
                                    <td>{{ $submission?->score !== null ? number_format((float) $submission->score, 1) : '-' }}</td>
                                    <td>{{ $submission?->attachments?->count() ?: '-' }}</td>
                                    <td>{{ optional($submission?->submitted_at)->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>{{ optional($submission?->reviewed_at)->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td class="text-right">
                                        @if($submission)
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
                                        @else
                                            <span class="text-muted small">Sin entrega</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        Aun no hay entregas para este entregable.
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
