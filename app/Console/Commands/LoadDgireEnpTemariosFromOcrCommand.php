<?php

namespace App\Console\Commands;

use App\Models\Subject;
use App\Models\Temario;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class LoadDgireEnpTemariosFromOcrCommand extends Command
{
    protected $signature = 'ams:load-dgire-enp-temarios-ocr
        {--key= : Clave DGIRE especifica, por ejemplo 1502}
        {--apply : Guarda los temarios en la base de datos}
        {--force : Reemplaza temarios DGIRE ENP existentes}
        {--force-ocr : Regenera el texto OCR aunque ya exista cache}
        {--limit=0 : Maximo de materias a procesar en esta corrida}';

    protected $description = 'Carga temarios DGIRE ENP desde PDFs oficiales usando OCR local.';

    private string $tesseract = 'C:\Program Files\Tesseract-OCR\tesseract.exe';

    public function handle(): int
    {
        $subjects = Subject::query()
            ->whereNotNull('subject_key')
            ->when($this->option('key'), fn ($query, $key) => $query->where('subject_key', (string) $key))
            ->orderBy('subject_key')
            ->get();

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $subjects = $subjects->take($limit);
        }

        if ($subjects->isEmpty()) {
            $this->warn('No encontre materias con esa clave DGIRE.');
            return self::SUCCESS;
        }

        $rows = [];
        $saved = 0;

        foreach ($subjects as $subject) {
            $key = (string) $subject->subject_key;
            $pdfPath = $this->pdfPath($key);

            if (! $pdfPath) {
                $rows[] = [$key, $subject->name, 'Sin PDF', '-', '-', '-'];
                continue;
            }

            $existing = $subject->temarios()
                ->where('title', 'Temario DGIRE ENP ' . $subject->name)
                ->first();
            $hasAnyTemario = $subject->temarios()->exists();

            if ($hasAnyTemario && ! $this->option('force')) {
                $rows[] = [$key, $subject->name, 'Ya tiene temario', '-', '-', 'Usa --force'];
                continue;
            }

            try {
                $textPath = $this->ocrTextPath($key);
                if ($this->option('force-ocr') || ! is_file($textPath)) {
                    $this->line("OCR {$key} - {$subject->name}");
                    $this->runOcr($pdfPath, $textPath);
                }

                $parsed = $this->parseTemario(file_get_contents($textPath) ?: '');
            } catch (\Throwable $exception) {
                $rows[] = [$key, $subject->name, 'Error', '-', '-', $exception->getMessage()];
                continue;
            }

            $unitCount = count($parsed['units']);
            $pointCount = collect($parsed['units'])->sum(fn ($unit) => count($unit['points']));

            if ($unitCount === 0 || $pointCount === 0) {
                $rows[] = [$key, $subject->name, 'Parse insuficiente', $unitCount, $pointCount, 'No se guardo'];
                continue;
            }

            if ($this->option('apply')) {
                $this->saveTemario($subject, $parsed, $existing);
                $saved++;
            }

            $rows[] = [
                $key,
                $subject->name,
                $this->option('apply') ? 'Guardado' : 'DRY RUN',
                $unitCount,
                $pointCount,
                mb_substr($parsed['general_objective'] ?: '-', 0, 90, 'UTF-8'),
            ];
        }

        $this->table(['Clave', 'Materia', 'Estado', 'Unidades', 'Puntos', 'Detalle'], $rows);
        $this->line($this->option('apply') ? "Temarios guardados: {$saved}" : 'DRY RUN: usa --apply para guardar.');

        return self::SUCCESS;
    }

    private function saveTemario(Subject $subject, array $parsed, ?Temario $existing): void
    {
        DB::transaction(function () use ($subject, $parsed, $existing) {
            $temario = $existing ?: $subject->temarios()->make();
            $temario->fill([
                'subject_id' => $subject->id,
                'title' => 'Temario DGIRE ENP ' . $subject->name,
                'description' => trim($parsed['general_objective'] ?: 'Importado por OCR desde programa oficial DGIRE ENP.'),
            ]);
            $temario->save();
            $temario->points()->delete();

            $position = 1;
            foreach ($parsed['units'] as $unit) {
                $temario->points()->create([
                    'position' => $position++,
                    'label' => (string) $unit['number'],
                    'level' => 1,
                    'type' => 'conceptual',
                    'content' => trim($unit['title'] . ($unit['objective'] !== '' ? ' | Objetivo especifico: ' . $unit['objective'] : '')),
                ]);

                foreach ($unit['points'] as $point) {
                    $temario->points()->create([
                        'position' => $position++,
                        'label' => $point['label'],
                        'level' => $this->levelFromLabel($point['label']),
                        'type' => $point['type'],
                        'content' => $point['content'],
                    ]);
                }
            }
        });
    }

    private function runOcr(string $pdfPath, string $textPath): void
    {
        $workDir = storage_path('app/ocr/enp/pages/' . pathinfo($pdfPath, PATHINFO_FILENAME));
        if (is_dir($workDir)) {
            $this->deleteDirectory($workDir);
        }
        @mkdir($workDir, 0777, true);
        @mkdir(dirname($textPath), 0777, true);

        $prefix = $workDir . DIRECTORY_SEPARATOR . 'page';
        $this->runProcess(['pdftoppm', '-r', '220', '-png', $pdfPath, $prefix], 180);

        $texts = [];
        $images = collect(glob($workDir . DIRECTORY_SEPARATOR . '*.png') ?: [])->sort()->values();
        foreach ($images as $image) {
            $outputBase = $workDir . DIRECTORY_SEPARATOR . pathinfo($image, PATHINFO_FILENAME);
            $this->runProcess([
                $this->tesseract,
                $image,
                $outputBase,
                '-l',
                'spa+eng',
                '--tessdata-dir',
                storage_path('app/ocr/tessdata'),
                '--psm',
                '6',
            ], 120);

            $pageText = $outputBase . '.txt';
            if (is_file($pageText)) {
                $texts[] = file_get_contents($pageText) ?: '';
            }
        }

        file_put_contents($textPath, implode("\n\n", $texts));
        $this->deleteDirectory($workDir);
    }

    private function parseTemario(string $text): array
    {
        $text = $this->normalizeText($text);
        $lines = collect(preg_split('/\R/u', $text) ?: [])
            ->map(fn ($line) => trim(preg_replace('/\s+/u', ' ', $line) ?: ''))
            ->filter(fn ($line) => $line !== '')
            ->values();

        $generalObjective = $this->extractGeneralObjective($lines);
        $units = [];
        $current = null;
        $section = null;
        $lastPointIndex = null;
        $collectingObjectives = false;

        foreach ($lines as $line) {
            if (preg_match('/^Unidad\s+([0-9]+)\s*[.:]\s*(.+)$/ui', $line, $matches) === 1) {
                if ($current) {
                    $units[] = $current;
                }

                $current = [
                    'number' => (int) $matches[1],
                    'title' => trim($matches[2]),
                    'objective' => '',
                    'points' => [],
                ];
                $section = null;
                $lastPointIndex = null;
                $collectingObjectives = false;
                continue;
            }

            if (! $current) {
                continue;
            }

            $lower = mb_strtolower($line, 'UTF-8');
            if (str_contains($lower, 'objetivos especificos') || str_contains($lower, 'objetivos específicos')) {
                $collectingObjectives = true;
                $section = null;
                $lastPointIndex = null;
                continue;
            }

            if (str_contains($lower, 'contenidos conceptuales')) {
                $collectingObjectives = false;
                $section = 'conceptual';
                $lastPointIndex = null;
                continue;
            }

            if (str_contains($lower, 'contenidos procedimentales')) {
                $collectingObjectives = false;
                $section = 'procedimental';
                $lastPointIndex = null;
                continue;
            }

            if (str_contains($lower, 'contenidos actitudinales')) {
                $collectingObjectives = false;
                $section = 'actitudinal';
                $lastPointIndex = null;
                continue;
            }

            if ($collectingObjectives) {
                if (! preg_match('/^(El alumno:?|e\s+)/ui', $line)) {
                    $current['objective'] = trim($current['objective'] . ' ' . preg_replace('/^[•\-\*e]\s*/u', '', $line));
                }
                continue;
            }

            if (! $section) {
                continue;
            }

            if (preg_match('/^([0-9]+(?:\.[0-9]+)+)\.?\s+(.+)$/u', $line, $matches) === 1) {
                $current['points'][] = [
                    'label' => $matches[1],
                    'type' => $section,
                    'content' => trim($matches[2]),
                ];
                $lastPointIndex = array_key_last($current['points']);
                continue;
            }

            if ($lastPointIndex !== null && ! preg_match('/^(Unidad|Objetivos|Contenidos)\b/ui', $line)) {
                $current['points'][$lastPointIndex]['content'] = trim($current['points'][$lastPointIndex]['content'] . ' ' . $line);
            }
        }

        if ($current) {
            $units[] = $current;
        }

        return [
            'general_objective' => $generalObjective,
            'units' => array_values(array_filter($units, fn ($unit) => ! empty($unit['points']))),
        ];
    }

    private function extractGeneralObjective(Collection $lines): string
    {
        $start = null;
        foreach ($lines as $index => $line) {
            $lower = mb_strtolower($line, 'UTF-8');
            if (str_contains($lower, 'objetivo general') || str_contains($lower, 'objetivos generales')) {
                $start = $index + 1;
                break;
            }
        }

        if ($start === null) {
            return '';
        }

        $parts = [];
        for ($i = $start; $i < $lines->count(); $i++) {
            $line = $lines->get($i);
            if (preg_match('/^Unidad\s+[0-9]+/ui', $line) === 1) {
                break;
            }
            $parts[] = $line;
        }

        return trim(implode(' ', $parts));
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[^\P{C}\r\n\t]+/u', '', $text) ?: $text;
        $text = strtr($text, [
            'Ã¡' => 'á', 'Ã©' => 'é', 'Ã­' => 'í', 'Ã³' => 'ó', 'Ãº' => 'ú',
            'Ã' => 'Á', 'Ã‰' => 'É', 'Ã' => 'Í', 'Ã“' => 'Ó', 'Ãš' => 'Ú',
            'Ã±' => 'ñ', 'Ã‘' => 'Ñ', 'Â¿' => '¿', 'Â¡' => '¡',
        ]);

        return $text;
    }

    private function levelFromLabel(string $label): int
    {
        return max(2, count(explode('.', $label)));
    }

    private function pdfPath(string $key): ?string
    {
        $paths = [
            storage_path("app/private/dgire/enp/{$key}.pdf"),
            storage_path("app/dgire/enp/{$key}.pdf"),
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function ocrTextPath(string $key): string
    {
        return storage_path("app/ocr/enp/{$key}.txt");
    }

    private function runProcess(array $command, int $timeout): void
    {
        $process = new Process($command, base_path());
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'Proceso externo fallido.');
        }
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}
