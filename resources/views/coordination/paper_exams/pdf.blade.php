<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .header { margin-bottom: 12px; }
        .question { margin-bottom: 10px; }
        .sub { margin-left: 14px; }
        .line { border-bottom: 1px solid #777; margin: 6px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $paperExam->title }}</h2>
        <div>Materia: {{ $paperExam->assignment->subject->name ?? 'N/D' }}</div>
        <div>Grupo: {{ $paperExam->assignment->group->name ?? 'N/D' }}</div>
        <div>Duración: {{ $paperExam->duration_minutes ? $paperExam->duration_minutes.' min' : 'N/D' }}</div>
        <div>Instrucciones: {{ $paperExam->instructions ?: 'Sin instrucciones.' }}</div>
        <hr>
    </div>

    @foreach($paperExam->examQuestions as $index => $examQuestion)
        @php $q = $examQuestion->question; @endphp
        <div class="question">
            <strong>{{ $index + 1 }}.</strong> {{ $q->prompt }}
            @if($q->type === 'open')
                <div class="line"></div><div class="line"></div><div class="line"></div>
            @elseif($q->type === 'multiple_choice')
                <div class="sub">
                    @foreach($q->options as $option)
                        <div>( ) {{ $option->option_text }}</div>
                    @endforeach
                </div>
            @elseif($q->type === 'matching')
                <div class="sub">
                    <table width="100%" cellpadding="3" cellspacing="0" border="1">
                        <tr><th>Columna A</th><th>Columna B</th></tr>
                        @foreach($q->matchingPairs as $pair)
                            <tr><td>{{ $pair->left_text }}</td><td>{{ $pair->right_text }}</td></tr>
                        @endforeach
                    </table>
                </div>
            @elseif($q->type === 'fill_blank')
                <div class="line"></div>
            @endif
        </div>
    @endforeach
</body>
</html>

