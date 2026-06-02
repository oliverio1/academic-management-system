<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\Student;
use App\Models\StudentGroupHistory;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportListamaestraStudents extends Command
{
    protected $signature = 'listamaestra:import-students
        {file : Ruta del archivo LISTAMAESTRA .xlsx}
        {--campus-code= : Codigo de campus para resolver grupos, por ejemplo VALLE o FLORIDA}
        {--domain=my.ula.edu.mx : Dominio para correos generados}
        {--dry-run : Simula la importacion sin guardar cambios}
        {--deactivate-missing : Marca inactivos alumnos del campus que no aparezcan en LISTAMAESTRA}';

    protected $description = 'Importa o actualiza alumnos desde la hoja ALUMNOS de LISTAMAESTRA.';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $campusCode = trim((string) $this->option('campus-code'));
        $domain = trim((string) $this->option('domain')) ?: 'my.ula.edu.mx';
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($file)) {
            $this->error("No se encontro el archivo: {$file}");
            return self::FAILURE;
        }

        if ($campusCode === '') {
            $this->error('Indica --campus-code=VALLE o --campus-code=FLORIDA.');
            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($file);
        $alumnosSheet = $spreadsheet->getSheetByName('ALUMNOS');
        $gruposSheet = $spreadsheet->getSheetByName('GRUPOS');

        if (! $alumnosSheet || ! $gruposSheet) {
            $this->error('El archivo debe tener las hojas ALUMNOS y GRUPOS.');
            return self::FAILURE;
        }

        $campus = DB::table('campuses')
            ->whereRaw('UPPER(code) = ?', [mb_strtoupper($campusCode)])
            ->first();

        if (! $campus) {
            $this->error("No existe el campus {$campusCode}.");
            return self::FAILURE;
        }

        $groupMap = $this->groupMap($gruposSheet, (int) $campus->id);
        $rows = $this->studentsRows($alumnosSheet);
        $seenEnrollments = [];
        $stats = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'deactivated' => 0,
            'skipped' => 0,
        ];
        $warnings = [];

        DB::beginTransaction();

        try {
            foreach ($rows as $rowNumber => $row) {
                $enrollment = $this->normalizeEnrollment((string) ($row['MATRICULA'] ?? ''));
                $name = $this->normalizeName((string) ($row['NOMBRE'] ?? ''));
                $externalGroupId = trim((string) ($row['GRUPO_ID'] ?? ''));
                $status = $this->normalizeStatus((string) ($row['ESTATUS'] ?? 'ACTIVO'));

                if ($enrollment === '' || $name === '' || $externalGroupId === '') {
                    $stats['skipped']++;
                    $warnings[] = "Fila {$rowNumber}: faltan MATRICULA, NOMBRE o GRUPO_ID.";
                    continue;
                }

                if (isset($seenEnrollments[$enrollment])) {
                    $stats['skipped']++;
                    $warnings[] = "Fila {$rowNumber}: matricula duplicada en archivo ({$enrollment}).";
                    continue;
                }

                $seenEnrollments[$enrollment] = true;
                $groupId = $groupMap[$externalGroupId] ?? null;

                if (! $groupId) {
                    $stats['skipped']++;
                    $warnings[] = "Fila {$rowNumber}: no se encontro grupo {$externalGroupId} en campus {$campusCode}.";
                    continue;
                }

                $result = $this->upsertStudent($enrollment, $name, (int) $groupId, (int) $campus->id, $status, $domain);
                $stats[$result]++;
            }

            if ((bool) $this->option('deactivate-missing')) {
                $stats['deactivated'] = $this->deactivateMissing(array_keys($seenEnrollments), (int) $campus->id);
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
            ['Creados', $stats['created']],
            ['Actualizados', $stats['updated']],
            ['Sin cambios', $stats['unchanged']],
            ['Inactivados', $stats['deactivated']],
            ['Omitidos', $stats['skipped']],
        ]);

        foreach (array_slice($warnings, 0, 40) as $warning) {
            $this->warn($warning);
        }

        if (count($warnings) > 40) {
            $this->warn('... y ' . (count($warnings) - 40) . ' advertencias mas.');
        }

        return self::SUCCESS;
    }

    private function groupMap(Worksheet $sheet, int $campusId): array
    {
        $rows = $this->rowsByHeader($sheet);
        $map = [];

        foreach ($rows as $row) {
            $externalId = trim((string) ($row['GRUPO_ID'] ?? ''));
            $visibleName = trim((string) ($row['NOMBRE_VISIBLE'] ?? ''));

            if ($externalId === '' || $visibleName === '') {
                continue;
            }

            $groupId = Group::query()
                ->where('campus_id', $campusId)
                ->where('name', $visibleName)
                ->value('id');

            if ($groupId) {
                $map[$externalId] = (int) $groupId;
            }
        }

        return $map;
    }

    private function studentsRows(Worksheet $sheet): array
    {
        return $this->rowsByHeader($sheet);
    }

    private function rowsByHeader(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $headerRow = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0] ?? [];
        $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), $headerRow);
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

    private function upsertStudent(
        string $enrollment,
        string $name,
        int $groupId,
        int $campusId,
        string $status,
        string $domain
    ): string {
        $student = Student::query()->where('enrollment_number', $enrollment)->with('user')->first();
        $isActive = $status !== 'INACTIVO';

        if (! $student) {
            $user = User::create([
                'name' => $name,
                'email' => $this->emailForEnrollment($enrollment, $domain),
                'password' => Hash::make('123123123'),
                'default_campus_id' => $campusId,
            ]);
            $user->assignRole('student');
            $user->campuses()->syncWithoutDetaching([$campusId]);

            $student = Student::create([
                'user_id' => $user->id,
                'group_id' => $groupId,
                'enrollment_number' => $enrollment,
                'is_active' => $isActive,
            ]);

            StudentGroupHistory::create([
                'student_id' => $student->id,
                'group_id' => $groupId,
                'start_date' => now(),
                'reason' => 'importacion_listamaestra',
            ]);

            return 'created';
        }

        $changes = [];

        if ($student->user) {
            $userChanges = [];

            if ($student->user->name !== $name) {
                $userChanges['name'] = $name;
            }

            if ((int) ($student->user->default_campus_id ?? 0) !== $campusId) {
                $userChanges['default_campus_id'] = $campusId;
            }

            if (! empty($userChanges)) {
                $student->user->update($userChanges);
                $changes[] = 'user';
            }

            $student->user->campuses()->syncWithoutDetaching([$campusId]);
        }

        if ((int) $student->group_id !== $groupId) {
            $previousGroupId = (int) $student->group_id;
            $student->group_id = $groupId;
            $changes[] = 'group';

            StudentGroupHistory::query()
                ->where('student_id', $student->id)
                ->whereNull('end_date')
                ->update(['end_date' => now()]);

            StudentGroupHistory::create([
                'student_id' => $student->id,
                'group_id' => $groupId,
                'start_date' => now(),
                'reason' => "importacion_listamaestra_desde_grupo_{$previousGroupId}",
            ]);
        }

        if ((bool) $student->is_active !== $isActive) {
            $student->is_active = $isActive;
            $changes[] = 'status';
        }

        if ($student->isDirty()) {
            $student->save();
        }

        return empty($changes) ? 'unchanged' : 'updated';
    }

    private function deactivateMissing(array $seenEnrollments, int $campusId): int
    {
        return Student::query()
            ->whereHas('group', fn ($query) => $query->where('campus_id', $campusId))
            ->whereNotIn('enrollment_number', $seenEnrollments)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    private function normalizeHeader(string $value): string
    {
        return mb_strtoupper(trim($value));
    }

    private function normalizeEnrollment(string $value): string
    {
        return mb_strtoupper(trim($value));
    }

    private function normalizeName(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?: '';

        return mb_strtoupper($value);
    }

    private function normalizeStatus(string $value): string
    {
        $value = mb_strtoupper(trim($value));

        return in_array($value, ['INACTIVO', 'BAJA'], true) ? 'INACTIVO' : 'ACTIVO';
    }

    private function emailForEnrollment(string $enrollment, string $domain): string
    {
        $base = mb_strtolower($enrollment) . '@' . $domain;

        if (! User::query()->where('email', $base)->exists()) {
            return $base;
        }

        $prefix = mb_strtolower($enrollment);
        $counter = 2;

        do {
            $email = "{$prefix}-{$counter}@{$domain}";
            $counter++;
        } while (User::query()->where('email', $email)->exists());

        return $email;
    }
}
