<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de entregable</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 15px; margin: 18px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        .meta { margin-bottom: 14px; }
        .meta td { padding: 3px 8px 3px 0; vertical-align: top; }
        .section { white-space: pre-wrap; line-height: 1.35; }
    </style>
</head>
<body>
    @include('student.practices.partials.report_content')
    @php
        $hasTeacherReview = $submission->status === 'reviewed'
            || $submission->reviewed_at
            || $submission->score !== null
            || filled($submission->teacher_corrections)
            || filled($submission->teacher_comments)
            || filled($submission->teacher_suggestions);
    @endphp
    @if($hasTeacherReview)
        <h2>Revisión del profesor</h2>
        @include('student.practices.partials.teacher_review_content')
    @endif
</body>
</html>
