<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$docFile = $argv[1] ?? '';
$listFile = $argv[2] ?? '';
$outputFile = $argv[3] ?? '';

if (! is_file($docFile)) {
    fwrite(STDERR, "No se encontro el archivo .doc: {$docFile}\n");
    exit(1);
}

if ($listFile !== '' && ! is_file($listFile)) {
    fwrite(STDERR, "No se encontro LISTAMAESTRA: {$listFile}\n");
    exit(1);
}

if ($outputFile === '') {
    fwrite(STDERR, "Indica ruta de salida .xlsx\n");
    exit(1);
}

$englishAssignments = $listFile !== '' ? readEnglishAssignments($listFile) : [];
$rows = parseDocSchedule($docFile, $englishAssignments);

writeAmsWorkbook($rows, $outputFile);

echo "Archivo generado: {$outputFile}\n";
echo "Filas Datos: " . count($rows) . "\n";
echo "Filas de ingles resueltas con LISTAMAESTRA: " . count(array_filter($rows, fn ($row) => str_contains(normalizeKey($row['materia']), 'ingles') && $row['docente'] !== 'INGLES')) . "\n";

function parseDocSchedule(string $file, array $englishAssignments): array
{
    $bytes = file_get_contents($file);
    $text = mb_convert_encoding($bytes, 'UTF-8', 'UTF-16LE');
    $text = str_replace("\0", '', $text);
    if (! str_contains($text, 'HORA')) {
        $text = iconv('UTF-16LE', 'UTF-8//IGNORE', $bytes);
        $text = str_replace("\0", '', $text);
    }

    $blocks = preg_split('/NIVEL\s+MEDIA\s+SUPERIOR/u', $text) ?: [];
    $rows = [];
    $seen = [];

    if (getenv('AMS_CONVERT_DEBUG')) {
        fwrite(STDERR, 'Texto chars: ' . mb_strlen($text, 'UTF-8') . PHP_EOL);
        fwrite(STDERR, 'Contiene HORA: ' . (str_contains($text, 'HORA') ? 'si' : 'no') . PHP_EOL);
        fwrite(STDERR, 'Bloques: ' . count($blocks) . PHP_EOL);
    }

    foreach ($blocks as $block) {
        $tokens = explode("\x07", $block);
        $horaIndex = findHoraIndex($tokens);
        if (getenv('AMS_CONVERT_DEBUG') && $horaIndex !== null) {
            fwrite(STDERR, 'Hora index: ' . $horaIndex . ' teacher=' . findTeacherName($tokens, $horaIndex) . PHP_EOL);
        }
        if ($horaIndex === null) {
            continue;
        }

        $teacher = findTeacherName($tokens, $horaIndex);
        if ($teacher === '') {
            continue;
        }

        for ($i = $horaIndex + 1; $i < count($tokens); $i++) {
            $time = parseTimeToken($tokens[$i]);
            if ($time === null) {
                continue;
            }
            if (getenv('AMS_CONVERT_DEBUG')) {
                fwrite(STDERR, 'Time found: ' . $time . PHP_EOL);
            }

            $cells = [];
            for ($j = $i + 1; $j < count($tokens); $j++) {
                if (parseTimeToken($tokens[$j]) !== null) {
                    break;
                }

                $cells[] = $tokens[$j];
            }

            if (str_contains(normalizeKey(implode(' ', $cells)), 'descanso')) {
                continue;
            }

            $days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
            foreach ($days as $offset => $day) {
                $cell = $cells[$offset] ?? '';
                foreach (parseClassCell($cell) as $class) {
                    foreach ($class['groups'] as $group) {
                        $subject = $class['subject'];
                        $isEnglish = str_contains(normalizeKey($subject), 'ingles');
                        $sectionRows = $isEnglish
                            ? resolveEnglishRows($englishAssignments, $group, $subject, $teacher)
                            : [['docente' => $teacher, 'seccion' => 'Clase entera', 'modalidad' => 'Grupo completo']];

                        foreach ($sectionRows as $sectionRow) {
                            $row = [
                                'grado' => levelFromGroup($group),
                                'grupo' => $group,
                                'dia' => $day,
                                'periodo' => periodFromTime($time),
                                'hora' => $time,
                                'materia' => $subject,
                                'modalidad' => $sectionRow['modalidad'],
                                'seccion' => $sectionRow['seccion'],
                                'docente' => $sectionRow['docente'],
                                'horas' => 1,
                                'tipo' => 'Teórica',
                            ];

                            $key = implode('|', array_map('normalizeKey', [
                                $row['grupo'],
                                $row['dia'],
                                $row['hora'],
                                $row['materia'],
                                $row['seccion'],
                                $row['docente'],
                            ]));

                            if (! isset($seen[$key])) {
                                $seen[$key] = true;
                                $rows[] = $row;
                            }
                        }
                    }
                }
            }
        }
    }

    usort($rows, function (array $a, array $b): int {
        $group = strnatcasecmp($a['grupo'], $b['grupo']);
        if ($group !== 0) {
            return $group;
        }

        $dayOrder = ['Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3, 'Jueves' => 4, 'Viernes' => 5];
        $day = ($dayOrder[$a['dia']] ?? 99) <=> ($dayOrder[$b['dia']] ?? 99);
        if ($day !== 0) {
            return $day;
        }

        return strcmp($a['hora'], $b['hora']);
    });

    return $rows;
}

function readEnglishAssignments(string $file): array
{
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getSheetByName('ASIGNACIONES_NOMBRES');
    if (! $sheet) {
        return [];
    }

    $assignments = [];
    for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
        $group = trim((string) $sheet->getCell("B{$row}")->getFormattedValue());
        $subject = cleanToken((string) $sheet->getCell("C{$row}")->getValue());
        $teacher = cleanToken((string) $sheet->getCell("D{$row}")->getValue());
        $section = trim((string) $sheet->getCell("G{$row}")->getFormattedValue());

        if ($group === '' || $subject === '' || ! str_contains(normalizeKey($subject), 'ingles')) {
            continue;
        }

        $sectionLabel = match (normalizeKey($section)) {
            'b', '2', 'avanzado' => 'Sección B',
            default => 'Sección A',
        };

        $assignments[normalizeKey($group)][normalizeKey($subject)][] = [
            'docente' => $teacher !== '' ? $teacher : 'INGLES',
            'seccion' => $sectionLabel,
            'modalidad' => 'Dividida',
        ];
    }

    return $assignments;
}

