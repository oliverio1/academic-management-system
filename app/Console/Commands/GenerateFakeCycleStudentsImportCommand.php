<?php

namespace App\Console\Commands;

use App\Models\Campus;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\TeachingAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateFakeCycleStudentsImportCommand extends Command
{
    protected $signature = 'students:fake-cycle-import
        {--cycle= : ID, codigo o nombre del ciclo}
        {--campus=FLORIDA : ID, codigo o nombre del campus}
        {--per-group=25 : Cantidad de alumnos por grupo}
        {--output= : Ruta del archivo xlsx de salida}';

    protected $description = 'Genera un Excel de alumnos ficticios para importar a un ciclo escolar.';

    private const HEADERS = [
        'CAMPUS',
        'CICLO',
        'GRADO',
        'GRUPO',
        'SECCION INGLES',
        'SECCION LAB',
        'MATRICULA',
        'NOMBRE',
        'APELLIDO PATERNO',
        'APELLIDO MATERNO',
        'CORREO ALUMNO',
        'TELEFONO ALUMNO',
        'ESTATUS',
        'TUTOR NOMBRE',
        'TUTOR PARENTESCO',
        'TUTOR CORREO',
        'TUTOR TELEFONO',
        'DIRECCION',
        'OBSERVACIONES',
    ];

    private array $names = [
        'ANDREA', 'DIEGO', 'SOFIA', 'MATEO', 'VALERIA', 'SANTIAGO', 'CAMILA', 'EMILIANO', 'REGINA', 'LEONARDO',
        'PAULA', 'SEBASTIAN', 'MARIANA', 'NICOLAS', 'FERNANDA', 'ALEXIS', 'XIMENA', 'RODRIGO', 'DANIELA', 'IKER',
        'JIMENA', 'MAURICIO', 'RENATA', 'PABLO', 'VICTORIA', 'GAEL', 'NATALIA', 'BRUNO', 'PAOLA', 'ADRIAN',
    ];

    private array $lastNames = [
        'GARCIA', 'HERNANDEZ', 'MARTINEZ', 'LOPEZ', 'GONZALEZ', 'PEREZ', 'RODRIGUEZ', 'SANCHEZ', 'RAMIREZ', 'CRUZ',
        'FLORES', 'GOMEZ', 'MORALES', 'VARGAS', 'CASTILLO', 'ORTIZ', 'REYES', 'JIMENEZ', 'TORRES', 'RIVERA',
        'AGUILAR', 'MENDOZA', 'ROMERO', 'RAMOS', 'SILVA', 'CHAVEZ', 'DELGADO', 'MEDINA', 'SOTO', 'NAVARRO',
    ];

    private array $streets = [
        'AV. MEXICO', 'CALLE VERACRUZ', 'AV. UNIVERSIDAD', 'CALLE MORELOS', 'PRIVADA NARANJOS',
        'CALLE HIDALGO', 'AV. INSURGENTES', 'CALLE DURANGO', 'CERRADA DEL BOSQUE', 'AV. REVOLUCION',
    ];

    public function handle(): int
    {
        $campus = $this->resolveCampus((string) $this->option('campus'));
        $cycle = $this->resolveCycle((string) $this->option('cycle'), $campus);
        $perGroup = max(1, (int) $this->option('per-group'));

        $cycleGroups = SchoolCycleGroup::query()
            ->with(['group.level'])
            ->where('school_cycle_id', $cycle->id)
            ->where('campus_id', $campus->id)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (SchoolCycleGroup $cycleGroup) => sprintf(
                '%s %s',
                (string) optional($cycleGroup->group?->level)->name,
                (string) $cycleGroup->group?->name
            ))
            ->values();

        if ($cycleGroups->isEmpty()) {
            $this->error('El ciclo/campus no tiene grupos activos.');

            return self::FAILURE;
        }

        $spreadsheet = $this->spreadsheet($campus, $cycle, $cycleGroups, $perGroup);
        $output = $this->outputPath($cycle, $campus);

        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0775, true);
        }

        (new Xlsx($spreadsheet))->save($output);
        $spreadsheet->disconnectWorksheets();

        $this->info('Archivo generado: ' . $output);
        $this->line('Grupos: ' . $cycleGroups->count());
        $this->line('Alumnos: ' . ($cycleGroups->count() * $perGroup));

        return self::SUCCESS;
    }

    private function resolveCampus(string $value): Campus
    {
        return Campus::query()
            ->where('id', (int) $value)
            ->orWhere('code', $value)
            ->orWhere('name', $value)
            ->firstOrFail();
    }

    private function resolveCycle(string $value, Campus $campus): SchoolCycle
    {
        $query = SchoolCycle::query()
            ->where(function ($nested) use ($campus) {
                $nested->where('campus_id', $campus->id)
                    ->orWhereHas('campuses', fn ($campuses) => $campuses->whereKey($campus->id));
            });

        if ($value !== '') {
            $query->where(function ($nested) use ($value) {
                $nested->where('id', (int) $value)
                    ->orWhere('code', $value)
                    ->orWhere('name', $value);
            });
        }

        return $query->orderByDesc('start_date')->firstOrFail();
    }

    private function spreadsheet(Campus $campus, SchoolCycle $cycle, $cycleGroups, int $perGroup): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator(config('app.name'))
            ->setTitle('Alumnos ficticios para ciclo')
            ->setSubject($cycle->name);

        $studentsSheet = $spreadsheet->getActiveSheet();
        $studentsSheet->setTitle('ALUMNOS');
        $studentsSheet->fromArray(self::HEADERS, null, 'A1');
        $this->styleHeader($studentsSheet, 'A1:S1');

        $row = 2;
        $globalIndex = 1;
        foreach ($cycleGroups as $cycleGroup) {
            $group = $cycleGroup->group;
            $levelName = (string) optional($group?->level)->name;
            $groupName = (string) $group?->name;
            $englishSections = $this->sectionsFor($cycleGroup, 'english', ['BASICO', 'AVANZADO']);
            $labSections = $this->sectionsFor($cycleGroup, 'lab_taller', ['A', 'B']);

            for ($index = 1; $index <= $perGroup; $index++) {
                $firstName = $this->names[($globalIndex + $index) % count($this->names)];
                $paternal = $this->lastNames[($globalIndex + ($index * 2)) % count($this->lastNames)];
                $maternal = $this->lastNames[($globalIndex + ($index * 3) + 5) % count($this->lastNames)];
                $guardianFirst = $this->names[($globalIndex + ($index * 4) + 7) % count($this->names)];
                $guardianPaternal = $this->lastNames[($globalIndex + ($index * 5) + 3) % count($this->lastNames)];
                $guardianMaternal = $this->lastNames[($globalIndex + ($index * 6) + 9) % count($this->lastNames)];
                $enrollment = sprintf('U26%s%02d', preg_replace('/\D+/', '', $groupName) ?: $globalIndex, $index);
                $studentSlug = Str::slug("{$firstName}.{$paternal}.{$maternal}.{$enrollment}");
                $guardianSlug = Str::slug("{$guardianFirst}.{$guardianPaternal}.{$guardianMaternal}.{$enrollment}");

                $studentsSheet->fromArray([
                    $campus->code,
                    $cycle->name,
                    $levelName,
                    $groupName,
                    $englishSections[($index - 1) % count($englishSections)],
                    $labSections[($index - 1) % count($labSections)],
                    $enrollment,
                    $firstName,
                    $paternal,
                    $maternal,
                    $studentSlug . '@alumnos.local',
                    $this->phone(55, $globalIndex, $index),
                    'ACTIVO',
                    trim("{$guardianFirst} {$guardianPaternal} {$guardianMaternal}"),
                    $index % 3 === 0 ? 'TUTOR' : ($index % 2 === 0 ? 'PADRE' : 'MADRE'),
                    $guardianSlug . '@tutores.local',
                    $this->phone(56, $globalIndex, $index),
                    $this->address($globalIndex, $index),
                    'Alumno ficticio para pruebas de ciclo.',
                ], null, "A{$row}");

                $row++;
                $globalIndex++;
            }
        }

        $lastRow = max(2, $row - 1);
        $studentsSheet->freezePane('A2');
        $studentsSheet->setAutoFilter("A1:S{$lastRow}");
        $studentsSheet->getStyle("A1:S{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $studentsSheet->getStyle("A2:S{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        foreach (range('A', 'S') as $column) {
            $studentsSheet->getColumnDimension($column)->setAutoSize(true);
        }

        $catalogSheet = $spreadsheet->createSheet();
        $catalogSheet->setTitle('CATALOGOS');
        $catalogSheet->fromArray([
            ['CAMPUS', 'CICLO', 'GRUPOS DEL CICLO', 'ESTATUS', 'PARENTESCOS', 'SECCION INGLES', 'SECCION LAB'],
            [$campus->code . ' - ' . $campus->name, $cycle->code . ' - ' . $cycle->name, '', 'ACTIVO', 'MADRE', 'BASICO', 'A'],
            ['', '', '', 'INACTIVO', 'PADRE', 'AVANZADO', 'B'],
            ['', '', '', '', 'TUTOR', '', 'C'],
            ['', '', '', '', 'ABUELA/O', '', ''],
            ['', '', '', '', 'OTRO', '', ''],
        ], null, 'A1');

        $catalogRow = 2;
        foreach ($cycleGroups as $cycleGroup) {
            $catalogSheet->setCellValue(
                "C{$catalogRow}",
                trim(optional($cycleGroup->group?->level)->name . ' ' . $cycleGroup->group?->name)
            );
            $catalogRow++;
        }
        $this->styleHeader($catalogSheet, 'A1:G1');
        foreach (range('A', 'G') as $column) {
            $catalogSheet->getColumnDimension($column)->setAutoSize(true);
        }

        $this->applyListValidation($studentsSheet, "E2:E{$lastRow}", 'CATALOGOS!$F$2:$F$3');
        $this->applyListValidation($studentsSheet, "F2:F{$lastRow}", 'CATALOGOS!$G$2:$G$4');
        $this->applyListValidation($studentsSheet, "M2:M{$lastRow}", 'CATALOGOS!$D$2:$D$3');

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function sectionsFor(SchoolCycleGroup $cycleGroup, string $type, array $fallback): array
    {
        $sections = TeachingAssignment::query()
            ->where('school_cycle_group_id', $cycleGroup->id)
            ->where('section_type', $type)
            ->where('is_active', true)
            ->whereNotNull('section_label')
            ->distinct()
            ->orderBy('section_label')
            ->pluck('section_label')
            ->filter()
            ->values()
            ->all();

        return $sections ?: $fallback;
    }

    private function phone(int $prefix, int $globalIndex, int $index): string
    {
        return sprintf('%02d%08d', $prefix, (($globalIndex * 97) + ($index * 31)) % 100000000);
    }

    private function address(int $globalIndex, int $index): string
    {
        $street = $this->streets[($globalIndex + $index) % count($this->streets)];

        return sprintf('%s %d, COL. FLORIDA, ALCALDIA ALVARO OBREGON', $street, 100 + (($globalIndex * 7 + $index) % 900));
    }

    private function outputPath(SchoolCycle $cycle, Campus $campus): string
    {
        $output = (string) $this->option('output');
        if ($output !== '') {
            return $output;
        }

        return public_path(sprintf(
            'downloads/alumnos-prueba-%s-%s.xlsx',
            Str::slug($campus->code),
            Str::slug($cycle->code ?: $cycle->name)
        ));
    }

    private function styleHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    private function applyListValidation($sheet, string $range, string $formula): void
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
}
