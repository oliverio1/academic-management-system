@extends('layouts.app')

@section('title', 'Revisión de intento')

@section('content')
<div class="content px-3 mt-3">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">{{ $paperExam->title }}</h4>
                <small class="text-muted">
                    Alumno: {{ optional($attempt->student->user)->name ?? 'N/D' }} |
                    Intento #{{ $attempt->attempt_number }} |
                    Estado: {{ $attempt->status }}
                </small>
            </div>
            <a href="{{ route('coordination.paper-exams.show', $paperExam) }}" class="btn btn-outline-secondary btn-sm">Volver</a>
        </div>
        <div class="card-body">
            <p class="mb-0">
                Puntaje actual:
                <strong>{{ number_format((float) ($attempt->score ?? 0), 2) }} / {{ number_format((float) ($attempt->max_score ?? 0), 2) }}</strong>
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('coordination.paper-exams.attempts.grade', [$paperExam, $attempt]) }}">
        @csrf
        @method('PUT')

        @foreach($paperExam->examQuestions as $index => $examQuestion)
            @php
                $question = $examQuestion->question;
                if (!$question) { continue; }
                $answer = $attempt->answers->firstWhere('question_id', $question->id);
                $max = (float) ($answer->max_score ?? $question->points ?? 1);
            @endphp
            <div class="card mb-3">
                <div class="card-header">
                    <strong>{{ $index + 1 }}.</strong> {{ $question->prompt }}
                    <span class="badge badge-info ml-1">{{ $question->type }}</span>
                </div>
                <div class="card-body">
                    @if($question->type === 'multiple_choice')
                        @php
                            $selectedId = (int) data_get($answer?->answer_payload, 'option_id', 0);
                            $correctOption = $question->options->firstWhere('is_correct', true);
                        @endphp
                        <p class="mb-1"><strong>Seleccionada:</strong> {{ optional($question->options->firstWhere('id', $selectedId))->option_text ?: 'Sin respuesta' }}</p>
                        <p class="mb-1"><strong>Correcta:</strong> {{ optional($correctOption)->option_text ?: 'N/D' }}</p>
                    @endif

                    @if($question->type === 'fill_blank')
                        @php $blanks = (array) data_get($answer?->answer_payload, 'blanks', []); @endphp
                        @forelse($question->fillBlanks as $i => $blank)
                            <p class="mb-1">
                                <strong>Respuesta {{ $i + 1 }}:</strong> {{ $blanks[$i] ?? '-' }}
                                <span class="text-muted">(esperada: {{ $blank->expected_answer }})</span>
                            </p>
                        @empty
                            <p class="text-muted mb-1">Sin respuestas esperadas configuradas.</p>
                        @endforelse
                    @endif

                    @if($question->type === 'matching')
                        @php $pairs = (array) data_get($answer?->answer_payload, 'pairs', []); @endphp
                        @foreach($question->matchingPairs as $pair)
                            <p class="mb-1">
                                <strong>{{ $pair->left_text }}:</strong>
                                {{ $pairs[$pair->id] ?? '-' }}
                                <span class="text-muted">(correcto: {{ $pair->right_text }})</span>
                            </p>
                        @endforeach
                    @endif

                    @if($question->type === 'open')
                        <p class="mb-2"><strong>Respuesta del alumno:</strong></p>
                        <div class="border rounded p-2 mb-2" style="white-space: pre-wrap;">{{ data_get($answer?->answer_payload, 'text', 'Sin respuesta') }}</div>
                        <div class="form-group mb-0">
                            <label>Calificación manual (0 a {{ number_format($max, 2) }})</label>
                            <input type="number"
                                   step="0.01"
                                   min="0"
                                   max="{{ $max }}"
                                   class="form-control @error('manual_scores.'.optional($answer)->id) is-invalid @enderror"
                                   name="manual_scores[{{ optional($answer)->id }}]"
                                   value="{{ old('manual_scores.'.optional($answer)->id, (float) ($answer->score ?? 0)) }}">
                            @error('manual_scores.'.optional($answer)->id)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    @if($question->type !== 'open')
                        <p class="mb-0 mt-2">
                            <strong>Puntaje automático:</strong>
                            {{ number_format((float) ($answer->score ?? 0), 2) }} / {{ number_format($max, 2) }}
                        </p>
                    @endif
                </div>
            </div>
        @endforeach

        <button class="btn btn-primary">Guardar calificación</button>
    </form>
</div>
@endsection

