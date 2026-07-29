<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 18mm 18mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; line-height: 1.4; }
        h1 { font-size: 18px; margin: 0 0 8px; text-transform: uppercase; }
        .brand-header {
            width: 100%;
            text-align: left;
            margin: 0 0 8mm;
        }
        .brand-header img { max-width: 31.5mm; max-height: 14mm; }
        .ula-wordmark {
            font-family: Arial, sans-serif;
            font-size: 31px;
            line-height: 1;
            font-weight: 900;
            font-style: italic;
            letter-spacing: -2px;
        }
        .brand-name { font-size: 9px; margin-top: 1px; }
        .document-title {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 14mm;
        }
        h2 { font-size: 14px; margin: 18px 0 8px; border-bottom: 1px solid #777; padding-bottom: 3px; }
        h3 { font-size: 12px; margin: 12px 0 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #777; padding: 5px; vertical-align: top; }
        th { background: #f2f2f2; }
        .muted { color: #666; }
        .small { font-size: 9px; }
        .section { margin-bottom: 12px; }
        .question { margin-bottom: 10px; page-break-inside: avoid; }
        .answer { background: #f5f5f5; border: 1px solid #bbb; padding: 5px; margin-top: 5px; }
        .page-break { page-break-before: always; }
        .generic-ula-page {
            margin: 16mm 8mm 10mm;
        }
    </style>
</head>
<body>
@php
    $logoPath = public_path('images/ula-logo.png');
    $logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
    $assignment = $item->assignment;
    $teacherName = $assignment?->teacher?->user?->name ?? 'N/D';
    $subjectName = $assignment?->subject?->name ?? 'N/D';
    $groupName = $assignment?->group?->name ?? 'N/D';
    $cycleName = $item->request?->cycle?->name ?? 'N/D';
    $temario = $payload['temario'] ?? null;
    $temarioTree = $payload['temarioTree'] ?? [];
    $plan = $payload['plan'] ?? null;
    $partials = $payload['partials'] ?? collect();
    $exams = $payload['exams'] ?? collect();
    $evaluationRows = $payload['evaluationRows'] ?? [];
    $planItemsByPartial = $payload['planItemsByPartial'] ?? [];
    $usesGenericUlaTemplate = in_array($item->document_type, ['criterios_evaluacion'], true);
@endphp

@if($usesGenericUlaTemplate)
    <div class="brand-header">
        @if($logoBase64)
            <img src="{{ $logoBase64 }}" alt="ULA">
        @else
            <div class="ula-wordmark">ULA</div>
            <div class="brand-name">Universidad Latinoamericana</div>
        @endif
    </div>

    <div class="generic-ula-page">
        <div class="document-title">{{ $documentLabel }}</div>
@else
    <div class="brand-header">
        @if($logoBase64)
            <img src="{{ $logoBase64 }}" alt="ULA">
        @else
            <div class="ula-wordmark">ULA</div>
            <div class="brand-name">Universidad Latinoamericana</div>
        @endif
    </div>

    <h1>{{ $documentLabel }}</h1>
    <table>
        <tr>
            <th>Profesor</th>
            <td>{{ $teacherName }}</td>
            <th>Materia</th>
            <td>{{ $subjectName }}</td>
        </tr>
        <tr>
            <th>Grupo</th>
            <td>{{ $groupName }}</td>
            <th>Ciclo</th>
            <td>{{ $cycleName }}</td>
        </tr>
        <tr>
            <th>Generado</th>
            <td colspan="3">{{ now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>
@endif

@if($item->document_type === 'temario')
    <h2>Temario</h2>
    @if($temario)
        <p><strong>{{ $temario->title }}</strong></p>
        @if($temario->description)
            <p>{{ $temario->description }}</p>
        @endif
        @foreach($temarioTree as $unit)
            <div class="section">
                <h3>{{ $unit['label'] }} {{ $unit['content'] }}</h3>
                @foreach($unit['children'] as $topic)
                    <p><strong>{{ $topic['label'] }}</strong> {{ $topic['content'] }}</p>
                    @foreach($topic['children'] as $subtopic)
                        <p class="small">{{ $subtopic['label'] }} {{ $subtopic['content'] }} <span class="muted">({{ $subtopic['type'] }})</span></p>
                    @endforeach
                @endforeach
            </div>
        @endforeach
    @else
        <p class="muted">No hay temario cargado para esta materia.</p>
    @endif
@elseif($item->document_type === 'criterios_evaluacion')
    <h2>Criterios de evaluacion</h2>
    @if(count($evaluationRows) > 0)
        <table>
            <thead>
                <tr>
                    <th>Elemento</th>
                </tr>
            </thead>
            <tbody>
            @foreach($evaluationRows as $row)
                <tr><td>{{ $row }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">No hay criterios de evaluacion capturados en la planeacion.</p>
    @endif
@elseif(in_array($item->document_type, ['guias_parciales', 'guia_final', 'guia_extraordinario'], true))
    <h2>Guia de estudio</h2>
    <p>Esta guia se genera a partir del temario y la planeacion vigente. El profesor puede editarla o sustituirla con un PDF propio si necesita mayor detalle.</p>
    @if($item->document_type === 'guias_parciales')
        @foreach($partials as $partial)
            @php $periodItems = collect($planItemsByPartial[$partial->id] ?? []); @endphp
            <h3>{{ $partial->name }}</h3>
            @if($periodItems->isNotEmpty())
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Contenido</th>
                            <th>Actividades sugeridas</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($periodItems as $planItem)
                        <tr>
                            <td>{{ optional($planItem->start_date)->format('d/m/Y') }}</td>
                            <td>{{ optional($planItem->temarioPoint)->label }} {{ optional($planItem->temarioPoint)->content }}</td>
                            <td>{{ $planItem->development ?: $planItem->opening ?: '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                <p class="muted">No hay sesiones de planeacion asociadas a este periodo.</p>
            @endif
        @endforeach
    @else
        @php $periodItems = $plan ? $plan->items : collect(); @endphp
        <h3>{{ $item->document_type === 'guia_extraordinario' ? 'Examen extraordinario' : 'Examen final' }}</h3>
        @if($periodItems->isNotEmpty())
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Contenido</th>
                        <th>Actividades sugeridas</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($periodItems as $planItem)
                    <tr>
                        <td>{{ optional($planItem->start_date)->format('d/m/Y') }}</td>
                        <td>{{ optional($planItem->temarioPoint)->label }} {{ optional($planItem->temarioPoint)->content }}</td>
                        <td>{{ $planItem->development ?: $planItem->opening ?: '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="muted">No hay planeacion capturada para generar la guia.</p>
        @endif
    @endif
@elseif($item->document_type === 'cuadernillo_actividades')
    <h2>Cuadernillo de actividades</h2>
    <p>Actividades propuestas para trabajo asincronico con base en la planeacion capturada.</p>
    @if($plan && $plan->items->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>Sesion</th>
                    <th>Contenido</th>
                    <th>Actividad</th>
                    <th>Producto</th>
                </tr>
            </thead>
            <tbody>
            @foreach($plan->items as $planItem)
                <tr>
                    <td>{{ $planItem->position }}</td>
                    <td>{{ optional($planItem->temarioPoint)->label }} {{ optional($planItem->temarioPoint)->content }}</td>
                    <td>{{ $planItem->development ?: $planItem->opening ?: '-' }}</td>
                    <td>{{ $planItem->evaluation ?: $planItem->closing ?: '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">No hay planeacion capturada para generar el cuadernillo.</p>
    @endif
@elseif($item->document_type === 'examenes')
    <h2>Examenes con respuestas</h2>
    @if($exams->isNotEmpty())
        @foreach($exams as $exam)
            <div class="{{ $loop->first ? '' : 'page-break' }}">
                <h2>{{ $exam->title }}</h2>
                <p><strong>Periodo:</strong> {{ $exam->partial?->name ?? 'N/D' }}</p>
                <p><strong>Duracion:</strong> {{ $exam->duration_minutes ? $exam->duration_minutes . ' min' : 'N/D' }}</p>
                <p><strong>Instrucciones:</strong> {{ $exam->instructions ?: 'Sin instrucciones.' }}</p>

                @foreach($exam->examQuestions as $index => $examQuestion)
                    @php $question = $examQuestion->question; @endphp
                    <div class="question">
                        <strong>{{ $index + 1 }}.</strong> {{ $question->prompt }}
                        <div class="small muted">Tipo: {{ $question->type_label }} | Valor: {{ $examQuestion->points_override ?? $question->points }}</div>

                        @if($question->type === 'multiple_choice')
                            @foreach($question->options as $option)
                                <div>{{ $option->is_correct ? '(*)' : '( )' }} {{ $option->option_text }}</div>
                            @endforeach
                            <div class="answer"><strong>Respuesta:</strong> {{ optional($question->options->firstWhere('is_correct', true))->option_text ?: 'N/D' }}</div>
                        @elseif($question->type === 'matching')
                            <table>
                                <thead><tr><th>Columna A</th><th>Respuesta</th></tr></thead>
                                <tbody>
                                @foreach($question->matchingPairs as $pair)
                                    <tr><td>{{ $pair->left_text }}</td><td>{{ $pair->right_text }}</td></tr>
                                @endforeach
                                </tbody>
                            </table>
                        @elseif($question->type === 'fill_blank')
                            <div class="answer">
                                <strong>Respuesta:</strong>
                                {{ $question->fillBlanks->pluck('expected_answer')->filter()->implode('; ') ?: 'N/D' }}
                            </div>
                        @else
                            <div class="answer"><strong>Respuesta abierta:</strong> Revisar con rubrica o criterio docente.</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    @else
        <p class="muted">No hay examenes capturados para esta materia, grupo y ciclo.</p>
    @endif
@else
    <p class="muted">Este tipo de documento se entrega mediante carga manual.</p>
@endif

@if($usesGenericUlaTemplate)
    </div>
@endif
</body>
</html>
