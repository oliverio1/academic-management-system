<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\PrefectDailyAttendance;
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
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PrefectClassSkipFlowTest extends TestCase
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
    }

    public function test_prefect_menu_links_to_class_skip_alerts(): void
    {
        $scenario = $this->academicScenario();
        $prefect = $this->userWithRole('prefect', $scenario['campus']);

        $this->actingAs($prefect)
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('prefect.groups.index'))
            ->assertOk()
            ->assertSee(route('prefect.class-skips.index'))
            ->assertSee('Alumnos fuera de clase');
    }

    public function test_prefect_sees_students_present_with_prefecture_but_absent_from_class_in_active_campus(): void
    {
        $scenario = $this->academicScenario();
        $otherCampusScenario = $this->academicScenario([
            'campus' => Campus::create([
                'name' => 'Valle',
                'code' => 'VALLE'.uniqid(),
                'is_active' => true,
            ]),
            'group_name' => '5002',
            'subject_name' => 'Geografia',
            'student_name' => 'Alumno Otro Campus',
        ]);

        $this->registerClassSkip($scenario);
        $this->registerClassSkip($otherCampusScenario);

        $prefect = $this->userWithRole('prefect', $scenario['campus']);

        $this->actingAs($prefect)
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('prefect.class-skips.index'))
            ->assertOk()
            ->assertSee($scenario['studentUser']->name)
            ->assertSee('Quimica III')
            ->assertDontSee('Alumno Otro Campus')
            ->assertDontSee('Ver alumno');
    }

    public function test_coordination_keeps_student_summary_action_on_class_skip_alerts(): void
    {
        $scenario = $this->academicScenario();
        $this->registerClassSkip($scenario);

        $coordinator = $this->userWithRole('coordinator', $scenario['campus']);

        $this->actingAs($coordinator)
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.students.class-skips'))
            ->assertOk()
            ->assertSee($scenario['studentUser']->name)
            ->assertSee('Ver alumno');
    }

    private function registerClassSkip(array $scenario): void
    {
        $date = '2026-09-15';

        PrefectDailyAttendance::create([
            'group_id' => $scenario['group']->id,
            'student_id' => $scenario['studentUser']->student->id,
            'attendance_date' => $date,
            'status' => 'present',
            'recorded_by' => $scenario['teacherUser']->id,
        ]);

        Attendance::create([
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['studentUser']->student->id,
            'status' => 'absent',
        ]);
    }

    private function academicScenario(array $overrides = []): array
    {
        $this->createTenantForCurrentAppUrl();

        $campus = $overrides['campus'] ?? Campus::create([
            'name' => 'Florida',
            'code' => 'FLORIDA'.uniqid(),
            'is_active' => true,
        ]);

        $modality = $overrides['modality'] ?? Modality::create([
            'name' => 'PREPARATORIA'.uniqid(),
            'is_active' => true,
        ]);

        $level = $overrides['level'] ?? Level::create([
            'modality_id' => $modality->id,
            'name' => 'Quinto',
            'is_active' => true,
        ]);

        $group = Group::create([
            'level_id' => $level->id,
            'name' => $overrides['group_name'] ?? '5001',
            'capacity' => 30,
            'is_active' => true,
        ]);

        $cycle = SchoolCycle::create([
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'name' => 'Preparatoria 2026-2027 '.uniqid(),
            'code' => 'PREPA-'.uniqid(),
            'start_date' => '2026-08-10',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $period = AcademicPeriod::create([
            'modality_id' => $modality->id,
            'name' => 'Primer parcial',
            'code' => 'P1-'.uniqid(),
            'start_date' => '2026-08-10',
            'end_date' => '2026-10-10',
            'is_active' => true,
        ]);

        $cycleGroup = SchoolCycleGroup::create([
            'school_cycle_id' => $cycle->id,
            'group_id' => $group->id,
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'section_count' => 1,
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'level_id' => $level->id,
            'name' => $overrides['subject_name'] ?? 'Quimica III',
            'hours_per_week' => 3,
            'type' => Subject::TYPE_THEORETICAL,
            'is_active' => true,
        ]);

        $cycleGroup->subjects()->syncWithoutDetaching([$subject->id]);

        $teacherUser = $this->teacherUser($campus);
        $studentUser = $this->studentUser($campus, $group, $overrides['student_name'] ?? 'Alumno Alerta');

        $assignment = TeachingAssignment::create([
            'teacher_id' => $teacherUser->teacher->id,
            'group_id' => $group->id,
            'school_cycle_group_id' => $cycleGroup->id,
            'subject_id' => $subject->id,
            'section_number' => 1,
            'is_active' => true,
        ]);

        $assignment->students()->syncWithoutDetaching([$studentUser->student->id]);

        $schedule = Schedule::create([
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'day_of_week' => 'martes',
            'start_time' => '07:00:00',
            'end_time' => '07:50:00',
            'type' => 'class',
            'is_active' => true,
        ]);

        $session = AcademicSession::create([
            'teaching_assignment_id' => $assignment->id,
            'schedule_id' => $schedule->id,
            'academic_period_id' => $period->id,
            'session_date' => '2026-09-15',
            'start_time' => '07:00:00',
            'end_time' => '07:50:00',
            'is_cancelled' => false,
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
            'studentUser',
            'assignment',
            'schedule',
            'session'
        );
    }

    private function userWithRole(string $role, Campus $campus, ?string $name = null): User
    {
        $user = User::factory()->create([
            'name' => $name ?? ucfirst($role).' Usuario',
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

    private function studentUser(Campus $campus, Group $group, string $name): User
    {
        $user = $this->userWithRole('student', $campus, $name);
        Student::create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'enrollment_number' => 'U'.random_int(100000, 999999),
            'is_active' => true,
        ]);

        return $user->refresh()->load('student');
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
