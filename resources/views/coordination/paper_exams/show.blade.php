@extends('layouts.app')

@section('title', 'Detalle de examen')

@section('content')
<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">{{ $paperExam->title }}</h4>
                <small class="text-muted">{{ $paperExam->assignment->subject->name ?? 'N/D' }} - Grupo {{ $paperExam->assignment->group->name ?? 'N/D' }}</small>
            </div>
            <a href="{{ route('coordination.paper-exams.pdf', $paperExam) }}" class="btn btn-danger btn-sm">Descargar PDF</a>
        </div>
        <div class="card-body">
            <p><strong>Instrucciones:</strong> {{ $paperExam->instructions ?: 'Sin instrucciones.' }}</p>
            <hr>
            <h5>Configuración en línea</h5>
            <form method="POST" action="{{ route('coordination.paper-exams.online.update', $paperExam) }}" class="mb-3">
                @csrf
                @method('PUT')
                <div class="form-row">
                    <div class="form-group col-md-2">
                        <label>Intentos máx.</label>
                        <input type="number" class="form-control" min="1" max="10" name="online_max_attempts" value="{{ (int) ($paperExam->online_max_attempts ?: 1) }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label>Disponible desde</label>
                        <input type="datetime-local" class="form-control" name="online_available_from"
                               value="{{ optional($paperExam->online_available_from)->format('Y-m-d\\TH:i') }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label>Disponible hasta</label>
                        <input type="datetime-local" class="form-control" name="online_available_until"
                               value="{{ optional($paperExam->online_available_until)->format('Y-m-d\\TH:i') }}">
                    </div>
                    <div class="form-group col-md-4 d-flex align-items-center">
                        <div class="custom-control custom-checkbox mr-4">
                            <input type="checkbox" class="custom-control-input" id="show_online_{{ $paperExam->id }}" name="is_online_enabled" value="1" {{ $paperExam->is_online_enabled ? 'checked' : '' }}>
                            <label class="custom-control-label" for="show_online_{{ $paperExam->id }}">Habilitar examen en línea</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="show_result_{{ $paperExam->id }}" name="online_show_result" value="1" {{ $paperExam->online_show_result ? 'checked' : '' }}>
                            <label class="custom-control-label" for="show_result_{{ $paperExam->id }}">Mostrar resultado al alumno</label>
                        </div>
                    </div>
                </div>
                <button class="btn btn-primary btn-sm">Guardar configuración</button>
            </form>

            <h6>Intentos registrados</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>Intento</th>
                            <th>Estado</th>
                            <th>Inicio</th>
                            <th>Envío</th>
                            <th>Puntaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paperExam->attempts->sortByDesc('id') as $attempt)
                            <tr>
                                <td>{{ optional(optional($attempt->student)->user)->name ?? 'N/D' }}</td>
                                <td>{{ $attempt->attempt_number }}</td>
                                <td><span class="badge badge-{{ $attempt->status === 'submitted' ? 'success' : 'warning' }}">{{ $attempt->status }}</span></td>
                                <td>{{ optional($attempt->started_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                <td>{{ optional($attempt->submitted_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                <td>
                                    @if($attempt->score !== null && $attempt->max_score !== null)
                                        {{ number_format((float) $attempt->score, 2) }} / {{ number_format((float) $attempt->max_score, 2) }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">Sin intentos todavía.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <ol>
                @foreach($paperExam->examQuestions as $examQuestion)
                    <li class="mb-2">
                        <strong>[{{ $examQuestion->question->type }}]</strong>
                        {{ $examQuestion->question->prompt }}
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</div>
@endsection
