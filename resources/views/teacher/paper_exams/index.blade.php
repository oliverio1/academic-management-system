@extends('layouts.app')

@section('title', 'Exámenes en línea')

@section('content')
<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Exámenes asignados</h4>
                @if($selectedAssignment)
                    <small class="text-muted">
                        {{ $selectedAssignment->subject->name ?? 'N/D' }} - Grupo {{ $selectedAssignment->group->name ?? 'N/D' }}
                    </small>
                @elseif(($selectedFilters['subject_id'] ?? 0) || ($selectedFilters['cycle_partial_id'] ?? 0))
                    <small class="text-muted">
                        Exámenes filtrados por banco de preguntas.
                    </small>
                @endif
            </div>
            <a href="{{ route('teacher.classes.index') }}" class="btn btn-outline-secondary btn-sm">Volver a mis clases</a>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Materia</th>
                        <th>Grupo</th>
                        <th>Preguntas</th>
                        <th>En línea</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($exams as $exam)
                        <tr>
                            <td>{{ $exam->title }}</td>
                            <td>{{ $exam->assignment->subject->name ?? 'N/D' }}</td>
                            <td>{{ $exam->assignment->group->name ?? 'N/D' }}</td>
                            <td>{{ $exam->exam_questions_count }}</td>
                            <td>
                                <span class="badge badge-{{ $exam->is_online_enabled ? 'success' : 'secondary' }}">
                                    {{ $exam->is_online_enabled ? 'Sí' : 'No' }}
                                </span>
                            </td>
                            <td class="text-right">
                                <a class="btn btn-outline-success btn-sm" href="{{ route('teacher.paper-exams.questions.edit', $exam) }}">
                                    Seleccionar preguntas
                                </a>
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('teacher.paper-exams.show', $exam) }}">
                                    Ver intentos
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                No hay exámenes asignados para esta clase.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
