@extends('layouts.app')

@section('title', 'Asistencia del alumno')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Asistencia del alumno</h4>
                    <small class="text-muted">
                        Alumno: {{ $student->user->name }}
                        @if($cycle)
                            | Ciclo: {{ $cycle->name }} ({{ $cycle->code }})
                        @endif
                    </small>
                </div>
                <div class="card-body table-responsive p-3">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="border rounded p-2 h-100">
                                <small class="text-muted d-block">Asistencia global (prefectura)</small>
                                <h5 class="mb-0">
                                    {{ $summary['prefect_percentage'] !== null ? number_format((float) $summary['prefect_percentage'], 1).'%' : '-' }}
                                </h5>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2 h-100">
                                <small class="text-muted d-block">Promedio asistencia por materias</small>
                                <h5 class="mb-0">
                                    {{ $summary['subjects_percentage'] !== null ? number_format((float) $summary['subjects_percentage'], 1).'%' : '-' }}
                                </h5>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2 h-100">
                                <small class="text-muted d-block">Días escolares considerados</small>
                                <h5 class="mb-0">{{ (int) ($summary['school_days'] ?? 0) }}</h5>
                            </div>
                        </div>
                    </div>

                    @php
                        $statusStyles = [
                            'present' => ['label' => 'A', 'class' => 'bg-success text-white'],
                            'absent' => ['label' => 'F', 'class' => 'bg-danger text-white'],
                            'late' => ['label' => 'R', 'class' => 'bg-warning text-dark'],
                            'justified' => ['label' => 'J', 'class' => 'bg-yellow text-dark'],
                            'no-class' => ['label' => '', 'class' => 'bg-secondary'],
                            null => ['label' => '', 'class' => 'bg-light text-muted'],
                        ];
                    @endphp

                    @if(($dailyMatrixDates ?? collect())->isNotEmpty())
                        <div class="mb-3">
                            <small class="text-muted">
                                <span class="badge bg-success text-white">A</span> Asistencia
                                <span class="badge bg-danger text-white ml-2">F</span> Falta
                                <span class="badge bg-warning text-dark ml-2">R</span> Retardo
                                <span class="badge bg-yellow text-dark ml-2">J</span> Justificada
                                <span class="badge bg-secondary ml-2">&nbsp;</span> Sin clase
                            </small>
                        </div>

                        <table class="table table-bordered table-sm mb-0">
                            <thead>
                                <tr>
                                    <th style="min-width: 220px;">Materia / Prefectura</th>
                                    @foreach($dailyMatrixDates as $date)
                                        <th class="text-center" style="min-width: 46px;">
                                            {{ \Carbon\Carbon::parse($date)->format('d/m') }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Prefectura</strong></td>
                                    @foreach($dailyMatrixDates as $date)
                                        @php
                                            $status = $dailyMatrixPrefect[$date] ?? null;
                                            $style = $statusStyles[$status] ?? $statusStyles[null];
                                        @endphp
                                        <td class="text-center {{ $style['class'] }}"><strong>{{ $style['label'] }}</strong></td>
                                    @endforeach
                                </tr>

                                @foreach(($dailyMatrixSubjects ?? collect()) as $subjectRow)
                                    <tr>
                                        <td>{{ $subjectRow['subject_name'] }}</td>
                                        @foreach($dailyMatrixDates as $date)
                                            @php
                                                $status = $dailyMatrixByAssignment[$subjectRow['assignment_id']][$date] ?? 'no-class';
                                                $style = $statusStyles[$status] ?? $statusStyles[null];
                                            @endphp
                                            <td class="text-center {{ $style['class'] }}"><strong>{{ $style['label'] }}</strong></td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-center text-muted py-4">
                            No hay fechas disponibles para mostrar asistencia.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
