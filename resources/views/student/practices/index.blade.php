@extends('layouts.app')

@section('title', 'Entregables')

@section('content')
<div class="content px-3">
    @if(session('success'))
        <div class="alert alert-success mt-3">{{ session('success') }}</div>
    @endif

    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Mis entregables</h4>
                    <small class="text-muted">Prácticas, tareas y proyectos asignados por tus profesores.</small>
                </div>
                <div class="card-body">
                    @forelse($practices as $practice)
                        @php
                            $studentSubmission = $practice->submissions
                                ->filter(function ($submission) use ($student) {
                                    return $submission->team
                                        && $submission->team->students->contains('id', $student->id);
                                })
                                ->sortBy(function ($submission) {
                                    return match ($submission->status) {
                                        'reviewed' => 0,
                                        'submitted' => 1,
                                        'draft' => 2,
                                        default => 3,
                                    };
                                })
                                ->first();
                            $isPastDue = $practice->due_date
                                ? $practice->due_date->copy()->endOfDay()->isPast()
                                : false;
                            $canCapture = ! $isPastDue && $studentSubmission?->status !== 'reviewed';
                        @endphp
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <span class="badge badge-info">{{ $practice->kind_label }}</span>
                                        <h5 class="mt-2 mb-1">{{ $practice->title }}</h5>
                                        <p class="text-muted mb-1">
                                            {{ $practice->teachingAssignment->subject->name }}
                                            · Profesor: {{ $practice->teachingAssignment->teacher->user->name ?? 'Sin profesor' }}
                                        </p>
                                        <small>
                                            Realización:
                                            {{ optional($practice->realization_date)->format('d/m/Y') ?? '-' }}
                                            · Entrega:
                                            <strong>{{ optional($practice->due_date)->format('d/m/Y') ?? '-' }}</strong>
                                        </small>
                                        <div class="mt-2">
                                            @if($studentSubmission?->status === 'reviewed')
                                                <span class="badge badge-primary">Revisado</span>
                                                <span class="text-muted small">
                                                    Calificación: {{ number_format((float) $studentSubmission->score, 1) }}
                                                    · {{ optional($studentSubmission->reviewed_at)->format('d/m/Y H:i') }}
                                                </span>
                                            @elseif($studentSubmission?->status === 'submitted')
                                                <span class="badge badge-success">Enviado</span>
                                            @elseif($studentSubmission?->status === 'draft')
                                                <span class="badge badge-secondary">Borrador</span>
                                            @else
                                                <span class="badge badge-warning">Pendiente</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-md-right mt-3 mt-md-0">
                                        @if($canCapture)
                                            <a href="{{ route('student.practices.show', $practice) }}"
                                               class="btn btn-primary">
                                                Capturar reporte
                                            </a>
                                        @else
                                            <button type="button" class="btn btn-secondary" disabled>
                                                Captura cerrada
                                            </button>
                                            <small class="text-muted d-block mt-1">
                                                {{ $studentSubmission?->status === 'reviewed' ? 'Ya fue revisado.' : 'Fecha de entrega vencida.' }}
                                            </small>
                                        @endif
                                        <a href="{{ route('student.practices.report', $practice) }}"
                                           class="btn btn-outline-secondary">
                                            Ver reporte
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-info mb-0">
                            No tienes entregables asignados por ahora.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
