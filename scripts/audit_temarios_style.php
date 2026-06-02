<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = App\Models\Temario::with(['subject','points' => function($q){ $q->orderBy('position'); }])->get();
$withIssues = [];

foreach ($rows as $t) {
    $issues = [];
    $unitRows = $t->points->where('level', 1)->values();

    if ($unitRows->isEmpty()) {
        $issues[] = 'Sin unidades nivel 1';
    }

    foreach ($unitRows as $u) {
        $content = (string) $u->content;
        if (str_contains($content, '|') && !preg_match('/^\s*.+\s*\|\s*Objetivo\s+espec[ií]fico\s*:\s*.+\s*$/iu', $content)) {
            $issues[] = 'Unidad con separador | pero formato irregular';
            break;
        }
    }

    foreach ($t->points as $p) {
        $label = trim((string) $p->label);
        if ($label !== '' && !preg_match('/^[0-9]+(?:\.[0-9]+)*$|^[a-zA-Z]\)$/', $label)) {
            $issues[] = 'Etiquetas con formato irregular';
            break;
        }
    }

    foreach ($t->points as $p) {
        if (preg_match('/\s{2,}/u', (string) $p->content)) {
            $issues[] = 'Texto con dobles espacios';
            break;
        }
    }

    if (!empty($issues)) {
        $withIssues[] = [
            'id' => $t->id,
            'subject' => optional($t->subject)->name,
            'title' => $t->title,
            'issues' => array_values(array_unique($issues)),
        ];
    }
}

echo json_encode([
    'total' => $rows->count(),
    'with_issues' => count($withIssues),
    'issues' => $withIssues,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
