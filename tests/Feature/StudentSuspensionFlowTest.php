<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\Schedule;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\StudentSuspension;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentSuspensionFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId = 'florida';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-20 08:00:00'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['coordinator', 'teacher', 'student', 'prefect', 'guardian', 'tutor'] as $role) {
            Role::findOrCreate($role);
        }

        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_coordinator_can_open_suspensions_index_and_create_form(): void
    {
        $scenario = $this->suspensionScenario();

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.suspensions.index'))
            ->assertOk()
            ->assertSee('Suspensiones de alumnos');

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.suspensions.create'))
            ->assertOk()
            ->assertSee('Registrar suspension')
            ->assertSee($scenario['group']->name)
            ->assertSee($scenario['student']->user->name);
    }

    public function test_coordinator_creates_suspension_and_locks_attendance_as_absent(): void
    {
        $scenario = $this->suspensionScenario();

        Attendance::create([
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['student']->id,
            'status' => 'present',
        ]);

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('coordination.suspensions.store'), [
                'group_id' => $scenario['group']->id,
                'student_id' => $scenario['student']->id,
                'start_date' => $scenario['session']->session_date->toDateString(),
                'end_date' => $scenario['session']->session_date->toDateString(),
                'reason' => 'Suspension disciplinaria',
            ])
            ->assertRedirect(route('coordination.suspensions.index'));

        $suspension = StudentSuspension::query()->firstOrFail();

        $this->assertSame($scenario['coordinatorUser']->id, (int) $suspension->created_by);
        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['student']->id,
            'status' => 'absent',
            'student_suspension_id' => $suspension->id,
            'is_suspension_locked' => true,
        ]);
        $this->assertDatabaseHas('announcements', [
            'title' => 'Suspension registrada: '.$scenario['student']->user->name,
            'scope' => 'internal',
            'audience' => 'specific',
        ]);
    }

    public function test_suspension_does_not_overwrite_existing_justified_attendance(): void
    {
        $scenario = $this->suspensionScenario();

        Attendance::create([
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['student']->id,
            'status' => 'justified',
        ]);

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('coordination.suspensions.store'), [
                'group_id' => $scenario['group']->id,
                'student_id' => $scenario['student']->id,
                'start_date' => $scenario['session']->session_date->toDateString(),
                'end_date' => $scenario['session']->session_date->toDateString(),
                'reason' => 'Suspension disciplinaria',
            ])
            ->assertRedirect(route('coordination.suspensions.index'));

        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['student']->id,
            'status' => 'justified',
            'student_suspension_id' => null,
            'is_suspension_locked' => false,
        ]);
    }

    public function test_teacher_cannot_overwrite_suspension_locked_attendance(): void
    {
        $scenario = $this->suspensionScenario();
        $suspension = StudentSuspension::create([
            'group_id' => $scenario['group']->id,
            'student_id' => $scenario['student']->id,
            'start_date' => $scenario['session']->session_date->toDateString(),
            'end_date' => $scenario['session']->session_date->toDateString(),
            'reason' => 'Suspension disciplinaria',
            'created_by' => $scenario['coordinatorUser']->id,
        ]);
        Attendance::create([
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['student']->id,
            'status' => 'absent',
            'student_suspension_id' => $suspension->id,
            'is_suspension_locked' => true,
        ]);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('attendance.store', $scenario['session']), [
                'attendance' => [
                    $scenario['student']->id => 'present',
                ],
            ])
            ->assertRedirect(route('teacher.classes.sessions.index', $scenario['assignment']));

        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['student']->id,
            'status' => 'absent',
            'student_suspension_id' => $suspension->id,
            'is_suspension_locked' => true,
        ]);
    }

    public function test_updating_suspension_releases_old_locks_and_applies_new_range(): void
    {
        $scenario = $this->suspensionScenario(['include_second_session' => true]);
        $suspension = StudentSuspension::create([
            'group_id' => $scenario['group']->id,
            'student_id' => $scenario['student']->id,
            'start_date' => $scenario['session']->session_date->toDateString(),
            'end_date' => $scenario['session']->session_date->toDateString(),
            'reason' => 'Suspension inicial',
            'created_by' => $scenario['coordinatorUser']->id,
        ]);
        Attendance::create([
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['student']->id,
            'status' => 'absent',
            'student_suspension_id' => $suspension->id,
            'is_suspension_locked' => true,
        ]);

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('coordination.suspensions.update', $suspension), [
                'group_id' => $scenario['group']->id,
                'student_id' => $scenario['student']->id,
                'start_date' => $scenario['secondSession']->session_date->toDateString(),
                'end_date' => $scenario['secondSession']->session_date->toDateString(),
                'reason' => 'Suspension ajustada',
            ])
            ->assertRedirect(route('coordination.suspensions.index'));

        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['student']->id,
            'student_suspension_id' => null,
            'is_suspension_locked' => false,
        ]);
        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['secondSession']->id,
            'student_id' => $scenario['student']->id,
            'status' => 'absent',
            'student_suspension_id' => $suspension->id,
            'is_suspension_locked' => true,
        ]);
    }

    public function test_deleting_suspension_releases_attendance_locks(): void
    {
        $scenario = $this->suspensionScenario();
        $suspension = StudentSuspension::create([
            'group_id' => $scenario['group']->id,
            'student_id' => $scenario['student']->id,
            'start_date' => $scenario['session']->session_date->toDateString(),
            'end_date' => $scenario['session']->session_date->toDateString(),
            'reason' => 'Suspension disciplinaria',
            'created_by' => $scenario['coordinatorUser']->id,
        ]);
        Attendance::create([
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['student']->id,
            'status' => 'absent',
            'student_suspension_id' => $suspension->id,
            'is_suspension_locked' => true,
        ]);

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->delete(route('coordination.suspensions.destroy', $suspension))
            ->assertRedirect(route('coordination.suspensions.index'));

        $this->assertDatabaseMissing('student_suspensions', [
            'id' => $suspension->id,
        ]);
        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['student']->id,
            'student_suspension_id' => null,
            'is_suspension_locked' => false,
        ]);
    }

    public function test_suspension_rejects_student_from_another_group(): void
    {
        $scenario = $this->suspensionScenario();
        $otherGroup = Group::create([
            'level_id' => $scenario['level']->id,
            'name' => '5006',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $otherStudent = $this->studentUser($scenario['campus'], $otherGroup)->student;

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('coordination.suspensions.store'), [
                'group_id' => $scenario['group']->id,
                'student_id' => $otherStudent->id,
                'start_date' => $scenario['session']->session_date->toDateString(),
                'end_date' => $scenario['session']->session_date->toDateString(),
                'reason' => 'Suspension disciplinaria',
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('student_suspensions', [
            'student_id' => $otherStudent->id,
        ]);
    }

    public function test_teacher_cannot_access_coordination_suspensions(): void
    {
        $scenario = $this->suspensionScenario();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.suspensions.index'))
            ->assertForbidden();
    }

    private function suspensionScenario(array $options = []): array
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
        $period = AcademicPeriod::create([
            'modality_id' => $modality->id,
            'name' => 'Primer parcial',
            'code' => 'P1'.uniqid(),
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
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
        $student = $this->studentUser($campus, $group)->student;

        $assignment = TeachingAssignment::create([
            'tenant_id' => $this->tenantId,
            'teacher_id' => $teacherUser->teacher->id,
            'group_id' => $group->id,
            'school_cycle_group_id' => $cycleGroup->id,
            'subject_id' => $subject->id,
            'section_number' => 1,
            'is_active' => true,
        ]);
        $assignment->students()->sync([$student->id]);

        $schedule = Schedule::create([
            'tenant_id' => $this->tenantId,
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'section_number' => 1,
            'day_of_week' => 'lunes',
            'start_time' => now()->subMinutes(5)->format('H:i:s'),
            'end_time' => now()->addMinutes(45)->format('H:i:s'),
            'type' => 'theory',
            'is_active' => true,
        ]);
        $session = AcademicSession::create([
            'teaching_assignment_id' => $assignment->id,
            'schedule_id' => $schedule->id,
            'academic_period_id' => $period->id,
            'session_date' => now()->toDateString(),
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'is_cancelled' => false,
        ]);

        $secondSession = null;
        if ($options['include_second_session'] ?? false) {
            $secondSession = AcademicSession::create([
                'teaching_assignment_id' => $assignment->id,
                'schedule_id' => $schedule->id,
                'academic_period_id' => $period->id,
                'session_date' => now()->addDay()->toDateString(),
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'is_cancelled' => false,
            ]);
        }

        return compact(
            'campus',
            'modality',
            'level',
            'group',
            'cycle',
            'period',
            'cycleGroup',
            'subject',
            'coordinatorUser',
            'teacherUser',
            'student',
            'assignment',
            'schedule',
            'session',
            'secondSession'
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

        return $user->refresh()->load('student');
    }
}
