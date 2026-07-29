<?php

use App\Models\Subject;
use App\Models\Temario;
use App\Models\TemarioPoint;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$apply = in_array('--apply', $argv, true);
$force = in_array('--force', $argv, true);
$dir = 'C:\\Users\\LapOne MX\\Desktop\\TEMARIOS BACHILLERATO';

$imports = [
    ['file' => 'taller de lectura y redaccion.pdf', 'subject_id' => 711, 'title' => 'Temario Taller de Lectura y Redacción I'],
    ['file' => 'quimica i.pdf', 'subject_id' => 709, 'title' => 'Temario Química I'],
    ['file' => 'ingles i.pdf', 'subject_id' => 707, 'title' => 'Temario Inglés I'],
    ['file' => 'ingles ii.pdf', 'subject_id' => 724, 'title' => 'Temario Inglés II'],
    ['file' => 'informatica i.pdf', 'subject_id' => 705, 'title' => 'Temario Informática I'],
    ['file' => 'informatica ii.pdf', 'subject_id' => 723, 'title' => 'Temario Informática II'],
    ['file' => 'introduccion a las ciencias sociales.pdf', 'subject_id' => 712, 'title' => 'Temario Introducción a las Ciencias Sociales'],
    ['file' => 'matematicas i.pdf', 'subject_id' => 706, 'title' => 'Temario Matemáticas I'],
    ['file' => '101 TEMARIO PLANEAR ACTIVIDADES Y ASIGNAR RECURSOS.docx', 'subject_id' => 730, 'title' => 'Temario Planear actividades y asignar recursos'],
    ['file' => '102 TEMARIO DIRECCIONAR EL PLAN DE ACCIÓN.docx', 'subject_id' => 734, 'title' => 'Temario Direccionar y evaluar el plan de acción'],
    ['file' => '103 TEMARIO GENERAR LA COMUNICACIÓN CUARTO.docx', 'subject_id' => 745, 'title' => 'Temario Generar la comunicación de la empresa'],
    ['file' => '104 TEMARIO CONTROLA DOCUMENTACION CUARTO.docx', 'subject_id' => 746, 'title' => 'Temario Controlar la información de la empresa'],
    ['file' => '2° TEMARIOS ING-BAS 26-1.docx', 'subject_id' => 724, 'title' => 'Temario Inglés II'],
    ['file' => '2º- H de México 1.pdf', 'subject_id' => 715, 'title' => 'Temario Historia de México I'],
    ['file' => '3° TEMARIOS ING-BAS 26-1.docx', 'subject_id' => 733, 'title' => 'Temario Inglés III'],
    ['file' => '3º- H de México 2.pdf', 'subject_id' => 727, 'title' => 'Temario Historia de México II'],
    ['file' => '4° TEMARIOS ING-BAS 26-1.docx', 'subject_id' => 739, 'title' => 'Temario Inglés IV'],
    ['file' => 'Temario Estructuras socioeconómicas de México.docx', 'subject_id' => 737, 'title' => 'Temario Estructura socioeconómica de México'],
    ['file' => '5° TEMARIO ING-BAS 26-1.docx', 'subject_id' => 753, 'title' => 'Temario Inglés V'],
    ['file' => '5º- H universal contemporánea.pdf', 'subject_id' => 750, 'title' => 'Temario Historia universal contemporánea'],
    ['file' => '6° TEMARIO ING-BAS 26-1.docx', 'subject_id' => 465, 'title' => 'Temario Inglés VI'],
    ['file' => 'Literatura I.docx', 'subject_id' => 735, 'title' => 'Temario Literatura I'],
    ['file' => 'Temario Bio 1.pdf', 'subject_id' => 731, 'title' => 'Temario Biología I'],
    ['file' => 'Temario Bio 2.pdf', 'subject_id' => 741, 'title' => 'Temario Biología II'],
    ['file' => 'Temario CS 2.pdf', 'subject_id' => 460, 'title' => 'Temario Ciencias de la Salud II'],
    ['file' => 'Temario de Filosofía.docx', 'subject_id' => 455, 'title' => 'Temario Filosofía'],
    ['file' => 'Temario EyMA.pdf', 'subject_id' => 454, 'title' => 'Temario Ecología y medio ambiente'],
    ['file' => 'Temario EyV II.docx', 'subject_id' => 716, 'title' => 'Temario Ética y valores II'],
    ['file' => 'TEMARIO INFORMÁTICA II.pdf', 'subject_id' => 723, 'title' => 'Temario Informática II'],
    ['file' => 'Temario Literatura II.docx', 'subject_id' => 740, 'title' => 'Temario Literatura II'],
    ['file' => 'TEMARIO METODOLOGÍA DE LA INVESTIGACIÓN.docx', 'subject_id' => 453, 'title' => 'Temario Metodología de la investigación'],
    ['file' => 'Temario OE II.docx', 'subject_id' => 719, 'title' => 'Temario Orientación Educativa II'],
    ['file' => 'Temario OE III.docx', 'subject_id' => 732, 'title' => 'Temario Orientación Educativa III'],
    ['file' => 'Temario OE IV.docx', 'subject_id' => 743, 'title' => 'Temario Orientación Educativa IV'],
    ['file' => 'Temario OE V.docx', 'subject_id' => 749, 'title' => 'Temario Orientación Educativa V'],
    ['file' => 'Temario OE VI.docx', 'subject_id' => 458, 'title' => 'Temario Orientación Educativa VI'],
    ['file' => 'Temario PSIC I.docx', 'subject_id' => 762, 'title' => 'Temario Psicología I'],
    ['file' => 'Temario PSIC II.docx', 'subject_id' => 469, 'title' => 'Temario Psicología II'],
    ['file' => 'TEMARIO SOCIO 1 pdf.pdf', 'subject_id' => 759, 'title' => 'Temario Sociología I'],
    ['file' => 'Temario Socio II pdf.pdf', 'subject_id' => 468, 'title' => 'Temario Sociología II'],
    ['file' => 'TEMARIO TEMAS SELECTOS DE QUÍMICA II.docx', 'subject_id' => 461, 'title' => 'Temario Temas selectos de Química II'],
    ['file' => 'Temario TLR II.docx', 'subject_id' => 717, 'title' => 'Temario Taller de Lectura y Redacción II'],
    ['file' => 'Temario TUT II.docx', 'subject_id' => 725, 'title' => 'Temario Tutorías II'],
    ['file' => 'Temario TUT III.docx', 'subject_id' => 736, 'title' => 'Temario Tutorías III'],
    ['file' => 'Temario TUT VI.docx', 'subject_id' => 459, 'title' => 'Temario Tutorías VI'],
    ['file' => 'Temario_Bach_26-1_Actualizar los sistemas de información de una empresa_HayrAriadneCalderonLule.docx', 'subject_id' => 748, 'title' => 'Temario Actualizar los sistemas de información de la empresa'],
    ['file' => 'Temario_Bach_26-1_Atender al cliente en su entorno_HayrAriadneCalderonLule.docx', 'subject_id' => 752, 'title' => 'Temario Atender al cliente en su entorno social de manera presencial'],
    ['file' => 'Temario_Bach_26-1_Atender al cliente mediante TICS_HayrAriadneCalderonLule.docx', 'subject_id' => 763, 'title' => 'Temario Atender al cliente mediante TICS'],
    ['file' => 'Temario_Bach_26-1_DerechoII_HayriAriadneCalderonLule.docx', 'subject_id' => 467, 'title' => 'Temario Derecho II'],
    ['file' => 'Temario_Bach_26-1_DerechoI_HayriAriadneCalderonLule.docx', 'subject_id' => 760, 'title' => 'Temario Derecho I'],
    ['file' => 'Temario_Bach_26-1_Detectar, atender y dar seguimiento al cliente_HayriAriadneCalderonLule.docx', 'subject_id' => 457, 'title' => 'Temario Detectar y dar seguimiento'],
    ['file' => 'Temario_Cálculo Integral_Bach_26-1_v0.docx', 'subject_id' => 462, 'title' => 'Temario Cálculo Integral'],
    ['file' => 'Temario_Matemáticas 2_Bach_26-1_v0.docx', 'subject_id' => 721, 'title' => 'Temario Matemáticas II'],
    ['file' => 'Temario_Matemáticas 3_Bach_26-1_v0.docx', 'subject_id' => 729, 'title' => 'Temario Matemáticas III'],
    ['file' => 'Temario_Matemáticas 4_Bach_26-1_v0.docx', 'subject_id' => 738, 'title' => 'Temario Matemáticas IV'],
    ['file' => 'Temario_Matemáticas Financieras 2_Bach_26-1_v0.docx', 'subject_id' => 466, 'title' => 'Temario Matemáticas Financieras II'],
    ['file' => 'Temario_Matemáticas Financieras1_Bach_26-1_v0.docx', 'subject_id' => 761, 'title' => 'Temario Matemáticas Financieras I'],
    ['file' => 'Temario_TemasSelectosdeFisica2_Bach_26-1_v0.docx', 'subject_id' => 463, 'title' => 'Temario Temas Selectos de Física II'],
    ['file' => 'TEMAS SELECTOS DE QUÍMICA I.docx', 'subject_id' => 754, 'title' => 'Temario Temas Selectos de Química I'],
];

