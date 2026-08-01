<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Temario;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$defaultOutputDir = 'C:\\Users\\LapOne MX\\Desktop\\PREPA 26-27\\TEMARIOS PREPARATORIA EXCEL';
$outputDir = $argv[1] ?? $defaultOutputDir;

if (! is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$temarios = Temario::query()
    ->with([
        'subject.level.modality',
        'points' => fn ($query) => $query->orderBy('position'),
    ])
    ->whereHas('subject.level.modality', fn ($query) => $query->where('name', 'like', '%PREPARATORIA%'))
    ->get()
    ->sortBy(fn (Temario $temario) => sprintf(
        '%s-%s',
        $temario->subject?->level?->name ?? '',
        $temario->subject?->name ?? ''
    ))
    ->values();

$indexRows = [[
    'Archivo',
    'Materia',
    'Nivel',
    'Temario ID',
    'Subject ID',
    'Puntos',
    'Unidades',
]];

$exported = [];

foreach ($temarios as $temario) {
    $subject = $temario->subject;
    if (! $subject) {
        continue;
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('temario');

    $sheet->setCellValue('A1', 'Nombre de la materia');
    $sheet->setCellValue('B1', $subject->name);
    $sheet->setCellValue('A2', 'Clave');
    $sheet->setCellValue('B2', $subject->subject_key);
    $sheet->setCellValue('A3', 'Tipo');
    $sheet->setCellValue('B3', $subject->type);
    $sheet->setCellValue('A4', 'Horas por semana');
    $sheet->setCellValue('B4', $subject->hours_per_week);
    $sheet->setCellValue('A5', 'Horas al año');
    $sheet->setCellValue('B5', $subject->annual_hours);
    $sheet->setCellValue('A6', 'Nivel');
    $sheet->setCellValue('B6', $subject->level?->name);
    $sheet->setCellValue('A8', 'Objetivo general');
    $sheet->setCellValue('B8', extractObjective($temario->description));

    $row = 9;
    foreach ($temario->points as $point) {
        [$content, $unitObjective] = splitUnitObjective((string) $point->content);
        $line = trim(trim((string) $point->label) . ' ' . $content);

        $sheet->setCellValue('A' . $row, $line);

        if ((int) $point->level === 1) {
            $sheet->setCellValue('B' . $row, $unitObjective);
            $sheet->setCellValue('C' . $row, $point->hours);
            $sheet->setCellValue('D' . $row, $point->type ?: 'otro');
        } else {
            $sheet->setCellValue('C' . $row, $point->type ?: 'conceptual');
        }

        $row++;
    }

    styleWorkbook($sheet, $row - 1);

    $filename = sprintf(
        '%s - %s.xlsx',
        filenamePart($subject->level?->name ?: 'SIN NIVEL'),
        filenamePart($subject->name)
    );
    $path = $outputDir . DIRECTORY_SEPARATOR . $filename;
    (new Xlsx($spreadsheet))->save($path);

    $units = $temario->points->where('level', 1)->count();
    $indexRows[] = [
        $filename,
        $subject->name,
        $subject->level?->name,
        $temario->id,
        $subject->id,
        $temario->points->count(),
        $units,
    ];

    $exported[] = $path;
}

$index = new Spreadsheet();
$indexSheet = $index->getActiveSheet();
$indexSheet->setTitle('indice');
$indexSheet->fromArray($indexRows, null, 'A1');
styleIndex($indexSheet, count($indexRows));
(new Xlsx($index))->save($outputDir . DIRECTORY_SEPARATOR . '00_INDICE_TEMARIOS_PREPARATORIA.xlsx');

echo json_encode([
    'output_dir' => $outputDir,
    'exported' => count($exported),
    'index' => $outputDir . DIRECTORY_SEPARATOR . '00_INDICE_TEMARIOS_PREPARATORIA.xlsx',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

function extractObjective(?string $description): string
{
    $text = trim((string) $description);
    if ($text === '') {
        return '';
    }

    if (preg_match('/Objetivo general:\s*(.+?)(?:\R[A-ZÁÉÍÓÚÑ][^\r\n:]{1,60}:|\z)/su', $text, $matches) === 1) {
        return trim((string) $matches[1]);
    }

    return $text;
}

function splitUnitObjective(string $content): array
{
    $content = trim($content);
    if (preg_match('/^(.*?)\s*\|\s*Objetivo\s+espec[ií]fico:\s*(.+)$/uis', $content, $matches) === 1) {
        return [
            trim((string) $matches[1]),
            trim((string) $matches[2]),
        ];
    }

    return [$content, ''];
}

function filenamePart(string $value): string
{
    $value = Str::ascii($value);
    $value = preg_replace('/[\\\\\/:*?"<>|]+/', '-', $value);
    $value = preg_replace('/\s+/', ' ', trim((string) $value));

    return $value !== '' ? $value : 'SIN NOMBRE';
}

function styleWorkbook($sheet, int $lastRow): void
{
    $softFill = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF2F8']];
    $border = ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2EC']]];

    $sheet->getStyle('A1:A8')->applyFromArray(['font' => ['bold' => true]]);
    $sheet->getStyle('A1:B8')->getBorders()->applyFromArray($border);
    $sheet->getStyle('A1:A8')->getFill()->applyFromArray($softFill);

    if ($lastRow >= 9) {
        $sheet->getStyle('A9:D' . $lastRow)->getBorders()->applyFromArray($border);
        $sheet->getStyle('A9:D' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
    }

    foreach (['A' => 78, 'B' => 70, 'C' => 16, 'D' => 18] as $column => $width) {
        $sheet->getColumnDimension($column)->setWidth($width);
    }

    $sheet->freezePane('A9');
}

function styleIndex($sheet, int $lastRow): void
{
    $border = ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2EC']]];
    $sheet->getStyle('A1:G1')->getFont()->setBold(true);
    $sheet->getStyle('A1:G1')->getFill()->applyFromArray([
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1F4E78'],
    ]);
    $sheet->getStyle('A1:G1')->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->getStyle('A1:G' . max(1, $lastRow))->getBorders()->applyFromArray($border);
    $sheet->getStyle('A1:G' . max(1, $lastRow))->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);

    foreach (['A' => 58, 'B' => 48, 'C' => 18, 'D' => 12, 'E' => 12, 'F' => 12, 'G' => 12] as $column => $width) {
        $sheet->getColumnDimension($column)->setWidth($width);
    }

    $sheet->freezePane('A2');
}
