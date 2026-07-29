@extends('layouts.app')

@section('title', 'Intentos de examen')

@section('content')
<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">{{ $paperExam->title }}</h4>
                <small class="text-muted">{{ $paperExam->assignment->subject->name ?? 'N/D' }} - Grupo {{ $paperExam->assignment->group->name ?? 'N/D' }}</small>
            </div>
            <div>
                <a href="{{ route('teacher.paper-exams.preview', $paperExam) }}" class="btn btn-outline-secondary btn-sm">Vista alumno</a>
                <a href="{{ route('teacher.paper-exams.questions.edit', $paperExam) }}" class="btn btn-outline-success btn-sm">Seleccionar preguntas</a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>Intento</th>
                            <th>Estado</th>
                            <th>Inicio</th>
                            <th>Envio</th>
                            <th>Incidentes</th>
                            <th>Puntaje</th>
                            <th>Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paperExam->attempts->sortByDesc('id') as $attempt)
                            @php
                                $baseTen = ($attempt->score !== null && (float) $attempt->max_score > 0)
                                    ? (((float) $attempt->score / (float) $attempt->max_score) * 10)
                                    : null;
                                $eventCount = $attempt->events->count();
                            @endphp
                            <tr>
                                <td>{{ optional(optional($attempt->student)->user)->name ?? 'N/D' }}</td>
                                <td>{{ $attempt->attempt_number }}</td>
                                <td>
                                    <span class="badge badge-{{ $attempt->status === 'locked' ? 'danger' : (in_array($attempt->status, ['graded', 'submitted']) ? 'success' : 'warning') }}">
                                        {{ $attempt->status }}
                                    </span>
                                    @if($attempt->lock_reason)
                                        <div class="small text-muted">{{ $attempt->lock_reason }}</div>
                                    @endif
                                </td>
                                <td>{{ optional($attempt->started_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                <td>{{ optional($attempt->submitted_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                <td>
                                    <span class="badge badge-{{ $eventCount > 0 ? 'warning' : 'light' }}">{{ $eventCount }}</span>
                                    @if($attempt->locked_at)
                                        <div class="small text-muted">Bloqueo: {{ $attempt->locked_at->format('H:i:s') }}</div>
                                    @endif
                                </td>
                                <td>{{ $baseTen !== null ? number_format($baseTen, 1) : '-' }}</td>
                                <td>
                                    <a href="{{ route('teacher.paper-exams.attempts.review', [$paperExam, $attempt]) }}" class="btn btn-outline-primary btn-sm">
                                        Revisar
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">Sin intentos todavia.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <a href="{{ route('teacher.paper-exams.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
        </div>
    </div>
</div>
@endsection