$skipFiles = [
    '4º- Estructura socioeconómica de México.pdf' => 'Duplicado: se usa el DOCX de la misma materia.',
    'Temario Fis 1.pdf' => 'Ya existe temario importado para Física I.',
    'Temario Fis 2.pdf' => 'Ya existe temario importado para Física II.',
    'TEMARIO GEOGRAFÍA.docx' => 'Ya existe temario importado para Geografía.',
    'Temario TUT IV.docx' => 'El ciclo 26-3 no tiene una materia Tutorías IV claramente nombrada.',
    'Temario TUT V.docx' => 'El ciclo 26-3 no tiene una materia Tutorías V claramente nombrada.',
    '4º- Estructura socioeconómica de México.pdf' => 'Duplicado: se usa el DOCX de la misma materia.',
];

echo ($apply ? 'APPLY' : 'DRY RUN') . ($force ? ' + FORCE' : '') . PHP_EOL;

$saved = 0;
$skipped = [];

foreach ($imports as $import) {
    $path = $dir . DIRECTORY_SEPARATOR . $import['file'];
    $subject = Subject::query()->find($import['subject_id']);

    if (! $subject) {
        $skipped[] = [$import['file'], 'Materia no encontrada #' . $import['subject_id']];
        continue;
    }

    if (! file_exists($path)) {
        $skipped[] = [$import['file'], 'Archivo no encontrado'];
        continue;
    }

    $existing = $subject->temarios()->first();
    if ($existing && ! $force) {
        $skipped[] = [$import['file'], 'La materia ya tiene temario: ' . $subject->name];
        continue;
    }

    $text = extractText($path);
    $program = parseProgram($text, $import['title']);

    if (count($program['points']) < 2) {
        $skipped[] = [$import['file'], 'No se detectaron puntos suficientes'];
        continue;
    }

    if ($apply) {
        saveTemario($subject, $import['title'], $program, $existing);
        $saved++;
    }

    printf(
        "%s | #%d %s | unidades=%d puntos=%d\n",
        $apply ? 'Guardado' : 'Listo',
        $subject->id,
        $subject->name,
        count(array_filter($program['points'], fn ($point) => $point['level'] === 1)),
        count($program['points'])
    );
}

