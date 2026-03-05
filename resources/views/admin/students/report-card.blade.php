@extends('layouts.app')

@section('title', 'Boleta del alumno')

@section('content')
<div class="card">
    <div class="card-body">

        {{-- ENCABEZADO INSTITUCIONAL --}}
        <div class="mb-2 font-weight-bold">
            09-046 UNIVERSIDAD LATINOAMERICANA - CAMPUS VALLE
        </div>

        {{-- DATOS GENERALES --}}
        <div class="mb-1">
            <strong>Matrícula:</strong> {{ $student->enrollment_number ?? '—' }}
            &nbsp;&nbsp;
            <strong>Nombre del alumno:</strong> {{ $student->user->name }}
        </div>

        <div class="mb-3">
            <strong>Grupo:</strong> {{ $student->group->name ?? '—' }}
            &nbsp;&nbsp;
            <strong>Ciclo escolar:</strong> {{ now()->year }}
            &nbsp;&nbsp;
            <strong>Grado escolar:</strong> {{ $student->group->grade ?? '—' }}
        </div>

        {{-- TABLA PRINCIPAL --}}
        <table class="table table-bordered table-sm">
            <thead>
                <tr class="text-center">
                    <th rowspan="2">NRC</th>
                    <th rowspan="2">Asignatura</th>

                    @foreach($periods as $period)
                        <th colspan="2">{{ $period->name }}</th>
                    @endforeach

                    <th colspan="2">Promedio</th>
                </tr>
                <tr class="text-center">
                    @foreach($periods as $period)
                        <th>Calif.</th>
                        <th>Asist.</th>
                    @endforeach
                    <th>Calif.</th>
                    <th>Asist.</th>
                </tr>
            </thead>
            <tbody>

                {{-- FILAS POR MATERIA --}}
                @foreach($subjects as $subject)
                    <tr>
                        <td class="text-center">
                            {{ $report[$subject->id]['nrc'] ?? '—' }}
                        </td>

                        <td>
                            {{ $report[$subject->id]['name'] }}
                        </td>

                        @foreach($periods as $period)
                            <td class="text-center">
                                {{ $report[$subject->id]['periods'][$period->id]['average'] ?? '---' }}
                            </td>
                            <td class="text-center">
                                @php
                                    $att = $report[$subject->id]['periods'][$period->id]['attendance'] ?? null;
                                @endphp
                                {{ $att !== null ? $att.'%' : '---' }}
                            </td>
                        @endforeach

                        <td class="text-center font-weight-bold">
                            {{ $report[$subject->id]['final']['average'] ?? '---' }}
                        </td>
                        <td class="text-center font-weight-bold">
                            @php
                                $finalAtt = $report[$subject->id]['final']['attendance'] ?? null;
                            @endphp
                            {{ $finalAtt !== null ? $finalAtt.'%' : '---' }}
                        </td>
                    </tr>
                @endforeach

                {{-- FILA PROMEDIO GENERAL --}}
                <tr class="font-weight-bold bg-light">
                    <td colspan="2" class="text-right">
                        PROMEDIO
                    </td>

                    @foreach($periods as $period)
                        <td class="text-center">
                            {{ $periodAverages[$period->id]['average'] ?? '---' }}
                        </td>
                        <td class="text-center">
                            @php
                                $pAtt = $periodAverages[$period->id]['attendance'] ?? null;
                            @endphp
                            {{ $pAtt !== null ? $pAtt.'%' : '---' }}
                        </td>
                    @endforeach

                    <td class="text-center">
                        {{ $generalAverage ?? '---' }}
                    </td>
                    <td class="text-center">
                        {{ $generalAttendance !== null ? $generalAttendance.'%' : '---' }}
                    </td>
                </tr>

            </tbody>
        </table>

        {{-- OBSERVACIONES --}}
        <div class="mt-4">
            <strong>Observaciones:</strong>
            <div style="height:60px;"></div>
        </div>

        {{-- FIRMAS --}}
        <div class="row mt-5 text-center">
            <div class="col-6">
                FIRMA DE TUTOR
            </div>
            <div class="col-6">
                DIRECTOR ACADÉMICO<br>
                Miriam Paola Pérez Luna
            </div>
        </div>

        {{-- ACCIONES --}}
        <div class="mt-4 text-right d-print-none">
            <a href="{{ url()->previous() }}" class="btn btn-secondary btn-sm">
                Volver
            </a>
        </div>
        <a href="{{ route('coordination.students.report-card.pdf', $student) }}"
            class="btn btn-danger btn-sm">
            <i class="fas fa-file-pdf"></i> Descargar PDF
        </a>

    </div>
</div>
@endsection
