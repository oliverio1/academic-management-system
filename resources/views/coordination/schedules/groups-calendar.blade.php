@extends('layouts.app')

@section('title', 'Grupos')

@section('content')
@php
    $normalizeSubjectName = function (?string $name): string {
        $value = trim((string) $name);
        if ($value === '') {
            return '-';
        }

        // Corrige texto mojibake tÃ­pico (ej. QuÃƒÂ­mica).
        $mojibakeMap = [
            'ÃƒÂ¡' => 'Ã¡', 'ÃƒÂ©' => 'Ã©', 'ÃƒÂ­' => 'Ã­', 'ÃƒÂ³' => 'Ã³', 'ÃƒÂº' => 'Ãº',
            'ÃƒÂ' => 'Ã', 'Ãƒâ€°' => 'Ã‰', 'ÃƒÂ' => 'Ã', 'Ãƒâ€œ' => 'Ã“', 'ÃƒÅ¡' => 'Ãš',
            'ÃƒÂ±' => 'Ã±', 'Ãƒâ€˜' => 'Ã‘', 'ÃƒÂ¼' => 'Ã¼', 'ÃƒÅ“' => 'Ãœ',
        ];
        $value = strtr($value, $mojibakeMap);

        // Correcciones frecuentes sin acento.
        $replacements = [
            'Matematicas' => 'MatemÃ¡ticas',
            'Fisica' => 'FÃ­sica',
            'Quimica' => 'QuÃ­mica',
            'Biologia' => 'BiologÃ­a',
            'Geografia' => 'GeografÃ­a',
            'Metodologia' => 'MetodologÃ­a',
            'Practica' => 'PrÃ¡ctica',
            'Investigacion' => 'InvestigaciÃ³n',
            'Educacion' => 'EducaciÃ³n',
            'Tecnologia' => 'TecnologÃ­a',
            'Comunicacion' => 'ComunicaciÃ³n',
            'Ingles' => 'InglÃ©s',
        ];

        return str_ireplace(array_keys($replacements), array_values($replacements), $value);
    };

    $isLaboratory = function (?string $name): bool {
        return mb_stripos((string) $name, 'laboratorio') !== false;
    };

    $weekdayOptions = collect($dayOptions)
        ->only(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
        ->all();

    $breakSlots = [
        '09:30-10:00',
        '11:40-12:10',
    ];
@endphp

<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Calendario semanal por grupo</h4>
                        <a href="{{ route('coordination.schedules.index') }}" class="btn btn-outline-primary">
                            Ver horarios (lista)
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if(!$activeCycle)
                        <div class="alert alert-warning">
                            No hay ciclo activo configurado.
                        </div>
                    @else
                        <div class="alert alert-info">
                            <strong>Ciclo activo:</strong> {{ $activeCycle->name }} ({{ $activeCycle->code }})
                        </div>
                    @endif

                    @if($groupCalendars->isEmpty())
                        <div class="alert alert-secondary">
                            No hay grupos activos en el ciclo seleccionado.
                        </div>
                    @endif

                    @foreach($groupCalendars as $calendar)
                        <div class="card mb-3 shadow-sm border-0 coordination-inner-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Grupo {{ $calendar['group']->name }}</strong>
                                    <small class="text-muted ml-2">
                                        {{ $calendar['group']->level->name ?? '' }}
                                        @if($calendar['group']->level?->modality?->name)
                                            - {{ $calendar['group']->level->modality->name }}
                                        @endif
                                    </small>
                                </div>
                                <span class="badge badge-primary">{{ $calendar['totalSchedules'] }} horarios</span>
                            </div>
                            <div class="card-body table-responsive p-3">
                                @if($calendar['timeSlots']->isEmpty())
                                    <div class="p-3 text-muted">Este grupo no tiene horarios registrados.</div>
                                @else
                                    @php
                                        $displaySlots = $calendar['timeSlots']
                                            ->pluck('key')
                                            ->merge($breakSlots)
                                            ->unique()
                                            ->sort()
                                            ->values();
                                    @endphp
                                    <table data-datatable="true" class="table table-bordered table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th style="min-width: 95px;">Hora</th>
                                                @foreach($weekdayOptions as $dayKey => $dayLabel)
                                                    <th>{{ $dayLabel }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($displaySlots as $slotKey)
                                                @php
                                                    [$slotStart, $slotEnd] = explode('-', $slotKey);
                                                    $isBreakSlot = in_array($slotKey, $breakSlots, true);
                                                @endphp

                                                @if($isBreakSlot)
                                                    <tr>
                                                        <td><strong>{{ $slotStart }}</strong></td>
                                                        <td colspan="{{ count($weekdayOptions) }}" class="p-0">
                                                            <div class="schedule-break text-center py-2">DESCANSO</div>
                                                        </td>
                                                    </tr>
                                                    @continue
                                                @endif

                                                <tr>
                                                    <td><strong>{{ $slotStart }}</strong></td>
                                                    @foreach($weekdayOptions as $dayKey => $dayLabel)
                                                        @php
                                                            $cells = $calendar['matrix'][$slotKey][$dayKey] ?? collect();
                                                        @endphp
                                                        <td class="{{ $cells->isNotEmpty() ? 'schedule-cell' : '' }}">
                                                            @if($cells->isNotEmpty())
                                                                @foreach($cells as $cell)
                                                                    @php
                                                                        $subjectName = $normalizeSubjectName($cell->assignment->subject->name ?? '');
                                                                        $isLab = $isLaboratory($subjectName);
                                                                        $sectionLabel = (int) ($cell->section_number ?: ($cell->assignment->section_number ?? 1));
                                                                    @endphp
                                                                    <div class="schedule-entry {{ $isLab ? 'schedule-cell-lab' : '' }}"
                                                                        @if(! $isLab)
                                                                            style="{{ 'background-color: ' . subjectColor($cell->assignment->subject_id) . ';' }}"
                                                                        @endif>
                                                                        <div class="{{ $isLab ? 'subject-pill-lab' : '' }}">
                                                                            <strong>{{ $subjectName }}</strong>
                                                                        </div>
                                                                        <div class="small {{ $isLab ? 'text-dark' : 'text-muted' }}">{{ $cell->assignment->teacher->user->name ?? '-' }}</div>
                                                                        <div class="small font-weight-bold">Sección {{ $sectionLabel }}</div>
                                                                        @if($cell->type && mb_strtolower((string) $cell->type) === 'laboratorio')
                                                                            <span class="badge badge-light">{{ $cell->type }}</span>
                                                                        @endif
                                                                    </div>
                                                                @endforeach
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_css')
<style>
    .coordination-inner-card .card-header {
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        padding: .65rem 1rem;
    }

    .coordination-inner-card .card-body {
        background: #fff;
    }

    .schedule-cell {
        vertical-align: middle !important;
        min-width: 190px;
        color: #111827;
    }

    .schedule-cell strong {
        display: block;
        margin-bottom: 0.2rem;
    }

    .schedule-entry {
        border: 1px solid rgba(0, 0, 0, .08);
        border-radius: .35rem;
        padding: .35rem .45rem;
        margin-bottom: .35rem;
    }

    .schedule-entry:last-child {
        margin-bottom: 0;
    }

    .subject-pill-lab {
        display: block;
        width: 100%;
        padding: 0.25rem 0.35rem;
        border-radius: 0.25rem;
        margin-bottom: 0.25rem;
        color: #1f2937;
    }

    .schedule-cell-lab {
        background: repeating-linear-gradient(
            135deg,
            #ffe8a3,
            #ffe8a3 8px,
            #ffd76a 8px,
            #ffd76a 16px
        );
        border: 1px solid #c99700 !important;
    }

    .schedule-break {
        background-color: #f1f3f5;
        color: #6c757d;
        font-weight: bold;
        letter-spacing: 0.1em;
        font-size: 0.8rem;
    }
</style>
@endsection


