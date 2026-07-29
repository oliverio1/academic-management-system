<?php

namespace App\Console\Commands;

use App\Http\Controllers\TeacherDidacticPlanController;
use App\Models\AcademicPeriod;
use App\Models\CyclePartial;
use App\Models\SchoolCycle;
use App\Models\TeachingAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ReflectionClass;
use ZipArchive;

class GenerateLegacyPlanningTemplateCommand extends Command
{
    protected $signature = 'ams:generate-legacy-planning-template
        {assignment_id=683 : ID de la asignacion docente}
        {--legacy-doc= : DOCX de planeacion anterior para tomar estilo de actividades}
        {--output= : Ruta de salida del XLSX}';

    protected $description = 'Genera una plantilla de planeacion actualizada usando el ciclo vigente y una planeacion DOCX anterior como referencia.';

    public function handle(): int
    {
        $assignment = TeachingAssignment::query()
            ->with(['teacher.user', 'subject', 'group.level', 'schoolCycleGroup.schoolCycle'])
            ->findOrFail((int) $this->argument('assignment_id'));

        $this->fixSubjectTemarioLevels((int) $assignment->subject_id);

        $controller = app(TeacherDidacticPlanController::class);
        $reflection = new ReflectionClass($controller);

        [$unitOptions, $topicOptions, $subtopicOptions] = $this->callPrivate($reflection, $controller, 'buildTemarioSelectors', [$assignment]);
        $cycles = $this->callPrivate($reflection, $controller, 'cyclesForAssignment', [$assignment]);
        $defaultCycle = $this->callPrivate($reflection, $controller, 'defaultCycleForAssignment', [$assignment, $cycles]);

        $periods = AcademicPeriod::query()
            ->where('modality_id', $assignment->group->level->modality_id)
            ->orderBy('start_date')
            ->get();

        $spreadsheet = $this->callPrivate($reflection, $controller, 'buildPlanningTemplateWorkbook', [
            $assignment,
            $unitOptions,
            $topicOptions,
            $subtopicOptions,
            $cycles,
            $defaultCycle,
            $periods,
        ]);

        $legacyDoc = (string) ($this->option('legacy-doc') ?: '');
        $legacy = $legacyDoc !== '' && is_file($legacyDoc)
            ? $this->parseLegacyDocx($legacyDoc)
            : ['strategies' => []];

        $this->completeInstitutionSheet($spreadsheet, $assignment, $defaultCycle);
        $this->completeSubjectSheet($spreadsheet, $assignment);
        $this->completeEvaluationSheet($spreadsheet);
        $this->completePlanningSheet($spreadsheet, $legacy['strategies'] ?? []);
        $this->completeCycleSheet($spreadsheet, $assignment, $defaultCycle);

        $output = (string) ($this->option('output') ?: '');
        if ($output === '') {
            $output = 'C:\\Users\\LapOne MX\\Desktop\\plantilla-planeacion-matematicas-v-5001-2026-2027.xlsx';
        }

        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0777, true);
        }

        (new Xlsx($spreadsheet))->save($output);

        $parsed = $this->callPrivate($reflection, $controller, 'parsePlanningWorkbook', [$output, $assignment]);

        $this->info('Plantilla generada: ' . $output);
        $this->line('Sesiones importables: ' . count($parsed['items']));

        return self::SUCCESS;
    }

    private function callPrivate(ReflectionClass $reflection, object $instance, string $method, array $arguments = []): mixed
    {
        $refMethod = $reflection->getMethod($method);
        $refMethod->setAccessible(true);

        return $refMethod->invokeArgs($instance, $arguments);
    }

    private function fixSubjectTemarioLevels(int $subjectId): void
    {
        $points = DB::table('temario_points')
            ->join('temarios', 'temarios.id', '=', 'temario_points.temario_id')
            ->where('temarios.subject_id', $subjectId)
            ->where('temario_points.level', '>', 1)
            ->select('temario_points.id', 'temario_points.label')
            ->get();

        foreach ($points as $point) {
            $level = max(2, substr_count((string) $point->label, '.') + 1);
            DB::table('temario_points')->where('id', (int) $point->id)->update(['level' => $level]);
        }
    }

    private function completeInstitutionSheet($spreadsheet, TeachingAssignment $assignment, ?SchoolCycle $cycle): void
    {
        $sheet = $spreadsheet->getSheetByName('Institucion');
        if (! $sheet) {
            return;
        }

        $sameSubjectGroups = TeachingAssignment::query()
            ->join('school_cycle_groups as scg', 'scg.id', '=', 'teaching_assignments.school_cycle_group_id')
            ->join('groups as g', 'g.id', '=', 'teaching_assignments.group_id')
            ->where('teaching_assignments.subject_id', $assignment->subject_id)
            ->where('teaching_assignments.teacher_id', $assignment->teacher_id)
            ->when($cycle, fn ($query) => $query->where('scg.school_cycle_id', $cycle->id))
            ->where('teaching_assignments.is_active', true)
            ->distinct()
            ->orderBy('g.name')
            ->pluck('g.name')
            ->values();

        $teacherName = $assignment->teacher->user->name ?? '';

        $sheet->setCellValue('B3', '1183');
        $sheet->setCellValue('B4', $cycle?->name ?? $sheet->getCell('B4')->getValue());
        $sheet->setCellValue('B5', $teacherName);
        $sheet->setCellValue('B6', '24012324');
        $sheet->setCellValue('B7', now()->format('Y-m-d'));
        $sheet->setCellValue('B9', 'Miriam Paola Perez Luna');
        $sheet->setCellValue('B10', $sameSubjectGroups->count() ?: 1);
        $sheet->setCellValue('B11', $sameSubjectGroups->isNotEmpty() ? $sameSubjectGroups->implode(', ') : ($assignment->group->name ?? ''));
    }

    private function completeSubjectSheet($spreadsheet, TeachingAssignment $assignment): void
    {
        $sheet = $spreadsheet->getSheetByName('Materia');
        if (! $sheet) {
            return;
        }

        $temario = $assignment->temarios()->latest()->first();
        $objective = trim((string) ($temario?->description ?? ''));

        $sheet->setCellValue('B12', $objective);
        $sheet->setCellValue('B13', implode("\n", [
            'Bello, I. (2009). Algebra Intermedia. Un enfoque del mundo real. Mexico: Mc Graw Hill.',
            'Alexander, C., y Koeberlein, M. (2013). Geometria. Mexico: Cengage Learning.',
            'Ruiz, J. (2006). Geometria Analitica. Mexico: Publicaciones Cultural.',
        ]));
        $sheet->setCellValue('B14', 'Pizarron, internet, proyector, plumones, software, GeoGebra.');
    }

    private function completeEvaluationSheet($spreadsheet): void
    {
        $sheet = $spreadsheet->getSheetByName('Evaluacion');
        if (! $sheet) {
            return;
        }

        for ($headerRow = 1; $headerRow <= $sheet->getHighestRow(); $headerRow++) {
            $period = trim((string) $sheet->getCell('A' . $headerRow)->getValue());
            if (! str_starts_with($period, 'Periodo ')) {
                continue;
            }

            $rows = [
                ['Evaluacion continua', 'Trabajo en aula', '40%'],
                ['Evaluacion continua', 'Portafolio de periodo', '10%'],
                ['Evaluacion continua', 'Habitos', '10%'],
                ['Examen de periodo', 'Examen', '40%'],
            ];

            foreach ($rows as $offset => $data) {
                $row = $headerRow + 1 + $offset;
                $sheet->setCellValue('B' . $row, $data[0]);
                $sheet->setCellValue('C' . $row, $data[1]);
                $sheet->setCellValue('D' . $row, $data[2]);
            }
        }
    }

    private function completePlanningSheet($spreadsheet, array $legacyStrategies): void
    {
        $sheet = $spreadsheet->getSheetByName('Planeacion');
        if (! $sheet) {
            return;
        }

        for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
            $content = trim((string) $sheet->getCell('F' . $row)->getFormattedValue());
            $key = $this->baseContentKey($content);

            $strategy = $legacyStrategies[$key] ?? null;
            if (! $strategy) {
                $strategy = $this->defaultStrategyForKey($key);
            }

            $sheet->setCellValue('G' . $row, $strategy);
        }
    }

    private function completeCycleSheet($spreadsheet, TeachingAssignment $assignment, ?SchoolCycle $cycle): void
    {
        $sheet = $spreadsheet->getSheetByName('Ciclo parciales');
        $planning = $spreadsheet->getSheetByName('Planeacion');
        if (! $sheet || ! $planning || ! $cycle) {
            return;
        }

        $partials = CyclePartial::query()
            ->where('school_cycle_id', $cycle->id)
            ->orderBy('sort_order')
            ->orderBy('start_date')
            ->take(4)
            ->get()
            ->values();

        foreach ($partials as $index => $partial) {
            $row = $index + 2;
            $keys = [];
            $dates = [];

            for ($planningRow = 2; $planningRow <= $planning->getHighestRow(); $planningRow++) {
                $date = trim((string) $planning->getCell('E' . $planningRow)->getFormattedValue());
                if ($date === '' || $date < optional($partial->start_date)->format('Y-m-d') || $date > optional($partial->end_date)->format('Y-m-d')) {
                    continue;
                }

                $dates[] = $date;
                $unit = explode('.', $this->baseContentKey((string) $planning->getCell('F' . $planningRow)->getFormattedValue()))[0] ?? '';
                if ($unit !== '' && ctype_digit($unit)) {
                    $keys[] = 'Unidad ' . ((int) $unit);
                }
            }

            $dates = collect($dates)->filter()->sort()->values();
            $sheet->setCellValue('A' . $row, $partial->name ?: ('Periodo ' . ($index + 1)));
            $sheet->setCellValue('B' . $row, collect($keys)->unique()->values()->implode(', '));
            $sheet->setCellValue('C' . $row, $dates->isNotEmpty() ? $dates->first() . ' a ' . $dates->last() : '');
        }
    }

    private function parseLegacyDocx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return ['strategies' => []];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            return ['strategies' => []];
        }

        $document = new \DOMDocument();
        $document->loadXML($xml);
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = [];
        foreach ($xpath->query('//w:p') as $paragraph) {
            $text = '';
            foreach ($xpath->query('.//w:t', $paragraph) as $node) {
                $text .= $node->textContent;
            }

            $text = trim(preg_replace('/\s+/u', ' ', $text) ?: '');
            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }

        $strategies = [];
        $start = null;
        foreach ($paragraphs as $index => $paragraph) {
            if (str_contains($paragraph, 'Instrumento de evaluación')) {
                $start = $index + 1;
                break;
            }
        }

        if ($start === null) {
            return ['strategies' => []];
        }

        for ($index = $start; $index + 6 < count($paragraphs); $index += 7) {
            $date = $paragraphs[$index + 1] ?? '';
            $content = $paragraphs[$index + 3] ?? '';
            $activity = $paragraphs[$index + 4] ?? '';

            if (! preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
                continue;
            }

            $key = $this->baseContentKey($content);
            if ($key !== '' && $activity !== '' && ! isset($strategies[$key])) {
                $strategies[$key] = $activity;
            }
        }

        return ['strategies' => $strategies];
    }

    private function baseContentKey(string $content): string
    {
        if (str_contains(mb_strtolower($content, 'UTF-8'), 'encuadre')) {
            return 'encuadre';
        }

        if (preg_match('/([0-9]+(?:\.[0-9]+)+)/u', $content, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/\b([0-9]+)\b/u', $content, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    private function defaultStrategyForKey(string $key): string
    {
        if ($key === 'encuadre') {
            return 'Presentacion del programa, criterios de evaluacion y forma de trabajo. Recuperacion de expectativas del grupo y acuerdos de convivencia academica.';
        }

        $unit = (int) (explode('.', $key)[0] ?? 0);

        return match ($unit) {
            1 => 'Actividad de indagacion y resolucion colaborativa de problemas geometricos. El docente guia la formalizacion de conceptos y los alumnos registran procedimientos y conclusiones.',
            2 => 'Exposicion guiada con ejemplos de geometria analitica, seguida de ejercicios practicos en parejas y contraste de procedimientos en plenaria.',
            3 => 'Modelacion de situaciones mediante funciones, analisis de representaciones tabulares, graficas y algebraicas, y uso de tecnologia digital cuando sea pertinente.',
            4 => 'Analisis de datos contextualizados, interpretacion de graficas y resolucion de ejercicios con argumentacion de resultados.',
            default => 'Lectura guiada, explicacion dialogada y resolucion de ejercicios para consolidar el contenido programado.',
        };
    }
}
