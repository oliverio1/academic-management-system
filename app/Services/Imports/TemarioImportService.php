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
            $result->addError('Formato invalido. La celda A1 debe contener el nombre de la materia.');
            return $result;
        }

        $rows = $this->extractSingleColumnRows($sheet, $result);
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
                    'type' => 'conceptual',
                    'content' => $row['content'],
                ]);

                $result->addCreated();
            }
        });

        return $result;
    }

    private function extractHeader(Worksheet $sheet, Subject $subject): ?array
    {
        $title = trim((string) $sheet->getCell('A1')->getFormattedValue());
        if ($title === '') {
            return null;
        }

        $courseObjective = trim((string) $sheet->getCell('B1')->getFormattedValue());
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
        ];
    }

    /**
     * @return array<int, array{label:string, level:int, content:string}>
     */
    private function extractSingleColumnRows(Worksheet $sheet, ImportResult $result): array
    {
        $rows = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($row = 4; $row <= $highestRow; $row++) {
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
            $level = $this->inferLevelFromLabel($label);

            if ($content === '') {
                $result->addWarning("Fila {$row}: se omite porque no contiene texto despues de la numeracion.");
                $result->addSkipped();
                continue;
            }

            if ($level === 1 && $unitObjective !== '') {
                $content .= ' | Objetivo especifico: ' . $unitObjective;
            }

            $rows[] = [
                'label' => $label,
                'level' => $level,
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
}
