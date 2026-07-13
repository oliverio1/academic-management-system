<?php

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Group;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\StudentGroupHistory;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CycleStudentsTemplateController extends Controller
{
    private const STUDENT_ROWS_PER_GROUP = 35;
    private const SESSION_KEY = 'cycle_students_import';
    private const HEADERS = [
        'CAMPUS',
        'CICLO',
        'GRADO',
        'GRUPO',
        'INGLES',
        'LAB_TALLER',
        'SECCION',
        'MATRICULA',
        'NOMBRE',
        'APELLIDO_PATERNO',
        'APELLIDO_MATERNO',
        'CORREO_ALUMNO',
        'TELEFONO_ALUMNO',
        'ESTATUS',
        'TUTOR_NOMBRE',
        'TUTOR_PARENTESCO',
        'TUTOR_CORREO',
        'TUTOR_TELEFONO',
        'DIRECCION',
        'OBSERVACIONES',
    ];

    public function create()
    {
        $activeCampusId = (int) session('active_campus_id', 0);

        $campuses = Campus::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $cycles = SchoolCycle::query()
            ->with(['campus:id,code,name'])
            ->withCount(['cycleGroups as active_groups_count'])
            ->when($activeCampusId > 0, fn ($query) => $this->applyCampusFilter($query, $activeCampusId))
            ->orderByDesc('start_date')
            ->get();

        return view('imports.cycle-students.create', compact('campuses', 'cycles', 'activeCampusId'));
    }

    public function template(Request $request)
    {
        $data = $request->validate([
            'campus_id' => ['required', 'integer', Rule::exists('campuses', 'id')],
            'school_cycle_id' => ['required', 'integer', Rule::exists('school_cycles', 'id')],
        ]);

        $campus = Campus::query()->findOrFail((int) $data['campus_id']);
        $cycle = SchoolCycle::query()
            ->with(['campus:id,code,name'])
            ->where('id', (int) $data['school_cycle_id'])
            ->firstOrFail();

        if (! $this->cycleBelongsToCampus($cycle, (int) $campus->id)) {
            throw ValidationException::withMessages([
                'school_cycle_id' => 'El ciclo no pertenece al campus seleccionado.',
            ]);
        }

        $cycleGroups = SchoolCycleGroup::query()
            ->with(['group.level.modality'])
            ->where('school_cycle_id', (int) $cycle->id)
            ->where('campus_id', (int) $campus->id)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn ($cycleGroup) => mb_strtolower(sprintf(
                '%s %s',
                (string) optional($cycleGroup->group?->level)->name,
                (string) $cycleGroup->group?->name
            )))
            ->values();

        if ($cycleGroups->isEmpty()) {
            throw ValidationException::withMessages([
                'school_cycle_id' => 'El ciclo seleccionado no tiene grupos activos para este campus.',
            ]);
        }

        $spreadsheet = $this->buildSpreadsheet($campus, $cycle, $cycleGroups);
        $fileName = sprintf(
            'cargar-alumnos-%s-%s.xlsx',
            Str::slug((string) $campus->code),
            Str::slug((string) $cycle->code)
        );

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'campus_id' => ['required', 'integer', Rule::exists('campuses', 'id')],
            'school_cycle_id' => ['required', 'integer', Rule::exists('school_cycles', 'id')],
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
            'deactivate_missing' => ['nullable', 'boolean'],
        ]);

        [$campus, $cycle] = $this->resolveCampusAndCycle((int) $data['campus_id'], (int) $data['school_cycle_id']);
        $filePath = $request->file('file')->storeAs(
            'imports/cycle-students',
            Str::uuid() . '.' . $request->file('file')->getClientOriginalExtension()
        );

        $summary = $this->processImport(Storage::path($filePath), $campus, $cycle, true, [
            'deactivate_missing' => ! empty($data['deactivate_missing']),
        ]);

        session([self::SESSION_KEY => [
            'file_path' => $filePath,
            'campus_id' => (int) $campus->id,
            'cycle_id' => (int) $cycle->id,
            'options' => [
                'deactivate_missing' => ! empty($data['deactivate_missing']),
            ],
        ]]);

        return view('imports.cycle-students.preview', [
            'campus' => $campus,
            'cycle' => $cycle,
            'summary' => $summary,
            'options' => [
                'deactivate_missing' => ! empty($data['deactivate_missing']),
            ],
        ]);
    }

    public function import()
    {
        $payload = session(self::SESSION_KEY);
        abort_if(! is_array($payload), 404);

        $filePath = (string) ($payload['file_path'] ?? '');
        if ($filePath === '' || ! Storage::exists($filePath)) {
            return redirect()
                ->route('imports.cycle-students.create')
                ->withErrors(['file' => 'El archivo temporal ya no esta disponible. Vuelve a cargar el archivo.']);
        }

        [$campus, $cycle] = $this->resolveCampusAndCycle((int) $payload['campus_id'], (int) $payload['cycle_id']);
        $summary = $this->processImport(Storage::path($filePath), $campus, $cycle, false, (array) ($payload['options'] ?? []));

        session()->forget(self::SESSION_KEY);

        return view('imports.cycle-students.result', compact('campus', 'cycle', 'summary'));
    }

    private function buildSpreadsheet(Campus $campus, SchoolCycle $cycle, $cycleGroups): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator(config('app.name'))
            ->setTitle('Carga de alumnos a ciclo')
            ->setSubject($cycle->name);

        $studentsSheet = $spreadsheet->getActiveSheet();
        $studentsSheet->setTitle('ALUMNOS');
        $this->buildStudentsSheet($studentsSheet, $campus, $cycle, $cycleGroups);

        $catalogSheet = $spreadsheet->createSheet();
        $catalogSheet->setTitle('CATALOGOS');
        $this->buildCatalogSheet($catalogSheet, $campus, $cycle, $cycleGroups);

        $instructionsSheet = $spreadsheet->createSheet();
        $instructionsSheet->setTitle('INSTRUCCIONES');
        $this->buildInstructionsSheet($instructionsSheet);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function buildStudentsSheet(Worksheet $sheet, Campus $campus, SchoolCycle $cycle, $cycleGroups): void
    {
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $this->styleHeader($sheet, 'A1:T1');

        $row = 2;
        foreach ($cycleGroups as $cycleGroup) {
            $group = $cycleGroup->group;
            $level = optional($group?->level)->name ?: '';

            for ($index = 0; $index < self::STUDENT_ROWS_PER_GROUP; $index++) {
                $sheet->fromArray([
                    $campus->code,
                    $cycle->name,
                    $level,
                    $group?->name,
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    'ACTIVO',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                ], null, "A{$row}");
                $row++;
            }
        }

        $lastRow = max(2, $row - 1);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:T{$lastRow}");
        $sheet->getStyle("A1:T{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle("A2:T{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        foreach (range('A', 'T') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $this->applyListValidation($sheet, "E2:E{$lastRow}", 'CATALOGOS!$G$2:$G$3');
        $this->applyListValidation($sheet, "F2:F{$lastRow}", 'CATALOGOS!$H$2:$H$4');
        $this->applyListValidation($sheet, "G2:G{$lastRow}", 'CATALOGOS!$F$2:$F$4');
        $this->applyListValidation($sheet, "N2:N{$lastRow}", 'CATALOGOS!$D$2:$D$3');
    }

    private function buildCatalogSheet(Worksheet $sheet, Campus $campus, SchoolCycle $cycle, $cycleGroups): void
    {
        $sheet->fromArray([
            ['CAMPUS', 'CICLO', 'GRUPOS_DEL_CICLO', 'ESTATUS', 'PARENTESCOS', 'SECCIONES', 'INGLES', 'LAB_TALLER'],
            [$campus->code . ' - ' . $campus->name, $cycle->code . ' - ' . $cycle->name, '', 'ACTIVO', 'MADRE', '1', 'BASICO', 'A'],
            ['', '', '', 'INACTIVO', 'PADRE', '2', 'AVANZADO', 'B'],
            ['', '', '', '', 'TUTOR', '3', '', 'C'],
            ['', '', '', '', 'ABUELA/O', '', '', ''],
            ['', '', '', '', 'OTRO', '', '', ''],
        ], null, 'A1');

        $row = 2;
        foreach ($cycleGroups as $cycleGroup) {
            $group = $cycleGroup->group;
            $level = optional($group?->level)->name ?: 'Sin grado';
            $sheet->setCellValue("C{$row}", trim($level . ' ' . ($group?->name ?? '')));
            $row++;
        }

        $lastRow = max(6, $row - 1);
        $this->styleHeader($sheet, 'A1:H1');
        $sheet->getStyle("A1:H{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function buildInstructionsSheet(Worksheet $sheet): void
    {
        $rows = [
            ['Carga de alumnos a ciclo'],
            ['Completa solo la hoja ALUMNOS y conserva los encabezados sin cambiarlos.'],
            ['Las columnas CAMPUS, CICLO, GRADO y GRUPO ya vienen precargadas desde el ciclo seleccionado.'],
            ['MATRICULA, NOMBRE y al menos un apellido seran necesarios para importar.'],
            ['ESTATUS acepta ACTIVO o INACTIVO. Si se deja vacio, se tomara como ACTIVO.'],
            ['INGLES acepta BASICO o AVANZADO. LAB_TALLER acepta A, B o C. Puedes dejar ambos vacios si no aplican.'],
            ['SECCION queda como compatibilidad con archivos anteriores; en nuevos archivos usa INGLES y LAB_TALLER.'],
            ['Los datos del tutor quedan listos para una importacion posterior y asignacion familiar.'],
            ['No elimines hojas ni columnas. Puedes agregar filas copiando una fila del mismo grupo.'],
            ['Ejemplo de fila ALUMNOS: FLORIDA | PREPARATORIA 25-26 FLORIDA | Cuarto | 4001 | BASICO | A | | U99826677 | OLIVER | MARTINEZ | ANAYA | oliverio.oo@gmail.com | 5569177811 | ACTIVO | DIONICIO MARTINEZ PINEDA | PADRE | marpindi@gmail.com | 5568177811 | Av. Mexico 410 E-102 |'],
        ];

        $sheet->fromArray($rows, null, 'A1');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1:F10')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getColumnDimension('A')->setWidth(110);
    }

    private function styleHeader(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E79'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    private function applyListValidation(Worksheet $sheet, string $range, string $formula): void
    {
        foreach ($sheet->rangeToArray($range, null, true, true, true) as $rowNumber => $columns) {
            foreach (array_keys($columns) as $column) {
                $validation = $sheet->getCell("{$column}{$rowNumber}")->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                $validation->setShowDropDown(true);
                $validation->setFormula1($formula);
            }
        }
    }

    private function cycleBelongsToCampus(SchoolCycle $cycle, int $campusId): bool
    {
        return (int) ($cycle->campus_id ?? 0) === $campusId
            || $cycle->campuses()->where('campuses.id', $campusId)->exists();
    }

    private function applyCampusFilter($query, int $campusId): void
    {
        $query->where(function ($nested) use ($campusId) {
            $nested->where('campus_id', $campusId)
                ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $campusId));
        });
    }

    private function resolveCampusAndCycle(int $campusId, int $cycleId): array
    {
        $campus = Campus::query()->findOrFail($campusId);
        $cycle = SchoolCycle::query()
            ->with(['campus:id,code,name', 'campuses:id,code,name'])
            ->findOrFail($cycleId);

        if (! $this->cycleBelongsToCampus($cycle, (int) $campus->id)) {
            throw ValidationException::withMessages([
                'school_cycle_id' => 'El ciclo no pertenece al campus seleccionado.',
            ]);
        }

        return [$campus, $cycle];
    }

    private function processImport(string $filePath, Campus $campus, SchoolCycle $cycle, bool $dryRun, array $options): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName('ALUMNOS');

        if (! $sheet) {
            throw ValidationException::withMessages([
                'file' => 'El archivo debe incluir la hoja ALUMNOS.',
            ]);
        }

        $rows = $this->studentRows($sheet);
        $groupMap = $this->cycleGroupMap($campus, $cycle);
        $seenEnrollments = [];
        $summary = [
            'metrics' => [
                'Filas leidas' => count($rows),
                'Filas validas' => 0,
                'Filas omitidas' => 0,
                'Alumnos creados' => 0,
                'Alumnos actualizados' => 0,
                'Tutores creados' => 0,
                'Tutores vinculados' => 0,
                'Vinculos de seccion' => 0,
                'Alumnos inactivados' => 0,
            ],
            'warnings' => [],
            'dry_run' => $dryRun,
        ];

        DB::beginTransaction();

        try {
            foreach ($rows as $rowNumber => $row) {
                $normalized = $this->normalizeImportRow($row);

                if ($this->isBlankStudentRow($normalized)) {
                    continue;
                }

                $validationWarning = $this->validateStudentRow($normalized, $rowNumber, $campus, $cycle, $groupMap, $seenEnrollments);
                if ($validationWarning) {
                    $summary['metrics']['Filas omitidas']++;
                    $summary['warnings'][] = $validationWarning;
                    continue;
                }

                $seenEnrollments[$normalized['MATRICULA']] = true;
                $summary['metrics']['Filas validas']++;

                if ($dryRun) {
                    $existing = Student::query()->where('enrollment_number', $normalized['MATRICULA'])->exists();
                    $summary['metrics'][$existing ? 'Alumnos actualizados' : 'Alumnos creados']++;
                    if ($normalized['TUTOR_NOMBRE'] !== '' || $normalized['TUTOR_CORREO'] !== '') {
                        if ($normalized['TUTOR_CORREO'] === '' || ! User::query()->where('email', $normalized['TUTOR_CORREO'])->exists()) {
                            $summary['metrics']['Tutores creados']++;
                        }
                        $summary['metrics']['Tutores vinculados']++;
                    }
                    $cycleGroup = $groupMap[$this->groupLookupKey($normalized['GRADO'], $normalized['GRUPO'])];
                    $summary['metrics']['Vinculos de seccion'] += $this->countSectionAssignmentsForRow($cycleGroup, $normalized);
                    continue;
                }

                $result = $this->upsertStudentFromRow($normalized, $campus, $cycle, $groupMap);
                $summary['metrics'][$result['student_metric']]++;
                $summary['metrics']['Tutores creados'] += $result['guardian_created'] ? 1 : 0;
                $summary['metrics']['Tutores vinculados'] += $result['guardian_linked'] ? 1 : 0;
                $summary['metrics']['Vinculos de seccion'] += $result['section_links'];
            }

            if (! $dryRun && ! empty($options['deactivate_missing'])) {
                $summary['metrics']['Alumnos inactivados'] = $this->deactivateMissingStudents($campus, $cycle, array_keys($seenEnrollments));
            } elseif ($dryRun && ! empty($options['deactivate_missing'])) {
                $summary['metrics']['Alumnos inactivados'] = $this->countMissingStudents($campus, $cycle, array_keys($seenEnrollments));
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        $summary['has_warnings'] = ! empty($summary['warnings']) || (int) $summary['metrics']['Filas omitidas'] > 0;

        return $summary;
    }

    private function studentRows(Worksheet $sheet): array
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

    private function cycleGroupMap(Campus $campus, SchoolCycle $cycle): array
    {
        return SchoolCycleGroup::query()
            ->with(['group.level'])
            ->where('school_cycle_id', (int) $cycle->id)
            ->where('campus_id', (int) $campus->id)
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(function (SchoolCycleGroup $cycleGroup) {
                $group = $cycleGroup->group;
                $level = optional($group?->level)->name ?: '';
                $key = $this->groupLookupKey($level, (string) ($group?->name ?? ''));

                return [$key => $cycleGroup];
            })
            ->all();
    }

    private function normalizeImportRow(array $row): array
    {
        $normalized = [];

        foreach (self::HEADERS as $header) {
            $value = $row[$header] ?? '';
            $normalized[$header] = trim((string) $value);
        }

        $normalized['CAMPUS'] = mb_strtoupper($normalized['CAMPUS']);
        $normalized['MATRICULA'] = mb_strtoupper($normalized['MATRICULA']);
        $normalized['ESTATUS'] = $this->normalizeStatus($normalized['ESTATUS']);
        $normalized['INGLES'] = $this->normalizeEnglishSection($normalized['INGLES']);
        $normalized['LAB_TALLER'] = $this->normalizeLabSection($normalized['LAB_TALLER']);
        $normalized['SECCION'] = $normalized['SECCION'] !== '' ? (string) max(1, (int) $normalized['SECCION']) : '';
        $normalized['CORREO_ALUMNO'] = mb_strtolower($normalized['CORREO_ALUMNO']);
        $normalized['TUTOR_CORREO'] = mb_strtolower($normalized['TUTOR_CORREO']);

        return $normalized;
    }

    private function validateStudentRow(array $row, int $rowNumber, Campus $campus, SchoolCycle $cycle, array $groupMap, array $seenEnrollments): ?string
    {
        foreach (['CAMPUS', 'CICLO', 'GRADO', 'GRUPO', 'MATRICULA', 'NOMBRE'] as $required) {
            if ($row[$required] === '') {
                return "Fila {$rowNumber}: falta {$required}.";
            }
        }

        if ($row['APELLIDO_PATERNO'] === '' && $row['APELLIDO_MATERNO'] === '') {
            return "Fila {$rowNumber}: captura al menos un apellido.";
        }

        if ($row['CAMPUS'] !== mb_strtoupper((string) $campus->code)) {
            return "Fila {$rowNumber}: campus {$row['CAMPUS']} no coincide con {$campus->code}.";
        }

        if (! $this->matchesCycle($row['CICLO'], $cycle)) {
            return "Fila {$rowNumber}: ciclo {$row['CICLO']} no coincide con {$cycle->name}.";
        }

        if (isset($seenEnrollments[$row['MATRICULA']])) {
            return "Fila {$rowNumber}: matricula duplicada en el archivo ({$row['MATRICULA']}).";
        }

        if (! isset($groupMap[$this->groupLookupKey($row['GRADO'], $row['GRUPO'])])) {
            return "Fila {$rowNumber}: no se encontro el grupo {$row['GRADO']} {$row['GRUPO']} en este ciclo/campus.";
        }

        if ($row['INGLES'] !== '' && ! in_array($row['INGLES'], ['BASICO', 'AVANZADO'], true)) {
            return "Fila {$rowNumber}: INGLES debe ser BASICO o AVANZADO.";
        }

        if ($row['LAB_TALLER'] !== '' && ! in_array($row['LAB_TALLER'], ['A', 'B', 'C'], true)) {
            return "Fila {$rowNumber}: LAB_TALLER debe ser A, B o C.";
        }

        if ($row['CORREO_ALUMNO'] !== '' && ! filter_var($row['CORREO_ALUMNO'], FILTER_VALIDATE_EMAIL)) {
            return "Fila {$rowNumber}: correo de alumno invalido.";
        }

        if ($row['TUTOR_CORREO'] !== '' && ! filter_var($row['TUTOR_CORREO'], FILTER_VALIDATE_EMAIL)) {
            return "Fila {$rowNumber}: correo de tutor invalido.";
        }

        return null;
    }

    private function upsertStudentFromRow(array $row, Campus $campus, SchoolCycle $cycle, array $groupMap): array
    {
        $cycleGroup = $groupMap[$this->groupLookupKey($row['GRADO'], $row['GRUPO'])];
        $group = $cycleGroup->group;
        $fullName = $this->fullStudentName($row);
        $student = Student::query()->where('enrollment_number', $row['MATRICULA'])->with('user')->first();
        $isActive = $row['ESTATUS'] !== 'INACTIVO';
        $guardianResult = $this->resolveGuardian($row, $campus);

        if (! $student) {
            $user = User::create([
                'name' => $fullName,
                'email' => $this->resolveStudentEmail($row),
                'password' => Hash::make('123123123'),
                'default_campus_id' => (int) $campus->id,
            ]);
            $user->assignRole('student');
            $user->campuses()->syncWithoutDetaching([(int) $campus->id]);

            $student = Student::create([
                'user_id' => $user->id,
                'guardian_user_id' => $guardianResult['guardian']?->id,
                'group_id' => (int) $group->id,
                'enrollment_number' => $row['MATRICULA'],
                'phone' => $row['TELEFONO_ALUMNO'] ?: null,
                'address' => $row['DIRECCION'] ?: null,
                'is_active' => $isActive,
            ]);

            StudentGroupHistory::create([
                'student_id' => $student->id,
                'group_id' => (int) $group->id,
                'start_date' => now(),
                'reason' => 'importacion_alumnos_ciclo_' . $cycle->code,
            ]);

            $studentMetric = 'Alumnos creados';
        } else {
            $studentMetric = 'Alumnos actualizados';
            $student->user?->update([
                'name' => $fullName,
                'default_campus_id' => (int) $campus->id,
            ]);
            $student->user?->campuses()->syncWithoutDetaching([(int) $campus->id]);

            if ((int) $student->group_id !== (int) $group->id) {
                StudentGroupHistory::query()
                    ->where('student_id', $student->id)
                    ->whereNull('end_date')
                    ->update(['end_date' => now()]);

                StudentGroupHistory::create([
                    'student_id' => $student->id,
                    'group_id' => (int) $group->id,
                    'start_date' => now(),
                    'reason' => 'importacion_alumnos_ciclo_' . $cycle->code,
                ]);
            }

            $student->update([
                'guardian_user_id' => $guardianResult['guardian']?->id ?: $student->guardian_user_id,
                'group_id' => (int) $group->id,
                'phone' => $row['TELEFONO_ALUMNO'] ?: null,
                'address' => $row['DIRECCION'] ?: null,
                'is_active' => $isActive,
            ]);
        }

        return [
            'student_metric' => $studentMetric,
            'guardian_created' => $guardianResult['created'],
            'guardian_linked' => (bool) $guardianResult['guardian'],
            'section_links' => $this->syncSectionAssignmentsForRow($student, $cycleGroup, $row),
        ];
    }

    private function resolveGuardian(array $row, Campus $campus): array
    {
        $name = trim($row['TUTOR_NOMBRE']);
        $email = trim($row['TUTOR_CORREO']);

        if ($name === '' && $email === '') {
            return ['guardian' => null, 'created' => false];
        }

        if ($email !== '') {
            $guardian = User::query()->where('email', $email)->first();
            if ($guardian) {
                $guardian->campuses()->syncWithoutDetaching([(int) $campus->id]);
                if (! $guardian->hasRole('guardian')) {
                    $guardian->assignRole('guardian');
                }

                return ['guardian' => $guardian, 'created' => false];
            }
        } elseif ($name !== '') {
            $generatedEmail = $this->generatedGuardianEmail($name);
            $guardian = User::query()->where('email', $generatedEmail)->first();

            if ($guardian) {
                $guardian->campuses()->syncWithoutDetaching([(int) $campus->id]);
                if (! $guardian->hasRole('guardian')) {
                    $guardian->assignRole('guardian');
                }

                return ['guardian' => $guardian, 'created' => false];
            }
        }

        $resolvedEmail = $email ?: $this->generatedGuardianEmail($name);
        $guardian = User::create([
            'name' => $name ?: $email,
            'email' => $email ?: $this->uniqueEmail($resolvedEmail),
            'password' => Hash::make('123123123'),
            'default_campus_id' => (int) $campus->id,
        ]);
        $guardian->assignRole('guardian');
        $guardian->campuses()->syncWithoutDetaching([(int) $campus->id]);

        return ['guardian' => $guardian, 'created' => true];
    }

    private function syncSectionAssignmentsForRow(Student $student, SchoolCycleGroup $cycleGroup, array $row): int
    {
        $assignmentIds = $this->sectionAssignmentIdsForRow($cycleGroup, $row);

        $linked = 0;
        foreach ($assignmentIds as $assignmentId) {
            DB::table('teaching_assignment_student')->updateOrInsert([
                'teaching_assignment_id' => (int) $assignmentId,
                'student_id' => (int) $student->id,
            ], [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $linked++;
        }

        return $linked;
    }

    private function countSectionAssignmentsForRow(SchoolCycleGroup $cycleGroup, array $row): int
    {
        return $this->sectionAssignmentIdsForRow($cycleGroup, $row)->count();
    }

    private function sectionAssignmentIdsForRow(SchoolCycleGroup $cycleGroup, array $row)
    {
        $queries = [];

        if (($row['INGLES'] ?? '') !== '') {
            $queries[] = TeachingAssignment::query()
                ->where('school_cycle_group_id', (int) $cycleGroup->id)
                ->where('section_type', 'english')
                ->where('section_label', $row['INGLES'])
                ->where('is_active', true);
        }

        if (($row['LAB_TALLER'] ?? '') !== '') {
            $queries[] = TeachingAssignment::query()
                ->where('school_cycle_group_id', (int) $cycleGroup->id)
                ->where('section_type', 'lab_taller')
                ->where('section_label', $row['LAB_TALLER'])
                ->where('is_active', true);
        }

        if (empty($queries) && (int) ($row['SECCION'] ?? 0) > 0) {
            $queries[] = TeachingAssignment::query()
                ->where('school_cycle_group_id', (int) $cycleGroup->id)
                ->where('section_number', (int) $row['SECCION'])
                ->where('is_active', true);
        }

        return collect($queries)
            ->flatMap(fn ($query) => $query->pluck('id'))
            ->unique()
            ->values();
    }

    private function deactivateMissingStudents(Campus $campus, SchoolCycle $cycle, array $seenEnrollments): int
    {
        return Student::query()
            ->whereIn('group_id', $this->cycleGroupIds($campus, $cycle))
            ->whereNotIn('enrollment_number', $seenEnrollments ?: ['__none__'])
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    private function countMissingStudents(Campus $campus, SchoolCycle $cycle, array $seenEnrollments): int
    {
        return Student::query()
            ->whereIn('group_id', $this->cycleGroupIds($campus, $cycle))
            ->whereNotIn('enrollment_number', $seenEnrollments ?: ['__none__'])
            ->where('is_active', true)
            ->count();
    }

    private function cycleGroupIds(Campus $campus, SchoolCycle $cycle): array
    {
        return SchoolCycleGroup::query()
            ->where('school_cycle_id', (int) $cycle->id)
            ->where('campus_id', (int) $campus->id)
            ->where('is_active', true)
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function resolveStudentEmail(array $row): string
    {
        if ($row['CORREO_ALUMNO'] !== '' && ! User::query()->where('email', $row['CORREO_ALUMNO'])->exists()) {
            return $row['CORREO_ALUMNO'];
        }

        return $this->uniqueEmail(mb_strtolower($row['MATRICULA']) . '@my.ula.edu.mx');
    }

    private function generatedGuardianEmail(string $name): string
    {
        $base = Str::slug($name ?: 'tutor') ?: 'tutor';

        return $base . '@tutores.local';
    }

    private function uniqueEmail(string $email): string
    {
        if (! User::query()->where('email', $email)->exists()) {
            return $email;
        }

        [$prefix, $domain] = explode('@', $email, 2);
        $counter = 2;

        do {
            $candidate = "{$prefix}-{$counter}@{$domain}";
            $counter++;
        } while (User::query()->where('email', $candidate)->exists());

        return $candidate;
    }

    private function isBlankStudentRow(array $row): bool
    {
        foreach (['MATRICULA', 'NOMBRE', 'APELLIDO_PATERNO', 'APELLIDO_MATERNO', 'CORREO_ALUMNO'] as $field) {
            if ($row[$field] !== '') {
                return false;
            }
        }

        return true;
    }

    private function fullStudentName(array $row): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
            $row['NOMBRE'],
            $row['APELLIDO_PATERNO'],
            $row['APELLIDO_MATERNO'],
        ]))) ?: $row['MATRICULA']);
    }

    private function matchesCycle(string $value, SchoolCycle $cycle): bool
    {
        $normalized = $this->normalizeComparable($value);

        return in_array($normalized, [
            $this->normalizeComparable((string) $cycle->name),
            $this->normalizeComparable((string) $cycle->code),
            $this->normalizeComparable($cycle->code . ' - ' . $cycle->name),
        ], true);
    }

    private function groupLookupKey(string $level, string $group): string
    {
        return $this->normalizeComparable($level) . '|' . $this->normalizeComparable($group);
    }

    private function normalizeHeader(string $value): string
    {
        return mb_strtoupper(trim($value));
    }

    private function normalizeStatus(string $value): string
    {
        $value = mb_strtoupper(trim($value));

        return in_array($value, ['INACTIVO', 'BAJA'], true) ? 'INACTIVO' : 'ACTIVO';
    }

    private function normalizeEnglishSection(string $value): string
    {
        $normalized = $this->normalizeComparable($value);

        if ($normalized === '') {
            return '';
        }

        if (in_array($normalized, ['basico', 'basic', '1', 'a'], true)) {
            return 'BASICO';
        }

        if (in_array($normalized, ['avanzado', 'advanced', '2', 'b'], true)) {
            return 'AVANZADO';
        }

        return mb_strtoupper(trim($value));
    }

    private function normalizeLabSection(string $value): string
    {
        $normalized = $this->normalizeComparable($value);

        if ($normalized === '') {
            return '';
        }

        if (in_array($normalized, ['a', '1'], true)) {
            return 'A';
        }

        if (in_array($normalized, ['b', '2'], true)) {
            return 'B';
        }

        if (in_array($normalized, ['c', '3'], true)) {
            return 'C';
        }

        return mb_strtoupper(trim($value));
    }

    private function normalizeComparable(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value), 'UTF-8'));
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        return strtr($value, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
        ]);
    }
}
