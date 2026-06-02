@extends('layouts.app')

@section('title', 'Detalle de asistencia prefectura')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="mb-0">Asistencia registrada por prefectura</h4>
                        <small class="text-muted">
                            Alumno: {{ $student->user->name }} |
                            Grupo: {{ $student->group->name ?? '-' }} |
                            Ciclo: {{ $cycle->name }} ({{ $cycle->code }})
                        </small>
                    </div>
                    <a href="{{ route('coordination.students.academic-summary', array_filter($backParams)) }}"
                       class="btn btn-outline-secondary btn-sm">
                        Volver
                    </a>
                </div>
                <div class="card-body table-responsive p-3">
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
                        <table class="table table-bordered table-sm mb-4">
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
                    @endif

                    @if(($dailyMatrixDates ?? collect())->isEmpty())
                        <div class="text-center text-muted py-4">
                            No hay fechas disponibles para mostrar la matriz en el ciclo seleccionado.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
