<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Subject;
use App\Models\Temario;
use Illuminate\Support\Facades\DB;

function pointsFromUnits(array $units): array
{
    $rows = [];
    foreach (array_values($units) as $unitIndex => $unit) {
        $unitNo = $unitIndex + 1;
        $rows[] = [
            'label' => (string) $unitNo,
            'level' => 1,
            'type' => 'otro',
            'content' => trim($unit['title']) . ' | Objetivo específico: ' . trim($unit['objective']),
        ];

        foreach (array_values($unit['points']) as $pointIndex => $point) {
            $rows[] = [
                'label' => $unitNo . '.' . ($pointIndex + 1),
                'level' => 2,
                'type' => 'conceptual',
                'content' => trim($point),
            ];
        }
    }
    return $rows;
}

function buildPottencia(int $grade): array
{
    $themes = [
        1 => ['Fundamentos de IA', 'Datos y sesgos', 'Prompts básicos', 'Ética digital'],
        2 => ['Modelos generativos', 'Verificación de información', 'Automatización escolar', 'Propiedad intelectual'],
        3 => ['IA para investigación', 'Análisis de datos con IA', 'Diseño de presentaciones', 'Ciudadanía digital'],
        4 => ['IA para productividad', 'Agentes y flujos de trabajo', 'Resolución de problemas', 'Seguridad de la información'],
        5 => ['IA para emprendimiento', 'Prototipado rápido', 'Comunicación profesional', 'Impacto social'],
        6 => ['IA para proyecto de vida', 'Portafolio con IA', 'Preparación académica/laboral', 'Gobernanza y futuro de IA'],
    ];

    $t = $themes[$grade] ?? $themes[1];
    return [
        'title' => "POTTENCIA {$grade}",
        'description' => "Temario enfocado en inteligencia artificial aplicada para estudiantes de bachillerato, con desarrollo progresivo de competencias técnicas, éticas y de aplicación práctica.",
        'units' => [
            [
                'title' => 'Contexto y fundamentos',
                'objective' => "Comprender los conceptos esenciales de IA y su uso responsable en el contexto académico.",
                'points' => [
                    "¿Qué es la IA y cómo se utiliza en la vida cotidiana?",
                    "Alfabetización digital crítica y detección de sesgos.",
                    "Riesgos, límites y oportunidades de la IA.",
                ],
            ],
            [
                'title' => 'Uso práctico en el aprendizaje',
                'objective' => "Aplicar herramientas de IA para estudiar, investigar y comunicar información con calidad.",
                'points' => [
                    "Diseño de prompts para tareas y estudio.",
                    "Validación de respuestas y contraste de fuentes.",
                    "Apoyo de IA en resúmenes, esquemas y presentaciones.",
                ],
            ],
            [
                'title' => 'Desarrollo de proyecto con IA',
                'objective' => "Construir un producto o evidencia académica apoyada por IA con criterios éticos y técnicos.",
                'points' => [
                    "{$t[0]} como eje de trabajo.",
                    "{$t[1]} en el proceso de elaboración.",
                    "{$t[2]} para comunicar resultados.",
                    "{$t[3]} y reflexión final.",
                ],
            ],
        ],
    ];
}

function buildOrientacion(int $grade): array
{
    return [
        'title' => "ORIENTACIÓN EDUCATIVA {$grade}",
        'description' => "Temario de acompañamiento académico y vocacional para fortalecer hábitos de estudio, autorregulación y toma de decisiones.",
        'units' => [
            [
                'title' => 'Autoconocimiento y adaptación escolar',
                'objective' => 'Reconocer fortalezas personales y construir estrategias de adaptación al ciclo escolar.',
                'points' => [
                    'Diagnóstico personal de habilidades e intereses.',
                    'Metas académicas y plan de mejora.',
                    'Convivencia, comunicación y manejo emocional.',
                ],
            ],
            [
                'title' => 'Estrategias de aprendizaje',
                'objective' => 'Desarrollar hábitos de estudio y técnicas para mejorar rendimiento y permanencia escolar.',
                'points' => [
                    'Organización del tiempo y agenda académica.',
                    'Técnicas de estudio y comprensión lectora.',
                    'Manejo de estrés y preparación para evaluaciones.',
                ],
            ],
            [
                'title' => 'Proyecto vocacional',
                'objective' => 'Construir una ruta académica y profesional con base en intereses, contexto y oportunidades.',
                'points' => [
                    'Exploración vocacional y campos profesionales.',
                    'Toma de decisiones y plan de acción.',
                    'Portafolio de evidencias y seguimiento.',
                ],
            ],
        ],
    ];
}

