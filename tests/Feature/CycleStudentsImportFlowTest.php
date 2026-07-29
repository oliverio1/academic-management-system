<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CycleStudentsImportFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId = 'florida';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-20 08:00:00'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['coordinator', 'teacher', 'student', 'guardian', 'admin'] as $role) {
            Role::findOrCreate($role);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_coordinator_downloads_cycle_students_template_with_catalogs(): void
    {
        $scenario = $this->importScenario();

        $response = $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('imports.cycle-students.template', [
                'campus_id' => $scenario['campus']->id,
                'school_cycle_id' => $scenario['cycle']->id,
            ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'cargar-alumnos-',
            (string) $response->headers->get('content-disposition')
        );

        $path = tempnam(sys_get_temp_dir(), 'cycle-students-template-') . '.xlsx';
        file_put_contents($path, $response->streamedContent());

        $spreadsheet = IOFactory::load($path);
        $this->assertSame(['ALUMNOS', 'CATALOGOS', 'INSTRUCCIONES'], $spreadsheet->getSheetNames());

        $studentsSheet = $spreadsheet->getSheetByName('ALUMNOS');
        $catalogSheet = $spreadsheet->getSheetByName('CATALOGOS');

        $this->assertSame('CAMPUS', $studentsSheet->getCell('A1')->getValue());
        $this->assertSame('SECCION INGLES', $studentsSheet->getCell('E1')->getValue());
        $this->assertSame('SECCION LAB', $studentsSheet->getCell('F1')->getValue());
        $this->assertSame('FLORIDA', $studentsSheet->getCell('A2')->getValue());
        $this->assertSame('5005', (string) $studentsSheet->getCell('D2')->getValue());
        $this->assertSame('BASICO', $catalogSheet->getCell('F2')->getValue());
        $this->assertSame('AVANZADO', $catalogSheet->getCell('F3')->getValue());
        $this->assertSame('A', $catalogSheet->getCell('G2')->getValue());
        $this->assertSame('B', $catalogSheet->getCell('G3')->getValue());

        $spreadsheet->disconnectWorksheets();
        @unlink($path);
    }

    public function test_preview_validates_students_file_without_persisting_records(): void
    {
        Storage::fake();
        $scenario = $this->importScenario();
        $file = $this->studentsWorkbookUpload($scenario, [
            ['U900001', 'OLIVER', 'MARTINEZ', 'ANAYA', 'oliver@example.test', 'BASICO', 'A'],
        ]);

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('imports.cycle-students.preview'), [
                'campus_id' => $scenario['campus']->id,
                'school_cycle_id' => $scenario['cycle']->id,
                'file' => $file,
            ])
            ->assertOk()
            ->assertViewIs('imports.cycle-students.preview')
            ->assertViewHas('summary', function (array $summary) {
                return $summary['dry_run'] === true
                    && $summary['metrics']['Filas validas'] === 1
                    && $summary['metrics']['Alumnos creados'] === 1
                    && $summary['metrics']['Tutores creados'] === 1
                    && $summary['metrics']['Vinculos de seccion'] === 2;
            });

        $this->assertDatabaseMissing('students', ['enrollment_number' => 'U900001']);
        $this->assertDatabaseMissing('users', ['email' => 'oliver@example.test']);
    }

    public function test_import_creates_student_guardian_history_and_section_links(): void
    {
        Storage::fake();
        $scenario = $this->importScenario();
        $file = $this->studentsWorkbookUpload($scenario, [
            ['U900002', 'SOFIA', 'GARCIA', 'LOPEZ', 'sofia@example.test', 'AVANZADO', 'B'],
        ]);

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('imports.cycle-students.preview'), [
                'campus_id' => $scenario['campus']->id,
                'school_cycle_id' => $scenario['cycle']->id,
                'file' => $file,
            ])
            ->assertOk();

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('imports.cycle-students.import'))
            ->assertOk()
            ->assertViewIs('imports.cycle-students.result')
            ->assertViewHas('summary', function (array $summary) {
                return $summary['dry_run'] === false
                    && $summary['metrics']['Alumnos creados'] === 1
                    && $summary['metrics']['Tutores creados'] === 1
                    && $summary['metrics']['Tutores vinculados'] === 1
                    && $summary['metrics']['Vinculos de seccion'] === 2;
            });

        $student = Student::query()
            ->where('enrollment_number', 'U900002')
            ->with(['user', 'guardian'])
            ->firstOrFail();

        $this->assertSame('SOFIA GARCIA LOPEZ', $student->user->name);
        $this->assertSame('sofia@example.test', $student->user->email);
        $this->assertSame($scenario['group']->id, $student->group_id);
        $this->assertNotNull($student->guardian_user_id);
        $this->assertTrue($student->guardian->hasRole('guardian'));

        $this->assertDatabaseHas('student_group_histories', [
            'student_id' => $student->id,
            'group_id' => $scenario['group']->id,
            'reason' => 'importacion_alumnos_ciclo_' . $scenario['cycle']->code,
        ]);
        $this->assertDatabaseHas('teaching_assignment_student', [
            'student_id' => $student->id,
            'teaching_assignment_id' => $scenario['englishAdvancedAssignment']->id,
        ]);
        $this->assertDatabaseHas('teaching_assignment_student', [
            'student_id' => $student->id,
            'teaching_assignment_id' => $scenario['labBAssignment']->id,
        ]);
        $this->assertDatabaseMissing('teaching_assignment_student', [
            'student_id' => $student->id,
            'teaching_assignment_id' => $scenario['englishBasicAssignment']->id,
        ]);
    }

    public function test_preview_reports_invalid_rows_and_keeps_import_button_safe(): void
    {
        Storage::fake();
        $scenario = $this->importScenario();
        $file = $this->studentsWorkbookUpload($scenario, [
            ['U900003', 'DIEGO', 'RAMIREZ', 'CRUZ', 'diego@example.test', 'INTERMEDIO', 'A'],
            ['U900003', 'DIEGO', 'RAMIREZ', 'CRUZ', 'diego2@example.test', 'BASICO', 'A'],
        ]);

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('imports.cycle-students.preview'), [
                'campus_id' => $scenario['campus']->id,
                'school_cycle_id' => $scenario['cycle']->id,
                'file' => $file,
            ])
            ->assertOk()
            ->assertViewHas('summary', function (array $summary) {
                return $summary['has_warnings'] === true
                    && $summary['metrics']['Filas validas'] === 1
                    && $summary['metrics']['Filas omitidas'] === 1
                    && str_contains(implode(' ', $summary['warnings']), 'SECCION_INGLES debe ser BASICO o AVANZADO');
            });

        $this->assertDatabaseMissing('students', ['enrollment_number' => 'U900003']);
    }

    public function test_teacher_cannot_open_cycle_students_import_screen(): void
    {
        $scenario = $this->importScenario();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('imports.cycle-students.create'))
            ->assertForbidden();
    }

    private function importScenario(): array
    {
        $this->createTenantForCurrentAppUrl();

        $campus = Campus::create([
            'name' => 'Universidad Latinoamericana - Campus Florida',
            'code' => 'FLORIDA',
            'is_active' => true,
        ]);
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
            'capacity' => 35,
            'is_active' => true,
        ]);
        $cycle = SchoolCycle::create([
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'name' => 'Preparatoria 2026-2027',
            'code' => 'PREPA-26-27',
            'start_date' => '2026-08-10',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
        $cycle->campuses()->sync([$campus->id]);
        $cycle->modalities()->sync([$modality->id]);

        $cycleGroup = SchoolCycleGroup::create([
            'tenant_id' => $this->tenantId,
            'school_cycle_id' => $cycle->id,
            'group_id' => $group->id,
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'section_count' => 2,
            'is_active' => true,
        ]);

        $englishSubject = Subject::create([
            'level_id' => $level->id,
            'name' => 'Ingles V',
            'hours_per_week' => 3,
            'type' => Subject::TYPE_THEORETICAL,
            'is_active' => true,
        ]);
        $labSubject = Subject::create([
            'level_id' => $level->id,
            'name' => 'Quimica III',
            'hours_per_week' => 4,
            'type' => Subject::TYPE_THEORETICAL_PRACTICAL,
            'is_active' => true,
        ]);
        $cycleGroup->subjects()->sync([$englishSubject->id, $labSubject->id]);

        $coordinator = $this->userWithRole('coordinator', $campus, 'Coordinacion Import');
        $teacherUser = $this->userWithRole('teacher', $campus, 'Profesor Import');
        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'is_active' => true,
        ]);

        $englishBasicAssignment = $this->assignment($teacher, $group, $cycleGroup, $englishSubject, 'english', 'BASICO');
        $englishAdvancedAssignment = $this->assignment($teacher, $group, $cycleGroup, $englishSubject, 'english', 'AVANZADO');
        $labAAssignment = $this->assignment($teacher, $group, $cycleGroup, $labSubject, 'lab_taller', 'A');
        $labBAssignment = $this->assignment($teacher, $group, $cycleGroup, $labSubject, 'lab_taller', 'B');

        return compact(
            'campus',
            'modality',
            'level',
            'group',
            'cycle',
            'cycleGroup',
            'coordinator',
            'teacherUser',
            'englishBasicAssignment',
            'englishAdvancedAssignment',
            'labAAssignment',
            'labBAssignment'
        );
    }

    private function assignment(
        Teacher $teacher,
        Group $group,
        SchoolCycleGroup $cycleGroup,
        Subject $subject,
        string $sectionType,
        string $sectionLabel
    ): TeachingAssignment {
        return TeachingAssignment::create([
            'tenant_id' => $this->tenantId,
            'teacher_id' => $teacher->id,
            'group_id' => $group->id,
            'school_cycle_group_id' => $cycleGroup->id,
            'subject_id' => $subject->id,
            'section_number' => $sectionLabel === 'BASICO' || $sectionLabel === 'A' ? 1 : 2,
            'section_type' => $sectionType,
            'section_label' => $sectionLabel,
            'is_active' => true,
        ]);
    }

    private function studentsWorkbookUpload(array $scenario, array $students): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('ALUMNOS');
        $sheet->fromArray([
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
        ], null, 'A1');

        $row = 2;
        foreach ($students as $student) {
            [$enrollment, $name, $lastName, $secondLastName, $email, $englishSection, $labSection] = $student;
            $sheet->fromArray([
                $scenario['campus']->code,
                $scenario['cycle']->name,
                $scenario['level']->name,
                $scenario['group']->name,
                $englishSection,
                $labSection,
                $enrollment,
                $name,
                $lastName,
                $secondLastName,
                $email,
                '5560000000',
                'ACTIVO',
                'TUTOR ' . $lastName,
                'PADRE',
                strtolower($enrollment) . '.tutor@example.test',
                '5570000000',
                'Av. Mexico 410',
                '',
            ], null, "A{$row}");
            $row++;
        }

        $path = tempnam(sys_get_temp_dir(), 'cycle-students-import-') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'alumnos-ciclo.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function userWithRole(string $role, Campus $campus, string $name): User
    {
        $user = User::factory()->create([
            'default_campus_id' => $campus->id,
            'name' => $name,
        ]);
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
}
