<?php

use App\Models\DidacticPlan;
use App\Models\SchoolCycle;
use App\Models\TeachingAssignment;
use App\Models\TemarioPoint;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/summarize_oliver_plans_docx.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dryRun = in_array('--dry-run', $argv, true);
$folder = 'C:\\Users\\LapOne MX\\Desktop\\BACHILLERATO FLORIDA 26-3-20260727T081948Z-1-001\\BACHILLERATO FLORIDA 26-3\\CARPETAS ADMINISTRATIVAS DOCENTES\\OLIVER MARTINEZ ANAYA\\PLANEACIONES';

$files = [
    'FISICA I.docx' => 'FÍSICA I',
    'FISICA II.docx' => 'FÍSICA II',
    'GEOGRAFIA.docx' => 'GEOGRAFÍA',
];

function cell(array $row, int $index): string
{
    return trim((string) ($row[$index] ?? ''));
}

function parseDateValue(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    foreach (['d/m/Y', 'd/m/y'] as $format) {
        try {
            return Carbon::createFromFormat($format, $value)->toDateString();
        } catch (Throwable $e) {
        }
    }

    return null;
}

function cleanType(?string $value): string
{
    $value = trim((string) $value);
    $value = preg_replace('/\(.*/us', '', $value) ?: $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?: $value;

    return trim($value);
}

function parseUnitNumber(?string $value): ?string
{
    if (preg_match('/Unidad\s*([0-9]+)/iu', (string) $value, $matches) === 1) {
        return $matches[1];
    }

    if (preg_match('/^([0-9]+)(?:\.|\s)/u', trim((string) $value), $matches) === 1) {
        return $matches[1];
    }

    return null;
}

function parseNumerals(?string $value): array
{
    $matchCount = preg_match_all('/\b[0-9]+(?:\.[0-9]+)*\b/u', (string) $value, $matches);
    if ($matchCount === false || $matchCount === 0) {
        return [];
    }

    return array_values(array_unique($matches[0] ?? []));
}

function compactBibliography(?string $value): string
{
    $value = trim((string) $value);
    $value = preg_replace('/(?<=[a-záéíóúüñ])(?=[A-ZÁÉÍÓÚÜÑ][a-záéíóúüñ]+,\s)/u', "\n", $value) ?: $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?: $value;
    $value = preg_replace('/\s*;\s*/u', "\n", $value) ?: $value;

    return trim($value);
}