foreach ($skipFiles as $file => $reason) {
    if (file_exists($dir . DIRECTORY_SEPARATOR . $file)) {
        $skipped[] = [$file, $reason];
    }
}

echo PHP_EOL . 'Guardados: ' . $saved . PHP_EOL;
if ($skipped) {
    echo 'Omitidos / revisar:' . PHP_EOL;
    foreach ($skipped as [$file, $reason]) {
        echo '- ' . $file . ' => ' . $reason . PHP_EOL;
    }
}

function saveTemario(Subject $subject, string $title, array $program, ?Temario $existing): void
{
    DB::transaction(function () use ($subject, $title, $program, $existing): void {
        $temario = $existing ?: new Temario();
        $temario->fill([
            'subject_id' => $subject->id,
            'title' => $title,
            'description' => $program['description'],
        ]);
        $temario->save();
        $temario->points()->delete();

        foreach ($program['points'] as $index => $point) {
            TemarioPoint::create([
                'temario_id' => $temario->id,
                'position' => $index + 1,
                'label' => $point['label'],
                'level' => $point['level'],
                'type' => 'conceptual',
                'content' => $point['content'],
            ]);
        }
    });
}

function extractText(string $path): string
{
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if ($extension === 'docx') {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();

        $xml = preg_replace('/<w:tab[^>]*\/>/u', ' ', $xml);
        $xml = preg_replace('/<\/w:p>/u', "\n", $xml);
        $xml = preg_replace('/<[^>]+>/u', '', $xml);

        return html_entity_decode($xml, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    if ($extension === 'pdf') {
        $command = 'pdftotext -layout -nopgbrk ' . escapeshellarg($path) . ' -';
        $text = (string) shell_exec($command);
        if (alphabeticCharacterCount($text) >= 200) {
            return $text;
        }

        return extractPdfTextWithOcr($path);
    }

    return '';
}

function alphabeticCharacterCount(string $text): int
{
    preg_match_all('/\p{L}/u', $text, $matches);

    return count($matches[0] ?? []);
}

function extractPdfTextWithOcr(string $path): string
{
    $tesseract = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
    if (! is_file($tesseract)) {
        return '';
    }

    $cacheDir = __DIR__ . '/../storage/app/imports/bachillerato_temarios_ocr';
    @mkdir($cacheDir, 0777, true);

    $cachePath = $cacheDir . DIRECTORY_SEPARATOR . sha1($path . '|' . filemtime($path)) . '.txt';
    if (is_file($cachePath)) {
        return (string) file_get_contents($cachePath);
    }

    $workDir = $cacheDir . DIRECTORY_SEPARATOR . pathinfo($path, PATHINFO_FILENAME) . '_' . substr(sha1((string) microtime(true)), 0, 8);
    @mkdir($workDir, 0777, true);

    $prefix = $workDir . DIRECTORY_SEPARATOR . 'page';
    $renderCommand = 'pdftoppm -r 170 -png ' . escapeshellarg($path) . ' ' . escapeshellarg($prefix);
    shell_exec($renderCommand);

    $texts = [];
    $images = glob($workDir . DIRECTORY_SEPARATOR . '*.png') ?: [];
    sort($images);

    foreach ($images as $image) {
        $outputBase = $workDir . DIRECTORY_SEPARATOR . pathinfo($image, PATHINFO_FILENAME);
        $ocrCommand = escapeshellarg($tesseract)
            . ' '
            . escapeshellarg($image)
            . ' '
            . escapeshellarg($outputBase)
            . ' -l spa+eng --tessdata-dir '
            . escapeshellarg(__DIR__ . '/../storage/app/ocr/tessdata')
            . ' --psm 6 2>NUL';
        shell_exec($ocrCommand);

        $pageText = $outputBase . '.txt';
        if (is_file($pageText)) {
            $texts[] = (string) file_get_contents($pageText);
        }
    }

    $text = implode("\n\n", $texts);
    file_put_contents($cachePath, $text);
    deleteDirectory($workDir);

    return $text;
}

function deleteDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $items = array_diff(scandir($directory) ?: [], ['.', '..']);
    foreach ($items as $item) {
        $path = $directory . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDirectory($path);
            continue;
        }

        @unlink($path);
    }

    @rmdir($directory);
}

