<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\StudentFollowUp;
use App\Models\StudentFollowUpResponse;
use App\Models\StudentFollowUpTeacher;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\StudentFollowUpRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentFollowUpFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId = 'florida';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['coordinator', 'teacher', 'student', 'prefect', 'guardian', 'tutor'] as $role) {
            Role::findOrCreate($role);
        }

        Notification::fake();
    }

    public function test_coordinator_creates_follow_up_and_assigns_group_teachers(): void
    {
        $scenario = $this->followUpScenario();

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('coordination.follow-ups.store'), [
                'student_id' => $scenario['student']->id,
                'message' => 'Favor de reportar desempeno academico y conducta.',
            ])
            ->assertRedirect();

        $followUp = StudentFollowUp::query()->firstOrFail();

        $this->assertSame($scenario['student']->id, (int) $followUp->student_id);
        $this->assertSame($scenario['coordinatorUser']->id, (int) $followUp->requested_by);
        $this->assertSame('mixed', $followUp->type);
        $this->assertSame('open', $followUp->status);
        $this->assertSame(2, StudentFollowUpTeacher::query()->where('student_follow_up_id', $followUp->id)->count());

        foreach ([$scenario['teacherUser'], $scenario['secondTeacherUser']] as $teacherUser) {
            $this->assertDatabaseHas('student_follow_up_teachers', [
                'student_follow_up_id' => $followUp->id,
                'teacher_id' => $teacherUser->teacher->id,
                'status' => StudentFollowUpTeacher::STATUS_PENDING,
            ]);

            Notification::assertSentTo($teacherUser, StudentFollowUpRequested::class);
        }
    }

    public function test_coordinator_cannot_create_duplicate_open_follow_up_for_same_student(): void
    {
        $scenario = $this->followUpScenario();

        StudentFollowUp::create([
            'student_id' => $scenario['student']->id,
            'requested_by' => $scenario['coordinatorUser']->id,
            'type' => 'mixed',
            'message' => 'Seguimiento abierto.',
            'status' => 'open',
        ]);

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('coordination.follow-ups.store'), [
                'student_id' => $scenario['student']->id,
                'message' => 'Segundo seguimiento.',
            ])
            ->assertStatus(422);

        $this->assertSame(1, StudentFollowUp::query()->where('student_id', $scenario['student']->id)->count());
    }

    public function test_teacher_can_open_only_own_follow_up_assignment(): void
    {
        $scenario = $this->followUpScenario();
        $followUp = $this->createFollowUpWithAssignments($scenario);
        $ownAssignment = $followUp->teachers()->where('teacher_id', $scenario['teacherUser']->teacher->id)->firstOrFail();
        $otherAssignment = $followUp->teachers()->where('teacher_id', $scenario['secondTeacherUser']->teacher->id)->firstOrFail();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.follow-ups.show', $ownAssignment))
            ->assertOk()
            ->assertSee($scenario['student']->user->name)
            ->assertSee('Respuesta del profesor');

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.follow-ups.show', $otherAssignment))
            ->assertForbidden();
    }

    public function test_teacher_response_keeps_follow_up_open_until_all_teachers_answer(): void
    {
        $scenario = $this->followUpScenario();
        $followUp = $this->createFollowUpWithAssignments($scenario);
        $assignment = $followUp->teachers()->where('teacher_id', $scenario['teacherUser']->teacher->id)->firstOrFail();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('teacher.follow-ups.respond', $assignment), [
                'behavior' => 'Participa con respeto y atiende indicaciones.',
                'academic' => 'Entrega actividades con buen avance.',
                'comments' => 'Conviene reforzar habitos de estudio.',
            ])
            ->assertRedirect(route('teacher.follow-ups.index'));

        $response = StudentFollowUpResponse::query()->firstOrFail();

        $this->assertSame('Participa con respeto y atiende indicaciones.', $response->questionnaire['behavior']);
        $this->assertSame('Entrega actividades con buen avance.', $response->questionnaire['academic']);
        $this->assertSame('Conviene reforzar habitos de estudio.', $response->questionnaire['comments']);
        $this->assertSame(StudentFollowUpTeacher::STATUS_ANSWERED, $assignment->fresh()->status);
        $this->assertNotNull($assignment->fresh()->answered_at);
        $this->assertSame('open', $followUp->fresh()->status);
    }

    public function test_follow_up_is_completed_when_all_teachers_answer(): void
    {
        $scenario = $this->followUpScenario();
        $followUp = $this->createFollowUpWithAssignments($scenario);

        foreach ([$scenario['teacherUser'], $scenario['secondTeacherUser']] as $teacherUser) {
            $assignment = $followUp->teachers()->where('teacher_id', $teacherUser->teacher->id)->firstOrFail();

            $this->actingAs($teacherUser)
                ->withSession(['active_campus_id' => $scenario['campus']->id])
                ->post(route('teacher.follow-ups.respond', $assignment), [
                    'behavior' => 'Mantiene una conducta adecuada en clase.',
                    'academic' => 'Cumple con las actividades solicitadas.',
                    'comments' => 'Sin observaciones adicionales.',
                ])
                ->assertRedirect(route('teacher.follow-ups.index'));
        }

        $this->assertSame('completed', $followUp->fresh()->status);
        $this->assertTrue($followUp->fresh()->isCompleted());
    }

    public function test_teacher_cannot_respond_twice(): void
    {
        $scenario = $this->followUpScenario();
        $followUp = $this->createFollowUpWithAssignments($scenario);
        $assignment = $followUp->teachers()->where('teacher_id', $scenario['teacherUser']->teacher->id)->firstOrFail();

        $assignment->response()->create([
            'questionnaire' => [
                'behavior' => 'Primera respuesta.',
                'academic' => 'Primera respuesta.',
            ],
            'comments' => null,
        ]);
        $assignment->update([
            'status' => StudentFollowUpTeacher::STATUS_ANSWERED,
            'answered_at' => now(),
        ]);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('teacher.follow-ups.respond', $assignment), [
                'behavior' => 'Segunda respuesta.',
                'academic' => 'Segunda respuesta.',
                'comments' => null,
            ])
            ->assertForbidden();

        $this->assertSame(1, StudentFollowUpResponse::query()->where('student_follow_up_teacher_id', $assignment->id)->count());
    }

    public function test_teacher_cannot_access_coordination_follow_ups(): void
    {
        $scenario = $this->followUpScenario();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.follow-ups.index'))
            ->assertForbidden();
    }

    private function createFollowUpWithAssignments(array $scenario): StudentFollowUp
    {
        $followUp = StudentFollowUp::create([
            'student_id' => $scenario['student']->id,
            'requested_by' => $scenario['coordinatorUser']->id,
            'type' => 'mixed',
            'message' => 'Seguimiento de prueba.',
            'status' => 'open',
        ]);

        foreach ([$scenario['teacherUser'], $scenario['secondTeacherUser']] as $teacherUser) {
            $followUp->teachers()->create([
                'teacher_id' => $teacherUser->teacher->id,
            ]);
        }

        return $followUp;
    }

    private function followUpScenario(): array
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
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(10)->toDateString(),
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
            'type' => Subject::TYPE_THEORETICAL,
            'is_active' => true,
        ]);
        $cycleGroup->subjects()->syncWithoutDetaching([$subject->id]);

        $coordinatorUser = $this->userWithRole('coordinator', $campus);
        $teacherUser = $this->teacherUser($campus);
        $secondTeacherUser = $this->teacherUser($campus);
        $student = $this->studentUser($campus, $group)->student;

        foreach ([$teacherUser, $teacherUser, $secondTeacherUser] as $index => $assignmentTeacherUser) {
            TeachingAssignment::create([
                'tenant_id' => $this->tenantId,
                'teacher_id' => $assignmentTeacherUser->teacher->id,
                'group_id' => $group->id,
                'school_cycle_group_id' => $cycleGroup->id,
                'subject_id' => $subject->id,
                'section_number' => $index + 1,
                'is_active' => true,
            ])->students()->syncWithoutDetaching([$student->id]);
        }

        return compact(
            'campus',
            'modality',
            'level',
            'group',
            'cycle',
            'cycleGroup',
            'subject',
            'coordinatorUser',
            'teacherUser',
            'secondTeacherUser',
            'student'
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

    private function studentUser(Campus $campus, Group $group): User
    {
        $user = $this->userWithRole('student', $campus);
        Student::create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'enrollment_number' => 'U'.random_int(100000, 999999),
            'is_active' => true,
        ]);

        return $user->refresh()->load('student.user');
    }
}
