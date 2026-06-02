<?php

namespace App\Services\Imports;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Attendance;
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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceImportService
{
    private const MAX_WARNINGS = 200;

    public function import(UploadedFile $file, int $academicPeriodId, ?int $schoolCycleId = null): ImportResult
    {
        $result = new ImportResult();
        $period = AcademicPeriod::findOrFail($academicPeriodId);

        $spreadsheet = IOFactory::load($file->getRealPath());

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $sheetName = trim($worksheet->getTitle());

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
        if ($sheetName === '' || str_starts_with($sheetName, '_')) {
            return;
        }

        $assignment = $this->resolveTeachingAssignment($sheetName, (int) $period->id, $schoolCycleId);

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
            ->get();

        if ($sessions->isEmpty()) {
            $this->addLimitedWarning(
                $result,
                "Hoja '{$sheetName}': no hay sesiones en el periodo {$period->name}. Se omite."
            );
            return;
        }

        $sessionsByDate = $sessions
            ->groupBy(fn (AcademicSession $s) => $s->session_date->toDateString());

        $columns = $this->buildAttendanceColumns($sheet, $period);
        if (empty($columns)) {
            $this->addLimitedWarning(
                $result,
                "Hoja '{$sheetName}': no se detectaron columnas de fechas válidas."
            );
            return;
        }

        $studentsByEnrollment = $this->studentsIndexForGroup($assignment->group_id);

        $highestRow = $sheet->getHighestDataRow();

        DB::transaction(function () use (
            $sheet,
            $sheetName,
            $columns,
            $studentsByEnrollment,
            $sessionsByDate,
            $highestRow,
            $result
        ) {
            for ($row = 4; $row <= $highestRow; $row++) { // Datos empiezan en fila 4
                $enrollmentRaw = trim((string) $sheet->getCellByColumnAndRow(3, $row)->getFormattedValue());
                if ($enrollmentRaw === '') {
                    continue;
                }

                $normalizedEnrollment = $this->normalizeEnrollment($enrollmentRaw);
                $studentCandidates = $studentsByEnrollment[$normalizedEnrollment] ?? collect();

                // DEBUG: Log temporal para estudiantes
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

                foreach ($columns as $colMeta) {
                    $raw = trim((string) $sheet->getCellByColumnAndRow($colMeta['column'], $row)->getFormattedValue());
                    $value = mb_strtolower($raw);

                    if ($value === '' || $value === 'x' || $value === 'na' || $value === 'n/a') {
                        $result->addSkipped();
                        continue;
                    }

                    $status = match ($value) {
                        '1' => 'present',
                        '0' => 'absent',
                        '2', 'j' => 'justified',
                        default => null,
                    };

                    if (! $status) {
                        $this->addLimitedWarning(
                            $result,
                            "Hoja '{$sheetName}': valor de asistencia invalido '{$raw}' para matricula {$enrollmentRaw} ({$colMeta['date']})."
                        );
                        $result->addSkipped();
                        continue;
                    }

                    /** @var Collection<int, AcademicSession> $daySessions */
                    $daySessions = $sessionsByDate->get($colMeta['date'], collect());

                    if ($daySessions->isEmpty()) {
                        $result->addSkipped();
                        continue;
                    }

                    foreach ($daySessions as $session) {
                        $attendance = Attendance::updateOrCreate(
                            [
                                'academic_session_id' => $session->id,
                                'student_id' => $student->id,
                            ],
                            [
                                'status' => $status,
                            ]
                        );

                        if ($attendance->wasRecentlyCreated) {
                            $result->addCreated();
                        } else {
                            $result->addUpdated();
                        }
                    }
                }
            }
        });
    }

    /**
     * @return array<int, array{column:int,date:string}>
     */
    protected function buildAttendanceColumns(Worksheet $sheet, AcademicPeriod $period): array
    {
        $columns = [];
        $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $headerRows = $this->detectAttendanceHeaderRows($sheet);

        if ($headerRows !== null) {
            [$monthRow, $dayRow] = $headerRows;

            for ($col = 4; $col <= $highestColumn; $col++) { // Empezar desde columna D (4)
                $monthCell = trim((string) $sheet->getCellByColumnAndRow($col, $monthRow)->getFormattedValue());
                if ($monthCell !== '') {
                    $candidateMonth = $this->monthNumberFromName($monthCell);
                    if ($candidateMonth !== null) {
                        $monthCursor = $candidateMonth;
                    }
                }

                $dayCell = trim((string) $sheet->getCellByColumnAndRow($col, $dayRow)->getFormattedValue());
                if (! is_numeric($dayCell)) {
                    continue;
                }

                $day = (int) $dayCell;
                if ($day < 1 || $day > 31 || empty($monthCursor)) {
                    continue;
                }

                $date = $this->resolveDateWithinPeriod($period, $monthCursor, $day);
                if (! $date) {
                    continue;
                }

                $columns[] = [
                    'column' => $col,
                    'date' => $date->toDateString(),
                ];
            }
        }

        if (empty($columns)) {
            for ($row = 1; $row <= 3; $row++) {
                for ($col = 4; $col <= $highestColumn; $col++) { // Empezar desde columna D (4)
                    $header = trim((string) $sheet->getCellByColumnAndRow($col, $row)->getFormattedValue());
                    $date = $this->parseAttendanceHeaderDate($header, $period);
                    if (! $date) {
                        continue;
                    }

                    $columns[] = [
                        'column' => $col,
                        'date' => $date->toDateString(),
                    ];
                }

                if (! empty($columns)) {
                    break;
                }
            }
        }

        return $columns;
    }

    protected function detectAttendanceHeaderRows(Worksheet $sheet): ?array
    {
        // Intentar primero el formato específico: meses en fila 2, días en fila 3
        $monthRow = 2;
        $dayRow = 3;

        $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        $monthMatches = 0;
        for ($col = 4; $col <= $highestColumn; $col++) { // Empezar desde columna D (4)
            $monthCell = trim((string) $sheet->getCellByColumnAndRow($col, $monthRow)->getFormattedValue());
            if ($monthCell !== '' && $this->monthNumberFromName($monthCell) !== null) {
                $monthMatches++;
            }
        }

        $dayMatches = 0;
        for ($col = 4; $col <= $highestColumn; $col++) { // Empezar desde columna D (4)
            $dayCell = trim((string) $sheet->getCellByColumnAndRow($col, $dayRow)->getFormattedValue());
            if (is_numeric($dayCell) && (int) $dayCell >= 1 && (int) $dayCell <= 31) {
                $dayMatches++;
            }
        }

        if ($monthMatches > 0 && $dayMatches > 0) {
            return [$monthRow, $dayRow];
        }

        // Si no funciona, intentar detectar automáticamente
        for ($monthRow = 1; $monthRow <= 4; $monthRow++) {
            $monthMatches = 0;
            for ($col = 4; $col <= $highestColumn; $col++) { // Empezar desde columna D (4)
                $monthCell = trim((string) $sheet->getCellByColumnAndRow($col, $monthRow)->getFormattedValue());
                if ($monthCell !== '' && $this->monthNumberFromName($monthCell) !== null) {
                    $monthMatches++;
                }
            }

            if ($monthMatches < 1) {
                continue;
            }

            for ($dayRow = $monthRow + 1; $dayRow <= min($monthRow + 3, 5); $dayRow++) {
                $dayMatches = 0;
                for ($col = 4; $col <= $highestColumn; $col++) { // Empezar desde columna D (4)
                    $dayCell = trim((string) $sheet->getCellByColumnAndRow($col, $dayRow)->getFormattedValue());
                    if (is_numeric($dayCell) && (int) $dayCell >= 1 && (int) $dayCell <= 31) {
                        $dayMatches++;
                    }
                }

                if ($dayMatches > 0) {
                    return [$monthRow, $dayRow];
                }
            }
        }

        return null;
    }

    protected function parseAttendanceHeaderDate(string $value, AcademicPeriod $period): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            $candidate = Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        $start = Carbon::parse($period->start_date)->startOfDay();
        $end = Carbon::parse($period->end_date)->endOfDay();

        return $candidate->between($start, $end) ? $candidate : null;
    }

    protected function resolveDateWithinPeriod(
        AcademicPeriod $period,
        int $month,
        int $day
    ): ?Carbon {
        $start = Carbon::parse($period->start_date)->startOfDay();
        $end = Carbon::parse($period->end_date)->endOfDay();

        $years = [$start->year - 1, $start->year, $end->year, $end->year + 1];

        foreach (array_unique($years) as $year) {
            if (! checkdate($month, $day, $year)) {
                continue;
            }

            $candidate = Carbon::create($year, $month, $day)->startOfDay();
            if ($candidate->between($start, $end)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function monthNumberFromName(string $name): ?int
    {
        $n = $this->normalizeText($name);

        $map = [
            'enero' => 1,
            'ene' => 1,
            'febrero' => 2,
            'feb' => 2,
            'marzo' => 3,
            'mar' => 3,
            'abril' => 4,
            'abr' => 4,
            'mayo' => 5,
            'may' => 5,
            'junio' => 6,
            'jun' => 6,
            'julio' => 7,
            'jul' => 7,
            'agosto' => 8,
            'ago' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'sep' => 9,
            'octubre' => 10,
            'oct' => 10,
            'noviembre' => 11,
            'nov' => 11,
            'diciembre' => 12,
            'dic' => 12,
        ];

        foreach ($map as $key => $value) {
            if (str_contains($n, $key)) {
                return $value;
            }
        }

        return null;
    }

    protected function resolveTeachingAssignment(string $sheetName, int $academicPeriodId, ?int $schoolCycleId = null): TeachingAssignment
    {
        if (! str_contains($sheetName, '-')) {
            throw new \RuntimeException('Formato de hoja inválido. Se esperaba GRUPO-MATERIA.');
        }

        [$groupPart, $subjectPart] = array_map('trim', explode('-', $sheetName, 2));

        $group = $this->resolveGroupBySheetGroup($groupPart, $schoolCycleId);

        if (! $group) {
            throw new \RuntimeException("Grupo '{$groupPart}' no encontrado.");
        }

        // DEBUG: Inicializar debugInfo
        $debugInfo = "Grupo encontrado: {$group->name} (ID: {$group->id})\n";

        // Primero intenta búsqueda exacta (ignorando tildes/mayúsculas)
        $targetSubjectName = $this->normalizeText($subjectPart);
        $allSubjects = Subject::query()->get();
        
        // DEBUG: Log temporal
        $debugInfo = "DEBUG Materia: Buscando '{$subjectPart}' -> '{$targetSubjectName}'\n";
        $debugInfo .= "Materias disponibles:\n";
        foreach ($allSubjects->take(5) as $s) { // Solo primeras 5 para no saturar
            $normalized = $this->normalizeText($s->name);
            $debugInfo .= "- {$s->name} -> {$normalized}\n";
        }
        
        $subject = $allSubjects->first(fn (Subject $s) => 
            $this->normalizeText((string) $s->name) === $targetSubjectName
        );

        if (!$subject) {
            $debugInfo .= "No encontrada coincidencia exacta, intentando búsqueda por palabras...\n";
        }

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

        // Si aún no encuentra, intenta búsqueda por similitud (Levenshtein) con mejor tolerancia
        if (! $subject) {
            $debugInfo .= "Intentando búsqueda por similitud...\n";
            $subject = $allSubjects->first(function (Subject $s) use ($targetSubjectName, &$debugInfo) {
                $normalizedSubjectName = $this->normalizeText((string) $s->name);
                
                // Calcular distancia de edición
                $distance = levenshtein($targetSubjectName, $normalizedSubjectName);
                $maxDistance = max(3, min(5, strlen($targetSubjectName) * 0.3)); // 30% de la longitud
                
                // También verificar si contiene las palabras clave principales
                $targetWords = array_filter(explode(' ', $targetSubjectName), fn($w) => strlen($w) > 2);
                $subjectWords = array_filter(explode(' ', $normalizedSubjectName), fn($w) => strlen($w) > 2);
                $commonWords = count(array_intersect($targetWords, $subjectWords));
                
                $matches = $distance <= $maxDistance || $commonWords >= 2;
                if ($matches) {
                    $debugInfo .= "Coincidencia encontrada: '{$s->name}' (distancia: {$distance}, palabras comunes: {$commonWords})\n";
                }
                
                return $matches;
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
            // Buscar asignaciones disponibles para este grupo
            $availableAssignments = TeachingAssignment::query()
                ->where('group_id', $group->id)
                ->with('subject')
                ->get()
                ->map(fn ($ta) => $ta->subject->name)
                ->join(', ');

            throw new \RuntimeException(
                "No existe asignación docente para '{$sheetName}'. " .
                "Asignaciones disponibles para el grupo {$group->name}: " .
                ($availableAssignments ?: 'ninguna')
            );
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



