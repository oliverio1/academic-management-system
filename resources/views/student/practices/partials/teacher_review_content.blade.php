@php
    $hasTeacherReview = $submission->status === 'reviewed'
        || $submission->reviewed_at
        || $submission->score !== null
        || filled($submission->teacher_corrections)
        || filled($submission->teacher_comments)
        || filled($submission->teacher_suggestions);
@endphp

@if($hasTeacherReview)
    <table class="meta">
        <tr>
            <td><strong>Calificación:</strong></td>
            <td>{{ $submission->score !== null ? number_format((float) $submission->score, 1) : '-' }}</td>
        </tr>
        <tr>
            <td><strong>Fecha de revisión:</strong></td>
            <td>{{ optional($submission->reviewed_at)->format('d/m/Y H:i') ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Revisó:</strong></td>
            <td>{{ $submission->reviewedBy->name ?? $practice->teachingAssignment->teacher->user->name ?? '-' }}</td>
        </tr>
    </table>

    @if($submission->teacher_corrections)
        <h3>Correcciones</h3>
        <div class="section math-content" style="white-space: pre-wrap;">{{ $submission->teacher_corrections }}</div>
    @endif

    @if($submission->teacher_comments)
        <h3>Comentarios</h3>
        <div class="section math-content" style="white-space: pre-wrap;">{{ $submission->teacher_comments }}</div>
    @endif

    @if($submission->teacher_suggestions)
        <h3>Sugerencias</h3>
        <div class="section math-content" style="white-space: pre-wrap;">{{ $submission->teacher_suggestions }}</div>
    @endif
@endif
