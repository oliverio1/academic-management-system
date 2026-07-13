<?php

namespace App\Console\Commands;

use App\Models\Campus;
use App\Models\Group;
use App\Models\Level;
use App\Models\Schedule;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportMasterScheduleCommand extends Command
{
    protected $signature = 'master-schedule:import
        {file : Ruta del libro maestro .xlsx}
        {--tenant= : ID del tenant donde se importara}
        {--campus-code= : Codigo de campus, por ejemplo VALLE o FLORIDA}
        {--cycle-id= : ID del ciclo escolar destino}
        {--cycle-code= : Codigo del ciclo escolar destino}
        {--dry-run : Valida y simula sin guardar cambios}
        {--create-missing-groups : Crea grupos faltantes usando las columnas Grado y Grupo}
        {--create-missing-subjects : Crea materias faltantes usando Materias por grupo para las horas semanales}
        {--create-missing-teachers : Crea usuarios/profesores faltantes con contrasena inicial 123123123}
        {--deactivate-existing : Inactiva horarios existentes del ciclo/campus antes de importar}';

    protected $description = 'Importa planeacion academica y horarios desde AMS_Libro_Maestro_Horarios.';

    private array $stats = [
        'rows' => 0,
        'imported' => 0,
        'skipped' => 0,
        'groups' => 0,
        'subjects' => 0,
        'teachers' => 0,
        'cycle_groups' => 0,
        'assignments' => 0,
        'schedules' => 0,
    ];

    private array $warnings = [];

    private array $subjectHours = [];

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($file)) {
            $this->error("No se encontro el archivo: {$file}");
            return self::FAILURE;
        }

        if (! $this->initializeTenant()) {
            return self::FAILURE;
        }

        $campus = $this->resolveCampus();
        if (! $campus) {
            return self::FAILURE;
        }

        $cycle = $this->resolveCycle($campus);
        if (! $cycle) {
            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getSheetByName('Datos');
        if (! $sheet) {
            $this->error('El libro maestro debe contener una hoja llamada Datos.');
            return self::FAILURE;
        }

        $rows = $this->rowsByHeader($sheet);
        if (empty($rows)) {
            $this->error('La hoja Datos no contiene filas importables.');
            return self::FAILURE;
        }
        $this->subjectHours = $this->loadSubjectHours($spreadsheet->getSheetByName('Materias por grupo'));

        $this->info(($dryRun ? 'Validando' : 'Importando') . " {$file}");
        $this->line("Campus: {$campus->name} ({$campus->code})");
        $this->line("Ciclo: {$cycle->name} ({$cycle->code})");

        DB::beginTransaction();

        try {
            if ((bool) $this->option('deactivate-existing')) {
                $this->deactivateExistingSchedules($cycle, $campus);
            }

            foreach ($rows as $rowNumber => $row) {
                $this->importRow($rowNumber, $row, $cycle, $campus);
            }

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
            return self::FAILURE;
        }

        $this->table(['Metrica', 'Total'], [
            ['Filas leidas', $this->stats['rows']],
            ['Filas importadas', $this->stats['imported']],
            ['Filas omitidas', $this->stats['skipped']],
            ['Grupos creados/reactivados', $this->stats['groups']],
            ['Materias creadas/reactivadas', $this->stats['subjects']],
            ['Docentes creados/reactivados', $this->stats['teachers']],
            ['Grupos de ciclo creados/reactivados', $this->stats['cycle_groups']],
            ['Asignaciones creadas/actualizadas', $this->stats['assignments']],
            ['Horarios creados/actualizados', $this->stats['schedules']],
        ]);

        foreach (array_slice($this->warnings, 0, 60) as $warning) {
            $this->warn($warning);
        }

        if (count($this->warnings) > 60) {
            $this->warn('... y ' . (count($this->warnings) - 60) . ' advertencias mas.');
        }

        return self::SUCCESS;
    }

    private function initializeTenant(): bool
    {
        $tenantId = trim((string) $this->option('tenant'));
        if ($tenantId === '') {
            if ((string) tenant('id') !== '') {
                return true;
            }

            $this->error('Indica --tenant=ID para evitar importar datos fuera del tenant.');
            return false;
        }

        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant) {
            $this->error("No existe el tenant {$tenantId}.");
            return false;
        }

        tenancy()->initialize($tenant);

        return true;
    }

    private function resolveCampus(): ?Campus
    {
        $campusCode = trim((string) $this->option('campus-code'));
        if ($campusCode === '') {
            $this->error('Indica --campus-code=VALLE o --campus-code=FLORIDA.');
            return null;
        }

        $campus = Campus::query()
            ->whereRaw('UPPER(code) = ?', [mb_strtoupper($campusCode)])
            ->first();

        if (! $campus) {
            $this->error("No existe el campus {$campusCode}.");
        }

        return $campus;
    }

    private function resolveCycle(Campus $campus): ?SchoolCycle
    {
        $cycleId = (int) $this->option('cycle-id');
        $cycleCode = trim((string) $this->option('cycle-code'));

        $query = SchoolCycle::query()
            ->where(function ($q) use ($campus) {
                $q->where('campus_id', $campus->id)
                    ->orWhereHas('campuses', fn ($qq) => $qq->where('campuses.id', $campus->id));
            });

        if ($cycleId > 0) {
            $cycle = (clone $query)->whereKey($cycleId)->first();
        } elseif ($cycleCode !== '') {
            $cycle = (clone $query)->where('code', $cycleCode)->first();
        } else {
            $cycle = (clone $query)->where('is_active', true)->orderByDesc('start_date')->first();
        }

        if (! $cycle) {
            $this->error('No se encontro ciclo destino. Usa --cycle-id= o --cycle-code=.');
        }

        return $cycle;
    }

    private function importRow(int $rowNumber, array $row, SchoolCycle $cycle, Campus $campus): void
    {
        $this->stats['rows']++;

        $levelName = trim((string) $this->rowValue($row, 'grado'));
        $groupName = trim((string) $this->rowValue($row, 'grupo'));
        $subjectName = trim((string) $this->rowValue($row, 'materia'));
        $teacherName = trim((string) $this->rowValue($row, 'docente'));
        $day = $this->dayKey((string) $this->rowValue($row, 'dia'));
        $time = $this->parseTimeRange((string) $this->rowValue($row, 'hora'));
        $type = trim((string) $this->rowValue($row, 'modalidad'));
        $rawSection = (string) $this->rowValue($row, 'seccion');
        $section = $this->sectionDescriptor($rawSection, $subjectName, $type);
        $sectionNumber = $section['number'];

        $missing = [];
        if ($groupName === '') {
            $missing[] = 'grupo';
        }
        if ($subjectName === '') {
            $missing[] = 'materia';
        }
        if ($teacherName === '') {
            $missing[] = 'docente';
        }
        if (! $day) {
            $missing[] = 'dia';
        }
        if (! $time) {
            $missing[] = 'hora';
        }

        if (! empty($missing)) {
            $this->skip($rowNumber, 'faltan o son invalidos: ' . implode(', ', $missing) . '.');
            return;
        }

        $group = $this->findGroup($groupName);
        if (! $group) {
            if (! (bool) $this->option('create-missing-groups')) {
                $this->skip($rowNumber, "no existe el grupo {$groupName}. Usa --create-missing-groups si debe crearse desde el maestro.");
                return;
            }

            $level = $this->findLevel($levelName);
            if (! $level) {
                $this->skip($rowNumber, "no existe el grado {$levelName} para crear el grupo {$groupName}.");
                return;
            }

            $group = $this->createOrReactivateGroup($groupName, $level);
        }

        $subject = $this->findSubject($subjectName, $group);
        if (! $subject) {
            if (! (bool) $this->option('create-missing-subjects')) {
                $this->skip($rowNumber, "no existe la materia {$subjectName} para el nivel del grupo {$groupName}. Usa --create-missing-subjects si debe crearse desde el maestro.");
                return;
            }

            $subject = $this->createOrReactivateSubject($subjectName, $group->level);
        }

        $teacher = $this->findTeacher($teacherName);
        if (! $teacher) {
            if (! (bool) $this->option('create-missing-teachers')) {
                $this->skip($rowNumber, "no existe el docente {$teacherName}. Usa --create-missing-teachers si debe crearse desde el maestro.");
                return;
            }

            $teacher = $this->createOrReactivateTeacher($teacherName, $campus);
        }

        $cycleGroup = $this->ensureCycleGroup($cycle, $group, $campus, $sectionNumber);
        $group->subjects()->syncWithoutDetaching([$subject->id]);
        $cycleGroup->subjects()->syncWithoutDetaching([$subject->id]);
        $teacher->subjects()->syncWithoutDetaching([$subject->id]);

        $assignment = TeachingAssignment::updateOrCreate(
            [
                'school_cycle_group_id' => $cycleGroup->id,
                'subject_id' => $subject->id,
                'section_number' => $sectionNumber,
            ],
            [
                'tenant_id' => (string) tenant('id') ?: null,
                'group_id' => $group->id,
                'teacher_id' => $teacher->id,
                'section_type' => $section['type'],
                'section_label' => $section['label'],
                'is_active' => true,
            ]
        );

        $this->stats['assignments']++;

        Schedule::updateOrCreate(
            [
                'teaching_assignment_id' => $assignment->id,
                'school_cycle_id' => $cycle->id,
                'section_number' => $sectionNumber,
                'day_of_week' => $day,
                'start_time' => $time[0],
                'end_time' => $time[1],
            ],
            [
                'tenant_id' => (string) tenant('id') ?: null,
                'section_type' => $section['type'],
                'section_label' => $section['label'],
                'type' => $type !== '' ? $type : null,
                'is_active' => true,
            ]
        );

        $this->stats['schedules']++;
        $this->stats['imported']++;
    }

    private function ensureCycleGroup(
        SchoolCycle $cycle,
        Group $group,
        Campus $campus,
        int $sectionNumber
    ): SchoolCycleGroup {
        $modalityId = (int) ($group->level->modality_id ?? 0);

        $cycleGroup = SchoolCycleGroup::updateOrCreate(
            [
                'school_cycle_id' => $cycle->id,
                'group_id' => $group->id,
                'campus_id' => $campus->id,
                'modality_id' => $modalityId ?: null,
            ],
            [
                'tenant_id' => (string) tenant('id') ?: null,
                'section_count' => max($sectionNumber, 1),
                'is_active' => true,
            ]
        );

        if ((int) $cycleGroup->section_count < $sectionNumber) {
            $cycleGroup->update(['section_count' => $sectionNumber]);
        }

        if ($cycleGroup->wasRecentlyCreated || $cycleGroup->wasChanged('is_active')) {
            $this->stats['cycle_groups']++;
        }

        return $cycleGroup;
    }

    private function deactivateExistingSchedules(SchoolCycle $cycle, Campus $campus): void
    {
        Schedule::query()
            ->where('school_cycle_id', $cycle->id)
            ->whereHas('assignment.schoolCycleGroup', fn ($q) => $q->where('campus_id', $campus->id))
            ->update(['is_active' => false]);
    }

    private function rowValue(array $row, string $key): mixed
    {
        $target = $this->normalizeKey($key);

        foreach ($row as $header => $value) {
            if ($this->normalizeKey((string) $header) === $target) {
                return $value;
            }
        }

        return null;
    }

    private function rowsByHeader(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $headerRow = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0] ?? [];
        $headers = array_map(fn ($value) => $this->canonicalHeader((string) $value), $headerRow);
        $rows = [];

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $values = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, true, false)[0] ?? [];
            $row = [];
            $hasValue = false;

            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $value = $values[$index] ?? null;
                $row[$header] = $value;
                $hasValue = $hasValue || trim((string) $value) !== '';
            }

            if ($hasValue) {
                $rows[$rowNumber] = $row;
            }
        }

        return $rows;
    }

    private function loadSubjectHours(?Worksheet $sheet): array
    {
        if (! $sheet) {
            return [];
        }

        $hours = [];

        foreach ($this->rowsByHeader($sheet) as $row) {
            $level = $this->normalizeKey((string) $this->rowValue($row, 'grado'));
            $subject = $this->normalizeKey((string) $this->rowValue($row, 'materia'));
            $weeklyHours = (int) $this->rowValue($row, 'horas/fichas semanales');

            if ($level === '' || $subject === '' || $weeklyHours <= 0) {
                continue;
            }

            $hours[$level][$subject] = max($hours[$level][$subject] ?? 0, $weeklyHours);
        }

        return $hours;
    }

    private function findGroup(string $groupName): ?Group
    {
        return Group::query()
            ->with('level')
            ->where('name', $groupName)
            ->first();
    }

    private function findLevel(string $levelName): ?Level
    {
        $target = $this->normalizeKey($levelName);

        return Level::query()
            ->where('is_active', true)
            ->get()
            ->first(fn (Level $level) => $this->normalizeKey($level->name) === $target);
    }

    private function createOrReactivateGroup(string $groupName, Level $level): Group
    {
        $group = Group::query()->firstOrCreate(
            [
                'level_id' => $level->id,
                'name' => $groupName,
            ],
            [
                'capacity' => null,
                'is_active' => true,
            ]
        );

        if (! $group->is_active) {
            $group->update(['is_active' => true]);
        }

        $group->load('level');
        $this->stats['groups']++;

        return $group;
    }

    private function findSubject(string $subjectName, Group $group): ?Subject
    {
        $target = $this->normalizeKey($subjectName);

        return Subject::query()
            ->where('level_id', $group->level_id)
            ->where('is_active', true)
            ->get()
            ->first(fn (Subject $subject) => $this->normalizeKey($subject->name) === $target);
    }

    private function createOrReactivateSubject(string $subjectName, Level $level): Subject
    {
        $weeklyHours = $this->subjectHours[$this->normalizeKey($level->name)][$this->normalizeKey($subjectName)] ?? 1;

        $subject = Subject::query()->firstOrCreate(
            [
                'level_id' => $level->id,
                'name' => $subjectName,
            ],
            [
                'hours_per_week' => $weeklyHours,
                'type' => 'Teórica',
                'is_active' => true,
            ]
        );

        $updates = [];
        if (! $subject->is_active) {
            $updates['is_active'] = true;
        }
        if ((int) $subject->hours_per_week !== (int) $weeklyHours && $weeklyHours > 0) {
            $updates['hours_per_week'] = $weeklyHours;
        }
        if (empty($subject->type)) {
            $updates['type'] = 'Teórica';
        }
        if (! empty($updates)) {
            $subject->update($updates);
        }

        $this->stats['subjects']++;

        return $subject;
    }

    private function findTeacher(string $teacherName): ?Teacher
    {
        $target = $this->normalizeKey($teacherName);

        return Teacher::query()
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->first(fn (Teacher $teacher) => $this->normalizeKey($teacher->user->name ?? '') === $target);
    }

    private function createOrReactivateTeacher(string $teacherName, Campus $campus): Teacher
    {
        $user = User::query()
            ->with('teacher')
            ->get()
            ->first(fn (User $user) => $this->normalizeKey($user->name) === $this->normalizeKey($teacherName));

        if (! $user) {
            $user = User::create([
                'default_campus_id' => $campus->id,
                'name' => $teacherName,
                'email' => $this->generateTeacherEmail($teacherName),
                'password' => Hash::make('123123123'),
            ]);
        }

        if (! $user->hasRole('teacher')) {
            $user->assignRole('teacher');
        }

        $user->campuses()->syncWithoutDetaching([$campus->id]);

        $teacher = $user->teacher ?: Teacher::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        if (! $teacher->is_active) {
            $teacher->update(['is_active' => true]);
        }

        $teacher->load('user');
        $this->stats['teachers']++;

        return $teacher;
    }

    private function generateTeacherEmail(string $teacherName): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '.', $this->normalizeKey($teacherName)) ?? '';
        $base = trim($base, '.');
        $base = $base !== '' ? $base : 'docente';
        $email = "{$base}@ula.local";
        $counter = 1;

        while (User::query()->where('email', $email)->exists()) {
            $email = "{$base}{$counter}@ula.local";
            $counter++;
        }

        return $email;
    }

    private function dayKey(string $day): ?string
    {
        $normalized = $this->normalizeKey($day);

        return match (true) {
            str_starts_with($normalized, 'lun') => 'monday',
            str_starts_with($normalized, 'mar') => 'tuesday',
            str_starts_with($normalized, 'mie') || str_starts_with($normalized, 'mi') => 'wednesday',
            str_starts_with($normalized, 'jue') => 'thursday',
            str_starts_with($normalized, 'vie') => 'friday',
            str_starts_with($normalized, 'sab') => 'saturday',
            str_starts_with($normalized, 'dom') => 'sunday',
            default => null,
        };
    }

    private function parseTimeRange(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (! preg_match_all('/\d{1,2}:\d{2}/', $value, $matches) || count($matches[0]) !== 2) {
            return null;
        }

        $start = $this->normalizeTime($matches[0][0]);
        $end = $this->normalizeTime($matches[0][1]);

        return $start && $end && $end > $start ? [$start, $end] : null;
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim($value);
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $value, $matches)) {
            return null;
        }

        return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
    }

    private function sectionNumber(string $value): int
    {
        $normalized = $this->normalizeKey($value);

        return match (true) {
            in_array($normalized, ['b', '2'], true) || str_contains($normalized, 'seccion b') => 2,
            in_array($normalized, ['c', '3'], true) || str_contains($normalized, 'seccion c') => 3,
            default => 1,
        };
    }

    private function sectionDescriptor(string $rawSection, string $subjectName, string $type): array
    {
        $subjectKey = $this->normalizeKey($subjectName);
        $typeKey = $this->normalizeKey($type);
        $sectionKey = $this->normalizeKey($rawSection);

        if (str_contains($subjectKey, 'ingles') || str_contains($subjectKey, 'english')) {
            $isAdvanced = str_contains($sectionKey, 'avanz')
                || str_contains($sectionKey, 'advanced')
                || str_contains($sectionKey, 'seccion b')
                || $sectionKey === 'b'
                || $sectionKey === '2';

            return [
                'number' => $isAdvanced ? 2 : 1,
                'type' => 'english',
                'label' => $isAdvanced ? 'AVANZADO' : 'BASICO',
            ];
        }

        if (
            str_contains($subjectKey, 'laboratorio')
            || str_contains($subjectKey, 'lab')
            || str_contains($subjectKey, 'taller')
            || str_contains($typeKey, 'laboratorio')
            || str_contains($typeKey, 'lab')
            || str_contains($typeKey, 'taller')
            || str_contains($typeKey, 'dividid')
        ) {
            $number = $this->sectionNumber($rawSection);
            $label = match ($number) {
                2 => 'B',
                3 => 'C',
                default => 'A',
            };

            return [
                'number' => $number,
                'type' => 'lab_taller',
                'label' => $label,
            ];
        }

        return [
            'number' => $this->sectionNumber($rawSection),
            'type' => null,
            'label' => null,
        ];
    }

    private function skip(int $rowNumber, string $reason): void
    {
        $this->stats['skipped']++;
        $this->warnings[] = "Fila {$rowNumber}: {$reason}";
    }

    private function canonicalHeader(string $value): string
    {
        $key = $this->normalizeKey($value);

        return match ($key) {
            'grado' => 'grado',
            'grupo' => 'grupo',
            'dia' => 'dia',
            'periodo' => 'periodo',
            'hora' => 'hora',
            'materia' => 'materia',
            'modalidad' => 'modalidad',
            'seccion' => 'seccion',
            'docente' => 'docente',
            'horas' => 'horas',
            default => $key,
        };
    }

    private function normalizeKey(string $value): string
    {
        $value = trim($value);
        $value = strtr($value, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = mb_strtolower($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }
}
