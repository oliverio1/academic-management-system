@extends('layouts.app')

@section('title', 'Exámenes imprimibles')

@section('content')
<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Exámenes imprimibles</h4>
            <div>
                <a href="{{ route('coordination.paper-exams.schedule') }}" class="btn btn-outline-secondary btn-sm">Horarios</a>
                <a href="{{ route('coordination.paper-exams.create') }}" class="btn btn-primary btn-sm">+ Nuevo examen</a>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead><tr><th>Título</th><th>Materia</th><th>Grupo</th><th>Preguntas</th><th></th></tr></thead>
                <tbody>
                    @forelse($exams as $exam)
                        <tr>
                            <td>{{ $exam->title }}</td>
                            <td>{{ $exam->assignment->subject->name ?? 'N/D' }}</td>
                            <td>{{ $exam->assignment->group->name ?? 'N/D' }}</td>
                            <td>{{ $exam->exam_questions_count }}</td>
                            <td class="text-right">
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('coordination.paper-exams.show', $exam) }}">Ver</a>
                                <a class="btn btn-outline-danger btn-sm" href="{{ route('coordination.paper-exams.pdf', $exam) }}">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No hay exámenes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
