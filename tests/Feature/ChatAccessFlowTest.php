<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\Schedule;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ChatAccessFlowTest extends TestCase
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

    public function test_coordinator_can_see_all_users_in_chat_bootstrap(): void
    {
        $scenario = $this->chatScenario();

        $payload = $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->getJson(route('chat.bootstrap'))
            ->assertOk()
            ->json();

        $availableNames = collect($payload['available_users'])->pluck('name');

        $this->assertTrue($availableNames->contains($scenario['teacherUser']->name));
        $this->assertTrue($availableNames->contains($scenario['studentUser']->name));
        $this->assertTrue($availableNames->contains($scenario['otherStudentUser']->name));
        $this->assertTrue($availableNames->contains($scenario['guardianUser']->name));
        $this->assertFalse($availableNames->contains($scenario['coordinatorUser']->name));
    }

    public function test_teacher_can_only_contact_administrative_users_and_own_students(): void
    {
        $scenario = $this->chatScenario();

        $payload = $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->getJson(route('chat.bootstrap'))
            ->assertOk()
            ->json();

        $availableNames = collect($payload['available_users'])->pluck('name');

        $this->assertTrue($availableNames->contains($scenario['coordinatorUser']->name));
        $this->assertTrue($availableNames->contains($scenario['adminUser']->name));
        $this->assertTrue($availableNames->contains($scenario['studentUser']->name));
        $this->assertFalse($availableNames->contains($scenario['otherStudentUser']->name));
        $this->assertFalse($availableNames->contains($scenario['otherTeacherUser']->name));
        $this->assertFalse($availableNames->contains($scenario['guardianUser']->name));

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->postJson(route('chat.direct.store'), ['user_id' => $scenario['studentUser']->id])
            ->assertOk()
            ->assertJsonStructure(['conversation_id']);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->postJson(route('chat.direct.store'), ['user_id' => $scenario['otherStudentUser']->id])
            ->assertForbidden();
    }

    public function test_student_can_only_contact_administrative_users_and_own_teachers(): void
    {
        $scenario = $this->chatScenario();

        $payload = $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->getJson(route('chat.bootstrap'))
            ->assertOk()
            ->json();

        $availableNames = collect($payload['available_users'])->pluck('name');

        $this->assertTrue($availableNames->contains($scenario['teacherUser']->name));
        $this->assertTrue($availableNames->contains($scenario['coordinatorUser']->name));
        $this->assertTrue($availableNames->contains($scenario['adminUser']->name));
        $this->assertFalse($availableNames->contains($scenario['otherTeacherUser']->name));
        $this->assertFalse($availableNames->contains($scenario['otherStudentUser']->name));

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->postJson(route('chat.direct.store'), ['user_id' => $scenario['teacherUser']->id])
            ->assertOk()
            ->assertJsonStructure(['conversation_id']);

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->postJson(route('chat.direct.store'), ['user_id' => $scenario['otherTeacherUser']->id])
            ->assertForbidden();
    }

    public function test_guardian_can_only_contact_administrative_users(): void
    {
        $scenario = $this->chatScenario();

        $payload = $this->actingAs($scenario['guardianUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->getJson(route('chat.bootstrap'))
            ->assertOk()
            ->json();

        $availableNames = collect($payload['available_users'])->pluck('name');

        $this->assertTrue($availableNames->contains($scenario['coordinatorUser']->name));
        $this->assertTrue($availableNames->contains($scenario['adminUser']->name));
        $this->assertFalse($availableNames->contains($scenario['teacherUser']->name));
        $this->assertFalse($availableNames->contains($scenario['studentUser']->name));

        $this->actingAs($scenario['guardianUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->postJson(route('chat.direct.store'), ['user_id' => $scenario['adminUser']->id])
            ->assertOk();

        $this->actingAs($scenario['guardianUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->postJson(route('chat.direct.store'), ['user_id' => $scenario['teacherUser']->id])
            ->assertForbidden();
    }

    public function test_teacher_can_create_group_chat_only_for_own_active_cycle_groups(): void
    {
        $scenario = $this->chatScenario();

        $payload = $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->getJson(route('chat.bootstrap'))
            ->assertOk()
            ->json();

        $availableGroupNames = collect($payload['available_groups'])->pluck('name');

        $this->assertTrue($availableGroupNames->contains('5005'));
        $this->assertFalse($availableGroupNames->contains('5006'));

        $response = $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->postJson(route('chat.group.store'), ['group_id' => $scenario['group']->id])
            ->assertOk()
            ->assertJsonStructure(['conversation_id']);

        $conversation = ChatConversation::findOrFail((int) $response->json('conversation_id'));
        $participantIds = $conversation->participants()->pluck('users.id');

        $this->assertTrue($participantIds->contains($scenario['studentUser']->id));
        $this->assertTrue($participantIds->contains($scenario['teacherUser']->id));
        $this->assertTrue($participantIds->contains($scenario['coordinatorUser']->id));
        $this->assertTrue($participantIds->contains($scenario['adminUser']->id));

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->postJson(route('chat.group.store'), ['group_id' => $scenario['otherGroup']->id])
            ->assertForbidden();
    }

    public function test_message_store_and_fetch_are_blocked_for_invalid_conversation_access(): void
    {
        $scenario = $this->chatScenario();

        $validResponse = $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->postJson(route('chat.direct.store'), ['user_id' => $scenario['studentUser']->id])
            ->assertOk();

        $validConversation = ChatConversation::findOrFail((int) $validResponse->json('conversation_id'));

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->postJson(route('chat.messages.store', $validConversation), ['body' => 'Mensaje de prueba.'])
            ->assertOk()
            ->assertJsonPath('message.body', 'Mensaje de prueba.');

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->getJson(route('chat.messages.fetch', $validConversation))
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Mensaje de prueba.');

        $invalidConversation = ChatConversation::create([
            'type' => 'direct',
            'created_by' => $scenario['teacherUser']->id,
        ]);
        $invalidConversation->participants()->sync([
            $scenario['teacherUser']->id => ['last_read_at' => null],
            $scenario['otherStudentUser']->id => ['last_read_at' => null],
        ]);
        ChatMessage::create([
            'conversation_id' => $invalidConversation->id,
            'user_id' => $scenario['otherStudentUser']->id,
            'body' => 'Mensaje que no debe verse.',
        ]);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->postJson(route('chat.messages.store', $invalidConversation), ['body' => 'Intento indebido.'])
            ->assertForbidden();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->getJson(route('chat.messages.fetch', $invalidConversation))
            ->assertForbidden();
    }

    private function chatScenario(): array
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
        $otherGroup = Group::create([
            'level_id' => $level->id,
            'name' => '5006',
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
        $cycleGroup = $this->cycleGroup($campus, $modality, $cycle, $group);
        $otherCycleGroup = $this->cycleGroup($campus, $modality, $cycle, $otherGroup);

        $subject = Subject::create([
            'level_id' => $level->id,
            'name' => 'Quimica III',
            'hours_per_week' => 3,
            'type' => Subject::TYPE_THEORETICAL,
            'is_active' => true,
        ]);
        $otherSubject = Subject::create([
            'level_id' => $level->id,
            'name' => 'Geografia',
            'hours_per_week' => 3,
            'type' => Subject::TYPE_THEORETICAL,
            'is_active' => true,
        ]);
        $cycleGroup->subjects()->syncWithoutDetaching([$subject->id]);
        $otherCycleGroup->subjects()->syncWithoutDetaching([$otherSubject->id]);

        $adminUser = $this->userWithRole('admin', $campus, 'Admin Chat');
        $coordinatorUser = $this->userWithRole('coordinator', $campus, 'Coordinacion Chat');
        $guardianUser = $this->userWithRole('guardian', $campus, 'Tutor Chat');
        $teacherUser = $this->teacherUser($campus, 'Profesor Quimica');
        $otherTeacherUser = $this->teacherUser($campus, 'Profesor Geografia');
        $studentUser = $this->studentUser($campus, $group, 'Alumno Propio');
        $otherStudentUser = $this->studentUser($campus, $otherGroup, 'Alumno Ajeno');

        $assignment = TeachingAssignment::create([
            'tenant_id' => $this->tenantId,
            'teacher_id' => $teacherUser->teacher->id,
            'group_id' => $group->id,
            'school_cycle_group_id' => $cycleGroup->id,
            'subject_id' => $subject->id,
            'section_number' => 1,
            'is_active' => true,
        ]);
        $assignment->students()->sync([$studentUser->student->id]);

        $otherAssignment = TeachingAssignment::create([
            'tenant_id' => $this->tenantId,
            'teacher_id' => $otherTeacherUser->teacher->id,
            'group_id' => $otherGroup->id,
            'school_cycle_group_id' => $otherCycleGroup->id,
            'subject_id' => $otherSubject->id,
            'section_number' => 1,
            'is_active' => true,
        ]);
        $otherAssignment->students()->sync([$otherStudentUser->student->id]);

        Schedule::create([
            'tenant_id' => $this->tenantId,
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'section_number' => 1,
            'day_of_week' => 'lunes',
            'start_time' => '07:00:00',
            'end_time' => '07:50:00',
            'type' => 'theory',
            'is_active' => true,
        ]);
        Schedule::create([
            'tenant_id' => $this->tenantId,
            'teaching_assignment_id' => $otherAssignment->id,
            'school_cycle_id' => $cycle->id,
            'section_number' => 1,
            'day_of_week' => 'martes',
            'start_time' => '07:00:00',
            'end_time' => '07:50:00',
            'type' => 'theory',
            'is_active' => true,
        ]);

        return compact(
            'campus',
            'modality',
            'level',
            'group',
            'otherGroup',
            'cycle',
            'cycleGroup',
            'otherCycleGroup',
            'subject',
            'otherSubject',
            'adminUser',
            'coordinatorUser',
            'guardianUser',
            'teacherUser',
            'otherTeacherUser',
            'studentUser',
            'otherStudentUser',
            'assignment',
            'otherAssignment'
        );
    }

    private function cycleGroup(Campus $campus, Modality $modality, SchoolCycle $cycle, Group $group): SchoolCycleGroup
    {
        return SchoolCycleGroup::create([
            'tenant_id' => $this->tenantId,
            'school_cycle_id' => $cycle->id,
            'group_id' => $group->id,
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'section_count' => 1,
            'is_active' => true,
        ]);
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

    private function teacherUser(Campus $campus, string $name): User
    {
        $user = $this->userWithRole('teacher', $campus, $name);
        Teacher::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        return $user->refresh()->load('teacher');
    }

    private function studentUser(Campus $campus, Group $group, string $name): User
    {
        $user = $this->userWithRole('student', $campus, $name);
        Student::create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'enrollment_number' => 'U'.random_int(100000, 999999),
            'is_active' => true,
        ]);

        return $user->refresh()->load('student.user');
    }
}
