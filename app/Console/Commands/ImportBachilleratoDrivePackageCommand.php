<?php

namespace App\Console\Commands;

use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Campus;
use App\Models\CyclePartial;
use App\Models\EvaluationCriterion;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\PrefectDailyAttendance;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\StudentGroupHistory;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Spatie\Permission\Models\Role;

class ImportBachilleratoDrivePackageCommand extends Command
{
    protected $signature = 'bachillerato:import-drive-package
        {path : Carpeta raiz descargada de Drive}
        {--cycle=2026-3 : ID, nombre o codigo del ciclo}
        {--campus-code=FLORIDA : Codigo de campus}
        {--modality=BACHILLERATO : Nombre de modalidad}
        {--dry-run : Simula la importacion sin guardar cambios}';

    protected $description = 'Importa LISTAMAESTRA, calificaciones por parcial y asistencia de prefectura del paquete Bachillerato Florida 26-3.';

    private array $stats = [];
    private array $warnings = [];
    private array $studentIdByExternal = [];
    private array $studentModelIdByEnrollment = [];
    private array $groupIdByExternal = [];
    private array $groupIdByName = [];
    private array $subjectIdByExternal = [];
    private array $teacherIdByExternal = [];
    private array $assignmentIdByExternal = [];
    private array $partialIdByKey = [];

    public function handle(): int
    {
        $root = $this->resolveRoot((string) $this->argument('path'));
        $listamaestra = $root . DIRECTORY_SEPARATOR . 'COORDINACION' . DIRECTORY_SEPARATOR . 'LISTAMAESTRA.xlsx';

        if (! is_file($listamaestra)) {
            $this->error('No encontre COORDINACION\\LISTAMAESTRA.xlsx dentro de la carpeta indicada.');
            return self::FAILURE;
        }

        $cycle = $this->resolveCycle();
        $campus = Campus::query()->whereRaw('UPPER(code) = ?', [mb_strtoupper((string) $this->option('campus-code'))])->firstOrFail();
        $modality = Modality::query()->whereRaw('UPPER(name) = ?', [mb_strtoupper((string) $this->option('modality'))])->firstOrFail();
        $dryRun = (bool) $this->option('dry-run');

        $this->stats = [
            'groups' => 0,
            'subjects' => 0,
            'teachers' => 0,
            'students' => 0,
            'cycle_groups' => 0,
            'assignments' => 0,
            'student_assignment_links' => 0,
            'partials' => 0,
            'criteria' => 0,
            'activities' => 0,
            'grades' => 0,
            'prefect_attendances' => 0,
            'evaluation_files' => 0,
            'prefect_files' => 0,
        ];

        DB::beginTransaction();

        try {
            $spreadsheet = IOFactory::load($listamaestra);
            $this->ensureCycleContext($cycle, $campus, $modality);
            $this->importPartials($spreadsheet->getSheetByName('PARCIALES'), $cycle, $modality);
            $this->importGroups($spreadsheet->getSheetByName('GRUPOS'), $cycle, $campus, $modality);
            $this->importTeachers($spreadsheet->getSheetByName('PROFESORES'), $campus);
            $this->importSubjects($spreadsheet->getSheetByName('MATERIAS'));
            $this->importStudents($spreadsheet->getSheetByName('ALUMNOS'), $cycle, $campus);
            $this->importAssignments($spreadsheet->getSheetByName('ASIGNACIONES'), $cycle);
            $this->importStudentAssignments($spreadsheet->getSheetByName('ALUMNO_ASIGNACION'));
            $this->importEvaluationFiles($root);
            $this->importPrefectFiles($root);

            if ($dryRun) {
                DB::rollBack();
                $this->warn('DRY RUN: no se guardaron cambios.');
            } else {
                DB::commit();
                $this->info('Importacion guardada.');
            }
        } catch (\Throwable $exception) {
            DB::rollBack();
            $this->error($exception->getMessage());
            $this->line($exception->getFile() . ':' . $exception->getLine());
            return self::FAILURE;
        }

        $this->line('Ciclo: ' . $cycle->name . ' | Campus: ' . $campus->code . ' | Modalidad: ' . $modality->name);
        $this->table(['Metrica', 'Total'], collect($this->stats)->map(fn ($value, $key) => [$key, $value])->values()->all());

        foreach (array_slice($this->warnings, 0, 60) as $warning) {
            $this->warn($warning);
        }

        if (count($this->warnings) > 60) {
            $this->warn('... y ' . (count($this->warnings) - 60) . ' advertencias mas.');
        }

        return self::SUCCESS;
    }

