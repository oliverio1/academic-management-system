<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Temario;

$temarios = Temario::with(['subject:id,name', 'points'])->get();
$updated = 0;

foreach ($temarios as $temario) {
    $current = trim((string) $temario->description);
    if ($current !== '') {
        continue;
    }

    $units = $temario->points->where('level', 1)->count();
    $subjectName = trim((string) optional($temario->subject)->name);
    $title = trim((string) $temario->title);
    $topicBase = $subjectName !== '' ? $subjectName : ($title !== '' ? $title : 'la asignatura');
    $unitsText = $units > 0 ? " organizado en {$units} unidades" : '';

    $temario->description = "Temario de {$topicBase}{$unitsText}, orientado al desarrollo de competencias conceptuales, procedimentales y actitudinales, con enfoque en la aplicación práctica de los contenidos.";
    $temario->save();
    $updated++;
}

echo json_encode([
    'temarios_total' => $temarios->count(),
    'descripciones_completadas' => $updated,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;

