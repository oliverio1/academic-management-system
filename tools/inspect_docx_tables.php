<?php

$files = array_slice($argv, 1);
foreach ($files as $file) {
    echo "\n## {$file}\n";
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) {
        echo "No se pudo abrir\n";
        continue;
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if (! $xml) {
        echo "Sin document.xml\n";
        continue;
    }

    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    foreach ($xpath->query('//w:tbl') as $tableIndex => $table) {
        echo "\nTABLE {$tableIndex}\n";
        foreach ($xpath->query('.//w:tr', $table) as $rowIndex => $row) {
            $cells = [];
            foreach ($xpath->query('./w:tc', $row) as $cell) {
                $texts = [];
                foreach ($xpath->query('.//w:t', $cell) as $t) {
                    $texts[] = $t->textContent;
                }
                $cells[] = trim(preg_replace('/\s+/u', ' ', implode(' ', $texts)) ?: '');
            }
            if ($rowIndex < 8 || $tableIndex >= 4) {
                echo str_pad((string) $rowIndex, 3, ' ', STR_PAD_LEFT).': '.implode(' | ', $cells)."\n";
            }
        }
    }
}