    private function resolveRoot(string $path): string
    {
        $path = rtrim($path, "\\/");
        if (is_dir($path . DIRECTORY_SEPARATOR . 'BACHILLERATO FLORIDA 26-3')) {
            return $path . DIRECTORY_SEPARATOR . 'BACHILLERATO FLORIDA 26-3';
        }

        return $path;
    }

    private function resolveCycle(): SchoolCycle
    {
        $cycle = (string) $this->option('cycle');

        return SchoolCycle::query()
            ->whereKey($cycle)
            ->orWhere('name', $cycle)
            ->orWhere('code', $cycle)
            ->firstOrFail();
    }

    private function ensureCycleContext(SchoolCycle $cycle, Campus $campus, Modality $modality): void
    {
        $cycle->update([
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
        ]);
        $cycle->campuses()->syncWithoutDetaching([$campus->id]);
        $cycle->modalities()->syncWithoutDetaching([$modality->id]);
    }

    private function importPartials(?Worksheet $sheet, SchoolCycle $cycle, Modality $modality): void
    {
        if (! $sheet) {
            throw new \RuntimeException('LISTAMAESTRA no tiene hoja PARCIALES.');
        }

        foreach ($this->rowsByHeader($sheet) as $row) {
            $externalId = trim((string) ($row['PARCIAL_ID'] ?? ''));
            $name = trim((string) ($row['NOMBRE'] ?? ''));
            if ($externalId === '' || $name === '') {
                continue;
            }

            $start = $this->parseDate($row['FECHA_INICIO'] ?? null);
            $end = $this->parseDate($row['FECHA_FIN'] ?? null);
            $sort = preg_match('/(\d+)/', $externalId, $matches) ? (int) $matches[1] : 1;
            $cyclePrefix = mb_strtoupper(preg_replace('/[^A-Z0-9]+/i', '-', $cycle->code ?: $cycle->name));
            $code = trim($cyclePrefix, '-') . '-' . mb_strtoupper($externalId);

            $existingPartial = CyclePartial::query()
                ->where('school_cycle_id', $cycle->id)
                ->where(function ($query) use ($code, $sort) {
                    $query->where('code', $code)
                        ->orWhere('sort_order', $sort);
                })
                ->first();

            $periodCode = $existingPartial?->academicPeriod?->code ?: $code;
            $period = AcademicPeriod::query()->updateOrCreate(
                ['code' => $periodCode],
                [
                    'name' => $name,
                    'modality_id' => $modality->id,
                    'start_date' => $start,
                    'end_date' => $end,
                    'is_active' => true,
                ]
            );

            $partial = $existingPartial ?: new CyclePartial([
                'school_cycle_id' => $cycle->id,
                'code' => $code,
            ]);

            $partial->fill([
                'academic_period_id' => $period->id,
                'name' => Str::title(mb_strtolower($name)),
                'sort_order' => $sort,
                'start_date' => $start,
                'end_date' => $end,
                'is_active' => true,
            ])->save();

            $this->partialIdByKey[mb_strtoupper($externalId)] = $partial->id;
            $this->stats['partials']++;
        }
    }