function resolveEnglishRows(array $assignments, string $group, string $subject, string $fallbackTeacher): array
{
    $matches = $assignments[normalizeKey($group)][normalizeKey($subject)] ?? [];
    if ($matches !== []) {
        return $matches;
    }

    return [
        ['docente' => $fallbackTeacher, 'seccion' => 'Sección A', 'modalidad' => 'Dividida'],
        ['docente' => $fallbackTeacher, 'seccion' => 'Sección B', 'modalidad' => 'Dividida'],
    ];
}

function parseClassCell(string $cell): array
{
    $rawCell = str_replace(["\r", "\n", "\t"], ' ', $cell);
    if (cleanToken($rawCell) === '' || str_contains(normalizeKey($rawCell), 'descanso')) {
        return [];
    }

    $parts = preg_split('/\x0B+/u', $rawCell);
    if (! is_array($parts) || count($parts) < 2) {
        return [];
    }

    $groupsText = cleanToken($parts[0]);
    $subject = cleanSubject(implode(' ', array_slice($parts, 1)));

    if ($groupsText === '' || $subject === '') {
        return [];
    }

    $groups = preg_split('/\s*,\s*/', $groupsText) ?: [];
    $groups = array_values(array_filter(array_map(fn ($group) => trim($group), $groups), fn ($group) => preg_match('/^\d+$/', $group)));

    return $groups === [] ? [] : [['groups' => $groups, 'subject' => $subject]];
}

function findHoraIndex(array $tokens): ?int
{
    foreach ($tokens as $index => $token) {
        if (normalizeKey($token) === 'hora') {
            return $index;
        }
    }

    return null;
}

function findTeacherName(array $tokens, int $horaIndex): string
{
    $ignored = [
        'plan',
        'cuatrimestre',
        'horario de docente',
        'nombre del docente asignatura',
        'tipo de',
        'contratacion',
        'carga horaria',
    ];

    for ($i = $horaIndex - 1; $i >= 0; $i--) {
        $token = cleanToken($tokens[$i]);
        $key = normalizeKey($token);
        if ($token === '' || in_array($key, $ignored, true)) {
            continue;
        }

        return normalizePersonName($token);
    }

    return '';
}

