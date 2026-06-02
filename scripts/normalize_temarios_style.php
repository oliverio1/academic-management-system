<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Temario;
use Illuminate\Support\Facades\DB;

function cleanSpaces(string $value): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $value));
}

function parseUnit(string $content): array
{
    $raw = cleanSpaces($content);
    $title = $raw;
    $objective = '';

    if (str_contains($raw, '|')) {
        [$left, $right] = array_pad(explode('|', $raw, 2), 2, '');
        $title = cleanSpaces($left);
        $objective = cleanSpaces((string) preg_replace('/^Objetivo\s+espec[ií]fico\s*[:\-]?\s*/iu', '', cleanSpaces($right)));
    } elseif (preg_match('/^(.*?)\s*Objetivo\s+espec[ií]fico\s*[:\-]\s*(.+)$/ui', $raw, $matches) === 1) {
        $title = cleanSpaces((string) ($matches[1] ?? ''));
        $objective = cleanSpaces((string) ($matches[2] ?? ''));
    }

    return [$title, $objective];
}

function generateObjective(string $unitTitle): string
{
    $title = cleanSpaces($unitTitle);
    return "Analizar y aplicar los conceptos clave de {$title} en situaciones académicas y prácticas.";
}

$temarios = Temario::with(['points' => fn ($q) => $q->orderBy('position')])->get();
$summary = [
    'temarios_total' => $temarios->count(),
    'temarios_updated' => 0,
    'units_with_generated_objective' => 0,
    'points_with_clean_spaces' => 0,
    'labels_normalized' => 0,
    'updated_temario_ids' => [],
];

foreach ($temarios as $temario) {
    DB::transaction(function () use ($temario, &$summary) {
        $changed = false;
        $unitCounter = 0;
        $pointCounterInUnit = 0;

        foreach ($temario->points as $point) {
            $newLabel = (string) $point->label;
            $newContent = cleanSpaces((string) $point->content);

            if ((int) $point->level === 1) {
                $unitCounter++;
                $pointCounterInUnit = 0;
                [$unitTitle, $unitObjective] = parseUnit($newContent);

                if ($unitObjective === '') {
                    $unitObjective = generateObjective($unitTitle);
                    $summary['units_with_generated_objective']++;
                }

                $newContent = cleanSpaces($unitTitle) . ' | Objetivo específico: ' . cleanSpaces($unitObjective);
                $newLabel = (string) $unitCounter;
            } else {
                $pointCounterInUnit++;
                $labelTrim = cleanSpaces((string) $point->label);
                if ($labelTrim === '' || !preg_match('/^[0-9]+(?:\.[0-9]+)*$|^[a-zA-Z]\)$/', $labelTrim)) {
                    $newLabel = "{$unitCounter}.{$pointCounterInUnit}";
                } else {
                    $newLabel = $labelTrim;
                }
            }

            if (cleanSpaces((string) $point->content) !== $newContent) {
                $summary['points_with_clean_spaces']++;
                $changed = true;
            }

            if (cleanSpaces((string) $point->label) !== $newLabel) {
                $summary['labels_normalized']++;
                $changed = true;
            }

            if ((string) $point->content !== $newContent || (string) $point->label !== $newLabel) {
                $point->content = $newContent;
                $point->label = $newLabel;
                $point->save();
            }
        }

        if ($changed) {
            $summary['temarios_updated']++;
            $summary['updated_temario_ids'][] = $temario->id;
        }
    });
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