function parsePlanDocx(string $path): array
{
    $tables = docxTables($path);
    $subjectTable = $tables[2] ?? [];
    $unitsTable = $tables[4] ?? [];
    $periodsTable = $tables[5] ?? [];
    $evaluationTables = [7 => $tables[6] ?? [], 8 => $tables[7] ?? []];
    $criteriaTable = $tables[8] ?? [];
    $planningTables = [$tables[9] ?? [], $tables[10] ?? []];

    $subjectName = cell($subjectTable[0] ?? [], 1);
    $character = cell($subjectTable[1] ?? [], 1);
    $subjectKey = cell($subjectTable[1] ?? [], 3);
    $totalAnnualHours = (int) cell($subjectTable[2] ?? [], 1) ?: null;
    $objective = cell($subjectTable[4] ?? [], 1);
    $generalResources = '';
    $bibliography = '';

    $units = [];
    foreach (array_slice($unitsTable, 2) as $row) {
        $label = cell($row, 0);
        if ($label === '' || mb_stripos($label, 'totales') !== false || mb_stripos($label, 'observaciones') !== false) {
            continue;
        }

        $number = parseUnitNumber($label);
        $title = trim(preg_replace('/^Unidad\s*[0-9]+\.?\s*/iu', '', $label) ?: $label);
        $units[] = [
            'number' => $number,
            'title' => $title,
            'program_theory_hours' => cell($row, 1),
            'program_practice_hours' => cell($row, 2),
            'teacher_theory_hours' => cell($row, 3),
            'teacher_practice_hours' => cell($row, 4),
        ];
    }

    $periods = [];
    foreach (array_slice($periodsTable, 1) as $row) {
        if (cell($row, 0) === '') {
            continue;
        }

        $periods[] = [
            'period' => cell($row, 0),
            'unit_text' => cell($row, 1),
            'theory_dates' => cell($row, 2),
            'practices' => cell($row, 3),
        ];
    }

    $evaluationLines = [];
    foreach ($evaluationTables as $table) {
        $period = trim(preg_replace('/Periodo\s*/iu', 'Periodo ', cell($table[0] ?? [], 0)));
        $period = $period !== '' ? $period : 'Periodo';
        foreach (array_slice($table, 1) as $row) {
            $type = cleanType(cell($row, 1));
            $element = cell($row, 2);
            $weight = cell($row, 3);

            if ($element === '' && $weight === '') {
                continue;
            }

            if (str_contains($element, 'Examen') && preg_match('/^([0-9]{2})([0-9]{2})$/', $weight, $matches) === 1) {
                $evaluationLines[] = "{$period} - 2. Examen de periodo: Examen ({$matches[1]})";
                $evaluationLines[] = "{$period} - 2. Examen de periodo: Guía de estudio ({$matches[2]})";
                continue;
            }

            $type = $type !== '' ? $type : '1. Evaluación continua';
            $evaluationLines[] = "{$period} - {$type}: {$element}" . ($weight !== '' ? " ({$weight})" : '');
        }
    }

    $criteria = [];
    foreach ($criteriaTable as $row) {
        if (cell($row, 0) !== '' || cell($row, 1) !== '') {
            $criteria[] = ['label' => cell($row, 0), 'value' => cell($row, 1)];
        }
    }

    $items = [];
    $planningBlocks = [];
    foreach ($planningTables as $periodIndex => $table) {
        if ($table === []) {
            continue;
        }

        $blockObjective = cell($table[2] ?? [], 1);
        $blockContents = [
            'conceptual' => cell($table[5] ?? [], 0),
            'procedural' => cell($table[5] ?? [], 1),
            'attitudinal' => cell($table[5] ?? [], 2),
        ];
        $blockRows = [];

        foreach (array_slice($table, 8) as $row) {
            $position = cell($row, 0);
            if (! ctype_digit($position)) {
                if (mb_stripos(cell($row, 0), 'Recursos') !== false) {
                    $generalResources = cell($row, 0 + 0) === '' ? $generalResources : ($generalResources ?: cell($row, 0));
                }
                continue;
            }

            $date = parseDateValue(cell($row, 2));
            $numerals = parseNumerals(cell($row, 3));
            $unitNumber = $numerals !== [] ? explode('.', $numerals[0])[0] : null;
            $strategy = cell($row, 4);

            $item = [
                'position' => (int) $position,
                'date' => $date,
                'content' => cell($row, 3),
                'numerals' => $numerals,
                'unit_number' => $unitNumber,
                'objective' => $blockObjective,
                'opening' => $strategy,
                'development' => null,
                'closing' => null,
                'resources' => null,
                'evaluation' => null,
            ];

            $items[] = $item;
            $blockRows[] = $item;
        }

        $lastRow = end($table);
        if (is_array($lastRow) && count($lastRow) >= 2) {
            $generalResources = cell($lastRow, 0) ?: $generalResources;
            $bibliography = compactBibliography(cell($lastRow, 1)) ?: $bibliography;
        }

        $planningBlocks[] = [
            'period' => 'Periodo ' . ($periodIndex + 1),
            'units_title' => cell($table[1] ?? [], 1),
            'objective' => $blockObjective,
            'contents' => $blockContents,
            'rows' => $blockRows,
        ];
    }

    return [
        'subject_name' => $subjectName,
        'subject_character' => $character,
        'subject_key' => $subjectKey,
        'total_annual_hours' => $totalAnnualHours,
        'objective' => $objective,
        'units' => $units,
        'periods' => $periods,
        'evaluation_lines' => $evaluationLines,
        'criteria' => $criteria,
        'items' => $items,
        'planning_blocks' => $planningBlocks,
        'general_resources' => 'Pizarrón, proyector, internet',
        'bibliography' => $bibliography,
    ];
}

function normalizeSubject(string $value): string
{
    $value = mb_strtoupper($value, 'UTF-8');
    $value = strtr($value, [
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
    ]);
    $value = preg_replace('/[^A-Z0-9]+/u', '', $value) ?: $value;

    return $value;
}

function groupedByLabel($points)
{
    return collect($points)->mapWithKeys(function ($point) {
        $label = trim((string) ($point->label ?? ''));
        return $label !== '' ? ['label:' . $label => $point] : [];
    });
}

