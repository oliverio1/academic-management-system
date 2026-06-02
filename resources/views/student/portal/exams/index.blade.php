@extends('layouts.app')

@section('title', 'Mis exámenes')

@section('content')
<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Exámenes en línea</h4>
            <small class="text-muted">Grupo: {{ $student->group->name ?? 'Sin grupo' }}</small>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Examen</th>
                        <th>Materia</th>
                        <th>Parcial</th>
                        <th>Ventana</th>
                        <th>Intentos</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($exams as $exam)
                        <tr>
                            <td>{{ $exam->title }}</td>
                            <td>{{ $exam->assignment->subject->name ?? 'N/D' }}</td>
                            <td>{{ optional($exam->partial)->name ?? '-' }}</td>
                            <td>
                                {{ optional($exam->online_available_from)->format('d/m/Y H:i') ?: 'Sin inicio' }}
                                <br>
                                {{ optional($exam->online_available_until)->format('d/m/Y H:i') ?: 'Sin cierre' }}
                            </td>
                            <td>{{ (int) $exam->attempts_count }} / {{ (int) ($exam->online_max_attempts ?: 1) }}</td>
                            <td>
                                <a href="{{ route('student.exams.show', $exam) }}" class="btn btn-outline-primary btn-sm">Abrir</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No tienes exámenes en línea disponibles.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

