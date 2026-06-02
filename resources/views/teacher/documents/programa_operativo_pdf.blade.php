@extends('layouts.pdf')

@section('content')
@php
    $logoPath = public_path('logo.png');
    $logoBase64 = null;
    if (file_exists($logoPath)) {
        $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
    }
@endphp

<style>
    .header-table td { border: none; vertical-align: top; }
    .meta-box td { border: 1px solid #000; padding: 4px; }
    .small { font-size: 10px; line-height: 1.35; }
    .tiny { font-size: 9px; line-height: 1.25; }
    .mt-6 { margin-top: 6px; }
    .mt-10 { margin-top: 10px; }
    .section-title { background: #efefef; font-weight: bold; text-transform: uppercase; }
</style>

<table class="header-table" style="width:100%;">
    <tr>
        <td style="width: 20%;">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" style="height:52px;" alt="Logo">
            @endif
        </td>
        <td class="center" style="width: 80%;">
            <div class="title">PROGRAMA OPERATIVO PARA LA PLANEACION DIDACTICA</div>
            <div class="subtitle">Universidad Latinoamericana - Campus Valle</div>
        </td>
    </tr>
</table>

<table class="meta-box mt-6">
    <tr>
        <td class="section-title" colspan="3">Datos de la institucion</td>
    </tr>
    <tr>
        <td colspan="3"><strong>Institucion:</strong> Universidad Latinoamericana</td>
    </tr>
    <tr>
        <td class="section-title" colspan="3">Datos del profesor</td>
    </tr>
    <tr>
        <td><strong>Docente:</strong> {{ $assignment->teacher->user->name ?? '-' }}</td>
        <td><strong>Fecha de elaboracion:</strong> {{ now()->format('d/m/Y') }}</td>
        <td><strong>Fecha de revision:</strong> __________________</td>
    </tr>
    <tr>
        <td class="section-title" colspan="3">Datos de la asignatura</td>
    </tr>
    <tr>
        <td><strong>Asignatura:</strong> {{ $assignment->subject->name ?? '-' }}</td>
        <td><strong>Grupo:</strong> {{ $assignment->group->name ?? '-' }}</td>
        <td><strong>Ciclo lectivo:</strong> {{ $cycle->name ?? '-' }}</td>
    </tr>
    <tr>
        <td><strong>Modalidad:</strong> {{ $assignment->group->level->modality->name ?? '-' }}</td>
        <td><strong>Parcial:</strong> {{ $partial->name ?? 'Todos' }}</td>
        <td><strong>Horas por semana:</strong> {{ $assignment->subject->hours_per_week ?? '-' }}</td>
    </tr>
    <tr>
        <td colspan="3"><strong>Plan de estudios:</strong> _________________________________</td>
    </tr>
</table>

<table class="meta-box mt-10">
    <tr>
        <td class="section-title">Proposito u objetivo general del curso</td>
    </tr>
    <tr>
        <td class="small">
            A traves de los contenidos de esta asignatura, el estudiantado fortalece el analisis disciplinar
            y aplica los aprendizajes esperados en contextos reales del grupo {{ $assignment->group->name }}.
            Este texto puede ajustarse por coordinacion segun lineamientos DGIRE.
        </td>
    </tr>
</table>

<table class="meta-box mt-10">
    <tr>
        <td class="section-title" colspan="4">Calendarizacion de unidades</td>
    </tr>
    <tr class="center bold">
        <td style="width:8%;">No.</td>
        <td style="width:42%;">Unidad</td>
        <td style="width:25%;">Fecha inicio</td>
        <td style="width:25%;">Fecha termino</td>
    </tr>
    @forelse($units as $idx => $row)
        <tr>
            <td class="center">{{ $idx + 1 }}</td>
            <td>{{ $row['unit'] }}</td>
            <td class="center">{{ $row['start_date'] }}</td>
            <td class="center">{{ $row['end_date'] }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="4" class="center">Sin unidades para calendarizar.</td>
        </tr>
    @endforelse
</table>

<table class="mt-10">
    <thead>
        <tr class="center bold">
            <th style="width:18%;">Unidad</th>
            <th style="width:20%;">Contenido tematico</th>
            <th style="width:22%;">Aprendizaje esperado</th>
            <th style="width:10%;">Inicio</th>
            <th style="width:10%;">Termino</th>
            <th style="width:20%;">Estrategias / evaluacion</th>
        </tr>
    </thead>
    <tbody>
        @forelse($units as $row)
            <tr>
                <td>{{ $row['unit'] }}</td>
                <td>{{ $row['topics'] !== '' ? $row['topics'] : '-' }}</td>
                <td>{{ $row['objective'] }}</td>
                <td class="center">{{ $row['start_date'] }}</td>
                <td class="center">{{ $row['end_date'] }}</td>
                <td>
                    <strong>Recursos:</strong> {{ $row['resources'] }}<br>
                    <strong>Evaluacion:</strong> {{ $row['evaluation'] }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="center">No hay informacion de planeacion para generar el programa operativo.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="meta-box mt-10">
    <tr>
        <td class="section-title">Observaciones</td>
    </tr>
    <tr>
        <td class="tiny">
            ____________________________________________________________________________________________<br>
            ____________________________________________________________________________________________<br>
            ____________________________________________________________________________________________
        </td>
    </tr>
</table>

<table class="meta-box mt-10">
    <tr>
        <td class="section-title">Recursos didacticos, metodologia y bibliografia de consulta</td>
    </tr>
    <tr>
        <td class="small">
            Estrategias sugeridas: trabajo en equipo, investigacion documental, aprendizaje basado en ejercicios,
            lecturas guiadas y resolucion de problemas.
        </td>
    </tr>
    <tr>
        <td class="small">
            @php $bib = collect($units)->pluck('bibliography')->filter()->unique()->implode(' | '); @endphp
            <strong>Bibliografia:</strong> {{ $bib !== '' ? $bib : 'Pendiente de captura.' }}
        </td>
    </tr>
</table>

<table class="header-table" style="width:100%; margin-top: 30px;">
    <tr>
        <td class="center" style="width:50%;">
            _______________________________<br>
            FIRMA DEL DOCENTE
        </td>
        <td class="center" style="width:50%;">
            _______________________________<br>
            FIRMA DEL DIRECTOR TECNICO
        </td>
    </tr>
</table>
@endsection