function parseTimeToken(string $token): ?string
{
    $token = str_replace(['–', '—', '.'], ['-', '-', ':'], cleanToken($token));
    if (! preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $token, $matches)) {
        return null;
    }

    return normalizeTime($matches[1]) . '-' . normalizeTime($matches[2]);
}

function normalizeTime(string $time): string
{
    [$hour, $minute] = array_map('intval', explode(':', $time));

    return sprintf('%d:%02d', $hour, $minute);
}

function periodFromTime(string $time): int
{
    $starts = [
        '7:00-7:50' => 1,
        '7:50-8:40' => 2,
        '8:40-9:30' => 3,
        '10:00-10:50' => 4,
        '10:50-11:40' => 5,
        '12:10-13:00' => 6,
        '13:00-13:50' => 7,
        '13:50-14:40' => 8,
    ];

    return $starts[$time] ?? 1;
}

function levelFromGroup(string $group): string
{
    return match (substr($group, 0, 1)) {
        '1' => 'PRIMERO',
        '2' => 'SEGUNDO',
        '3' => 'TERCERO',
        '4' => 'CUARTO',
        '5' => 'QUINTO',
        '6' => 'SEXTO',
        default => 'SIN GRADO',
    };
}

function cleanSubject(string $subject): string
{
    $subject = cleanToken($subject);
    $subject = preg_replace('/\s+/u', ' ', $subject) ?? $subject;

    return trim($subject);
}

function cleanToken(string $token): string
{
    $token = str_replace(["\r", "\n", "\t"], ' ', $token);
    $token = preg_replace('/[[:cntrl:]&&[^\v]]/u', ' ', $token) ?? $token;
    $token = preg_replace('/\s+/u', ' ', $token) ?? $token;

    return trim($token);
}

function normalizePersonName(string $name): string
{
    $name = cleanToken($name);
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

    return mb_strtoupper(trim($name), 'UTF-8');
}

function normalizeKey(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $from = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'];
    $to = ['a', 'e', 'i', 'o', 'u', 'u', 'n'];
    $value = str_replace($from, $to, $value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

function writeAmsWorkbook(array $rows, string $outputFile): void
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Datos');

    $headers = ['Grado', 'Grupo', 'Día', 'Periodo', 'Hora', 'Materia', 'Modalidad', 'Sección', 'Docente', 'Horas', 'Tipo'];
    $sheet->fromArray($headers, null, 'A1');

    $rowNumber = 2;
    foreach ($rows as $row) {
        $sheet->fromArray([
            $row['grado'],
            $row['grupo'],
            $row['dia'],
            $row['periodo'],
            $row['hora'],
            $row['materia'],
            $row['modalidad'],
            $row['seccion'],
            $row['docente'],
            $row['horas'],
            $row['tipo'],
        ], null, "A{$rowNumber}");
        $rowNumber++;
    }

    $lastRow = max(1, $rowNumber - 1);
    $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
    $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->freezePane('A2');
    $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");

    foreach (range(1, count($headers)) as $columnIndex) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
    }

    $notes = $spreadsheet->createSheet();
    $notes->setTitle('Notas conversion');
    $notes->fromArray([
        ['Regla', 'Detalle'],
        ['Fuente principal', 'HORARIO FLORIDA 26-3.doc'],
        ['Fuente de apoyo', 'LISTAMAESTRA.xlsx solo para resolver secciones/docentes de inglés cuando fue posible.'],
        ['Grupos juntos', 'Cuando el horario trae grupos como 5110, 5120 se genero una fila por grupo.'],
        ['Tipo de materia', 'Todas las materias se dejaron como Teórica por instrucción del usuario.'],
        ['Inglés', 'Se genero como Dividida. Si LISTAMAESTRA no tenia profesor por sección, se dejaron Sección A y Sección B con el docente del .doc.'],
        ['Importación', 'Revisar este archivo antes de importarlo. No se hizo ningun cambio en la base de datos.'],
    ], null, 'A1');
    $notes->getStyle('A1:B1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
    ]);
    $notes->getColumnDimension('A')->setWidth(22);
    $notes->getColumnDimension('B')->setWidth(110);
    $notes->getStyle('A1:B7')->getAlignment()->setWrapText(true);

    $directory = dirname($outputFile);
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    (new Xlsx($spreadsheet))->save($outputFile);
}