function parseProgram(string $text, string $title): array
{
    $lines = normalizeLines($text);
    $description = collectDescription($lines, $title);
    $dgbBlocks = parseDgbLearningBlocks($lines);
    if ($dgbBlocks) {
        $points = [];
        foreach ($dgbBlocks as $index => $blockTitle) {
            $number = $index + 1;
            $points[] = [
                'label' => (string) $number,
                'level' => 1,
                'content' => $blockTitle,
            ];
            $points[] = [
                'label' => $number . '.1',
                'level' => 2,
                'content' => $blockTitle,
            ];
        }

        return compact('description', 'points');
    }

    $points = [];
    $unitNumber = 0;
    $topicNumber = 0;

    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        if (shouldIgnoreLine($line)) {
            continue;
        }

        $unitData = parseUnitData($line);
        if (! $unitData && isStandaloneUnitLine($line)) {
            $next = nextContentLine($lines, $i + 1);
            if ($next) {
                $i = $next['index'];
                $unitData = parseUnitData($next['line']) ?: [
                    'number' => standaloneUnitNumber($line),
                    'title' => $next['line'],
                ];
            }
        }

        if ($unitData) {
            $rawUnitNumber = normalizeUnitNumber($unitData['number']);
            if ($rawUnitNumber !== null && $rawUnitNumber <= $unitNumber) {
                continue;
            }

            $unitNumber = $rawUnitNumber ?? ($unitNumber + 1);
            $topicNumber = 0;
            $points[] = [
                'label' => (string) $unitNumber,
                'level' => 1,
                'content' => cleanContent($unitData['title']),
            ];
            continue;
        }

        $numbered = parseNumberedTopic($line);
        if ($numbered) {
            [$label, $content] = $numbered;
            $level = max(2, min(3, substr_count($label, '.') + 1));
            $points[] = [
                'label' => $label,
                'level' => $level,
                'content' => $content,
            ];
            continue;
        }

        if (isContentLine($line)) {
            if ($unitNumber === 0) {
                $unitNumber = 1;
                $topicNumber = 0;
                $points[] = [
                    'label' => '1',
                    'level' => 1,
                    'content' => $title,
                ];
            }

            $topicNumber++;
            $points[] = [
                'label' => $unitNumber . '.' . $topicNumber,
                'level' => 2,
                'content' => cleanContent($line),
            ];
        }
    }

    $points = deduplicatePoints($points);

    return compact('description', 'points');
}

