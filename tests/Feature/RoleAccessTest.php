<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\PaperExam;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['admin', 'coordinator', 'teacher', 'student', 'guardian', 'tutor', 'prefect'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_teacher_cannot_open_another_teacher_assignment(): void
    {
        $scenario = $this->academicScenario();
        $otherScenario = $this->academicScenario([
            'campus' => $scenario['campus'],
            'modality' => $scenario['modality'],
            'level' => $scenario['level'],
            'cycle' => $scenario['cycle'],
            'group_name' => '5002',
        ]);
        $otherAssignment = $otherScenario['assignment'];

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.classes.show', $otherAssignment))
            ->assertForbidden();
    }

    public function test_student_cannot_open_exam_from_another_group(): void
    {
        $scenario = $this->academicScenario();
        $otherGroupScenario = $this->academicScenario([
            'campus' => $scenario['campus'],
            'modality' => $scenario['modality'],
            'level' => $scenario['level'],
            'cycle' => $scenario['cycle'],
            'group_name' => '5002',
        ]);
        $exam = $this->paperExam($otherGroupScenario);

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('student.exams.show', $exam))
            ->assertForbidden();
    }

    public function test_student_role_cannot_access_teacher_classes(): void
    {
        $scenario = $this->academicScenario();

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.classes.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_student_exams(): void
    {
        $this->get(route('student.exams.index'))
            ->assertRedirect(route('login'));
    }

    private function academicScenario(array $overrides = []): array
    {
        $campus = $overrides['campus'] ?? Campus::create([
            'name' => 'Florida',
            'code' => 'FLORIDA'.uniqid(),
            'is_active' => true,
        ]);

        $modality = $overrides['modality'] ?? Modality::create([
            'name' => 'PREPARATORIA',
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

        $cycle = $overrides['cycle'] ?? SchoolCycle::create([
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'name' => 'Preparatoria 2026-2027',
            'code' => 'PREPA-'.uniqid(),
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(10)->toDateString(),
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

        $teacherUser = $this->teacherUser($campus);
        $studentUser = $this->studentUser($campus, $group);
        $assignment = $this->assignment($teacherUser->teacher, compact('group', 'cycleGroup', 'subject', 'cycle'));
        $assignment->students()->syncWithoutDetaching([$studentUser->student->id]);

        return compact(
            'campus',
            'modality',
            'level',
            'group',
            'cycle',
            'cycleGroup',
            'subject',
            'teacherUser',
            'studentUser',
            'assignment'
        );
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

    private function assignment(Teacher $teacher, array $scenario): TeachingAssignment
    {
        return TeachingAssignment::create([
            'teacher_id' => $teacher->id,
            'group_id' => $scenario['group']->id,
            'school_cycle_group_id' => $scenario['cycleGroup']->id,
            'subject_id' => $scenario['subject']->id,
            'section_number' => 1,
            'is_active' => true,
        ]);
    }

    private function paperExam(array $scenario): PaperExam
    {
        return PaperExam::create([
            'created_by' => $scenario['teacherUser']->id,
            'teaching_assignment_id' => $scenario['assignment']->id,
            'school_cycle_id' => $scenario['cycle']->id,
            'title' => 'Primer parcial',
            'duration_minutes' => 50,
            'is_active' => true,
            'is_online_enabled' => true,
            'online_available_from' => now()->subHour(),
            'online_available_until' => now()->addHour(),
            'online_max_attempts' => 1,
        ]);
    }
}
