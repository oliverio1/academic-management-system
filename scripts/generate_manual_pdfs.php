<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$cli = parseCliArgs($argv ?? []);
$schoolName = $cli['school'] ?? 'Sistema Academico';
$version = $cli['version'] ?? '1.0';
$logoPath = $cli['logo'] ?? '';
$printDate = $cli['date'] ?? date('Y-m-d');

$root = dirname(__DIR__);
$docsDir = $root . '/docs';
$rolesDir = $docsDir . '/roles';
$pdfDir = $docsDir . '/pdf';

if (! is_dir($pdfDir)) {
    mkdir($pdfDir, 0777, true);
}

$sources = [
    $docsDir . '/MANUAL_USO_SISTEMA.md' => $pdfDir . '/Manual_Uso_Sistema.pdf',
    $rolesDir . '/COORDINADOR.md' => $pdfDir . '/Manual_Coordinador.pdf',
    $rolesDir . '/PROFESOR.md' => $pdfDir . '/Manual_Profesor.pdf',
    $rolesDir . '/PREFECTO.md' => $pdfDir . '/Manual_Prefecto.pdf',
    $rolesDir . '/TUTOR.md' => $pdfDir . '/Manual_Tutor.pdf',
    $rolesDir . '/ALUMNO.md' => $pdfDir . '/Manual_Alumno.pdf',
];

foreach ($sources as $source => $target) {
    if (! file_exists($source)) {
        echo "No existe: {$source}\n";
        continue;
    }

    $markdown = file_get_contents($source) ?: '';
    $html = markdownToHtml($markdown);
    $title = basename($target, '.pdf');
    $styledHtml = wrapHtml($html, $title, $schoolName, $version, $printDate, $logoPath);

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($styledHtml, 'UTF-8');
    $dompdf->setPaper('letter');
    $dompdf->render();

    file_put_contents($target, $dompdf->output());
    echo "Generado: {$target}\n";
}

function markdownToHtml(string $md): string
{
    $lines = preg_split('/\R/', $md) ?: [];
    $html = '';
    $inList = false;

    foreach ($lines as $line) {
        $trim = trim($line);

        if ($trim === '') {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            continue;
        }

        if (str_starts_with($trim, '## ')) {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $html .= '<h2>' . eHtml(substr($trim, 3)) . '</h2>';
            continue;
        }

        if (str_starts_with($trim, '# ')) {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $html .= '<h1>' . eHtml(substr($trim, 2)) . '</h1>';
            continue;
        }

        if (str_starts_with($trim, '- ')) {
            if (! $inList) {
                $html .= '<ul>';
                $inList = true;
            }
            $html .= '<li>' . inlineCode(eHtml(substr($trim, 2))) . '</li>';
            continue;
        }

        if (preg_match('/^\d+\.\s+(.+)$/', $trim, $matches)) {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $html .= '<p>' . inlineCode(eHtml($matches[1])) . '</p>';
            continue;
        }

        if ($inList) {
            $html .= '</ul>';
            $inList = false;
        }

        $html .= '<p>' . inlineCode(eHtml($trim)) . '</p>';
    }

    if ($inList) {
        $html .= '</ul>';
    }

    return $html;
}

function wrapHtml(
    string $body,
    string $title,
    string $schoolName,
    string $version,
    string $printDate,
    string $logoPath
): string
{
    $logoHtml = '';
    if ($logoPath !== '' && file_exists($logoPath)) {
        $data = base64_encode(file_get_contents($logoPath) ?: '');
        $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : ($ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : 'image/png');
        if ($data !== '') {
            $logoHtml = '<img class="logo" src="data:' . $mime . ';base64,' . $data . '" alt="Logo">';
        }
    }

    $cover = '<section class="cover">'
        . $logoHtml
        . '<h1>' . eHtml($schoolName) . '</h1>'
        . '<h2>' . eHtml(str_replace('_', ' ', $title)) . '</h2>'
        . '<p><strong>Version:</strong> ' . eHtml($version) . '</p>'
        . '<p><strong>Fecha:</strong> ' . eHtml($printDate) . '</p>'
        . '</section><div class="page-break"></div>';

    return '<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
        body{font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#111; line-height:1.45;}
        .cover{height:95vh; text-align:center; display:flex; flex-direction:column; justify-content:center; align-items:center;}
        .cover .logo{max-width:180px; max-height:120px; margin-bottom:18px;}
        .cover h1{font-size:28px; margin:0 0 10px 0;}
        .cover h2{font-size:20px; margin:0 0 14px 0; border:none;}
        .page-break{page-break-after:always;}
        h1{font-size:20px; margin:0 0 10px 0;}
        h2{font-size:15px; margin:16px 0 8px 0; border-bottom:1px solid #ddd; padding-bottom:3px;}
        p{margin:0 0 8px 0;}
        ul{margin:0 0 10px 18px; padding:0;}
        li{margin:0 0 4px 0;}
        code{background:#f2f2f2; padding:1px 4px; border-radius:3px;}
    </style></head><body>' . $cover . $body . '</body></html>';
}

function eHtml(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function inlineCode(string $text): string
{
    return preg_replace('/`([^`]+)`/', '<code>$1</code>', $text) ?? $text;
}

function parseCliArgs(array $argv): array
{
    $args = [];
    foreach (array_slice($argv, 1) as $token) {
        if (! str_starts_with($token, '--')) {
            continue;
        }
        $parts = explode('=', substr($token, 2), 2);
        $key = $parts[0] ?? '';
        $value = $parts[1] ?? '1';
        if ($key !== '') {
            $args[$key] = $value;
        }
    }
    return $args;
}