function parseDgbLearningBlocks(array $lines): array
{
    $start = null;
    foreach ($lines as $index => $line) {
        if (preg_match('/^Bloques?\s+de\s+aprendizaje\.?$/iu', $line) === 1) {
            $start = $index + 1;
            break;
        }
    }

    if ($start === null) {
        return [];
    }

    $blocks = [];
    for ($i = $start; $i < count($lines); $i++) {
        $line = $lines[$i];
        $upper = mb_strtoupper($line, 'UTF-8');

        if (str_starts_with($upper, 'DGB/DCA') || str_starts_with($upper, 'COMPETENCIAS')) {
            break;
        }

        if (preg_match('/^Bloque\s+([IVXLCDM|l]+)\.?\s*(.+)$/iu', $line, $matches) === 1) {
            $title = cleanContent($matches[2]);
            if ($title !== '') {
                $blocks[] = $title;
            }
        }
    }

    return array_values(array_unique($blocks));
}

function normalizeLines(string $text): array
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[ \t]+/u', ' ', $text);

    return array_values(array_filter(array_map(function ($line) {
        $line = trim((string) $line);
        $line = preg_replace('/^[\x{2022}\-\x{25E6}]\s*/u', '', $line);
        $line = preg_replace('/\s+/u', ' ', $line);

        return trim($line);
    }, explode("\n", $text)), fn ($line) => $line !== ''));
}

function collectDescription(array $lines, string $title): string
{
    $parts = [];
    foreach ($lines as $line) {
        if (parseUnit($line) || parseNumberedTopic($line)) {
            break;
        }

        if (! shouldIgnoreLine($line) && mb_strlen($line) > 30) {
            $parts[] = cleanContent($line);
        }

        if (count($parts) >= 3) {
            break;
        }
    }

    return $parts ? implode("\n", $parts) : 'Importado desde carpeta TEMARIOS BACHILLERATO. ' . $title;
}

function parseUnit(string $line): ?string
{
    $unit = parseUnitData($line);

    return $unit ? cleanContent($unit['title']) : null;
}

function parseUnitData(string $line): ?array
{
    $patterns = [
        '/^(?:UNIDAD|Unidad)\s+([IVXLCDM]+|\d+)\.?\s*[:.\-]?\s*(.+)$/u',
        '/^(?:BLOQUE|Bloque)\s+([IVXLCDM]+|\d+)\.?\s*[:.\-]?\s*(.+)$/u',
        '/^(Primer|Segundo|Tercer|Cuarto|Quinto|Sexto)\s+parcial\s*[:.\-]\s*(.+)$/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $line, $matches) === 1) {
            return [
                'number' => $matches[1] ?? null,
                'title' => cleanContent($matches[2] ?? $line),
            ];
        }
    }

    return null;
}

