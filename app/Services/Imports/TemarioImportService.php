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

        $header = $this->extractHeader($sheet, $subject, $file->getClientOriginalName());
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
            $this->updateSubjectMetadata($subject, $header['metadata']);

            $normalizedTitle = preg_replace('/\s+/u', ' ', trim((string) $header['title']));
            $programKey = trim((string) ($header['program_key'] ?? ''));
            $temarioQuery = $subject->temarios()
                ->whereRaw('LOWER(TRIM(title)) = ?', [mb_strtolower($normalizedTitle, 'UTF-8')]);

            if ($programKey !== '') {
                $temarioQuery->where(function ($query) use ($programKey) {
                    $query->where('program_key', $programKey)
                        ->orWhereNull('program_key');
                });
            }

            $temario = $temarioQuery
                ->orderByRaw('CASE WHEN program_key = ? THEN 0 ELSE 1 END', [$programKey])
                ->first();

            if (! $temario) {
                $temario = $subject->temarios()->make();
            }

            $wasExisting = $temario->exists;
            $temario->title = $normalizedTitle;
            $temario->description = $header['description'];
            $temario->general_objective = $header['general_objective'];
            $temario->program_key = $programKey !== '' ? $programKey : null;
            $temario->area = $header['area'];
            $temario->area_label = $header['area_label'];
            $temario->weekly_hours = $header['weekly_hours'];
            $temario->annual_hours = $header['annual_hours'];
            $temario->source_filename = $header['source_filename'];
            $temario->save();

            if ($wasExisting) {
                $result->addUpdated();
            } else {
                $result->addCreated();
            }

            $temario->points()->delete();

            $parentsByKey = [];
            foreach (array_values($rows) as $index => $row) {
                $point = $temario->points()->create([
                    'parent_id' => $this->parentIdForKey($row['sort_key'], $parentsByKey),
                    'position' => $index + 1,
                    'label' => $row['label'],
                    'sort_key' => $row['sort_key'],
                    'level' => $row['level'],
                    'type' => $row['type'],
                    'title' => $row['title'],
                    'objective' => $row['objective'],
                    'hours' => $row['hours'],
                    'content' => $row['content'],
                ]);

                if ($row['sort_key'] !== '') {
                    $parentsByKey[$row['sort_key']] = $point->id;
                }

                $result->addCreated();
            }
        });

        return $result;
    }

    private function extractHeader(Worksheet $sheet, Subject $subject, ?string $sourceFilename = null): ?array
    {
        $firstLabel = $this->normalizedText((string) $sheet->getCell('A1')->getFormattedValue());
        $title = trim((string) $sheet->getCell('A1')->getFormattedValue());
        $courseObjective = trim((string) $sheet->getCell('B1')->getFormattedValue());
        $startRow = 4;
        $metadata = [];

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

                $value = trim((string) $sheet->getCellByColumnAndRow(2, $row)->getFormattedValue());
                if ($label !== '' && $value !== '') {
                    $metadata[$label] = $value;
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

        foreach ($metadata as $label => $value) {
            if (str_contains($label, 'credito')) {
                continue;
            }

            $descriptionParts[] = $this->displayMetadataLabel($label) . ': ' . $value;
        }

        if (empty($descriptionParts)) {
            $descriptionParts[] = 'Importado desde formato de temario';
        }

        $descriptionParts[] = 'Materia: ' . ($subject->name ?? ('#' . $subject->id));

        return [
            'title' => $title,
            'description' => implode("\n", $descriptionParts),
            'general_objective' => $courseObjective !== '' ? $courseObjective : null,
            'start_row' => $startRow,
            'metadata' => $metadata,
            'program_key' => $metadata['clave'] ?? null,
            'area' => $this->areaFromFilename($sourceFilename),
            'area_label' => $this->areaLabelFromFilename($sourceFilename),
            'weekly_hours' => $this->metadataInteger($metadata, ['horas por semana']),
            'annual_hours' => $this->metadataInteger($metadata, ['horas al ano', 'horas al a~no']),
            'source_filename' => $sourceFilename,
        ];
    }

    /**
     * @return array<int, array{label:string, sort_key:string, level:int, type:string, title:string, objective:?string, hours:?float, content:string}>
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

            if (preg_match('/^\s*([0-9]+(?:\.[0-9]+)*\.?[a-zA-Z]?\.?)\s+(.+)$/us', $raw, $matches) !== 1) {
                $result->addWarning("Fila {$row}: formato invalido. Debe iniciar con numeracion (ej. 1., 1.1., 1.1.1. o 1.1.a.).");
                $result->addSkipped();
                continue;
            }

            $label = $this->normalizeLabel(trim($matches[1]));
            $content = trim($matches[2]);
            $unitObjective = trim((string) $sheet->getCellByColumnAndRow(2, $row)->getFormattedValue());
            $thirdColumn = trim((string) $sheet->getCellByColumnAndRow(3, $row)->getFormattedValue());
            $fourthColumn = trim((string) $sheet->getCellByColumnAndRow(4, $row)->getFormattedValue());
            $level = $this->inferLevelFromLabel($label);
            $sortKey = $this->labelKey($label);
            $type = $this->normalizePointType($thirdColumn)
                ?? $this->normalizePointType($fourthColumn)
                ?? 'conceptual';
            $hours = $level === 1
                ? ($this->normalizeHours($thirdColumn) ?? $this->normalizeHours($fourthColumn))
                : null;

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
                'sort_key' => $sortKey,
                'level' => $level,
                'type' => $type,
                'title' => $content === '' ? null : preg_replace('/\s*\|\s*Objetivo\s+espec[ií]fico:.+$/uis', '', $content),
                'objective' => $level === 1 && $unitObjective !== '' ? $unitObjective : null,
                'hours' => $hours,
                'content' => $content,
            ];
        }

        return $rows;
    }

    private function inferLevelFromLabel(string $label): int
    {
        $clean = trim($label);
        if (preg_match('/^([0-9]+(?:\.(?:[0-9]+|[a-zA-Z]))*)\.?$/', $clean, $matches) !== 1) {
            return 1;
        }

        $parts = array_values(array_filter(explode('.', $matches[1]), fn ($part) => $part !== ''));
        return max(1, count($parts));
    }

    private function normalizeLabel(string $label): string
    {
        $clean = trim($label);

        if (preg_match('/^([0-9]+(?:\.[0-9]+)*)([a-zA-Z])\.?$/', $clean, $matches) === 1) {
            return $matches[1].'.'.$matches[2].'.';
        }

        return $clean;
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

    private function normalizeHours(string $value): ?float
    {
        $normalized = trim(str_replace(',', '.', $value));
        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function labelKey(string $label): string
    {
        if (preg_match('/([0-9]+(?:\.(?:[0-9]+|[a-zA-Z]))*)/u', $label, $matches) === 1) {
            return rtrim((string) $matches[1], '.');
        }

        return '';
    }

    private function parentIdForKey(string $key, array $parentsByKey): ?int
    {
        $parts = explode('.', $key);
        if (count($parts) <= 1) {
            return null;
        }

        array_pop($parts);
        while (! empty($parts)) {
            $parentKey = implode('.', $parts);
            if (isset($parentsByKey[$parentKey])) {
                return (int) $parentsByKey[$parentKey];
            }
            array_pop($parts);
        }

        return null;
    }

    private function metadataInteger(array $metadata, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($metadata[$key]) && is_numeric($metadata[$key])) {
                return (int) $metadata[$key];
            }
        }

        return null;
    }

    private function areaFromFilename(?string $filename): ?string
    {
        $label = $this->areaLabelFromFilename($filename);
        if (! $label) {
            return null;
        }

        $normalized = $this->normalizedText($label);

        return match ($normalized) {
            'i' => '1',
            'ii' => '2',
            'iii' => '3',
            'iv' => '4',
            'i y ii', 'i ii' => '1,2',
            default => null,
        };
    }

    private function areaLabelFromFilename(?string $filename): ?string
    {
        if (! $filename || preg_match('/\(([^)]+)\)/u', $filename, $matches) !== 1) {
            return null;
        }

        return trim((string) $matches[1]);
    }

    private function updateSubjectMetadata(Subject $subject, array $metadata): void
    {
        $updates = [];

        if (! empty($metadata['clave'])) {
            $updates['subject_key'] = $metadata['clave'];
        }

        if (! empty($metadata['tipo'])) {
            $updates['type'] = $this->normalizeSubjectType($metadata['tipo']) ?? $subject->type;
        }

        if (! empty($metadata['horas por semana']) && is_numeric($metadata['horas por semana'])) {
            $updates['hours_per_week'] = (int) $metadata['horas por semana'];
        }

        $annualHours = $metadata['horas al ano'] ?? $metadata['horas al a~no'] ?? null;
        if (! empty($annualHours) && is_numeric($annualHours)) {
            $updates['annual_hours'] = (int) $annualHours;
        }

        if (! empty($updates)) {
            $subject->update($updates);
        }
    }

    private function normalizeSubjectType(string $value): ?string
    {
        $normalized = str_replace(["'", '`', '´'], '', $this->normalizedText($value));

        if (str_contains($normalized, 'practico') || str_contains($normalized, 'practica')) {
            return Subject::TYPE_THEORETICAL_PRACTICAL;
        }

        if (str_contains($normalized, 'teorico') || str_contains($normalized, 'teorica')) {
            return Subject::TYPE_THEORETICAL;
        }

        return null;
    }

    private function displayMetadataLabel(string $label): string
    {
        return match ($label) {
            'clave' => 'Clave',
            'tipo' => 'Tipo',
            'horas por semana' => 'Horas por semana',
            'horas al ano', 'horas al a~no' => 'Horas al año',
            default => ucfirst($label),
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
