<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\Modality;
use App\Models\Subject;
use App\Services\Imports\TemarioImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TemarioImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_olicati_bachillerato_format_with_unit_hours(): void
    {
        $subject = $this->subject('QUÍMICA I');
        $file = $this->workbookUpload([
            ['Nombre de la materia', 'Química I', null],
            ['Creditos', '10', null],
            [null, null, null],
            ['Objetivo general', 'Comprender la química como ciencia experimental.', null],
            ['1. Química como herramienta de vida', 'Argumenta la importancia de la Química.', '5'],
            ['1.1. Concepto de Química', null, null],
            ['1.1.1. La Química y su relación con otras Ciencias', null, null],
        ]);

        $result = app(TemarioImportService::class)->import($file, $subject);

        $this->assertFalse($result->hasErrors(), implode("\n", $result->errors));
        $temario = $subject->temarios()->with('points')->firstOrFail();

        $this->assertSame('Química I', $temario->title);
        $this->assertStringContainsString('Objetivo general: Comprender la química como ciencia experimental.', $temario->description);
        $this->assertStringContainsString('Creditos: 10', $temario->description);

        $unit = $temario->points->firstWhere('label', '1.');
        $this->assertNotNull($unit);
        $this->assertSame(1, (int) $unit->level);
        $this->assertSame('conceptual', $unit->type);
        $this->assertSame(5.0, (float) $unit->hours);
        $this->assertStringContainsString('Objetivo especifico: Argumenta la importancia de la Química.', $unit->content);
    }

    public function test_imports_content_type_from_next_column_for_preparatoria_format(): void
    {
        $subject = $this->subject('FÍSICA I');
        $file = $this->workbookUpload([
            ['Física I', 'Comprender fenómenos físicos.', null],
            ['Creditos', '8', null],
            [null, null, null],
            ['1. Cinemática', 'Analizar el movimiento.', 'conceptual'],
            ['1.1. Movimiento rectilíneo uniforme', null, 'procedimental'],
            ['1.1.1. Gráficas posición-tiempo', null, 'actitudinal'],
        ]);

        $result = app(TemarioImportService::class)->import($file, $subject);

        $this->assertFalse($result->hasErrors(), implode("\n", $result->errors));
        $temario = $subject->temarios()->with('points')->firstOrFail();

        $this->assertSame('Física I', $temario->title);
        $this->assertSame('conceptual', $temario->points->firstWhere('label', '1.')->type);
        $this->assertSame('procedimental', $temario->points->firstWhere('label', '1.1.')->type);
        $this->assertSame('actitudinal', $temario->points->firstWhere('label', '1.1.1.')->type);
    }

    public function test_imports_preparatoria_olicati_metadata_hours_types_and_lettered_labels(): void
    {
        $subject = $this->subject('QUÍMICA III');
        $file = $this->workbookUpload([
            ['Nombre de la materia', 'Química III', null, null],
            ['Creditos', '14', null, null],
            ['Clave', '1501', null, null],
            ['Tipo', 'Teórico/Práctico', null, null],
            ['Horas por semana', '4', null, null],
            ['Horas al año', '120', null, null],
            [null, null, null, null],
            ['Objetivo general', 'Aplicar conocimientos químicos a problemáticas actuales.', null, null],
            ['1. Elementos químicos en los dispositivos móviles', 'Explicar propiedades físicas y químicas.', '40', null],
            ['1.1. Minerales y dispositivos móviles', null, null, 'Conceptual'],
            ['1.1.a. Obsolescencia programada', null, null, 'Conceptual'],
            ['1.4. Búsqueda y análisis de textos de divulgación científica', null, null, 'Procedimental'],
            ['1.9. Valoración del conocimiento químico', null, null, 'Actitudinal'],
        ]);

        $result = app(TemarioImportService::class)->import($file, $subject);

        $this->assertFalse($result->hasErrors(), implode("\n", $result->errors));

        $subject->refresh();
        $this->assertSame('1501', $subject->subject_key);
        $this->assertSame(Subject::TYPE_THEORETICAL_PRACTICAL, $subject->type);
        $this->assertSame(4, (int) $subject->hours_per_week);
        $this->assertSame(120, (int) $subject->annual_hours);

        $temario = $subject->temarios()->with('points')->firstOrFail();
        $this->assertStringContainsString('Clave: 1501', $temario->description);
        $this->assertStringContainsString('Tipo: Teórico/Práctico', $temario->description);
        $this->assertSame(40.0, (float) $temario->points->firstWhere('label', '1.')->hours);
        $this->assertSame('conceptual', $temario->points->firstWhere('label', '1.1.')->type);
        $this->assertSame(3, (int) $temario->points->firstWhere('label', '1.1.a.')->level);
        $this->assertSame('procedimental', $temario->points->firstWhere('label', '1.4.')->type);
        $this->assertSame('actitudinal', $temario->points->firstWhere('label', '1.9.')->type);
    }

    private function subject(string $name): Subject
    {
        $modality = Modality::create([
            'name' => 'BACHILLERATO',
            'is_active' => true,
        ]);
        $level = Level::create([
            'modality_id' => $modality->id,
            'name' => 'PRIMERO',
            'is_active' => true,
        ]);

        return Subject::create([
            'level_id' => $level->id,
            'name' => $name,
            'hours_per_week' => 5,
            'type' => Subject::TYPE_THEORETICAL,
            'is_active' => true,
        ]);
    }

    private function workbookUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('temario');

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                if ($value !== null) {
                    $sheet->setCellValueByColumnAndRow($columnIndex + 1, $rowIndex + 1, $value);
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'temario-import-') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'temario.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
