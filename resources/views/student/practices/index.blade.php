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
                    <small class="text-muted">Tareas, practicas y proyectos asignados por tus profesores.</small>
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
                            $status = $studentSubmission?->status ?? 'pending';
                        @endphp
                        <div class="delivery-card mb-3">
                            <div class="delivery-card__main">
                                <div>
                                    <span class="badge badge-info">{{ $practice->kind_label }}</span>
                                    @if($status === 'reviewed')
                                        <span class="badge badge-primary">Revisado</span>
                                    @elseif($status === 'submitted')
                                        <span class="badge badge-success">Enviado</span>
                                    @elseif($status === 'draft')
                                        <span class="badge badge-secondary">Borrador</span>
                                    @elseif($isPastDue)
                                        <span class="badge badge-dark">Vencido</span>
                                    @else
                                        <span class="badge badge-warning">Pendiente</span>
                                    @endif
                                </div>

                                <h5 class="mt-2 mb-1">{{ $practice->title }}</h5>
                                <p class="text-muted mb-2">
                                    {{ $practice->teachingAssignment->subject->name }}
                                    - Profesor: {{ $practice->teachingAssignment->teacher->user->name ?? 'Sin profesor' }}
                                </p>

                                <div class="delivery-card__dates">
                                    <span>Realizacion: {{ optional($practice->realization_date)->format('d/m/Y') ?? '-' }}</span>
                                    <span>Entrega: <strong>{{ optional($practice->due_date)->format('d/m/Y') ?? '-' }}</strong></span>
                                    @if($studentSubmission?->status === 'reviewed')
                                        <span>Calificacion: <strong>{{ number_format((float) $studentSubmission->score, 1) }}</strong></span>
                                    @endif
                                </div>
                            </div>

                            <div class="delivery-card__actions">
                                @if($canCapture)
                                    <a href="{{ route('student.practices.show', $practice) }}"
                                       class="btn btn-primary">
                                        Capturar entrega
                                    </a>
                                @else
                                    <button type="button" class="btn btn-secondary" disabled>
                                        Captura cerrada
                                    </button>
                                @endif
                                <a href="{{ route('student.practices.report', $practice) }}"
                                   class="btn btn-outline-secondary">
                                    Ver entrega
                                </a>
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

<style>
    .delivery-card {
        align-items: center;
        border: 1px solid #dbe3ee;
        border-radius: 6px;
        display: flex;
        gap: 1rem;
        justify-content: space-between;
        padding: 1rem;
    }

    .delivery-card__main {
        min-width: 0;
    }

    .delivery-card__dates {
        color: #6c757d;
        display: flex;
        flex-wrap: wrap;
        font-size: .9rem;
        gap: .5rem 1rem;
    }

    .delivery-card__actions {
        display: flex;
        flex: 0 0 auto;
        flex-wrap: wrap;
        gap: .5rem;
        justify-content: flex-end;
    }

    @media (max-width: 767.98px) {
        .delivery-card {
            align-items: stretch;
            flex-direction: column;
        }

        .delivery-card__actions {
            justify-content: flex-start;
        }
    }
</style>
@endsection