    private function importGroups(?Worksheet $sheet, SchoolCycle $cycle, Campus $campus, Modality $modality): void
    {
        if (! $sheet) {
            throw new \RuntimeException('LISTAMAESTRA no tiene hoja GRUPOS.');
        }

        foreach ($this->rowsByHeader($sheet) as $row) {
            $externalId = trim((string) ($row['GRUPO_ID'] ?? ''));
            $name = trim((string) ($row['NOMBRE_VISIBLE'] ?? ''));
            if ($externalId === '' || $name === '') {
                continue;
            }

            $levelId = $this->levelIdForGroup($name, $modality);
            $group = Group::query()->updateOrCreate(
                ['campus_id' => $campus->id, 'level_id' => $levelId, 'name' => $name],
                ['capacity' => 40, 'is_active' => true]
            );

            $cycleGroup = SchoolCycleGroup::query()->updateOrCreate(
                [
                    'tenant_id' => (string) tenant('id') ?: 'escuela',
                    'school_cycle_id' => $cycle->id,
                    'group_id' => $group->id,
                    'campus_id' => $campus->id,
                    'modality_id' => $modality->id,
                ],
                ['section_count' => 2, 'is_active' => true]
            );

            $this->groupIdByExternal[$externalId] = $group->id;
            $this->groupIdByName[$this->key($name)] = $group->id;
            $this->stats['groups']++;
            $this->stats['cycle_groups']++;
        }
    }

    private function importTeachers(?Worksheet $sheet, Campus $campus): void
    {
        if (! $sheet) {
            throw new \RuntimeException('LISTAMAESTRA no tiene hoja PROFESORES.');
        }

        Role::findOrCreate('teacher');

        foreach ($this->rowsByHeader($sheet) as $row) {
            $externalId = trim((string) ($row['PROFESOR_ID'] ?? ''));
            $name = $this->normalizeName((string) ($row['NOMBRE'] ?? ''));
            $email = mb_strtolower(trim((string) ($row['CORREO'] ?? '')));
            if ($externalId === '' || $name === '' || $email === '') {
                continue;
            }

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('123123123'), 'default_campus_id' => $campus->id]
            );
            $user->update(['name' => $name, 'default_campus_id' => $campus->id]);
            $user->assignRole('teacher');
            $user->campuses()->syncWithoutDetaching([$campus->id]);

