<?php

namespace Tests\Feature;

use App\Models\AcademicCalendarDay;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Campus;
use App\Models\CyclePartial;
use App\Models\DidacticPlan;
use App\Models\EvaluationCriterion;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\Schedule;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Temario;
use App\Models\TemarioPoint;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeacherDidacticPlanTemplateTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId = 'florida';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-20 08:00:00'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['coordinator', 'teacher', 'student', 'prefect', 'guardian', 'tutor', 'admin'] as $role) {
            Role::findOrCreate($role);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_teacher_downloads_planning_template_with_temario_and_calendarized_theory_sessions(): void
    {
        $scenario = $this->planningScenario();

        $response = $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.didactic-plans.template', $scenario['assignment']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $spreadsheet = $this->spreadsheetFromResponse($response);

        $this->assertSame([
            'Institucion',
            'Materia',
            'Ciclo parciales',
            'Evaluacion',
            'Planeacion',
            'Catalogos',
        ], $spreadsheet->getSheetNames());

        $institution = $spreadsheet->getSheetByName('Institucion');
        $subject = $spreadsheet->getSheetByName('Materia');
        $planning = $spreadsheet->getSheetByName('Planeacion');
        $catalog = $spreadsheet->getSheetByName('Catalogos');

        $this->assertSame('Preparatoria 2026-2027', $institution->getCell('B4')->getValue());
        $this->assertSame($scenario['teacherUser']->name, $institution->getCell('B5')->getValue());
        $this->assertSame('5005', $institution->getCell('B11')->getFormattedValue());

        $this->assertSame('Quimica III', $subject->getCell('B2')->getValue());
        $this->assertSame(Subject::TYPE_THEORETICAL_PRACTICAL, $subject->getCell('B3')->getValue());
        $this->assertSame('1501', $subject->getCell('B5')->getFormattedValue());
        $this->assertSame(120, (int) $subject->getCell('B6')->getValue());

        $this->assertSame('tema_id', $planning->getCell('A1')->getValue());
        $this->assertSame('unidad_id', $planning->getCell('B1')->getValue());
        $this->assertFalse($planning->getColumnDimension('A')->getVisible());
        $this->assertFalse($planning->getColumnDimension('B')->getVisible());

        $this->assertSame('2026-08-03', $planning->getCell('E2')->getFormattedValue());
        $this->assertSame('2026-08-06', $planning->getCell('E3')->getFormattedValue());
        $this->assertSame('', trim((string) $planning->getCell('E4')->getFormattedValue()));
        $this->assertSame('1.1.1', $planning->getCell('F2')->getValue());
        $this->assertSame('1.1.2', $planning->getCell('F3')->getValue());

        $this->assertSame('1 Materia y energia', $catalog->getCell('C2')->getValue());
        $this->assertSame('1.1 Estructura de la materia', $catalog->getCell('D2')->getValue());
        $this->assertSame('1.1.1 Propiedades de la materia', $catalog->getCell('E2')->getValue());
        $this->assertSame('1.1.2 Cambios de estado', $catalog->getCell('E3')->getValue());
    }

    public function test_teacher_cannot_download_template_for_another_teacher_assignment(): void
    {
        $scenario = $this->planningScenario();

        $this->actingAs($scenario['otherTeacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.didactic-plans.template', $scenario['assignment']))
            ->assertForbidden();
    }

    public function test_teacher_downloads_template_prefilled_from_tentative_plan(): void
    {
        $scenario = $this->planningScenario();
        $tentative = $this->tentativePlan($scenario);

        $response = $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.didactic-plans.template', $scenario['assignment']));

        $response->assertOk();
        $spreadsheet = $this->spreadsheetFromResponse($response);
        $subject = $spreadsheet->getSheetByName('Materia');
        $planning = $spreadsheet->getSheetByName('Planeacion');

        $this->assertSame('Programa indicativo de prueba.', $subject->getCell('B12')->getValue());
        $this->assertSame('2026-08-03', $planning->getCell('E2')->getFormattedValue());
        $this->assertSame('1.1.1; 1.1.2', $planning->getCell('F2')->getValue());
        $this->assertStringContainsString('Estrategia tentativa de desarrollo.', $planning->getCell('G2')->getValue());
        $this->assertSame($tentative->id, DidacticPlan::query()->where('status', DidacticPlan::STATUS_TENTATIVE)->value('id'));
    }

    public function test_teacher_imports_planning_template_and_creates_plan_items(): void
    {
        $scenario = $this->planningScenario();
        $path = $this->filledPlanningTemplatePath($scenario);

        try {
            $this->actingAs($scenario['teacherUser'])
                ->withSession(['active_campus_id' => $scenario['campus']->id])
                ->post(route('teacher.didactic-plans.import.store', $scenario['assignment']), [
                    'planning_file' => new UploadedFile(
                        $path,
                        'planeacion-quimica-5005.xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        null,
                        true
                    ),
                ])
                ->assertRedirect(route('teacher.didactic-plans.plans', $scenario['assignment']))
                ->assertSessionHas('success');
        } finally {
            @unlink($path);
        }

        $plan = DidacticPlan::query()
            ->where('teaching_assignment_id', $scenario['assignment']->id)
            ->firstOrFail();

        $this->assertSame('Planeacion Quimica III - Grupo 5005', $plan->title);
        $this->assertSame($scenario['cycle']->id, (int) $plan->school_cycle_id);
        $this->assertSame($scenario['period']->id, (int) $plan->academic_period_id);
        $this->assertSame('1501', $plan->subject_key);
        $this->assertSame(120, (int) $plan->total_annual_hours);
        $this->assertSame('Objetivo general capturado por el profesor.', $plan->objective);
        $this->assertSame('Libro de texto, proyector, cuaderno', $plan->general_resources);
        $this->assertSame('Bibliografia de prueba.', $plan->bibliography);

        $items = $plan->items()->orderBy('position')->get();
        $this->assertCount(2, $items);

        $this->assertSame(1, (int) $items[0]->position);
        $this->assertSame($scenario['unit']->id, (int) $items[0]->field_training_point_id);
        $this->assertSame($scenario['topic']->id, (int) $items[0]->temario_point_id);
        $this->assertSame([$scenario['subtopicA']->id], $items[0]->temario_subtopic_ids);
        $this->assertSame('Actividad de apertura con diagnostico.', $items[0]->development);
        $this->assertSame('2026-08-03', $items[0]->start_date->toDateString());

        $this->assertSame([$scenario['subtopicB']->id], $items[1]->temario_subtopic_ids);
        $this->assertSame('Actividad guiada con ejemplos.', $items[1]->development);
        $this->assertSame('2026-08-06', $items[1]->start_date->toDateString());
    }

    public function test_importing_prefilled_tentative_template_replaces_tentative_with_final_plan(): void
    {
        $scenario = $this->planningScenario();
        $this->tentativePlan($scenario);

        $path = $this->filledPlanningTemplatePath($scenario);

        try {
            $this->actingAs($scenario['teacherUser'])
                ->withSession(['active_campus_id' => $scenario['campus']->id])
                ->post(route('teacher.didactic-plans.import.store', $scenario['assignment']), [
                    'planning_file' => new UploadedFile(
                        $path,
                        'planeacion-quimica-5005.xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        null,
                        true
                    ),
                ])
                ->assertRedirect(route('teacher.didactic-plans.plans', $scenario['assignment']));
        } finally {
            @unlink($path);
        }

        $this->assertSame(0, DidacticPlan::query()->where('status', DidacticPlan::STATUS_TENTATIVE)->count());
        $finalPlan = DidacticPlan::query()
            ->where('teaching_assignment_id', $scenario['assignment']->id)
            ->where('status', DidacticPlan::STATUS_FINAL)
            ->firstOrFail();

        $this->assertFalse((bool) $finalPlan->generated_by_system);
        $this->assertNull($finalPlan->generated_at);
        $this->assertCount(2, $finalPlan->items);
        $this->assertSame('Actividad de apertura con diagnostico.', $finalPlan->items->first()->development);
    }

    public function test_teacher_confirms_tentative_plan_as_final(): void
    {
        $scenario = $this->planningScenario();
        $plan = $this->tentativePlan($scenario);
        $this->sessionsAndCriterionForPlanConfirmation($scenario);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->patch(route('teacher.didactic-plans.confirm-final', $plan))
            ->assertRedirect(route('teacher.didactic-plans.plans', $scenario['assignment']))
            ->assertSessionHas('success', 'Planeacion confirmada como final. Actividades generadas: 2. Actividades evaluables: 2.');

        $plan->refresh();

        $this->assertSame(DidacticPlan::STATUS_FINAL, $plan->status);
        $this->assertFalse((bool) $plan->generated_by_system);
        $this->assertNull($plan->generated_at);
        $this->assertStringContainsString('Confirmada como final por el docente', $plan->notes);
        $this->assertSame(2, $plan->items()->count());
        $this->assertSame(2, \App\Models\SessionActivity::query()->count());
        $this->assertSame(2, \App\Models\Activity::query()->count());
    }

    public function test_teacher_cannot_confirm_another_teacher_tentative_plan(): void
    {
        $scenario = $this->planningScenario();
        $plan = $this->tentativePlan($scenario);

        $this->actingAs($scenario['otherTeacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->patch(route('teacher.didactic-plans.confirm-final', $plan))
            ->assertForbidden();

        $this->assertSame(DidacticPlan::STATUS_TENTATIVE, $plan->refresh()->status);
    }

    public function test_import_can_replace_existing_plan_for_same_cycle(): void
    {
        $scenario = $this->planningScenario();
        DidacticPlan::create([
            'teaching_assignment_id' => $scenario['assignment']->id,
            'school_cycle_id' => $scenario['cycle']->id,
            'academic_period_id' => $scenario['period']->id,
            'title' => 'Planeacion anterior',
            'is_active' => true,
        ]);

        $path = $this->filledPlanningTemplatePath($scenario);

        try {
            $this->actingAs($scenario['teacherUser'])
                ->withSession(['active_campus_id' => $scenario['campus']->id])
                ->post(route('teacher.didactic-plans.import.store', $scenario['assignment']), [
                    'replace_existing' => 1,
                    'planning_file' => new UploadedFile(
                        $path,
                        'planeacion-quimica-5005.xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        null,
                        true
                    ),
                ])
                ->assertRedirect(route('teacher.didactic-plans.plans', $scenario['assignment']));
        } finally {
            @unlink($path);
        }

        $this->assertDatabaseMissing('didactic_plans', [
            'teaching_assignment_id' => $scenario['assignment']->id,
            'title' => 'Planeacion anterior',
        ]);
        $this->assertSame(1, DidacticPlan::query()->where('teaching_assignment_id', $scenario['assignment']->id)->count());
    }

    public function test_teacher_cannot_import_planning_for_another_teacher_assignment(): void
    {
        $scenario = $this->planningScenario();
        $path = $this->filledPlanningTemplatePath($scenario);

        try {
            $this->actingAs($scenario['otherTeacherUser'])
                ->withSession(['active_campus_id' => $scenario['campus']->id])
                ->post(route('teacher.didactic-plans.import.store', $scenario['assignment']), [
                    'planning_file' => new UploadedFile(
                        $path,
                        'planeacion-quimica-5005.xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        null,
                        true
                    ),
                ])
                ->assertForbidden();
        } finally {
            @unlink($path);
        }

        $this->assertSame(0, DidacticPlan::query()->count());
    }

    public function test_teacher_downloads_dgire_pdf_for_imported_plan(): void
    {
        $this->skipIfWkhtmltopdfIsMissing();

        $scenario = $this->planningScenario();
        $plan = $this->importFilledPlan($scenario);

        $response = $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.didactic-plans.pdf', $plan));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('inline; filename="PLANEACION_5005_Quimica_III.pdf"', $response->headers->get('content-disposition'));
    }

    public function test_teacher_cannot_download_dgire_pdf_for_another_teacher_plan(): void
    {
        $scenario = $this->planningScenario();
        $plan = $this->importFilledPlan($scenario);

        $this->actingAs($scenario['otherTeacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.didactic-plans.pdf', $plan))
            ->assertForbidden();
    }

    public function test_teacher_clones_plan_to_peer_groups_and_recalendarizes_sessions(): void
    {
        $scenario = $this->planningScenario();
        $targetAssignment = $this->peerAssignment($scenario, '5004', [
            ['martes', '07:00:00', '07:50:00', 'theory'],
            ['miercoles', '07:00:00', '07:50:00', 'lab'],
            ['viernes', '07:00:00', '07:50:00', 'theory'],
        ]);
        $plan = $this->importFilledPlan($scenario);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('teacher.didactic-plans.clone-to-peer-groups', $plan))
            ->assertRedirect(route('teacher.didactic-plans.plans', $scenario['assignment']))
            ->assertSessionHas('success', 'Planeaciones generadas: 1.');

        $clonedPlan = DidacticPlan::query()
            ->where('teaching_assignment_id', $targetAssignment->id)
            ->firstOrFail();

        $this->assertSame('Planeacion Quimica III - Grupo 5004', $clonedPlan->title);
        $this->assertSame($scenario['cycle']->id, (int) $clonedPlan->school_cycle_id);
        $this->assertSame('1501', $clonedPlan->subject_key);
        $this->assertSame('Objetivo general capturado por el profesor.', $clonedPlan->objective);

        $items = $clonedPlan->items()->orderBy('position')->get();
        $this->assertCount(2, $items);
        $this->assertSame('2026-08-04', $items[0]->start_date->toDateString());
        $this->assertSame('2026-08-07', $items[1]->start_date->toDateString());
        $this->assertSame('Actividad de apertura con diagnostico.', $items[0]->development);
        $this->assertSame('Actividad guiada con ejemplos.', $items[1]->development);
    }

    public function test_clone_skips_peer_group_with_existing_plan_unless_replace_is_enabled(): void
    {
        $scenario = $this->planningScenario();
        $targetAssignment = $this->peerAssignment($scenario, '5004', [
            ['martes', '07:00:00', '07:50:00', 'theory'],
            ['viernes', '07:00:00', '07:50:00', 'theory'],
        ]);
        $plan = $this->importFilledPlan($scenario);

        DidacticPlan::create([
            'teaching_assignment_id' => $targetAssignment->id,
            'school_cycle_id' => $scenario['cycle']->id,
            'academic_period_id' => $scenario['period']->id,
            'title' => 'Planeacion existente',
            'is_active' => true,
        ]);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('teacher.didactic-plans.clone-to-peer-groups', $plan))
            ->assertRedirect(route('teacher.didactic-plans.plans', $scenario['assignment']))
            ->assertSessionHas('success', 'Planeaciones generadas: 0. Omitidas: 5004 ya tenia planeacion.');

        $this->assertDatabaseHas('didactic_plans', [
            'teaching_assignment_id' => $targetAssignment->id,
            'title' => 'Planeacion existente',
        ]);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('teacher.didactic-plans.clone-to-peer-groups', $plan), [
                'replace_existing' => 1,
            ])
            ->assertRedirect(route('teacher.didactic-plans.plans', $scenario['assignment']))
            ->assertSessionHas('success', 'Planeaciones generadas: 1.');

        $this->assertDatabaseMissing('didactic_plans', [
            'teaching_assignment_id' => $targetAssignment->id,
            'title' => 'Planeacion existente',
        ]);
        $this->assertSame(1, DidacticPlan::query()->where('teaching_assignment_id', $targetAssignment->id)->count());
    }

    public function test_teacher_cannot_clone_another_teacher_plan_to_peer_groups(): void
    {
        $scenario = $this->planningScenario();
        $this->peerAssignment($scenario, '5004', [
            ['martes', '07:00:00', '07:50:00', 'theory'],
        ]);
        $plan = $this->importFilledPlan($scenario);

        $this->actingAs($scenario['otherTeacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('teacher.didactic-plans.clone-to-peer-groups', $plan))
            ->assertForbidden();

        $this->assertSame(1, DidacticPlan::query()->count());
    }

    public function test_import_rejects_workbook_without_required_sheets(): void
    {
        $scenario = $this->planningScenario();
        $path = $this->invalidWorkbookWithoutPlanningSheetPath();

        try {
            $this->actingAs($scenario['teacherUser'])
                ->withSession(['active_campus_id' => $scenario['campus']->id])
                ->post(route('teacher.didactic-plans.import.store', $scenario['assignment']), [
                    'planning_file' => new UploadedFile(
                        $path,
                        'archivo-invalido.xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        null,
                        true
                    ),
                ])
                ->assertSessionHasErrors('planning_file');
        } finally {
            @unlink($path);
        }

        $this->assertSame(0, DidacticPlan::query()->count());
    }

    public function test_import_rejects_planning_rows_without_dates(): void
    {
        $scenario = $this->planningScenario();
        $path = $this->filledPlanningTemplatePath($scenario);

        try {
            $spreadsheet = IOFactory::load($path);
            $planning = $spreadsheet->getSheetByName('Planeacion');
            $planning->setCellValue('E2', '');
            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

            $this->actingAs($scenario['teacherUser'])
                ->withSession(['active_campus_id' => $scenario['campus']->id])
                ->post(route('teacher.didactic-plans.import.store', $scenario['assignment']), [
                    'planning_file' => new UploadedFile(
                        $path,
                        'planeacion-sin-fecha.xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        null,
                        true
                    ),
                ])
                ->assertSessionHasErrors('planning_file');
        } finally {
            @unlink($path);
        }

        $this->assertSame(0, DidacticPlan::query()->count());
    }

    public function test_import_rejects_assignment_without_temario(): void
    {
        $scenario = $this->planningScenario();
        $scenario['temario']->delete();
        $path = $this->minimalPlanningWorkbookPath($scenario);

        try {
            $this->actingAs($scenario['teacherUser'])
                ->withSession(['active_campus_id' => $scenario['campus']->id])
                ->post(route('teacher.didactic-plans.import.store', $scenario['assignment']), [
                    'planning_file' => new UploadedFile(
                        $path,
                        'planeacion-sin-temario.xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        null,
                        true
                    ),
                ])
                ->assertSessionHasErrors('planning_file');
        } finally {
            @unlink($path);
        }

        $this->assertSame(0, DidacticPlan::query()->count());
    }

    private function spreadsheetFromResponse($response)
    {
        $path = tempnam(sys_get_temp_dir(), 'planning-template-');
        file_put_contents($path, $response->streamedContent());

        try {
            return IOFactory::load($path);
        } finally {
            @unlink($path);
        }
    }

    private function filledPlanningTemplatePath(array $scenario): string
    {
        $response = $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.didactic-plans.template', $scenario['assignment']));

        $response->assertOk();

        $spreadsheet = $this->spreadsheetFromResponse($response);
        $institution = $spreadsheet->getSheetByName('Institucion');
        $subject = $spreadsheet->getSheetByName('Materia');
        $planning = $spreadsheet->getSheetByName('Planeacion');

        $institution->setCellValue('B3', 'UNAM-1234');
        $institution->setCellValue('B6', 'DGIRE-OLIVER-001');
        $subject->setCellValue('B12', 'Objetivo general capturado por el profesor.');
        $subject->setCellValue('B13', 'Bibliografia de prueba.');
        $subject->setCellValue('B14', 'Libro de texto, proyector, cuaderno');
        $planning->setCellValue('G2', 'Actividad de apertura con diagnostico.');
        $planning->setCellValue('G3', 'Actividad guiada con ejemplos.');

        $path = tempnam(sys_get_temp_dir(), 'filled-planning-');
        $xlsxPath = $path . '.xlsx';
        @unlink($path);

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($xlsxPath);

        return $xlsxPath;
    }

    private function invalidWorkbookWithoutPlanningSheetPath(): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setTitle('Datos');
        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Archivo invalido');

        return $this->saveTemporaryWorkbook($spreadsheet, 'invalid-planning-');
    }

    private function minimalPlanningWorkbookPath(array $scenario): string
    {
        $spreadsheet = new Spreadsheet();

        $institution = $spreadsheet->getActiveSheet();
        $institution->setTitle('Institucion');
        $institution->setCellValue('B4', $scenario['cycle']->name);

        $subject = $spreadsheet->createSheet();
        $subject->setTitle('Materia');
        $subject->setCellValue('B2', $scenario['subject']->name);
        $subject->setCellValue('B3', $scenario['subject']->type);

        $planning = $spreadsheet->createSheet();
        $planning->setTitle('Planeacion');
        $planning->fromArray([[
            'tema_id',
            'unidad_id',
            'No. de sesion',
            'Clave de grupo(s)',
            'Fecha programada',
            'Contenidos (Solo los numerales)',
            'Estrategias de ensenanza-aprendizaje',
        ]], null, 'A1');
        $planning->fromArray([[0, 0, 1, $scenario['group']->name, '2026-08-03', '1.1.1', 'Actividad sin temario.']], null, 'A2');

        return $this->saveTemporaryWorkbook($spreadsheet, 'minimal-planning-');
    }

    private function saveTemporaryWorkbook(Spreadsheet $spreadsheet, string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        $xlsxPath = $path . '.xlsx';
        @unlink($path);

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($xlsxPath);

        return $xlsxPath;
    }

    private function importFilledPlan(array $scenario): DidacticPlan
    {
        $path = $this->filledPlanningTemplatePath($scenario);

        try {
            $this->actingAs($scenario['teacherUser'])
                ->withSession(['active_campus_id' => $scenario['campus']->id])
                ->post(route('teacher.didactic-plans.import.store', $scenario['assignment']), [
                    'planning_file' => new UploadedFile(
                        $path,
                        'planeacion-quimica-5005.xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        null,
                        true
                    ),
                ])
                ->assertRedirect(route('teacher.didactic-plans.plans', $scenario['assignment']));
        } finally {
            @unlink($path);
        }

        return DidacticPlan::query()
            ->where('teaching_assignment_id', $scenario['assignment']->id)
            ->firstOrFail();
    }

    private function tentativePlan(array $scenario): DidacticPlan
    {
        $plan = DidacticPlan::create([
            'teaching_assignment_id' => $scenario['assignment']->id,
            'school_cycle_id' => $scenario['cycle']->id,
            'academic_period_id' => $scenario['period']->id,
            'title' => 'Planeacion tentativa Quimica III - Grupo 5005',
            'status' => DidacticPlan::STATUS_TENTATIVE,
            'generated_by_system' => true,
            'generated_at' => now(),
            'subject_key' => '1501',
            'total_annual_hours' => 80,
            'objective' => 'Programa indicativo de prueba.',
            'general_resources' => 'Pizarron, cuaderno',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'is_active' => true,
        ]);

        $plan->items()->create([
            'position' => 1,
            'field_training_point_id' => $scenario['unit']->id,
            'objective' => 'Objetivo de unidad.',
            'temario_point_id' => $scenario['topic']->id,
            'temario_subtopic_ids' => [
                $scenario['subtopicA']->id,
                $scenario['subtopicB']->id,
            ],
            'opening' => 'Apertura tentativa.',
            'development' => 'Estrategia tentativa de desarrollo.',
            'closing' => 'Cierre tentativo.',
            'resources' => 'Cuaderno',
            'evaluation' => 'Ejercicios',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
        ]);

        $plan->items()->create([
            'position' => 2,
            'field_training_point_id' => $scenario['unit']->id,
            'objective' => 'Objetivo de unidad.',
            'temario_point_id' => $scenario['topic']->id,
            'temario_subtopic_ids' => [
                $scenario['subtopicB']->id,
            ],
            'opening' => 'Apertura tentativa 2.',
            'development' => 'Estrategia tentativa de desarrollo 2.',
            'closing' => 'Cierre tentativo 2.',
            'resources' => 'Cuaderno',
            'evaluation' => 'Ejercicios',
            'start_date' => '2026-08-06',
            'end_date' => '2026-08-06',
        ]);

        return $plan->refresh();
    }

    private function sessionsAndCriterionForPlanConfirmation(array $scenario): void
    {
        $partial = CyclePartial::create([
            'school_cycle_id' => $scenario['cycle']->id,
            'academic_period_id' => $scenario['period']->id,
            'name' => 'Periodo 1',
            'code' => 'P1',
            'sort_order' => 1,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'is_active' => true,
        ]);

        EvaluationCriterion::create([
            'teaching_assignment_id' => $scenario['assignment']->id,
            'cycle_partial_id' => $partial->id,
            'name' => 'Evaluacion continua',
            'percentage' => 20,
        ]);

        $theorySchedules = Schedule::query()
            ->where('teaching_assignment_id', $scenario['assignment']->id)
            ->whereNull('section_type')
            ->orderBy('day_of_week')
            ->take(2)
            ->get()
            ->values();

        foreach (['2026-08-03', '2026-08-06'] as $index => $date) {
            AcademicSession::create([
                'teaching_assignment_id' => $scenario['assignment']->id,
                'schedule_id' => $theorySchedules[$index]->id,
                'academic_period_id' => $scenario['period']->id,
                'session_date' => $date,
                'start_time' => $theorySchedules[$index]->start_time,
                'end_time' => $theorySchedules[$index]->end_time,
                'is_cancelled' => false,
            ]);
        }
    }

    private function peerAssignment(array $scenario, string $groupName, array $schedules): TeachingAssignment
    {
        $group = Group::create([
            'level_id' => $scenario['level']->id,
            'name' => $groupName,
            'capacity' => 30,
            'is_active' => true,
        ]);

        $cycleGroup = SchoolCycleGroup::create([
            'tenant_id' => $this->tenantId,
            'school_cycle_id' => $scenario['cycle']->id,
            'group_id' => $group->id,
            'campus_id' => $scenario['campus']->id,
            'modality_id' => $scenario['modality']->id,
            'section_count' => 1,
            'is_active' => true,
        ]);
        $cycleGroup->subjects()->syncWithoutDetaching([$scenario['subject']->id]);

        $assignment = TeachingAssignment::create([
            'tenant_id' => $this->tenantId,
            'teacher_id' => $scenario['teacherUser']->teacher->id,
            'group_id' => $group->id,
            'school_cycle_group_id' => $cycleGroup->id,
            'subject_id' => $scenario['subject']->id,
            'section_number' => 1,
            'is_active' => true,
        ]);

        foreach ($schedules as [$day, $start, $end, $type]) {
            Schedule::create([
                'tenant_id' => $this->tenantId,
                'teaching_assignment_id' => $assignment->id,
                'school_cycle_id' => $scenario['cycle']->id,
                'section_number' => 1,
                'section_type' => $type === 'lab' ? 'lab_taller' : null,
                'section_label' => $type === 'lab' ? 'A' : null,
                'day_of_week' => $day,
                'start_time' => $start,
                'end_time' => $end,
                'type' => $type,
                'is_active' => true,
            ]);
        }

        return $assignment->refresh();
    }

    private function skipIfWkhtmltopdfIsMissing(): void
    {
        $binary = trim((string) config('snappy.pdf.binary'), '"');
        if ($binary === '' || ! is_file($binary)) {
            $this->markTestSkipped('wkhtmltopdf no esta disponible en este entorno.');
        }
    }

    private function planningScenario(): array
    {
        $campus = Campus::create([
            'name' => 'Florida',
            'code' => 'FLORIDA'.uniqid(),
            'is_active' => true,
        ]);
        $this->createTenantForCurrentAppUrl();

        $modality = Modality::create([
            'name' => 'PREPARATORIA',
            'is_active' => true,
        ]);
        $level = Level::create([
            'modality_id' => $modality->id,
            'name' => 'Quinto',
            'is_active' => true,
        ]);
        $group = Group::create([
            'level_id' => $level->id,
            'name' => '5005',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $cycle = SchoolCycle::create([
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'name' => 'Preparatoria 2026-2027',
            'code' => 'PREPA-'.uniqid(),
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'is_active' => true,
        ]);
        $period = AcademicPeriod::create([
            'modality_id' => $modality->id,
            'name' => 'Primer parcial',
            'code' => 'P1'.uniqid(),
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-28',
            'is_active' => true,
        ]);
        $cycleGroup = SchoolCycleGroup::create([
            'tenant_id' => $this->tenantId,
            'school_cycle_id' => $cycle->id,
            'group_id' => $group->id,
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'section_count' => 1,
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'level_id' => $level->id,
            'name' => 'Quimica III',
            'hours_per_week' => 3,
            'weekly_theory_hours' => 2,
            'weekly_practice_hours' => 1,
            'annual_hours' => 120,
            'annual_theory_hours' => 80,
            'annual_practice_hours' => 40,
            'type' => Subject::TYPE_THEORETICAL_PRACTICAL,
            'subject_character' => 'Obligatoria',
            'subject_key' => '1501',
            'is_active' => true,
        ]);
        $cycleGroup->subjects()->syncWithoutDetaching([$subject->id]);

        $teacherUser = $this->teacherUser($campus);
        $otherTeacherUser = $this->teacherUser($campus);

        $assignment = TeachingAssignment::create([
            'tenant_id' => $this->tenantId,
            'teacher_id' => $teacherUser->teacher->id,
            'group_id' => $group->id,
            'school_cycle_group_id' => $cycleGroup->id,
            'subject_id' => $subject->id,
            'section_number' => 1,
            'is_active' => true,
        ]);

        foreach ([
            ['lunes', '07:00:00', '07:50:00', 'theory'],
            ['martes', '07:00:00', '07:50:00', 'lab'],
            ['miercoles', '07:00:00', '07:50:00', 'theory'],
            ['jueves', '07:00:00', '07:50:00', 'theory'],
        ] as [$day, $start, $end, $type]) {
            Schedule::create([
                'tenant_id' => $this->tenantId,
                'teaching_assignment_id' => $assignment->id,
                'school_cycle_id' => $cycle->id,
                'section_number' => 1,
                'section_type' => $type === 'lab' ? 'lab_taller' : null,
                'section_label' => $type === 'lab' ? 'A' : null,
                'day_of_week' => $day,
                'start_time' => $start,
                'end_time' => $end,
                'type' => $type,
                'is_active' => true,
            ]);
        }

        AcademicCalendarDay::create([
            'date' => '2026-08-05',
            'type' => 'holiday',
            'name' => 'Feriado de prueba',
            'modality_id' => $modality->id,
            'affects_teachers' => true,
            'affects_students' => true,
        ]);

        $temario = Temario::create([
            'subject_id' => $subject->id,
            'title' => 'Temario Quimica III',
            'description' => 'Programa indicativo de prueba.',
        ]);
        $unit = TemarioPoint::create([
            'temario_id' => $temario->id,
            'position' => 1,
            'label' => '1',
            'level' => 1,
            'type' => 'conceptual',
            'content' => 'Materia y energia',
        ]);
        $topic = TemarioPoint::create([
            'temario_id' => $temario->id,
            'position' => 2,
            'label' => '1.1',
            'level' => 2,
            'type' => 'conceptual',
            'content' => 'Estructura de la materia',
        ]);
        $subtopicA = TemarioPoint::create([
            'temario_id' => $temario->id,
            'position' => 3,
            'label' => '1.1.1',
            'level' => 3,
            'type' => 'conceptual',
            'content' => 'Propiedades de la materia',
        ]);
        $subtopicB = TemarioPoint::create([
            'temario_id' => $temario->id,
            'position' => 4,
            'label' => '1.1.2',
            'level' => 3,
            'type' => 'conceptual',
            'content' => 'Cambios de estado',
        ]);

        return compact(
            'campus',
            'modality',
            'level',
            'group',
            'cycle',
            'period',
            'cycleGroup',
            'subject',
            'teacherUser',
            'otherTeacherUser',
            'assignment',
            'temario',
            'unit',
            'topic',
            'subtopicA',
            'subtopicB'
        );
    }

    private function userWithRole(string $role, Campus $campus): User
    {
        $user = User::factory()->create(['default_campus_id' => $campus->id]);
        $user->assignRole($role);
        $user->campuses()->sync([$campus->id]);

        return $user;
    }

    private function createTenantForCurrentAppUrl(): void
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'gestion-escolar.text';
        $tenant = Tenant::query()->firstOrCreate(['id' => $this->tenantId]);

        if (! $tenant->domains()->where('domain', $host)->exists()) {
            $tenant->domains()->create(['domain' => $host]);
        }
    }

    private function teacherUser(Campus $campus): User
    {
        $user = $this->userWithRole('teacher', $campus);
        Teacher::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        return $user->refresh()->load('teacher');
    }
}
