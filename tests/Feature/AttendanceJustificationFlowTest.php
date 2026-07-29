<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\AttendanceJustification;
use App\Models\Campus;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\PrefectDailyAttendance;
use App\Models\Schedule;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\StudentGroupHistory;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceJustificationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-20 08:00:00'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['coordinator', 'teacher', 'student'] as $role) {
            Role::findOrCreate($role);
        }

        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_coordinator_can_open_justification_form_with_active_cycle_groups(): void
    {
        $scenario = $this->justificationScenario();

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('attendance_justifications.index'))
            ->assertOk()
            ->assertSee('Alta de justificantes')
            ->assertSee($scenario['group']->name)
            ->assertSee($scenario['student']->user->name);
    }

    public function test_coordinator_creates_justification_and_marks_attendance_as_justified(): void
    {
        $scenario = $this->justificationScenario();

        Attendance::create([
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['student']->id,
            'status' => 'absent',
        ]);

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('attendance_justifications.store'), [
                'group_id' => $scenario['group']->id,
                'student_id' => $scenario['student']->id,
                'from_date' => $scenario['session']->session_date->toDateString(),
                'to_date' => $scenario['session']->session_date->toDateString(),
                'reason' => 'Cita medica',
            ])
            ->assertRedirect(route('attendance_justifications.index'));

        $this->assertDatabaseHas('attendance_justifications', [
            'student_id' => $scenario['student']->id,
            'reason' => 'Cita medica',
            'issued_by' => $scenario['coordinatorUser']->id,
        ]);
        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['student']->id,
            'status' => 'justified',
        ]);
        $this->assertTrue(
            PrefectDailyAttendance::query()
                ->where('student_id', $scenario['student']->id)
                ->where('group_id', $scenario['group']->id)
                ->whereDate('attendance_date', $scenario['session']->session_date->toDateString())
                ->where('status', 'justified')
                ->exists()
        );
    }

    public function test_justification_does_not_apply_to_student_from_another_group(): void
    {
        $scenario = $this->justificationScenario();
        $otherGroup = Group::create([
            'level_id' => $scenario['level']->id,
            'name' => '5006',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $otherStudent = $this->studentUser($scenario['campus'], $otherGroup)->student;

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->from(route('attendance_justifications.index'))
            ->post(route('attendance_justifications.store'), [
                'group_id' => $scenario['group']->id,
                'student_id' => $otherStudent->id,
                'from_date' => $scenario['session']->session_date->toDateString(),
                'to_date' => $scenario['session']->session_date->toDateString(),
                'reason' => 'Tramite familiar',
            ])
            ->assertRedirect(route('attendance_justifications.index'))
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseMissing('attendance_justifications', [
            'student_id' => $otherStudent->id,
            'reason' => 'Tramite familiar',
        ]);
    }

    public function test_teacher_cannot_overwrite_attendance_justified_by_coordination(): void
    {
        $scenario = $this->justificationScenario();

        AttendanceJustification::create([
            'student_id' => $scenario['student']->id,
            'from_date' => $scenario['session']->session_date->toDateString(),
            'to_date' => $scenario['session']->session_date->toDateString(),
            'reason' => 'Cita medica',
            'issued_by' => $scenario['coordinatorUser']->id,
            'issued_at' => now(),
        ]);
        Attendance::create([
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['student']->id,
            'status' => 'justified',
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
            'status' => 'justified',
        ]);
    }

    public function test_teacher_cannot_access_coordination_justification_form(): void
    {
        $scenario = $this->justificationScenario();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('attendance_justifications.index'))
            ->assertForbidden();
    }

    private function justificationScenario(): array
    {
        $campus = Campus::create([
            'name' => 'Florida',
            'code' => 'FLORIDA'.uniqid(),
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
        StudentGroupHistory::create([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => null,
            'reason' => 'Alta inicial',
        ]);

        $assignment = TeachingAssignment::create([
            'teacher_id' => $teacherUser->teacher->id,
            'group_id' => $group->id,
            'school_cycle_group_id' => $cycleGroup->id,
            'subject_id' => $subject->id,
            'section_number' => 1,
            'is_active' => true,
        ]);
        $assignment->students()->sync([$student->id]);

        $schedule = Schedule::create([
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
            'session'
        );
    }

    private function userWithRole(string $role, Campus $campus): User
    {
        $user = User::factory()->create(['default_campus_id' => $campus->id]);
        $user->assignRole($role);
        $user->campuses()->sync([$campus->id]);

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