            $teacher = Teacher::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['is_active' => true]
            );

            $this->teacherIdByExternal[$externalId] = $teacher->id;
            $this->stats['teachers']++;
        }
    }

    private function importSubjects(?Worksheet $sheet): void
    {
        if (! $sheet) {
            throw new \RuntimeException('LISTAMAESTRA no tiene hoja MATERIAS.');
        }

        $rows = $this->rowsByHeader($sheet);
        foreach ($rows as $row) {
            $externalId = trim((string) ($row['MATERIA_ID'] ?? ''));
            $name = $this->normalizeName((string) ($row['NOMBRE'] ?? ''));
            if ($externalId === '' || $name === '') {
                continue;
            }

            $this->subjectIdByExternal[$externalId] = null;
        }
    }

    private function importStudents(?Worksheet $sheet, SchoolCycle $cycle, Campus $campus): void
    {
        if (! $sheet) {
            throw new \RuntimeException('LISTAMAESTRA no tiene hoja ALUMNOS.');
        }

        Role::findOrCreate('student');

        foreach ($this->rowsByHeader($sheet) as $row) {
            $externalId = trim((string) ($row['ALUMNO_ID'] ?? ''));
            $enrollment = mb_strtoupper(trim((string) ($row['MATRICULA'] ?? '')));
            $name = $this->normalizeName((string) ($row['NOMBRE'] ?? ''));
            $groupId = $this->groupIdByExternal[trim((string) ($row['GRUPO_ID'] ?? ''))] ?? null;
            if ($externalId === '' || $enrollment === '' || $name === '' || ! $groupId) {
                continue;
            }

            $isActive = ! in_array(mb_strtoupper(trim((string) ($row['ESTATUS'] ?? 'ACTIVO'))), ['INACTIVO', 'BAJA'], true);
            $user = User::query()->firstOrCreate(
                ['email' => mb_strtolower($enrollment) . '@my.ula.edu.mx'],
                ['name' => $name, 'password' => Hash::make('123123123'), 'default_campus_id' => $campus->id]
            );
            $user->update(['name' => $name, 'default_campus_id' => $campus->id]);
            $user->assignRole('student');
            $user->campuses()->syncWithoutDetaching([$campus->id]);

            $student = Student::query()->updateOrCreate(
                ['enrollment_number' => $enrollment],
                ['user_id' => $user->id, 'group_id' => $groupId, 'is_active' => $isActive]
            );

            StudentGroupHistory::query()->updateOrCreate(
                ['student_id' => $student->id, 'group_id' => $groupId, 'start_date' => $cycle->start_date?->toDateString()],
                ['reason' => 'importacion_bachillerato_26_3']
            );

            $this->studentIdByExternal[$externalId] = $student->id;
            $this->studentModelIdByEnrollment[$enrollment] = $student->id;
            $this->stats['students']++;
        }
    }

    private function importAssignments(?Worksheet $sheet, SchoolCycle $cycle): void
    {
        if (! $sheet) {
            throw new \RuntimeException('LISTAMAESTRA no tiene hoja ASIGNACIONES.');
        }

        $subjects = $this->rowsByHeader(IOFactory::load($this->resolveRoot((string) $this->argument('path')) . DIRECTORY_SEPARATOR . 'COORDINACION' . DIRECTORY_SEPARATOR . 'LISTAMAESTRA.xlsx')->getSheetByName('MATERIAS'));
        $subjectNames = [];
        foreach ($subjects as $row) {
            $subjectNames[trim((string) ($row['MATERIA_ID'] ?? ''))] = $this->normalizeName((string) ($row['NOMBRE'] ?? ''));
        }

        foreach ($this->rowsByHeader($sheet) as $row) {
            $externalId = trim((string) ($row['ASIGNACION_ID'] ?? ''));
            $groupId = $this->groupIdByExternal[trim((string) ($row['GRUPO_ID'] ?? ''))] ?? null;
            $teacherId = $this->teacherIdByExternal[trim((string) ($row['PROFESOR_ID'] ?? ''))] ?? null;
            $subjectExternal = trim((string) ($row['MATERIA_ID'] ?? ''));
            if ($externalId === '' || ! $groupId || ! $teacherId || ! isset($subjectNames[$subjectExternal])) {
                continue;
            }

            $group = Group::query()->findOrFail($groupId);
            $subject = Subject::query()->updateOrCreate(
                ['level_id' => $group->level_id, 'name' => $subjectNames[$subjectExternal]],
                [
                    'hours_per_week' => max(1, (int) ($row['HORASSEMANALES'] ?? 1)),
                    'type' => 'Teorica',
                    'is_active' => true,
                ]
            );
            $this->subjectIdByExternal[$subjectExternal] = $subject->id;
            $this->stats['subjects']++;

            $cycleGroup = SchoolCycleGroup::query()
                ->where('school_cycle_id', $cycle->id)
                ->where('group_id', $groupId)
                ->firstOrFail();
            $cycleGroup->subjects()->syncWithoutDetaching([$subject->id]);
            $group->subjects()->syncWithoutDetaching([$subject->id]);
            Teacher::query()->find($teacherId)?->subjects()->syncWithoutDetaching([$subject->id]);

            $section = trim((string) ($row['SECCION'] ?? ''));
            $sectionNumber = $section === '' ? 1 : (mb_strtoupper($section) === 'B' ? 2 : 1);
            $assignment = TeachingAssignment::query()->updateOrCreate(
                ['school_cycle_group_id' => $cycleGroup->id, 'subject_id' => $subject->id, 'section_number' => $sectionNumber],
                [
                    'teacher_id' => $teacherId,
                    'group_id' => $groupId,
                    'section_type' => $section !== '' ? 'english' : null,
                    'section_label' => $section !== '' ? mb_strtoupper($section) : null,
                    'nrc' => $externalId,
                    'is_active' => true,
                ]
            );

            $this->assignmentIdByExternal[$externalId] = $assignment->id;
            $this->stats['assignments']++;
        }
    }

    private function importStudentAssignments(?Worksheet $sheet): void
    {
        if (! $sheet) {
            throw new \RuntimeException('LISTAMAESTRA no tiene hoja ALUMNO_ASIGNACION.');
        }

        foreach ($this->rowsByHeader($sheet) as $row) {
            $studentId = $this->studentIdByExternal[trim((string) ($row['ALUMNO_ID'] ?? ''))] ?? null;
            $assignmentId = $this->assignmentIdByExternal[trim((string) ($row['ASIGNACION_ID'] ?? ''))] ?? null;
            if (! $studentId || ! $assignmentId) {
                continue;
            }

            DB::table('teaching_assignment_student')->updateOrInsert(
                ['teaching_assignment_id' => $assignmentId, 'student_id' => $studentId],
                ['created_at' => now(), 'updated_at' => now()]
            );
            $this->stats['student_assignment_links']++;
        }
    }

    private function importEvaluationFiles(string $root): void
    {
        foreach (glob($root . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'EVALUACION*.xlsx') ?: [] as $file) {
            $partialKey = str_contains(mb_strtoupper($file), 'SEGUNDO PARCIAL') ? 'P2' : 'P1';
            $partialId = $this->partialIdByKey[$partialKey] ?? null;
            if (! $partialId) {
                continue;
            }
            $this->stats['evaluation_files']++;
            $spreadsheet = IOFactory::load($file);
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $this->importEvaluationSheet($sheet, $partialId, basename($file));
            }
        }
    }

    private function importEvaluationSheet(Worksheet $sheet, int $partialId, string $source): void
    {
        $assignment = $this->assignmentBySheetTitle($sheet->getTitle());
        if (! $assignment) {
            $this->warnings[] = "Evaluacion {$source} [{$sheet->getTitle()}]: no encontre asignacion.";
            return;
        }

        $finalColumn = $this->findHeaderColumn($sheet, 'FINAL', 3) ?: 21;
        $criterion = EvaluationCriterion::query()->updateOrCreate(
            ['teaching_assignment_id' => $assignment->id, 'cycle_partial_id' => $partialId, 'name' => 'Calificacion importada'],
            ['percentage' => 100]
        );
        $this->stats['criteria']++;

        $partial = CyclePartial::query()->find($partialId);
        $activity = Activity::query()->updateOrCreate(
            ['teaching_assignment_id' => $assignment->id, 'evaluation_criterion_id' => $criterion->id, 'title' => 'Calificacion importada ' . $partial->name],
            [
                'academic_period_id' => $partial->academic_period_id,
                'max_score' => 10,
                'due_date' => $partial->end_date,
                'description' => 'Importada desde ' . $source . ' / ' . $sheet->getTitle(),
                'evaluation_mode' => 'individual',
                'is_active' => true,
            ]
        );
        $this->stats['activities']++;

        for ($row = 4; $row <= $sheet->getHighestDataRow(); $row++) {
            $enrollment = mb_strtoupper(trim((string) $sheet->getCell('A' . $row)->getFormattedValue()));
            $score = $this->toScore($sheet->getCell(Coordinate::stringFromColumnIndex($finalColumn) . $row)->getFormattedValue());
            $studentId = $this->studentModelIdByEnrollment[$enrollment] ?? null;
            if (! $studentId || $score === null) {
                continue;
            }

            Grade::query()->updateOrCreate(
                ['activity_id' => $activity->id, 'student_id' => $studentId],
                ['score' => $score, 'comments' => 'Importado de Drive']
            );
            $this->stats['grades']++;
        }
    }

    private function importPrefectFiles(string $root): void
    {
        foreach (glob($root . DIRECTORY_SEPARATOR . 'PREFECTURA' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'ASISTENCIA PREFECTURA.xlsx') ?: [] as $file) {
            $this->stats['prefect_files']++;
            $spreadsheet = IOFactory::load($file);
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $this->importPrefectSheet($sheet);
            }
        }
    }

    private function importPrefectSheet(Worksheet $sheet): void
    {
        $group = Group::query()->where('name', trim($sheet->getTitle()))->first();
        if (! $group) {
            return;
        }

        $months = $sheet->rangeToArray('A2:' . $sheet->getHighestDataColumn() . '2', null, true, false)[0] ?? [];
        $days = $sheet->rangeToArray('A3:' . $sheet->getHighestDataColumn() . '3', null, true, false)[0] ?? [];
        $currentMonth = null;
        $dateByColumn = [];
        foreach ($days as $index => $day) {
            $monthLabel = trim((string) ($months[$index] ?? ''));
            if ($monthLabel !== '') {
                $currentMonth = $monthLabel;
            }
            if ($index < 4 || ! is_numeric($day) || ! $currentMonth) {
                continue;
            }
            $dateByColumn[$index + 1] = Carbon::create(2026, $this->monthNumber($currentMonth), (int) $day)->toDateString();
        }

        for ($row = 4; $row <= $sheet->getHighestDataRow(); $row++) {
            $enrollment = mb_strtoupper(trim((string) $sheet->getCell('A' . $row)->getFormattedValue()));
            $studentId = $this->studentModelIdByEnrollment[$enrollment] ?? null;
            if (! $studentId) {
                continue;
            }

            foreach ($dateByColumn as $column => $date) {
                $value = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($column) . $row)->getFormattedValue());
                $status = match ($value) {
                    '1' => 'present',
                    '2' => 'justified',
                    '0' => 'absent',
                    default => null,
                };
                if (! $status) {
                    continue;
                }

                PrefectDailyAttendance::query()->updateOrCreate(
                    ['student_id' => $studentId, 'attendance_date' => $date],
                    ['group_id' => $group->id, 'status' => $status, 'recorded_by' => 7]
                );
                $this->stats['prefect_attendances']++;
            }
        }
    }

    private function rowsByHeader(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $headers = array_map(fn ($v) => mb_strtoupper(trim((string) $v)), $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0] ?? []);
        $rows = [];
        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $values = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, true, false)[0] ?? [];
            $row = [];
            $hasValue = false;
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $row[$header] = $values[$index] ?? null;
                $hasValue = $hasValue || trim((string) ($values[$index] ?? '')) !== '';
            }
            if ($hasValue) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function levelIdForGroup(string $groupName, Modality $modality): int
    {
        $first = (int) substr($groupName, 0, 1);
        $levelName = $first <= 2 ? 'Cuarto' : ($first <= 4 ? 'Quinto' : 'Sexto');

        return Level::query()
            ->where('modality_id', $modality->id)
            ->where('name', $levelName)
            ->value('id');
    }

    private function assignmentBySheetTitle(string $title): ?TeachingAssignment
    {
        [$groupName, $subjectName] = array_pad(explode('-', $title, 2), 2, null);
        if (! $groupName || ! $subjectName) {
            return null;
        }

        $groupId = $this->groupIdByName[$this->key($groupName)] ?? null;
        if (! $groupId) {
            return null;
        }

        $subjectKey = $this->key($subjectName);
        return TeachingAssignment::query()
            ->where('group_id', $groupId)
            ->with('subject')
            ->get()
            ->first(function (TeachingAssignment $assignment) use ($subjectKey) {
                $assignmentKey = $this->key($assignment->subject?->name ?? '');

                return $assignmentKey === $subjectKey
                    || (mb_strlen($subjectKey) >= 12 && str_starts_with($assignmentKey, $subjectKey))
                    || (mb_strlen($assignmentKey) >= 12 && str_starts_with($subjectKey, $assignmentKey));
            });
    }

    private function findHeaderColumn(Worksheet $sheet, string $needle, int $row): ?int
    {
        $highest = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        for ($column = 1; $column <= $highest; $column++) {
            if (mb_strtoupper(trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($column) . $row)->getFormattedValue())) === $needle) {
                return $column;
            }
        }

        return null;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        return Carbon::parse(str_replace('/', '-', (string) $value))->toDateString();
    }

    private function toScore(mixed $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-' || ! is_numeric($value)) {
            return null;
        }
        $score = (float) $value;
        return $score >= 0 && $score <= 10 ? round($score, 2) : null;
    }

    private function normalizeName(string $value): string
    {
        return mb_strtoupper(preg_replace('/\s+/', ' ', trim($value)) ?: '');
    }

    private function key(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value);
    }

    private function monthNumber(string $month): int
    {
        return match ($this->key($month)) {
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            default => 1,
        };
    }
}
