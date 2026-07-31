<?php

namespace App\Services\Imports;

use App\Models\Subject;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TemarioImportService
{
    public function import(UploadedFile $file, Subject $subject): ImportResult
    {
        $result = new ImportResult();
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();

        $header = $this->extractHeader($sheet, $subject);
        if ($header === null) {
            $result->addError('Formato invalido. La celda A1 debe contener el nombre de la materia o la etiqueta "Nombre de la materia" con el valor en B1.');
            return $result;
        }

        $rows = $this->extractSingleColumnRows($sheet, $result, (int) $header['start_row']);
        if (empty($rows)) {
            $result->addWarning('No se encontraron filas validas para importar.');
            return $result;
        }

        DB::transaction(function () use ($subject, $rows, $header, $result) {
            $normalizedTitle = preg_replace('/\s+/u', ' ', trim((string) $header['title']));
            $temario = $subject->temarios()
                ->whereRaw('LOWER(TRIM(title)) = ?', [mb_strtolower($normalizedTitle, 'UTF-8')])
                ->first();

            if (! $temario) {
                $temario = $subject->temarios()->make();
            }

            $wasExisting = $temario->exists;
            $temario->title = $normalizedTitle;
            $temario->description = $header['description'];
            $temario->save();

            if ($wasExisting) {
                $result->addUpdated();
            } else {
                $result->addCreated();
            }

            $temario->points()->delete();

            foreach (array_values($rows) as $index => $row) {
                $temario->points()->create([
                    'position' => $index + 1,
                    'label' => $row['label'],
                    'level' => $row['level'],
                    'type' => $row['type'],
                    'content' => $row['content'],
                ]);

                $result->addCreated();
            }
        });

        return $result;
    }

    private function extractHeader(Worksheet $sheet, Subject $subject): ?array
    {
        $firstLabel = $this->normalizedText((string) $sheet->getCell('A1')->getFormattedValue());
        $title = trim((string) $sheet->getCell('A1')->getFormattedValue());
        $courseObjective = trim((string) $sheet->getCell('B1')->getFormattedValue());
        $startRow = 4;

        if (str_contains($firstLabel, 'nombre de la materia')) {
            $title = trim((string) $sheet->getCell('B1')->getFormattedValue());
            $courseObjective = '';
            $startRow = 2;

            for ($row = 2; $row <= min(20, $sheet->getHighestDataRow()); $row++) {
                $label = $this->normalizedText((string) $sheet->getCellByColumnAndRow(1, $row)->getFormattedValue());
                if (str_contains($label, 'objetivo general')) {
                    $courseObjective = trim((string) $sheet->getCellByColumnAndRow(2, $row)->getFormattedValue());
                    $startRow = $row + 1;
                    break;
                }
            }
        }

        if ($title === '') {
            return null;
        }

        $creditsLabel = trim((string) $sheet->getCell('A2')->getFormattedValue());
        $credits = trim((string) $sheet->getCell('B2')->getFormattedValue());

        $descriptionParts = [];
        if ($courseObjective !== '') {
            $descriptionParts[] = 'Objetivo general: ' . $courseObjective;
        }

        if ($credits !== '') {
            $creditsTitle = $creditsLabel !== '' ? $creditsLabel : 'Creditos';
            $descriptionParts[] = $creditsTitle . ': ' . $credits;
        }

        if (empty($descriptionParts)) {
            $descriptionParts[] = 'Importado desde formato de temario';
        }

        $descriptionParts[] = 'Materia: ' . ($subject->name ?? ('#' . $subject->id));

        return [
            'title' => $title,
            'description' => implode("\n", $descriptionParts),
            'start_row' => $startRow,
        ];
    }

    /**
     * @return array<int, array{label:string, level:int, type:string, content:string}>
     */
    private function extractSingleColumnRows(Worksheet $sheet, ImportResult $result, int $startRow = 4): array
    {
        $rows = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($row = $startRow; $row <= $highestRow; $row++) {
            $raw = trim((string) $sheet->getCellByColumnAndRow(1, $row)->getFormattedValue());
            if ($raw === '') {
                continue;
            }

            if (preg_match('/^\s*([0-9]+(?:\.[0-9]+)*\.?)\s*(.+)$/u', $raw, $matches) !== 1) {
                $result->addWarning("Fila {$row}: formato invalido. Debe iniciar con numeracion (ej. 1., 1.1., 1.1.1.).");
                $result->addSkipped();
                continue;
            }

            $label = trim($matches[1]);
            $content = trim($matches[2]);
            $unitObjective = trim((string) $sheet->getCellByColumnAndRow(2, $row)->getFormattedValue());
            $thirdColumn = trim((string) $sheet->getCellByColumnAndRow(3, $row)->getFormattedValue());
            $fourthColumn = trim((string) $sheet->getCellByColumnAndRow(4, $row)->getFormattedValue());
            $level = $this->inferLevelFromLabel($label);
            $type = $this->normalizePointType($thirdColumn)
                ?? $this->normalizePointType($fourthColumn)
                ?? 'conceptual';

            if ($content === '') {
                $result->addWarning("Fila {$row}: se omite porque no contiene texto despues de la numeracion.");
                $result->addSkipped();
                continue;
            }

            if ($level === 1 && $thirdColumn !== '' && ! $this->normalizePointType($thirdColumn)) {
                $content .= ' | Horas: ' . $thirdColumn;
            }

            if ($level === 1 && $unitObjective !== '') {
                $content .= ' | Objetivo especifico: ' . $unitObjective;
            }

            $rows[] = [
                'label' => $label,
                'level' => $level,
                'type' => $type,
                'content' => $content,
            ];
        }

        return $rows;
    }

    private function inferLevelFromLabel(string $label): int
    {
        $clean = trim($label);
        if (preg_match('/^([0-9]+(?:\.[0-9]+)*)\.?$/', $clean, $matches) !== 1) {
            return 1;
        }

        $parts = array_values(array_filter(explode('.', $matches[1]), fn ($part) => $part !== ''));
        return max(1, count($parts));
    }

    private function normalizePointType(string $value): ?string
    {
        $normalized = $this->normalizedText($value);
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'conceptual' => 'conceptual',
            'procedimental' => 'procedimental',
            'actitudinal' => 'actitudinal',
            'otro', 'otros', 'otra' => 'otro',
            default => null,
        };
    }

    private function normalizedText(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) {
            $value = $converted;
        }

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
