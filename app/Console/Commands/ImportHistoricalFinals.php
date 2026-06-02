<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportHistoricalFinals extends Command
{
    protected $signature = 'import:historical-finals
        {--file= : Ruta del archivo .ods/.xlsx}
        {--cycle=2026-2 : Nombre o ID del ciclo escolar}
        {--group= : Nombre del grupo (ej. 6120)}
        {--dry-run : Solo analizar sin guardar}';

    protected $description = 'Importa calificaciones finales historicas (parcial 1, parcial 2 y asistencia) por alumno/materia.';

    public function handle(): int
    {
        $file = (string) $this->option('file');
        if ($file === '' || ! file_exists($file)) {
            $this->error('Archivo no encontrado. Usa --file con una ruta válida.');
            return self::FAILURE;
        }

        $cycleInput = (string) $this->option('cycle');
        $groupName = trim((string) $this->option('group'));
        $dryRun = (bool) $this->option('dry-run');

        $cycle = is_numeric($cycleInput)
            ? DB::table('school_cycles')->where('id', (int) $cycleInput)->first()
            : DB::table('school_cycles')->where('name', $cycleInput)->first();

        if (! $cycle) {
            $this->error('No se encontró el ciclo especificado.');
            return self::FAILURE;
        }

        $assignmentQuery = DB::table('teaching_assignments as ta')
            ->join('groups as g', 'g.id', '=', 'ta.group_id')
            ->join('subjects as s', 's.id', '=', 'ta.subject_id')
            ->join('schedules as sc', function ($join) use ($cycle) {
                $join->on('sc.teaching_assignment_id', '=', 'ta.id')
                    ->where('sc.school_cycle_id', '=', (int) $cycle->id)
                    ->where('sc.is_active', '=', 1);
            })
            ->where('ta.is_active', 1)
            ->select(
                'ta.id as assignment_id',
                'ta.tenant_id',
                'g.name as group_name',
                's.name as subject_name'
            )
            ->distinct();

        if ($groupName !== '') {
            $assignmentQuery->where('g.name', $groupName);
        }

        $assignments = $assignmentQuery->get();
        if ($assignments->isEmpty()) {
            $this->error('No se encontraron asignaciones activas para ese ciclo/grupo.');
            return self::FAILURE;
        }

        $tenantIds = $assignments->pluck('tenant_id')->filter()->unique()->values();
        if ($tenantIds->count() !== 1) {
            $this->error('No se pudo inferir un tenant único para la importación.');
            return self::FAILURE;
        }
        $tenantId = (string) $tenantIds->first();

        $assignmentsBySubject = [];
        $normalizedAssignments = [];
        foreach ($assignments as $assignment) {
            $key = $this->normalize((string) $assignment->subject_name);
            if (! isset($assignmentsBySubject[$key])) {
                $assignmentsBySubject[$key] = [];
            }
            $assignmentsBySubject[$key][] = $assignment;
            $normalizedAssignments[] = [
                'key' => $key,
                'assignment' => $assignment,
            ];
        }

        $spreadsheet = IOFactory::load($file);
        $rowsToUpsert = [];
        $warnings = [];
        $matchedStudents = 0;
        $sheetCount = 0;
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $sheetCount++;
            $highestRow = $sheet->getHighestDataRow();
            $highestColIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

            $subjectBlocks = [];
            for ($col = 1; $col <= $highestColIndex; $col++) {
                $subjectRaw = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($col) . '1')->getFormattedValue());
                if ($subjectRaw === '' || in_array(mb_strtolower($subjectRaw), ['#', 'matricula', 'nombre'], true)) {
                    continue;
                }

                $head1 = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($col) . '2')->getFormattedValue());
                $head2 = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($col + 2) . '2')->getFormattedValue());
                if (mb_strtoupper($head1) !== 'FINAL' || mb_strtoupper($head2) !== 'FINAL') {
                    continue;
                }

                $subjectBlocks[] = [
                    'subject' => $subjectRaw,
                    'subject_key' => $this->normalize($subjectRaw),
                    'col_start' => $col,
                    'sheet' => $sheet->getTitle(),
                ];
            }

            if (empty($subjectBlocks)) {
                continue;
            }

            for ($row = 3; $row <= $highestRow; $row++) {
                $enrollment = trim((string) $sheet->getCell('B' . $row)->getFormattedValue());
                if ($enrollment === '') {
                    continue;
                }

                $student = DB::table('students')
                    ->where('enrollment_number', $enrollment)
                    ->first(['id']);

                if (! $student) {
                    $warnings[] = "[{$sheet->getTitle()}] Fila {$row}: matrícula {$enrollment} no existe en students.";
                    continue;
                }

                $matchedStudents++;

                foreach ($subjectBlocks as $block) {
                    $resolved = $this->resolveAssignment($block['subject_key'], $assignmentsBySubject, $normalizedAssignments);
                    if (! $resolved) {
                        $warnings[] = "[{$sheet->getTitle()}] Materia '{$block['subject']}' sin match único en ciclo/grupo.";
                        continue;
                    }

                    $assignmentId = (int) $resolved->assignment_id;
                    $c = $block['col_start'];
                    $p1Final = $this->toScore($sheet->getCell(Coordinate::stringFromColumnIndex($c) . $row)->getFormattedValue());
                    $p1Asis = $this->toScore($sheet->getCell(Coordinate::stringFromColumnIndex($c + 1) . $row)->getFormattedValue(), 100);
                    $p2Final = $this->toScore($sheet->getCell(Coordinate::stringFromColumnIndex($c + 2) . $row)->getFormattedValue());
                    $p2Asis = $this->toScore($sheet->getCell(Coordinate::stringFromColumnIndex($c + 3) . $row)->getFormattedValue(), 100);

                    if ($p1Final === null && $p2Final === null) {
                        continue;
                    }

                    $finalValues = array_values(array_filter([$p1Final, $p2Final], fn ($v) => $v !== null));
                    $asisValues = array_values(array_filter([$p1Asis, $p2Asis], fn ($v) => $v !== null));

                    $finalGrade = ! empty($finalValues)
                        ? round(array_sum($finalValues) / count($finalValues), 2)
                        : null;
                    $attendance = ! empty($asisValues)
                        ? round(array_sum($asisValues) / count($asisValues), 2)
                        : null;

                    $rowsToUpsert[] = [
                        'tenant_id' => $tenantId,
                        'student_id' => (int) $student->id,
                        'teaching_assignment_id' => $assignmentId,
                        'school_cycle_id' => (int) $cycle->id,
                        'partial_1_final' => $p1Final,
                        'partial_2_final' => $p2Final,
                        'final_grade' => $finalGrade,
                        'partial_1_attendance' => $p1Asis,
                        'partial_2_attendance' => $p2Asis,
                        'attendance_percentage' => $attendance,
                        'source_file' => basename($file) . ' [' . $sheet->getTitle() . ']',
                        'imported_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        $this->info('Ciclo: ' . $cycle->name . ' | Grupo: ' . ($groupName !== '' ? $groupName : '(todos)'));
        $this->info('Hojas procesadas: ' . $sheetCount);
        $this->info('Estudiantes con match: ' . $matchedStudents);
        $this->info('Registros históricos preparados: ' . count($rowsToUpsert));
        if (! empty($warnings)) {
            $this->warn('Advertencias: ' . count($warnings));
            foreach (array_slice($warnings, 0, 20) as $warning) {
                $this->line('- ' . $warning);
            }
        }

        if ($dryRun) {
            $this->comment('Dry-run: no se guardaron cambios.');
            return self::SUCCESS;
        }

        if (empty($rowsToUpsert)) {
            $this->warn('No hay registros para guardar.');
            return self::SUCCESS;
        }

        DB::table('student_assignment_historicals')->upsert(
            $rowsToUpsert,
            ['tenant_id', 'student_id', 'teaching_assignment_id', 'school_cycle_id'],
            [
                'partial_1_final',
                'partial_2_final',
                'final_grade',
                'partial_1_attendance',
                'partial_2_attendance',
                'attendance_percentage',
                'source_file',
                'imported_at',
                'updated_at',
            ]
        );

        $this->info('Importación histórica completada.');
        return self::SUCCESS;
    }

    private function normalize(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function toScore(mixed $value, int $max = 10): ?float
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '#NUM!') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $num = (float) $value;
        if ($num < 0 || $num > $max) {
            return null;
        }
        return round($num, 2);
    }

    private function resolveAssignment(string $subjectKey, array $assignmentsBySubject, array $normalizedAssignments): ?object
    {
        $exact = $assignmentsBySubject[$subjectKey] ?? [];
        if (count($exact) === 1) {
            return $exact[0];
        }
        if (count($exact) > 1) {
            return collect($exact)->sortBy('assignment_id')->first();
        }

        $best = null;
        $bestScore = 0.0;
        $ties = 0;
        foreach ($normalizedAssignments as $entry) {
            $score = $this->tokenSimilarity($subjectKey, (string) $entry['key']);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $entry['assignment'];
                $ties = 1;
            } elseif (abs($score - $bestScore) < 0.0001) {
                $ties++;
            }
        }

        if ($best && $bestScore >= 0.35) {
            return $best;
        }

        return null;
    }

    private function tokenSimilarity(string $left, string $right): float
    {
        $leftTokens = collect(explode(' ', $left))->filter()->values();
        $rightTokens = collect(explode(' ', $right))->filter()->values();
        if ($leftTokens->isEmpty() || $rightTokens->isEmpty()) {
            return 0.0;
        }

        $leftSet = $leftTokens->unique()->values();
        $rightSet = $rightTokens->unique()->values();
        $intersection = $leftSet->intersect($rightSet)->count();
        $union = $leftSet->merge($rightSet)->unique()->count();
        if ($union === 0) {
            return 0.0;
        }

        return $intersection / $union;
    }
}