function saveParsedPlan(TeachingAssignment $assignment, SchoolCycle $cycle, array $parsed, bool $dryRun): array
{
    $assignment->loadMissing('subject.temarios.points', 'group');
    $points = $assignment->subject->temarios->flatMap(fn ($temario) => $temario->points);
    $pointsByLabel = groupedByLabel($points);
    $unitsByNumber = $points
        ->filter(fn ($point) => (int) $point->level === 1)
        ->mapWithKeys(fn ($point) => [preg_replace('/\D+/', '', (string) $point->label) => $point]);

    $plan = $assignment->didacticPlans()
        ->where('school_cycle_id', $cycle->id)
        ->first();

    if (! $plan) {
        $plan = new DidacticPlan(['teaching_assignment_id' => $assignment->id]);
    }

    if ($dryRun) {
        return ['plan_id' => $plan->exists ? $plan->id : null, 'items' => count($parsed['items'])];
    }

    $dates = collect($parsed['items'])->pluck('date')->filter()->sort()->values();

    DB::transaction(function () use ($plan, $assignment, $cycle, $parsed, $dates, $pointsByLabel, $unitsByNumber) {
        $plan->fill([
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'title' => 'Planeacion ' . ($assignment->subject->name ?? $parsed['subject_name']) . ' - Grupo ' . ($assignment->group->name ?? ''),
            'status' => DidacticPlan::STATUS_FINAL,
            'generated_by_system' => false,
            'generated_at' => null,
            'subject_character' => $parsed['subject_character'] ?: null,
            'subject_key' => $parsed['subject_key'] ?: null,
            'total_annual_hours' => $parsed['total_annual_hours'],
            'objective' => $parsed['objective'] ?: null,
            'evaluation_instruments' => implode("\n", $parsed['evaluation_lines']),
            'general_resources' => $parsed['general_resources'],
            'bibliography' => $parsed['bibliography'],
            'start_date' => $dates->first(),
            'end_date' => $dates->last(),
            'notes' => collect($parsed['criteria'])->map(fn ($row) => trim(($row['label'] ?: '') . ': ' . ($row['value'] ?: '')))->filter()->implode("\n"),
            'dgire_metadata' => [
                'source' => 'DOCX administrativo Oliver Martinez',
                'units' => $parsed['units'],
                'periods' => $parsed['periods'],
                'planning_blocks' => $parsed['planning_blocks'],
                'criteria' => $parsed['criteria'],
            ],
            'is_active' => true,
        ]);
        $plan->save();

        $plan->items()->delete();

        foreach ($parsed['items'] as $item) {
            $numeralPoints = collect($item['numerals'])
                ->map(fn ($label) => $pointsByLabel->get('label:' . $label))
                ->filter()
                ->values();

            $topicPoint = $numeralPoints->first(fn ($point) => (int) $point->level === 2)
                ?? $numeralPoints->first()
                ?? null;

            $plan->items()->create([
                'position' => $item['position'],
                'field_training_point_id' => $item['unit_number'] ? ($unitsByNumber->get($item['unit_number'])?->id) : null,
                'objective' => $item['objective'] ?: null,
                'temario_point_id' => $topicPoint?->id,
                'temario_subtopic_ids' => $numeralPoints->pluck('id')->values()->all(),
                'opening' => $item['opening'] !== '' ? $item['opening'] : '—',
                'development' => $item['development'],
                'closing' => $item['closing'],
                'resources' => $item['resources'],
                'evaluation' => $item['evaluation'],
                'start_date' => $item['date'],
                'end_date' => $item['date'],
            ]);
        }
    });

    return ['plan_id' => $plan->id, 'items' => count($parsed['items'])];
}

$cycle = SchoolCycle::where('code', '26-3')
    ->orWhere('name', 'like', '%26-3%')
    ->firstOrFail();

$teacher = App\Models\Teacher::whereHas('user', fn ($query) => $query->where('email', 'oliver.martinez@ula.com'))->firstOrFail();
$assignments = TeachingAssignment::query()
    ->with(['subject', 'group', 'schoolCycleGroup.schoolCycle'])
    ->where('teacher_id', $teacher->id)
    ->where('is_active', true)
    ->whereHas('schoolCycleGroup.schoolCycle', fn ($query) => $query->whereKey($cycle->id))
    ->get();

$planIds = DidacticPlan::query()
    ->whereIn('teaching_assignment_id', $assignments->pluck('id'))
    ->pluck('id');
$backup = [
    'created_at' => now()->toDateTimeString(),
    'cycle_id' => $cycle->id,
    'teacher_id' => $teacher->id,
    'plans' => DidacticPlan::whereIn('id', $planIds)->get()->toArray(),
    'items' => \App\Models\DidacticPlanItem::whereIn('didactic_plan_id', $planIds)->get()->toArray(),
];

$backupPath = database_path('backups/oliver_docx_plans_before_import_' . now()->format('Ymd_His') . '.json');
if (! is_dir(dirname($backupPath))) {
    mkdir(dirname($backupPath), 0777, true);
}
file_put_contents($backupPath, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo ($dryRun ? "DRY RUN\n" : "IMPORTANDO\n");
echo "Backup focal: {$backupPath}\n";

$results = [];
foreach ($files as $filename => $subjectName) {
    $path = $folder . '\\' . $filename;
    $parsed = parsePlanDocx($path);
    $matchingAssignments = $assignments
        ->filter(fn ($assignment) => normalizeSubject($assignment->subject->name ?? '') === normalizeSubject($subjectName))
        ->values();

    foreach ($matchingAssignments as $assignment) {
        $result = saveParsedPlan($assignment, $cycle, $parsed, $dryRun);
        $results[] = [
            'subject' => $assignment->subject->name,
            'group' => $assignment->group->name,
            'assignment_id' => $assignment->id,
            'plan_id' => $result['plan_id'],
            'items' => $result['items'],
        ];
    }
}

foreach ($results as $row) {
    echo implode(' | ', [
        $row['subject'],
        'Grupo ' . $row['group'],
        'assignment=' . $row['assignment_id'],
        'plan=' . ($row['plan_id'] ?: 'nuevo'),
        'items=' . $row['items'],
    ]) . PHP_EOL;
}
