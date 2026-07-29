<h1 class="math-content">{{ $practice->kind_label }} {{ $practice->number }}: {{ $practice->title }}</h1>

<table class="meta">
    <tr>
        <td><strong>Alumno:</strong></td>
        <td>{{ $student?->user?->name ?? $team->students->map(fn ($student) => $student->user->name)->join(', ') ?? '-' }}</td>
    </tr>
    <tr>
        <td><strong>Matricula:</strong></td>
        <td>{{ $student?->enrollment_number ?? '-' }}</td>
    </tr>
    <tr>
        <td><strong>Equipo:</strong></td>
        <td>{{ $team->name ?? '-' }}</td>
    </tr>
    <tr>
        <td><strong>Materia:</strong></td>
        <td>{{ $practice->teachingAssignment->subject->name }}</td>
    </tr>
    <tr>
        <td><strong>Profesor:</strong></td>
        <td>{{ $practice->teachingAssignment->teacher->user->name ?? '-' }}</td>
    </tr>
    <tr>
        <td><strong>Fecha de realizacion:</strong></td>
        <td>{{ optional($practice->realization_date)->format('d/m/Y') ?? '-' }}</td>
    </tr>
    <tr>
        <td><strong>Fecha de entrega:</strong></td>
        <td>{{ optional($practice->due_date)->format('d/m/Y') ?? '-' }}</td>
    </tr>
</table>

@if($submission->is_resubmission_allowed)
    <h2>Reentrega solicitada</h2>
    <div class="section">
        Fecha limite: {{ optional($submission->resubmission_due_date)->format('d/m/Y') ?? '-' }}
        @if($submission->resubmission_note)
            <br>{{ $submission->resubmission_note }}
        @endif
    </div>
@endif

@foreach($practice->delivery_field_definitions as $field)
    @php
        $fieldId = (string) $field['id'];
        $legacyField = $field['legacy_field'] ?? null;
        $customAnswers = (array) $submission->custom_field_answers;
        $answer = $customAnswers[$fieldId] ?? ($legacyField ? ($submission->{$legacyField} ?? '') : '');
    @endphp
    <h2 class="math-content">{{ $field['label'] }}</h2>
    <div class="section math-content" style="white-space: pre-wrap;">{{ $answer ?: 'Sin capturar' }}</div>
@endforeach

@if(!empty($practice->questionnaire))
    <h2>Preguntas adicionales</h2>
    @foreach($practice->questionnaire as $index => $question)
        @php
            $key = ($question['id'] ?? 'q') . '_' . $index;
            $questionnaireAnswers = (array) $submission->questionnaire_answers;
        @endphp
        <p class="math-content">
            <strong>{{ $question['question'] ?? 'Pregunta' }}</strong><br>
            {{ $questionnaireAnswers[$key] ?? 'Sin respuesta' }}
        </p>
    @endforeach
@endif

@if($submission->attachments->isNotEmpty())
    <h2>Evidencias y archivos</h2>
    <ul>
        @foreach($submission->attachments as $attachment)
            <li>
                @if(request()->routeIs('*.pdf') || app()->runningInConsole())
                    {{ $attachment->original_name }}
                @else
                    <a href="{{ route('practice-submission-attachments.download', $attachment) }}">
                        {{ $attachment->original_name }}
                    </a>
                @endif
                <span>({{ number_format($attachment->size / 1024, 1) }} KB)</span>
            </li>
        @endforeach
    </ul>
@endif
