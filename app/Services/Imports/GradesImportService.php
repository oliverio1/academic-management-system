<?php

namespace App\Services\Imports;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Activity;
use App\Models\CyclePartial;
use App\Models\EvaluationCriterion;
use App\Models\Grade;
use App\Models\Group;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GradesImportService
{
    private const MAX_WARNINGS = 200;

    public function import(UploadedFile $file, int $academicPeriodId, ?int $schoolCycleId = null): ImportResult
    {
        $result = new ImportResult();
        $period = AcademicPeriod::findOrFail($academicPeriodId);
        $spreadsheet = IOFactory::load($file->getRealPath());

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $sheetName = trim($worksheet->getTitle());
            if ($this->normalizeText($sheetName) === 'rubros') {
                continue;
            }

            try {
                $this->importSheet($worksheet, $sheetName, $period, $result, $schoolCycleId);
            } catch (\Throwable $e) {
                $result->addError("Hoja '{$sheetName}': {$e->getMessage()}");
            }
        }

        return $result;
    }

    protected function importSheet(
        Worksheet $sheet,
        string $sheetName,
        AcademicPeriod $period,
        ImportResult $result,
        ?int $schoolCycleId = null
    ): void {
        if ($sheetName === '') {
            return;
        }

        $assignment = $this->resolveTeachingAssignment($sheetName, (int) $period->id, $schoolCycleId);
        $rubricColumns = $this->detectRubricColumnsFromSheet($sheet);
        $activityColumns = $this->detectActivityColumns($sheet, $period, $rubricColumns);
        $rubrics = $this->extractRubricsFromSheet($sheet, $rubricColumns, $sheetName, $result);
        $criteriaByRubricColumn = $this->resolveCriteriaByRubric(
            $assignment,
            $period,
            $rubrics,
            $sheetName,
            $result
        );
        $continuaCriterion = $this->resolveContinuaCriterion(
            $assignment,
            $period,
            $criteriaByRubricColumn,
            $sheetName,
            $result
        );
        $sessions = AcademicSession::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('academic_period_id', $period->id)
            ->where('is_cancelled', false)
            ->when(
                $schoolCycleId,
                fn ($q) => $q->whereHas('schedule', fn ($qq) => $qq->where('school_cycle_id', $schoolCycleId))
            )
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get(['session_date']);

        $activityColumns = $this->assignMissingDueDates(
            $activityColumns,
            $sessions,
            $sheetName,
            $result
        );

        if (empty($activityColumns)) {
            $this->addLimitedWarning(
                $result,
                "Hoja '{$sheetName}': no se detectaron actividades para importar."
            );
            return;
        }

        $studentsByEnrollment = $this->studentsIndexForGroup($assignment->group_id);

        DB::transaction(function () use (
            $sheet,
            $sheetName,
            $period,
            $assignment,
            $continuaCriterion,
            $activityColumns,
            $rubrics,
            $criteriaByRubricColumn,
            $sessions,
            $studentsByEnrollment,
            $result
        ) {
            $activitiesByColumn = [];

            foreach ($activityColumns as $meta) {
                $activity = Activity::updateOrCreate(
                    [
                        'teaching_assignment_id' => $assignment->id,
                        'academic_period_id' => $period->id,
                        'title' => $meta['title'],
                        'due_date' => $meta['due_date'],
                    ],
                    [
                        'evaluation_criterion_id' => $continuaCriterion->id,
                        'max_score' => 10,
                        'evaluation_mode' => 'individual',
                        'is_active' => true,
                    ]
                );

                $activitiesByColumn[$meta['column']] = $activity;

                if ($activity->wasRecentlyCreated) {
                    $result->addCreated();
                } else {
                    $result->addUpdated();
                }
            }

            $summaryActivitiesByColumn = [];
            $fallbackDueDate = $sessions->last()?->session_date?->toDateString()
                ?? Carbon::parse($period->end_date)->toDateString();

            foreach ($rubrics as $idx => $rubricMeta) {
                if ($idx === 0) {
                    continue;
                }

                $summaryCriterion = $criteriaByRubricColumn[$rubricMeta['column']] ?? null;
                if (! $summaryCriterion) {
                    continue;
                }

                $activityTitle = $rubricMeta['name'];

                $activity = Activity::updateOrCreate(
                    [
                        'teaching_assignment_id' => $assignment->id,
                        'academic_period_id' => $period->id,
                        'title' => $activityTitle,
                        'due_date' => $fallbackDueDate,
                    ],
                    [
                        'evaluation_criterion_id' => $summaryCriterion->id,
                        'max_score' => 10,
                        'evaluation_mode' => 'individual',
                        'is_active' => true,
                    ]
                );

                $summaryActivitiesByColumn[$rubricMeta['column']] = $activity;

                if ($activity->wasRecentlyCreated) {
                    $result->addCreated();
                } else {
                    $result->addUpdated();
                }
            }

            $allActivitiesByColumn = $activitiesByColumn + $summaryActivitiesByColumn;

            $highestRow = $sheet->getHighestDataRow();

            for ($row = 4; $row <= $highestRow; $row++) { // Datos empiezan en fila 4
                $enrollmentRaw = trim((string) $sheet->getCellByColumnAndRow(3, $row)->getFormattedValue());
                if ($enrollmentRaw === '') {
                    continue;
                }

                $studentCandidates = $studentsByEnrollment[$this->normalizeEnrollment($enrollmentRaw)] ?? collect();

                if ($studentCandidates->count() === 0) {
                    $this->addLimitedWarning(
                        $result,
                        "Hoja '{$sheetName}': alumno no encontrado por matricula '{$enrollmentRaw}'."
                    );
                    continue;
                }

                if ($studentCandidates->count() > 1) {
                    $this->addLimitedWarning(
                        $result,
                        "Hoja '{$sheetName}': matricula ambigua '{$enrollmentRaw}' (multiples coincidencias)."
                    );
                    continue;
                }

                /** @var Student $student */
                $student = $studentCandidates->first();

                foreach ($allActivitiesByColumn as $column => $activity) {
                    $raw = trim((string) $sheet->getCellByColumnAndRow($column, $row)->getFormattedValue());
                    $score = $this->normalizeScore($raw);

                    if ($score === null) {
                        $result->addSkipped();
                        continue;
                    }

                    $grade = Grade::updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'activity_id' => $activity->id,
                        ],
                        [
                            'score' => $score,
                        ]
                    );

                    if ($grade->wasRecentlyCreated) {
                        $result->addCreated();
                    } else {
                        $result->addUpdated();
                    }
                }
            }
        });
    }

    /**
     * @return array<int, array{column:int,title:string,due_date:?string}>
     */
    protected function detectActivityColumns(Worksheet $sheet, AcademicPeriod $period, array $rubricColumns = []): array
    {
        $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $activities = [];
        $rubricColumnMap = array_fill_keys(array_map(fn ($column) => (int) $column, $rubricColumns), true);

        for ($col = 4; $col <= $highestColumn; $col++) { // Empezar desde columna D (4)
            if (isset($rubricColumnMap[$col])) {
                continue;
            }

            $titleRaw = trim((string) $sheet->getCellByColumnAndRow($col, 3)->getFormattedValue()); // Títulos en fila 3
            if ($titleRaw === '') {
                continue;
            }

            if ($this->isCalculatedColumn($titleRaw)) {
                continue;
            }

            $title = preg_replace('/\s+/u', ' ', $titleRaw) ?? $titleRaw;
            $title = trim($title);

            if ($title === '') {
                continue;
            }

            $dueDate = $this->extractDateFromTitle($title, $period);

            $activities[] = [
                'column' => $col,
                'title' => $title,
                'due_date' => $dueDate?->toDateString(),
            ];
        }

        return $activities;
    }

    protected function extractDateFromTitle(string $title, AcademicPeriod $period): ?Carbon
    {
        if (! preg_match('/\((\d{1,2})\/(\d{1,2})\/(\d{2,4})\)/u', $title, $m)) {
            return null;
        }

        $day = (int) $m[1];
        $month = (int) $m[2];
        $yearRaw = (int) $m[3];
        $year = $yearRaw < 100 ? (2000 + $yearRaw) : $yearRaw;

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        $date = Carbon::create($year, $month, $day)->startOfDay();
        $start = Carbon::parse($period->start_date)->startOfDay();
        $end = Carbon::parse($period->end_date)->endOfDay();

        return $date->between($start, $end) ? $date : null;
    }

    /**
     * @return array<int>
     */
    protected function detectRubricColumnsFromSheet(Worksheet $sheet): array
    {
        $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $columns = [];

        for ($col = 1; $col <= $highestColumn; $col++) {
            $pctRaw = trim((string) $sheet->getCellByColumnAndRow($col, 2)->getFormattedValue());
            if ($pctRaw === '') {
                continue;
            }

            $candidate = str_replace(['%', ','], ['', '.'], $pctRaw);
            if (! is_numeric($candidate)) {
                continue;
            }

            $columns[] = $col;
        }

        return $columns;
    }

    /**
     * @return array<int, array{column:int,kind:string}>
     */
    protected function detectSummaryGradeColumns(Worksheet $sheet, array $activityColumns = [], array $rubrics = []): array
    {
        $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $summary = [];

        // Las columnas de resumen empiezan después de actividades y rubros
        $startColumn = 4; // Columna D como mínimo
        if (!empty($activityColumns)) {
            $lastActivityColumn = max(array_column($activityColumns, 'column'));
            $startColumn = max($startColumn, $lastActivityColumn + 1);
        }
        if (!empty($rubrics)) {
            // Asumir que los rubros ocupan columnas consecutivas después de actividades
            $startColumn = max($startColumn, $startColumn + count($rubrics));
        }

        for ($col = $startColumn; $col <= $highestColumn; $col++) {
            $header = trim((string) $sheet->getCellByColumnAndRow($col, 1)->getFormattedValue());
            if ($header === '') {
                continue;
            }

            $normalized = $this->normalizeText($header);
            $kind = null;

            if ($normalized === 'guia' || $normalized === 'proyecto') {
                $kind = 'guia';
            } elseif ($normalized === 'examen') {
                $kind = 'examen';
            } elseif ($normalized === 'habitos' || $normalized === 'habito' || $normalized === 'habitos de estudio') {
                $kind = 'habitos';
            }

            if ($kind) {
                $summary[] = [
                    'column' => $col,
                    'kind' => $kind,
                ];
            }
        }

        // Si hay columnas repetidas del mismo tipo, nos quedamos con la primera.
        $byKind = [];
        foreach ($summary as $item) {
            if (! isset($byKind[$item['kind']])) {
                $byKind[$item['kind']] = $item;
            }
        }

        return array_values($byKind);
    }

    /**
     * @param array<int, array{column:int,title:string,due_date:?string}> $activityColumns
     * @return array<int, array{column:int,title:string,due_date:?string}>
     */
    protected function assignMissingDueDates(
        array $activityColumns,
        Collection $sessions,
        string $sheetName,
        ImportResult $result
    ): array {
        if (empty($activityColumns)) {
            return $activityColumns;
        }

        if ($sessions->isEmpty()) {
            $missing = collect($activityColumns)->whereNull('due_date')->count();
            if ($missing > 0) {
                $this->addLimitedWarning(
                    $result,
                    "Hoja '{$sheetName}': {$missing} actividades sin fecha y sin sesiones disponibles en el parcial."
                );
            }

            return $activityColumns;
        }

        $sessionDates = $sessions
            ->pluck('session_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->values();

        $lastIndex = max(0, $sessionDates->count() - 1);

        foreach ($activityColumns as $idx => $meta) {
            if (! empty($meta['due_date'])) {
                continue;
            }

            $sessionIndex = min($idx, $lastIndex);
            $activityColumns[$idx]['due_date'] = $sessionDates[$sessionIndex];
        }

        return $activityColumns;
    }

    protected function resolveCriterion(
        TeachingAssignment $assignment,
        array $rubrics,
        string $sheetName,
        ImportResult $result
    ): EvaluationCriterion
    {
        if (! empty($rubrics)) {
            foreach ($rubrics as $rubric) {
                EvaluationCriterion::updateOrCreate(
                    [
                        'teaching_assignment_id' => $assignment->id,
                        'name' => $rubric['name'],
                    ],
                    [
                        'percentage' => $rubric['percentage'],
                    ]
                );
            }
        }

        $criteria = EvaluationCriterion::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->get();

        if ($criteria->isEmpty()) {
            $fallback = EvaluationCriterion::create([
                'teaching_assignment_id' => $assignment->id,
                'name' => 'Evaluacion',
                'percentage' => 100,
            ]);

            $result->addWarning(
                "Hoja '{$sheetName}': no habia rubros. Se creo 'Evaluacion' al 100%."
            );

            return $fallback;
        }

        $preferred = $criteria->first(function (EvaluationCriterion $criterion) {
            $name = $this->normalizeText($criterion->name);
            return in_array($name, ['evaluacion', 'evaluacion academica'], true);
        });

        if ($preferred) {
            return $preferred;
        }

        $nonAttendance = $criteria->first(fn (EvaluationCriterion $criterion) => ! $criterion->isAttendance());
        if ($nonAttendance) {
            return $nonAttendance;
        }

        return $criteria->first();
    }

    /**
     * @return array<int, array{name:string,percentage:float}>
     */
    protected function extractRubricsFromWorkbook(Spreadsheet $spreadsheet, ImportResult $result): array
    {
        // Este método ya no se usa, los rubros se extraen por hoja
        return [];
    }

    protected function extractRubricsFromSheet(
        Worksheet $sheet,
        array $rubricColumns,
        string $sheetName,
        ImportResult $result
    ): array
    {
        $rubrics = [];

        foreach ($rubricColumns as $idx => $col) {
            $cell = $sheet->getCellByColumnAndRow($col, 2);
            $pctRaw = trim((string) $cell->getFormattedValue());
            $nameRaw = trim((string) $sheet->getCellByColumnAndRow($col, 3)->getFormattedValue());

            if ($pctRaw === '') {
                continue;
            }

            $pctRaw = str_replace('%', '', $pctRaw);
            $pctRaw = str_replace(',', '.', $pctRaw);

            if (! is_numeric($pctRaw)) {
                continue;
            }

            $percentage = (float) $pctRaw;
            if ($percentage < 0 || $percentage > 100) {
                continue;
            }

            $normalizedName = $this->normalizeText($nameRaw);
            $isTotal = in_array($normalizedName, ['final', 'promedio', 'total'], true);
            $formula = (string) $cell->getValue();
            if ($isTotal || str_starts_with($formula, '=')) {
                continue;
            }

            if ($idx === 0) {
                $nameRaw = 'Evaluación continua';
            }

            if ($nameRaw === '') {
                $nameRaw = $idx === 0 ? 'Evaluación continua' : ('Rubro ' . ($idx + 1));
            }

            $rubrics[] = [
                'column' => (int) $col,
                'name' => trim($nameRaw),
                'percentage' => round($percentage, 2),
            ];
        }

        if (empty($rubrics)) {
            $result->addWarning("Hoja '{$sheetName}': no se detectaron rubros por fila 2/fila 3.");
        }

        return $rubrics;
    }

    /**
     * @param array<int, array{column:int,name:string,percentage:float}> $rubrics
     * @return array<int, EvaluationCriterion>
     */
    protected function resolveCriteriaByRubric(
        TeachingAssignment $assignment,
        AcademicPeriod $period,
        array $rubrics,
        string $sheetName,
        ImportResult $result
    ): array {
        $partialId = $this->resolveCyclePartialIdForPeriod($assignment, (int) $period->id);
        $criteriaByColumn = [];

        foreach ($rubrics as $rubric) {
            $criterion = EvaluationCriterion::updateOrCreate(
                [
                    'teaching_assignment_id' => $assignment->id,
                    'cycle_partial_id' => $partialId,
                    'name' => $rubric['name'],
                ],
                [
                    'percentage' => $rubric['percentage'],
                ]
            );

            $criteriaByColumn[(int) $rubric['column']] = $criterion;
        }

        if (empty($criteriaByColumn)) {
            $fallback = EvaluationCriterion::firstOrCreate(
                [
                    'teaching_assignment_id' => $assignment->id,
                    'cycle_partial_id' => $partialId,
                    'name' => 'Evaluación continua',
                ],
                [
                    'percentage' => 100,
                ]
            );

            $criteriaByColumn[0] = $fallback;
            $result->addWarning(
                "Hoja '{$sheetName}': sin rubros válidos, se creó 'Evaluación continua' al 100%."
            );
        }

        return $criteriaByColumn;
    }

    protected function resolveContinuaCriterion(
        TeachingAssignment $assignment,
        AcademicPeriod $period,
        array $criteriaByRubricColumn,
        string $sheetName,
        ImportResult $result
    ): EvaluationCriterion {
        $firstCriterion = reset($criteriaByRubricColumn);
        if ($firstCriterion instanceof EvaluationCriterion) {
            return $firstCriterion;
        }

        $partialId = $this->resolveCyclePartialIdForPeriod($assignment, (int) $period->id);
        $criterion = EvaluationCriterion::firstOrCreate(
            [
                'teaching_assignment_id' => $assignment->id,
                'cycle_partial_id' => $partialId,
                'name' => 'Evaluación continua',
            ],
            [
                'percentage' => 100,
            ]
        );

        $result->addWarning(
            "Hoja '{$sheetName}': no se encontró rubro inicial; se usará 'Evaluación continua'."
        );

        return $criterion;
    }

    protected function resolveCyclePartialIdForPeriod(TeachingAssignment $assignment, int $academicPeriodId): ?int
    {
        $cycleId = (int) (
            optional($assignment->schoolCycleGroup)->school_cycle_id
            ?: $assignment->schedules()
                ->where('is_active', true)
                ->orderByDesc('school_cycle_id')
                ->value('school_cycle_id')
        );

        if ($cycleId <= 0) {
            return null;
        }

        $partialId = CyclePartial::query()
            ->where('school_cycle_id', $cycleId)
            ->where('academic_period_id', $academicPeriodId)
            ->orderBy('sort_order')
            ->value('id');

        return $partialId ? (int) $partialId : null;
    }

    protected function normalizeScore(string $raw): ?float
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        $upper = mb_strtoupper($value);
        if (in_array($upper, ['N/A', 'NA', 'X'], true)) {
            return null;
        }

        if (in_array($upper, ['SI', 'SÍ', 'J', 'EX'], true)) {
            return 10.0;
        }

        $numericCandidate = str_replace(',', '.', $value);
        if (! is_numeric($numericCandidate)) {
            return null;
        }

        $score = (float) $numericCandidate;
        if ($score < 0) {
            $score = 0.0;
        }
        if ($score > 10) {
            $score = 10.0;
        }

        return round($score, 2);
    }

    protected function resolveSummaryCriterion(
        Collection $criteria,
        string $kind,
        EvaluationCriterion $fallback
    ): EvaluationCriterion {
        $aliases = match ($kind) {
            'guia' => ['guia', 'proyecto'],
            'examen' => ['examen'],
            'habitos' => ['habitos', 'habitos de estudio'],
            default => [$kind],
        };

        foreach ($criteria as $criterion) {
            $name = $this->normalizeText((string) $criterion->name);
            if (in_array($name, $aliases, true)) {
                return $criterion;
            }
        }

        return $fallback;
    }

    protected function isCalculatedColumn(string $name): bool
    {
        $upper = $this->normalizeText($name);
        return str_contains($upper, 'final')
            || str_contains($upper, 'promedio')
            || str_contains($upper, 'continua')
            || str_contains($upper, 'pedagogica')
            || str_contains($upper, 'proyecto')
            || str_contains($upper, 'examen')
            || str_contains($upper, 'habitos')
            || str_contains($upper, 'evaluacion');
    }

    protected function resolveTeachingAssignment(
        string $sheetName,
        int $academicPeriodId,
        ?int $schoolCycleId = null
    ): TeachingAssignment
    {
        if (! str_contains($sheetName, '-')) {
            throw new \RuntimeException('Formato de hoja inválido. Se esperaba GRUPO-MATERIA.');
        }

        [$groupPart, $subjectPart] = array_map('trim', explode('-', $sheetName, 2));

        $group = $this->resolveGroupBySheetGroup($groupPart, $schoolCycleId);

        if (! $group) {
            throw new \RuntimeException("Grupo '{$groupPart}' no encontrado.");
        }

        $targetSubjectName = $this->normalizeText($subjectPart);
        $allSubjects = Subject::query()->get();
        
        // Intenta coincidencia exacta primero
        $subject = $allSubjects->first(fn (Subject $s) => 
            $this->normalizeText((string) $s->name) === $targetSubjectName
        );

        // Si no encuentra, intenta búsqueda más flexible: cualquier palabra que coincida
        if (! $subject) {
            $words = array_filter(explode(' ', $targetSubjectName));
            $subject = $allSubjects->first(function (Subject $s) use ($words) {
                $normalizedSubjectName = $this->normalizeText((string) $s->name);
                foreach ($words as $word) {
                    if (strlen($word) > 2 && str_contains($normalizedSubjectName, $word)) {
                        return true;
                    }
                }
                return false;
            });
        }

        // Si aún no encuentra, intenta búsqueda por similitud (Levenshtein)
        if (! $subject) {
            $subject = $allSubjects->first(function (Subject $s) use ($targetSubjectName) {
                $normalizedSubjectName = $this->normalizeText((string) $s->name);
                
                // Calcular distancia de edición
                $distance = levenshtein($targetSubjectName, $normalizedSubjectName);
                $maxDistance = max(3, min(5, strlen($targetSubjectName) * 0.3)); // 30% de la longitud
                
                // También verificar si contiene las palabras clave principales
                $targetWords = array_filter(explode(' ', $targetSubjectName), fn($w) => strlen($w) > 2);
                $subjectWords = array_filter(explode(' ', $normalizedSubjectName), fn($w) => strlen($w) > 2);
                $commonWords = count(array_intersect($targetWords, $subjectWords));
                
                return $distance <= $maxDistance || $commonWords >= 2;
            });
        }

        if (! $subject) {
            // Mensaje mejorado con sugerencias
            $availableSubjects = $allSubjects->map(fn (Subject $s) => $s->name)->join(', ');
            throw new \RuntimeException(
                "Materia '{$subjectPart}' no encontrada. Posibles coincidencias: {$availableSubjects}"
            );
        }

        $assignment = TeachingAssignment::query()
            ->where('group_id', $group->id)
            ->where('subject_id', $subject->id)
            ->when(
                $schoolCycleId,
                fn ($q) => $q->whereHas('schedules', fn ($qq) => $qq->where('school_cycle_id', $schoolCycleId))
            )
            ->withCount([
                'academicSessions as sessions_in_period_count' => function ($q) use ($academicPeriodId) {
                    $q->where('academic_period_id', $academicPeriodId)
                        ->where('is_cancelled', false);
                },
            ])
            ->orderByDesc('sessions_in_period_count')
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->first();

        if (! $assignment) {
            throw new \RuntimeException("No existe asignación para '{$sheetName}'.");
        }

        return $assignment;
    }

    protected function resolveGroupBySheetGroup(string $groupPart, ?int $schoolCycleId = null): ?Group
    {
        $normalizedTarget = $this->normalizeText($groupPart);

        if ($schoolCycleId) {
            $cycleGroupIds = SchoolCycleGroup::query()
                ->where('school_cycle_id', $schoolCycleId)
                ->where('is_active', true)
                ->pluck('group_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (! empty($cycleGroupIds)) {
                $cycleGroups = Group::query()
                    ->whereIn('id', $cycleGroupIds)
                    ->get();

                $exact = $cycleGroups->first(function (Group $group) use ($normalizedTarget) {
                    return $this->normalizeText((string) $group->name) === $normalizedTarget;
                });

                if ($exact) {
                    return $exact;
                }

                $prefixed = $cycleGroups->first(function (Group $group) use ($normalizedTarget) {
                    $normalizedName = $this->normalizeText((string) $group->name);
                    return str_starts_with($normalizedName, $normalizedTarget . ' -');
                });

                if ($prefixed) {
                    return $prefixed;
                }
            }

            return null;
        }

        // Búsqueda por coincidencia exacta o por prefijo
        $normalizedGroupPart = mb_strtolower(trim($groupPart));
        $groups = Group::query()->get();

        // Intenta coincidencia exacta primero
        $exact = $groups->first(function (Group $group) use ($normalizedGroupPart) {
            return mb_strtolower(trim($group->name)) === $normalizedGroupPart;
        });

        if ($exact) {
            return $exact;
        }

        // Intenta búsqueda con prefijo (ej: "5120" encuentra "5120 - 26-2")
        return $groups->first(function (Group $group) use ($normalizedGroupPart) {
            $normalizedName = mb_strtolower(trim($group->name));
            return str_starts_with($normalizedName, $normalizedGroupPart . ' ');
        });
    }

    /**
     * @return array<string, Collection<int, Student>>
     */
    protected function studentsIndexForGroup(int $groupId): array
    {
        $index = [];
        $students = Student::query()
            ->where('group_id', $groupId)
            ->get();

        foreach ($students as $student) {
            $key = $this->normalizeEnrollment((string) ($student->enrollment_number ?? ''));
            if ($key === '') {
                continue;
            }

            if (! isset($index[$key])) {
                $index[$key] = collect();
            }

            $index[$key]->push($student);
        }

        return $index;
    }

    protected function normalizeEnrollment(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', '', $value) ?? $value;
        return mb_strtoupper($value);
    }

    protected function normalizeText(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = mb_strtolower($value);

        // Mapa de caracteres con acentos a sin acentos
        $accentMap = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'ñ' => 'n',
            'ç' => 'c',
        ];

        $value = strtr($value, $accentMap);

        // Intentar iconv como respaldo
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) {
            $value = $ascii;
        }

        return trim($value);
    }

    protected function addLimitedWarning(ImportResult $result, string $message): void
    {
        if (count($result->warnings) < self::MAX_WARNINGS) {
            $result->addWarning($message);
        }
    }
}
