<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planeación didáctica</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.35; color: #111; }
        table { width: 100%; border-collapse: collapse; }
        .header-table td { border: 1px solid #008b8b; vertical-align: middle; }
        .meta td, .meta th { border: 1px solid #008b8b; padding: 4px; }
        .plan td, .plan th { border: 1px solid #008b8b; padding: 4px; vertical-align: top; }
        .no-border td { border: none !important; }
        .center { text-align: center; }
        .teal { color: #006d6d; }
        .section { background: #e9f7f7; font-weight: bold; text-transform: uppercase; }
        .label { font-weight: bold; }
        .small { font-size: 9.5px; }
        .tiny { font-size: 9px; }
        .mb-6 { margin-bottom: 6px; }
        .mb-10 { margin-bottom: 10px; }
        .mb-16 { margin-bottom: 16px; }
        .title { font-size: 14px; font-weight: bold; }
        .subtitle { font-size: 11px; }
    </style>
</head>
<body>
@php
    $logoPath = public_path('logo.png');
    $logoBase64 = null;
    if (file_exists($logoPath)) {
        $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
    }

    $assignment->loadMissing('schedules', 'group.level.modality');

    $days = [
        'monday' => 'L',
        'tuesday' => 'M',
        'wednesday' => 'M',
        'thursday' => 'J',
        'friday' => 'V',
    ];

    $activeSchedules = collect($assignment->schedules ?? [])->where('is_active', true);
    $hasDay = [];
    foreach ($days as $dayKey => $label) {
        $hasDay[$dayKey] = $activeSchedules->where('day_of_week', $dayKey)->isNotEmpty();
    }

    $monthRange = '-';
    if (!empty($plan->schoolCycle?->start_date) && !empty($plan->schoolCycle?->end_date)) {
        $start = \Carbon\Carbon::parse($plan->schoolCycle->start_date);
        $end = \Carbon\Carbon::parse($plan->schoolCycle->end_date);
        $monthRange = mb_strtoupper($start->translatedFormat('F')) . ' - ' . mb_strtoupper($end->translatedFormat('F'));
    }

    $rowsByUnit = collect($rows)
        ->groupBy(fn ($row) => (($row['field_training'] ?? '-') . '||' . ($row['objective'] ?? '-')))
        ->map(function ($groupRows) {
            $first = $groupRows->first();
            $fieldTraining = (string) ($first['field_training'] ?? '-');
            $fieldTraining = preg_replace('/\s*\|\s*Objetivo\s+especifico:.*$/ui', '', $fieldTraining) ?: $fieldTraining;

            return [
                'field_training' => trim($fieldTraining),
                'objective' => $first['objective'] ?? '-',
                'rows' => $groupRows->values(),
            ];
        })
        ->values();
@endphp

<table class="header-table mb-10">
    <tr>
        <td style="width: 22%; text-align:center;">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" style="height:52px;" alt="Logo">
            @endif
        </td>
        <td class="center" style="width: 78%;">
            <div class="title teal">FORMATO DE PLANEACION DIDACTICA</div>
            <div class="subtitle">UNIVERSIDAD LATINOAMERICANA - CAMPUS VALLE</div>
        </td>
    </tr>
</table>

<table class="meta mb-6">
    <tr>
        <td style="width:28%;"><span class="label">Docente:</span> {{ $assignment->teacher->user->name ?? '-' }}</td>
        <td style="width:20%;"><span class="label">Grupo:</span> {{ $assignment->group->name ?? '-' }}</td>
        <td class="center label" style="width:12%;">Horario</td>
        <td class="center label" style="width:4%;">L</td>
        <td class="center label" style="width:4%;">M</td>
        <td class="center label" style="width:4%;">M</td>
        <td class="center label" style="width:4%;">J</td>
        <td class="center label" style="width:4%;">V</td>
        <td class="center label" style="width:10%;">Mes(es)</td>
        <td class="center" style="width:10%;">{{ $monthRange }}</td>
    </tr>
    <tr>
        <td><span class="label">Asignatura:</span> {{ $assignment->subject->name ?? '-' }}</td>
        <td><span class="label">Modalidad:</span> {{ $assignment->group->level->modality->name ?? '-' }}</td>
        <td class="center label">Sesion</td>
        <td class="center">{{ $hasDay['monday'] ? 'X' : '' }}</td>
        <td class="center">{{ $hasDay['tuesday'] ? 'X' : '' }}</td>
        <td class="center">{{ $hasDay['wednesday'] ? 'X' : '' }}</td>
        <td class="center">{{ $hasDay['thursday'] ? 'X' : '' }}</td>
        <td class="center">{{ $hasDay['friday'] ? 'X' : '' }}</td>
        <td class="center label">Cuatrimestre</td>
        <td class="center">{{ $cuatrimestre_label ?? '-' }}</td>
    </tr>
</table>

<table class="meta mb-16">
    <tr>
        <td style="width:20%;"><span class="label">Ciclo escolar</span></td>
        <td>{{ $ciclo_escolar_label ?? '-' }}</td>
    </tr>
</table>

@forelse($rowsByUnit as $unit)
    <table class="meta mb-6">
        <tr>
            <td style="width:28%;"><span class="label">Nombre de la unidad</span></td>
            <td>{{ $unit['field_training'] }}</td>
        </tr>
        <tr>
            <td><span class="label">Proposito de la unidad</span></td>
            <td>{{ $unit['objective'] }}</td>
        </tr>
    </table>

    <table class="plan mb-6">
        <thead>
            <tr class="center section">
                <th style="width:23%;">Contenido tematico</th>
                <th style="width:13%;">Apertura</th>
                <th style="width:13%;">Desarrollo</th>
                <th style="width:13%;">Cierre</th>
                <th style="width:14%;">Material didactico</th>
                <th style="width:10%;">Evaluacion</th>
                <th style="width:14%;">Fechas de ejecucion</th>
            </tr>
        </thead>
        <tbody>
            @foreach($unit['rows'] as $row)
                <tr>
                    <td>
                        <div><strong>{{ $row['topic'] }}</strong></div>
                        <div class="small">{{ $row['subtopics'] }}</div>
                    </td>
                    <td>{{ $row['opening'] }}</td>
                    <td>{{ $row['development'] }}</td>
                    <td>{{ $row['closing'] }}</td>
                    <td>{{ $row['resources'] }}</td>
                    <td>{{ $row['evaluation'] }}</td>
                    <td class="center"><strong>{{ $row['start_date'] }} al {{ $row['end_date'] }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@empty
    <table class="plan mb-6">
        <tbody>
            <tr>
                <td class="center">Sin renglones registrados.</td>
            </tr>
        </tbody>
    </table>
@endforelse

<table class="meta mb-6">
    <tr>
        <td style="width:24%;"><span class="label">Referencias</span></td>
        <td>
            @if(!empty($references_list))
                <ol style="margin: 0; padding-left: 16px;">
                    @foreach($references_list as $reference)
                        <li>{{ $reference }}</li>
                    @endforeach
                </ol>
            @else
                -
            @endif
        </td>
    </tr>
</table>

<table class="meta mb-10">
    <tr>
        <td class="section">Observaciones</td>
    </tr>
    <tr>
        <td class="tiny">
            {{ $plan->notes ?: '____________________________________________________________________________________________' }}
        </td>
    </tr>
</table>

<table class="no-border" style="margin-top: 20px;">
    <tr>
        <td class="center" style="width:50%;">
            _______________________________<br>
            FIRMA DEL DOCENTE
        </td>
        <td class="center" style="width:50%;">
            _______________________________<br>
            VO. BO. COORDINACION
        </td>
    </tr>
</table>
</body>
</html>