function isStandaloneUnitLine(string $line): bool
{
    return preg_match('/^(?:UNIDAD|Unidad|BLOQUE|Bloque)\s+([IVXLCDM]+|\d+)\.?$/u', trim($line)) === 1;
}

function standaloneUnitNumber(string $line): ?string
{
    if (preg_match('/^(?:UNIDAD|Unidad|BLOQUE|Bloque)\s+([IVXLCDM]+|\d+)\.?$/u', trim($line), $matches) === 1) {
        return $matches[1];
    }

    return null;
}

function normalizeUnitNumber(?string $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    if (preg_match('/^\d+$/u', $value) === 1) {
        return (int) $value;
    }

    $map = [
        'PRIMER' => 1,
        'PRIMERO' => 1,
        'SEGUNDO' => 2,
        'TERCER' => 3,
        'TERCERO' => 3,
        'CUARTO' => 4,
        'QUINTO' => 5,
        'SEXTO' => 6,
        'I' => 1,
        'II' => 2,
        'III' => 3,
        'IV' => 4,
        'V' => 5,
        'VI' => 6,
        'VII' => 7,
        'VIII' => 8,
        'IX' => 9,
        'X' => 10,
    ];

    return $map[mb_strtoupper($value, 'UTF-8')] ?? null;
}

function nextContentLine(array $lines, int $start): ?array
{
    for ($i = $start; $i < count($lines); $i++) {
        $line = $lines[$i];
        if (shouldIgnoreLine($line) || isStandaloneUnitLine($line)) {
            continue;
        }

        if (parseUnitData($line)) {
            return ['index' => $i, 'line' => $line];
        }

        if (parseNumberedTopic($line)) {
            return null;
        }

        if (isContentLine($line)) {
            return ['index' => $i, 'line' => cleanContent($line)];
        }
    }

    return null;
}

function parseNumberedTopic(string $line): ?array
{
    if (preg_match('/^(\d+(?:\s*\.\s*\d+){1,3})\.?\s+(.+)$/u', $line, $matches) !== 1) {
        return null;
    }

    $label = preg_replace('/\s+/u', '', $matches[1]);
    $content = cleanContent($matches[2]);

    if ($content === '' || mb_strlen($content) < 3) {
        return null;
    }

    return [$label, $content];
}

function shouldIgnoreLine(string $line): bool
{
    $upper = mb_strtoupper($line, 'UTF-8');
    $ignore = [
        'TEMARIO',
        'TEMARIOS',
        'NIVEL:',
        'CICLO ESCOLAR',
        'BACHILLERATO',
        'MATERIA:',
        'MATERIAS:',
        'PERIODO:',
        'DOCENTE:',
        'PROFESOR:',
        'CAMPUS:',
        'GRADO:',
        'GRUPO:',
        'HORAS:',
        'APRENDIZAJES ESPERADOS',
        'CONTENIDO(S)',
        'TEMAS / SUBTEMAS',
        'CONTENIDOS APRENDIZAJES ESPERADOS',
        'PROPÓSITO.',
        'PROPOSITO.',
    ];

    foreach ($ignore as $needle) {
        if (str_starts_with($upper, $needle)) {
            return true;
        }
    }

    return false;
}

function isContentLine(string $line): bool
{
    $line = cleanContent($line);
    if ($line === '' || mb_strlen($line) < 4 || mb_strlen($line) > 260) {
        return false;
    }

    if (preg_match('/^\d+$/u', $line) === 1) {
        return false;
    }

    if (preg_match('/^(Unidad|Bloque)\s*$/iu', $line) === 1) {
        return false;
    }

    return true;
}

function cleanContent(string $line): string
{
    $line = preg_replace('/\s+/u', ' ', trim($line));
    $line = preg_replace('/\s+([,.;:])/', '$1', $line);

    return trim($line, " \t\n\r\0\x0B.-");
}

function deduplicatePoints(array $points): array
{
    $seen = [];
    $deduped = [];
    foreach ($points as $point) {
        $key = $point['label'] . '|' . mb_strtolower($point['content'], 'UTF-8');
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $deduped[] = $point;
    }

    return $deduped;
}
