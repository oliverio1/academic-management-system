@extends('layouts.pdf')

@section('content')
@php
    $logoPath = public_path('images/ula-logo.png');
    $logoBase64 = null;
    if (file_exists($logoPath)) {
        $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
    }

    $days = [
        'monday' => 'L',
        'tuesday' => 'M',
        'wednesday' => 'M',
        'thursday' => 'J',
        'friday' => 'V',
    ];

    $assignment->loadMissing('schedules');
    $activeSchedules = collect($assignment->schedules ?? [])->where('is_active', true);
    $hasDay = [];
    foreach ($days as $dayKey => $label) {
        $hasDay[$dayKey] = $activeSchedules->where('day_of_week', $dayKey)->isNotEmpty();
    }

    $allRows = collect($rowsByUnit)->flatMap(fn ($unit) => $unit['rows'] ?? []);
    $allStarts = $allRows->pluck('start_date')->filter(fn ($d) => $d && $d !== '-')->values();
    $allEnds = $allRows->pluck('end_date')->filter(fn ($d) => $d && $d !== '-')->values();

    $monthRange = '-';
    if ($allStarts->isNotEmpty() && $allEnds->isNotEmpty()) {
        $start = \Carbon\Carbon::createFromFormat('d/m/Y', $allStarts->sort()->first());
        $end = \Carbon\Carbon::createFromFormat('d/m/Y', $allEnds->sort()->last());
        $monthRange = mb_strtoupper($start->translatedFormat('F')) . ' - ' . mb_strtoupper($end->translatedFormat('F'));
    }
@endphp

<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.35; color: #111; }
    .pdf-logo { max-width: 84px; max-height: 36px; }
    .header-table td { border: 1px solid #008b8b; vertical-align: middle; }
    .meta td, .meta th { border: 1px solid #008b8b; padding: 4px; }
    .plan td, .plan th { border: 1px solid #008b8b; padding: 4px; vertical-align: top; }
    .no-border td { border: none !important; }
    .teal { color: #006d6d; }
    .section { background: #e9f7f7; font-weight: bold; text-transform: uppercase; }
    .label { font-weight: bold; }
    .mb-6 { margin-bottom: 6px; }
    .mb-10 { margin-bottom: 10px; }
    .tiny { font-size: 9px; }
    .small { font-size: 9.5px; }
</style>

<table class="header-table mb-6" style="width:100%;">
    <tr>
        <td class="center">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" class="pdf-logo" alt="Logo">
            @endif
        </td>
    </tr>
    <tr>
        <td class="center">
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
        <td class="center label">Ciclo</td>
        <td class="center">{{ $cycle->name ?? '-' }}</td>
    </tr>
</table>

@forelse($rowsByUnit as $unitBlock)
    <table class="meta mb-6">
        <tr>
            <td style="width:28%;"><span class="label">Campo formativo / contenido disciplinar</span></td>
            <td>{{ $unitBlock['unit'] }}</td>
        </tr>
        <tr>
            <td><span class="label">Objetivo / progresion</span></td>
            <td>{{ $unitBlock['objective'] }}</td>
        </tr>
    </table>

    <table class="plan mb-6">
        <thead>
            <tr class="center section">
                <th style="width:17%;">Contenido tematico</th>
                <th style="width:12%;">Apertura</th>
                <th style="width:12%;">Desarrollo</th>
                <th style="width:12%;">Cierre</th>
                <th style="width:11%;">Material didactico</th>
                <th style="width:14%;">Fechas de ejecucion</th>
                <th style="width:12%;">Evaluacion</th>
                <th style="width:10%;">Referencias</th>
            </tr>
        </thead>
        <tbody>
            @forelse($unitBlock['rows'] as $row)
                <tr>
                    <td>
                        <div><strong>{{ $row['topic'] }}</strong></div>
                        <div class="small">{{ $row['subtopics'] }}</div>
                    </td>
                    <td>{{ $row['opening'] }}</td>
                    <td>{{ $row['development'] }}</td>
                    <td>{{ $row['closing'] }}</td>
                    <td>{{ $row['resources'] }}</td>
                    <td class="center"><strong>{{ $row['start_date'] }}</strong><br>{{ $row['end_date'] }}</td>
                    <td>{{ $row['evaluation'] }}</td>
                    <td class="small">{{ $unitBlock['bibliography'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="center">Sin renglones de planeacion en esta unidad.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="meta mb-6">
        <tr>
            <td style="width:24%;"><span class="label">Referencias bibliograficas</span></td>
            <td>{{ $unitBlock['bibliography'] }}</td>
        </tr>
        <tr>
            <td><span class="label">Bibliografia complementaria</span></td>
            <td>{{ $unitBlock['complementary_bibliography'] }}</td>
        </tr>
    </table>

    <table class="meta mb-10">
        <tr>
            <td class="section">Observaciones de la unidad</td>
        </tr>
        <tr>
            <td class="tiny">
                ____________________________________________________________________________________________<br>
                ____________________________________________________________________________________________
            </td>
        </tr>
    </table>
@empty
    <p>No hay planeaciones registradas para generar el formato.</p>
@endforelse

<table class="no-border" style="width:100%; margin-top: 20px;">
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
@endsection