function buildTutorias(int $grade): array
{
    return [
        'title' => "TUTORÍAS {$grade}",
        'description' => "Temario para acompañamiento integral del estudiante mediante seguimiento académico, socioemocional y de permanencia.",
        'units' => [
            [
                'title' => 'Diagnóstico y seguimiento inicial',
                'objective' => 'Identificar necesidades del grupo y establecer metas de acompañamiento.',
                'points' => [
                    'Integración grupal y clima escolar.',
                    'Detección de riesgos académicos y de asistencia.',
                    'Definición de acuerdos y metas de tutoría.',
                ],
            ],
            [
                'title' => 'Intervención y mejora',
                'objective' => 'Aplicar acciones de apoyo para fortalecer desempeño, hábitos y convivencia.',
                'points' => [
                    'Estrategias de recuperación académica.',
                    'Habilidades socioemocionales y resolución de conflictos.',
                    'Canalización y coordinación con áreas de apoyo.',
                ],
            ],
            [
                'title' => 'Cierre y proyección',
                'objective' => 'Evaluar avances y construir una ruta de continuidad académica y personal.',
                'points' => [
                    'Evaluación de logros y áreas de oportunidad.',
                    'Plan de continuidad y seguimiento.',
                    'Evidencias de tutoría y reflexión final.',
                ],
            ],
        ],
    ];
}

$subjects = Subject::query()->get(['id', 'name']);
$updated = [];
$missing = [];

foreach ($subjects as $subject) {
    $name = mb_strtoupper(trim((string) $subject->name), 'UTF-8');
    $data = null;

    if (preg_match('/^POTTENCIA\s+([IVX]+|\d+)$/u', $name, $m)) {
        $roman = $m[1];
        $map = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5, 'VI' => 6];
        $grade = is_numeric($roman) ? (int) $roman : ($map[$roman] ?? 1);
        $data = buildPottencia($grade);
    } elseif (preg_match('/^ORIENTACIÓN EDUCATIVA\s+([IVX]+|\d+)$/u', $name, $m) || preg_match('/^ORIENTACION EDUCATIVA\s+([IVX]+|\d+)$/u', $name, $m)) {
        $roman = $m[1];
        $map = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5, 'VI' => 6];
        $grade = is_numeric($roman) ? (int) $roman : ($map[$roman] ?? 1);
        $data = buildOrientacion($grade);
    } elseif (preg_match('/^TUTORÍAS\s+([IVX]+|\d+)$/u', $name, $m) || preg_match('/^TUTORIAS\s+([IVX]+|\d+)$/u', $name, $m)) {
        $roman = $m[1];
        $map = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5, 'VI' => 6];
        $grade = is_numeric($roman) ? (int) $roman : ($map[$roman] ?? 1);
        $data = buildTutorias($grade);
    }

    if (!$data) {
        continue;
    }

    DB::transaction(function () use ($subject, $data, &$updated) {
        $temario = Temario::query()
            ->where('subject_id', $subject->id)
            ->latest('id')
            ->first();

        if (!$temario) {
            $temario = Temario::create([
                'subject_id' => $subject->id,
                'title' => $data['title'],
                'description' => $data['description'],
            ]);
        } else {
            $temario->update([
                'title' => $data['title'],
                'description' => $data['description'],
            ]);
            $temario->points()->delete();
        }

        $rows = pointsFromUnits($data['units']);
        foreach ($rows as $idx => $row) {
            $temario->points()->create([
                'position' => $idx + 1,
                'label' => $row['label'],
                'level' => $row['level'],
                'type' => $row['type'],
                'content' => $row['content'],
            ]);
        }

        $updated[] = [
            'subject_id' => $subject->id,
            'subject_name' => $subject->name,
            'temario_id' => $temario->id,
            'title' => $temario->title,
        ];
    });
}

if (empty($updated)) {
    foreach (['POTTENCIA I-VI', 'ORIENTACIÓN EDUCATIVA I-VI', 'TUTORÍAS I-VI'] as $pattern) {
        $missing[] = $pattern;
    }
}

echo json_encode([
    'updated_count' => count($updated),
    'updated' => $updated,
    'missing_patterns' => $missing,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;

