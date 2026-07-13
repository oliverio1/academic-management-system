<?php

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CycleStudentsTemplateController extends Controller
{
    private const STUDENT_ROWS_PER_GROUP = 35;

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
        $headers = [
            'CAMPUS',
            'CICLO',
            'GRADO',
            'GRUPO',
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

        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeader($sheet, 'A1:R1');

        $row = 2;
        foreach ($cycleGroups as $cycleGroup) {
            $group = $cycleGroup->group;
            $level = optional($group?->level)->name ?: '';
            $sectionCount = max(1, (int) ($cycleGroup->section_count ?? 1));

            for ($index = 0; $index < self::STUDENT_ROWS_PER_GROUP; $index++) {
                $sheet->fromArray([
                    $campus->code,
                    $cycle->name,
                    $level,
                    $group?->name,
                    $sectionCount > 1 ? '' : '1',
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
        $sheet->setAutoFilter("A1:R{$lastRow}");
        $sheet->getStyle("A1:R{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle("A2:R{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        foreach (range('A', 'R') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $this->applyListValidation($sheet, "E2:E{$lastRow}", 'CATALOGOS!$F$2:$F$4');
        $this->applyListValidation($sheet, "L2:L{$lastRow}", 'CATALOGOS!$D$2:$D$3');
    }

    private function buildCatalogSheet(Worksheet $sheet, Campus $campus, SchoolCycle $cycle, $cycleGroups): void
    {
        $sheet->fromArray([
            ['CAMPUS', 'CICLO', 'GRUPOS_DEL_CICLO', 'ESTATUS', 'PARENTESCOS', 'SECCIONES'],
            [$campus->code . ' - ' . $campus->name, $cycle->code . ' - ' . $cycle->name, '', 'ACTIVO', 'MADRE', '1'],
            ['', '', '', 'INACTIVO', 'PADRE', '2'],
            ['', '', '', '', 'TUTOR', '3'],
            ['', '', '', '', 'ABUELA/O', ''],
            ['', '', '', '', 'OTRO', ''],
        ], null, 'A1');

        $row = 2;
        foreach ($cycleGroups as $cycleGroup) {
            $group = $cycleGroup->group;
            $level = optional($group?->level)->name ?: 'Sin grado';
            $sheet->setCellValue("C{$row}", trim($level . ' ' . ($group?->name ?? '')));
            $row++;
        }

        $lastRow = max(6, $row - 1);
        $this->styleHeader($sheet, 'A1:F1');
        $sheet->getStyle("A1:F{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);

        foreach (range('A', 'F') as $column) {
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
            ['SECCION se usa cuando el grupo divide materias por seccion; si no aplica, dejala en 1.'],
            ['Los datos del tutor quedan listos para una importacion posterior y asignacion familiar.'],
            ['No elimines hojas ni columnas. Puedes agregar filas copiando una fila del mismo grupo.'],
            ['Ejemplo de fila ALUMNOS: FLORIDA | PREPARATORIA 25-26 FLORIDA | Cuarto | 4001 | 1 | U99826677 | OLIVER | MARTINEZ | ANAYA | oliverio.oo@gmail.com | 5569177811 | ACTIVO | DIONICIO MARTINEZ PINEDA | PADRE | marpindi@gmail.com | 5568177811 | Av. Mexico 410 E-102 |'],
        ];

        $sheet->fromArray($rows, null, 'A1');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1:F8')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
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
}
