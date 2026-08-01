<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\Schedule;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherStudentReport;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\CoordinatorReviewNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeacherStudentReportFlowTest extends TestCase
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

    public function test_teacher_can_open_report_form_with_active_cycle_students(): void
    {
        $scenario = $this->reportScenario();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.reports.create'))
            ->assertOk()
            ->assertSee('Reporte de alumno')
            ->assertSee($scenario['group']->name)
            ->assertSee($scenario['student']->user->name);
    }

    public function test_teacher_can_browse_assigned_groups_students_and_student_detail(): void
    {
        $scenario = $this->reportScenario();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.students.index'))
            ->assertOk()
            ->assertSee('Mis alumnos')
            ->assertSee($scenario['group']->name)
            ->assertSee('Quimica III');

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.students.group', $scenario['group']))
            ->assertOk()
            ->assertSee($scenario['student']->user->name)
            ->assertSee('Detalle');

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.students.show', $scenario['student']))
            ->assertOk()
            ->assertSee($scenario['student']->user->name)
            ->assertSee('Resumen por materia')
            ->assertSee('Asistencia reciente')
            ->assertSee('Actividades recientes');
    }

    public function test_teacher_cannot_open_student_detail_for_unassigned_group(): void
    {
        $scenario = $this->reportScenario();

        $this->actingAs($scenario['otherTeacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.students.group', $scenario['group']))
            ->assertForbidden();

        $this->actingAs($scenario['otherTeacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.students.show', $scenario['student']))
            ->assertForbidden();
    }

    public function test_teacher_creates_report_for_assigned_student_and_notifies_coordination(): void
    {
        $scenario = $this->reportScenario();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('teacher.reports.store'), [
                'group_id' => $scenario['group']->id,
                'student_id' => $scenario['student']->id,
                'report_type' => 'academic',
                'reason' => 'No entrego actividades durante dos semanas.',
                'severity' => 2,
            ])
            ->assertRedirect(route('teacher.reports.index'));

        $report = TeacherStudentReport::query()->firstOrFail();

        $this->assertSame($scenario['teacherUser']->teacher->id, (int) $report->teacher_id);
        $this->assertSame($scenario['group']->id, (int) $report->group_id);
        $this->assertSame($scenario['student']->id, (int) $report->student_id);
        $this->assertSame('academic', $report->report_type);
        $this->assertSame('open', $report->status);
        $this->assertSame(2, $report->severity);

        Notification::assertSentTo($scenario['coordinatorUser'], CoordinatorReviewNotification::class);
    }

    public function test_teacher_cannot_report_student_from_another_group(): void
    {
        $scenario = $this->reportScenario();
        $otherGroup = Group::create([
            'level_id' => $scenario['level']->id,
            'name' => '5006',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $otherStudent = $this->studentUser($scenario['campus'], $otherGroup)->student;

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('teacher.reports.store'), [
                'group_id' => $scenario['group']->id,
                'student_id' => $otherStudent->id,
                'report_type' => 'behavioral',
                'reason' => 'El alumno no pertenece al grupo seleccionado.',
                'severity' => 1,
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('teacher_student_reports', [
            'student_id' => $otherStudent->id,
        ]);
    }

    public function test_teacher_cannot_open_or_update_another_teacher_report(): void
    {
        $scenario = $this->reportScenario();
        $report = $this->createTeacherReport($scenario);

        $this->actingAs($scenario['otherTeacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.reports.show', $report))
            ->assertForbidden();

        $this->actingAs($scenario['otherTeacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('teacher.reports.update', $report), [
                'group_id' => $scenario['group']->id,
                'student_id' => $scenario['student']->id,
                'report_type' => 'mixed',
                'reason' => 'Intento de modificar reporte ajeno.',
                'severity' => 3,
            ])
            ->assertForbidden();

        $this->assertSame('Reporte original.', $report->fresh()->reason);
    }

    public function test_teacher_cannot_update_report_after_coordination_reviewed_it(): void
    {
        $scenario = $this->reportScenario();
        $report = $this->createTeacherReport($scenario);
        $report->update([
            'status' => 'reviewed',
            'reviewed_by' => $scenario['coordinatorUser']->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('teacher.reports.update', $report), [
                'group_id' => $scenario['group']->id,
                'student_id' => $scenario['student']->id,
                'report_type' => 'mixed',
                'reason' => 'Cambio posterior a revision.',
                'severity' => 3,
            ])
            ->assertRedirect(route('teacher.reports.index'));

        $this->assertSame('Reporte original.', $report->fresh()->reason);
        $this->assertSame('reviewed', $report->fresh()->status);
    }

    public function test_coordination_can_list_and_mark_teacher_report_as_reviewed(): void
    {
        $scenario = $this->reportScenario();
        $report = $this->createTeacherReport($scenario);

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.reports.index'))
            ->assertOk()
            ->assertSee($scenario['student']->user->name)
            ->assertSee($scenario['teacherUser']->name)
            ->assertSee('Reporte original.');

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->patch(route('coordination.reports.review', $report))
            ->assertRedirect();

        $report->refresh();

        $this->assertSame('reviewed', $report->status);
        $this->assertSame($scenario['coordinatorUser']->id, (int) $report->reviewed_by);
        $this->assertNotNull($report->reviewed_at);
    }

    public function test_teacher_cannot_access_coordination_reports(): void
    {
        $scenario = $this->reportScenario();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.reports.index'))
            ->assertForbidden();
    }

    private function createTeacherReport(array $scenario): TeacherStudentReport
    {
        return TeacherStudentReport::create([
            'teacher_id' => $scenario['teacherUser']->teacher->id,
            'group_id' => $scenario['group']->id,
            'student_id' => $scenario['student']->id,
            'report_type' => 'academic',
            'reason' => 'Reporte original.',
            'severity' => 2,
            'status' => 'open',
        ]);
    }

    private function reportScenario(): array
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
        $otherTeacherUser = $this->teacherUser($campus);
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
            'otherTeacherUser',
            'student',
            'assignment'
        );
    }

    private function userWithRole(string $role, Campus $campus): User
    {
        $user = User::factory()->create([
            'default_campus_id' => $campus->id,
            'name' => ucfirst($role) . ' Test ' . uniqid(),
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
