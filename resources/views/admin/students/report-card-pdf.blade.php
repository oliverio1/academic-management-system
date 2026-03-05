<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boleta</title>

    <style>
        @page {
            margin: 20px 25px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #000;
        }

        .header {
            font-weight: bold;
            margin-bottom: 8px;
        }

        .student-info {
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .student-info span {
            margin-right: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th, td {
            border: 1px solid #000;
            padding: 4px 5px;
            vertical-align: middle;
        }

        th {
            text-align: center;
            font-weight: bold;
            font-size: 10px;
        }

        td {
            font-size: 10.5px;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .observations {
            margin-top: 20px;
        }

        .observations-box {
            height: 55px;
            border: 1px solid #000;
            margin-top: 4px;
        }

        .signatures {
            margin-top: 35px;
            width: 100%;
        }

        .signature {
            width: 45%;
            text-align: center;
            display: inline-block;
        }

        .signature-line {
            margin-top: 35px;
            border-top: 1px solid #000;
        }
    </style>
</head>
<body>

    {{-- ENCABEZADO --}}
    <div class="header">
        09-046 UNIVERSIDAD LATINOAMERICANA - CAMPUS VALLE
    </div>

    {{-- DATOS DEL ALUMNO --}}
    <div class="student-info">
        <span><strong>Matrícula:</strong> {{ $student->enrollment_number ?? '—' }}</span>
        <span><strong>Nombre del alumno:</strong> {{ $student->user->name }}</span>
        <br>
        <span><strong>Grupo:</strong> {{ $student->group->name }}</span>
        <span><strong>Ciclo escolar:</strong> {{ now()->year }}</span>
        <span><strong>Grado escolar:</strong> {{ $student->group->level->name }}</span>
    </div>

    {{-- TABLA PRINCIPAL --}}
    <table>
        <thead>
            <tr>
                <th rowspan="2">NRC</th>
                <th rowspan="2">Asignatura</th>

                @foreach($periods as $period)
                    <th colspan="2">{{ $period->name }}</th>
                @endforeach

                <th colspan="2">Promedio</th>
            </tr>
            <tr>
                @foreach($periods as $period)
                    <th>Calif.</th>
                    <th>Asist.</th>
                @endforeach
                <th>Calif.</th>
                <th>Asist.</th>
            </tr>
        </thead>

        <tbody>
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

                    <td class="text-center bold">
                        {{ $report[$subject->id]['final']['average'] ?? '---' }}
                    </td>
                    <td class="text-center bold">
                        @php
                            $finalAtt = $report[$subject->id]['final']['attendance'] ?? null;
                        @endphp
                        {{ $finalAtt !== null ? $finalAtt.'%' : '---' }}
                    </td>
                </tr>
            @endforeach

            {{-- PROMEDIO GENERAL --}}
            <tr class="bold">
                <td colspan="2" class="text-right">PROMEDIO</td>

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
    <div class="observations">
        <strong>Observaciones:</strong>
        <div class="observations-box"></div>
    </div>

    {{-- FIRMAS --}}
    <div class="signatures">
        <div class="signature">
            <div class="signature-line"></div>
            FIRMA DE TUTOR
        </div>

        <div class="signature" style="float:right;">
            <div class="signature-line"></div>
            DIRECTOR ACADÉMICO
        </div>
    </div>

</body>
</html>
