<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boleta</title>
    <style>
        @page { margin: 18px 20px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #000;
        }

        .header-wrap { width: 100%; margin-bottom: 8px; }
        .logo-box { float: left; width: 35%; }
        .title-box { float: right; width: 65%; text-align: right; font-weight: 700; margin-top: 8px; }
        .clear { clear: both; }

        .logo-box img {
            max-width: 120px;
            height: auto;
        }

        .student-grid {
            width: 100%;
            margin: 10px 0 8px;
            border-collapse: collapse;
        }

        .student-grid td {
            border: none;
            padding: 2px 3px;
            vertical-align: top;
        }

        .field-label { font-size: 10px; font-weight: 700; }
        .field-value { font-size: 10.5px; }

        table.main {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        table.main th,
        table.main td {
            border: 1px solid #000;
            padding: 4px 5px;
        }

        table.main th {
            font-size: 10px;
            text-align: center;
            font-weight: 700;
        }

        table.main td { font-size: 10px; }

        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: 700; }
        .subject-col { width: 42%; }
        .nrc-col { width: 6%; }
        .avg-row { background: #f5f5f5; font-weight: 700; }

        .obs {
            margin-top: 14px;
            font-size: 10px;
        }
        .obs-box {
            border-bottom: 1px solid #000;
            height: 24px;
            margin-top: 3px;
        }

        .signatures {
            margin-top: 38px;
            width: 100%;
        }
        .sig {
            width: 45%;
            display: inline-block;
            text-align: center;
            vertical-align: top;
        }
        .sig.right { float: right; }
        .sig-line {
            border-top: 1px solid #000;
            margin-top: 24px;
            padding-top: 6px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="header-wrap">
        <div class="logo-box">
            @php $logoPath = public_path('logo.png'); @endphp
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" alt="ULA">
            @else
                <div style="font-size: 28px; font-weight: 700;">ULA</div>
            @endif
        </div>
        <div class="title-box">
            UNIVERSIDAD LATINOAMERICANA - CAMPUS VALLE
        </div>
        <div class="clear"></div>
    </div>

    <table class="student-grid">
        <tr>
            <td width="14%">
                <div class="field-label">Matrícula</div>
                <div class="field-value">{{ $student->enrollment_number ?? '---' }}</div>
            </td>
            <td width="36%">
                <div class="field-label">Nombre del alumno</div>
                <div class="field-value">{{ $student->user->name }}</div>
            </td>
            <td width="10%">
                <div class="field-label">Grupo</div>
                <div class="field-value">{{ $student->group->name ?? '---' }}</div>
            </td>
            <td width="18%">
                <div class="field-label">Ciclo escolar</div>
                <div class="field-value">{{ optional($periods->first())->start_date?->format('Y') }}-{{ optional($periods->last())->end_date?->format('Y') }}</div>
            </td>
            <td width="22%">
                <div class="field-label">Grado escolar</div>
                <div class="field-value">{{ $student->group->level->name ?? '---' }}</div>
            </td>
        </tr>
    </table>

    <table class="main">
        <thead>
            <tr>
                <th class="nrc-col" rowspan="2">NRC</th>
                <th class="subject-col" rowspan="2">Asignatura</th>
                @foreach($periods as $period)
                    <th colspan="2">{{ $period->name }}</th>
                @endforeach
                <th colspan="2">Promedio</th>
            </tr>
            <tr>
                @foreach($periods as $period)
                    <th>Calificación</th>
                    <th>Asistencia</th>
                @endforeach
                <th>Calificación</th>
                <th>Asistencia</th>
            </tr>
        </thead>
        <tbody>
            @foreach($subjects as $subject)
                <tr>
                    <td class="center">{{ $report[$subject->id]['nrc'] ?? '---' }}</td>
                    <td>{{ $report[$subject->id]['name'] }}</td>
                    @foreach($periods as $period)
                        <td class="center">{{ isset($report[$subject->id]['periods'][$period->id]['average']) ? number_format((float) $report[$subject->id]['periods'][$period->id]['average'], 1) : '---' }}</td>
                        @php $att = $report[$subject->id]['periods'][$period->id]['attendance'] ?? null; @endphp
                        <td class="center">{{ $att !== null ? number_format((float) $att, 0) : '---' }}</td>
                    @endforeach
                    <td class="center bold">{{ isset($report[$subject->id]['final']['average']) ? number_format((float) $report[$subject->id]['final']['average'], 1) : '---' }}</td>
                    @php $fAtt = $report[$subject->id]['final']['attendance'] ?? null; @endphp
                    <td class="center bold">{{ $fAtt !== null ? number_format((float) $fAtt, 0) : '---' }}</td>
                </tr>
            @endforeach

            <tr class="avg-row">
                <td colspan="2">PROMEDIO DEL PARCIAL</td>
                @foreach($periods as $period)
                    <td class="center">{{ isset($periodAverages[$period->id]['average']) ? number_format((float) $periodAverages[$period->id]['average'], 1) : '---' }}</td>
                    @php $pAtt = $periodAverages[$period->id]['attendance'] ?? null; @endphp
                    <td class="center">{{ $pAtt !== null ? number_format((float) $pAtt, 0) : '---' }}</td>
                @endforeach
                <td class="center">{{ $generalAverage !== null ? number_format((float) $generalAverage, 1) : '---' }}</td>
                <td class="center">{{ $generalAttendance !== null ? number_format((float) $generalAttendance, 0) : '---' }}</td>
            </tr>
        </tbody>
    </table>

    <div class="obs">
        <strong>Observaciones:</strong>
        <div class="obs-box"></div>
    </div>

    <div class="signatures">
        <div class="sig">
            <div class="sig-line">FIRMA DEL TUTOR</div>
        </div>
        <div class="sig right">
            <div class="sig-line">
                DIRECTOR ACADÉMICO<br>
                Miriam Paola Pérez Luna
            </div>
        </div>
    </div>
</body>
</html>
