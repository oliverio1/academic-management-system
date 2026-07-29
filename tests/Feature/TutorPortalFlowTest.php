<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\EvaluationCriterion;
use App\Models\Finance\FinanceCharge;
use App\Models\Finance\FinanceConcept;
use App\Models\Finance\FinancePayment;
use App\Models\Finance\FinancePaymentApplication;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\Schedule;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\StudentFollowUp;
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

class TutorPortalFlowTest extends TestCase
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

    public function test_tutor_sees_only_assigned_student_subjects(): void
    {
        $scenario = $this->tutorScenario();

        $this->actingAs($scenario['tutorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('tutor.subjects'))
            ->assertOk()
            ->assertSee($scenario['studentUser']->name)
            ->assertSee('Quimica III')
            ->assertSee($scenario['teacherUser']->name)
            ->assertDontSee($scenario['otherStudentUser']->name)
            ->assertDontSee('Geografia');
    }

    public function test_tutor_can_view_assigned_student_subject_detail_with_attendance_and_grades(): void
    {
        $scenario = $this->tutorScenario();

        $this->actingAs($scenario['tutorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('tutor.subjects.show', $scenario['assignment']))
            ->assertOk()
            ->assertSee($scenario['studentUser']->name)
            ->assertSee('Quimica III')
            ->assertSee('Asistencia')
            ->assertSee('Reporte de laboratorio')
            ->assertSee('9.3')
            ->assertDontSee($scenario['otherStudentUser']->name)
            ->assertDontSee('Actividad ajena');
    }

    public function test_tutor_cannot_view_another_student_assignment_detail(): void
    {
        $scenario = $this->tutorScenario();

        $this->actingAs($scenario['tutorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('tutor.subjects.show', $scenario['otherAssignment']))
            ->assertForbidden();
    }

    public function test_tutor_sees_only_assigned_student_follow_ups(): void
    {
        $scenario = $this->tutorScenario();

        $this->actingAs($scenario['tutorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('tutor.followups'))
            ->assertOk()
            ->assertSee('Revisar desempeno en laboratorio.')
            ->assertDontSee('Seguimiento de alumno ajeno.');
    }

    public function test_tutor_sees_only_assigned_student_account_statement(): void
    {
        $scenario = $this->tutorScenario();

        $this->actingAs($scenario['tutorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('tutor.account-statement'))
            ->assertOk()
            ->assertSee('Colegiatura julio')
            ->assertSee('$1,500.00')
            ->assertSee('$500.00')
            ->assertSee('$1,000.00')
            ->assertDontSee('Cargo ajeno');
    }

    public function test_unassigned_tutor_sees_unassigned_portal_message(): void
    {
        $scenario = $this->tutorScenario();
        $unassignedTutor = $this->userWithRole('guardian', $scenario['campus']);

        $this->actingAs($unassignedTutor)
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('tutor.subjects'))
            ->assertOk()
            ->assertSee('No tienes un alumno asociado');
    }

    private function tutorScenario(): array
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
        $period = AcademicPeriod::create([
            'modality_id' => $modality->id,
            'name' => 'Primer parcial',
            'code' => 'P1'.uniqid(),
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
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

        $tutorUser = $this->userWithRole('guardian', $campus);
        $teacherUser = $this->teacherUser($campus);
        $otherTeacherUser = $this->teacherUser($campus);
        $coordinatorUser = $this->userWithRole('coordinator', $campus);
        $studentUser = $this->studentUser($campus, $group, $tutorUser);
        $otherStudentUser = $this->studentUser($campus, $otherGroup);

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

        $schedule = Schedule::create([
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

        $session = AcademicSession::create([
            'teaching_assignment_id' => $assignment->id,
            'schedule_id' => $schedule->id,
            'academic_period_id' => $period->id,
            'session_date' => now()->subDays(2)->toDateString(),
            'start_time' => '07:00:00',
            'end_time' => '07:50:00',
            'is_cancelled' => false,
        ]);
        Attendance::create([
            'academic_session_id' => $session->id,
            'student_id' => $studentUser->student->id,
            'status' => 'present',
        ]);

        $criterion = EvaluationCriterion::create([
            'teaching_assignment_id' => $assignment->id,
            'name' => 'Entregables',
            'percentage' => 30,
        ]);
        $otherCriterion = EvaluationCriterion::create([
            'teaching_assignment_id' => $otherAssignment->id,
            'name' => 'Entregables',
            'percentage' => 30,
        ]);

        $activity = Activity::create([
            'teaching_assignment_id' => $assignment->id,
            'evaluation_criterion_id' => $criterion->id,
            'academic_period_id' => $period->id,
            'title' => 'Reporte de laboratorio',
            'description' => 'Entrega del reporte.',
            'max_score' => 10,
            'due_date' => now()->addWeek()->toDateString(),
            'is_active' => true,
        ]);
        Grade::create([
            'activity_id' => $activity->id,
            'student_id' => $studentUser->student->id,
            'score' => 9.3,
            'comments' => 'Muy bien.',
        ]);

        $otherActivity = Activity::create([
            'teaching_assignment_id' => $otherAssignment->id,
            'evaluation_criterion_id' => $otherCriterion->id,
            'academic_period_id' => $period->id,
            'title' => 'Actividad ajena',
            'description' => 'No debe mostrarse.',
            'max_score' => 10,
            'due_date' => now()->addWeek()->toDateString(),
            'is_active' => true,
        ]);
        Grade::create([
            'activity_id' => $otherActivity->id,
            'student_id' => $otherStudentUser->student->id,
            'score' => 10,
        ]);

        StudentFollowUp::create([
            'student_id' => $studentUser->student->id,
            'requested_by' => $coordinatorUser->id,
            'type' => 'academic',
            'message' => 'Revisar desempeno en laboratorio.',
            'status' => 'open',
        ]);
        StudentFollowUp::create([
            'student_id' => $otherStudentUser->student->id,
            'requested_by' => $coordinatorUser->id,
            'type' => 'academic',
            'message' => 'Seguimiento de alumno ajeno.',
            'status' => 'open',
        ]);

        $concept = FinanceConcept::create([
            'tenant_id' => $this->tenantId,
            'campus_id' => $campus->id,
            'code' => 'COL',
            'name' => 'Colegiatura',
            'default_amount' => 1500,
            'is_active' => true,
        ]);
        $charge = FinanceCharge::create([
            'tenant_id' => $this->tenantId,
            'campus_id' => $campus->id,
            'school_cycle_id' => $cycle->id,
            'student_id' => $studentUser->student->id,
            'concept_id' => $concept->id,
            'reference' => 'COL-5005-01',
            'description' => 'Colegiatura julio',
            'amount' => 1500,
            'due_date' => now()->addDays(5)->toDateString(),
            'issued_at' => now(),
            'status' => 'partial',
            'created_by' => $coordinatorUser->id,
        ]);
        $payment = FinancePayment::create([
            'tenant_id' => $this->tenantId,
            'campus_id' => $campus->id,
            'student_id' => $studentUser->student->id,
            'payment_date' => now(),
            'amount' => 500,
            'method' => 'transfer',
            'reference' => 'PAY-01',
            'status' => 'applied',
            'created_by' => $coordinatorUser->id,
        ]);
        FinancePaymentApplication::create([
            'tenant_id' => $this->tenantId,
            'payment_id' => $payment->id,
            'charge_id' => $charge->id,
            'applied_amount' => 500,
            'applied_at' => now(),
            'created_by' => $coordinatorUser->id,
        ]);
        FinanceCharge::create([
            'tenant_id' => $this->tenantId,
            'campus_id' => $campus->id,
            'school_cycle_id' => $cycle->id,
            'student_id' => $otherStudentUser->student->id,
            'concept_id' => $concept->id,
            'reference' => 'COL-5006-01',
            'description' => 'Cargo ajeno',
            'amount' => 9999,
            'due_date' => now()->addDays(5)->toDateString(),
            'issued_at' => now(),
            'status' => 'pending',
            'created_by' => $coordinatorUser->id,
        ]);

        return compact(
            'campus',
            'modality',
            'level',
            'group',
            'otherGroup',
            'cycle',
            'period',
            'subject',
            'otherSubject',
            'tutorUser',
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

    private function studentUser(Campus $campus, Group $group, ?User $guardian = null): User
    {
        $user = $this->userWithRole('student', $campus);
        Student::create([
            'user_id' => $user->id,
            'guardian_user_id' => $guardian?->id,
            'group_id' => $group->id,
            'enrollment_number' => 'U'.random_int(100000, 999999),
            'is_active' => true,
        ]);

        return $user->refresh()->load('student.user');
    }
}
