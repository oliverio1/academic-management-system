<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 1cm; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #000; }
        .title { text-align: center; font-weight: 700; font-size: 13px; margin-bottom: 8px; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .meta-table td { border: none; padding: 2px 4px; vertical-align: top; }
        .meta-label { font-weight: 700; }
        .line { display: inline-block; min-width: 120px; border-bottom: 1px solid #000; padding: 0 4px 1px; }
        table.grid { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 4px; font-size: 10px; }
        table.grid th { background: #f2f2f2; text-align: center; }
        .center { text-align: center; }
        .obs { margin-top: 8px; font-size: 10px; }
        .obs-line { border-bottom: 1px solid #000; height: 18px; }
    </style>
</head>
<body>
    <div class="title">KARDEX DE PROFESORES PARA EL CONTROL DEL AVANCE DEL PROGRAMA OPERATIVO</div>

    <table class="meta-table">
        <tr>
            <td><span class="meta-label">Nombre del profesor:</span> <span class="line">{{ $assignment->teacher->user->name ?? '-' }}</span></td>
            <td><span class="meta-label">Ciclo:</span> <span class="line">{{ $schoolCycle->name ?? ($period->name ?? '-') }}</span></td>
            <td><span class="meta-label">Asignatura:</span> <span class="line">{{ $assignment->subject->name ?? '-' }}</span></td>
        </tr>
        <tr>
            <td><span class="meta-label">Grupo:</span> <span class="line">{{ $assignment->group->name ?? '-' }}</span></td>
            <td><span class="meta-label">Horario:</span> <span class="line">{{ $scheduleText !== '' ? $scheduleText : '-' }}</span></td>
            <td><span class="meta-label">Clave:</span> <span class="line">&nbsp;</span></td>
        </tr>
        <tr>
            <td><span class="meta-label">Salon:</span> <span class="line">&nbsp;</span></td>
            <td><span class="meta-label">Dia:</span> <span class="line">{{ $days !== '' ? $days : '-' }}</span></td>
            <td></td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 5%;">No.</th>
                <th style="width: 10%;">No. Hora</th>
                <th style="width: 10%;">Fecha D/M/A</th>
                <th style="width: 14%;">Unidad</th>
                <th style="width: 22%;">Tema(s)</th>
                <th style="width: 24%;">Subtema(s)</th>
                <th style="width: 8%;">Firma</th>
                <th style="width: 12%;">Revision DT<br>(Fecha y firma)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="center">{{ $row['num'] }}</td>
                    <td class="center">{{ $row['hour'] }}</td>
                    <td class="center">{{ $row['date'] }}</td>
                    <td>{{ $row['unit'] }}</td>
                    <td>{{ $row['topic'] }}</td>
                    <td>{{ $row['subtopic'] }}</td>
                    <td></td>
                    <td></td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="center">No hay actividades registradas en sesiones para el periodo actual.</td>
                </tr>
            @endforelse
            @for($i = 0; $i < max(0, 10 - count($rows)); $i++)
                <tr>
                    <td>&nbsp;</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="obs"><strong>Observaciones:</strong></div>
    <div class="obs-line"></div>
    <div class="obs-line"></div>
    <div class="obs-line"></div>
</body>
</html>

