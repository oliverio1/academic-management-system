<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherDocumentContent;
use App\Models\TeacherDocumentRequestItem;
use App\Models\TeacherDocumentSubmission;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\CyclePartial;
use App\Services\TeacherDocumentChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeacherDocumentRequestCriteriaFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId = 'florida';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['admin', 'coordinator', 'teacher', 'student', 'guardian', 'tutor', 'prefect'] as $role) {
            Role::findOrCreate($role);
        }

        Storage::fake('public');
    }

    public function test_teacher_can_view_delivered_document_as_inline_pdf(): void
    {
        $scenario = $this->documentScenario();
        $sourceItem = $this->criteriaItem($scenario['sourceAssignment']);
        $submission = $this->submitCriteria($sourceItem, $scenario['teacherUser']);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.document-requests.index'))
            ->assertOk()
            ->assertSee('Ver PDF')
            ->assertSee('Abrir editor')
            ->assertSee('Examen primer parcial')
            ->assertSee('Examen cuarto parcial')
            ->assertSee('Configurar examen')
            ->assertSee('Ir a planeacion')
            ->assertSee('Sin opcion')
            ->assertSee('Misma materia')
            ->assertSee('Todas mis materias')
            ->assertDontSee('Redactar')
            ->assertDontSee('Cargar PDF')
            ->assertDontSee('Generar desde sistema');

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.document-requests.submissions.pdf', $submission))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertSee('%PDF-criterios', false);
    }

    public function test_coordinator_can_open_teacher_document_dashboard_with_database_cache(): void
    {
        $scenario = $this->documentScenario();

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.teacher-documents.index'))
            ->assertOk()
            ->assertSee('Expediente docente');
    }

    public function test_teacher_clones_evaluation_criteria_to_same_subject_only(): void
    {
        $scenario = $this->documentScenario();
        $sourceItem = $this->criteriaItem($scenario['sourceAssignment']);
        $sameSubjectItem = $this->criteriaItem($scenario['sameSubjectAssignment']);
        $otherSubjectItem = $this->criteriaItem($scenario['otherSubjectAssignment']);

        $this->submitCriteria($sourceItem, $scenario['teacherUser']);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('teacher.document-requests.clone-criteria', $sourceItem), [
                'scope' => 'same_subject',
            ])
            ->assertRedirect();

        $this->assertTrue($sameSubjectItem->submissions()->exists());
        $this->assertFalse($otherSubjectItem->submissions()->exists());
        $this->assertSame(
            '<h2>Criterios base</h2><p>Actividades 60%, examen 40%.</p>',
            $sameSubjectItem->fresh('editableContent')->editableContent->content_html
        );
    }

    public function test_teacher_clones_evaluation_criteria_to_all_subjects(): void
    {
        $scenario = $this->documentScenario();
        $sourceItem = $this->criteriaItem($scenario['sourceAssignment']);
        $sameSubjectItem = $this->criteriaItem($scenario['sameSubjectAssignment']);
        $otherSubjectItem = $this->criteriaItem($scenario['otherSubjectAssignment']);
        $otherTeacherItem = $this->criteriaItem($scenario['otherTeacherAssignment']);

        $this->submitCriteria($sourceItem, $scenario['teacherUser']);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('teacher.document-requests.clone-criteria', $sourceItem), [
                'scope' => 'all_subjects',
            ])
            ->assertRedirect();

        $this->assertTrue($sameSubjectItem->submissions()->exists());
        $this->assertTrue($otherSubjectItem->submissions()->exists());
        $this->assertFalse($otherTeacherItem->submissions()->exists());
    }

    public function test_teacher_document_requests_and_dashboard_follow_selected_cycle(): void
    {
        $scenario = $this->documentScenario();

        $otherCycle = SchoolCycle::create([
            'campus_id' => $scenario['campus']->id,
            'modality_id' => $scenario['modality']->id,
            'name' => 'Bachillerato 2026-3',
            'code' => 'BACH-'.uniqid(),
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addMonths(4)->toDateString(),
            'is_active' => true,
        ]);

        $this->assignment(
            $scenario['teacherUser']->teacher,
            $scenario['campus'],
            $scenario['modality'],
            $otherCycle,
            $scenario['level'],
            $scenario['math'],
            '6001'
        );

        $this->actingAs($scenario['teacherUser'])
            ->withSession([
                'active_campus_id' => $scenario['campus']->id,
                'active_school_cycle_id' => $scenario['cycle']->id,
            ])
            ->get(route('teacher.document-requests.index'))
            ->assertOk()
            ->assertSee('5001')
            ->assertDontSee('6001');

        $this->actingAs($scenario['teacherUser'])
            ->withSession([
                'active_campus_id' => $scenario['campus']->id,
                'active_school_cycle_id' => $scenario['cycle']->id,
            ])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tienes 30 documento(s) pendiente(s) por entregar.');
    }

    public function test_document_checklist_uses_real_cycle_partial_count_for_exam_documents(): void
    {
        $scenario = $this->documentScenario();

        CyclePartial::create([
            'school_cycle_id' => $scenario['cycle']->id,
            'name' => 'Primer parcial',
            'code' => 'P1',
            'sort_order' => 1,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->subWeek()->toDateString(),
            'is_active' => true,
        ]);
        CyclePartial::create([
            'school_cycle_id' => $scenario['cycle']->id,
            'name' => 'Segundo parcial',
            'code' => 'P2',
            'sort_order' => 2,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ]);

        app(TeacherDocumentChecklistService::class)->ensureForAssignment($scenario['sourceAssignment']->fresh());

        $types = TeacherDocumentRequestItem::query()
            ->where('teaching_assignment_id', $scenario['sourceAssignment']->id)
            ->pluck('document_type')
            ->all();

        $this->assertContains('examen_parcial_1', $types);
        $this->assertContains('examen_parcial_2', $types);
        $this->assertNotContains('examen_parcial_3', $types);
        $this->assertNotContains('examen_parcial_4', $types);
    }

    private function documentScenario(): array
    {
        $this->createTenantForCurrentAppUrl();

        $campus = Campus::create([
            'name' => 'Florida',
            'code' => 'FLORIDA'.uniqid(),
            'is_active' => true,
        ]);

        $coordinator = $this->userWithRole('coordinator', $campus);
        $teacherUser = $this->teacherUser($campus);
        $otherTeacherUser = $this->teacherUser($campus);

        $modality = Modality::create([
            'name' => 'PREPARATORIA',
            'is_active' => true,
        ]);

        $level = Level::create([
            'modality_id' => $modality->id,
            'name' => 'Quinto',
            'is_active' => true,
        ]);

        $cycle = SchoolCycle::create([
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'name' => 'Preparatoria 2026-2027',
            'code' => 'PREPA-'.uniqid(),
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(10)->toDateString(),
            'is_active' => true,
        ]);

        $chemistry = Subject::create([
            'level_id' => $level->id,
            'name' => 'Quimica III',
            'hours_per_week' => 3,
            'type' => Subject::TYPE_THEORETICAL,
            'is_active' => true,
        ]);

        $math = Subject::create([
            'level_id' => $level->id,
            'name' => 'Matematicas V',
            'hours_per_week' => 5,
            'type' => Subject::TYPE_THEORETICAL,
            'is_active' => true,
        ]);

        $sourceAssignment = $this->assignment($teacherUser->teacher, $campus, $modality, $cycle, $level, $chemistry, '5001');
        $sameSubjectAssignment = $this->assignment($teacherUser->teacher, $campus, $modality, $cycle, $level, $chemistry, '5002');
        $otherSubjectAssignment = $this->assignment($teacherUser->teacher, $campus, $modality, $cycle, $level, $math, '5003');
        $otherTeacherAssignment = $this->assignment($otherTeacherUser->teacher, $campus, $modality, $cycle, $level, $chemistry, '5004');

        return compact(
            'campus',
            'coordinator',
            'teacherUser',
            'otherTeacherUser',
            'modality',
            'level',
            'cycle',
            'math',
            'sourceAssignment',
            'sameSubjectAssignment',
            'otherSubjectAssignment',
            'otherTeacherAssignment'
        );
    }

    private function assignment(
        Teacher $teacher,
        Campus $campus,
        Modality $modality,
        SchoolCycle $cycle,
        Level $level,
        Subject $subject,
        string $groupName
    ): TeachingAssignment {
        $group = Group::create([
            'level_id' => $level->id,
            'name' => $groupName,
            'capacity' => 30,
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

        $cycleGroup->subjects()->syncWithoutDetaching([$subject->id]);

        return TeachingAssignment::create([
            'tenant_id' => $this->tenantId,
            'teacher_id' => $teacher->id,
            'group_id' => $group->id,
            'school_cycle_group_id' => $cycleGroup->id,
            'subject_id' => $subject->id,
            'section_number' => 1,
            'is_active' => true,
        ]);
    }

    private function criteriaItem(TeachingAssignment $assignment): TeacherDocumentRequestItem
    {
        return TeacherDocumentRequestItem::query()
            ->where('teaching_assignment_id', (int) $assignment->id)
            ->where('document_type', 'criterios_evaluacion')
            ->firstOrFail();
    }

    private function submitCriteria(TeacherDocumentRequestItem $item, User $teacherUser): TeacherDocumentSubmission
    {
        Storage::disk('public')->put('teacher_documents/criterios-base.pdf', '%PDF-criterios');

        TeacherDocumentContent::create([
            'tenant_id' => $this->tenantId,
            'item_id' => $item->id,
            'teacher_id' => $teacherUser->teacher->id,
            'updated_by' => $teacherUser->id,
            'title' => 'Criterios base',
            'content_html' => '<h2>Criterios base</h2><p>Actividades 60%, examen 40%.</p>',
            'submitted_at' => now(),
        ]);

        return TeacherDocumentSubmission::create([
            'tenant_id' => $this->tenantId,
            'item_id' => $item->id,
            'teacher_id' => $teacherUser->teacher->id,
            'uploaded_by' => $teacherUser->id,
            'file_path' => 'teacher_documents/criterios-base.pdf',
            'original_name' => 'criterios-base.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 14,
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);
    }

    private function userWithRole(string $role, Campus $campus): User
    {
        $user = User::factory()->create([
            'default_campus_id' => $campus->id,
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);
        $user->campuses()->syncWithoutDetaching([$campus->id]);

        return $user;
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

    private function createTenantForCurrentAppUrl(): void
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'gestion-escolar.text';
        $tenant = Tenant::query()->firstOrCreate(['id' => $this->tenantId]);

        if (! $tenant->domains()->where('domain', $host)->exists()) {
            $tenant->domains()->create(['domain' => $host]);
        }
    }
}
