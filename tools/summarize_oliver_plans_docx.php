<?php

$folder = 'C:\\Users\\LapOne MX\\Desktop\\BACHILLERATO FLORIDA 26-3-20260727T081948Z-1-001\\BACHILLERATO FLORIDA 26-3\\CARPETAS ADMINISTRATIVAS DOCENTES\\OLIVER MARTINEZ ANAYA\\PLANEACIONES';

function textFromNode(DOMNode $node): string
{
    $text = '';

    foreach ($node->childNodes as $child) {
        if ($child->nodeName === 'w:t') {
            $text .= $child->nodeValue;
        } elseif ($child->nodeName === 'w:tab') {
            $text .= "\t";
        } elseif ($child->nodeName === 'w:br') {
            $text .= "\n";
        } else {
            $text .= textFromNode($child);
        }
    }

    return trim(preg_replace('/[ \t]+/u', ' ', $text));
}

function docxTables(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException("No se pudo abrir {$path}");
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    $tables = [];
    foreach ($xpath->query('//w:tbl') as $tableNode) {
        $rows = [];
        foreach ($xpath->query('./w:tr', $tableNode) as $rowNode) {
            $cells = [];
            foreach ($xpath->query('./w:tc', $rowNode) as $cellNode) {
                $cells[] = textFromNode($cellNode);
            }
            $rows[] = $cells;
        }
        $tables[] = $rows;
    }

    return $tables;
}

if (realpath($argv[0] ?? '') !== __FILE__) {
    return;
}

foreach (glob($folder . '\\*.docx') as $path) {
    echo PHP_EOL . '### ' . basename($path) . PHP_EOL;
    $tables = docxTables($path);
    echo 'Tablas: ' . count($tables) . PHP_EOL;

    foreach ($tables as $index => $rows) {
        $maxCells = 0;
        foreach ($rows as $row) {
            $maxCells = max($maxCells, count($row));
        }

        echo '- Tabla ' . ($index + 1) . ': filas=' . count($rows) . ', max celdas=' . $maxCells . PHP_EOL;
        $matches = [];
        foreach ($rows as $rowNumber => $row) {
            $line = implode(' | ', array_map(fn ($cell) => mb_substr(str_replace(["\r", "\n"], ' / ', $cell), 0, 100), $row));
            if ($rowNumber < 3 || preg_match('/sesion|sesión|fecha|contenido|aprendizaje|evaluaci[oó]n|unidad|objetivo|bibliograf/i', $line)) {
                $matches[] = str_pad((string) ($rowNumber + 1), 3, ' ', STR_PAD_LEFT) . ': ' . $line;
            }
        }

        foreach (array_slice($matches, 0, 14) as $line) {
            echo '  ' . $line . PHP_EOL;
        }
    }
}
